#!/usr/bin/env python3
"""Shared Markdown→HTML renderer for the Persian master (used by HTML book, PDF, and DOCX builders)."""
from __future__ import annotations
import html, json, re, shutil
from pathlib import Path
import markdown

ROOT = Path(__file__).resolve().parent.parent
FOOT = re.compile(r"^\[\^([^\]]*)\]:\s*(.*)$")          # label may be empty when extraction lost the footnote number
ARTIFACT_LINE = re.compile(r"^\s*[-*•–—]\s*[-–—]?\s*$")   # bare list markers left behind by PDF extraction
FIG = re.compile(r"^\[FIGURE ([^\]]+)\]\s*(.*)$")
LABELS = {"source_translation": "ترجمهٔ متن اصلی", "editorial_synthesis": "یادداشت تدوینگر", "editorial_localization_ir": "کاربرد در سازمان‌های ایرانی", "editorial_template_ir": "نمونه فرم"}
_blocks_cache: dict[str, dict] = {}

def figure_asset(figure_id: str) -> dict | None:
    dk = figure_id.split("::")[0]
    if dk not in _blocks_cache:
        p = ROOT / "sources" / "extracted" / f"{dk}.blocks.jsonl"
        _blocks_cache[dk] = {b.get("figure_id"): b for b in (json.loads(l) for l in open(p, encoding="utf-8")) if b.get("figure_id")} if p.exists() else {}
    return _blocks_cache[dk].get(figure_id)

def demote(md_text: str, levels: int = 2) -> str:
    out = []
    for line in md_text.split("\n"):
        m = re.match(r"^(#{1,6})\s+(.*)$", line)
        out.append("#" * min(6, len(m.group(1)) + levels) + " " + m.group(2) if m else line)
    return "\n".join(out)

LTR_BRACKET = re.compile(r"\[([A-Za-z0-9][A-Za-z0-9,;:\.\- –]{0,40})\]")          # [12], [ix, 6], [Smith 2021]
LTR_PAREN = re.compile(r"\(([A-Za-z][A-Za-z0-9 ,;:\.\-–/&']{1,90})\)")             # (English Term, ACR)
LTR_TOKEN = re.compile(r"(?<![\w/`\"'<>=])((?:NIST\s)?(?:AI|SP|IR)\s?\d{3,4}(?:-\d+[A-Za-z]?)?(?:e\d{4})?|ISO/IEC(?:\s?TS)?\s?\d{4,5}(?::\d{4})?|[A-Z]{2,6}(?:/[A-Z]{2,6})?\s?\d{2,5}(?:\.\d+)*)(?![\w/])")

def isolate_ltr(md_text: str) -> str:
    """Wrap Latin citations, parentheticals and identifiers in dir=ltr spans so bidi line-breaking keeps them intact.
    Skips table separator rows, code spans, URLs and figure markers."""
    out = []
    for line in md_text.split("\n"):
        if line.lstrip().startswith(("[FIGURE", "[^", "|--")) or "http" in line or "`" in line:
            out.append(line); continue
        line = LTR_BRACKET.sub(lambda m: f'<span dir="ltr">[{m.group(1)}]</span>', line)
        line = LTR_PAREN.sub(lambda m: f'<span dir="ltr">({m.group(1)})</span>', line)
        line = LTR_TOKEN.sub(lambda m: f'<span dir="ltr">{m.group(1)}</span>', line)
        out.append(line)
    return "\n".join(out)

def md_to_html(md_text: str, asset_dir: Path | None = None, asset_url_prefix: str = "../../assets/figures", heading_offset: int = 2) -> str:
    """Render one section's Markdown. Footnote definitions → ordered list; [FIGURE …] → <figure>; tables → semantic tables."""
    foot, body, figs = [], [], []
    md_text = isolate_ltr(md_text)
    for line in md_text.split("\n"):
        if ARTIFACT_LINE.match(line):
            continue
        m = FOOT.match(line.strip())
        if m:
            foot.append((m.group(1) or str(len(foot) + 1), m.group(2))); continue
        f = FIG.match(line.strip())
        if f:
            fid, cap = f.group(1).strip(), f.group(2).strip()
            b = figure_asset(fid)
            src = ""
            if b and b.get("asset") and (ROOT / b["asset"]).exists():
                name = fid.replace("::", "_") + Path(b["asset"]).suffix
                if asset_dir:
                    asset_dir.mkdir(parents=True, exist_ok=True); shutil.copy2(ROOT / b["asset"], asset_dir / name)
                src = f"{asset_url_prefix}/{name}" if asset_dir else str(ROOT / b["asset"])
            figs.append(len(body))
            body.append(f'\n<figure class="source-figure" data-figure-id="{html.escape(fid)}">' + (f'<img src="{html.escape(src)}" alt="{html.escape(cap or fid)}" loading="lazy">' if src else "") +
                        (f"<figcaption>{html.escape(cap)}</figcaption>" if cap else "") + "</figure>\n")
            continue
        body.append(line)
    text = demote("\n".join(body), heading_offset)
    text = re.sub(r"^\*(.+)\*$", r'<p class="caption">\1</p>', text, flags=re.M)
    text = re.sub(r"\[\^([^\]\s]{0,12})\]", lambda m: f'<sup class="fnref">{m.group(1)}</sup>' if m.group(1) else "", text)   # inline footnote references
    h = markdown.markdown(text, extensions=["tables", "sane_lists", "md_in_html"], output_format="html5")
    h = h.replace("<table>", '<div class="table-wrap"><table>').replace("</table>", "</table></div>")
    if foot:
        h += '<ol class="footnotes" aria-label="پانوشت‌ها">' + "".join(f'<li id="fn-{html.escape(n)}"><span class="fn-num">{html.escape(n)}</span> {html.escape(t)}</li>' for n, t in foot) + "</ol>"
    return h

def fa_digits(s: str) -> str:
    return s.translate(str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹"))
