#!/usr/bin/env python3
"""Phase 21 — print-quality Persian PDF (MasterPrompt §20, §22, §46, §76). WeasyPrint (CSS Paged Media), A4, RTL, Vazirmatn, TOC with page numbers,
PDF bookmarks, running headers, page numbers, XMP/custom metadata. Output: pdf/fa/book-fa.pdf (+ pdf/fa/book-fa.print.html)."""
from __future__ import annotations
import html as H, json, subprocess, sys
from pathlib import Path
import yaml
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, render  # noqa: E402

PUB = yaml.safe_load(open(ROOT / "config" / "publishing.yaml", encoding="utf-8"))
PROV = yaml.safe_load(open(ROOT / "config" / "provenance.yaml", encoding="utf-8"))
SRC = {d["doc_key"]: d for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]}
E = H.escape

def font_faces() -> str:
    """@font-face rules from locally installed fonts (fc-list); falls back to system Persian fonts."""
    faces = []
    try:
        out = subprocess.run(["fc-list", ":", "family", "file", "style"], capture_output=True, text=True, timeout=20).stdout
    except Exception:
        out = ""
    for line in out.splitlines():
        try:
            path, rest = line.split(":", 1)
        except ValueError:
            continue
        fam = rest.split(":")[0].strip().split(",")[0]; style = rest.split("style=")[-1].split(",")[0] if "style=" in rest else "Regular"
        if fam in ("Vazirmatn", "Noto Naskh Arabic", "Noto Sans Mono", "Inter", "Noto Sans"):
            weight = "700" if "Bold" in style else "600" if "SemiBold" in style else "500" if "Medium" in style else "400"
            if any(s in style for s in ("Thin", "Light", "Black", "ExtraBold", "Italic")) and "Bold" not in style: continue
            faces.append(f'@font-face{{font-family:"{fam}";src:url("file://{path.strip()}");font-weight:{weight};font-style:normal}}')
    return "\n".join(sorted(set(faces)))

CSS = """
@page { size: A4; margin: 25mm 20mm 22mm 20mm;
  @bottom-center { content: counter(page, persian); font-family: "Vazirmatn"; font-size: 9pt; color: #555 }
  @top-right { content: string(chapter); font-family: "Vazirmatn"; font-size: 8.5pt; color: #666 }
  @top-left { content: "کهن‌دژ — ویرایش EDITION"; font-family: "Vazirmatn"; font-size: 8.5pt; color: #666; direction: rtl } }
@page :first { @top-right { content: none } @top-left { content: none } @bottom-center { content: none } }
@page part { @top-right { content: none } @top-left { content: none } }
html { font-family: "Vazirmatn", "Noto Naskh Arabic", "Geeza Pro", sans-serif; font-size: 11pt; line-height: 1.75; color: #1b1f24 }
body { direction: rtl; margin: 0 }
:lang(en), .latin { font-family: "Inter", "Noto Sans", sans-serif } code, .mono, .hash, .cid { font-family: "Noto Sans Mono", "Menlo", monospace; font-size: 8.5pt; direction: ltr; unicode-bidi: isolate; word-break: break-all }
h1, h2, h3, h4 { font-weight: 700; line-height: 1.35; page-break-after: avoid; bookmark-level: none }
h1.chapter { font-size: 20pt; margin: 0 0 4mm; string-set: chapter content(); bookmark-level: 2; page-break-before: always }
h2.unit { font-size: 14.5pt; margin-top: 8mm; border-bottom: 1.5px solid #d9dee5; padding-bottom: 1mm; bookmark-level: 3 }
h3 { font-size: 12pt; margin-top: 5mm } h4 { font-size: 11pt } h5, h6 { font-size: 10.5pt }
section.part-opener { page: part; page-break-before: always; text-align: center; padding-top: 25mm } section.part-opener .pid { font-size: 14pt; color: #555 }
section.part-opener h1 { font-size: 24pt; bookmark-level: 1; string-set: chapter content() }
p { margin: 0 0 2.6mm; text-align: justify; orphans: 3; widows: 3 } ul, ol { padding-right: 6mm; padding-left: 0; margin: 0 0 2.6mm } li { margin: 0.8mm 0 }
table { border-collapse: collapse; width: 100%; font-size: 9pt; margin: 3mm 0; page-break-inside: auto } tr { page-break-inside: avoid } th, td { border: 0.6px solid #b8bfc7; padding: 1.4mm 2mm; vertical-align: top } th { background: #eef1f4; font-weight: 700 } thead { display: table-header-group }
figure { margin: 4mm 0; text-align: center; page-break-inside: avoid } figure img { max-width: 100%; max-height: 120mm; border: 0.5px solid #d9dee5 } figcaption, .caption { font-size: 9pt; color: #555; margin-top: 1.5mm; text-align: center }
ol.footnotes { font-size: 8.5pt; color: #444; border-top: 0.6px dashed #b8bfc7; margin-top: 4mm; padding-top: 1.5mm } .fn-num { font-weight: 700 }
.unit-meta { font-size: 7.5pt; color: #666; margin: 0 0 3mm } .unit-meta .cid { background: #f2f4f6; padding: 0 1mm }
aside.editorial { border: 0.8px solid #e0a800; border-right: 4px solid #e0a800; background: #fff8e6; padding: 3mm 4mm; margin: 4mm 0; page-break-inside: auto }
aside.editorial > .label { display: inline-block; font-weight: 700; font-size: 8.5pt; background: #e0a800; color: #fff; padding: 0 2mm; margin-bottom: 1.5mm }
aside.localization { background: #eaf7ee; border-color: #2e8b57 } aside.localization > .label { background: #2e8b57 }
aside.executive { background: #eef2ff; border-color: #4054b2 } aside.executive > .label { background: #4054b2 }
aside.technical { background: #f3eefc; border-color: #7b3fbf } aside.technical > .label { background: #7b3fbf }
aside.template { background: #fdf0f0; border-color: #c0392b } aside.template > .label { background: #c0392b }
aside.source-box { background: #f6f8fa; border: 0.6px solid #d9dee5; padding: 2.5mm 3.5mm; margin: 3mm 0 5mm; font-size: 9pt } aside.source-box dl { margin: 0 } aside.source-box dt { display: inline; color: #555 } aside.source-box dd { display: inline; margin: 0 } aside.source-box div { margin: 0.5mm 0 }
.attrib { color: #555; font-size: 9pt } .cover { page: part; text-align: center; padding-top: 12mm } .cover-art { width: 120mm; height: auto; display: block; margin: 0 auto 14mm } .part-art { width: 150mm; height: auto; display: block; margin: 0 auto 10mm } .cover h1 { font-size: 26pt; bookmark-level: none } .cover .sub { font-size: 11pt; color: #444 }
nav.toc { page-break-before: always } nav.toc h1 { font-size: 18pt; bookmark-level: 1 } nav.toc ol { list-style: none; padding: 0 } nav.toc li { margin: 1.2mm 0 } nav.toc li.part { font-weight: 700; margin-top: 3mm } nav.toc li.ch { padding-right: 5mm }
nav.toc a { text-decoration: none; color: inherit } nav.toc a::after { content: leader(". ") target-counter(attr(href), page, persian) }
.appendix h1 { font-size: 18pt; page-break-before: always; bookmark-level: 1; string-set: chapter content() }
"""

def build():
    book = json.load(open(ROOT / "master" / "book.json", encoding="utf-8"))
    ver = json.load(open(ROOT / "provenance" / "verification.json", encoding="utf-8")) if (ROOT / "provenance" / "verification.json").exists() else {}
    assets = ROOT / "pdf" / "fa" / "assets"; assets.mkdir(parents=True, exist_ok=True)
    gm = ROOT / "images" / "manifests" / "generated-images.json"
    IMG = {i["image_id"]: ROOT / i["file"] for i in (json.load(open(gm, encoding="utf-8")) if gm.exists() else []) if (ROOT / i["file"]).exists()}
    def img_tag(iid, cls):
        return f'<img class="{cls}" src="file://{IMG[iid]}" alt="">' if iid in IMG else ""
    parts = []
    parts.append(f'<section class="cover">{img_tag("IMG-COVER", "cover-art")}<h1>{E(book["title_fa"])}</h1><p class="sub latin" dir="ltr" style="text-align:center">{E(book["title_en"])}</p><p style="margin-top:40mm">ترجمه، تدوین و بازآرایی فارسی:<br><strong>{E(PUB["compiler"])}</strong></p><p class="sub">بر پایه اسناد و انتشارات اصلی NIST و منابع مشخص‌شده در هر فصل</p><p class="sub">kohandezh.com · ویرایش {E(PUB["edition_id"])}</p></section>')
    parts.append(f'<section class="appendix"><h1>شناسنامهٔ انتشار</h1><p>{E(book["attribution"])}</p><p>ویرایش: <span class="mono">{E(PUB["edition_id"])}</span> · شناسهٔ انتشار: <span class="mono">{E(ver.get("release_id","Release Candidate"))}</span> · تاریخ ساخت: <span class="mono">{E(book["built_at"])}</span></p><p>ریشهٔ مرکل: <span class="hash">{E(book["merkle_root"])}</span></p><p>وضعیت امضا: {E(ver.get("signing_status","PUBLISHER_SIGNING_KEY_REQUIRED"))}</p><p class="attrib">این اثر ترجمهٔ رسمی NIST نیست و NIST آن را تأیید یا منتشر نکرده است. بخش‌های تدوینگر و لایهٔ اجرایی ایرانی با برچسب مشخص از متن ترجمه‌شده متمایز شده‌اند.</p></section>')
    toc = ['<nav class="toc"><h1>فهرست مطالب</h1><ol>']
    for P in book["parts"]:
        toc.append(f'<li class="part"><a href="#{E(P["part_id"])}">{E(P["part_id"])} — {E(P["title_fa"])}</a></li>')
        for C in P["chapters"]:
            if C["sections"]: toc.append(f'<li class="ch"><a href="#{E(C["chapter_id"])}">{E(C["title_fa"])}</a></li>')
    toc.append("</ol></nav>"); parts.append("".join(toc))
    for P in book["parts"]:
        parts.append(f'<section class="part-opener" id="{E(P["part_id"])}">{img_tag("IMG-" + P["part_id"], "part-art")}<div class="pid">{E(P["part_id"])}</div><h1>{E(P["title_fa"])}</h1><p class="attrib">منابع اصلی: {E("، ".join(SRC[d].get("series_identifier", d) for d in P["sources"]))}</p></section>')
        for C in P["chapters"]:
            if not C["sections"]: continue
            body = [f'<h1 class="chapter" id="{E(C["chapter_id"])}">{E(C["title_fa"])}</h1>']
            if C["kind"] == "source":
                d = SRC.get(C["source_doc"], {}); pages = sorted({p for S in C["sections"] for p in S["source_pages"]})
                body.append(f'<aside class="source-box"><dl><div><dt>منبع اصلی: </dt><dd class="latin">{E(d.get("series_identifier",""))} — {E(d.get("title",""))}</dd></div><div><dt>نویسندگان: </dt><dd class="latin">{E(", ".join(d.get("authors") or []) or d.get("organization",""))}</dd></div><div><dt>نوع / وضعیت انتشار: </dt><dd>{E(d.get("publication_type",""))} — {E(d.get("publication_status",""))} — {E(d.get("publication_date",""))}</dd></div><div><dt>صفحات استفاده‌شده: </dt><dd>{E(f"{pages[0]}–{pages[-1]}" if pages else "—")}</dd></div><div><dt>DOI: </dt><dd class="mono">{E(d.get("doi") or "—")}</dd></div></dl></aside>')
            else:
                body.append(f'<p class="attrib">[{E(render.LABELS.get(C["origin"], "یادداشت تدوینگر"))}] این فصل محتوای تدوینگر است و متن NIST نیست.</p>')
            for S in C["sections"]:
                inner = render.md_to_html(S["fa_text"], asset_dir=assets, asset_url_prefix="assets", heading_offset=2)
                head = f'<h2 class="unit" id="{E(S["structural_id"])}">{E(S["title_fa"])}</h2><div class="unit-meta"><span class="cid">{E(S["content_id"])}</span></div>'
                if S["origin"] == "source_translation":
                    body.append(head + inner)
                else:
                    lbl = S.get("label_fa") or render.LABELS.get(S["origin"], "یادداشت تدوینگر")
                    cls = "executive" if lbl == "برای مدیران" else "technical" if lbl == "برای تیم فنی" else "template" if "فرم" in lbl else "localization" if S["origin"] == "editorial_localization_ir" else ""
                    body.append(f'{head}<aside class="editorial {cls}"><span class="label">{E(lbl)}</span>{inner}<p class="attrib">محتوای تدوینگر — متن NIST نیست.</p></aside>')
            parts.append("".join(body))
    # appendices
    gp = ROOT / "translation_memory" / "glossary.json"
    if gp.exists():
        terms = sorted([t for t in json.load(open(gp, encoding="utf-8"))["terms"].values() if t.get("preferred_fa")], key=lambda t: t["preferred_fa"])
        parts.append('<section class="appendix"><h1>پیوست الف — واژه‌نامه</h1><table><thead><tr><th>فارسی</th><th>English</th><th>تعریف</th></tr></thead><tbody>' + "".join(f'<tr><td>{E(t["preferred_fa"])}</td><td class="latin">{E(t["english"])}{(" (" + E(t["acronym"]) + ")") if t.get("acronym") else ""}</td><td>{E(t.get("definition_fa") or "")}</td></tr>' for t in terms) + "</tbody></table></section>")
    parts.append('<section class="appendix"><h1>پیوست ب — کتاب‌شناسی منابع</h1><ol>' + "".join(f'<li class="latin" dir="ltr" style="text-align:left">{E(d.get("series_identifier",""))} — {E(d.get("title",""))}. {E(", ".join(d.get("authors") or []) or d.get("organization",""))}. {E(d.get("publication_type",""))}, {E(d.get("publication_status",""))}, {E(d.get("publication_date",""))}. {("DOI: " + E(d["doi"])) if d.get("doi") else ""} <span class="hash">sha256:{E(d["sha256"][:16])}…</span></li>' for d in SRC.values()) + "</ol></section>")
    smap = json.load(open(ROOT / "master" / "source_map.json", encoding="utf-8")) if (ROOT / "master" / "source_map.json").exists() else []
    parts.append('<section class="appendix"><h1>پیوست ج — نقشهٔ منابع</h1><table><thead><tr><th>شناسهٔ بخش</th><th>سند</th><th>بخش منبع</th><th>صفحات</th></tr></thead><tbody>' + "".join(f'<tr><td class="cid">{E(r["structural_id"])}</td><td class="latin">{E(r["source_series"])}</td><td class="latin">{E(r["source_section"] or "")}</td><td>{E(f"{r['source_pages'][0]}–{r['source_pages'][-1]}" if r["source_pages"] else "—")}</td></tr>' for r in smap) + "</tbody></table></section>")
    parts.append(f'<section class="appendix"><h1>پیوست د — اصالت و اعتبارسنجی</h1><p>هر بخش این کتاب یک شناسهٔ محتوا و یک هش SHA-256 دارد که در درخت مرکل ویرایش {E(PUB["edition_id"])} ثبت شده است.</p><p>ریشهٔ مرکل: <span class="hash">{E(book["merkle_root"])}</span></p><p>کلید عمومی: <span class="hash">{E(ver.get("public_key_ed25519_hex","—"))}</span></p><p>بررسی اصالت: kohandezh.com/ai-book/verify/</p></section>')
    html = f"""<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>{E(book["title_fa"])}</title>
<meta name="author" content="{E(PUB["compiler"])}"><meta name="description" content="{E(book["title_en"])}"><meta name="keywords" content="NIST, AI RMF, هوش مصنوعی, کهن‌دژ">
<meta name="generator" content="kohandezh-pipeline"><meta name="kdj:edition" content="{E(PUB["edition_id"])}"><meta name="kdj:release" content="{E(ver.get("release_id","RC"))}"><meta name="kdj:master-hash" content="{E(book["merkle_root"])}"><meta name="kdj:publisher" content="کهن‌دژ"><meta name="kdj:website" content="{E(PUB["website"])}"><meta name="kdj:manifest" content="{E(ver.get("manifest_sha256","pending"))}">
<style>{font_faces()}{CSS.replace("EDITION", PUB["edition_id"])}</style></head><body>{"".join(parts)}</body></html>"""
    out_html = ROOT / "pdf" / "fa" / "book-fa.print.html"; out_html.write_text(html, encoding="utf-8")
    from weasyprint import HTML
    out = ROOT / "pdf" / "fa" / "book-fa.pdf"
    HTML(string=html, base_url=str(out_html.parent)).write_pdf(str(out), custom_metadata=True)
    info = subprocess.run(["pdfinfo", str(out)], capture_output=True, text=True).stdout
    pages = next((l.split(":")[1].strip() for l in info.splitlines() if l.startswith("Pages:")), "?")
    db.event("pdf_built", {"path": str(out.relative_to(ROOT)), "pages": pages, "bytes": out.stat().st_size})
    print(f"PDF built: {out} pages={pages} bytes={out.stat().st_size}")

if __name__ == "__main__":
    build()
