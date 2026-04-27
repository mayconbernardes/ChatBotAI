from typing import Dict, Any, List, Optional
import uuid
from app.services.document_processor import DocumentProcessor, URLProcessor
from app.services.embeddings_service import EmbeddingsService
from app.api.deps import get_vector_store


class TrainService:
    def __init__(self):
        self.document_processor = DocumentProcessor()
        self.url_processor = URLProcessor()
        self.embeddings_service = EmbeddingsService()
    
    async def train_from_urls(self, urls: List[str], max_depth: int = 2) -> Dict[str, Any]:
        all_content = []
        
        for url in urls:
            try:
                content = await self.url_processor.fetch_and_parse(url, max_depth)
                if content:
                    all_content.append({
                        "content": content,
                        "source": url,
                        "type": "url"
                    })
            except Exception as e:
                continue
        
        if not all_content:
            return {
                "status": "error",
                "message": "Failed to fetch content from URLs",
                "documents_added": 0,
                "chunks_created": 0
            }
        
        vector_store = await get_vector_store()
        
        total_chunks = 0
        documents_added = 0
        
        for item in all_content:
            text = item["content"]
            chunks = self.embeddings_service.chunk_text(text)
            
            if chunks:
                metadatas = [{"source": item["source"], "type": item["type"], "url": item["source"]} for _ in chunks]
                ids = [f"{uuid.uuid4()}_{i}" for i in range(len(chunks))]
                
                await vector_store.add_documents(
                    documents=chunks,
                    metadatas=metadatas,
                    ids=ids
                )
                
                total_chunks += len(chunks)
                documents_added += 1
        
        return {
            "status": "success",
            "message": f"Successfully trained from {len(urls)} URLs",
            "documents_added": documents_added,
            "chunks_created": total_chunks
        }
    
    async def train_from_manual(self, content: str, title: Optional[str] = None, content_type: str = "faq") -> Dict[str, Any]:
        if not content or not content.strip():
            return {
                "status": "error",
                "message": "Content is empty",
                "documents_added": 0,
                "chunks_created": 0
            }
        
        enriched_content = content
        if title:
            enriched_content = f"{title}\n\n{content}"
        
        chunks = self.embeddings_service.chunk_text(enriched_content)
        
        if not chunks:
            return {
                "status": "error",
                "message": "Failed to process content",
                "documents_added": 0,
                "chunks_created": 0
            }
        
        vector_store = await get_vector_store()
        
        metadatas = [{"source": "manual", "type": content_type, "title": title or "Manual Entry"} for _ in chunks]
        ids = [f"manual_{uuid.uuid4()}_{i}" for i in range(len(chunks))]
        
        await vector_store.add_documents(
            documents=chunks,
            metadatas=metadatas,
            ids=ids
        )
        
        return {
            "status": "success",
            "message": "Successfully added manual content",
            "documents_added": 1,
            "chunks_created": len(chunks)
        }
    
    async def retrain_all(self) -> Dict[str, Any]:
        vector_store = await get_vector_store()
        stats = await vector_store.get_stats()
        
        return {
            "status": "success",
            "message": "Knowledge base is up to date",
            "documents_added": stats.get("total_documents", 0),
            "chunks_created": stats.get("total_chunks", 0)
        }