import sys
import os
import json
import pickle
import faiss
import re
from sentence_transformers import SentenceTransformer
from groq import Groq
from dotenv import load_dotenv

load_dotenv()

INDEX_FILE = "faiss.index"
CHUNKS_FILE = "chunks_metadata.pkl"

# Must match injest.py: all-MiniLM-L6-v2 for ingest, but the saved index
# was built with that model. query.py uses a different model (MiniLM-L3-v2).
# FIX: use the SAME model as injest.py so embeddings are compatible.
model = SentenceTransformer("all-MiniLM-L6-v2")

client = Groq(api_key=os.getenv("GROQ_API_KEY"))

with open(CHUNKS_FILE, "rb") as f:
    chunks = pickle.load(f)

# The FAISS index built by injest.py is IndexFlatIP (cosine/inner product).
# We must use the same normalization when querying.
index = faiss.read_index(INDEX_FILE)


# -------- KEYWORD FILTER --------
def keyword_filter(all_chunks, query):
    tokens = re.sub(r"[^\w\s]", " ", query.lower()).split()
    return [
        c["text"] if isinstance(c, dict) else c
        for c in all_chunks
        if any(t in (c["text"] if isinstance(c, dict) else c).lower() for t in tokens)
    ]


# -------- QUERY EXPANSION --------
def expand_query(q):
    return list(set([
        q,
        q.replace(".", " "),
        q.replace("section", "").strip(),
        "Unit " + q,
    ]))


# -------- STRIP JSON FENCES --------
def strip_json_fences(text):
    text = text.strip()
    text = re.sub(r"^```(?:json)?\s*", "", text, flags=re.IGNORECASE)
    text = re.sub(r"\s*```$", "", text)
    return text.strip()


# -------- MAIN ASK FUNCTION --------
def ask(question, k=20):
    expanded = expand_query(question)

    # Keyword filter across all chunks (work on text strings)
    candidate_texts = []
    for q in expanded:
        filtered = keyword_filter(chunks, q)
        candidate_texts.extend(filtered)

    candidate_texts = list(set(candidate_texts))
    if not candidate_texts:
        # Fall back to full chunk texts
        candidate_texts = [c["text"] if isinstance(c, dict) else c for c in chunks]

    # Embed candidates — MUST normalize for IndexFlatIP
    import numpy as np
    embeddings = model.encode(candidate_texts, convert_to_numpy=True, normalize_embeddings=True)

    # Build temporary IP index for candidate re-ranking
    temp_index = faiss.IndexFlatIP(embeddings.shape[1])
    temp_index.add(embeddings)

    # Embed query with same normalization
    q_embedding = model.encode([question], convert_to_numpy=True, normalize_embeddings=True)
    _, indices = temp_index.search(q_embedding, min(k, len(candidate_texts)))

    retrieved = [candidate_texts[i] for i in indices[0] if i != -1]
    context = "\n\n".join(retrieved)

    # -------- RERANK via LLM --------
    rerank_prompt = f"""Select ONLY the passages most relevant to the question below.
Return the selected passages verbatim, separated by blank lines.
Do not add commentary.

Question:
{question}

Passages:
{context}
"""

    try:
        reranked_resp = client.chat.completions.create(
            model="llama-3.3-70b-versatile",
            messages=[{"role": "user", "content": rerank_prompt}],
            temperature=0,
            max_tokens=800,
        )
        context = reranked_resp.choices[0].message.content.strip()
    except Exception as e:
        print(f"Rerank failed (using raw context): {e}", file=sys.stderr)

    # -------- FINAL ANSWER --------
    final_prompt = f"""You are a strict academic assistant.

Rules:
- Use ONLY the context below.
- If the answer is not in the context, say exactly:
  "The provided documents do not contain this information."
- Do NOT guess or add outside knowledge.

Context:
{context}

Question:
{question}
"""

    response = client.chat.completions.create(
        model="llama-3.3-70b-versatile",
        messages=[
            {"role": "system", "content": "You are a strict academic assistant."},
            {"role": "user", "content": final_prompt}
        ],
        temperature=0.2,
        max_tokens=600,
    )

    return response.choices[0].message.content.strip()


# -------- CLI ENTRY --------
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No question provided"}))
        sys.exit(1)

    question = sys.argv[1]
    try:
        answer = ask(question)
        print(json.dumps({"question": question, "answer": answer}))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)