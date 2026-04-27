from fastapi import APIRouter, HTTPException, Depends
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
from app.services.chat_service import ChatService
from app.api.deps import get_chat_service

router = APIRouter()


class ChatRequest(BaseModel):
    message: str
    session_id: Optional[str] = None
    user_id: Optional[str] = None


class ChatResponse(BaseModel):
    answer: str
    session_id: str
    sources: Optional[List[Dict[str, Any]]] = None
    metadata: Optional[Dict[str, Any]] = None


@router.post("", response_model=ChatResponse)
async def chat(
    request: ChatRequest,
    service: ChatService = Depends(get_chat_service)
):
    try:
        result = await service.process_message(
            message=request.message,
            session_id=request.session_id,
            user_id=request.user_id
        )
        return result
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail="Internal server error")


@router.get("/sessions/{session_id}")
async def get_session_history(
    session_id: str,
    service: ChatService = Depends(get_chat_service)
):
    history = await service.get_session_history(session_id)
    return {"session_id": session_id, "history": history}


@router.delete("/sessions/{session_id}")
async def clear_session(
    session_id: str,
    service: ChatService = Depends(get_chat_service)
):
    await service.clear_session(session_id)
    return {"status": "Session cleared"}