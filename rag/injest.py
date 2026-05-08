import os
import re
import json
import pickle
import pdfplumber
from pdf2image import convert_from_path
import pytesseract
import faiss
import numpy as np
from sentence_transformers import SentenceTransformer
from nltk.tokenize import sent_tokenize
from pdf_cleaner import clean_pdf_text

import nltk

# ---------------- DOWNLOAD NLTK ----------------
nltk.download('punkt')
nltk.download('punkt_tab')

# ---------------- CONFIG ----------------
PDF_DIR = "pdfs"
INDEX_FILE = "faiss.index"
METADATA_FILE = "chunks_metadata.pkl"

POPPLER_PATH = r"C:\poppler\Library\bin"
pytesseract.pytesseract.tesseract_cmd = r"C:\Program Files\Tesseract-OCR\tesseract.exe"

# ✅ FIX 1: Single source of truth for the model name.
# server.py must use this SAME model — "all-MiniLM-L6-v2"
EMBED_MODEL = "all-MiniLM-L6-v2"

# ---------------- LOAD TOPICS JSON ----------------
with open("demo_topics.json", "r", encoding="utf-8") as f:
    TOPICS = json.load(f)

# ---------------- LOAD MODEL ----------------
model = SentenceTransformer(EMBED_MODEL)


# ✅ FIX 2: Pre-build a flat keyword → (unit, section) lookup for fast scoring.
# Each keyword maps to its parent unit+section so we can score any text block.
KEYWORD_INDEX: list[dict] = []   # list of {unit, section, title, keywords: [str]}

for unit_name, sections in TOPICS.items():
    for sec_id, data in sections.items():
        entry = {
            "unit": unit_name,
            "section": sec_id,
            "title": data.get("title", ""),
            "keywords": [kw.lower() for kw in data.get("keywords", [])],
        }
        KEYWORD_INDEX.append(entry)


# ---------------- TEXT EXTRACTION ----------------
def extract_text(pdf_path):
    text = ""
    try:
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                t = page.extract_text()
                if t:
                    text += t + "\n"
    except Exception as e:
        print(f"❌ PDF text extraction error: {e}")
    return text


# ---------------- OCR EXTRACTION ----------------
def extract_images_text(pdf_path):
    text = ""
    try:
        images = convert_from_path(pdf_path, dpi=200, poppler_path=POPPLER_PATH)
        for img in images:
            ocr = pytesseract.image_to_string(img, lang="eng")
            if ocr.strip():
                text += ocr + "\n"
    except Exception as e:
        print(f"❌ OCR extraction error: {e}")
    return text


# ✅ FIX 3: Score a block of text (multiple lines / a window) against ALL topics.
# Returns (best_unit, best_section) or (None, None) if no match found.
def detect_topic_from_block(text_block: str):
    """
    Score a multi-line text block against every topic's keywords + title.
    Returns the (unit, section) with the highest score.
    Minimum score threshold avoids false positives from generic words.
    """
    text_lower = text_block.lower()
    best_unit = None
    best_section = None
    best_score = 0

    for entry in KEYWORD_INDEX:
        score = 0

        # Title match is a strong signal
        title_lower = entry["title"].lower()
        if title_lower and title_lower in text_lower:
            score += 6

        # Individual keyword matches
        for kw in entry["keywords"]:
            if kw in text_lower:
                score += 1

        if score > best_score:
            best_score = score
            best_unit = entry["unit"]
            best_section = entry["section"]

    # Require at least 2 matching signals to avoid noise
    if best_score >= 2:
        return best_unit, best_section
    return None, None


# ✅ FIX 4: Structure text using a sliding WINDOW of lines instead of line-by-line.
# This gives detect_topic_from_block much more context to work with.
def structure_text(raw_text: str, window_size: int = 6):
    lines = [l.strip() for l in raw_text.splitlines() if l.strip()]

    structured = []
    current_unit = None
    current_section = None

    for i, line in enumerate(lines):

        # --- Explicit UNIT heading detection (fast regex) ---
        unit_match = re.search(r"\bUNIT\s+([IVX]+|\d+)\b", line, re.I)
        if unit_match:
            raw_num = unit_match.group(1)
            current_unit = f"UNIT {raw_num.upper() if not raw_num.isdigit() else raw_num}"

        # --- Explicit section number detection (e.g. "2.3 Stack Overflow") ---
        sec_match = re.search(r"\b(\d+\.\d+(?:\.\d+)?)\b", line)
        if sec_match:
            candidate = sec_match.group(1)
            # Check candidate is actually a known section id
            parts = candidate.split(".")
            if len(parts) >= 2:
                # e.g. "2.3" → look in UNIT II
                current_section = candidate if candidate in _known_sections() else current_section

        # --- Sliding window topic detection ---
        window_start = max(0, i - window_size // 2)
        window_end = min(len(lines), i + window_size // 2 + 1)
        window_text = " ".join(lines[window_start:window_end])

        detected_unit, detected_section = detect_topic_from_block(window_text)
        if detected_unit:
            current_unit = detected_unit
        if detected_section:
            current_section = detected_section

        # --- Fallbacks ---
        if current_unit is None:
            current_unit = "UNIT GENERAL"
        if current_section is None:
            current_section = "GENERAL"

        structured.append({
            "unit": current_unit,
            "section": current_section,
            "text": line,
        })

    return structured


def _known_sections() -> set:
    """Return the flat set of all section IDs from TOPICS."""
    ids = set()
    for sections in TOPICS.values():
        ids.update(sections.keys())
    return ids


# ---------------- SMART CHUNKING ----------------
def create_chunks(structured_lines, max_chars=1200):
    """
    Sentence-aware chunking.  Crucially, the metadata (unit/section) for each
    chunk is decided by MAJORITY VOTE of the lines inside the chunk — not just
    the first line.  This handles topic transitions mid-chunk gracefully.
    """
    chunks = []
    current_text = ""
    current_lines_meta: list[dict] = []

    def flush_chunk():
        if not current_text.strip():
            return
        # Majority vote on unit & section
        from collections import Counter
        unit_votes = Counter(m["unit"] for m in current_lines_meta)
        sec_votes  = Counter(m["section"] for m in current_lines_meta)
        best_unit  = unit_votes.most_common(1)[0][0]
        best_sec   = sec_votes.most_common(1)[0][0]
        chunks.append({
            "text": current_text.strip(),
            "unit": best_unit,
            "section": best_sec,
        })

    for entry in structured_lines:
        sentences = sent_tokenize(entry["text"])
        for sentence in sentences:
            sentence = sentence.strip()
            if not sentence:
                continue

            if len(current_text) + len(sentence) < max_chars:
                current_text += " " + sentence
                current_lines_meta.append(entry)
            else:
                flush_chunk()
                current_text = sentence
                current_lines_meta = [entry]

    flush_chunk()
    return chunks


# ---------------- MAIN INGEST ----------------
all_chunks = []

for file in sorted(os.listdir(PDF_DIR)):
    if not file.lower().endswith(".pdf"):
        continue

    print(f"\n📄 Processing: {file}")
    path = os.path.join(PDF_DIR, file)

    text     = clean_pdf_text(extract_text(path))
    ocr_text = clean_pdf_text(extract_images_text(path))
    combined = text + "\n" + ocr_text

    if not combined.strip():
        print(f"⚠️ No text found in {file}")
        continue

    structured = structure_text(combined)
    chunks_raw = create_chunks(structured)

    print(f"✂️ Chunks created: {len(chunks_raw)}")

    for chunk in chunks_raw:
        enriched_text = (
            f"[{chunk['unit']} | SECTION {chunk['section']}]\n"
            f"SOURCE: {file}\n\n"
            f"{chunk['text']}"
        )
        all_chunks.append({
            "text": enriched_text,
            "unit": chunk["unit"],
            "section": chunk["section"],
            "source": file,
        })

# ✅ Show how many chunks each section got — useful for debugging sparse topics
print("\n📊 Chunks per section:")
from collections import defaultdict
section_counts: dict = defaultdict(int)
for c in all_chunks:
    section_counts[(c["unit"], c["section"])] += 1
for (u, s), count in sorted(section_counts.items()):
    print(f"  {u} | {s}: {count} chunks")

print(f"\n✅ Total chunks from all PDFs: {len(all_chunks)}")

# ---------------- CREATE EMBEDDINGS ----------------
texts = [c["text"] for c in all_chunks]
print("\n🧠 Creating embeddings...")
embeddings = model.encode(
    texts,
    batch_size=32,
    show_progress_bar=True,
    convert_to_numpy=True,
)

# Normalize for cosine similarity (IndexFlatIP)
faiss.normalize_L2(embeddings)

# ---------------- CREATE FAISS INDEX ----------------
dimension = embeddings.shape[1]
index = faiss.IndexFlatIP(dimension)
index.add(embeddings)
faiss.write_index(index, INDEX_FILE)
print("💾 FAISS index saved")

# ---------------- SAVE METADATA ----------------
with open(METADATA_FILE, "wb") as f:
    pickle.dump(all_chunks, f)
print("💾 Metadata saved")
print("\n🎉 Ingestion complete")