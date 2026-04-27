from fastapi import APIRouter, HTTPException, UploadFile, File, Form
from typing import Optional, List
import os
import uuid
from pathlib import Path
from app.core.config import settings
from app.services.document_processor import DocumentProcessor
from app.services.embeddings_service import EmbeddingsService
from app.api.deps import get_vector_store

router = APIRouter()

ALLOWED_EXTENSIONS = {'.pdf', '.docx', '.txt', '.csv'}


@router.post("/file")
async def upload_file(
    file: UploadFile = File(...),
    category: Optional[str] = Form("documents")
):
    if not file.filename:
        raise HTTPException(status_code=400, detail="No file provided")
    
    ext = Path(file.filename).suffix.lower()
    if ext not in ALLOWED_EXTENSIONS:
        raise HTTPException(
            status_code=400, 
            detail=f"File type not allowed. Allowed: {', '.join(ALLOWED_EXTENSIONS)}"
        )
    
    file_id = str(uuid.uuid4())
    upload_dir = Path(settings.UPLOAD_DIR) / category
    upload_dir.mkdir(parents=True, exist_ok=True)
    
    file_path = upload_dir / f"{file_id}{ext}"
    
    try:
        content = await file.read()
        
        if len(content) > settings.MAX_FILE_SIZE:
            raise HTTPException(
                status_code=400,
                detail=f"File too large. Max size: {settings.MAX_FILE_SIZE / 1024 / 1024}MB"
            )
        
        with open(file_path, "wb") as f:
            f.write(content)
        
        processor = DocumentProcessor()
        text = await processor.process_file(file_path)
        
        embeddings_service = EmbeddingsService()
        chunks = embeddings_service.chunk_text(text)
        
        vector_store = await get_vector_store()
        await vector_store.add_documents(
            documents=chunks,
            metadatas=[{"source": str(file_path), "category": category, "file_id": file_id} for _ in chunks],
            ids=[f"{file_id}_{i}" for i in range(len(chunks))]
        )
        
        return {
            "status": "success",
            "file_id": file_id,
            "filename": file.filename,
            "chunks": len(chunks),
            "path": str(file_path)
        }
        
    except Exception as e:
        if file_path.exists():
            file_path.unlink()
        raise HTTPException(status_code=500, detail=str(e))


@router.delete("/file/{file_id}")
async def delete_file(file_id: str):
    try:
        vector_store = await get_vector_store()
        await vector_store.delete_by_metadata("file_id", file_id)
        
        upload_dir = Path(settings.UPLOAD_DIR)
        for ext in ALLOWED_EXTENSIONS:
            file_path = upload_dir / "documents" / f"{file_id}{ext}"
            if file_path.exists():
                file_path.unlink()
                break
        
        return {"status": "success", "message": "File deleted"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/files")
async def list_files(category: Optional[str] = None):
    upload_dir = Path(settings.UPLOAD_DIR)
    
    if category:
        upload_dir = upload_dir / category
        if not upload_dir.exists():
            return {"files": []}
    
    files = []
    for file_path in upload_dir.rglob("*"):
        if file_path.is_file():
            files.append({
                "name": file_path.name,
                "path": str(file_path),
                "size": file_path.stat().st_size,
                "modified": file_path.stat().st_mtime
            })
    
    return {"files": files}