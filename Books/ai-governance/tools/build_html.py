#!/usr/bin/env python3
"""Phase 19 — Persian HTML Book (MasterPrompt §43, §47–§52, §117). Static, crawlable, no JS required.
Output: html/fa/ai-book/… (one page per chapter) + assets, glossary, sources/source-map, templates, search, and /ai-book/verify/."""
from __future__ import annotations
import csv, html as H, json, shutil, sys
from pathlib import Path
import yaml
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, render  # noqa: E402

PUB = yaml.safe_load(open(ROOT / "config" / "publishing.yaml", encoding="utf-8"))
SRC = {d["doc_key"]: d for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]}
SITE = PUB["website"].rstrip("/"); BASE = "/fa/ai-book"
OUT = ROOT / "html"; FA = OUT / "fa" / "ai-book"; ASSETS = OUT / "assets"
E = H.escape

CSS = """:root{--fg:#1b1f24;--bg:#fff;--muted:#5b6470;--line:#d9dee5;--acc:#0b5d8a;--src:#f6f8fa;--ed:#fff8e6;--ed-b:#e0a800;--loc:#eaf7ee;--loc-b:#2e8b57;--exe:#eef2ff;--exe-b:#4054b2;--tech:#f3eefc;--tech-b:#7b3fbf;--tpl:#fdf0f0;--tpl-b:#c0392b}
@font-face{font-family:"Vazirmatn";src:local("Vazirmatn"),url("/assets/fonts/Vazirmatn[wght].woff2") format("woff2");font-weight:100 900;font-display:swap}
html{font-family:"Vazirmatn","Noto Naskh Arabic","Noto Sans",system-ui,sans-serif;background:var(--bg);color:var(--fg);line-height:1.85;font-size:17px}
:lang(en),.latin,code,.mono,[dir=ltr]{font-family:"Inter","Noto Sans",system-ui,sans-serif}code,.mono,.hash,.cid{font-family:"Noto Sans Mono",ui-monospace,Menlo,monospace;font-size:.85em;direction:ltr;unicode-bidi:isolate;word-break:break-all}
body{margin:0}main{max-width:52rem;margin:0 auto;padding:1rem 1.25rem 4rem}header.site{border-bottom:1px solid var(--line);padding:.6rem 1.25rem;font-size:.95rem}header.site a{color:var(--acc);text-decoration:none}
nav.crumbs{font-size:.9rem;color:var(--muted);margin:.6rem 0}nav.crumbs a{color:var(--acc)}h1{font-size:1.9rem;line-height:1.4;margin:.6rem 0}h2{font-size:1.45rem;margin-top:2.4rem;border-bottom:2px solid var(--line);padding-bottom:.3rem}h3{font-size:1.2rem;margin-top:1.8rem}h4{font-size:1.05rem}
p{margin:.7rem 0;text-align:justify}ul,ol{padding-right:1.6rem;padding-left:0}li{margin:.25rem 0}blockquote{border-right:4px solid var(--line);margin:1rem 0;padding:.2rem 1rem;color:var(--muted)}
.table-wrap{overflow-x:auto;margin:1rem 0}table{border-collapse:collapse;width:100%;font-size:.93rem}th,td{border:1px solid var(--line);padding:.45rem .6rem;vertical-align:top}th{background:var(--src)}
figure.source-figure{margin:1.2rem 0;text-align:center}figure img{max-width:100%;height:auto;border:1px solid var(--line)}figcaption,.caption{font-size:.9rem;color:var(--muted);margin-top:.4rem}
ol.footnotes{font-size:.88rem;color:var(--muted);border-top:1px dashed var(--line);margin-top:1.5rem;padding-top:.6rem}.fn-num{font-weight:700;margin-left:.3rem}
section.unit{margin:1.5rem 0 2.5rem;scroll-margin-top:1rem}.unit-meta{font-size:.8rem;color:var(--muted);display:flex;gap:1rem;flex-wrap:wrap;margin:.2rem 0 .8rem}.unit-meta .cid{background:var(--src);padding:.1rem .4rem;border-radius:4px}
aside.editorial{border:1px solid var(--ed-b);border-right-width:6px;background:var(--ed);padding:.9rem 1.1rem;margin:1.6rem 0;border-radius:6px}aside.editorial>.label{display:inline-block;font-weight:700;font-size:.85rem;background:var(--ed-b);color:#fff;padding:.1rem .6rem;border-radius:4px;margin-bottom:.5rem}
aside.editorial.localization{background:var(--loc);border-color:var(--loc-b)}aside.editorial.localization>.label{background:var(--loc-b)}aside.editorial.executive{background:var(--exe);border-color:var(--exe-b)}aside.editorial.executive>.label{background:var(--exe-b)}
aside.editorial.technical{background:var(--tech);border-color:var(--tech-b)}aside.editorial.technical>.label{background:var(--tech-b)}aside.editorial.template{background:var(--tpl);border-color:var(--tpl-b)}aside.editorial.template>.label{background:var(--tpl-b)}
aside.source-box{background:var(--src);border:1px solid var(--line);border-radius:6px;padding:.8rem 1rem;margin:2rem 0;font-size:.9rem}aside.source-box h3{margin:.2rem 0 .6rem;font-size:1rem}aside.source-box dl{display:grid;grid-template-columns:max-content 1fr;gap:.2rem .8rem;margin:0}aside.source-box dt{color:var(--muted)}aside.source-box dd{margin:0}
nav.pn{display:flex;justify-content:space-between;gap:1rem;margin:3rem 0 1rem;border-top:1px solid var(--line);padding-top:1rem}nav.pn a{color:var(--acc);text-decoration:none;max-width:48%}footer.site{border-top:1px solid var(--line);color:var(--muted);font-size:.85rem;padding:1.2rem;text-align:center}
.toc ul{list-style:none;padding:0}.toc li{margin:.35rem 0}.toc .part{font-weight:700;margin-top:1rem}.toc a{color:var(--acc);text-decoration:none}.badge{font-size:.72rem;border:1px solid var(--line);border-radius:3px;padding:0 .35rem;color:var(--muted);margin-right:.3rem}
.attrib{color:var(--muted);font-size:.95rem}dfn{font-style:normal;font-weight:700}img.art{width:100%;max-width:52rem;height:auto;border-radius:8px;margin:.5rem 0 1rem}img.cover-art{width:min(100%,22rem);height:auto;display:block;margin:1rem auto;border-radius:8px}
@media print{header.site,nav.pn,footer.site,nav.crumbs{display:none}}"""

def page(title: str, desc: str, canonical: str, body: str, jsonld: dict | list, crumbs: list[tuple[str, str]] | None = None, extra_head: str = "") -> str:
    crumb_html = ""
    if crumbs:
        crumb_html = '<nav class="crumbs" aria-label="مسیر"><ol style="list-style:none;padding:0;display:flex;gap:.4rem;flex-wrap:wrap">' + "".join(f'<li><a href="{E(u)}">{E(t)}</a> ›</li>' for t, u in crumbs) + "</ol></nav>"
        bl = {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [{"@type": "ListItem", "position": i + 1, "name": t, "item": SITE + u} for i, (t, u) in enumerate(crumbs)]}
        jsonld = [jsonld, bl] if isinstance(jsonld, dict) else jsonld + [bl]
    return f"""<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{E(title)}</title>
<meta name="description" content="{E(desc[:300])}">
<link rel="canonical" href="{E(SITE + canonical)}">
<link rel="alternate" hreflang="fa" href="{E(SITE + canonical)}"><link rel="alternate" hreflang="x-default" href="{E(SITE + canonical)}">
<meta property="og:type" content="article"><meta property="og:title" content="{E(title)}"><meta property="og:description" content="{E(desc[:200])}"><meta property="og:url" content="{E(SITE + canonical)}"><meta property="og:locale" content="fa_IR"><meta property="og:site_name" content="کهن‌دژ">
<meta name="author" content="{E(PUB['compiler'])}"><meta name="publisher" content="کهن‌دژ"><meta name="kdj:edition" content="{E(PUB['edition_id'])}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&family=Inter:wght@400;600&family=Noto+Sans+Mono&display=swap">
<link rel="stylesheet" href="/assets/kdj.css">{extra_head}
<script type="application/ld+json">{json.dumps(jsonld, ensure_ascii=False)}</script>
</head>
<body>
<header class="site"><a href="{BASE}/">{E(PUB['book_title_fa'])}</a> · <a href="{BASE}/glossary/">واژه‌نامه</a> · <a href="{BASE}/sources/">منابع و نقشهٔ منابع</a> · <a href="{BASE}/templates/">نمونه فرم‌ها</a> · <a href="{BASE}/search/">جست‌وجو</a> · <a href="/ai-book/verify/">اعتبارسنجی نسخه</a></header>
<main>{crumb_html}{body}</main>
<footer class="site">ترجمه، تدوین و بازآرایی فارسی: {E(PUB['compiler'])} — بر پایه اسناد و انتشارات اصلی NIST و منابع مشخص‌شده در هر فصل — <a href="{SITE}">kohandezh.com</a> · نسخهٔ {E(PUB['edition_id'])} · این نسخه ترجمهٔ رسمی NIST نیست و NIST آن را تأیید نکرده است.</footer>
</body></html>"""

def source_box(dk: str, pages: list[int], sections_used: list[str], cid: str) -> str:
    d = SRC.get(dk, {})
    pg = f"{min(pages)}–{max(pages)}" if pages else "—"
    rows = [("منبع اصلی", d.get("title", dk)), ("شناسه سند", d.get("series_identifier", "")), ("عنوان اصلی", (d.get("title", "") + (" — " + d["subtitle"] if d.get("subtitle") else ""))),
            ("نویسندگان", "، ".join(d.get("authors") or []) or d.get("organization", "")), ("نوع انتشار", d.get("publication_type", "")), ("وضعیت انتشار", d.get("publication_status", "")),
            ("تاریخ", d.get("publication_date", "")), ("بخش‌های استفاده‌شده", "، ".join(sections_used[:12]) + ("…" if len(sections_used) > 12 else "")), ("صفحات", pg),
            ("DOI", f'<a class="mono" href="{E(d.get("doi_url",""))}">{E(d.get("doi",""))}</a>' if d.get("doi") else "—"), ("شناسه محتوای کهن‌دژ", f'<span class="cid">{E(cid)}</span>')]
    return '<aside class="source-box" aria-label="منبع"><h3>منبع اصلی این فصل</h3><dl>' + "".join(f"<dt>{E(k)}</dt><dd>{v if k in ('DOI','شناسه محتوای کهن‌دژ') else E(str(v))}</dd>" for k, v in rows) + "</dl></aside>"

def unit_html(S: dict, asset_dir: Path) -> str:
    inner = render.md_to_html(S["fa_text"], asset_dir=asset_dir, asset_url_prefix="/assets/figures", heading_offset=2)
    cid, h = S["content_id"], S["content_hash"]
    smap = ";".join(f"{dk}:p{min(S['source_pages'])}-{max(S['source_pages'])}" if S["source_pages"] else dk for dk in S["source_documents"])
    meta = f'<div class="unit-meta"><span class="cid" title="شناسه محتوا">{E(cid)}</span><span>شناسهٔ استناد: <span class="mono">{E(S["structural_id"])}</span></span></div>'
    head = f'<h2 id="{E(S["structural_id"])}">{E(S["title_fa"])}</h2>'
    if S["origin"] == "source_translation":
        body = f"{head}{meta}{inner}"
    else:
        cls = {"editorial_localization_ir": "localization", "editorial_template_ir": "template"}.get(S["origin"], "")
        lbl = S.get("label_fa") or render.LABELS.get(S["origin"], "یادداشت تدوینگر")
        if lbl in ("برای مدیران",): cls = "executive"
        if lbl in ("برای تیم فنی",): cls = "technical"
        body = f'{head}{meta}<aside class="editorial {cls}" data-origin="{E(S["origin"])}"><span class="label">{E(lbl)}</span>{inner}<p class="attrib">این بخش محتوای تدوینگر است و متن NIST نیست. مبتنی بر: {E(", ".join(S.get("derived_from_content_ids", [])[:6]))}</p></aside>'
    return f'<!-- KDJ-PROVENANCE:{cid} -->\n<section class="unit" data-kdj-id="{E(cid)}" data-kdj-edition="{E(PUB["edition_id"])}" data-kdj-content-hash="{E(h)}" data-kdj-source-map="{E(smap)}" data-kdj-origin="{E(S["origin"])}">{body}</section>'

def build():
    book = json.load(open(ROOT / "master" / "book.json", encoding="utf-8"))
    if FA.exists(): shutil.rmtree(FA)
    FA.mkdir(parents=True); ASSETS.mkdir(parents=True, exist_ok=True); (ASSETS / "kdj.css").write_text(CSS, encoding="utf-8")
    figs = ASSETS / "figures"
    gm = ROOT / "images" / "manifests" / "generated-images.json"
    art = ASSETS / "art"; art.mkdir(exist_ok=True); IMG = {}
    for i in (json.load(open(gm, encoding="utf-8")) if gm.exists() else []):
        src = ROOT / i["file"]
        if src.exists():   # web-optimised JPEG (≤1600px wide); the PNG originals stay in images/ for print
            from PIL import Image
            im = Image.open(src).convert("RGB"); w, h = im.size
            if w > 1600: im = im.resize((1600, int(h * 1600 / w)), Image.LANCZOS)
            im.save(art / (i["image_id"] + ".jpg"), "JPEG", quality=82, optimize=True, progressive=True)
            IMG[i["image_id"]] = (f"/assets/art/{i['image_id']}.jpg", i.get("alt_fa", ""))
    def art_tag(iid, cls="art"):
        return f'<img class="{cls}" src="{IMG[iid][0]}" alt="{E(IMG[iid][1])}" loading="lazy">' if iid in IMG else ""
    urls = []
    flat = [(P, C) for P in book["parts"] for C in P["chapters"] if C["sections"]]
    # book index
    toc = ['<div class="toc"><ul>']
    for P in book["parts"]:
        toc.append(f'<li class="part"><a href="{BASE}/{P["slug"]}/">{E(P["part_id"])} — {E(P["title_fa"])}</a><ul>')
        for C in P["chapters"]:
            if not C["sections"]: continue
            badge = "" if C["kind"] == "source" else f'<span class="badge">{E(render.LABELS.get(C["origin"], "تدوینگر"))}</span>'
            toc.append(f'<li>{badge}<a href="{BASE}/{P["slug"]}/{C["slug"]}/">{E(C["title_fa"])}</a></li>')
        toc.append("</ul></li>")
    toc.append("</ul></div>")
    intro = (art_tag("IMG-COVER", "cover-art") + f'<h1>{E(book["title_fa"])}</h1><p class="attrib">{E(book["attribution"])}</p><p>این کتاب مرجعِ یکپارچه، ترجمهٔ وفادار و بازآرایی‌شدهٔ ۱۸ سند NIST دربارهٔ حاکمیت، ریسک، امنیت، اعتماد، ارزیابی و حقوق هوش مصنوعی است. سه لایهٔ دانش در سراسر کتاب از هم متمایز شده‌اند: '
             f'<strong>ترجمهٔ متن اصلی</strong>، <strong>یادداشت‌های تدوینگر</strong> (جمع‌بندی و ارتباط اسناد) و <strong>لایهٔ اجرایی برای سازمان‌های ایرانی</strong>. هر بخش شناسهٔ استناد پایدار و اثر انگشت رمزنگاری‌شده دارد؛ اصالت این نسخه را در <a href="/ai-book/verify/">صفحهٔ اعتبارسنجی</a> بررسی کنید.</p>')
    ld = {"@context": "https://schema.org", "@type": "Book", "name": book["title_fa"], "inLanguage": "fa-IR", "author": {"@type": "Person", "name": PUB["compiler"]}, "publisher": {"@type": "Organization", "name": "کهن‌دژ", "url": SITE},
          "bookEdition": book["edition_id"], "url": SITE + BASE + "/", "isBasedOn": [d.get("doi_url") or d.get("title") for d in SRC.values()], "dateModified": book["built_at"][:10]}
    (FA / "index.html").write_text(page(book["title_fa"], "ترجمه و تدوین فارسی ۱۸ سند NIST دربارهٔ حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی", BASE + "/", intro + "".join(toc), ld), encoding="utf-8"); urls.append(BASE + "/")
    for pi, P in enumerate(book["parts"]):
        pdir = FA / P["slug"]; pdir.mkdir(exist_ok=True)
        items = "".join(f'<li><a href="{BASE}/{P["slug"]}/{C["slug"]}/">{E(C["title_fa"])}</a> {"" if C["kind"]=="source" else "<span class=badge>"+E(render.LABELS.get(C["origin"],"تدوینگر"))+"</span>"}</li>' for C in P["chapters"] if C["sections"])
        body = art_tag("IMG-" + P["part_id"]) + f'<h1>{E(P["part_id"])} — {E(P["title_fa"])}</h1><p>منابع اصلی این بخش: {E("، ".join(SRC[d].get("series_identifier", d) for d in P["sources"]))}</p><div class="toc"><ul>{items}</ul></div>'
        (pdir / "index.html").write_text(page(f'{P["title_fa"]} — {book["title_fa"]}', f"بخش {P['part_id']}: {P['title_fa']}", f"{BASE}/{P['slug']}/", body,
                                              {"@context": "https://schema.org", "@type": "CreativeWork", "name": P["title_fa"], "isPartOf": {"@type": "Book", "name": book["title_fa"]}, "inLanguage": "fa-IR"}, [("کتاب", BASE + "/")]), encoding="utf-8")
        urls.append(f"{BASE}/{P['slug']}/")
    for i, (P, C) in enumerate(flat):
        cdir = FA / P["slug"] / C["slug"]; cdir.mkdir(parents=True, exist_ok=True)
        url = f"{BASE}/{P['slug']}/{C['slug']}/"
        units = "".join(unit_html(S, figs) for S in C["sections"])
        sbox = ""
        if C["kind"] == "source":
            pages = sorted({p for S in C["sections"] for p in S["source_pages"]}); secs = [S["title_en"] for S in C["sections"]]
            sbox = source_box(C["source_doc"], pages, secs, C["sections"][0]["structural_id"].rsplit("-S", 1)[0])
        prev = flat[i - 1] if i > 0 else None; nxt = flat[i + 1] if i + 1 < len(flat) else None
        pn = '<nav class="pn">' + (f'<a href="{BASE}/{prev[0]["slug"]}/{prev[1]["slug"]}/">‹ {E(prev[1]["title_fa"])}</a>' if prev else "<span></span>") + (f'<a href="{BASE}/{nxt[0]["slug"]}/{nxt[1]["slug"]}/">{E(nxt[1]["title_fa"])} ›</a>' if nxt else "<span></span>") + "</nav>"
        label = "" if C["kind"] == "source" else f'<p class="attrib"><span class="badge">{E(render.LABELS.get(C["origin"], "تدوینگر"))}</span> این فصل محتوای تدوینگر است و متن NIST نیست.</p>'
        body = f'<article data-kdj-chapter="{E(C["chapter_id"])}"><header><h1>{E(C["title_fa"])}</h1>{label}<p class="attrib">{E(P["part_id"])} — {E(P["title_fa"])}{(" · " + E(C["title_en"])) if C.get("title_en") else ""}</p></header>{sbox}{units}{sbox and ""}</article>{pn}'
        desc = " ".join(C["sections"][0]["fa_text"].split()[:40])
        d = SRC.get(C.get("source_doc") or "", {})
        ld = {"@context": "https://schema.org", "@type": "Chapter" if C["kind"] == "source" else "TechArticle", "name": C["title_fa"], "headline": C["title_fa"], "inLanguage": "fa-IR", "url": SITE + url,
              "isPartOf": {"@type": "Book", "name": book["title_fa"], "bookEdition": book["edition_id"]}, "author": {"@type": "Person", "name": PUB["compiler"]}, "publisher": {"@type": "Organization", "name": "کهن‌دژ"},
              "datePublished": book["built_at"][:10], "dateModified": book["built_at"][:10], "identifier": C["sections"][0]["content_id"],
              **({"isBasedOn": {"@type": "CreativeWork", "name": d.get("title"), "identifier": d.get("doi_url") or d.get("series_identifier"), "publisher": {"@type": "Organization", "name": d.get("organization") or "NIST"}, "datePublished": d.get("publication_date_iso") or d.get("publication_date")}} if d else {})}
        (cdir / "index.html").write_text(page(f'{C["title_fa"]} — {book["title_fa"]}', desc, url, body, ld, [("کتاب", BASE + "/"), (P["title_fa"], f"{BASE}/{P['slug']}/")]), encoding="utf-8"); urls.append(url)
    # glossary
    gp = ROOT / "translation_memory" / "glossary.json"
    if gp.exists():
        terms = sorted(json.load(open(gp, encoding="utf-8"))["terms"].values(), key=lambda t: (t.get("preferred_fa") or ""))
        rows = "".join(f'<tr><td><dfn>{E(t.get("preferred_fa") or "")}</dfn></td><td class="latin">{E(t["english"])}{(" (" + E(t["acronym"]) + ")") if t.get("acronym") else ""}</td><td>{E(t.get("definition_fa") or "")}</td><td><span class="badge">{E(t["status"])}</span></td></tr>' for t in terms if t.get("preferred_fa"))
        (FA / "glossary").mkdir(exist_ok=True)
        (FA / "glossary" / "index.html").write_text(page("واژه‌نامهٔ دوزبانه", "واژه‌نامهٔ فارسی–انگلیسی اصطلاحات هوش مصنوعی بر پایهٔ اسناد NIST", BASE + "/glossary/", f'<h1>واژه‌نامه</h1><div class="table-wrap"><table><thead><tr><th>فارسی</th><th>English</th><th>تعریف</th><th>وضعیت</th></tr></thead><tbody>{rows}</tbody></table></div>',
                                                          {"@context": "https://schema.org", "@type": "DefinedTermSet", "name": "واژه‌نامهٔ کهن‌دژ", "inLanguage": "fa-IR"}, [("کتاب", BASE + "/")]), encoding="utf-8"); urls.append(BASE + "/glossary/")
    # sources + source map
    smap = json.load(open(ROOT / "master" / "source_map.json", encoding="utf-8")) if (ROOT / "master" / "source_map.json").exists() else []
    bib = "".join(f'<li><span class="latin"><strong>{E(d.get("series_identifier",""))}</strong> — {E(d.get("title",""))}{(" — " + E(d["subtitle"])) if d.get("subtitle") else ""}. {E(", ".join(d.get("authors") or []) or d.get("organization",""))}. {E(d.get("publication_type",""))}, {E(d.get("publication_status",""))}, {E(d.get("publication_date",""))}.</span> {("<a class=mono href=" + E(d.get("doi_url") or ("https://doi.org/" + d["doi"])) + ">" + E(d["doi"]) + "</a>") if d.get("doi") else ""} <span class="hash">sha256:{E(d["sha256"][:16])}…</span></li>' for d in SRC.values())
    mrows = "".join(f'<tr><td class="cid">{E(r["structural_id"])}</td><td>{E(r["source_series"])}</td><td class="latin">{E(r["source_section"] or "")}</td><td>{E(("، ".join(map(str, r["source_pages"][:1])) + "–" + str(r["source_pages"][-1])) if r["source_pages"] else "—")}</td><td>{E(render.LABELS.get(r["origin"], r["origin"]))}</td></tr>' for r in smap)
    (FA / "sources").mkdir(exist_ok=True)
    (FA / "sources" / "index.html").write_text(page("منابع و نقشهٔ منابع", "کتاب‌شناسی ۱۸ سند NIST و نگاشت هر بخش کتاب به سند، بخش و صفحات منبع", BASE + "/sources/", f'<h1>منابع</h1><ol>{bib}</ol><h2>نقشهٔ منابع</h2><div class="table-wrap"><table><thead><tr><th>شناسهٔ بخش</th><th>سند</th><th>بخش منبع</th><th>صفحات</th><th>لایه</th></tr></thead><tbody>{mrows}</tbody></table></div>',
                                                     {"@context": "https://schema.org", "@type": "WebPage", "name": "منابع"}, [("کتاب", BASE + "/")]), encoding="utf-8"); urls.append(BASE + "/sources/")
    # templates
    tdir = ROOT / "templates"; (FA / "templates").mkdir(exist_ok=True)
    tl = []
    for tp in sorted(tdir.glob("*.json")):
        t = json.load(open(tp, encoding="utf-8"))
        shutil.copy2(tp, FA / "templates" / tp.name)
        with open(FA / "templates" / (tp.stem + ".csv"), "w", newline="", encoding="utf-8") as f:
            w = csv.writer(f); w.writerow(["field_fa", "field_en", "description_fa", "example_fa"]); [w.writerow([x.get("name_fa"), x.get("name_en"), x.get("description_fa"), x.get("example_fa")]) for x in t.get("fields", [])]
        rows = "".join(f'<tr><td>{E(x.get("name_fa",""))}</td><td class="latin">{E(x.get("name_en",""))}</td><td>{E(x.get("description_fa",""))}</td><td>{E(x.get("example_fa",""))}</td></tr>' for x in t.get("fields", []))
        tl.append(f'<aside class="editorial template"><span class="label">نمونه فرم</span><h2>{E(t.get("title_fa",""))}</h2><p class="attrib">{E(t.get("note_fa",""))} — مبتنی بر: {E(", ".join(t.get("derived_from", [])))}</p><div class="table-wrap"><table><thead><tr><th>فیلد</th><th>English</th><th>توضیح</th><th>نمونه</th></tr></thead><tbody>{rows}</tbody></table></div><p><a href="{E(tp.name)}">JSON</a> · <a href="{E(tp.stem)}.csv">CSV</a></p></aside>')
    (FA / "templates" / "index.html").write_text(page("نمونه فرم‌ها و قالب‌های اجرایی", "قالب‌های ویرایش‌پذیر تدوینگر برای پیاده‌سازی حاکمیت و امنیت هوش مصنوعی", BASE + "/templates/", "<h1>نمونه فرم‌ها</h1><p class='attrib'>این قالب‌ها نمونهٔ پیشنهادی تدوینگر هستند و فرم رسمی NIST نیستند.</p>" + "".join(tl), {"@context": "https://schema.org", "@type": "WebPage", "name": "نمونه فرم‌ها"}, [("کتاب", BASE + "/")]), encoding="utf-8"); urls.append(BASE + "/templates/")
    # search (no-JS list + progressive JS)
    idx = json.load(open(ROOT / "html" / "search" / "index.json", encoding="utf-8")) if (ROOT / "html" / "search" / "index.json").exists() else {"entries": []}
    (FA / "search").mkdir(exist_ok=True); shutil.copy2(ROOT / "html" / "search" / "index.json", FA / "search" / "index.json") if (ROOT / "html" / "search" / "index.json").exists() else None
    lst = "".join(f'<li data-k="{E(" ".join(e["keywords_fa"] + e["keywords_en"] + e["acronyms"]).lower())}"><a href="{E(e["url"].replace(SITE, ""))}">{E(e["title_fa"])}</a> <span class="badge">{E(e["chapter"])}</span></li>' for e in idx["entries"])
    js = "<script>document.addEventListener('DOMContentLoaded',()=>{const q=document.getElementById('q'),ul=document.getElementById('res');if(!q)return;q.addEventListener('input',()=>{const v=q.value.trim().toLowerCase();for(const li of ul.children){li.style.display=(!v||li.textContent.toLowerCase().includes(v)||(li.dataset.k||'').includes(v))?'':'none'}})});</script>"
    (FA / "search" / "index.html").write_text(page("جست‌وجو در کتاب", "جست‌وجوی بخش‌های کتاب بر اساس عنوان، مفاهیم، اختصارات و شناسهٔ سند", BASE + "/search/", f'<h1>جست‌وجو</h1><input id="q" type="search" placeholder="عبارت، مفهوم یا اختصار…" style="width:100%;font:inherit;padding:.5rem;border:1px solid var(--line);border-radius:6px"><ul id="res" class="toc">{lst}</ul>{js}', {"@context": "https://schema.org", "@type": "WebPage", "name": "جست‌وجو"}, [("کتاب", BASE + "/")]), encoding="utf-8")
    # verify page
    vp = ROOT / "provenance" / "verification.json"
    v = json.load(open(vp, encoding="utf-8")) if vp.exists() else {}
    vdir = OUT / "ai-book" / "verify"; vdir.mkdir(parents=True, exist_ok=True)
    arts = "".join(f'<tr><td class="mono">{E(k)}</td><td class="hash">{E(a["sha256"])}</td></tr>' for k, a in (v.get("artifact_hashes") or {}).items())
    vbody = (f'<h1>اعتبارسنجی نسخهٔ منتشرشده</h1><p>با اطلاعات زیر می‌توانید اصالت هر نسخه از این کتاب را به‌صورت مستقل بررسی کنید.</p><dl class="table-wrap"><dt>شناسهٔ ویرایش</dt><dd class="mono">{E(v.get("edition_id", PUB["edition_id"]))}</dd><dt>شناسهٔ انتشار</dt><dd class="mono">{E(v.get("release_id", "—"))}</dd><dt>تاریخ انتشار</dt><dd class="mono">{E(v.get("publication_date", "—"))}</dd>'
             f'<dt>ریشهٔ مرکل</dt><dd class="hash">{E(v.get("merkle_root", book.get("merkle_root", "")))}</dd><dt>کلید عمومی Ed25519</dt><dd class="hash">{E(v.get("public_key_ed25519_hex", "—"))}</dd><dt>وضعیت امضا</dt><dd>{E(v.get("signing_status", "PUBLISHER_SIGNING_KEY_REQUIRED"))}</dd><dt>امضای مانیفست</dt><dd class="hash">{E(v.get("manifest_signature_hex", "—"))}</dd></dl>'
             f'<h2>هش آرتیفکت‌ها</h2><div class="table-wrap"><table><thead><tr><th>فایل</th><th>SHA-256</th></tr></thead><tbody>{arts}</tbody></table></div><h2>روش بررسی</h2><ol>' + "".join(f"<li class='latin' dir='ltr' style='text-align:left'>{E(s)}</li>" for s in v.get("verify_instructions", [])) + '</ol><p>فایل‌های <a href="release-manifest.json">release-manifest.json</a>، <a href="release-manifest.sig">release-manifest.sig</a>، <a href="public-key.pem">public-key.pem</a> و <a href="verify_release.py">verify_release.py</a>.</p>')
    for fn in ("release-manifest.json", "release-manifest.sig", "public-key.pem"):
        if (ROOT / "provenance" / fn).exists(): shutil.copy2(ROOT / "provenance" / fn, vdir / fn)
    shutil.copy2(ROOT / "tools" / "verify_release.py", vdir / "verify_release.py")
    (vdir / "index.html").write_text(page("اعتبارسنجی نسخه", "اطلاعات رمزنگاری برای بررسی اصالت نسخهٔ منتشرشدهٔ کتاب", "/ai-book/verify/", vbody, {"@context": "https://schema.org", "@type": "WebPage", "name": "اعتبارسنجی"}), encoding="utf-8")
    (OUT / "sitemap.xml").write_text('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' + "".join(f"<url><loc>{E(SITE + u)}</loc><lastmod>{book['built_at'][:10]}</lastmod></url>" for u in urls + ["/ai-book/verify/"]) + "</urlset>", encoding="utf-8")
    (OUT / "robots.txt").write_text(f"User-agent: *\nAllow: /\nSitemap: {SITE}/sitemap.xml\n", encoding="utf-8")
    db.event("html_built", {"pages": len(urls) + 2})
    print(f"HTML book: {len(urls)} crawlable pages + verify/search; assets in html/assets")

if __name__ == "__main__":
    build()
