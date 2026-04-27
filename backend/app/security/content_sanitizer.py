import re
import html
from typing import List, Dict, Any, Optional
from bs4 import BeautifulSoup
import logging

logger = logging.getLogger(__name__)


class ContentSanitizer:
    
    DANGEROUS_PATTERNS = [
        r'<script[^>]*>.*?</script>',
        r'<iframe[^>]*>.*?</iframe>',
        r'javascript:',
        r'on\w+\s*=',
        r'<object[^>]*>',
        r'<embed[^>]*>',
        r'<form[^>]*>',
        r'<input[^>]*]',
        r'<!--.*?-->',
        r'<style[^>]*>.*?</style>',
    ]
    
    HIDDEN_INSTRUCTION_PATTERNS = [
        r'\{[\'"]system[\'"]\s*:',
        r'__import__|import\s+os|import\s+sys',
        r'eval\s*\(|exec\s*\(',
        r'<!--\s*ignore\s*-->',
        r'<!--\s*system\s*-->',
    ]
    
    def __init__(self):
        self.compiled_patterns = [re.compile(p, re.IGNORECASE | re.DOTALL) for p in self.DANGEROUS_PATTERNS]
        self.hidden_patterns = [re.compile(p, re.IGNORECASE | re.DOTALL) for p in self.HIDDEN_INSTRUCTION_PATTERNS]
    
    def sanitize_document(self, text: str, content_type: str = "text") -> str:
        if not text:
            return ""
        
        sanitized = text
        
        if content_type in ["html", "webpage"]:
            sanitized = self._strip_html(sanitized)
        else:
            sanitized = self._remove_html_tags(sanitized)
        
        sanitized = self._remove_dangerous_patterns(sanitized)
        
        sanitized = self._normalize_whitespace(sanitized)
        
        sanitized = self._remove_null_bytes(sanitized)
        
        return sanitized.strip()
    
    def _strip_html(self, html_content: str) -> str:
        try:
            soup = BeautifulSoup(html_content, 'html.parser')
            
            for script in soup(["script", "style", "iframe", "object", "embed"]):
                script.decompose()
            
            for tag in soup.find_all(True):
                if any(attr.startswith('on') for attr in tag.attrs):
                    del tag.attrs[onl_attr for onl_attr in list(tag.attrs) if onl_attr.startswith('on')]
                
                if 'javascript:' in tag.get('href', '').lower():
                    tag['href'] = '#'
                
                if tag.name == 'form':
                    tag.replace_with(soup.new_string(tag.get_text()))
            
            text = soup.get_text(separator='\n', strip=True)
            
            return text
        except Exception as e:
            logger.warning(f"HTML parsing error, falling back to basic cleaning: {e}")
            return self._remove_html_tags(html_content)
    
    def _remove_html_tags(self, text: str) -> str:
        text = html.escape(text)
        
        text = re.sub(r'<[^>]+>', '', text)
        
        return text
    
    def _remove_dangerous_patterns(self, text: str) -> str:
        for pattern in self.compiled_patterns:
            text = pattern.sub('', text)
        
        return text
    
    def check_for_hidden_instructions(self, text: str) -> Dict[str, Any]:
        detected = []
        cleaned_text = text
        
        for i, pattern in enumerate(self.hidden_patterns):
            matches = pattern.findall(text)
            if matches:
                pattern_name = self._get_hidden_pattern_name(i)
                detected.append(pattern_name)
                
                cleaned_text = pattern.sub('', cleaned_text)
        
        return {
            "has_hidden_instructions": len(detected) > 0,
            "detected_patterns": detected,
            "cleaned_text": cleaned_text,
            "requires_review": any(p in ["system_injection", "code_injection"] for p in detected)
        }
    
    def _get_hidden_pattern_name(self, pattern_index: int) -> str:
        names = [
            "json_system_injection",
            "python_import_injection",
            "code_execution_injection",
            "html_comment_system",
            "html_comment_ignore"
        ]
        return names[pattern_index] if pattern_index < len(names) else f"hidden_pattern_{pattern_index}"
    
    def _normalize_whitespace(self, text: str) -> str:
        text = re.sub(r'[ \t]+', ' ', text)
        
        text = re.sub(r'\n\n+', '\n\n', text)
        
        text = re.sub(r'\n\s*\n', '\n\n', text)
        
        return text
    
    def _remove_null_bytes(self, text: str) -> str:
        return text.replace('\x00', '').replace('\ufffd', '')
    
    def sanitize_for_embedding(self, text: str, max_length: int = 10000) -> str:
        sanitized = self.sanitize_document(text)
        
        if len(sanitized) > max_length:
            sanitized = sanitized[:max_length]
        
        check_result = self.check_for_hidden_instructions(sanitized)
        if check_result["requires_review"]:
            logger.warning(f"Document flagged for review: {check_result['detected_patterns']}")
            sanitized = check_result["cleaned_text"]
        
        return sanitized


class RAGSecurityLayer:
    
    def __init__(self):
        self.sanitizer = ContentSanitizer()
        self.allowed_content_types = ["pdf", "docx", "txt", "csv", "html", "webpage", "faq"]
    
    def process_uploaded_document(
        self, 
        content: str, 
        content_type: str,
        source: str
    ) -> Dict[str, Any]:
        if content_type not in self.allowed_content_types:
            raise ValueError(f"Unsupported content type: {content_type}")
        
        sanitized = self.sanitizer.sanitize_for_embedding(content)
        
        hidden_check = self.sanitizer.check_for_hidden_instructions(sanitized)
        
        if hidden_check["requires_review"]:
            logger.warning(
                f"Document from {source} flagged for security review: "
                f"{hidden_check['detected_patterns']}"
            )
        
        chunks = self._chunk_content(sanitized)
        
        return {
            "status": "processed",
            "original_length": len(content),
            "sanitized_length": len(sanitized),
            "chunks_created": len(chunks),
            "chunks": chunks,
            "security_flags": hidden_check["detected_patterns"],
            "requires_review": hidden_check["requires_review"]
        }
    
    def _chunk_content(self, text: str, chunk_size: int = 1000) -> List[str]:
        chunks = []
        start = 0
        text_length = len(text)
        
        while start < text_length:
            end = min(start + chunk_size, text_length)
            
            if end < text_length:
                break_point = max(
                    text.rfind('\n\n', start, end),
                    text.rfind('. ', start, end),
                    text.rfind('\n', start, end)
                )
                if break_point > start:
                    end = break_point + 1
            
            chunk = text[start:end].strip()
            if chunk:
                chunks.append(chunk)
            
            start = end
        
        return chunks
    
    def validate_search_query(self, query: str) -> Dict[str, Any]:
        query = query.strip()
        
        if not query or len(query) < 2:
            return {"valid": False, "reason": "Query too short"}
        
        if len(query) > 500:
            return {"valid": False, "reason": "Query too long"}
        
        hidden_check = self.sanitizer.check_for_hidden_instructions(query)
        
        if hidden_check["has_hidden_instructions"]:
            logger.warning(f"Search query contains hidden instructions: {hidden_check['detected_patterns']}")
        
        return {
            "valid": True,
            "sanitized_query": hidden_check.get("cleaned_text", query),
            "flags": hidden_check["detected_patterns"]
        }


content_sanitizer = ContentSanitizer()
rag_security = RAGSecurityLayer()