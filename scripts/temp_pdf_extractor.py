#!/usr/bin/env python3
import sys
try:
    import PyPDF2
    with open(sys.argv[1], "rb") as file:
        reader = PyPDF2.PdfReader(file)
        text = ""
        for page in reader.pages:
            text += page.extract_text() or ""
        print(text)
except ImportError:
    try:
        import pdfplumber
        with pdfplumber.open(sys.argv[1]) as pdf:
            text = ""
            for page in pdf.pages:
                text += page.extract_text() or ""
            print(text)
    except ImportError:
        print("No PDF libraries available")
