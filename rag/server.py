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
                "10. If something asked is completely irrelevant, say: This is out of scope.\n"
                "11. Structure answers using short paragraphs.\n"
                "12. Use at most 2–3 short blocks of text.\n"
                "13. If defining something, follow this format:\n"
                "   - One-line definition.\n"
                "   - One short intuitive explanation.\n"
                "   - One small example (1–2 lines only).\n"
                "14. Avoid long analogies.\n"
                "15. Avoid ASCII diagrams.\n"
                "16. Avoid repeating ideas.\n"
                "17. Keep total response under 120 words.\n"
            )
        },
        {
            "role": "system",
            "content": f"Relevant course material:\n{context}"
        }
    ]

   # Add previous chat properly formatted
    for msg in history:
        if "sender" in msg and "message" in msg:
            role = "assistant" if msg["sender"] == "bot" else "user"
            messages.append({
                "role": role,
                "content": msg["message"]
            })

    # Add current question
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

                    # 🔥 ADD UNIT + TOPIC HERE
                    # pick random section used for this question

                    selected_unit = None
                    selected_topic = None

                    if sections:
                        chosen = random.choice(sections)
                        selected_unit = chosen.get("unit", "General")
                        selected_topic = chosen.get("section", "General")

                    elif units:
                        selected_unit = random.choice(units)
                        selected_topic = "General"

                    else:
                        selected_unit = "General"
                        selected_topic = "General"

                    mcq["unit"] = selected_unit
                    mcq["topic"] = selected_topic

                    questions.append(mcq)
            except:
                continue

        return {"ok": True, "questions": questions}

    except Exception as e:
        print("generate_quiz error:", e)
        return {"ok": False, "error": "Quiz generation failed."}


# at top imports (if not already)
from pydantic import BaseModel, Field

# Add this model (can place near other Pydantic models)
class RecommendRequest(BaseModel):
    wrong_questions: List[str] = Field(default_factory=list)
    selected_topics: Optional[Dict[str, List[str]]] = Field(default_factory=dict)
    score: Optional[int] = None
    total: Optional[int] = None

# Add endpoint
@app.post("/recommend-quiz")
def recommend_quiz(payload: RecommendRequest):
    # Build very short prompt focused on weakness
    # Keep request compact (we only need wrong questions and topic ids)
    wrong_qs = payload.wrong_questions or []
    selected_topics = payload.selected_topics or {}
    score = payload.score
    total = payload.total

    if len(wrong_qs) == 0:
        return {"ok": True, "recommendation": "Good work — no incorrect answers to analyze."}

    # Compose concise prompt
    sample_prompt = (
        "You are a helpful, concise tutor. Produce a 1–2 sentence recommendation (very short) "
        "for a student who got these questions wrong. Focus on the likely weak concepts they need "
        "to revise and one short next-step action they can take (e.g., review X section, try exercises). "
        "Output only the recommendation (no preamble).\n\n"
        f"Selected topics mapping (unit -> sections): {json.dumps(selected_topics)}\n\n"
        f"Wrong question texts:\n"
    )

    for i, q in enumerate(wrong_qs, start=1):
        sample_prompt += f"{i}. {q}\n"

    # ask Groq
    try:
        resp = client.chat.completions.create(
            model="llama-3.1-8b-instant",
            messages=[
                {"role": "system", "content": "You are a concise tutor producing a short recommendation."},
                {"role": "user", "content": sample_prompt},
            ],
            temperature=0.2,
            max_tokens=70  # enough for 1-2 short sentences
        )
        recommendation = resp.choices[0].message.content.strip()
        # sanitize: if empty, fallback
        if not recommendation:
            recommendation = "Revise the related unit sections and practice the example problems."
        return {"ok": True, "recommendation": recommendation}
    except Exception as e:
        print("recommend-quiz error:", e)
        return {"ok": False, "error": "Recommendation generation failed."}