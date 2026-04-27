import time
import logging
from typing import Dict, Optional, Any
from dataclasses import dataclass, field
from collections import defaultdict
import threading
import hashlib

logger = logging.getLogger(__name__)


@dataclass
class RateLimitConfig:
    requests: int
    window_seconds: int
    burst_allowance: int = 0


@dataclass
class RateLimitResult:
    allowed: bool
    remaining: int
    reset_time: int
    retry_after: Optional[int] = None


class InMemoryRateLimiter:
    def __init__(self):
        self._store: Dict[str, list] = defaultdict(list)
        self._lock = threading.Lock()
    
    def _get_user_key(self, identifier: str, limit_type: str) -> str:
        return f"{limit_type}:{identifier}"
    
    def check(
        self, 
        identifier: str, 
        limit: RateLimitConfig,
        limit_type: str = "api"
    ) -> RateLimitResult:
        key = self._get_user_key(identifier, limit_type)
        current_time = time.time()
        
        with self._lock:
            if key in self._store:
                self._store[key] = [
                    t for t in self._store[key]
                    if current_time - t < limit.window_seconds
                ]
            
            request_count = len(self._store[key])
            
            max_requests = limit.requests
            
            if limit.burst_allowance > 0:
                max_requests += limit.burst_allowance
            
            allowed = request_count < max_requests
            
            if allowed:
                self._store[key].append(current_time)
                remaining = max_requests - request_count - 1
            else:
                oldest = min(self._store[key])
                reset_time = int(oldest + limit.window_seconds)
                retry_after = reset_time - int(current_time)
                
                return RateLimitResult(
                    allowed=False,
                    remaining=0,
                    reset_time=reset_time,
                    retry_after=retry_after
                )
            
            reset_time = int(current_time + limit.window_seconds)
            
            return RateLimitResult(
                allowed=True,
                remaining=remaining,
                reset_time=reset_time
            )
    
    def reset(self, identifier: str, limit_type: str = "api"):
        key = self._get_user_key(identifier, limit_type)
        with self._lock:
            if key in self._store:
                del self._store[key]


class TokenBucketRateLimiter:
    def __init__(self, capacity: int, refill_rate: float):
        self.capacity = capacity
        self.refill_rate = refill_rate
        self._buckets: Dict[str, Dict[str, Any]] = {}
        self._lock = threading.Lock()
    
    def _get_bucket(self, identifier: str) -> Dict[str, Any]:
        if identifier not in self._buckets:
            self._buckets[identifier] = {
                "tokens": self.capacity,
                "last_refill": time.time()
            }
        
        bucket = self._buckets[identifier]
        current_time = time.time()
        
        elapsed = current_time - bucket["last_refill"]
        refill_amount = elapsed * self.refill_rate
        
        bucket["tokens"] = min(self.capacity, bucket["tokens"] + refill_amount)
        bucket["last_refill"] = current_time
        
        return bucket
    
    def consume(self, identifier: str, cost: int = 1) -> bool:
        with self._lock:
            bucket = self._get_bucket(identifier)
            
            if bucket["tokens"] >= cost:
                bucket["tokens"] -= cost
                return True
            
            return False


class RateLimiterFactory:
    @staticmethod
    def create_limiter(limit_type: str) -> InMemoryRateLimiter:
        return InMemoryRateLimiter()


class RateLimitService:
    DEFAULT_CONFIGS = {
        "api": RateLimitConfig(requests=60, window_seconds=60, burst_allowance=10),
        "chat": RateLimitConfig(requests=30, window_seconds=60, burst_allowance=5),
        "upload": RateLimitConfig(requests=10, window_seconds=60),
        "train": RateLimitConfig(requests=5, window_seconds=300),
    }
    
    def __init__(self):
        self.limiters: Dict[str, InMemoryRateLimiter] = {}
        self.api_key_limiters: Dict[str, TokenBucketRateLimiter] = {}
        self._init_default_limiters()
    
    def _init_default_limiters(self):
        for limit_type in self.DEFAULT_CONFIGS.keys():
            self.limiters[limit_type] = RateLimiterFactory.create_limiter(limit_type)
    
    def check_rate_limit(
        self,
        identifier: str,
        limit_type: str = "api",
        custom_config: Optional[RateLimitConfig] = None
    ) -> RateLimitResult:
        if limit_type not in self.limiters:
            limit_type = "api"
        
        config = custom_config or self.DEFAULT_CONFIGS.get(limit_type, self.DEFAULT_CONFIGS["api"])
        
        limiter = self.limiters[limit_type]
        
        return limiter.check(identifier, config, limit_type)
    
    def check_api_key_limit(
        self,
        api_key: str,
        requests_per_minute: int = 60
    ) -> bool:
        key_hash = hashlib.sha256(api_key.encode()).hexdigest()
        
        if key_hash not in self.api_key_limiters:
            self.api_key_limiters[key_hash] = TokenBucketRateLimiter(
                capacity=requests_per_minute,
                refill_rate=requests_per_minute / 60.0
            )
        
        return self.api_key_limiters[key_hash].consume(key_hash)
    
    def get_client_ip(self, request) -> str:
        x_forwarded_for = request.headers.get("X-Forwarded-For")
        if x_forwarded_for:
            return x_forwarded_for.split(",")[0].strip()
        
        x_real_ip = request.headers.get("X-Real-IP")
        if x_real_ip:
            return x_real_ip
        
        return request.client.host if request.client else "unknown"
    
    def reset_limit(self, identifier: str, limit_type: str = "api"):
        if limit_type in self.limiters:
            self.limiters[limit_type].reset(identifier, limit_type)


rate_limiter = RateLimitService()