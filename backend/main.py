from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware
from fastapi.responses import JSONResponse
from contextlib import asynccontextmanager
import logging

from app.core.config import settings
from app.api.routes import chat, train, upload, knowledge
from app.services.vector_store import VectorStoreService

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("Starting AI Smart Chatbot Backend...")
    
    vector_store = VectorStoreService()
    await vector_store.initialize()
    app.state.vector_store = vector_store
    
    logger.info("Backend ready!")
    yield
    
    logger.info("Shutting down backend...")


app = FastAPI(
    title="AI Smart Chatbot API",
    description="Backend API for AI Smart Chatbot with RAG system",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
    lifespan=lifespan
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(chat.router, prefix="/api/v1/chat", tags=["Chat"])
app.include_router(train.router, prefix="/api/v1/train", tags=["Train"])
app.include_router(upload.router, prefix="/api/v1/upload", tags=["Upload"])
app.include_router(knowledge.router, prefix="/api/v1/knowledge", tags=["Knowledge"])


@app.get("/")
async def root():
    return {
        "name": "AI Smart Chatbot API",
        "version": "1.0.0",
        "status": "running"
    }


@app.get("/health")
async def health_check():
    return {"status": "healthy"}


@app.exception_handler(Exception)
async def global_exception_handler(request, exc):
    logger.error(f"Global exception: {exc}", exc_info=True)
    return JSONResponse(
        status_code=500,
        content={"detail": "Internal server error"}
    )