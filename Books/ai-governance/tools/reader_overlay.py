#!/usr/bin/env python3
"""Reader presentation overlay for the Persian AI-Governance book.

master/book.json is canonical and signed: every section's content_id ends in the
first 8 hex of the sha256 of its text, and the release manifest, Merkle tree and
printed PDF/DOCX all depend on that. This tool never touches it. It derives a
PRESENTATION overlay (master/reader-overlay.json) that the WordPress reader
merges on top of the canonical text: PDF debris removed, NIST AI RMF
subcategories in one structure, rectangular tables, Persian table headers,
header rows that are really group/caption/data rows taken out of the header,
normalised title numbering, cover-title echoes removed, and footnotes: every
[^n] gets the `[^n]: text` definition recovered from the unlabelled note blocks
the canonical Persian text already carries (never translated or paraphrased here).

Every transform is a named function that bumps a counter; nothing is
hand-picked by section id except where the source document itself is named
(chapter titles come from taxonomy/doc_titles_fa.json).

Usage (stdlib only, plain python3):
    python3 tools/reader_overlay.py            # write master/reader-overlay.json
    python3 tools/reader_overlay.py --check    # exit 1 if the committed overlay is stale
    python3 tools/reader_overlay.py --report   # baseline vs after metrics + removal log
"""
from __future__ import annotations

import argparse
import difflib
import hashlib
import json
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
BOOK_PATH = ROOT / "master" / "book.json"
OVERLAY_PATH = ROOT / "master" / "reader-overlay.json"
DOC_TITLES_PATH = ROOT / "taxonomy" / "doc_titles_fa.json"
SCHEMA = "kbk-reader-overlay/1"
GENERATOR = "tools/reader_overlay.py"

COUNT: Counter = Counter()             # transform -> number of applications
LOG: dict = defaultdict(list)          # structural_id -> [(transform, snippet)]
UNKNOWN_HEADERS: Counter = Counter()   # English header cells with no mapping
AMBIGUOUS: list = []                   # (sid, note) table repairs that needed a fallback

FA_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
LATIN_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
PERSIAN_CHARS = re.compile(r"[؀-ۿ]")
FIGURE_ONLY = re.compile(r"^\s*\[FIGURE[^\]]*\]\s*$")
HEADING = re.compile(r"^(#{1,6})\s+(.*)$")
SEPARATOR_CELL = re.compile(r"^:?-{3,}:?$")
TABLE_LINE = re.compile(r"^\s*\|.*\|\s*$")


def note(sid: str, transform: str, snippet: str = "") -> None:
    COUNT[transform] += 1
    LOG[sid].append((transform, snippet.strip()[:90]))


def plain(text: str) -> str:
    """Comparable form: no markdown emphasis/heading marks, digits Latin, single spaces."""
    t = re.sub(r"^#+\s*", "", text.strip())
    t = t.replace("*", "").replace("_", " ").translate(LATIN_DIGITS)
    return re.sub(r"\s+", " ", t).strip()


# --------------------------------------------------------------------------- tables

def split_cells(line: str) -> list[str]:
    inner = line.strip()
    inner = inner[1:] if inner.startswith("|") else inner
    inner = inner[:-1] if inner.endswith("|") and not inner.endswith("\\|") else inner
    return [c.strip() for c in re.split(r"(?<!\\)\|", inner)]


def is_separator_row(cells: list[str]) -> bool:
    return bool(cells) and all(SEPARATOR_CELL.match(c) for c in cells)


class Table:
    __slots__ = ("raw", "header", "sep", "body", "dirty")

    def __init__(self, raw: list[str]):
        self.raw = raw
        rows = [split_cells(l) for l in raw]
        self.header = rows[0]
        self.sep = len(rows) > 1 and is_separator_row(rows[1])
        self.body = rows[2:] if self.sep else rows[1:]
        self.dirty = False

    def rows(self) -> list[list[str]]:
        return [self.header] + self.body

    def width(self) -> int:
        return len(self.header)

    def lines(self) -> list[str]:
        if not self.dirty:
            return list(self.raw)
        out = ["| " + " | ".join(self.header) + " |"]
        if self.sep:
            out.append("|" + "|".join(["---"] * len(self.header)) + "|")
        out += ["| " + " | ".join(r) + " |" for r in self.body]
        return out


def parse(text: str) -> list:
    """Items: str for an ordinary line ('' = blank), Table for a pipe-table run."""
    lines = text.split("\n")
    items, i = [], 0
    while i < len(lines):
        if TABLE_LINE.match(lines[i]) and lines[i].strip().startswith("|"):
            j = i
            while j < len(lines) and TABLE_LINE.match(lines[j]):
                j += 1
            items.append(Table(lines[i:j]))
            i = j
        else:
            items.append(lines[i])
            i += 1
    return items


def serialize(items: list) -> str:
    out = []
    for it in items:
        out.extend(it.lines() if isinstance(it, Table) else [it])
    return "\n".join(out)


def next_nonblank(items: list, i: int, step: int = 1):
    j = i + step
    while 0 <= j < len(items) and isinstance(items[j], str) and not items[j].strip():
        j += step
    return j if 0 <= j < len(items) else None


# --------------------------------------------------------------------------- patterns

DOT_LEADER = re.compile(r"(?:\.\s?){4,}|…{2,}")
PAGE_CELL = re.compile(r"^(?:[0-9۰-۹]{1,3}|[ivx]{1,5})$")
TOC_ENTRY = re.compile(
    r"^(?:[0-9۰-۹]+(?:[.٫][0-9۰-۹]+)*\.?(?:\s|$)|[A-Zا-ی]\.[0-9۰-۹]|پیوست|ضمیمه|Appendix|شکل|جدول|Fig\.?|Figure|Table|"
    r"مراجع|منابع|واژه‌نامه|خلاصه|چکیده|مقدمه|فهرست|قدردانی|پیشگفتار|پیش‌گفتار)")
TOC_HEADING = re.compile(
    r"^(?:#+\s*|\*\*)?\s*(?:فهرست\s*(?:مطالب|جدول‌ها|جداول|اشکال|شکل‌ها|تصاویر|نمودارها)|"
    r"Table of Contents|Contents|List of (?:Figures|Tables))\s*(?:\*\*)?\s*$", re.I)
TOC_LINE = re.compile(r"^.{3,}?(?:\.\s?){5,}\s*[0-9۰-۹ivx]{0,4}\s*$")
TOC_LIST_LINE = re.compile(r"^[-*]\s*(?:[0-9۰-۹]+(?:\.[0-9۰-۹]+)+\.?|شکل\s*[0-9۰-۹]+|جدول\s*[0-9۰-۹]+)\s+.{3,}\s[0-9۰-۹]{1,3}$")
CONTD_LINE = re.compile(r"^\*{0,2}\s*\(?\s*(?:ادامه\s+(?:در|از)\s+صفحه[ٔ‌]?\s*(?:بعد|بعدی|قبل|قبلی)|continued on next page)\s*\)?\s*\*{0,2}$", re.I)
PAGE_NUMBER_LINE = re.compile(r"^\*?\s*[0-9۰-۹]{1,3}\s*\*?$")
BACK_TO_INDEX = re.compile(r"\s*\[Back to Index\]", re.I)
FREE_OF_CHARGE = re.compile(r"(?:به[‌ ]?صورت|بصورت)\s*رایگان")
CAPTION = re.compile(r"^\*{0,2}\s*(?:جدول|شکل|Table|Figure|Fig\.)\s*[0-9۰-۹]")
CONTD_CAPTION = re.compile(r"\((?:ادامه|continued)\)\s*\.?\s*\*{0,2}\s*$", re.I)

# Publisher / cover metadata; only applied inside front-matter sections.
PUBLISHER_LINE = [
    re.compile(r"وزارت بازرگانی"),
    re.compile(r"Raimondo|Locascio|Lutnick|Burkhardt|Wilbur Ross|Walter Copan|Kent Rochford|Olthoff"),
    re.compile(r"معاون وزیر بازرگانی|Natl\. Inst\. Stand|CODEN"),
    re.compile(r"^\**\s*مؤسسه ملی استاندارد و فناوری(?: آمریکا)?(?:\s*\(NIST\))?\s*\**$"),
    re.compile(r"^\**\s*آزمایشگاه فناوری اطلاعات.{0,60}$"),
    re.compile(r"هیئت\s+(?:بازبینی|داوری)\s+تحریریه"),
    re.compile(r"بیانیه‌های حق نشر"),
    re.compile(r"\d{4}-\d{4}-\d{4}-\d{3}[\dX]"),                      # ORCID ids
    re.compile(r"ORCID"),
    re.compile(r"^\**\s*(?:اطلاعات تماس|تماس)\s*:"),
    re.compile(r"^(?=.{0,110}$).*[\w.+-]+@[\w-]+\.[\w.-]+"),             # short contact line with an e-mail
    re.compile(r"^(?=.*(?:تجاری|تجهیزات))(?=.*(?:اشاره|شناسایی|ذکر|معرفی))(?=.*(?:تأیید|توصیه))"),  # commercial-entity disclaimer
    re.compile(r"^(?=.*(?:نهادهای تجاری|شرکت‌های تجاری|تجهیزات یا مواد))(?=.*(?:توصیف کافی|تشریح کافی))"),
    re.compile(r"^[-*\s]*توصیه یا تأیید(?:ی)?\s+از سوی"),
    re.compile(r"^\**\s*سلب مسئولیت"),
    re.compile(r"قانون آزادی اطلاعات|Freedom of Information"),
    re.compile(r"^\**\s*(?:نحوه|شیوهٔ?)\s+(?:استناد|ارجاع)"),
    re.compile(r"^#*\s*\**\s*(?:ژانویه|فوریه|مارس|آوریل|مه|می|ژوئن|ژوئیه|اوت|آگوست|سپتامبر|اکتبر|نوامبر|دسامبر)\s+[0-9۰-۹]{4}(?:\s*\([^)]*\))?\s*\**$"),
]
# Cover-only lines: removed only in the cover zone (before the first content anchor heading).
COVER_LINE = re.compile(
    r"^#*\s*\**\s*(?:NIST Artif|NISTIR\s*\d+\s*$|انتشار ویژه|NIST (?:AI|SP|IR)\s*[\d-]+\w*\s*$|"
    r"هوش مصنوعی (?:قابل[\u200c ]?اعتماد|اعتماد[\u200c ]?پذیر) و مسئولانه|(?=.{0,90}$).*NIST (?:AI|SP) [\d-]+\w*\s*\**$)")
COVER_ANCHOR = re.compile(r"^#+\s*\**\s*(?:چکیده|خلاصه|پیشگفتار|پیش‌گفتار|مقدمه|قدردانی|کلیدواژه|مخاطبان|پیشینه|درباره|هوش مصنوعی و دادگ)")
DEBRIS_HEADING = re.compile(
    r"^(?:#+\s*|\*\*)?\s*(?:سیاست‌های مجموعه|تاریخچه (?:انتشار|نشریه)|نحوه (?:استناد|ارجاع)|شناسه‌های ORCID|"
    r"ارسال (?:دیدگاه‌ها|نظرات)|اطلاعات تماس|اطلاعات (?:تکمیلی|بیشتر)|خط‌مشی‌های مجموعه)")
FRONT_TITLE = "مطالب آغازین سند"

# NIST AI RMF function codes
FUNC_FA = {"حاکمیت": "GOVERN", "حکمرانی": "GOVERN", "نگاشت": "MAP", "ترسیم": "MAP",
           "سنجش": "MEASURE", "مدیریت": "MANAGE"}
FUNC_ORDER = {"GOVERN": 0, "MAP": 1, "MEASURE": 2, "MANAGE": 3}
CODE_EN = r"(GOVERN|MAP|MEASURE|MANAGE)\s+(\d+)(?:\.(\d+))?"
CODE_SPLIT = re.compile(r"(?=(?:GOVERN|MAP|MEASURE|MANAGE)\s+\d+(?:\.\d+)?\s*:)")
CODE_HEAD = re.compile(r"^\s*" + CODE_EN + r"\s*:\s*(.*)$", re.S)
STATEMENT = re.compile(
    r"^\s*(\*{0,2})\s*(GOVERN|MAP|MEASURE|MANAGE|حاکمیت|حکمرانی|نگاشت|سنجش|مدیریت)\s+([0-9۰-۹]+)\s*[.٫]\s*([0-9۰-۹]+)\s*:\s*(.+?)\s*\*{0,2}\s*$")
CORE_CAPTION = re.compile(r"جدول\s*[0-9۰-۹]+\s*:\s*(?:دسته‌ها|رده‌ها|مقوله‌ها)\s+و\s+(?:زیردسته‌ها|زیررده‌ها|زیرمقوله‌ها)")
CORE_LABEL = re.compile(r"^(?:دسته‌ها|رده‌ها|مقوله‌ها)\s*(?:زیردسته‌ها|زیررده‌ها|زیرمقوله‌ها)?\s*")
CORE_HEADER = ["مقوله / زیرمقوله", "شرح"]
CORE_LABEL_CELL = re.compile(r"^(?:دسته‌ها|رده‌ها|مقوله‌ها|زیردسته‌ها|زیررده‌ها|زیرمقوله‌ها)$")
METRIC_FRONT = [re.compile(p) for p in (
    r"وزارت بازرگانی", r"Raimondo|Locascio|Lutnick|Burkhardt", r"هیئت\s+(?:بازبینی|داوری)\s+تحریریه",
    r"بیانیه‌های حق نشر", r"ORCID", r"قانون آزادی اطلاعات")]
ACTOR_ROW = re.compile(r"^(?:AI Actor Tasks?\b|وظایف کنشگر(?:ان)?\b|کنشگران هوش مصنوعی\s*:)", re.I)

HEADER_MAP = {
    "action id": "شناسه اقدام", "suggested action": "اقدام پیشنهادی", "gai risks": "ریسک‌های GAI",
    "acronyms": "اختصارات", "acronym": "اختصار", "term": "اصطلاح", "terms": "اصطلاحات",
    "definition": "تعریف", "synonyms & related terms": "مترادف‌ها و اصطلاحات مرتبط",
    "description": "شرح", "category": "دسته", "subcategory": "زیرمقوله", "source": "منبع",
    "dataset": "مجموعه‌داده", "public": "عمومی", "rule-list": "فهرست قواعد",
    "test accuracy": "دقت آزمون",
    "d-participant": "مشارکت‌کنندهٔ D", "g-participant": "مشارکت‌کنندهٔ G",
    "d-submission": "ارسال D", "g-submission": "ارسال G",
    "d-submission id": "شناسهٔ ارسال D", "g-submission id": "شناسهٔ ارسال G",
    "d-submission ids": "شناسه‌های ارسال D", "g-submission ids": "شناسه‌های ارسال G",
    "d-submission ids su": "شناسه‌های ارسال D", "bmission count": "تعداد ارسال",
    "submission count": "تعداد ارسال",
    "d-team": "تیم D", "g-team": "تیم G", "dteam": "تیم D", "gteam": "تیم G",
    "d.subid": "شناسهٔ ارسال D", "g.subid": "شناسهٔ ارسال G",
    "d-round-1": "دور ۱ D", "d-round-2": "دور ۲ D", "d-round-3": "دور ۳ D",
    "g-round-1": "دور ۱ G", "g-round-2": "دور ۲ G",
}
LATIN_KEEP = re.compile(r"^(?:[A-Z][A-Za-z]{0,6}|PP)$")  # metric acronyms such as AUC, BrierT stay Latin


# --------------------------------------------------------------------------- section transforms

def strip_pdf_page_debris(items: list, sid: str) -> list:
    """Continuation notes, standalone page numbers, [Back to Index] links, free-of-charge DOI lines."""
    out = []
    for it in items:
        if isinstance(it, str) and it.strip():
            s = it.strip()
            if CONTD_LINE.match(s):
                note(sid, "strip_pdf_page_debris:continued", s)
                continue
            if PAGE_NUMBER_LINE.match(s):
                note(sid, "strip_pdf_page_debris:page_number", s)
                continue
            if FREE_OF_CHARGE.search(s) and re.search(r"doi\.org|nist\.gov", s) and len(s) < 240:
                note(sid, "strip_pdf_page_debris:free_of_charge_doi", s)
                continue
            if BACK_TO_INDEX.search(it):
                note(sid, "strip_pdf_page_debris:back_to_index", s)
                it = BACK_TO_INDEX.sub("", it)
        out.append(it)
    return out


def is_toc_table(t: Table) -> bool:
    body = [r for r in t.rows() if not is_separator_row(r) and any(c for c in r)]
    if not body:
        return False
    n = len(body)
    dots = sum(1 for r in body if DOT_LEADER.search(" ".join(r)))
    pages = sum(1 for r in body if PAGE_CELL.match(next((c for c in reversed(r) if c), "")))
    numbered = sum(1 for r in body if TOC_ENTRY.match(next((c for c in r if c), ""))
                   and re.search(r"[\u0600-\u06FFA-Za-z]{3,}.*\s.*[\u0600-\u06FFA-Za-z]{2,}", " ".join(r)))
    return dots >= max(1, n / 2) or (n >= 3 and pages >= 0.6 * n and numbered >= 0.5 * n)


def strip_pdf_toc(items: list, sid: str) -> list:
    """Copied PDF tables of contents / lists of figures and tables, with their headings."""
    drop = set()
    for i, it in enumerate(items):
        if isinstance(it, Table) and is_toc_table(it):
            drop.add(i)
            note(sid, "strip_pdf_toc:table", it.raw[0])
            j = next_nonblank(items, i, -1)
            if j is not None and isinstance(items[j], str) and TOC_HEADING.match(items[j].strip()):
                drop.add(j)
        elif isinstance(it, str) and TOC_LINE.match(it.strip()) and not it.strip().startswith("|"):
            drop.add(i)
            note(sid, "strip_pdf_toc:dot_leader_line", it)
    # In a section that carries a copied TOC, "- 4.2.3 Title 37" list lines are TOC entries too.
    if drop or any(isinstance(it, str) and TOC_HEADING.match(it.strip()) for it in items):
        for i, it in enumerate(items):
            if isinstance(it, str) and TOC_LIST_LINE.match(it.strip()):
                drop.add(i)
                note(sid, "strip_pdf_toc:list_entry_with_page", it)
    out = [it for i, it in enumerate(items) if i not in drop]
    return drop_empty_headings(out, sid, TOC_HEADING, "strip_pdf_toc:empty_heading")


def heading_level(line: str) -> int:
    m = HEADING.match(line.strip())
    return len(m.group(1)) if m else 0


def drop_empty_headings(items: list, sid: str, pattern: re.Pattern, tag: str) -> list:
    """Remove headings matching `pattern` that no longer introduce any content."""
    changed = True
    while changed:
        changed = False
        for i, it in enumerate(items):
            if not (isinstance(it, str) and it.strip() and pattern.match(it.strip())):
                continue
            j = next_nonblank(items, i, 1)
            lvl = heading_level(it) or 6
            nxt = items[j] if j is not None else None
            empty = nxt is None or (isinstance(nxt, str) and 0 < heading_level(nxt) <= lvl) or (
                isinstance(nxt, str) and pattern.match(nxt.strip()))
            if empty:
                note(sid, tag, it)
                del items[i]
                changed = True
                break
    return items


def is_front_section(section: dict, index: int, chapter: dict) -> bool:
    if section["title_fa"].strip() == FRONT_TITLE:
        return True
    if chapter.get("kind") != "source" or index > 2:
        return False
    hits = sum(1 for l in section["fa_text"].split("\n")
               if any(p.search(l) for p in PUBLISHER_LINE) or FREE_OF_CHARGE.search(l))
    return hits >= 2


def strip_front_matter(items: list, sid: str, cover_titles: set, cover_tokens: list = ()) -> list:
    """Cover text, publisher metadata, citation/ORCID/contact blocks, duplicated cover lines."""
    n = len(items)
    debris = [False] * n
    near = set()
    in_debris_block = False
    block_level = 0
    seen = set()
    cover_zone = True
    heading_keys = {plain(it) for it in items if isinstance(it, str) and heading_level(it)}
    for i, it in enumerate(items):
        if isinstance(it, Table) or not it.strip():
            continue
        s = it.strip()
        lvl = heading_level(s)
        if lvl and in_debris_block and lvl <= block_level:
            in_debris_block = False
        if DEBRIS_HEADING.match(s):
            debris[i] = True
            # only a real heading (or a bold-only label line) opens a debris block
            if lvl or re.match(r"^\*\*[^*]+\*\*$", s):
                in_debris_block = True
                block_level = lvl or 6
            continue
        if in_debris_block:
            debris[i] = True
            continue
        if any(p.search(s) for p in PUBLISHER_LINE):
            debris[i] = True
            continue
        if COVER_ANCHOR.match(s) or (not lvl and len(s) >= 200):
            cover_zone = False
        if not cover_zone:
            continue
        key = plain(s)
        if COVER_LINE.match(s):
            debris[i] = True          # series label ("NIST Trustworthy and Responsible AI …")
            continue
        if lvl and key in cover_titles:
            debris[i] = True          # cover heading repeating the chapter / document title
            continue
        if not lvl and not FIGURE_ONLY.match(s) and not s.startswith("- ") and (
                key in cover_titles or key in heading_keys or key in seen):
            debris[i] = True          # cover echo of a title / heading, or a repeated title-page line
            continue
        if not s.startswith("- ") and not FIGURE_ONLY.match(s) and near_title(s, cover_tokens):
            debris[i] = True          # the document title again, in a second wording
            near.add(i)
            continue
        seen.add(key)
    # A citation line directly after a "how to cite" line is the citation itself.
    for i, it in enumerate(items):
        if debris[i] and isinstance(it, str) and re.search(r"(?:نحوه|شیوهٔ?)\s+(?:استناد|ارجاع)", it):
            j = next_nonblank(items, i, 1)
            if j is not None and isinstance(items[j], str) and re.search(r"\((?:19|20)\d\d\)", items[j]):
                debris[j] = True
    # Figure markers that sit inside removed debris (cover art) go with it; so do lines that
    # carry a figure marker followed by debris text.
    for i, it in enumerate(items):
        if isinstance(it, str) and it.strip().startswith("[FIGURE") and not debris[i]:
            rest = re.sub(r"^\s*\[FIGURE[^\]]*\]\s*", "", it)
            if rest and any(p.search(rest) for p in PUBLISHER_LINE):
                debris[i] = True
                continue
            if rest:
                continue
            p = next_nonblank(items, i, -1)
            q = next_nonblank(items, i, 1)

            def gone(k):
                return k is None or debris[k] or (isinstance(items[k], str) and FIGURE_ONLY.match(items[k]))
            if gone(p) and gone(q):
                debris[i] = True
    out = []
    for i, it in enumerate(items):
        if debris[i]:
            note(sid, "strip_front_matter" + (":near_duplicate_title" if i in near else ""),
                 it if isinstance(it, str) else it.raw[0])
        else:
            out.append(it)
    return drop_empty_headings(out, sid, DEBRIS_HEADING, "strip_front_matter:empty_heading")


def dedupe_captions(items: list, sid: str) -> list:
    """The extractor often emits a caption twice (italic, then bold). Keep the bold one."""
    i = 0
    while i < len(items):
        it = items[i]
        if isinstance(it, str) and CAPTION.match(it.strip()):
            j = next_nonblank(items, i, 1)
            if j is not None and isinstance(items[j], str) and plain(items[j]) == plain(it):
                keep_second = items[j].strip().startswith("**") or not it.strip().startswith("**")
                victim = i if keep_second else j
                note(sid, "dedupe_captions", items[victim])
                del items[victim]
                continue
        i += 1
    return items


def statement_key(m: re.Match) -> tuple:
    func = FUNC_FA.get(m.group(2), m.group(2))
    return (func, m.group(3).translate(LATIN_DIGITS), m.group(4).translate(LATIN_DIGITS), plain(m.group(5)))


def dedupe_nist_statements(items: list, sid: str) -> list:
    """A subcategory statement repeated later in the same section (page-layout echo) is dropped
    at its EARLIER position; the later copy is the one that introduces its table."""
    keys = []
    for i, it in enumerate(items):
        m = STATEMENT.match(it) if isinstance(it, str) else None
        keys.append(statement_key(m) if m else None)
    drop = {i for i, k in enumerate(keys) if k and k in keys[i + 1:]}
    for i in sorted(drop):
        note(sid, "normalize_nist:duplicate_statement", items[i])
    return [it for i, it in enumerate(items) if i not in drop]


def id_shape(cell: str) -> str:
    c = cell.strip().split(":")[0].split(" ")[0]
    if not re.search(r"\d", c) or len(c) > 14 or not re.match(r"^[A-Z]", c):
        return ""
    return re.sub(r"[A-Z]", "A", re.sub(r"\d+", "9", c))


def merge_split_tables(items: list, sid: str) -> list:
    """Re-join tables split by a PDF page break (repeated header, or a header row that is data)."""
    i = 0
    while i < len(items):
        a = items[i]
        if not isinstance(a, Table):
            i += 1
            continue
        j, gap = i + 1, []
        while j < len(items) and isinstance(items[j], str) and (
                not items[j].strip() or (CAPTION.match(items[j].strip()) and CONTD_CAPTION.search(items[j]))):
            gap.append(j)
            j += 1
        if j >= len(items) or not isinstance(items[j], Table):
            i += 1
            continue
        b = items[j]
        a_shapes = {id_shape(r[0]) for r in a.body if r} - {""}
        same_header = [plain(c) for c in b.header] == [plain(c) for c in a.header]
        data_header = bool(a_shapes) and (id_shape(b.header[0]) in a_shapes or any(
            r and id_shape(r[0]) in a_shapes for r in b.body)) and not CORE_LABEL.match(b.header[0] or "-")
        if not (same_header or data_header) or (a.width() == 1 and not same_header):
            i += 1
            continue
        a.body += (b.body if same_header else [b.header] + b.body)
        a.dirty = True
        note(sid, "merge_split_tables", b.raw[0])
        for k in gap:
            if items[k].strip():
                note(sid, "merge_split_tables:continuation_caption", items[k])
        del items[i + 1:j + 1]
    return items


def _code_items(text: str, context: str, state: dict) -> list:
    """Split a fragment at subcategory codes; text before the first code continues an earlier item."""
    parts = [p for p in CODE_SPLIT.split(text) if p.strip()]
    for part in parts:
        m = CODE_HEAD.match(part)
        if m:
            func, a, b, desc = m.group(1), m.group(2), m.group(3), m.group(4).strip()
            item = {"func": func, "a": int(a), "b": int(b) if b else None, "text": [desc] if desc else []}
            state["items"].append(item)
            state["sub" if b else "cat"] = item
            state["last"] = item
        else:
            target = state.get(context) or state.get("last")
            if target is None:
                state["orphans"].append(part.strip())
            else:
                target["text"].append(part.strip())


def normalize_nist_core_tables(items: list, sid: str) -> list:
    """AI RMF core tables (categories + subcategories) arrive as page-split, row-wrapped tables
    and flattened paragraphs. Rebuild each one as a single 2-column table «مقوله / زیرمقوله | شرح»
    in code order. Lossless: every source character is re-emitted (checked)."""
    out, i = [], 0
    while i < len(items):
        it = items[i]
        if not (isinstance(it, str) and CORE_CAPTION.search(it) and not CONTD_CAPTION.search(it)):
            out.append(it)
            i += 1
            continue
        j = i + 1
        region = []
        while j < len(items):
            x = items[j]
            if isinstance(x, str) and heading_level(x):
                break
            if isinstance(x, str) and CORE_CAPTION.search(x) and not CONTD_CAPTION.search(x):
                break
            if isinstance(x, str) and x.strip() and not CONTD_CAPTION.search(x) and not CODE_SPLIT.search(x) \
                    and not CORE_LABEL.match(x.strip()):
                break
            region.append(x)
            j += 1
        state = {"items": [], "orphans": [], "cat": None, "sub": None, "last": None}
        source_chars = []
        for x in region:
            if isinstance(x, Table):
                for r in x.rows():
                    if is_separator_row(r) or (len(r) <= 2 and all(CORE_LABEL_CELL.match(c) or not c for c in r)):
                        continue
                    cells = [c for c in r]
                    if len(cells) >= 2:
                        _code_items(cells[0], "cat", state)
                        _code_items(" ".join(c for c in cells[1:] if c), "sub", state)
                    else:
                        _code_items(cells[0], "last", state)
                    source_chars += list("".join(cells))
            elif x.strip() and not CONTD_CAPTION.search(x):
                body = CORE_LABEL.sub("", x.strip())
                _code_items(body, "last", state)
                source_chars += list(body)
        rows_items = state["items"]
        if not rows_items or state["orphans"]:
            out.append(it)
            i += 1
            continue
        rows_items.sort(key=lambda d: (FUNC_ORDER[d["func"]], d["a"], -1 if d["b"] is None else d["b"]))
        rows, dropped = [], ""
        for d in rows_items:
            code = f"{d['func']} {d['a']}" + ("" if d["b"] is None else f".{d['b']}")
            desc = re.sub(r"\s+", " ", " ".join(d["text"])).strip()
            if rows and rows[-1][0].strip("*") == code:
                prev = rows[-1][1]
                short, long_ = (prev, desc) if len(prev) <= len(desc) else (desc, prev)
                if long_.startswith(short):
                    # the PDF repeated a row cut short at a page break; keep the complete one
                    dropped += code + ":" + short
                    rows[-1][1] = long_
                    note(sid, "normalize_nist:prefix_duplicate_row", code)
                    continue
            rows.append([f"**{code}**" if d["b"] is None else code, desc])
        emitted = dropped + "".join(c.replace("**", "") + ("" if k else ":") for r in rows for k, c in enumerate(r))
        if sorted(re.sub(r"\s", "", "".join(source_chars))) != sorted(re.sub(r"\s", "", emitted)):
            AMBIGUOUS.append((sid, "core table not rebuilt: character check failed"))
            out.append(it)
            i += 1
            continue
        t = Table(["| x |", "|---|"])
        t.header, t.sep, t.body, t.dirty = list(CORE_HEADER), True, rows, True
        caption = "**" + it.strip().strip("*").strip() + "**"
        out += [caption, "", t, ""]
        note(sid, "normalize_nist:core_table", f"{plain(it)} -> {len(rows)} rows")
        i = j
    return out


def actor_rows_out_of_tables(items: list, sid: str) -> list:
    """'AI Actor Tasks: …' is a footer spanning the whole table; it becomes the paragraph after it."""
    out = []
    for it in items:
        if not isinstance(it, Table):
            out.append(it)
            continue
        if not any(r and ACTOR_ROW.match(r[0]) for r in it.body):
            out.append(it)
            continue
        keep, footers, current = [], [], None
        for r in it.body:
            if r and ACTOR_ROW.match(r[0]):
                current = list(r)
                footers.append(current)
            elif current is not None and not id_shape(r[0] if r else ""):
                current += r          # the footer wrapped onto the next table row
            else:
                current = None
                keep.append(r)
        it.body, it.dirty = keep, True
        out.append(it)
        for r in footers:
            text = re.sub(r"\s+:", ":", " ".join(c for c in r if c)).strip()
            out += ["", text]
            note(sid, "actor_rows_out_of_tables", text)
    return out


def translate_english_headers(items: list, sid: str) -> list:
    for it in items:
        if not isinstance(it, Table) or not it.sep:
            continue
        joined = " ".join(it.header)
        if PERSIAN_CHARS.search(joined) or not re.search(r"[A-Za-z]", joined):
            continue
        new = []
        for c in it.header:
            key = re.sub(r"\s+", " ", c.strip().lower())
            if key in HEADER_MAP:
                new.append(HEADER_MAP[key])
            else:
                if c.strip() and not LATIN_KEEP.match(c.strip()):
                    UNKNOWN_HEADERS[c.strip()] += 1
                new.append(c)
        if new != it.header:
            note(sid, "translate_english_headers", " | ".join(it.header))
            it.header, it.dirty = new, True
    return items


def one_column_tables_to_lists(items: list, sid: str) -> list:
    out = []
    for it in items:
        if not isinstance(it, Table):
            out.append(it)
            continue
        rows = [r for r in it.rows() if not is_separator_row(r)]
        if any(len([c for c in r]) > 1 for r in rows):
            out.append(it)
            continue
        cells = [r[0] for r in rows if r and r[0]]
        if not cells:
            note(sid, "one_column_tables_to_lists:empty_dropped", it.raw[0])
            continue
        if len(cells) == 1:
            out.append(cells[0])
            note(sid, "one_column_tables_to_lists:paragraph", cells[0])
        else:
            out += ["- " + c for c in cells]
            note(sid, "one_column_tables_to_lists:list", cells[0])
    return out


ACRONYM_HEADER = re.compile(r"^(?:acronyms?|اختصارات|اختصار|مخفف‌ها|سرواژه‌ها|سرواژه)$", re.I)


def acronym_like(cell: str) -> bool:
    c = cell.strip()
    if not c or not re.match(r"^[A-Z0-9]{2,}(?:[/\s]|$)", c):
        return False
    return "/" in c or not re.search(r"\b[A-Za-z]*[a-z][A-Za-z]*\b", c)


def realign_acronym_column(t: Table, sid: str) -> None:
    """Glossary tables (Acronyms | Term | Definition | Synonyms): the extractor drops the empty
    acronym cell, so a term lands under 'Acronyms'. Shift such rows one column right."""
    if t.width() < 3 or not ACRONYM_HEADER.match(t.header[0].strip()):
        return
    h = t.width()
    for k, r in enumerate(t.body):
        if not r or acronym_like(r[0]):
            continue
        if len(r) < h or (len(r) == h and not r[-1]):
            t.body[k] = [""] + (r[:-1] if len(r) == h else r)
            t.dirty = True
            note(sid, "repair_ragged_tables:acronym_column_realign", r[0])


def _numeric_scale(cells: list[str]) -> bool:
    return all(re.match(r"^[0-9۰-۹]{1,2}\b", c) for c in cells if c) and any(cells)


def repair_ragged_tables(items: list, sid: str) -> list:
    """Every table rectangular: stray separator / empty rows dropped, trailing empty cells trimmed,
    systematic width mismatches widened, ID-anchored short rows shifted, the rest padded/merged."""
    out = []
    for it in items:
        if not isinstance(it, Table):
            out.append(it)
            continue
        body0 = it.body
        body = [r for r in body0 if not is_separator_row(r) and any(c for c in r)]
        if len(body) != len(body0):
            note(sid, "repair_ragged_tables:stray_or_empty_row", str(len(body0) - len(body)))
            it.dirty = True
        it.body = body
        realign_acronym_column(it, sid)
        widths = [len(r) for r in it.rows()]
        if len(set(widths)) == 1:
            if it.sep and len(split_cells(it.raw[1])) != it.width():
                note(sid, "repair_ragged_tables:separator_width", it.raw[0])
                it.dirty = True
            out.append(it)
            continue
        h = it.width()
        # trailing empty cells beyond the header width are layout padding
        for k, r in enumerate(it.body):
            while len(r) > h and not r[-1]:
                r = r[:-1]
            it.body[k] = r
        long_rows = [r for r in it.body if len(r) > h]
        if long_rows:
            w = max(len(r) for r in long_rows)
            same = len({len(r) for r in it.body}) == 1
            if same or len(long_rows) * 2 >= len(it.body):
                pad = [""] * (w - h)
                it.header = (pad + it.header) if _numeric_scale(it.header) else (it.header + pad)
                note(sid, "repair_ragged_tables:widen_header", f"{h}->{w}")
                h = w
            else:
                for k, r in enumerate(it.body):
                    if len(r) > h:
                        it.body[k] = r[:h - 1] + [" ".join(c for c in r[h - 1:] if c)]
                        note(sid, "repair_ragged_tables:merge_into_last_cell", r[0])
                        AMBIGUOUS.append((sid, f"merged {len(r)}->{h} cells: {r[0][:40]}"))
        # short rows: shift when the first cell is an ID whose shape lives in a later column
        full = [r for r in it.body if len(r) == h]
        col_shapes = [Counter(id_shape(r[c]) for r in full if id_shape(r[c])) for c in range(h)]
        for k, r in enumerate(it.body):
            if len(r) >= h:
                continue
            shift = 0
            shp = id_shape(r[0]) if r else ""
            if shp and not col_shapes[0].get(shp):
                for c in range(1, h - len(r) + 1):
                    if col_shapes[c].get(shp):
                        shift = c
                        break
            it.body[k] = [""] * shift + r + [""] * (h - len(r) - shift)
            note(sid, "repair_ragged_tables:shift_short_row" if shift else "repair_ragged_tables:pad_short_row", r[0] if r else "")
        it.dirty = True
        out.append(it)
    return out


def normalize_nist_labels(items: list, sid: str) -> list:
    """Single statements become headings '### GOVERN 1.1: …'; code headings use the Latin label."""
    out = []
    for it in items:
        if isinstance(it, str):
            m = STATEMENT.match(it)
            hm = HEADING.match(it.strip())
            if m and not hm:
                func = FUNC_FA.get(m.group(2), m.group(2))
                a, b = m.group(3).translate(LATIN_DIGITS), m.group(4).translate(LATIN_DIGITS)
                stmt = m.group(5).strip().strip("*").strip()
                new = f"### {func} {a}.{b}: {stmt}"
                if new != it:
                    note(sid, "normalize_nist:statement_heading", f"{func} {a}.{b}")
                it = new
            elif hm:
                hm2 = re.match(r"^(#+)\s*(GOVERN|MAP|MEASURE|MANAGE|حاکمیت|حکمرانی|نگاشت|سنجش|مدیریت)\s*-?\s*([0-9۰-۹]+)\s*[.٫]\s*([0-9۰-۹]+)\s*$", it.strip())
                if hm2:
                    func = FUNC_FA.get(hm2.group(2), hm2.group(2))
                    new = f"{hm2.group(1)} {func} {hm2.group(3).translate(LATIN_DIGITS)}.{hm2.group(4).translate(LATIN_DIGITS)}"
                    if new != it:
                        note(sid, "normalize_nist:code_heading", new)
                    it = new
        out.append(it)
    return out


def tidy(text: str) -> str:
    text = re.sub(r"[ \t]+\n", "\n", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip()


def substantive(text: str) -> bool:
    for l in text.split("\n"):
        s = l.strip()
        if s and not HEADING.match(s) and not FIGURE_ONLY.match(s):
            return True
    return False


# --------------------------------------------------------------------------- markdown artefacts

STRIKE_FRAG = re.compile(r"(\s?)~~([^~\s]{1,3})~~(\s?)")    # "AI ~~N~~ ow" = an underscore the PDF lost
URL_START = re.compile(r"://|doi|\bID:")
TILDE_FRAG = re.compile(r"\s*(?:_\{_)?\s*\[\^_∼_\]\s*(?:_\}_)?\s*")  # "edu/ _{_[^_∼_] _}_ name" = "edu/~name"
NONNUM_REF = re.compile(r"\[\^(?![0-9۰-۹])[^\]]+\](?!:)\s?")   # "[^ˇ]", "[^i i i]": accent / roman marks, not notes
TEX_ESCAPE = re.compile(r"\s*_\\_\s*")
LEAD_US_STAR = re.compile(r"^(\s*)_\*(?=\S)")
MID_HEADING = re.compile(r"\S[ \t]+#{2,6}[ \t]+\S")


def in_url(line: str, pos: int) -> bool:
    last = None
    for last in URL_START.finditer(line[:pos]):
        pass
    if last is None:
        return False
    tail = line[last.end():pos]
    return len(tail) <= 160 and not PERSIAN_CHARS.search(tail)


def clean_markdown_artefacts(text: str, sid: str) -> str:
    out = []
    for line in text.split("\n"):
        # An extracted-image reference wrapped in TeX display math: the PNG never
        # shipped, so the line would print literally.
        if re.fullmatch(r"\s*\$\$\s*!\[[^\]]*\]\([^)]*\)\s*\$\$\s*", line):
            COUNT["clean_markdown_artefacts:image_debris"] += 1
            note(sid, "clean_markdown_artefacts", line)
            continue
        new = TILDE_FRAG.sub("~", line)
        if "~~" in new:
            src = new
            new = STRIKE_FRAG.sub(lambda m: ("_" + m.group(2)) if in_url(src, m.start())
                                  else (m.group(1) + m.group(2)), src)
        new = NONNUM_REF.sub("", new)
        new = TEX_ESCAPE.sub("", new).replace("textquotesingle ", "’").replace("textquotesingle", "’")
        new = LEAD_US_STAR.sub(r"\1*", new)
        if new != line:
            note(sid, "clean_markdown_artefacts", line)
        out.append(new)
    return "\n".join(out)


def artefact_count(text: str) -> int:
    n = 0
    for line in text.split("\n"):
        n += len(STRIKE_FRAG.findall(line)) + len(re.findall(r"_\{_|_\}_", line)) + len(NONNUM_REF.findall(line))
        n += len(TEX_ESCAPE.findall(line)) + (1 if LEAD_US_STAR.match(line) else 0)
        cells = split_cells(line) if TABLE_LINE.match(line) else [line]
        for c in cells:
            if c.count("**") % 2 or (c is not line and re.match(r"^#{1,6}\s", c.strip())) or MID_HEADING.search(c):
                n += 1
    return n


# --------------------------------------------------------------------------- table header rows

GROUP_TERM = re.compile(r"^\**\s*«[^»]+»\s*\([A-Za-z][^)]*\)\s*\**$")   # «اثربخشی» (Efficacy)
BOLD_ONLY = re.compile(r"^\*\*[^*]+\*\*$")
TIME_CELL = re.compile(r"^[0-9۰-۹]{1,2}:[0-9۰-۹]{2}\s*(?:AM|PM|am|pm)?$")
STD_CELL = re.compile(r"^([A-Z][A-Z/.-]{1,15})\s+[0-9]")


def _words(c: str) -> int:
    return len(c.split())


def _term(c: str) -> bool:
    return 0 < _words(c) <= 8 and not c.strip().rstrip("*").endswith((".", ":", "؛"))


def row_shape(c: str) -> str:
    c = c.strip()
    if TIME_CELL.match(c):
        return "time"
    m = STD_CELL.match(c)
    return ("std:" + m.group(1)) if m else id_shape(c)


def is_group_label_row(r: list[str]) -> bool:
    return bool(r and (GROUP_TERM.match(r[0].strip()) or BOLD_ONLY.match(r[0].strip()))
                and not any(c.strip() for c in r[1:]))


def header_kind(t: Table) -> str:
    """Why a table's header row is not a row of column labels: 'group' (a category row of the same
    term/definition shape as the rows below it), 'caption' (the table caption), 'data' (an ordinary
    data row: term/definition, time or identifier like the body's first cells), or ''."""
    if not t.sep or not t.body:
        return ""
    h, body, n = t.header, t.body, len(t.body)
    if (t.width() in (2, 3) and GROUP_TERM.match(h[0].strip()) and all(c.strip() for c in h[1:])
            and _words(" ".join(h[1:])) >= 6
            and sum(1 for r in body if GROUP_TERM.match(r[0].strip())) * 3 >= 2 * n):
        return "group"
    caps = [c for c in h if CAPTION.match(c.strip())]
    rest = [c for c in h if c.strip() and not CAPTION.match(c.strip())]
    if (len(caps) == 1 and all(re.fullmatch(r"[0-9۰-۹]{1,3}", c.strip()) for c in rest) and n >= 2
            and all(c.strip() and _words(c) <= 6 for c in body[0])):
        return "caption"
    if (t.width() == 2 and _term(h[0]) and _words(h[1]) >= 6 and not any(CAPTION.match(c.strip()) for c in h)
            and sum(1 for r in body if _term(r[0]) and _words(r[1]) >= 6) * 3 >= 2 * n):
        return "data"
    hs = row_shape(h[0])
    if hs and sum(1 for r in body if row_shape(r[0]) == hs) * 3 >= 2 * n:
        return "data"
    return ""


def _lead_line(cells: list[str]) -> str:
    term = cells[0].strip().strip("*").strip()
    rest = " — ".join(c.strip() for c in cells[1:] if c.strip())
    return f"**{term}:** {rest}" if rest else f"**{term}**"


def _headerless(rows: list[list[str]]) -> Table:
    return Table(["| " + " | ".join(r) + " |" for r in rows])


def _push_block(out: list, block) -> None:
    if out and not (isinstance(out[-1], str) and not out[-1].strip()):
        out.append("")
    out.append(block)
    out.append("")


def table_header_rows(items: list, sid: str) -> list:
    """Mobile cards label every cell with the header row, so a header that is really a group row,
    a caption or a data row repeats on every card. Group row -> bold lead line + headerless table
    (one per group, also for label-only group rows inside the table); caption -> caption line +
    first row as header; data row -> headerless table that keeps the row. No header is invented."""
    out = []
    for it in items:
        if not isinstance(it, Table):
            out.append(it)
            continue
        kind = header_kind(it)
        mid = it.width() in (2, 3) and any(is_group_label_row(r) for r in it.body)
        if kind == "group" or mid:
            groups = [(it.header, [])] if kind == "group" else [(None, [])]
            for r in it.body:
                if is_group_label_row(r):
                    groups.append((r, []))
                else:
                    groups[-1][1].append(r)
            for k, (label, rows) in enumerate(groups):
                if label is not None:
                    _push_block(out, _lead_line(label))
                if not rows:
                    continue
                if label is None and it.sep:      # rows before the first in-table group keep the real header
                    t = Table(it.lines()[:2] + ["| " + " | ".join(r) + " |" for r in rows])
                else:
                    t = _headerless(rows)
                _push_block(out, t)
            note(sid, "table_header_rows:group", " | ".join(it.header))
            continue
        if kind == "caption":
            cap = next(c for c in it.header if CAPTION.match(c.strip())).strip().strip("*").strip()
            prev = next((x for x in reversed(out) if not (isinstance(x, str) and not x.strip())), None)
            if not (isinstance(prev, str) and plain(prev) == plain(cap)):
                _push_block(out, f"**{cap}**")
            dropped = [c for c in it.header if c.strip() and not CAPTION.match(c.strip())]
            it.header, it.body, it.dirty = it.body[0], it.body[1:], True
            note(sid, "table_header_rows:caption", cap + (f" (dropped mark {dropped})" if dropped else ""))
            out.append(it)
            continue
        if kind == "data":
            it.sep, it.dirty = False, True
            note(sid, "table_header_rows:data_row", " | ".join(it.header))
        out.append(it)
    return out


# --------------------------------------------------------------------------- cover titles

TITLE_SYNONYMS = {  # translation variants of the same title word (comparison only)
    "ریسکها": "خطرها", "ریسک": "خطر", "اثرات": "اثرها", "سنجش": "ارزیابی", "توضیحپذیر": "تبیینپذیر",
    "تفسیرپذیر": "تبیینپذیر", "مبانی": "پایههای", "رویههای": "شیوههای", "واژگان": "واژهنامه",
    "واژهنامهای": "واژهنامه", "دادگستری": "دادگاهها", "مطالبی": "مواد", "قاضیان": "قضات",
    "مقدماتی": "آزمایشی", "ژرف": "جامع", "تفصیلی": "جامع", "نمایه": "پروفایل",
}


def title_tokens(s: str) -> list[str]:
    s = plain(s.replace("مؤسسه ملی استاندارد و فناوری آمریکا", "NIST"))
    s = re.sub(r"\([^()]*[A-Za-z][^()]*\)", " ", s)
    s = s.replace("‌", "").replace("ٔ", "").replace("ي", "ی").replace("ك", "ک")
    s = re.sub(r"[«»:–—\-،,.()\"'؛/]", " ", s)
    return [TITLE_SYNONYMS.get(w, w) for w in s.split()]


def near_title(line: str, cover_tokens: list) -> bool:
    """Normalised similarity to a cover/document title (another phrasing of the same title)."""
    toks = title_tokens(line)
    if len(toks) < 2:
        return False
    a, sa = "".join(toks), set(toks)
    for t in cover_tokens:
        if not t:
            continue
        r = difflib.SequenceMatcher(None, a, "".join(t), autojunk=False).ratio()
        j = len(sa & set(t)) / len(sa | set(t))
        if r >= 0.85 or (j >= 0.75 and r >= 0.6):
            return True
    return False


def cover_title_tokens(chapter: dict, doc_titles: dict) -> list:
    doc = doc_titles.get(chapter.get("source_doc") or "", {})
    raw = [chapter["title_fa"], chapter_title(chapter, doc_titles, count=False) or "",
           doc.get("title_fa", ""), doc.get("subtitle_fa", ""),
           (doc.get("title_fa", "") + " " + doc.get("subtitle_fa", "")).strip()]
    raw += [x["title_fa"] for x in chapter["sections"] if x["title_fa"].strip() != FRONT_TITLE]
    return [title_tokens(x) for x in raw if x.strip()]


def cover_zone_lines(text: str) -> list[str]:
    out = []
    for l in text.split("\n"):
        s = l.strip()
        if not s or TABLE_LINE.match(s):
            continue
        if COVER_ANCHOR.match(s) or (not heading_level(s) and len(s) >= 200):
            break
        out.append(s)
    return out


# --------------------------------------------------------------------------- footnotes

FN_BLOB = re.compile(r"^\s*\[\^\]:\s*(.*)$")                  # "[^]: > 26 text > 27 text": translated note block
FN_QUOTE = re.compile(r"^\s*>\s*([0-9۰-۹]{1,3})\s+(\S.*)$")    # "> ۲ Vogel, M. et al. …": note block without the marker
FN_BARE = re.compile(  # "۱ فرمان اجرایی 14110 …", "11استانداردهای داده …": a note printed as a paragraph
    r"^\s*([0-9۰-۹]{1,3})(?:\s+(?=\S)|(?=[^\s\d۰-۹.()\[\]\-:/٫,،%٪]))(.{15,})$")
FN_MARK = re.compile(r"(?:^|\s)>\s*([0-9۰-۹]{1,3})(?=[^\d۰-۹])")
FN_REF = re.compile(r"\[\^([0-9۰-۹]{1,4}(?:\s*,\s*[0-9۰-۹]{1,4})*)\](?!:)")
FN_DEF = re.compile(r"^\[\^([0-9]+)\]:\s+\S")
SUPERSCRIPT = str.maketrans("0123456789", "⁰¹²³⁴⁵⁶⁷⁸⁹")
GLUED_REF = r"(?<=[A-Za-z0-9])\[\^%s\](?: (?=[A-Za-z]))?"       # GA[^2]M, L[^1], 2[^40]: an exponent, not a note
FN_STATS: dict = {}                                            # structural_id -> [refs, recovered, orphan]
# Notes the Persian text does not carry at all, taken from the English source only because they
# are language-neutral (a URL / a bibliographic citation). sources/ is not in git, so the text is
# pinned here; each was checked against the source page that holds the citing sentence.
SOURCE_NOTES = {
    # sources/extracted/NIST-AI-STD-FED-PLAN-2019.md: 'The Executive Order (EO) on AI<sup>46</sup>'
    ("NIST-AI-STD-FED-PLAN-2019", 46):
        "https://www.whitehouse.gov/presidential-actions/executive-order-maintaining-american-leadership-artificial-intelligence/.",
    # sources/extracted/NIST-SP-1270.md, PDF page 45/77 (Accountability paragraph)
    ("NIST-SP-1270", 22): "Bd. Governors Fed. Rsrv. Sys., supra note 20.",
}


def _fn_key(text: str) -> str:
    return re.sub(r"[\W_]+", "", text.translate(LATIN_DIGITS))


def _fn_parse(sid: str, text: str) -> dict:
    lines = text.split("\n")
    refs, first, blobs = [], {}, []
    for k, line in enumerate(lines):
        m = FN_BLOB.match(line)
        if m or FN_QUOTE.match(line):
            parts = FN_MARK.split(m.group(1) if m else line.strip())
            segs = [[int(parts[x].translate(LATIN_DIGITS)), parts[x], parts[x + 1].strip()]
                    for x in range(1, len(parts), 2)]
            nums = [s[0] for s in segs]
            if (segs or m) and nums == sorted(set(nums)):
                blobs.append({"idx": k, "kind": "blob" if m else "quote", "prefix": parts[0].strip(), "segs": segs})
            elif m:      # numbering not monotonic: keep the text, drop the raw "[^]:" marker
                body = m.group(1).strip()
                lines[k] = body if body.startswith(">") else "> " + body
                note(sid, "footnotes:unparsed_note_block_as_blockquote", body)
            continue
        b = FN_BARE.match(line)
        if b:
            blobs.append({"idx": k, "kind": "bare", "prefix": "",
                          "segs": [[int(b.group(1).translate(LATIN_DIGITS)), b.group(1), b.group(2).strip()]]})

        def norm_ref(mm):
            ns = [int(x.strip().translate(LATIN_DIGITS)) for x in mm.group(1).split(",")]
            for n in ns:
                if n not in refs:
                    refs.append(n)
                    first[n] = k
            return "".join(f"[^{n}]" for n in ns)
        new = FN_REF.sub(norm_ref, line)
        if new != line:
            lines[k] = new
    return {"sid": sid, "lines": lines, "refs": refs, "first": first, "blobs": blobs, "defs": []}


def recover_footnotes(entries: list) -> dict:
    """entries: [(structural_id, text, source_documents)] of one chapter (one source document), in book order.

    The canonical Persian text already carries the translated footnotes, as unlabelled blocks
    ("[^]: > 26 …", "> 26 …") or as a paragraph that starts with the note number. A ref [^n]
    gets the block numbered n in its own section, else the only block numbered n in the
    chapter when no other section cites n (identical repeats of a block count once). A bare
    numbered paragraph is used only in the citing section, after the ref, and only when the
    chapter has no marked block n. Anything else stays an orphan. Unused blocks are kept as
    plain blockquotes; an orphan glued to a Latin letter or digit (GA[^2]M, 2[^40]) is an
    exponent the extractor misread and becomes a superscript."""
    secs = [_fn_parse(sid, text) for sid, text, _ in entries]
    docs = [d for _, _, d in entries]
    marked = defaultdict(list)                                    # n -> [(si, bi, gi)] of marked blocks
    for si, s in enumerate(secs):
        for bi, b in enumerate(s["blobs"]):
            if b["kind"] != "bare":
                for gi, seg in enumerate(b["segs"]):
                    marked[seg[0]].append((si, bi, gi))
    cited = defaultdict(list)
    for si, s in enumerate(secs):
        for n in s["refs"]:
            cited[n].append(si)
    # "[^1213]" = refs 12 and 13 run together
    for si, s in enumerate(secs):
        for n in list(s["refs"]):
            if n in marked or n < 100:
                continue
            d = str(n)
            for cut in range(1, len(d)):
                a, b2 = int(d[:cut]), int(d[cut:])
                if b2 == a + 1 and d[cut] != "0":
                    s["lines"] = [l.replace(f"[^{n}]", f"[^{a}][^{b2}]") for l in s["lines"]]
                    i = s["refs"].index(n)
                    s["refs"][i:i + 1] = [x for x in (a, b2) if x not in s["refs"]]
                    for x in (a, b2):
                        s["first"].setdefault(x, s["first"][n])
                        if si not in cited[x]:
                            cited[x].append(si)
                    cited.pop(n, None)
                    note(s["sid"], "footnotes:split_joined_ref", f"{n} -> {a},{b2}")
                    break
    chapter_refs = set(cited)
    # A block often runs two notes together without the second marker: "> 7 text. 8 text".
    for s in secs:
        for b in s["blobs"]:
            i = 0
            while i < len(b["segs"]):
                n, _, txt = b["segs"][i]
                m = n + 1
                if m in chapter_refs and not marked[m] and (i + 1 == len(b["segs"]) or b["segs"][i + 1][0] > m):
                    hits = list(re.finditer(r"(?<=[.!؟?)»”\]])\s+(?:%d|%s)\s+(?=\S)" % (m, str(m).translate(FA_DIGITS)), txt))
                    if len(hits) == 1:
                        h = hits[0]
                        b["segs"][i][2] = txt[:h.start()].strip()
                        b["segs"].insert(i + 1, [m, str(m), txt[h.end():].strip()])
                        if b["kind"] != "bare":
                            marked[m].append(None)            # placeholder: index rebuilt below
                        note(s["sid"], "footnotes:split_unmarked_note", f"{m} {txt[h.end():]}")
                i += 1
    marked, bare = defaultdict(list), defaultdict(list)
    for si, s in enumerate(secs):
        for bi, b in enumerate(s["blobs"]):
            for gi, seg in enumerate(b["segs"]):
                (bare if b["kind"] == "bare" else marked)[seg[0]].append((si, bi, gi))

    def seg(w):
        return secs[w[0]]["blobs"][w[1]]["segs"][w[2]]
    used = set()
    for si, s in enumerate(secs):
        rec = 0
        orphans = []
        for n in s["refs"]:
            local = [w for w in marked[n] if w[0] == si]
            pool = local or (marked[n] if cited[n] == [si] else [])
            pick = None
            keys = sorted({_fn_key(seg(w)[2]) for w in pool}, key=len)
            if pool and all(keys[-1].startswith(k) for k in keys):
                # identical repeats (or a repeat cut short at a page edge): one note, the longest text
                pick = sorted(pool, key=lambda w: -len(_fn_key(seg(w)[2])))
            elif not marked[n]:
                cand = [w for w in bare[n] if w[0] == si and secs[si]["blobs"][w[1]]["idx"] > s["first"][n]]
                pick = cand if len(cand) == 1 else None
            if not pick and not marked[n] and cited[n] == [si]:
                src = [SOURCE_NOTES[(d, n)] for d in docs[si] if (d, n) in SOURCE_NOTES]
                if len(src) == 1:
                    s["defs"].append((n, src[0]))
                    note(s["sid"], "footnotes:recovered:source_citation", str(n))
                    rec += 1
                    continue
            if not pick or any(w in used for w in pick) or not seg(pick[0])[2]:
                orphans.append(n)
                continue
            used.update(pick)
            s["defs"].append((n, seg(pick[0])[2]))
            note(s["sid"], "footnotes:recovered" + ("" if pick[0][0] == si else ":from_other_section")
                 + (":numbered_paragraph" if secs[pick[0][0]]["blobs"][pick[0][1]]["kind"] == "bare" else ""), str(n))
            rec += 1
        for n in orphans:   # exponent read as a note mark: GA[^2]M, L[^1], 2[^40]
            pat = re.compile(GLUED_REF % n)
            new = [pat.sub(str(n).translate(SUPERSCRIPT), l) for l in s["lines"]]
            if new != s["lines"]:
                s["lines"] = new
                note(s["sid"], "footnotes:orphan_exponent_to_superscript", str(n))
        if s["refs"]:
            FN_STATS[s["sid"]] = [len(s["refs"]), rec, len(orphans)]
    out = {}
    for si, s in enumerate(secs):
        lines = s["lines"]
        for bi, b in enumerate(s["blobs"]):
            keep = [sg for gi, sg in enumerate(b["segs"]) if (si, bi, gi) not in used]
            if b["kind"] == "bare":
                if len(keep) != len(b["segs"]):
                    lines[b["idx"]] = " ".join(f"{sg[1]} {sg[2]}" for sg in keep) or None
                continue
            if b["kind"] == "quote" and len(keep) == len(b["segs"]):
                continue
            # an unlabelled "[^]:" block would print as raw markup: unused notes stay, as blockquotes
            quoted = ([b["prefix"] if b["prefix"].startswith(">") else "> " + b["prefix"]] if b["prefix"] else [])
            quoted += [f"> {sg[1]} {sg[2]}" for sg in keep]
            lines[b["idx"]] = "\n\n".join(quoted) if quoted else None
            if quoted and b["kind"] == "blob":
                note(s["sid"], "footnotes:unused_note_block_as_blockquote", quoted[0])
        text = "\n".join(l for l in lines if l is not None)
        if s["defs"]:
            sup = re.compile(GLUED_REF % r"(\d+)")
            text = text.rstrip() + "\n\n" + "\n".join(
                "[^%d]: %s" % (n, re.sub(r"\s+_(?=[\s,.;:])", "",       # "Davis _, 426": stray italic mark
                                         sup.sub(lambda m: m.group(1).translate(SUPERSCRIPT), " ".join(t.split()))))
                for n, t in s["defs"])
        out[s["sid"]] = text
    return out


# --------------------------------------------------------------------------- titles

LEADING_NUM = re.compile(r"^([0-9۰-۹]+(?:\s*[.٫]\s*[0-9۰-۹]+)*)(\.?)(\s+)(.*)$")


def chapter_title(chapter: dict, doc_titles: dict, count: bool = True) -> str | None:
    title = chapter["title_fa"].strip()
    new = title
    m = LEADING_NUM.match(new)
    if m and m.group(4):
        new = m.group(4).strip()
        COUNT["chapter_title:strip_source_numbering"] += count
    # A source chapter titled with the book's own short name ("AI Governance") gets its document's title.
    # (also: a cover series label such as "NIST Special Publication 1270" used as a chapter title)
    if chapter.get("kind") == "source" and chapter.get("source_doc") in doc_titles and re.match(
            r"^(?:AI Governance\b|انتشار ویژه|NIST (?:AI|SP|IR)\b)", new):
        doc = doc_titles[chapter["source_doc"]]["title_fa"].strip()
        func = re.search(r"—\s*(حاکمیت|نگاشت|سنجش|مدیریت)", new)
        new = doc + (f" — {func.group(1)}" if func else "")
        COUNT["chapter_title:source_document_title"] += count
    return new if new != title else None


def section_title(title: str) -> str | None:
    t = title.strip()
    # NIST AI RMF subcategory titles -> Latin label
    m = re.match(r"^AI Governance\s+(\d+\.\d+)\b", t)
    if m:
        COUNT["section_title:nist_label"] += 1
        return f"GOVERN {m.group(1)}"
    m = re.match(r"^(GOVERN|MAP|MEASURE|MANAGE|حاکمیت|حکمرانی|نگاشت|سنجش|مدیریت)\s+([0-9۰-۹]+)\s*[.٫]\s*([0-9۰-۹]+)"
                 r"(?:\s*\((?:GOVERN|MAP|MEASURE|MANAGE)\s+[\d.]+\))?$", t)
    if m:
        new = f"{FUNC_FA.get(m.group(1), m.group(1))} {m.group(2).translate(LATIN_DIGITS)}.{m.group(3).translate(LATIN_DIGITS)}"
        if new != t:
            COUNT["section_title:nist_label"] += 1
            return new
        return None
    # A bare number before «پیوست» is a stray footnote/page mark, not numbering.
    m = re.match(r"^[0-9۰-۹]{1,2}\s+(پیوست\b.*)$", t)
    if m:
        COUNT["section_title:strip_stray_number"] += 1
        return m.group(1)
    m = LEADING_NUM.match(t)
    if m and m.group(4):
        num = re.sub(r"\s*[.٫]\s*", ".", m.group(1)).translate(FA_DIGITS)
        new = f"{num}{m.group(2)} {m.group(4)}"
        if new != t:
            COUNT["section_title:persian_digits"] += 1
            return new
    return None


def section_title_contextual(section: dict, chapter: dict, text: str, doc_titles: dict) -> tuple[str | None, str]:
    """Title fixes that need the chapter or the text. Returns (new title or None, text)."""
    t = section["title_fa"].strip()
    # A glossary split by initial letter: the source heading is the bare letter.
    if re.fullmatch(r"[A-Z]", t):
        COUNT["section_title:letter_index"] += 1
        return f"واژه‌نامه — {t}", text
    # The source document's cover title echoed as the first section's title.
    if t and not re.search(r"[؀-ۿ]", t) and plain(t) == plain(chapter.get("title_en") or ""):
        lines = text.split("\n")
        first = next((i for i, l in enumerate(lines) if l.strip()), None)
        if first is not None:
            m = re.match(r"^\s*(?:[-*]\s+)?([0-9۰-۹]{1,2}[.)]\s+\S[^\n]{0,40})\s*$", lines[first])
            if m:
                COUNT["section_title:cover_echo_first_heading"] += 1
                return m.group(1).translate(FA_DIGITS), "\n".join(lines[:first] + lines[first + 1:])
        doc = doc_titles.get(chapter.get("source_doc") or "", {})
        if doc.get("title_fa"):
            COUNT["section_title:cover_echo_doc_title"] += 1
            return doc["title_fa"], text
    return None, text


# --------------------------------------------------------------------------- build

def load_book() -> tuple[dict, bytes]:
    raw = BOOK_PATH.read_bytes()
    return json.loads(raw), raw


def iter_sections(book: dict):
    for part in book["parts"]:
        for chapter in part["chapters"]:
            for idx, section in enumerate(chapter["sections"]):
                yield part, chapter, idx, section


def transform_section(section: dict, chapter: dict, idx: int, doc_titles: dict) -> str:
    """Per-section transforms; returns the serialized text (untidied; unchanged text if nothing applied)."""
    sid = section["structural_id"]
    text = section["fa_text"]
    items = parse(clean_markdown_artefacts(text, sid))
    items = strip_pdf_page_debris(items, sid)
    if is_front_section(section, idx, chapter):
        cover = {plain(chapter["title_fa"])} | {plain(x["title_fa"]) for x in chapter["sections"]} - {plain(FRONT_TITLE)}
        doc = doc_titles.get(chapter.get("source_doc") or "", {})
        cover |= {plain(doc.get("title_fa", "")), plain(doc.get("subtitle_fa", ""))} - {""}
        items = strip_front_matter(items, sid, cover, cover_title_tokens(chapter, doc_titles))
    items = strip_pdf_toc(items, sid)
    items = dedupe_nist_statements(items, sid)
    items = dedupe_captions(items, sid)
    items = normalize_nist_core_tables(items, sid)
    items = merge_split_tables(items, sid)
    items = actor_rows_out_of_tables(items, sid)
    items = translate_english_headers(items, sid)
    items = one_column_tables_to_lists(items, sid)
    items = repair_ragged_tables(items, sid)
    items = table_header_rows(items, sid)
    items = normalize_nist_labels(items, sid)
    return serialize(items)


def finalize(section: dict, new: str) -> tuple[str, bool]:
    text = section["fa_text"]
    if new == text:
        return text, False
    new = tidy(new)
    if not substantive(new):
        note(section["structural_id"], "omit_debris_section", section["title_fa"])
        return new, True
    return new, False


def build(book: dict, raw: bytes) -> dict:
    COUNT.clear(); LOG.clear(); UNKNOWN_HEADERS.clear(); AMBIGUOUS.clear(); FN_STATS.clear()
    doc_titles = json.loads(DOC_TITLES_PATH.read_text(encoding="utf-8"))
    chapters, sections = {}, {}
    for part in book["parts"]:
        for chapter in part["chapters"]:
            t = chapter_title(chapter, doc_titles)
            if t:
                chapters[chapter["chapter_id"]] = {"title_fa": t}
    phase1, docs, by_chapter = {}, {}, defaultdict(list)
    for part, chapter, idx, section in iter_sections(book):
        sid = section["structural_id"]
        phase1[sid] = transform_section(section, chapter, idx, doc_titles)
        docs[sid] = list(section.get("source_documents") or [])
        by_chapter[(part["part_id"], chapter["chapter_id"])].append(sid)
    for key in by_chapter:      # footnote numbering is per source document = per chapter
        phase1.update(recover_footnotes([(sid, phase1[sid], docs[sid]) for sid in by_chapter[key]]))
    for part, chapter, idx, section in iter_sections(book):
        entry = {}
        new_title = section_title(section["title_fa"])
        ctx_title, ctx_text = section_title_contextual(section, chapter, phase1[section["structural_id"]], doc_titles)
        if ctx_title:
            new_title = ctx_title
        if new_title and new_title != section["title_fa"]:
            entry["title_fa"] = new_title
        text, omit = finalize(section, ctx_text)
        if omit:
            entry["omit"] = True
        elif text != section["fa_text"]:
            entry["fa_text"] = text
        if entry:
            sections[section["structural_id"]] = entry
    return {
        "schema": SCHEMA,
        "edition_id": book["edition_id"],
        "book_sha256": hashlib.sha256(raw).hexdigest(),
        "generator": GENERATOR,
        "chapters": chapters,
        "sections": sections,
    }


def dump(overlay: dict) -> str:
    return json.dumps(overlay, ensure_ascii=False, sort_keys=True, indent=1) + "\n"


def validate(overlay: dict, book: dict) -> list[str]:
    errors = []
    ids = {s["structural_id"] for _, _, _, s in iter_sections(book)}
    chap_ids = {c["chapter_id"] for p in book["parts"] for c in p["chapters"]}
    for cid, e in overlay["chapters"].items():
        if cid not in chap_ids:
            errors.append(f"unknown chapter {cid}")
        if not isinstance(e.get("title_fa"), str) or not e["title_fa"].strip():
            errors.append(f"empty chapter title {cid}")
    for sid, e in overlay["sections"].items():
        if sid not in ids:
            errors.append(f"unknown section {sid}")
        for k, v in e.items():
            if k not in ("title_fa", "fa_text", "omit"):
                errors.append(f"{sid}: unknown field {k}")
            elif k == "omit" and v is not True:
                errors.append(f"{sid}: omit must be true")
            elif k != "omit" and (not isinstance(v, str) or not v.strip()):
                errors.append(f"{sid}: empty {k}")
        if "fa_text" in e:
            labels = [d.group(1) for d in (FN_DEF.match(l) for l in e["fa_text"].split("\n")) if d]
            if len(labels) != len(set(labels)):
                errors.append(f"{sid}: duplicate footnote definition label")
            if any(FN_BLOB.match(l) for l in e["fa_text"].split("\n")):
                errors.append(f"{sid}: unlabelled [^]: note block left")
            for t in parse(e["fa_text"]):
                if isinstance(t, Table) and len({len(r) for r in t.rows()}) != 1:
                    errors.append(f"{sid}: non-rectangular table: {t.raw[0][:60]}")
                if isinstance(t, Table) and t.sep and len(split_cells(t.raw[1])) != t.width():
                    errors.append(f"{sid}: separator width mismatch: {t.raw[0][:60]}")
    return errors


# --------------------------------------------------------------------------- report

def metrics(book: dict, overlay: dict | None) -> dict:
    ov = (overlay or {}).get("sections", {})
    ovc = (overlay or {}).get("chapters", {})
    m = Counter()
    sizes = Counter()
    chapter_titles = []
    suspicious = []
    doc_titles = json.loads(DOC_TITLES_PATH.read_text(encoding="utf-8"))
    for part in book["parts"]:
        for chapter in part["chapters"]:
            ct = ovc.get(chapter["chapter_id"], {}).get("title_fa", chapter["title_fa"])
            chapter_titles.append(ct)
            if LEADING_NUM.match(ct.strip()):
                m["chapter titles with leading numbers"] += 1
    for _, chapter, idx, s in iter_sections(book):
        e = ov.get(s["structural_id"], {})
        m["sections"] += 1
        if e.get("omit"):
            m["sections omitted (debris stub)"] += 1
            continue
        text = e.get("fa_text", s["fa_text"])
        title = e.get("title_fa", s["title_fa"]).strip()
        sizes[(chapter["chapter_id"], ovc.get(chapter["chapter_id"], {}).get("title_fa", chapter["title_fa"]))] += len(text)
        if re.match(r"^[0-9]+(?:\.[0-9]+)*\.?\s", title):
            m["section titles Latin-numbered"] += 1
        elif re.match(r"^[۰-۹]+(?:[.٫][۰-۹]+)*\.?\s", title):
            m["section titles Persian-numbered"] += 1
        if "[FIGURE" in text:
            m["sections containing [FIGURE"] += 1
        m["leftover markdown artefacts"] += artefact_count(text)
        if is_front_section(s, idx, chapter):
            toks = cover_title_tokens(chapter, doc_titles)
            m["front-matter near-duplicate title lines"] += sum(
                1 for l in cover_zone_lines(text)
                if not l.startswith("- ") and not FIGURE_ONLY.match(l) and near_title(l, toks))
        defs = {int(d.group(1)) for d in (FN_DEF.match(l) for l in text.split("\n")) if d}
        m["footnote definitions [^n]:"] += len(defs)
        m["unlabelled note blocks [^]:"] += sum(1 for l in text.split("\n") if FN_BLOB.match(l))
        for r in FN_REF.finditer(text):
            for x in r.group(1).split(","):
                m["footnote refs"] += 1
                ok = x.strip().isascii() and int(x) in defs
                m["footnote refs with definition" if ok else "footnote refs without definition"] += 1
        for l in text.split("\n"):
            ls = l.strip()
            if not ls.startswith("|") and (any(p.search(ls) for p in METRIC_FRONT) or
                                           (FREE_OF_CHARGE.search(ls) and "doi.org" in ls)):
                m["front-matter/publisher hits"] += 1
            if CONTD_LINE.match(ls) or BACK_TO_INDEX.search(ls) or PAGE_NUMBER_LINE.match(ls) or TOC_LINE.match(ls):
                m["PDF page-debris lines"] += 1
        for t in parse(text):
            if not isinstance(t, Table):
                continue
            m["tables total"] += 1
            if len({len(split_cells(l)) for l in t.lines()}) != 1:
                m["tables ragged"] += 1
            joined = " ".join(t.header)
            if t.sep and not PERSIAN_CHARS.search(joined) and re.search(r"[A-Za-z]", joined):
                m["tables English-only header"] += 1
            if t.width() == 1:
                m["tables one-column"] += 1
            if not t.sep:
                m["tables headerless"] += 1
            kind = header_kind(t)
            if kind == "group" or (t.width() in (2, 3) and any(is_group_label_row(r) for r in t.body)):
                m["tables group-row header"] += 1
            elif kind:
                m["tables data/caption-row header"] += 1
            if is_toc_table(t):
                m["tables PDF-TOC/page-number"] += 1
        first = re.sub(r"^(\s*(\[[^\]]*\]|#+[^\n]*\n|\*+)\s*)+", "", text.strip())
        lines = [l.strip() for l in text.strip().split("\n") if l.strip()]
        last = lines[-1] if lines else ""
        bad_start = re.match(r"^(‌|های\b|ها\b|ای\b|اند\b|ترین\b|[a-z]|[،,.;:)\]])", first)
        bad_end = (not re.match(r"^(\||#|-|\*|\d+[.)]|\[FIGURE|\[\^|>)", last) and
                   re.search(r"[ء-يپچژکگیA-Za-z0-9،,]$", last)
                   and len(last) > 30 and not re.search(r"https?://|www\.|/\S+$", last))
        if bad_start or bad_end:
            suspicious.append((s["structural_id"], "start" if bad_start else "end", (first[:40] if bad_start else last[-50:])))
    dup = Counter(chapter_titles)
    m["duplicate chapter titles"] = sum(c - 1 for c in dup.values() if c > 1)
    m["suspicious section starts/ends"] = len(suspicious)
    return {"m": m, "sizes": sizes, "suspicious": suspicious,
            "dups": [t for t, c in dup.items() if c > 1]}


def report(book: dict, overlay: dict) -> None:
    base, after = metrics(book, None), metrics(book, overlay)
    keys = ["sections", "sections omitted (debris stub)", "tables total", "tables ragged",
            "tables English-only header", "tables one-column", "tables PDF-TOC/page-number",
            "tables group-row header", "tables data/caption-row header", "tables headerless",
            "footnote refs", "footnote refs with definition", "footnote refs without definition",
            "footnote definitions [^n]:", "unlabelled note blocks [^]:",
            "sections containing [FIGURE", "front-matter/publisher hits",
            "front-matter near-duplicate title lines", "leftover markdown artefacts", "PDF page-debris lines",
            "section titles Latin-numbered", "section titles Persian-numbered",
            "chapter titles with leading numbers", "duplicate chapter titles", "suspicious section starts/ends"]
    print(f"{'metric':42} {'baseline':>9} {'after':>9}")
    for k in keys:
        print(f"{k:42} {base['m'][k]:>9} {after['m'][k]:>9}")
    print(f"\noverlay: {len(overlay['chapters'])} chapter entries, {len(overlay['sections'])} section entries "
          f"({sum(1 for e in overlay['sections'].values() if 'fa_text' in e)} fa_text, "
          f"{sum(1 for e in overlay['sections'].values() if 'title_fa' in e)} title_fa, "
          f"{sum(1 for e in overlay['sections'].values() if e.get('omit'))} omit)")
    print("\ntransform counters:")
    for k in sorted(COUNT):
        print(f"  {k:52} {COUNT[k]}")
    print("\nchapter title changes:")
    for cid, e in sorted(overlay["chapters"].items()):
        orig = next(c["title_fa"] for p in book["parts"] for c in p["chapters"] if c["chapter_id"] == cid)
        print(f"  {cid}: {orig}  ->  {e['title_fa']}")
    if after["dups"]:
        print("\nduplicate chapter titles (after):", after["dups"])
    print("\nremoved PDF debris per section (review):")
    for sid in sorted(LOG):
        rem = [f"{t[6:] if t.startswith('strip_') else t}: {s}" for t, s in LOG[sid]
               if t.startswith("strip_") or t.startswith("omit")]
        if rem:
            print(f"  {sid[14:]} ({len(rem)})")
            for r in rem:
                print(f"      - {r}")
    print("\ntable header rows changed (kind: header):")
    for sid in sorted(LOG):
        for t, snip in LOG[sid]:
            if t.startswith("table_header_rows"):
                print(f"  {sid[14:]} {t[18:]}: {snip}")
    print("\nfootnotes per section (refs / recovered / orphan):")
    for sid in sorted(FN_STATS):
        r, ok, orphan = FN_STATS[sid]
        print(f"  {sid[14:]} {r:>3} {ok:>3} {orphan:>3}")
    tot = [sum(v[k] for v in FN_STATS.values()) for k in range(3)]
    print(f"  total {tot[0]} distinct refs, {tot[1]} recovered, {tot[2]} orphan, in {len(FN_STATS)} sections")
    if UNKNOWN_HEADERS:
        print("\nEnglish header cells left untranslated (no fixed mapping):")
        for c, n in sorted(UNKNOWN_HEADERS.items()):
            print(f"  {n}x {c}")
    if AMBIGUOUS:
        print("\nambiguous table repairs (fallback used):")
        for sid, n in AMBIGUOUS:
            print(f"  {sid[14:]}: {n}")
    print("\nsuspicious section starts/ends (display text; not repaired unless the fragment is provable):")
    for sid, kind, snip in after["suspicious"]:
        print(f"  {sid[14:]} {kind}: {snip}")
    print("\ntop-10 chapters by fa_text chars (display):")
    for (cid, title), n in after["sizes"].most_common(10):
        print(f"  {cid} {n:>7}  {title[:60]}")


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    g = ap.add_mutually_exclusive_group()
    g.add_argument("--check", action="store_true", help="exit 1 if the committed overlay is stale")
    g.add_argument("--report", action="store_true", help="print baseline vs after metrics")
    args = ap.parse_args()
    book, raw = load_book()
    overlay = build(book, raw)
    errors = validate(overlay, book)
    if errors:
        for e in errors:
            print("ERROR:", e, file=sys.stderr)
        return 2
    text = dump(overlay)
    if args.check:
        if not OVERLAY_PATH.exists():
            print("reader-overlay.json missing", file=sys.stderr)
            return 1
        current = OVERLAY_PATH.read_text(encoding="utf-8")
        try:
            cur_sha = json.loads(current).get("book_sha256")
        except ValueError:
            cur_sha = None
        if cur_sha != overlay["book_sha256"]:
            print("stale: book_sha256 does not match master/book.json", file=sys.stderr)
            return 1
        if current != text:
            print("stale: regenerated overlay differs from the committed file", file=sys.stderr)
            return 1
        print("reader-overlay.json is up to date")
        return 0
    if args.report:
        report(book, overlay)
        return 0
    OVERLAY_PATH.write_text(text, encoding="utf-8")
    print(f"wrote {OVERLAY_PATH.relative_to(ROOT)}: {len(overlay['chapters'])} chapters, {len(overlay['sections'])} sections")
    return 0


if __name__ == "__main__":
    sys.exit(main())
