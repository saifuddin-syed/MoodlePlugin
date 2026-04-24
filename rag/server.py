from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from typing import List, Dict, Optional
import pickle
import faiss
import os
import json
import random
from sentence_transformers import SentenceTransformer
from groq import Groq
from dotenv import load_dotenv

# ------------------------
# LOAD ENV
# ------------------------
load_dotenv()

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # dev only
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ------------------------
# LOAD MODEL + INDEX
# ------------------------
model = SentenceTransformer("paraphrase-MiniLM-L3-v2")

with open("chunks.pkl", "rb") as f:
    chunks = pickle.load(f)

index = faiss.read_index("faiss.index")

client = Groq(api_key=os.getenv("GROQ_API_KEY"))

# ------------------------
# LOAD DEMO TOPICS
# ------------------------
with open("demo_topics.json", "r", encoding="utf-8") as f:
    demo_topics = json.load(f)

topic_chunk_map = {}

for unit, sections in demo_topics.items():
    topic_chunk_map[unit] = {}

    for section, meta in sections.items():
        keywords = meta.get("keywords", [])
        topic_chunk_map[unit][section] = []

        for idx, chunk in enumerate(chunks):
            chunk_lower = chunk.lower()
            if any(kw.lower() in chunk_lower for kw in keywords):
                topic_chunk_map[unit][section].append(idx)

# ------------------------
# REQUEST MODELS
# ------------------------
class QuestionRequest(BaseModel):
    question: str
    history: List[Dict[str, str]] = Field(default_factory=list)


class QuizRequest(BaseModel):
    units: Optional[List[str]] = []
    sections: Optional[List[Dict[str, str]]] = []
    num_questions: int
    difficulty: str


class RecommendRequest(BaseModel):
    wrong_questions: List[str] = Field(default_factory=list)
    selected_topics: Optional[Dict[str, List[str]]] = Field(default_factory=dict)
    score: Optional[int] = None
    total: Optional[int] = None


class ExplainRequest(BaseModel):
    question: str
    options: List[str]
    correct_index: int


# ======================================================
# CHAT ENDPOINT (FIXED)
# ======================================================
@app.post("/ask")
def ask_question(data: QuestionRequest):

    question = data.question
    history = data.history[-6:]

    # === Embed ===
    q_embedding = model.encode([question], normalize_embeddings=True)

    # === FAISS Search ===
    scores, indices = index.search(q_embedding, 6)

    best_score = scores[0][0] if len(scores[0]) > 0 else 0

    if best_score < 0.15:
        return {
            "ok": True,
            "answer": "This question appears to be outside the scope of this course."
        }

    # ✅ SAFE INDEX HANDLING (FIX)
    valid_indices = [
        i for i in indices[0]
        if i != -1 and 0 <= i < len(chunks)
    ]

    if not valid_indices:
        return {
            "ok": True,
            "answer": "No relevant content found for this question."
        }

    retrieved = [chunks[i] for i in valid_indices]
    context = "\n\n".join(retrieved)

    # === Build messages ===
    messages = [
        {
            "role": "system",
            "content": (
                "You are an interactive university tutor.\n"
                "Keep answers short, simple, intuitive, and under 120 words.\n"
                "Give at most one example.\n"
                "End with one short follow-up question."
            )
        },
        {
            "role": "system",
            "content": f"Relevant material:\n{context}"
        }
    ]

    for msg in history:
        if "sender" in msg and "message" in msg:
            role = "assistant" if msg["sender"] == "bot" else "user"
            messages.append({
                "role": role,
                "content": msg["message"]
            })

    messages.append({
        "role": "user",
        "content": question
    })

    response = client.chat.completions.create(
        model="llama-3.1-8b-instant",
        messages=messages,
        temperature=0.3,
        max_tokens=220
    )

    return {
        "ok": True,
        "answer": response.choices[0].message.content
    }


# ======================================================
# GET TOPICS
# ======================================================
@app.get("/topics")
def get_topics():
    formatted = []

    for unit, sections in demo_topics.items():
        formatted.append({
            "unit": unit,
            "sections": [
                {
                    "section": sec,
                    "title": sections[sec]["title"]
                }
                for sec in sections
            ]
        })

    return {"topics": formatted}


# ======================================================
# GENERATE QUIZ
# ======================================================
@app.post("/generate-quiz")
def generate_quiz(data: QuizRequest):

    try:
        units = data.units or []
        sections = data.sections or []

        candidate_ids = set()

        for s in sections:
            u = s.get("unit")
            sec = s.get("section")

            if u in topic_chunk_map and sec in topic_chunk_map[u]:
                candidate_ids.update(topic_chunk_map[u][sec])

        if not sections and units:
            for u in units:
                if u in topic_chunk_map:
                    for sec in topic_chunk_map[u]:
                        candidate_ids.update(topic_chunk_map[u][sec])

        if not candidate_ids:
            return {"ok": False, "error": "No content found."}

        num_questions = min(max(1, data.num_questions), 10)
        candidate_list = list(candidate_ids)

        questions = []

        for _ in range(num_questions):

            chosen_ids = random.sample(candidate_list, min(5, len(candidate_list)))
            context = "\n\n".join([chunks[i] for i in chosen_ids])

            prompt = f"""
Generate ONE MCQ from this material.

Return JSON:
{{
  "question": "",
  "options": ["A","B","C","D"],
  "answer_index": 0
}}

Material:
{context}
"""

            response = client.chat.completions.create(
                model="llama-3.1-8b-instant",
                messages=[{"role": "user", "content": prompt}],
                temperature=0.3,
                max_tokens=300
            )

            try:
                mcq = json.loads(response.choices[0].message.content)

                if "question" in mcq:
                    questions.append(mcq)
            except:
                continue

        return {"ok": True, "questions": questions}

    except Exception as e:
        print("generate_quiz error:", e)
        return {"ok": False, "error": "Quiz generation failed."}


# ======================================================
# RECOMMENDATION
# ======================================================
@app.post("/recommend-quiz")
def recommend_quiz(payload: RecommendRequest):

    wrong_qs = payload.wrong_questions or []

    if len(wrong_qs) == 0:
        return {"ok": True, "recommendation": "Good work."}

    prompt = "Give a short recommendation for improvement:\n"
    for q in wrong_qs:
        prompt += f"- {q}\n"

    resp = client.chat.completions.create(
        model="llama-3.1-8b-instant",
        messages=[{"role": "user", "content": prompt}],
        max_tokens=70
    )

    return {
        "ok": True,
        "recommendation": resp.choices[0].message.content
    }


# ======================================================
# EXPLAIN QUIZ
# ======================================================
@app.post("/explain-quiz")
def explain_quiz(data: ExplainRequest):

    try:
        prompt = f"""
Explain each option briefly.

Question: {data.question}
Options: {data.options}
Correct: {data.correct_index}

Return JSON:
{{"explanations": []}}
"""

        response = client.chat.completions.create(
            model="llama-3.1-8b-instant",
            messages=[{"role": "user", "content": prompt}],
            max_tokens=200
        )

        return json.loads(response.choices[0].message.content)

    except:
        return {"ok": False, "error": "Failed to explain"}