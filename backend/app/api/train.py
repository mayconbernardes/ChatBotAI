from fastapi import APIRouter, HTTPException, UploadFile, File, Form
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
from app.services.train_service import TrainService
from app.api.deps import get_train_service
from app.core.config import settings

router = APIRouter()


class URLTrainingRequest(BaseModel):
    urls: List[str]
    max_depth: Optional[int] = 2


class ManualContentRequest(BaseModel):
    content: str
    title: Optional[str] = None
    content_type: str = "faq"


class TrainResponse(BaseModel):
    status: str
    message: str
    documents_added: int
    chunks_created: int


@router.post("/url", response_model=TrainResponse)
async def train_from_urls(
    request: URLTrainingRequest,
    service: TrainService = Depends(get_train_service)
):
    try:
        result = await service.train_from_urls(
            urls=request.urls,
            max_depth=request.max_depth
        )
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/manual", response_model=TrainResponse)
async def train_from_manual(
    content: str = Form(...),
    title: Optional[str] = Form(None),
    content_type: str = Form("faq"),
    service: TrainService = Depends(get_train_service)
):
    try:
        result = await service.train_from_manual(
            content=content,
            title=title,
            content_type=content_type
        )
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/retrain")
async def retrain_all(
    service: TrainService = Depends(get_train_service)
):
    try:
        result = await service.retrain_all()
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))