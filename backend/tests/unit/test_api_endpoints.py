import pytest
from fastapi.testclient import TestClient
from unittest.mock import Mock, patch, AsyncMock
import sys
import os

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from main import app


@pytest.fixture
def client():
    return TestClient(app)


@pytest.fixture
def mock_vector_store():
    mock = Mock()
    mock.search = AsyncMock(return_value=[
        {"text": "Our return policy is 30 days.", "metadata": {"source": "faq"}, "score": 0.9}
    ])
    mock.add_to_memory = Mock()
    mock.get_memory = Mock(return_value=[])
    return mock


@pytest.fixture
def mock_chat_service():
    with patch('app.services.chat_service.ChatService') as mock_cls:
        mock_instance = Mock()
        mock_instance.process_message = AsyncMock(return_value={
            "answer": "Our return policy is 30 days.",
            "session_id": "test-session",
            "sources": [{"source": "faq"}],
            "metadata": {"chunks_used": 1}
        })
        mock_cls.return_value = mock_instance
        yield mock_instance


@pytest.fixture
def authenticated_headers():
    return {"X-API-Key": "test-api-key-12345"}


class TestChatEndpoint:

    def test_chat_requires_api_key(self, client):
        response = client.post("/api/v1/chat", json={"message": "Hello"})
        assert response.status_code == 401

    def test_chat_rejects_empty_message(self, client, authenticated_headers):
        response = client.post(
            "/api/v1/chat",
            json={"message": ""},
            headers=authenticated_headers
        )
        assert response.status_code == 422

    def test_chat_accepts_valid_message(self, client, authenticated_headers, mock_chat_service):
        response = client.post(
            "/api/v1/chat",
            json={"message": "What is your return policy?"},
            headers=authenticated_headers
        )
        assert response.status_code == 200
        data = response.json()
        assert "answer" in data
        assert "session_id" in data
        assert "sources" in data

    def test_chat_returns_session_id(self, client, authenticated_headers, mock_chat_service):
        response = client.post(
            "/api/v1/chat",
            json={"message": "Hello"},
            headers=authenticated_headers
        )
        data = response.json()
        assert data["session_id"] is not None
        assert len(data["session_id"]) > 0

    def test_chat_preserves_session(self, client, authenticated_headers, mock_chat_service):
        session_id = "existing-session-123"
        
        client.post(
            "/api/v1/chat",
            json={"message": "First message", "session_id": session_id},
            headers=authenticated_headers
        )
        
        response = client.post(
            "/api/v1/chat",
            json={"message": "Second message", "session_id": session_id},
            headers=authenticated_headers
        )
        
        data = response.json()
        assert data["session_id"] == session_id


class TestHealthEndpoint:

    def test_health_check(self, client):
        response = client.get("/health")
        assert response.status_code == 200
        assert response.json()["status"] == "healthy"

    def test_root_endpoint(self, client):
        response = client.get("/")
        assert response.status_code == 200
        assert "name" in response.json()


class TestRateLimiting:

    def test_rate_limit_header_present(self, client, authenticated_headers):
        for _ in range(5):
            response = client.post(
                "/api/v1/chat",
                json={"message": "Test"},
                headers=authenticated_headers
            )
        
        assert "X-RateLimit-Limit" in response.headers
        assert "X-RateLimit-Remaining" in response.headers


class TestInputValidation:

    def test_chat_rejects_missing_message(self, client, authenticated_headers):
        response = client.post(
            "/api/v1/chat",
            json={},
            headers=authenticated_headers
        )
        assert response.status_code == 422

    def test_chat_rejects_non_string_message(self, client, authenticated_headers):
        response = client.post(
            "/api/v1/chat",
            json={"message": 12345},
            headers=authenticated_headers
        )
        assert response.status_code == 422

    def test_chat_rejects_very_long_message(self, client, authenticated_headers):
        long_message = "A" * 10001
        response = client.post(
            "/api/v1/chat",
            json={"message": long_message},
            headers=authenticated_headers
        )
        assert response.status_code in [422, 429]


class TestCORS:

    def test_cors_headers_absent_for_wildcard(self, client):
        response = client.options(
            "/api/v1/chat",
            headers={"Origin": "http://evil.com", "Access-Control-Request-Method": "POST"}
        )
        assert "Access-Control-Allow-Origin" not in response.headers or response.headers.get("Access-Control-Allow-Origin") != "*"