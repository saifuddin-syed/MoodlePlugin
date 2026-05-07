from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from typing import List, Dict, Optional
import pickle
import faiss
import numpy as np
import os
import re
import random
import json
import time
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
    allow_origins=["*"],   # dev only
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ------------------------
# LOAD MODEL + INDEX ONCE
# ------------------------
# ✅ FIX 1: Use the SAME model as injest.py.
# paraphrase-MiniLM-L3-v2 has a DIFFERENT embedding space even though
# the vector dimension is the same — this was silently breaking all retrieval.
model = SentenceTransformer("all-MiniLM-L6-v2")

with open("chunks_metadata.pkl", "rb") as f:
    _raw_chunks = pickle.load(f)


def _chunk_corruption_score(text: str) -> int:
    """Same logic as injest.py — filters garbled OCR / bad pdfplumber output."""
    null_bytes  = text.count('\x00')
    replacement = text.count('\uffff')
    smashed     = len(re.findall(r'[a-z]{20,}', text))
    tokens      = text.split()
    long_bad    = sum(
        1 for t in tokens
        if len(t) > 25
        and not t.startswith('[')
        and '/' not in t
        and 'http' not in t
    )
    return null_bytes + (replacement * 3) + (smashed * 5) + (long_bad * 3)


_CORRUPTION_THRESHOLD = 15

# Filter out corrupted chunks and rebuild a clean FAISS index that matches
# the filtered list — so chunk indices stay in sync with the index.
chunks = [c for c in _raw_chunks if _chunk_corruption_score(c.get("text", "")) < _CORRUPTION_THRESHOLD]

_dropped = len(_raw_chunks) - len(chunks)
print(f"✅ Chunks loaded: {len(chunks)} usable  ({_dropped} corrupted chunks dropped)")

# Rebuild FAISS index from clean chunks only
print("🔨 Rebuilding FAISS index from clean chunks...")
_clean_texts = [c["text"] for c in chunks]
_embeddings  = model.encode(
    _clean_texts,
    batch_size=32,
    show_progress_bar=False,
    convert_to_numpy=True,
    normalize_embeddings=True,
)
faiss.normalize_L2(_embeddings)
index = faiss.IndexFlatIP(_embeddings.shape[1])
index.add(_embeddings)
print(f"✅ Index rebuilt with {index.ntotal} vectors")

client = Groq(api_key=os.getenv("GROQ_API_KEY"))

# ------------------------
# LOAD DEMO TOPICS METADATA
# ------------------------
with open("demo_topics.json", "r", encoding="utf-8") as f:
    demo_topics = json.load(f)

# Build flat keyword list for flag-message
TOPIC_KEYWORDS_FLAT = []
for unit, sections in demo_topics.items():
    for sec_id, data in sections.items():
        TOPIC_KEYWORDS_FLAT.append(data.get("title", ""))
        TOPIC_KEYWORDS_FLAT.extend(data.get("keywords", []))
TOPIC_KEYWORDS_FLAT = list(set(TOPIC_KEYWORDS_FLAT))

# ✅ FIX 2: Build topic → chunk index map
# Also pre-compute per-section embeddings for semantic chunk retrieval.
topic_chunk_map: Dict[str, Dict[str, List[int]]] = {}

for idx, chunk in enumerate(chunks):
    unit    = chunk.get("unit")
    section = chunk.get("section")
    if not unit or not section:
        continue
    topic_chunk_map.setdefault(unit, {}).setdefault(section, []).append(idx)

# ✅ FIX 3: Pre-extract all chunk text vectors so we can do per-section
# semantic re-ranking quickly without hitting the full FAISS index.
# We store them lazily as a dict keyed by chunk index.
_chunk_text_cache: Dict[int, str] = {i: c["text"] for i, c in enumerate(chunks)}


# ======================================================
# HELPERS
# ======================================================

def _sanitise_chunk(text: str) -> str:
    """Light clean of a chunk before sending to the LLM."""
    import unicodedata
    # Ligatures
    ligs = {'\ufb00':'ff','\ufb01':'fi','\ufb02':'fl','\ufb03':'ffi','\ufb04':'ffl'}
    for bad, good in ligs.items():
        text = text.replace(bad, good)
    text = unicodedata.normalize('NFC', text)
    # Rejoin hyphenated line-breaks
    text = re.sub(r'-\s*\n\s*', '', text)
    # Collapse excess whitespace
    text = re.sub(r' {2,}', ' ', text)
    text = re.sub(r'\n{3,}', '\n\n', text)
    return text.strip()


    text = text.strip()
    text = re.sub(r"^```(?:json)?\s*", "", text, flags=re.IGNORECASE)
    text = re.sub(r"\s*```$", "", text)
    return text.strip()


def safe_json_parse(text: str) -> Optional[dict]:
    try:
        return json.loads(strip_json_fences(text))
    except Exception:
        match = re.search(r"\{.*\}", text, re.DOTALL)
        if match:
            try:
                return json.loads(match.group())
            except Exception:
                pass
    return None


def call_groq_with_retry(messages, model_name="llama-3.3-70b-versatile",
                          temperature=0.3, max_tokens=600,
                          retries=3, delay=1.5):
    last_err = None
    for attempt in range(retries):
        try:
            response = client.chat.completions.create(
                model=model_name,
                messages=messages,
                temperature=temperature,
                max_tokens=max_tokens,
            )
            return response.choices[0].message.content.strip()
        except Exception as e:
            last_err = e
            if attempt < retries - 1:
                time.sleep(delay * (attempt + 1))
    raise RuntimeError(f"Groq API failed after {retries} retries: {last_err}")


# ✅ FIX 4: Semantic retrieval within a candidate pool.
# Given a list of chunk IDs (belonging to the selected sections),
# rank them by cosine similarity to a query embedding and return the top-k.
def semantic_top_k(candidate_ids: List[int], query_embedding: np.ndarray, k: int) -> List[int]:
    """
    Re-rank candidate_ids by cosine similarity to query_embedding.
    Falls back to random sample if the pool is too small.
    """
    if len(candidate_ids) <= k:
        return candidate_ids

    candidate_texts = [_chunk_text_cache[i] for i in candidate_ids]
    cand_embeddings = model.encode(
        candidate_texts,
        convert_to_numpy=True,
        normalize_embeddings=True,
        show_progress_bar=False,
    )

    # query_embedding is already L2-normalised; dot product = cosine similarity
    scores = cand_embeddings @ query_embedding.T   # shape (N,)
    ranked = sorted(zip(scores, candidate_ids), reverse=True)
    return [cid for _, cid in ranked[:k]]


# ✅ FIX 5: Build a rich semantic query for a topic so retrieval is meaningful.
def topic_query(unit: str, section: str) -> str:
    """
    Construct a natural-language query for a (unit, section) pair
    using the topic title and keywords from demo_topics.json.
    This gives semantic search a meaningful signal instead of a blank.
    """
    entry = demo_topics.get(unit, {}).get(section, {})
    title = entry.get("title", section)
    keywords = entry.get("keywords", [])
    kw_str = ", ".join(keywords[:6]) if keywords else ""
    query = f"{title}"
    if kw_str:
        query += f": {kw_str}"
    return query


# ======================================================
# REQUEST SCHEMAS
# ======================================================

class QuestionRequest(BaseModel):
    question: str
    history: List[Dict[str, str]] = Field(default_factory=list)


class QuizRequest(BaseModel):
    units: Optional[List[str]] = Field(default_factory=list)
    sections: Optional[List[Dict[str, str]]] = Field(default_factory=list)
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


class FlagRequest(BaseModel):
    message: str


# ======================================================
# CHAT ENDPOINT
# ======================================================

def _sanitise_input(text: str, max_len: int = 500) -> str:
    """Clean incoming chat text: strip control chars, collapse whitespace, truncate."""
    if not text:
        return ""
    text = re.sub(r'[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]', '', text)
    text = re.sub(r'[ \t]+', ' ', text)
    text = re.sub(r'\n+', ' ', text)
    return text.strip()[:max_len]


def _looks_like_garbage(text: str) -> bool:
    """
    Return True for inputs that are clearly not real questions:
    - Digit-sequence dumps like "25 27 29 31 33 ..."  (leaked chunk indices)
    - Fewer than 2 real alphabetic words
    - More than 60% of characters are digits + spaces
    """
    if len(text) < 4:
        return True
    words = [w for w in text.split() if any(c.isalpha() for c in w)]
    if len(words) < 2:
        return True
    digit_space = sum(1 for c in text if c.isdigit() or c == ' ')
    if digit_space / len(text) > 0.60:
        return True
    alpha = sum(c.isalpha() for c in text)
    if alpha / len(text) < 0.25:
        return True
    return False


@app.post("/ask")
def ask_question(data: QuestionRequest):

    # ── 1. Sanitise & validate input ──────────────────────────────────────────
    question = _sanitise_input(data.question or "", max_len=500)

    if not question:
        return {"ok": True, "answer": "Please type a question so I can help you."}

    if _looks_like_garbage(question):
        print(f"  [ASK] Garbage input rejected: {question[:80]!r}")
        return {
            "ok": True,
            "answer": "I didn\'t quite catch that — could you rephrase your question?"
        }

    # ── 2. Sanitise history ───────────────────────────────────────────────────
    clean_history = []
    for msg in (data.history or [])[-6:]:
        if not isinstance(msg, dict):
            continue
        sender  = msg.get("sender", "")
        content = _sanitise_input(msg.get("message", ""), max_len=400)
        if sender and content and not _looks_like_garbage(content):
            clean_history.append({"sender": sender, "message": content})

    # ── 3. Embed & FAISS search ───────────────────────────────────────────────
    q_embedding = model.encode([question], normalize_embeddings=True)
    scores, indices = index.search(q_embedding, 6)

    best_score = float(scores[0][0]) if len(scores[0]) > 0 else 0.0
    if best_score < 0.15:
        return {
            "ok": True,
            "answer": "That question doesn\'t seem to be covered in this course. Try asking about a topic from the syllabus."
        }

    valid_indices = [int(i) for i in indices[0] if i != -1 and 0 <= i < len(chunks)]
    if not valid_indices:
        return {"ok": True, "answer": "No relevant content found for this question."}

    # ── 4. Build context ──────────────────────────────────────────────────────
    retrieved = []
    for i in valid_indices:
        raw = chunks[i]["text"] if isinstance(chunks[i], dict) else chunks[i]
        retrieved.append(_sanitise_chunk(raw))
    context = "\n\n".join(retrieved)

    # ── 5. Build messages ─────────────────────────────────────────────────────
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
        {"role": "system", "content": f"Course material:\n{context}"}
    ]

    for msg in clean_history:
        role = "assistant" if msg["sender"] == "bot" else "user"
        messages.append({"role": role, "content": msg["message"]})

    messages.append({"role": "user", "content": question})

    # ── 6. Call LLM ───────────────────────────────────────────────────────────
    try:
        answer = call_groq_with_retry(messages, temperature=0.3, max_tokens=220)
    except Exception as e:
        return {"ok": False, "error": str(e)}

    return {"ok": True, "answer": answer}


# ======================================================
# TOPICS ENDPOINT
# ======================================================

@app.get("/topics")
def get_topics():
    formatted = []
    for unit, sections in demo_topics.items():
        formatted.append({
            "unit": unit,
            "sections": [
                {"section": sec, "title": sections[sec]["title"]}
                for sec in sections
            ]
        })
    return {"topics": formatted}


# ======================================================
# QUIZ PROMPT BUILDER
# ======================================================

def _build_quiz_prompt(context: str, difficulty: str, existing_questions: List[str],
                        topic_hint: str = "") -> str:
    avoid_block = ""
    if existing_questions:
        avoid_list = "\n".join(f"- {q}" for q in existing_questions)
        avoid_block = f"\nDo NOT generate any of these questions again:\n{avoid_list}\n"

    difficulty_hint = {
        "easy":   "Test basic recall and definitions.",
        "medium": "Test understanding and application of concepts.",
        "hard":   "Test analysis, comparison, or edge cases.",
    }.get(difficulty.lower(), "Test understanding of concepts.")

    topic_line = f"Topic focus: {topic_hint}\n" if topic_hint else ""

    return f"""You are a university-level exam setter generating a {difficulty} multiple choice question.

{difficulty_hint}
{topic_line}{avoid_block}
Rules:
- Generate exactly ONE question from the course material below.
- The question MUST test conceptual or theoretical understanding.
- DO NOT ask about document structure, units, sections, or formatting.
- DO NOT ask "where is X mentioned" or "which section covers X".
- Provide exactly 4 answer options (A, B, C, D).
- Exactly 1 option must be correct; others must be plausible but wrong.
- answer_index is 0-based (0=A, 1=B, 2=C, 3=D).
- Return ONLY valid JSON with NO markdown fences, NO extra text.

Required JSON format:
{{"question": "...", "options": ["A text","B text","C text","D text"], "answer_index": 0}}

Course Material:
{context}"""


# ======================================================
# FAILSAFE: Generate MCQ from topic metadata only
# ======================================================

def _build_failsafe_prompt(unit: str, sec: str, difficulty: str,
                            existing_questions: List[str]) -> str:
    """Build prompt using only topic title + keywords — no chunks needed."""
    entry    = demo_topics.get(unit, {}).get(sec, {})
    title    = entry.get("title", sec)
    keywords = entry.get("keywords", [])

    avoid_block = ""
    if existing_questions:
        avoid_list = "\n".join(f"- {q}" for q in existing_questions[-8:])
        avoid_block = f"\nDo NOT generate any of these questions:\n{avoid_list}\n"

    difficulty_hint = {
        "easy":   "Test basic recall and definitions of the topic.",
        "medium": "Test understanding and application of the concept.",
        "hard":   "Test analysis, edge cases, or comparison with related concepts.",
    }.get(difficulty.lower(), "Test understanding of the concept.")

    kw_str = ", ".join(keywords) if keywords else title

    return (
        f"You are a university-level exam setter.\n\n"
        f"Topic: {title}\n"
        f"Key concepts: {kw_str}\n"
        f"Difficulty: {difficulty}\n\n"
        f"{difficulty_hint}\n"
        f"{avoid_block}\n"
        f"Rules:\n"
        f"- Generate exactly ONE multiple choice question about the topic above.\n"
        f"- Use your own subject knowledge — do NOT reference any document or passage.\n"
        f"- The question MUST test conceptual understanding, not trivia.\n"
        f"- DO NOT mention the word unit, section, document, text, or passage.\n"
        f"- Provide exactly 4 answer options.\n"
        f"- Exactly 1 must be correct; the other 3 must be plausible but wrong.\n"
        f"- answer_index is 0-based (0=A, 1=B, 2=C, 3=D).\n"
        f"- Return ONLY valid JSON, no markdown fences, no extra text.\n\n"
        f'Required format:\n'
        f'{{"question": "...", "options": ["A text","B text","C text","D text"], "answer_index": 0}}'
    )


def _generate_failsafe_mcq(unit: str, sec: str, difficulty: str,
                             existing_questions: List[str]) -> Optional[dict]:
    """
    Generate one MCQ from topic metadata alone when all chunk-based attempts fail.
    Returns parsed MCQ dict on success, None on failure.
    """
    print(f"  [FAILSAFE] Triggered for {unit} | {sec}")

    prompt = _build_failsafe_prompt(unit, sec, difficulty, existing_questions)
    messages = [
        {
            "role": "system",
            "content": (
                "You are a university exam setter with deep subject-matter knowledge. "
                "Always respond with ONLY a valid JSON object — no markdown, no explanation."
            )
        },
        {"role": "user", "content": prompt}
    ]

    meta_patterns = [
        r"\bunit\b", r"\bsection\b", r"\bmentioned\b",
        r"\bchapter\b", r"\bdocument\b", r"\btext\b",
        r"\bpassage\b", r"\bwhich (part|topic)\b",
    ]

    for fs_attempt in range(3):
        try:
            raw = call_groq_with_retry(
                messages,
                temperature=0.6 + fs_attempt * 0.1,
                max_tokens=500,
                retries=2,
                delay=1.0,
            )
        except Exception as e:
            print(f"  [FAILSAFE] Attempt {fs_attempt+1}: API error: {e}")
            continue

        mcq = safe_json_parse(raw)
        if not mcq:
            print(f"  [FAILSAFE] Attempt {fs_attempt+1}: JSON parse failed. Raw: {raw[:150]}")
            continue

        q_text  = mcq.get("question", "")
        opts    = mcq.get("options", [])
        ans_idx = mcq.get("answer_index")

        if (not q_text
                or not isinstance(opts, list)
                or len(opts) != 4
                or ans_idx is None
                or not isinstance(ans_idx, int)
                or not (0 <= ans_idx <= 3)):
            print(f"  [FAILSAFE] Attempt {fs_attempt+1}: Invalid MCQ structure.")
            continue

        if any(re.search(p, q_text, re.I) for p in meta_patterns):
            print(f"  [FAILSAFE] Attempt {fs_attempt+1}: Meta question rejected.")
            continue

        mcq["unit"]      = unit
        mcq["topic"]     = sec
        mcq["_failsafe"] = True   # flag for analytics / logging
        print(f"  [FAILSAFE] Succeeded on attempt {fs_attempt+1}.")
        return mcq

    print(f"  [FAILSAFE] All attempts exhausted — slot will be skipped.")
    return None


@app.post("/generate-quiz")
def generate_quiz(data: QuizRequest):
    print("Incoming Request Data:", data.dict())

    try:
        units      = data.units or []
        sections   = data.sections or []
        difficulty = data.difficulty or "medium"

        # ---- Collect candidate chunk IDs per (unit, section) slot ----
        # ✅ FIX 6: Keep track of which (unit, section) each candidate came from
        # so we can do topic-targeted semantic retrieval per question.
        slot_map: Dict[tuple, List[int]] = {}  # (unit, section) → [chunk_ids]

        if sections:
            for s in sections:
                u   = s.get("unit")
                sec = s.get("section")
                if u and sec:
                    ids = topic_chunk_map.get(u, {}).get(sec, [])
                    if ids:
                        slot_map[(u, sec)] = ids
                    else:
                        print(f"  ⚠️ No chunks found for {u} | {sec}")

        elif units:
            for u in units:
                for sec, ids in topic_chunk_map.get(u, {}).items():
                    if ids:
                        slot_map[(u, sec)] = ids

        # ✅ FIX 7: Fallback — if a section has NO indexed chunks, do a global
        # semantic search against the full FAISS index to find the nearest chunks.
        if sections and not slot_map:
            print("  ⚠️ All selected sections are empty in topic_chunk_map — using semantic fallback.")
            for s in sections:
                u   = s.get("unit")
                sec = s.get("section")
                if not u or not sec:
                    continue
                query_text   = topic_query(u, sec)
                q_emb        = model.encode([query_text], normalize_embeddings=True)
                _, top_ids   = index.search(q_emb, 15)
                valid        = [int(i) for i in top_ids[0] if i != -1 and i < len(chunks)]
                if valid:
                    slot_map[(u, sec)] = valid

        if not slot_map:
            return {"ok": False, "error": "No content found for selected topics."}

        # Flat pool for fallback when a slot is exhausted
        all_candidate_ids = list({cid for ids in slot_map.values() for cid in ids})
        slots             = list(slot_map.keys())

        num_questions = min(max(1, data.num_questions), 20)
        questions: List[dict] = []
        existing_question_texts: List[str] = []
        used_chunk_ids: set = set()

        MAX_ATTEMPTS_PER_Q = 5
        CHUNKS_PER_Q       = 5   # context chunks per question

        for q_index in range(num_questions):

            # Round-robin slot assignment → even topic distribution
            slot = slots[q_index % len(slots)]
            u, sec = slot
            topic_hint = demo_topics.get(u, {}).get(sec, {}).get("title", sec)

            # Build a semantic query for this topic
            query_text = topic_query(u, sec)
            q_emb      = model.encode([query_text], normalize_embeddings=True)

            # ✅ FIX 8: Semantic ranking within the section's chunk pool.
            section_pool = slot_map[slot]
            unused       = [i for i in section_pool if i not in used_chunk_ids]
            pool         = unused if len(unused) >= CHUNKS_PER_Q else section_pool

            # If section pool is tiny, augment with global semantic search
            if len(pool) < CHUNKS_PER_Q:
                _, global_ids = index.search(q_emb, 20)
                extra = [int(i) for i in global_ids[0]
                         if i != -1 and i < len(chunks) and i not in pool]
                pool = pool + extra[:CHUNKS_PER_Q]

            ranked_pool = semantic_top_k(pool, q_emb[0], k=15)

            success = False

            for attempt in range(MAX_ATTEMPTS_PER_Q):
                # Pick from top of ranked pool (with a little randomness for variety)
                top_n      = min(10, len(ranked_pool))
                chosen_ids = random.sample(ranked_pool[:top_n], min(CHUNKS_PER_Q, top_n))
                context    = "\n\n".join(_sanitise_chunk(chunks[i]["text"]) for i in chosen_ids)

                prompt = _build_quiz_prompt(
                    context, difficulty, existing_question_texts, topic_hint
                )

                messages = [
                    {
                        "role": "system",
                        "content": (
                            "You are a university exam setter. "
                            "You always respond with ONLY a valid JSON object, "
                            "no markdown, no explanation."
                        )
                    },
                    {"role": "user", "content": prompt}
                ]

                try:
                    raw = call_groq_with_retry(
                        messages,
                        temperature=0.5,
                        max_tokens=500,
                        retries=2,
                        delay=1.0,
                    )
                except Exception as e:
                    print(f"  Q{q_index+1} attempt {attempt+1}: API error: {e}")
                    continue

                mcq = safe_json_parse(raw)
                if not mcq:
                    print(f"  Q{q_index+1} attempt {attempt+1}: JSON parse failed. Raw: {raw[:200]}")
                    continue

                # Validate structure
                q_text  = mcq.get("question", "")
                opts    = mcq.get("options", [])
                ans_idx = mcq.get("answer_index")

                if (not q_text
                        or not isinstance(opts, list)
                        or len(opts) != 4
                        or ans_idx is None
                        or not isinstance(ans_idx, int)
                        or not (0 <= ans_idx <= 3)):
                    print(f"  Q{q_index+1} attempt {attempt+1}: Invalid MCQ structure.")
                    continue

                # Reject meta / structural questions
                meta_patterns = [
                    r"\bunit\b", r"\bsection\b", r"\bmentioned\b",
                    r"\bchapter\b", r"\bdocument\b", r"\btext\b",
                    r"\bpassage\b", r"\bwhich (part|topic)\b",
                ]
                if any(re.search(p, q_text, re.I) for p in meta_patterns):
                    print(f"  Q{q_index+1} attempt {attempt+1}: Meta question rejected.")
                    continue

                mcq["unit"]  = u
                mcq["topic"] = sec

                questions.append(mcq)
                existing_question_texts.append(q_text)
                used_chunk_ids.update(chosen_ids)
                success = True
                print(f"  Q{q_index+1}: ✅ Generated — {u} | {sec}")
                break

            if not success:
                print(f"  Q{q_index+1}: ❌ All {MAX_ATTEMPTS_PER_Q} chunk-based attempts failed — trying failsafe.")
                fs_mcq = _generate_failsafe_mcq(u, sec, difficulty, existing_question_texts)
                if fs_mcq:
                    questions.append(fs_mcq)
                    existing_question_texts.append(fs_mcq.get("question", ""))
                    print(f"  Q{q_index+1}: ✅ Failsafe question used — {u} | {sec}")
                else:
                    print(f"  Q{q_index+1}: ❌ Failsafe also failed — slot skipped.")

        if not questions:
            return {
                "ok": False,
                "error": "Could not generate any questions. Try selecting more topics or a different difficulty."
            }

        meta = {}
        if len(questions) < num_questions:
            meta["warning"] = (
                f"Only {len(questions)} of {num_questions} questions could be generated. "
                "Try selecting more topics or reducing the count."
            )

        return {"ok": True, "questions": questions, **meta}

    except Exception as e:
        print("generate_quiz error:", e)
        import traceback
        traceback.print_exc()
        return {"ok": False, "error": "Quiz generation failed. Please try again."}


# ======================================================
# RECOMMEND QUIZ
# ======================================================

@app.post("/recommend-quiz")
def recommend_quiz(payload: RecommendRequest):
    wrong_qs       = payload.wrong_questions or []
    selected_topics = payload.selected_topics or {}
    score          = payload.score
    total          = payload.total

    if not wrong_qs:
        return {"ok": True, "recommendation": "Great job — no incorrect answers to analyze!"}

    topic_names = []
    for unit, sec_list in selected_topics.items():
        for sec in sec_list:
            if unit in demo_topics and sec in demo_topics[unit]:
                topic_names.append(demo_topics[unit][sec].get("title", sec))
    topic_summary = ", ".join(topic_names) if topic_names else "the selected topics"

    wrong_list = "\n".join(f"{i+1}. {q}" for i, q in enumerate(wrong_qs[:10]))

    prompt = (
        f"A student studying {topic_summary} answered these questions incorrectly:\n"
        f"{wrong_list}\n\n"
        "Give a 1–2 sentence recommendation on what concepts to revise and one concrete next step. "
        "Be direct and practical. Output only the recommendation text."
    )

    try:
        recommendation = call_groq_with_retry(
            messages=[
                {"role": "system", "content": "You are a concise, helpful academic tutor."},
                {"role": "user", "content": prompt},
            ],
            temperature=0.2,
            max_tokens=100,
        )
        if not recommendation:
            recommendation = "Revise the related unit sections and practice the example problems."
        return {"ok": True, "recommendation": recommendation}
    except Exception as e:
        print("recommend-quiz error:", e)
        return {"ok": False, "error": "Recommendation generation failed."}


# ======================================================
# EXPLAIN QUIZ
# ======================================================

@app.post("/explain-quiz")
def explain_quiz(data: ExplainRequest):
    try:
        question      = data.question
        options       = data.options
        correct_index = data.correct_index

        if not question or not options or correct_index is None:
            return {"ok": False, "error": "Invalid input"}

        options_text = ""
        for i, opt in enumerate(options):
            label  = chr(65 + i)
            marker = " ← CORRECT" if i == correct_index else ""
            options_text += f"{label}. {opt}{marker}\n"

        prompt = f"""You are a concise university tutor explaining a multiple choice question.

Explain why each option is correct or incorrect in 1–2 short lines each.
Be specific about the concept, not just "this is wrong".
Output ONLY valid JSON, no markdown, no extra text.

Required format:
{{"explanations": ["Explanation for A", "Explanation for B", "Explanation for C", "Explanation for D"]}}

Question: {question}

Options:
{options_text}"""

        messages = [
            {
                "role": "system",
                "content": (
                    "You explain MCQ answers clearly and briefly. "
                    "Always respond with ONLY a valid JSON object."
                )
            },
            {"role": "user", "content": prompt}
        ]

        raw    = call_groq_with_retry(messages, temperature=0.2, max_tokens=400)
        parsed = safe_json_parse(raw)

        if (parsed
                and "explanations" in parsed
                and isinstance(parsed["explanations"], list)
                and len(parsed["explanations"]) == 4):
            return {"ok": True, "explanations": parsed["explanations"]}

        print(f"explain-quiz: unexpected format. Raw: {raw[:300]}")
        return {"ok": False, "error": "Could not parse explanation response."}

    except Exception as e:
        print("explain-quiz error:", e)
        return {"ok": False, "error": "Explanation generation failed."}


# ======================================================
# FLAG MESSAGE
# ======================================================

@app.post("/flag-message")
def flag_message(data: FlagRequest):
    try:
        message = (data.message or "").strip()
        if not message:
            return {"ok": False, "error": "Empty message"}

        keywords_sample = ", ".join(TOPIC_KEYWORDS_FLAT[:80])

        prompt = (
            f"Course keywords: {keywords_sample}\n\n"
            f'Student message: "{message}"\n\n'
            "Is this message related to the course? "
            'Reply ONLY with JSON: {{"relevant": true}} or {{"relevant": false}}'
        )

        raw = call_groq_with_retry(
            messages=[
                {"role": "system", "content": "You classify student questions. Reply only with JSON."},
                {"role": "user", "content": prompt},
            ],
            temperature=0,
            max_tokens=20,
        )

        parsed = safe_json_parse(raw)
        if parsed is not None and "relevant" in parsed:
            return {"ok": True, "relevant": bool(parsed["relevant"])}

        print(f"flag-message: could not parse response: {raw}")
        return {"ok": True, "relevant": True}

    except Exception as e:
        print("flag-message error:", e)
        return {"ok": False, "error": "Flagging failed"}