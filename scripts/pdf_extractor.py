#!/usr/bin/env python3
"""
PDF Text Extractor for HeyTeacher Dashboard
Fallback script for extracting text from PDF files
"""

import sys
import os

def extract_text_with_pypdf2(pdf_path):
    """Extract text using PyPDF2"""
    try:
        import PyPDF2
        with open(pdf_path, "rb") as file:
            reader = PyPDF2.PdfReader(file)
            text = ""
            for page in reader.pages:
                page_text = page.extract_text()
                if page_text:
                    text += page_text + "\n"
            return text
    except ImportError:
        return None
    except Exception as e:
        print(f"PyPDF2 error: {e}", file=sys.stderr)
        return None

def extract_text_with_pdfplumber(pdf_path):
    """Extract text using pdfplumber"""
    try:
        import pdfplumber
        with pdfplumber.open(pdf_path) as pdf:
            text = ""
            for page in pdf.pages:
                page_text = page.extract_text()
                if page_text:
                    text += page_text + "\n"
            return text
    except ImportError:
        return None
    except Exception as e:
        print(f"pdfplumber error: {e}", file=sys.stderr)
        return None

def extract_text_with_pymupdf(pdf_path):
    """Extract text using PyMuPDF (fitz)"""
    try:
        import fitz
        doc = fitz.open(pdf_path)
        text = ""
        for page in doc:
            page_text = page.get_text()
            if page_text:
                text += page_text + "\n"
        doc.close()
        return text
    except ImportError:
        return None
    except Exception as e:
        print(f"PyMuPDF error: {e}", file=sys.stderr)
        return None

def extract_text_with_pdfminer(pdf_path):
    """Extract text using pdfminer"""
    try:
        from pdfminer.high_level import extract_text
        text = extract_text(pdf_path)
        return text
    except ImportError:
        return None
    except Exception as e:
        print(f"pdfminer error: {e}", file=sys.stderr)
        return None

def main():
    if len(sys.argv) != 2:
        print("Usage: python3 pdf_extractor.py <pdf_file_path>", file=sys.stderr)
        sys.exit(1)
    
    pdf_path = sys.argv[1]
    
    if not os.path.exists(pdf_path):
        print(f"Error: File {pdf_path} does not exist", file=sys.stderr)
        sys.exit(1)
    
    # Try different PDF libraries in order of preference
    text = None
    
    # Try PyMuPDF first (most reliable)
    text = extract_text_with_pymupdf(pdf_path)
    
    # Try pdfplumber if PyMuPDF failed
    if not text:
        text = extract_text_with_pdfplumber(pdf_path)
    
    # Try PyPDF2 if pdfplumber failed
    if not text:
        text = extract_text_with_pypdf2(pdf_path)
    
    # Try pdfminer as last resort
    if not text:
        text = extract_text_with_pdfminer(pdf_path)
    
    if text:
        # Clean up the text
        text = text.strip()
        # Remove excessive whitespace
        text = ' '.join(text.split())
        # Handle Unicode encoding for Windows
        try:
            print(text)
        except UnicodeEncodeError:
            # Fallback for Windows console encoding issues
            import codecs
            sys.stdout = codecs.getwriter('utf-8')(sys.stdout.detach())
            print(text)
    else:
        print("Error: Could not extract text from PDF. No suitable libraries available.", file=sys.stderr)
        print("Available libraries:", file=sys.stderr)
        print("  - PyMuPDF (fitz): pip install PyMuPDF", file=sys.stderr)
        print("  - pdfplumber: pip install pdfplumber", file=sys.stderr)
        print("  - PyPDF2: pip install PyPDF2", file=sys.stderr)
        print("  - pdfminer.six: pip install pdfminer.six", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()
