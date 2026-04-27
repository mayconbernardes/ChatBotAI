from pathlib import Path
from typing import Union
import uuid


class DocumentProcessor:
    def __init__(self):
        self.supported_types = {
            '.txt': self._process_txt,
            '.pdf': self._process_pdf,
            '.docx': self._process_docx,
            '.csv': self._process_csv,
        }
    
    async def process_file(self, file_path: Union[str, Path]) -> str:
        file_path = Path(file_path)
        ext = file_path.suffix.lower()
        
        if ext not in self.supported_types:
            raise ValueError(f"Unsupported file type: {ext}")
        
        return await self.supported_types[ext](file_path)
    
    async def _process_txt(self, file_path: Path) -> str:
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            return f.read()
    
    async def _process_pdf(self, file_path: Path) -> str:
        try:
            import PyPDF2
            
            text = []
            with open(file_path, 'rb') as f:
                reader = PyPDF2.PdfReader(f)
                for page in reader.pages:
                    text.append(page.extract_text())
            
            return '\n'.join(text)
        except ImportError:
            return await self._extract_pdf_text_basic(file_path)
    
    async def _extract_pdf_text_basic(self, file_path: Path) -> str:
        with open(file_path, 'rb') as f:
            content = f.read()
        
        import re
        text = re.sub(rb'[^\x20-\x7E]', b'', content)
        return text.decode('ascii', errors='ignore')
    
    async def _process_docx(self, file_path: Path) -> str:
        try:
            from docx import Document
            
            doc = Document(file_path)
            text = []
            for para in doc.paragraphs:
                text.append(para.text)
            
            return '\n'.join(text)
        except ImportError:
            raise ImportError("python-docx is required for .docx files")
    
    async def _process_csv(self, file_path: Path) -> str:
        import csv
        
        text = []
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            reader = csv.reader(f)
            headers = next(reader, None)
            
            if headers:
                text.append(' | '.join(headers))
            
            for row in reader:
                if row:
                    text.append(' | '.join(row))
        
        return '\n'.join(text)


class URLProcessor:
    def __init__(self):
        pass
    
    async def fetch_and_parse(self, url: str, max_depth: int = 2) -> str:
        import httpx
        from bs4 import BeautifulSoup
        
        visited = set()
        content = []
        
        async def crawl(current_url: str, depth: int):
            if depth > max_depth or current_url in visited:
                return
            
            visited.add(current_url)
            
            try:
                async with httpx.AsyncClient() as client:
                    response = await client.get(current_url, timeout=30.0)
                    response.raise_for_status()
                    
                    soup = BeautifulSoup(response.text, 'html.parser')
                    
                    for script in soup(["script", "style"]):
                        script.decompose()
                    
                    text = soup.get_text(separator='\n', strip=True)
                    if text:
                        content.append(f"Source: {current_url}\n{text}")
                    
                    if depth < max_depth:
                        for link in soup.find_all('a', href=True):
                            href = link['href']
                            if href.startswith('http') and href not in visited:
                                await crawl(href, depth + 1)
            
            except Exception:
                pass
        
        await crawl(url, 0)
        
        return '\n\n'.join(content)