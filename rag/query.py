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
CHUNKS_FILE = "chunks.pkl"

model = SentenceTransformer("paraphrase-MiniLM-L3-v2")
client = Groq(api_key=os.getenv("GROQ_API_KEY"))

with open(CHUNKS_FILE, "rb") as f:
    chunks = pickle.load(f)

index = faiss.read_index(INDEX_FILE)


# -------- KEYWORD FILTER --------
def keyword_filter(chunks, query):
    tokens = re.sub(r"[^\w\s]", " ", query.lower()).split()
    return [
        c for c in chunks
        if any(t in c.lower() for t in tokens)
    ]


# -------- QUERY EXPANSION --------
def expand_query(q):
    return list(set([
        q,
        q.replace(".", " "),
        q.replace("section", ""),
        "Unit " + q
    ]))


# -------- MAIN ASK FUNCTION --------
def ask(question, k=20):
    expanded = expand_query(question)

    candidate_chunks = []
    for q in expanded:
        filtered = keyword_filter(chunks, q)
        candidate_chunks.extend(filtered)

    candidate_chunks = list(set(candidate_chunks))
    if not candidate_chunks:
        candidate_chunks = chunks

    embeddings = model.encode(candidate_chunks)
    temp_index = faiss.IndexFlatL2(embeddings.shape[1])
    temp_index.add(embeddings)

    q_embedding = model.encode([question])
    _, indices = temp_index.search(q_embedding, k)

    retrieved = [candidate_chunks[i] for i in indices[0]]
    context = "\n\n".join(retrieved)

    # -------- RERANK --------
    rerank_prompt = f"""
Select only the passages that best answer the question.

Question:
{question}

Passages:
{context}
"""

    reranked = client.chat.completions.create(
        model="llama-3.1-8b-instant",
        messages=[{"role": "user", "content": rerank_prompt}],
        temperature=0
    )

    context = reranked.choices[0].message.content

    # -------- FINAL ANSWER --------
    final_prompt = f"""
You are a strict academic assistant.

Rules:
- Use ONLY the context below.
- If the answer is missing, say:
  "The provided documents do not contain this information."
- Do NOT guess.

Context:
{context}

Question:
{question}
"""

    response = client.chat.completions.create(
        model="llama-3.1-8b-instant",
        messages=[
            {"role": "system", "content": "Strict academic assistant"},
            {"role": "user", "content": final_prompt}
        ],
        temperature=0.2
    )

    return response.choices[0].message.content


# -------- CLI ENTRY (for JS) --------
if __name__ == "__main__":
    question = sys.argv[1]
    answer = ask(question)

    print(json.dumps({
        "question": question,
        "answer": answer
    }))