from app.core.config import settings
from typing import Dict, Any, Optional, List
import uuid
import httpx


class ChatService:
    def __init__(self, vector_store):
        self.vector_store = vector_store
        self.default_tone_prompts = {
            "professional": "You are a professional business assistant. Be formal, concise, and helpful.",
            "friendly": "You are a friendly assistant. Be casual, warm, and approachable.",
            "sales": "You are a sales assistant. Be persuasive, friendly, and focus on helping users find solutions.",
            "technical": "You are a technical support assistant. Be precise, detailed, and thorough."
        }
    
    async def process_message(
        self,
        message: str,
        session_id: Optional[str] = None,
        user_id: Optional[str] = None
    ) -> Dict[str, Any]:
        if not session_id:
            session_id = str(uuid.uuid4())
        
        self.vector_store.add_to_memory(session_id, "user", message)
        
        context = await self._get_context(message)
        
        prompt = self._build_prompt(message, context)
        
        response = await self._call_ai(prompt, session_id)
        
        self.vector_store.add_to_memory(session_id, "assistant", response)
        
        return {
            "answer": response,
            "session_id": session_id,
            "sources": context.get("sources", []),
            "metadata": {
                "tone": settings.DEFAULT_MODEL,
                "chunks_used": len(context.get("chunks", []))
            }
        }
    
    async def _get_context(self, query: str) -> Dict[str, Any]:
        results = await self.vector_store.search(query, limit=5)
        
        if not results:
            return {
                "context": "",
                "chunks": [],
                "sources": []
            }
        
        chunks = [r["text"] for r in results]
        sources = [r.get("metadata", {}) for r in results]
        
        return {
            "context": "\n\n".join(chunks),
            "chunks": chunks,
            "sources": sources
        }
    
    def _build_prompt(self, message: str, context: Dict[str, Any]) -> str:
        tone = settings.DEFAULT_MODEL
        tone_prompt = self.default_tone_prompts.get(tone, self.default_tone_prompts["professional"])
        
        context_section = ""
        if context.get("context"):
            context_section = f"""
Relevant information from the knowledge base:
{context['context']}
"""
        
        memory = self.vector_store.get_memory(session_id)
        memory_section = ""
        if memory:
            memory_section = f"""
Previous conversation:
{memory}
"""
        
        prompt = f"""{tone_prompt}

{context_section}
{memory_section}

User question: {message}

Instructions:
- Answer ONLY based on the provided information above
- If you don't have enough information to answer accurately, say "I don't have that information"
- Be helpful and concise
- Do not make up information

Answer:"""
        
        return prompt
    
    async def _call_ai(self, prompt: str, session_id: str) -> str:
        provider = settings.DEFAULT_MODEL
        
        if "gpt" in provider:
            return await self._call_openai(prompt)
        elif "claude" in provider:
            return await self._call_anthropic(prompt)
        elif "gemini" in provider:
            return await self._call_google(prompt)
        else:
            return await self._call_openai(prompt)
    
    async def _call_openai(self, prompt: str) -> str:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                "https://api.openai.com/v1/chat/completions",
                headers={
                    "Authorization": f"Bearer {settings.OPENAI_API_KEY}",
                    "Content-Type": "application/json"
                },
                json={
                    "model": settings.DEFAULT_MODEL,
                    "messages": [{"role": "user", "content": prompt}],
                    "temperature": settings.DEFAULT_TEMPERATURE
                },
                timeout=30.0
            )
            
            if response.status_code != 200:
                raise Exception(f"OpenAI API error: {response.text}")
            
            data = response.json()
            return data["choices"][0]["message"]["content"]
    
    async def _call_anthropic(self, prompt: str) -> str:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                "https://api.anthropic.com/v1/messages",
                headers={
                    "x-api-key": settings.ANTHROPIC_API_KEY,
                    "anthropic-version": "2023-06-01",
                    "Content-Type": "application/json"
                },
                json={
                    "model": "claude-3-sonnet-20240229",
                    "max_tokens": 1024,
                    "messages": [{"role": "user", "content": prompt}]
                },
                timeout=30.0
            )
            
            if response.status_code != 200:
                raise Exception(f"Anthropic API error: {response.text}")
            
            data = response.json()
            return data["content"][0]["text"]
    
    async def _call_google(self, prompt: str) -> str:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={settings.GOOGLE_API_KEY}",
                json={
                    "contents": [{"parts": [{"text": prompt}]}],
                    "generationConfig": {
                        "temperature": settings.DEFAULT_TEMPERATURE,
                        "maxOutputTokens": 1024
                    }
                },
                timeout=30.0
            )
            
            if response.status_code != 200:
                raise Exception(f"Google API error: {response.text}")
            
            data = response.json()
            return data["candidates"][0]["content"]["parts"][0]["text"]
    
    async def get_session_history(self, session_id: str) -> List[Dict]:
        return self.vector_store.get_memory(session_id)
    
    async def clear_session(self, session_id: str):
        self.vector_store.clear_memory(session_id)