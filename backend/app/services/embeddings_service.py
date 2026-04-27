from app.core.config import settings
from typing import List
import httpx


class EmbeddingsService:
    def __init__(self):
        self.embedding_model = settings.EMBEDDING_MODEL
        self.dimension = settings.EMBEDDING_DIMENSION
    
    async def get_embeddings(self, texts: List[str]) -> List[List[float]]:
        if settings.OPENAI_API_KEY:
            return await self._openai_embeddings(texts)
        else:
            return await self._local_embeddings(texts)
    
    async def _openai_embeddings(self, texts: List[str]) -> List[List[float]]:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                "https://api.openai.com/v1/embeddings",
                headers={
                    "Authorization": f"Bearer {settings.OPENAI_API_KEY}",
                    "Content-Type": "application/json"
                },
                json={
                    "input": texts,
                    "model": self.embedding_model
                },
                timeout=30.0
            )
            
            if response.status_code != 200:
                raise Exception(f"OpenAI API error: {response.text}")
            
            data = response.json()
            return [item["embedding"] for item in data["data"]]
    
    async def _local_embeddings(self, texts: List[str]) -> List[List[float]]:
        return [[0.0] * self.dimension for _ in texts]
    
    def chunk_text(self, text: str) -> List[str]:
        import re
        
        if not text:
            return []
        
        chunks = []
        start = 0
        text_length = len(text)
        
        while start < text_length:
            end = start + settings.CHUNK_SIZE
            
            if end < text_length:
                break_point = text.rfind('\n\n', start, end)
                if break_point == -1:
                    break_point = text.rfind('. ', start, end)
                if break_point == -1:
                    break_point = text.rfind(' ', start, end)
                if break_point > start:
                    end = break_point + 1
            
            chunk = text[start:end].strip()
            if chunk:
                chunks.append(chunk)
            
            start = end - settings.CHUNK_OVERLAP
            if start < 0:
                start = 0
        
        return chunks