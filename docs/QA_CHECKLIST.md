# QA Checklist - AI Smart Chatbot

## Pre-Release Validation

### Functional Testing

- [ ] **Chatbot Widget**
  - [ ] Widget appears on frontend pages
  - [ ] Toggle button opens/closes chat
  - [ ] Messages send and receive correctly
  - [ ] Typing indicator shows during processing
  - [ ] Session persists across page reloads
  
- [ ] **Admin Dashboard**
  - [ ] All menu pages load without errors
  - [ ] Settings save and retrieve correctly
  - [ ] Statistics display accurately
  
- [ ] **Knowledge Base**
  - [ ] PDF upload works
  - [ ] DOCX upload works
  - [ ] TXT upload works
  - [ ] CSV upload works
  - [ ] URL crawling retrieves content
  - [ ] Manual content input works
  - [ ] Re-train processes all content
  
- [ ] **AI Responses**
  - [ ] Returns contextual answers
  - [ ] "No information" response works
  - [ ] All 4 tones work (professional, friendly, sales, technical)
  
- [ ] **Customization**
  - [ ] Primary color changes apply
  - [ ] Secondary color changes apply
  - [ ] Position settings work
  - [ ] Widget styles (bubble, fullscreen, sidebar) work
  - [ ] Dark mode toggles correctly
  - [ ] Title and greeting update

### Security Testing

- [ ] **Input Validation**
  - [ ] XSS attempts in chat are sanitized
  - [ ] Prompt injection blocked
  - [ ] Malicious uploads rejected
  - [ ] SQL injection prevented
  
- [ ] **API Security**
  - [ ] Invalid API key rejected (401)
  - [ ] Rate limiting works
  - [ ] CORS restricts origins
  
- [ ] **WordPress Hardening**
  - [ ] Nonces validate correctly
  - [ ] Admin actions require permissions
  - [ ] Escaping applied to all outputs

### Performance Testing

- [ ] Response time < 5 seconds
- [ ] Concurrent users handled (10+)
- [ ] Memory usage stable
- [ ] No memory leaks in 1-hour test

### Compatibility Testing

- [ ] WordPress 6.4.x
- [ ] WordPress 6.5.x
- [ ] PHP 8.1, 8.2, 8.3
- [ ] Popular themes (Astra, OceanWP, GeneratePress)
- [ ] Common plugins (WooCommerce, Elementor)

---

## Deployment Checklist

### Pre-Deployment

- [ ] All tests pass
- [ ] Code reviewed
- [ ] Security audit completed
- [ ] Documentation updated
- [ ] Version bumped in main plugin file

### Environment Setup

- [ ] Backend .env configured
- [ ] Database initialized
- [ ] Vector DB ready
- [ ] API keys set
- [ ] CORS origins configured

### Plugin Installation

- [ ] ZIP package created
- [ ] Plugin installs via WP Admin
- [ ] Activation runs without errors
- [ ] Deactivation cleans up

### Post-Deployment

- [ ] Health check passes
- [ ] First user interaction succeeds
- [ ] Knowledge base loads
- [ ] Monitoring alerts configured

---

## Production Readiness Criteria

| Category | Requirement |
|----------|-------------|
| Stability | 99.9% uptime target |
| Security | No critical vulnerabilities |
| Performance | < 3s p95 response |
| Reliability | Zero data loss |
| Monitoring | Full observability |
| Rollback | Can revert in < 5 min |