from app.core.config import settings
from typing import List, Dict, Any, Optional
import uuid
import json
from pathlib import Path


class VectorStoreService:
    def __init__(self):
        self.client = None
        self.collection = None
        self._memory: Dict[str, List[Dict]] = {}
    
    async def initialize(self):
        if settings.VECTOR_DB_TYPE == "chroma":
            await self._init_chroma()
        elif settings.VECTOR_DB_TYPE == "pinecone":
            await self._init_pinecone()
        else:
            await self._init_chroma()
    
    async def _init_chroma(self):
        try:
            import chromadb
            from chromadb.config import Settings
            
            self.client = chromadb.PersistentClient(
                path=settings.CHROMA_PERSIST_DIR,
                settings=Settings(anonymized_telemetry=False)
            )
            
            self.collection = self.client.get_or_create_collection(
                name="aisc_documents",
                metadata={"hnsw:space": "cosine"}
            )
        except ImportError:
            self._use_fallback()
    
    async def _init_pinecone(self):
        try:
            from pinecone import Pinecone
            
            pc = Pinecone(api_key=settings.PINECONE_API_KEY)
            
            try:
                self.index = pc.Index(settings.PINECONE_INDEX_NAME)
            except:
                pc.create_index(
                    name=settings.PINECONE_INDEX_NAME,
                    dimension=settings.EMBEDDING_DIMENSION,
                    metric="cosine"
                )
                self.index = pc.Index(settings.PINECONE_INDEX_NAME)
            
            self._use_pinecone = True
        except ImportError:
            self._use_fallback()
    
    def _use_fallback(self):
        self._fallback_store = []
        self._fallback_embeddings = []
    
    async def add_documents(
        self,
        documents: List[str],
        metadatas: Optional[List[Dict]] = None,
        ids: Optional[List[str]] = None
    ):
        if hasattr(self, '_use_fallback') and self._fallback_store is not None:
            for i, doc in enumerate(documents):
                self._fallback_store.append({
                    "id": ids[i] if ids else str(uuid.uuid4()),
                    "text": doc,
                    "metadata": metadatas[i] if metadatas else {}
                })
            return
        
        doc_ids = ids or [str(uuid.uuid4()) for _ in documents]
        
        if hasattr(self, '_use_pinecone'):
            from app.services.embeddings_service import EmbeddingsService
            em = EmbeddingsService()
            embeddings = await em.get_embeddings(documents)
            
            vectors = [
                {"id": doc_ids[i], "values": embeddings[i], "metadata": metadatas[i] or {}}
                for i in range(len(documents))
            ]
            
            self.index.upsert(vectors=vectors)
        else:
            self.collection.add(
                documents=documents,
                metadatas=metadatas,
                ids=doc_ids
            )
    
    async def search(self, query: str, limit: int = 10, filter_dict: Optional[Dict] = None):
        if hasattr(self, '_use_fallback') and self._fallback_store is not None:
            results = sorted(
                self._fallback_store,
                key=lambda x: self._simple_similarity(query, x['text']),
                reverse=True
            )[:limit]
            return [{"text": r["text"], "metadata": r["metadata"], "score": 1.0} for r in results]
        
        from app.services.embeddings_service import EmbeddingsService
        em = EmbeddingsService()
        query_embedding = await em.get_embeddings([query])
        query_embedding = query_embedding[0]
        
        if hasattr(self, '_use_pinecone'):
            results = self.index.query(
                vector=query_embedding,
                top_k=limit,
                filter=filter_dict,
                include_metadata=True
            )
            
            return [
                {
                    "text": r["metadata"].get("text", ""),
                    "metadata": r["metadata"],
                    "score": 1 - r["score"]
                }
                for r in results["matches"]
            ]
        else:
            results = self.collection.query(
                query_embeddings=[query_embedding],
                n_results=limit,
                where=filter_dict,
                include_documents=True,
                include_metadatas=True
            )
            
            output = []
            if results["documents"] and results["documents"][0]:
                for i, doc in enumerate(results["documents"][0]):
                    output.append({
                        "text": doc,
                        "metadata": results["metadatas"][0][i] if results["metadatas"] else {},
                        "score": 1 - (results["distances"][0][i] if results["distances"] else 0)
                    })
            
            return output
    
    def _simple_similarity(self, query: str, text: str) -> float:
        query_words = set(query.lower().split())
        text_words = set(text.lower().split())
        if not query_words or not text_words:
            return 0.0
        return len(query_words.intersection(text_words)) / len(query_words)
    
    async def delete_by_metadata(self, key: str, value: str):
        if hasattr(self, '_use_fallback') and self._fallback_store is not None:
            self._fallback_store = [
                d for d in self._fallback_store 
                if d["metadata"].get(key) != value
            ]
            return
        
        if hasattr(self, '_use_pinecone'):
            self.index.delete(filter={key: value})
        else:
            results = self.collection.get(where={key: value})
            if results["ids"]:
                self.collection.delete(ids=results["ids"])
    
    async def clear(self, category: Optional[str] = None):
        if hasattr(self, '_use_fallback') and self._fallback_store is not None:
            if category:
                self._fallback_store = [
                    d for d in self._fallback_store 
                    if d["metadata"].get("category") != category
                ]
            else:
                self._fallback_store = []
            return
        
        filter_dict = {"category": category} if category else None
        
        if hasattr(self, '_use_pinecone'):
            self.index.delete(filter=filter_dict or {})
        else:
            if filter_dict:
                results = self.collection.get(where=filter_dict)
                if results["ids"]:
                    self.collection.delete(ids=results["ids"])
            else:
                self.client.delete_collection("aisc_documents")
                self.collection = self.client.get_or_create_collection(
                    name="aisc_documents",
                    metadata={"hnsw:space": "cosine"}
                )
    
    async def get_stats(self) -> Dict[str, Any]:
        if hasattr(self, '_use_fallback') and self._fallback_store is not None:
            total = len(self._fallback_store)
            categories = {}
            for doc in self._fallback_store:
                cat = doc["metadata"].get("category", "unknown")
                categories[cat] = categories.get(cat, 0) + 1
            
            return {
                "total_documents": len(set(d["metadata"].get("file_id") for d in self._fallback_store if d["metadata"].get("file_id"))),
                "total_chunks": total,
                "categories": categories
            }
        
        if hasattr(self, '_use_pinecone'):
            stats = self.index.describe_index_stats()
            return {
                "total_documents": stats.get("total_vector_count", 0),
                "total_chunks": stats.get("total_vector_count", 0),
                "categories": {}
            }
        
        count = self.collection.count()
        
        categories = {}
        all_docs = self.collection.get()
        if all_docs["metadatas"]:
            for meta in all_docs["metadatas"]:
                cat = meta.get("category", "unknown")
                categories[cat] = categories.get(cat, 0) + 1
        
        return {
            "total_documents": count,
            "total_chunks": count,
            "categories": categories
        }
    
    def add_to_memory(self, session_id: str, role: str, content: str):
        if session_id not in self._memory:
            self._memory[session_id] = []
        
        self._memory[session_id].append({"role": role, "content": content})
        
        if len(self._memory[session_id]) > settings.SESSION_MEMORY_LIMIT:
            self._memory[session_id] = self._memory[session_id][-settings.SESSION_MEMORY_LIMIT:]
    
    def get_memory(self, session_id: str) -> List[Dict]:
        return self._memory.get(session_id, [])
    
    def clear_memory(self, session_id: str):
        if session_id in self._memory:
            del self._memory[session_id]