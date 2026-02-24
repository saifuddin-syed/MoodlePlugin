from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import pickle
import faiss
import os
import re
import random
import json
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
    allow_origins=["*"],    # dev only
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ------------------------
# LOAD MODEL + INDEX ONCE
# ------------------------
model = SentenceTransformer("paraphrase-MiniLM-L3-v2")

with open("chunks.pkl", "rb") as f:
    chunks = pickle.load(f)

index = faiss.read_index("faiss.index")

client = Groq(api_key=os.getenv("GROQ_API_KEY"))

# ------------------------
# LOAD DEMO TOPICS METADATA
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

            # If ANY keyword appears in chunk
            if any(kw.lower() in chunk_lower for kw in keywords):
                topic_chunk_map[unit][section].append(idx)

# ------------------------
# REQUEST SCHEMAS
# ------------------------

from typing import List, Dict
from pydantic import Field

class QuestionRequest(BaseModel):
    question: str
    history: List[Dict[str, str]] = Field(default_factory=list)

class QuizRequest(BaseModel):
    units: list[str] = []
    sections: list[dict] = []
    num_questions: int
    difficulty: str


# ======================================================
# CHAT ENDPOINT
# ======================================================

@app.post("/ask")
def ask_question(data: QuestionRequest):

    question = data.question
    history = data.history[-6:]  # last 6 turns only

    # === Retrieve RAG Context ===
    q_embedding = model.encode([question], normalize_embeddings=True)
    scores, indices = index.search(q_embedding, 6)

    best_score = scores[0][0]

    if best_score < 0.15:
        return {
            "ok": True,
            "answer": "This question appears to be outside the scope of this course."
        }

    retrieved = [chunks[i] for i in indices[0]]
    context = "\n\n".join(retrieved)

    # === Build Conversation ===
    messages = [
        {
            "role": "system",
            "content": (
                "You are an interactive university tutor.\n\n"
                "Teaching style rules:\n"
                "1. Keep responses SHORT (4–8 sentences max).\n"
                "2. Explain intuitively, not like a textbook.\n"
                "3. Use simple language.\n"
                "4. Give at most ONE small example.\n"
                "5. Always end with ONE short follow-up question.\n"
                "6. Do NOT over-explain.\n"
                "7. Do NOT give long bullet lists.\n"
                "8. Encourage the student to think.\n"
                "9. Never mention documents or syllabus.\n"
                "10. If something asked is completely irrelevant to the topic, just answer that, This is out of scope.\n"
            )
        },
        {
            "role": "system",
            "content": f"Relevant course material:\n{context}"
        }
    ]

    # Add previous chat
    for msg in history:
        messages.append(msg)

    # Add current question
    messages.append({
        "role": "user",
        "content": question
    })

    response = client.chat.completions.create(
        model="llama-3.1-8b-instant",
        messages=messages,
        temperature=0.3,
        max_tokens=300
    )

    return {
        "ok": True,
        "answer": response.choices[0].message.content
    }


# ======================================================
# GET AVAILABLE UNITS (For Dropdown)
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
# GENERATE QUIZ (Unit-Level Selection)
# ======================================================
from typing import Optional


class QuizRequest(BaseModel):
    units: Optional[List[str]] = []
    sections: Optional[List[Dict[str, str]]] = []
    num_questions: int
    difficulty: str


@app.post("/generate-quiz")
def generate_quiz(data: QuizRequest):

    try:
        units = data.units or []
        sections = data.sections or []

        candidate_ids = set()

        # If specific sections selected
        for s in sections:
            u = s.get("unit")
            sec = s.get("section")

            if u in topic_chunk_map and sec in topic_chunk_map[u]:
                candidate_ids.update(topic_chunk_map[u][sec])

        # If only units selected
        if not sections and units:
            for u in units:
                if u in topic_chunk_map:
                    for sec in topic_chunk_map[u]:
                        candidate_ids.update(topic_chunk_map[u][sec])

        if not candidate_ids:
            return {"ok": False, "error": "No content found for selected topics."}

        num_questions = min(max(1, data.num_questions), 10)

        questions = []
        candidate_list = list(candidate_ids)

        for _ in range(num_questions):

            chosen_ids = random.sample(candidate_list, min(5, len(candidate_list)))
            context = "\n\n".join([chunks[i] for i in chosen_ids])
            if len(candidate_ids) < 1:
                return {
                    "ok": False,
                    "error": "No relevant material found for selected topics."
                }

            prompt = f"""
You are a university-level exam setter.

Generate ONE high-quality multiple choice question STRICTLY from the material below.

Rules:
- Question MUST test understanding of a concept.
- DO NOT ask about document structure (units, sections, formatting).
- DO NOT ask about "where something is mentioned".
- Focus only on conceptual or theoretical content.
- 4 options.
- Only 1 correct answer.
- Wrong options must be plausible but clearly incorrect.
- Avoid trivial wording.
- Return STRICT JSON only:

{{
  "question": "text",
  "options": ["A","B","C","D"],
  "answer_index": 0
}}

Course Material:
{context}
"""

            response = client.chat.completions.create(
                model="llama-3.1-8b-instant",
                messages=[
                    {"role": "system", "content": "You are a university-level exam setter."},
                    {"role": "user", "content": prompt}
                ],
                temperature=0.3,
                max_tokens=300
            )

            try:
                mcq = json.loads(response.choices[0].message.content)
                if "question" in mcq and "options" in mcq and "answer_index" in mcq:
                    questions.append(mcq)
            except:
                continue

        return {"ok": True, "questions": questions}

    except Exception as e:
        print("generate_quiz error:", e)
        return {"ok": False, "error": "Quiz generation failed."}