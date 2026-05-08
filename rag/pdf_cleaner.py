import re
import unicodedata


# ── Ligature map (common PDF extraction artefacts) ────────────────────────────
_LIGATURES = {
    '\ufb00': 'ff',   # ﬀ
    '\ufb01': 'fi',   # ﬁ
    '\ufb02': 'fl',   # ﬂ
    '\ufb03': 'ffi',  # ﬃ
    '\ufb04': 'ffl',  # ﬄ
    '\ufb05': 'st',   # ﬅ
    '\ufb06': 'st',   # ﬆ
    '\u2013': '-',    # en-dash
    '\u2014': '-',    # em-dash
    '\u2018': "'",    # left single quote
    '\u2019': "'",    # right single quote
    '\u201c': '"',    # left double quote
    '\u201d': '"',    # right double quote
    '\u2022': '*',    # bullet
    '\u00a0': ' ',    # non-breaking space
}


def clean_pdf_text(text: str) -> str:
    """
    Fix common PDF text-extraction artefacts:
      1. Replace Unicode ligatures (ﬀ → ff, ﬁ → fi …)
      2. Rejoin soft-hyphenated words broken across lines
         e.g. "subsolu-\ntions" → "subsolutions"
      3. Collapse runs of whitespace / blank lines
      4. Normalise to NFC Unicode
    """
    if not text:
        return text

    # 1. Ligature replacement
    for bad, good in _LIGATURES.items():
        text = text.replace(bad, good)

    # 2. Normalise Unicode (NFC handles composed characters)
    text = unicodedata.normalize('NFC', text)

    # 3. Rejoin hyphenated line-breaks:
    #    "algo-\nrithm" → "algorithm"
    #    but NOT "e.g.\n" (sentence break after full-stop)
    text = re.sub(r'-\s*\n\s*', '', text)

    # 4. Also rejoin plain mid-word line-breaks (word continues on next line
    #    with a lowercase letter, no punctuation before the break)
    #    "subsolu\ntions" → "subsolutions"
    text = re.sub(r'(?<=[a-z])\n(?=[a-z])', '', text)

    # 5. Collapse multiple blank lines to one
    text = re.sub(r'\n{3,}', '\n\n', text)

    # 6. Strip trailing whitespace from each line
    text = '\n'.join(line.rstrip() for line in text.splitlines())

    return text.strip()
