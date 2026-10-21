#!/usr/bin/env python3
"""Deterministic checks for an edition-2 standards chapter (work/editorial/<PART>/<CHAPTER>.json).

ERRORS  (exit 1): schema, labels/origins, unknown source IDs, unknown evidence IDs, evidence from a source the section does
         not declare, dangling/hashed internal citation IDs, headings or raw HTML inside fa_text, too many/too long quotes,
         verbatim quotes that are not backed by a T/Q evidence item.
WARNINGS (exit 0): terminology drift against evidence/terminology-decisions.md (e.g. «کنترل» outside a parenthetical alias,
         «اشتهای ریسک», «حاکمیت», «حسابرسی» in new prose), phrases that need a human look (certification/legal/mandate wording),
         short sections. Warnings are for the author and the reviewer; they never replace review.

    python3 tools/e2_validate_chapter.py --root <E2 root> work/editorial/P02/C58.json [more.json ...]
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import unicodedata
from pathlib import Path

import yaml

ALLOWED_ORIGINS = {"editorial_synthesis", "editorial_localization_ir"}
INTERNAL_ID = re.compile(r"KDJ-AI-2026E2-P\d{2}-C\d{2}-S\d{2}(?:-[0-9A-F]{8})?")
QUOTE_MAX_WORDS, QUOTE_MAX_COUNT = 60, 10


def norm(s: str) -> str:
    s = unicodedata.normalize("NFC", s or "").replace("ي", "ی").replace("ك", "ک")
    return re.sub(r"[\s‌‎‏]+", "", s)


def words(s: str) -> int:
    return len(re.findall(r"\S+", s))


def paren_spans(text: str) -> list[tuple[int, int]]:
    return [(m.start(), m.end()) for m in re.finditer(r"[(（][^()（）\n]{0,80}[)）]", text)]


def outside_parens(text: str, term: str) -> list[int]:
    spans = paren_spans(text)
    return [m.start() for m in re.finditer(re.escape(term), text) if not any(a <= m.start() < b for a, b in spans)]


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    ap.add_argument("files", nargs="+")
    args = ap.parse_args()
    root = Path(args.root)
    style = yaml.safe_load((root / "config" / "style.yaml").read_text(encoding="utf-8"))["editorial_labels"]
    labels_syn, labels_loc = set(style["synthesis"]), set(style["localization_ir"])
    sources = {d["doc_key"] for d in json.loads((root / "manifests" / "sources.json").read_text(encoding="utf-8"))["documents"]}
    index = json.loads((root / "evidence" / "packets" / "index.json").read_text(encoding="utf-8"))
    e1_ids = set(re.findall(r"KDJ-AI-2026E2-P\d{2}-C\d{2}-S\d{2}", (root / "evidence" / "e1-index.md").read_text(encoding="utf-8")))
    exit_code = 0
    for f in args.files:
        path = Path(f) if Path(f).is_absolute() else root / f
        errors: list[str] = []
        warns: list[str] = []
        try:
            doc = json.loads(path.read_text(encoding="utf-8"))
        except Exception as e:  # noqa: BLE001
            print(f"{path}: cannot parse JSON: {e}")
            exit_code = 1
            continue
        part, chap = path.parent.name, path.stem
        for k in ("title_fa", "part_id", "chapter_id", "kind", "origin", "sections"):
            if k not in doc:
                errors.append(f"missing top-level key {k}")
        if doc.get("part_id") != part or doc.get("chapter_id") != chap:
            errors.append(f"part/chapter ({doc.get('part_id')}/{doc.get('chapter_id')}) do not match path {part}/{chap}")
        if doc.get("kind") != "standards_integration":
            errors.append("kind must be 'standards_integration'")
        total_words = 0
        quote_count = 0
        for i, s in enumerate(doc.get("sections", [])):
            tag = f"section {i}"
            for k in ("title_fa", "label_fa", "fa_text", "origin", "derived_from_source_ids", "evidence_refs"):
                if not s.get(k):
                    errors.append(f"{tag}: missing/empty {k}")
            text = s.get("fa_text", "")
            origin, label = s.get("origin"), s.get("label_fa")
            if origin not in ALLOWED_ORIGINS:
                errors.append(f"{tag}: origin {origin!r} not allowed")
            if label not in labels_syn | labels_loc:
                errors.append(f"{tag}: label {label!r} not in config/style.yaml")
            elif (label in labels_loc) != (origin == "editorial_localization_ir") and label not in labels_syn:
                errors.append(f"{tag}: label {label!r} (localization) does not match origin {origin!r}")
            elif label in labels_syn and origin == "editorial_localization_ir":
                errors.append(f"{tag}: synthesis label {label!r} used with localization origin")
            declared = set(s.get("derived_from_source_ids") or [])
            for sid in declared:
                if sid not in sources:
                    errors.append(f"{tag}: unknown derived_from_source_id {sid}")
            for ref in s.get("evidence_refs") or []:
                item = index.get(ref)
                if item is None:
                    errors.append(f"{tag}: unknown evidence ref {ref}")
                elif item["source_id"] not in declared and not item["source_id"].startswith("EXTERNAL"):
                    errors.append(f"{tag}: evidence {ref} comes from {item['source_id']} which the section does not declare")
            for cid in s.get("derived_from_content_ids") or []:
                if cid not in e1_ids:
                    errors.append(f"{tag}: derived_from_content_id {cid} not in the E1/E2 section index")
            for m in INTERNAL_ID.finditer(text):
                if len(m.group(0)) > 25 and re.search(r"-[0-9A-F]{8}$", m.group(0)):
                    errors.append(f"{tag}: hashed internal ID {m.group(0)} (use the structural ID without hash)")
                elif m.group(0) not in e1_ids:
                    errors.append(f"{tag}: inline citation {m.group(0)} does not exist")
            if re.search(r"^\s{0,3}#{1,6}\s", text, re.M):
                errors.append(f"{tag}: markdown heading inside fa_text (the section title is the heading)")
            if re.search(r"</?(?:div|span|p|br|table|script|img|a)\b", text, re.I):
                errors.append(f"{tag}: raw HTML inside fa_text")
            quotes = [q for q in re.split(r"\n\s*\n", text) if q.lstrip().startswith(">")]
            quote_count += len(quotes)
            quotable = " ".join(norm(index[r]["text"]) for r in s.get("evidence_refs") or [] if r in index and index[r]["kind"] in ("term", "quality", "vocabulary"))
            for q in quotes:
                body = re.sub(r"(?m)^\s*>\s?", "", q)
                body_main = re.sub(r"\[[^\]]*\]", "", body.split("\n")[0])
                if words(body_main) > QUOTE_MAX_WORDS:
                    warns.append(f"{tag}: quote longer than {QUOTE_MAX_WORDS} words")
                core = norm(re.sub(r"[«»\"]", "", body_main))[:60]
                if core and core not in quotable:
                    errors.append(f"{tag}: blockquote is not backed by a cited verbatim T/Q/V evidence item: {body_main[:50]!r}")
            nw = words(text)
            total_words += nw
            if nw < 60:
                warns.append(f"{tag}: very short ({nw} words)")
            # terminology lint (warnings)
            if outside_parens(text, "کنترل"):
                warns.append(f"{tag}: «کنترل» outside a parenthetical alias (official: واپایش) x{len(outside_parens(text, 'کنترل'))}")
            for term, hint in (("اشتهای ریسک", "سطح مخاطره‌پذیری (اشتهای ریسک)"), ("حاکمیت", "حکمرانی"), ("حسابرسی", "ممیزی (unless financial audit)"),
                               ("بهبود مستمر", "بهبود مداوم"), ("سیستم مدیریت", "نظام مدیریت"), ("سامانه مدیریت", "نظام مدیریت"), ("مدیریت ارشد", "مدیریت رده‌بالا"),
                               ("پاسخ‌گویی", "پاسخگویی"), ("مطابقت", "انطباق"), ("داده‌های بزرگ", "کلان‌داده"), ("تحلیل داده", "واکاوش داده (if the term is the 20546 one)")):
                if outside_parens(text, term):
                    warns.append(f"{tag}: «{term}» outside parentheses → official «{hint}»")
            for pat in (r"گواهی\s?نامه|گواهی‌نامه", r"تأیید رسمی|مورد تأیید", r"الزام قانونی|بر اساس قانون|طبق قانون|مصوبه|آیین\s?نامه", r"NIST (?:توصیه|الزام|دستور) می", r"(?:INSO|ISO|IEEE|استاندارد ملی)[^.\n]{0,40}(?:الزام قانونی|اجباری)"):
                for m in re.finditer(pat, text):
                    a = max(0, m.start() - 35)
                    warns.append(f"{tag}: needs a human look → …{text[a:m.end() + 35].replace(chr(10), ' ')}…")
        if quote_count > QUOTE_MAX_COUNT:
            errors.append(f"{quote_count} blockquotes (max {QUOTE_MAX_COUNT})")
        print(f"\n== {path.relative_to(root) if path.is_relative_to(root) else path}: {len(doc.get('sections', []))} sections, {total_words} words, {quote_count} quotes, {len(errors)} errors, {len(warns)} warnings")
        for e in errors:
            print("  ERROR:", e)
        for w in warns[:60]:
            print("  warn :", w)
        if len(warns) > 60:
            print(f"  … {len(warns) - 60} more warnings")
        if errors:
            exit_code = 1
    return exit_code


if __name__ == "__main__":
    sys.exit(main())
