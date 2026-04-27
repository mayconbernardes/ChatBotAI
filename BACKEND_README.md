# AI Smart Chatbot - Backend

FastAPI backend for AI Smart Chatbot with RAG system.

## Quick Start

### Local Development

```bash
cd backend
pip install -r requirements.txt
cp .env.example .env
# Add your OPENAI_API_KEY to .env
uvicorn main:app --reload --host 0.0.0.0 --port 10000
```

### Deploy to Render

1. **Push to GitHub:**
```bash
git add .
git commit -m "Backend ready for deployment"
git push origin main
```

2. **Connect to Render:**
   - Go to https://dashboard.render.com
   - Create new Web Service
   - Connect your GitHub repository
   - Select the `backend` directory as root

3. **Configure Environment Variables:**
   - `OPENAI_API_KEY` - Your OpenAI API key (required)
   - `GOOGLE_API_KEY` - Optional (for Gemini)
   - `ANTHROPIC_API_KEY` - Optional (for Claude)

4. **Deploy:**
   - Click "Create Web Service"
   - Wait for build to complete

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/` | GET | API info |
| `/health` | GET | Health check |
| `/api/v1/chat` | POST | Send chat message |
| `/api/v1/train/url` | POST | Train from URLs |
| `/api/v1/train/manual` | POST | Add manual content |
| `/api/v1/knowledge/stats` | GET | Get knowledge base stats |

## Example Usage

### Chat Request
```bash
curl -X POST https://your-app.onrender.com/api/v1/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello, how can you help me?"}'
```

### Train from URL
```bash
curl -X POST https://your-app.onrender.com/api/v1/train/url \
  -H "Content-Type: application/json" \
  -d '{"urls": ["https://example.com"], "max_depth": 2}'
```

## Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `OPENAI_API_KEY` | OpenAI API key | Yes |
| `GOOGLE_API_KEY` | Google Gemini API key | No |
| `ANTHROPIC_API_KEY` | Anthropic Claude API key | No |
| `DEFAULT_MODEL` | Default AI model | No |
| `CORS_ORIGINS` | Allowed CORS origins | No |

## WordPress Integration

Update your WordPress plugin settings:
- Backend URL: `https://your-app.onrender.com`