from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from typing import List, Optional, Dict, Any
from app.api.deps import get_vector_store

router = APIRouter()


class KnowledgeStats(BaseModel):
    total_documents: int
    total_chunks: int
    categories: Dict[str, int]


@router.get("/stats", response_model=KnowledgeStats)
async def get_knowledge_stats():
    try:
        vector_store = await get_vector_store()
        stats = await vector_store.get_stats()
        return stats
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/search")
async def search_knowledge(
    query: str,
    limit: Optional[int] = 10
):
    try:
        vector_store = await get_vector_store()
        results = await vector_store.search(query, limit=limit)
        return {"results": results}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.delete("/clear")
async def clear_knowledge(category: Optional[str] = None):
    try:
        vector_store = await get_vector_store()
        await vector_store.clear(category=category)
        return {"status": "success", "message": "Knowledge base cleared"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))