from fastapi import Request
from typing import Optional
from app.core.config import settings


async def get_vector_store():
    from app.services.vector_store import VectorStoreService
    from main import app
    return app.state.vector_store


def get_chat_service():
    from app.services.chat_service import ChatService
    from main import app
    return ChatService(app.state.vector_store)


def get_train_service():
    from app.services.train_service import TrainService
    return TrainService()