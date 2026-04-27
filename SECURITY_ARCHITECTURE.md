# AI Smart Chatbot - Security Architecture

## Overview
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SECURITY ARCHITECTURE                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────┐     ┌──────────────┐     ┌──────────────┐                │
│  │   CLIENTS   │     │  WORDPRESS   │     │    BACKEND   │                │
│  │  (Browser)  │     │   PLUGIN     │     │   (FastAPI)  │                │
│  └──────┬───────┘     └──────┬───────┘     └──────┬───────┘                │
│         │                    │                    │                         │
│         │                    │                    │                         │
│         ▼                    ▼                    ▼                         │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                     SECURITY LAYERS                                  │   │
│  ├──────────────────────────────────────────────────────────────────────┤   │
│  │                                                                       │   │
│  │  1. INPUT VALIDATION          - Sanitization                         │   │
│  │  2. AUTHENTICATION            - API Keys + JWT                        │   │
│  │  3. RATE LIMITING             - Per-IP, Per-Key, Per-Session        │   │
│  │  4. PROMPT INJECTION DEFENSE  - Pattern Detection + Filtering        │   │
│  │  5. RAG SECURITY LAYER        - Content Cleaning + Isolation        │   │
│  │  6. WORDPRESS HARDENING       - Nonces + Escaping                   │   │
│  │  7. MONITORING                - Logging + Alerting                   │   │
│  │  8. SECRETS MANAGEMENT       - Environment Variables Only            │   │
│  │                                                                       │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘

                              DATA FLOW (SECURE)
                              ==================

    ┌─────────┐    1. HTTPS    ┌─────────────┐    2. API Key    ┌───────────┐
    │ Browser │ ───────────►  │ WordPress   │ ────────��─────► │  Backend  │
    └─────────┘                │   Plugin    │                 │   API     │
                               └─────────────┘                 └─────┬─────┘
                                     │                               │
                                     │                               │
                                     ▼                               ▼
                               ┌─────────────┐              ┌─────────────────┐
                               │  CSRF Nonce │              │  RATE LIMIT     │
                               │  Validation │              │  Middleware     │
                               └─────────────┘              └────────┬────────┘
                                                                        │
                                                                        ▼
                                                                ┌───────────────┐
                                                                │  INPUT        │
                                                                │  SANITIZATION │
                                                                └────────┬──────┘
                                                                         │
                                                                         ▼
                                                          ┌──────────────────────┐
                                                          │  PROMPT INJECTION    │
                                                          │  PROTECTION LAYER   │
                                                          └───────────┬──────────┘
                                                                      │
                                                                      ▼
                                                          ┌──────────────────────┐
                                                          │  USER INPUT          │
                                                          │  (Cleaned + Checked) │
                                                          └───────────┬──────────┘
                                                                      │
                                                                      ▼
                                                          ┌──────────────────────┐
                                                          │  RAG PIPELINE        │
                                                          │  (Content Filtered) │
                                                          └───────────┬──────────┘
                                                                      │
                                                                      ▼
                                                          ┌──────────────────────┐
                                                          │  VECTOR DATABASE     │
                                                          │  (Isolated Namespace)│
                                                          └──────────────────────┘
                                                                      │
                                                                      ▼
                                                          ┌──────────────────────┐
                                                          │  AI PROVIDER         │
                                                          │  (API Key Protected) │
                                                          └──────────────────────┘
```

## Threat Coverage Matrix

| THREAT                    | PROTECTION MECHANISM                          |
|---------------------------|----------------------------------------------|
| Prompt Injection          | Pattern detection, system prompt isolation   |
| RAG Poisoning             | Content sanitization, HTML stripping         |
| API Abuse                 | Rate limiting, API key auth, request expiry  |
| WordPress XSS             | Output escaping, nonce validation            |
| WordPress CSRF           | WordPress nonces, admin post validation     |
| Data Leakage              | No secrets in frontend, secure logging      |
| DDoS                      | Per-IP/Per-Key rate limiting                 |
| Unauthorized Access      | API key validation, CORS whitelist          |


## Security Folder Structure

```
ai-smart-chatbot/
├── security/
│   ├── class-aisc-security.php      # Main security controller
│   ├── class-aisc-input-sanitizer.php
│   ├── class-aisc-nonce-manager.php
│   └── class-aisc-logger.php
├── includes/
│   └── [existing files]
└── [existing files]

backend/
├── app/
│   ├── security/
│   │   ├── __init__.py
│   │   ├── auth.py              # API Key + JWT authentication
│   │   ├── rate_limiter.py      # Rate limiting (Redis-based)
│   │   ├── prompt_protection.py # Prompt injection detection
│   │   ├── content_sanitizer.py # RAG content cleaning
│   │   └── logger.py            # Security event logging
│   ├── api/
│   │   └── [existing files]
│   └── services/
│       └── [existing files]
├── .env.example                   # NEVER commit .env
└── requirements.txt
```

## Authentication Flow

```
1. WordPress → Backend Request:
   - Include X-API-Key header
   - Include X-WP-Signature (HMAC) for sensitive endpoints
   - Include request timestamp
   
2. Backend validates:
   - API key exists and is active
   - Request timestamp < 5 minutes (prevent replay)
   - HMAC signature matches (for admin endpoints)
   
3. Response includes:
   - No sensitive error messages
   - Rate limit headers
```