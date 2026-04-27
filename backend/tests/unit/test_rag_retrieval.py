import pytest
import asyncio
from unittest.mock import Mock, AsyncMock, patch
import sys
import os

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))


from app.services.vector_store import VectorStoreService
from app.services.embeddings_service import EmbeddingsService


class TestRAGRetrieval:

    @pytest.fixture
    def vector_store(self):
        return VectorStoreService()

    @pytest.mark.asyncio
    async def test_search_returns_relevant_documents(self, vector_store):
        await vector_store.initialize()
        
        await vector_store.add_documents(
            documents=[
                "Our company offers premium widgets for $99.",
                "The weather today is sunny and warm.",
                "We have a 30-day return policy for all products."
            ],
            metadatas=[
                {"source": "products"},
                {"source": "weather"},
                {"source": "faq"}
            ],
            ids=["doc1", "doc2", "doc3"]
        )
        
        results = await vector_store.search("return policy", limit=2)
        
        assert len(results) > 0
        assert any("return" in r["text"].lower() for r in results)

    @pytest.mark.asyncio
    async def test_search_respects_limit(self, vector_store):
        await vector_store.initialize()
        
        docs = [f"Document {i} content" for i in range(10)]
        await vector_store.add_documents(
            documents=docs,
            ids=[f"doc_{i}" for i in range(10)]
        )
        
        results = await vector_store.search("content", limit=3)
        
        assert len(results) <= 3

    @pytest.mark.asyncio
    async def test_search_with_no_results(self, vector_store):
        await vector_store.initialize()
        
        results = await vector_store.search("nonexistent query xyzabc", limit=5)
        
        assert len(results) == 0

    @pytest.mark.asyncio
    async def test_chunking_splits_text_correctly(self):
        with patch('app.services.embeddings_service.settings') as mock_settings:
            mock_settings.CHUNK_SIZE = 100
            mock_settings.CHUNK_OVERLAP = 20
            
            service = EmbeddingsService()
            text = "This is sentence one. This is sentence two. This is sentence three. " * 10
            
            chunks = service.chunk_text(text)
            
            assert len(chunks) > 1
            assert all(len(chunk) <= 150 for chunk in chunks)

    @pytest.mark.asyncio
    async def test_memory_stores_conversation(self, vector_store):
        await vector_store.initialize()
        
        vector_store.add_to_memory("session1", "user", "Hello")
        vector_store.add_to_memory("session1", "assistant", "Hi there!")
        
        memory = vector_store.get_memory("session1")
        
        assert len(memory) == 2
        assert memory[0]["role"] == "user"
        assert memory[1]["role"] == "assistant"

    @pytest.mark.asyncio
    async def test_memory_limits_size(self, vector_store):
        await vector_store.initialize()
        
        with patch('app.services.embeddings_service.settings') as mock_settings:
            mock_settings.SESSION_MEMORY_LIMIT = 3
            
            for i in range(5):
                vector_store.add_to_memory("session1", "user", f"Message {i}")
            
            memory = vector_store.get_memory("session1")
            assert len(memory) <= 3


class TestEmbeddingsService:

    @pytest.fixture
    def embeddings_service(self):
        with patch('app.services.embeddings_service.settings') as mock_settings:
            mock_settings.OPENAI_API_KEY = ""
            mock_settings.EMBEDDING_DIMENSION = 1536
            mock_settings.CHUNK_SIZE = 1000
            mock_settings.CHUNK_OVERLAP = 200
            yield EmbeddingsService()

    def test_get_embeddings_returns_vectors(self, embeddings_service):
        with patch.object(embeddings_service, '_local_embeddings') as mock_emb:
            mock_emb.return_value = [[0.1] * 1536]
            
            result = asyncio.run(embeddings_service.get_embeddings(["test text"]))
            
            assert len(result) == 1
            assert len(result[0]) == 1536

    def test_chunk_text_empty_input(self, embeddings_service):
        chunks = embeddings_service.chunk_text("")
        assert len(chunks) == 0

    def test_chunk_text_preserves_sentences(self, embeddings_service):
        text = "First sentence. Second sentence. Third sentence."
        
        chunks = embeddings_service.chunk_text(text)
        
        assert len(chunks) > 0
        for chunk in chunks:
            assert "First" in chunk or "Second" in chunk or "Third" in chunk


class TestRAGQualityMetrics:

    @pytest.mark.asyncio
    async def test_precision_at_k(self, vector_store):
        await vector_store.initialize()
        
        await vector_store.add_documents(
            documents=[
                "Python is a programming language.",
                "JavaScript is also a programming language.",
                "The sky is blue.",
                "We sell premium products.",
                "Our return policy is 30 days."
            ],
            ids=["doc1", "doc2", "doc3", "doc4", "doc5"]
        )
        
        results = await vector_store.search("programming languages", limit=3)
        
        relevant_docs = [r for r in results if "programming" in r["text"].lower()]
        
        precision = len(relevant_docs) / len(results) if results else 0
        
        assert precision >= 0.5

    @pytest.mark.asyncio
    async def test_context_retrieval_relevance(self, vector_store):
        await vector_store.initialize()
        
        await vector_store.add_documents(
            documents=[
                "Product A costs $100 and has a 1-year warranty.",
                "Product B costs $50 and has a 6-month warranty.",
                "Our support team is available 24/7."
            ]
        )
        
        results = await vector_store.search("warranty information", limit=2)
        
        assert len(results) >= 1
        assert "warranty" in results[0]["text"].lower() or " warranty" in results[0]["text"].lower()


class TestVectorStoreStats:

    @pytest.mark.asyncio
    async def test_get_stats_counts_documents(self, vector_store):
        await vector_store.initialize()
        
        await vector_store.add_documents(
            documents=["Doc1", "Doc2", "Doc3", "Doc4", "Doc5"],
            ids=["id1", "id2", "id3", "id4", "id5"]
        )
        
        stats = await vector_store.get_stats()
        
        assert stats["total_chunks"] >= 5
        assert stats["total_documents"] >= 5

    @pytest.mark.asyncio
    async def test_get_stats_categories(self, vector_store):
        await vector_store.initialize()
        
        await vector_store.add_documents(
            documents=["FAQ1", "FAQ2"],
            metadatas=[{"category": "faq"}, {"category": "faq"}],
            ids=["f1", "f2"]
        )
        await vector_store.add_documents(
            documents=["Product1"],
            metadatas=[{"category": "product"}],
            ids=["p1"]
        )
        
        stats = await vector_store.get_stats()
        
        assert "faq" in stats["categories"]
        assert "product" in stats["categories"]