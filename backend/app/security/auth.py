from fastapi import Request, HTTPException, Depends, status
from fastapi.security import APIKeyHeader
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.responses import JSONResponse
import time
import hmac
import hashlib
import logging
from typing import Optional, Dict, Any
from app.core.config import settings

logger = logging.getLogger(__name__)

API_KEY_HEADER = APIKeyHeader(name="X-API-Key", auto_error=False)
REQUEST_TIMESTAMP_HEADER = APIKeyHeader(name="X-Request-Timestamp", auto_error=False)
SIGNATURE_HEADER = APIKeyHeader(name="X-WP-Signature", auto_error=False)


class SecurityEventLogger:
    def __init__(self):
        self.logger = logging.getLogger("security")
        self.logger.setLevel(logging.INFO)
        
        if not self.logger.handlers:
            handler = logging.FileHandler("logs/security.log")
            handler.setFormatter(logging.Formatter(
                "%(asctime)s - %(levelname)s - %(message)s"
            ))
            self.logger.addHandler(handler)
    
    def log(self, event_type: str, details: Dict[str, Any], severity: str = "INFO"):
        log_entry = {
            "event": event_type,
            "details": details,
            "severity": severity,
            "timestamp": time.time()
        }
        
        if severity == "WARNING":
            self.logger.warning(str(log_entry))
        elif severity == "CRITICAL":
            self.logger.critical(str(log_entry))
        else:
            self.logger.info(str(log_entry))
    
    def log_auth_failure(self, api_key_prefix: str, reason: str):
        self.log("AUTH_FAILURE", {"key_prefix": api_key_prefix, "reason": reason}, "WARNING")
    
    def log_rate_limit_exceeded(self, identifier: str, limit: int):
        self.log("RATE_LIMIT_EXCEEDED", {"identifier": identifier, "limit": limit}, "WARNING")
    
    def log_prompt_injection_attempt(self, user_input: str, detected_pattern: str):
        self.log("PROMPT_INJECTION_ATTEMPT", {
            "pattern": detected_pattern,
            "input_length": len(user_input),
            "input_preview": user_input[:200]
        }, "WARNING")
    
    def log_suspicious_request(self, ip: str, reason: str):
        self.log("SUSPICIOUS_REQUEST", {"ip": ip, "reason": reason}, "WARNING")


security_logger = SecurityEventLogger()


class AuthService:
    VALID_API_KEYS: Dict[str, Dict[str, Any]] = {}
    
    @classmethod
    def add_api_key(cls, key: str, name: str, permissions: list, rate_limit: int = 60):
        key_hash = hashlib.sha256(key.encode()).hexdigest()
        cls.VALID_API_KEYS[key_hash] = {
            "name": name,
            "permissions": permissions,
            "rate_limit": rate_limit,
            "created_at": time.time()
        }
    
    @classmethod
    def validate_api_key(cls, api_key: Optional[str]) -> Optional[Dict[str, Any]]:
        if not api_key:
            return None
        
        key_hash = hashlib.sha256(api_key.encode()).hexdigest()
        return cls.VALID_API_KEYS.get(key_hash)
    
    @classmethod
    def get_rate_limit(cls, api_key: Optional[str]) -> int:
        key_data = cls.validate_api_key(api_key)
        if key_data:
            return key_data.get("rate_limit", 60)
        
        api_key_from_config = settings.OPENAI_API_KEY or settings.ANTHROPIC_API_KEY
        if api_key_from_config:
            return 60
        
        return 30


def get_api_key_data(api_key: str = Depends(API_KEY_HEADER)) -> Dict[str, Any]:
    key_data = AuthService.validate_api_key(api_key)
    
    if not key_data:
        security_logger.log_auth_failure(api_key[:8] if api_key else "none", "Invalid API key")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing API key"
        )
    
    return key_data


def validate_request_timestamp(timestamp_header: Optional[str] = Depends(REQUEST_TIMESTAMP_HEADER)):
    if not timestamp_header:
        return
    
    try:
        request_time = int(timestamp_header)
        current_time = int(time.time())
        
        if abs(current_time - request_time) > 300:
            raise HTTPException(
                status_code=status.HTTP_408_REQUEST_TIMEOUT,
                detail="Request timestamp expired"
            )
    except ValueError:
        pass


def validate_signature(
    api_key: str = Depends(API_KEY_HEADER),
    timestamp: str = Depends(REQUEST_TIMESTAMP_HEADER),
    signature: Optional[str] = Depends(SIGNATURE_HEADER),
    require_signature: bool = False
):
    if not require_signature or not signature:
        return
    
    api_key_data = AuthService.validate_api_key(api_key)
    if not api_key_data or "admin" not in api_key_data.get("permissions", []):
        return
    
    message = f"{timestamp}:{api_key}"
    expected_signature = hmac.new(
        settings.OPENAI_API_KEY.encode(),
        message.encode(),
        hashlib.sha256
    ).hexdigest()
    
    if not hmac.compare_digest(signature, expected_signature):
        security_logger.log_suspicious_request("unknown", "Invalid HMAC signature")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid signature"
        )


class RateLimitMiddleware(BaseHTTPMiddleware):
    def __init__(self, app, redis_url: Optional[str] = None):
        super().__init__(app)
        self.redis_url = redis_url
        self.memory_store: Dict[str, list] = {}
    
    async def dispatch(self, request: Request, call_next):
        client_ip = request.client.host if request.client else "unknown"
        
        key = f"rate_limit:{client_ip}"
        current_time = time.time()
        
        if key not in self.memory_store:
            self.memory_store[key] = []
        
        self.memory_store[key] = [
            t for t in self.memory_store[key]
            if current_time - t < 60
        ]
        
        if len(self.memory_store[key]) >= 60:
            security_logger.log_rate_limit_exceeded(client_ip, 60)
            return JSONResponse(
                status_code=status.HTTP_429_TOO_MANY_REQUESTS,
                content={"detail": "Rate limit exceeded. Try again later."}
            )
        
        self.memory_store[key].append(current_time)
        
        response = await call_next(request)
        
        response.headers["X-RateLimit-Limit"] = "60"
        response.headers["X-RateLimit-Remaining"] = str(60 - len(self.memory_store[key]))
        
        return response


class SecurityHeadersMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        response = await call_next(request)
        
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["X-XSS-Protection"] = "1; mode=block"
        response.headers["Referrer-Policy"] = "strict-origin-when-cross-origin"
        response.headers["Permissions-Policy"] = "geolocation=(), microphone=(), camera=()"
        
        if settings.CORS_ORIGINS != ["*"]:
            origin = request.headers.get("origin")
            if origin in settings.CORS_ORIGINS:
                response.headers["Access-Control-Allow-Origin"] = origin
        
        return response


def require_permissions(allowed_permissions: list):
    def dependency(key_data: Dict = Depends(get_api_key_data)):
        key_permissions = key_data.get("permissions", [])
        
        if not any(perm in key_permissions for perm in allowed_permissions):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Insufficient permissions"
            )
        
        return key_data
    
    return dependency