import pytest
import time
import sys
import os

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))


from app.security.rate_limiter import (
    InMemoryRateLimiter, 
    RateLimitConfig, 
    RateLimitResult,
    RateLimitService,
    TokenBucketRateLimiter
)


class TestInMemoryRateLimiter:

    @pytest.fixture
    def limiter(self):
        return InMemoryRateLimiter()

    def test_allows_requests_under_limit(self, limiter):
        config = RateLimitConfig(requests=5, window_seconds=60)
        
        for _ in range(5):
            result = limiter.check("user1", config, "test")
            assert result.allowed is True

    def test_blocks_requests_over_limit(self, limiter):
        config = RateLimitConfig(requests=2, window_seconds=60)
        
        limiter.check("user1", config, "test")
        limiter.check("user1", config, "test")
        
        result = limiter.check("user1", config, "test")
        
        assert result.allowed is False
        assert result.retry_after is not None
        assert result.retry_after > 0

    def test_different_users_independent(self, limiter):
        config = RateLimitConfig(requests=1, window_seconds=60)
        
        result1 = limiter.check("user1", config, "test")
        result2 = limiter.check("user2", config, "test")
        
        assert result1.allowed is True
        assert result2.allowed is True

    def test_different_limit_types_independent(self, limiter):
        config_api = RateLimitConfig(requests=1, window_seconds=60)
        config_chat = RateLimitConfig(requests=1, window_seconds=60)
        
        limiter.check("user1", config_api, "api")
        result2 = limiter.check("user1", config_chat, "chat")
        
        assert result2.allowed is True

    def test_burst_allowance(self, limiter):
        config = RateLimitConfig(requests=2, window_seconds=60, burst_allowance=2)
        
        for _ in range(4):
            result = limiter.check("user1", config, "test")
            assert result.allowed is True
        
        result = limiter.check("user1", config, "test")
        assert result.allowed is False


class TestTokenBucketRateLimiter:

    def test_consume_within_capacity(self):
        limiter = TokenBucketRateLimiter(capacity=5, refill_rate=1)
        
        assert limiter.consume("user1", 1) is True
        assert limiter.consume("user1", 1) is True
        assert limiter.consume("user1", 1) is True

    def test_reject_when_empty(self):
        limiter = TokenBucketRateLimiter(capacity=2, refill_rate=1)
        
        limiter.consume("user1", 1)
        limiter.consume("user1", 1)
        
        assert limiter.consume("user1", 1) is False
        assert limiter.consume("user1", 1) is False

    def test_refill_over_time(self):
        limiter = TokenBucketRateLimiter(capacity=2, refill_rate=2)
        
        limiter.consume("user1", 2)
        assert limiter.consume("user1", 1) is False
        
        time.sleep(1)
        assert limiter.consume("user1", 1) is True


class TestRateLimitService:

    @pytest.fixture
    def service(self):
        return RateLimitService()

    def test_default_api_limit(self, service):
        result = service.check_rate_limit("127.0.0.1", "api")
        
        assert result.allowed is True
        assert result.remaining >= 0

    def test_chat_limit_separate(self, service):
        api_result = service.check_rate_limit("127.0.0.1", "api")
        chat_result = service.check_rate_limit("127.0.0.1", "chat")
        
        assert api_result.remaining != chat_result.remaining

    def test_get_client_ip_from_x_forwarded_for(self, service):
        class MockRequest:
            headers = {"X-Forwarded-For": "1.2.3.4, 5.6.7.8"}
            client = None
        
        ip = service.get_client_ip(MockRequest())
        assert ip == "1.2.3.4"

    def test_get_client_ip_fallback(self, service):
        class MockRequest:
            headers = {}
            client = Mock()
            client.host = "192.168.1.1"
        
        ip = service.get_client_ip(MockRequest())
        assert ip == "192.168.1.1"


class TestRateLimiterEdgeCases:

    def test_rapid_requests(self):
        limiter = InMemoryRateLimiter()
        config = RateLimitConfig(requests=10, window_seconds=1)
        
        for i in range(15):
            result = limiter.check(f"user_{i}", config, "test")
            if i < 14:
                assert result.allowed is True
            else:
                assert result.allowed is False

    def test_reset_limit(self):
        limiter = InMemoryRateLimiter()
        config = RateLimitConfig(requests=1, window_seconds=60)
        
        limiter.check("user1", config, "test")
        result = limiter.check("user1", config, "test")
        assert result.allowed is False
        
        limiter.reset_limit("user1", "test")
        result = limiter.check("user1", config, "test")
        assert result.allowed is True

    def test_window_expiry(self):
        limiter = InMemoryRateLimiter()
        config = RateLimitConfig(requests=1, window_seconds=1)
        
        limiter.check("user1", config, "test")
        result = limiter.check("user1", config, "test")
        assert result.allowed is False
        
        time.sleep(1.1)
        
        result = limiter.check("user1", config, "test")
        assert result.allowed is True