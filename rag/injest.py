import os
import re
import pickle
import pdfplumber
from pdf2image import convert_from_path
import pytesseract
import faiss
import numpy as np
from sentence_transformers import SentenceTransformer
from nltk.tokenize import sent_tokenize

import nltk
nltk.download('punkt')
nltk.download('punkt_tab')
# -------- CONFIG --------
PDF_DIR = "pdfs"
INDEX_FILE = "faiss.index"
METADATA_FILE = "chunks_metadata.pkl"

POPPLER_PATH = r"C:\poppler\Library\bin"
pytesseract.pytesseract.tesseract_cmd = r"C:\Program Files\Tesseract-OCR\tesseract.exe"

EMBED_MODEL = "all-MiniLM-L6-v2"

# -------- LOAD MODEL --------
model = SentenceTransformer(EMBED_MODEL)


# -------- TEXT EXTRACTION --------
def extract_text(pdf_path):
    text = ""
    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            t = page.extract_text()
            if t:
                text += t + "\n"
    return text


def extract_images_text(pdf_path):
    text = ""
    images = convert_from_path(pdf_path, dpi=300, poppler_path=POPPLER_PATH)
    for img in images:
        ocr = pytesseract.image_to_string(img, lang="eng")
        if ocr.strip():
            text += ocr + "\n"
    return text


# -------- STRUCTURING --------
def structure_text(raw_text):
    lines = raw_text.splitlines()

    current_unit = "UNKNOWN"
    current_section = "UNKNOWN"

    structured = []

    for line in lines:
        unit_match = re.search(r"UNIT\s+(\d+)", line, re.I)
        section_match = re.search(r"(section\s*)?(\d+(\.\d+)+)", line, re.I)

        if unit_match:
            current_unit = unit_match.group(1)

        if section_match:
            current_section = section_match.group(2)

        structured.append({
            "unit": current_unit,
            "section": current_section,
            "text": line.strip()
        })

    return structured


# -------- SMART CHUNKING --------
def create_chunks(structured_lines, max_tokens=300):
    chunks = []
    current_text = ""
    current_meta = None

    for entry in structured_lines:
        sentence_list = sent_tokenize(entry["text"])

        for sentence in sentence_list:
            if not sentence.strip():
                continue

            if len(current_text) + len(sentence) < max_tokens:
                current_text += " " + sentence
                current_meta = entry
            else:
                chunks.append({
                    "text": current_text.strip(),
                    "unit": current_meta["unit"],
                    "section": current_meta["section"]
                })
                current_text = sentence
                current_meta = entry

    if current_text:
        chunks.append({
            "text": current_text.strip(),
            "unit": current_meta["unit"],
            "section": current_meta["section"]
        })

    return chunks


# -------- MAIN INGEST --------
all_chunks = []

for file in os.listdir(PDF_DIR):
    if not file.endswith(".pdf"):
        continue

    print(f"📄 Processing {file}")
    path = os.path.join(PDF_DIR, file)

    text = extract_text(path)
    ocr_text = extract_images_text(path)

    combined = text + "\n" + ocr_text
    structured = structure_text(combined)

    chunks = create_chunks(structured)

    for chunk in chunks:
        chunk["source"] = file
        all_chunks.append(chunk)

print(f"✂️ Total chunks: {len(all_chunks)}")


# -------- EMBEDDING --------
texts = [c["text"] for c in all_chunks]

embeddings = model.encode(
    texts,
    batch_size=64,
    show_progress_bar=True,
    convert_to_numpy=True
)

# Normalize for cosine similarity
faiss.normalize_L2(embeddings)

# -------- FAISS INDEX --------
dimension = embeddings.shape[1]
index = faiss.IndexFlatIP(dimension)  # cosine similarity
index.add(embeddings)

faiss.write_index(index, INDEX_FILE)

# -------- SAVE METADATA --------
with open(METADATA_FILE, "wb") as f:
    pickle.dump(all_chunks, f)

print("✅ Ingestion complete")