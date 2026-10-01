#!/usr/bin/env python3
"""Phases 14–16, 27–28 — canonical Persian master, Content IDs, citation registry, source map, hashes, Merkle tree.

Inputs : taxonomy/book_structure.yaml, sources/extracted/*.blocks.jsonl, work/chunks/*, approved translations (state/project.db),
         work/editorial/<part_id>/<chapter_id>.json (editorial + localization units, if present)
Outputs: master/book.json, master/book.md, master/bibliography.json, master/source_map.json(.csv), master/citation_map.json,
         provenance/content_ids.json, provenance/hashes.json, provenance/citation-registry.json, provenance/merkle-tree.json, provenance/merkle-root.txt
Hash normalization spec: config/provenance.yaml (kdj-hash-1). Never change silently.
"""
from __future__ import annotations
import collections, csv, hashlib, json, re, sys, unicodedata
from pathlib import Path
import yaml

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402
from iran_dates import localize_persian_dates  # noqa: E402

PROV = yaml.safe_load(open(ROOT / "config" / "provenance.yaml", encoding="utf-8"))
PUB = yaml.safe_load(open(ROOT / "config" / "publishing.yaml", encoding="utf-8"))
STRUCT = yaml.safe_load(open(ROOT / "taxonomy" / "book_structure.yaml", encoding="utf-8"))
SRC = {d["doc_key"]: d for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]}
EDITION = PROV["edition_id"]
_DT = ROOT / "taxonomy" / "doc_titles_fa.json"
DOC_TITLES = json.load(open(_DT, encoding="utf-8")) if _DT.exists() else {}
ZW_REMOVE = re.compile("[​‍﻿⁠‎‏‪-‮⁦-⁩]")

# --------------------------------------------------------------------------- canonical text & hashing
def canonical_text(s: str) -> str:
    s = unicodedata.normalize("NFC", s or "")
    s = s.replace("\r\n", "\n").replace("\r", "\n")
    s = ZW_REMOVE.sub("", s)
    lines = [re.sub(r"[ \t ]+", " ", l).rstrip() for l in s.split("\n")]
    s = "\n".join(lines)
    s = re.sub(r"\n{3,}", "\n\n", s).strip("\n")
    return s

def content_hash(structural_id: str, locale: str, origin: str, title_fa: str, title_en: str, text: str) -> str:
    payload = "\n".join([structural_id, locale, origin, canonical_text(title_fa), canonical_text(title_en), canonical_text(text)])
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()

def merkle(hashes_hex: list[str]) -> tuple[str, list[list[str]]]:
    if not hashes_hex:
        return hashlib.sha256(b"\x00").hexdigest(), [[]]
    level = [hashlib.sha256(b"\x00" + bytes.fromhex(h)).hexdigest() for h in hashes_hex]
    levels = [level]
    while len(level) > 1:
        nxt = []
        for i in range(0, len(level), 2):
            if i + 1 < len(level):
                nxt.append(hashlib.sha256(b"\x01" + bytes.fromhex(level[i]) + bytes.fromhex(level[i + 1])).hexdigest())
            else:
                nxt.append(level[i])  # promote unpaired node unchanged
        level = nxt; levels.append(level)
    return level[0], levels

# --------------------------------------------------------------------------- data access
def load_blocks(doc_key: str) -> list[dict]:
    p = ROOT / "sources" / "extracted" / f"{doc_key}.blocks.jsonl"
    return [json.loads(l) for l in open(p, encoding="utf-8")] if p.exists() else []

def approved_chunks(con, doc_key: str) -> list[dict]:
    rows = con.execute("SELECT a.chunk_id, a.fa_text, a.sha256, a.translator_model, a.reviewer_model, a.escalated, c.block_ids, c.order_no, c.heading, c.section_path FROM approved a JOIN chunks c ON c.chunk_id=a.chunk_id WHERE c.doc_key=? ORDER BY c.order_no", (doc_key,)).fetchall()
    return [dict(r) for r in rows]

def fa_heading_from_chunk(fa_text: str) -> str:
    for line in fa_text.splitlines():
        if line.startswith("#"):
            return line.lstrip("#").strip()
    return ""

def strip_leading_heading(fa_text: str) -> str:
    lines = fa_text.splitlines()
    if lines and lines[0].startswith("#"):
        return "\n".join(lines[1:]).strip()
    return fa_text

# --------------------------------------------------------------------------- chunk → section assignment (one approved chunk → exactly one unit)
# RC1 (2026-09-18) duplicated whole documents 2–50× because stale yaml anchors defaulted to block 0 and split chapters
# (source_section_root) were never bounded. The assembly below resolves anchors robustly, bounds chapters by the next
# chapter's start, records excluded chunks, and refuses to build when a chunk is missing or duplicated.
TOC_HEADINGS = {"table of contents", "contents", "list of tables", "list of figures", "table of contents (continued)"}
FRONT_MATTER = {"title_en": "Front matter", "title_fa": "مطالب آغازین سند", "section_path": "FM"}
ARTIFACT_LINE = re.compile(r"^\s*[-*•–—]\s*[-–—]?\s*$")   # bare list markers left behind by PDF extraction
_GP = ROOT / "translation_memory" / "glossary.json"
try:
    GLOSS_FA = {t["english"].strip().lower(): t["preferred_fa"] for t in json.load(open(_GP, encoding="utf-8"))["terms"].values() if t.get("english") and t.get("preferred_fa")}
except Exception:
    GLOSS_FA = {}

def _norm_title(s: str) -> str:
    s = re.sub(r"^\s*(?:\d{1,3}\s+)+(?=\S)", "", s or "")        # margin line numbers glued to headings by extraction ("1 2. Harms…")
    s = re.sub(r"[^\w\s]", " ", s.lower())
    return re.sub(r"\s+", " ", s).strip()

def _title_match(a: str, b: str) -> bool:
    na, nb = _norm_title(a), _norm_title(b)
    if not na or not nb:
        return False
    if na == nb:
        return True
    return min(len(na), len(nb)) >= 12 and (na.startswith(nb[:40]) or nb.startswith(na[:40]))

def _persian(s: str) -> bool:
    return bool(re.search(r"[؀-ۿ]", s or ""))

def clean_artifacts(fa: str) -> str:
    return "\n".join(l for l in fa.splitlines() if not ARTIFACT_LINE.match(l))

def fa_headings(fa: str) -> list[str]:
    return [l.lstrip("#").strip() for l in fa.splitlines() if l.lstrip().startswith("#")]

def resolve_anchor(sec: dict, blocks: list[dict], order: dict) -> tuple[str | None, str]:
    """(block_id, how) for a yaml section anchor: exact block_id → heading whose text matches title_en → non-numeric section_path."""
    bid = sec.get("block_id")
    if bid in order:
        return bid, "block_id"
    for b in blocks:
        if b["block_type"] == "heading" and _title_match(b["text"], sec.get("title_en", "")):
            return b["block_id"], "title"
    sp = sec.get("section_path")
    if sp and not re.fullmatch(r"\d{1,3}", sp):
        for b in blocks:
            if b["section_path"] == sp and b["block_type"] == "heading":
                return b["block_id"], "section_path"
    return None, "unresolved"

def aligned_title(block_id: str, chunk: dict, bmap: dict) -> str:
    """Persian heading for an EN heading block = the k-th '#' line of the chunk holding it (structural_check enforces equal heading counts)."""
    heads = [b for b in chunk["_bids"] if b in bmap and bmap[b]["block_type"] == "heading"]
    fh = fa_headings(chunk["fa_text"])
    if block_id in heads and len(heads) == len(fh):
        return fh[heads.index(block_id)]
    if heads and block_id == heads[0] and fh:
        return fh[0]
    return ""

def title_fallback(title_en: str, dk: str) -> str:
    return GLOSS_FA.get((title_en or "").strip().lower()) or title_en or DOC_TITLES.get(dk, {}).get("title_fa", "")

def is_toc_chunk(c: dict, bmap: dict) -> bool:
    h = (c["heading"] or "").strip().lower()
    if not (h in TOC_HEADINGS or h.startswith("table of contents")):
        return False
    return "paragraph" not in {bmap[b]["block_type"] for b in c["_bids"] if b in bmap}

def _section(dk: str, title_fa: str, title_en: str, section_path: str | None, members: list[dict], bmap: dict, chapter_title: str) -> dict:
    fa_parts, src_blocks, src_pages, models = [], [], set(), set()
    for c in members:
        fa_parts.append(clean_artifacts(c["fa_text"]).strip()); src_blocks += c["_bids"]
        for b in c["_bids"]:
            if b in bmap:
                src_pages.update(range(bmap[b]["page_start"], bmap[b]["page_end"] + 1))
        models.add(c["translator_model"] or "")
    text = "\n\n".join(p for p in fa_parts if p)
    first = next((l for l in text.splitlines() if l.strip()), "")
    if first.lstrip().startswith("#") and first.lstrip("#").strip() in (title_fa, chapter_title):   # rendered as the unit/chapter title
        text = text.replace(first, "", 1).lstrip("\n")
    return {"title_fa": title_fa, "title_en": title_en, "origin": "source_translation", "fa_text": text, "source_documents": [dk], "source_blocks": src_blocks,
            "source_pages": sorted(src_pages), "source_section": section_path, "chunk_ids": [c["chunk_id"] for c in members], "translator_models": sorted(models),
            "escalated": any(c["escalated"] for c in members), "_chapter_title": chapter_title}

def assemble_doc(con, dk: str, doc_chapters: list[dict], log: list[str]) -> dict:
    """Assign every approved chunk of `dk` to exactly one section across the chapters that draw on it.
    Chapter spans come from `source_section_root` (heading matching title_en) or the first anchor and end where the next
    chapter starts; sections come from resolved anchors; chunks before the document's first anchor form a 'Front matter'
    section; pure table-of-contents chunks are excluded and recorded."""
    blocks = load_blocks(dk); bmap = {b["block_id"]: b for b in blocks}; order = {b["block_id"]: b["order_no"] for b in blocks}
    chunks = approved_chunks(con, dk); block_chunk = {}
    for c in chunks:
        c["_bids"] = json.loads(c["block_ids"]); c["_first"] = min((order[b] for b in c["_bids"] if b in order), default=10 ** 9)
        for b in c["_bids"]:
            block_chunk[b] = c
    spans = []
    for ch in doc_chapters:
        root = ch.get("source_section_root"); root_block = None
        if root:
            root_block = next((b for b in blocks if b["block_type"] == "heading" and _title_match(b["text"], ch.get("title_en", ""))), None)
            if not root_block:
                cands = [b for b in blocks if b["section_path"] == root or b["section_path"].startswith((root + ".", root + "-"))]
                heads = [b for b in cands if b["block_type"] == "heading"]
                root_block = heads[0] if heads else (cands[0] if cands else None)
            if not root_block:
                log.append(f"{ch['id']}: root section {root!r} not found in {dk}")
        secs = []
        for s in (ch.get("sections") or []):
            a, how = resolve_anchor(s, blocks, order)
            if a:
                secs.append({"start": order[a], "anchor": a, "def": s, "how": how})
            else:
                log.append(f"{ch['id']}: section anchor unresolved: {s.get('title_en')!r}")
        secs.sort(key=lambda x: x["start"])
        spans.append({"ch": ch, "start": root_block["order_no"] if root_block else (secs[0]["start"] if secs else 0), "root_block": root_block, "secs": secs})
    spans.sort(key=lambda x: x["start"])
    for i, sp in enumerate(spans):
        sp["end"] = spans[i + 1]["start"] if i + 1 < len(spans) else 10 ** 9
    first_anchor = min([s["start"] for sp in spans for s in sp["secs"]] + [sp["start"] for sp in spans if sp["root_block"]] + [10 ** 9])
    spans[0]["start"] = 0                                     # the first chapter also carries the document's front matter
    excluded, ordered, by_chapter, chapter_titles = {}, [], {}, {}
    for sp in spans:
        ch = sp["ch"]
        ctitle = ch.get("title_fa") or ""
        if not ctitle and sp["root_block"]:
            rc = block_chunk.get(sp["root_block"]["block_id"])
            ctitle = aligned_title(sp["root_block"]["block_id"], rc, bmap) if rc else ""
            if not _persian(ctitle):
                ctitle = GLOSS_FA.get((ch.get("title_en") or "").strip().lower(), "") or ctitle
        if not ctitle:
            ctitle = DOC_TITLES.get(dk, {}).get("title_fa", "") or ch.get("title_en", "")
        chapter_titles[ch["id"]] = ctitle
        members = [c for c in chunks if sp["start"] <= c["_first"] < sp["end"]]
        secs = sp["secs"] or [{"start": sp["start"], "anchor": sp["root_block"]["block_id"] if sp["root_block"] else None,
                               "def": {"title_en": ch.get("title_en", ""), "section_path": ch.get("source_section_root") or "ALL"}, "how": "chapter"}]
        buckets = {i: [] for i in range(len(secs))}; front = []
        for c in members:
            if sp is spans[0] and c["_first"] < first_anchor:
                if is_toc_chunk(c, bmap):
                    excluded[c["chunk_id"]] = "source table of contents / list of tables or figures (PDF navigation apparatus; page numbers do not apply)"
                else:
                    front.append(c)
                continue
            k = max([i for i, s in enumerate(secs) if s["start"] <= c["_first"]] or [0])
            buckets[k].append(c)
        out = []
        if front:
            out.append(_section(dk, FRONT_MATTER["title_fa"], FRONT_MATTER["title_en"], FRONT_MATTER["section_path"], front, bmap, ctitle))
        for i, s in enumerate(secs):
            if not buckets[i]:
                continue
            a = s["anchor"]
            title = s["def"].get("title_fa") or (aligned_title(a, block_chunk[a], bmap) if a and a in block_chunk else "")
            if not _persian(title):
                title = title if title else title_fallback(s["def"].get("title_en", ""), dk)
            out.append(_section(dk, title, s["def"].get("title_en", ""), s["def"].get("section_path"), buckets[i], bmap, ctitle))
        by_chapter[ch["id"]] = out; ordered += out
    # a chunk that ends with the next section's/chapter's heading (chunker artifact) leaves a dangling heading: drop it
    for i in range(len(ordered) - 1):
        lines = ordered[i]["fa_text"].rstrip().splitlines()
        if lines and lines[-1].lstrip().startswith("#") and lines[-1].lstrip("#").strip() in (ordered[i + 1]["title_fa"], ordered[i + 1]["_chapter_title"]):
            ordered[i]["fa_text"] = "\n".join(lines[:-1]).rstrip()
    for s in ordered:
        s.pop("_chapter_title", None)
    assigned = [cid for s in ordered for cid in s["chunk_ids"]]
    return {"by_chapter": by_chapter, "chapter_titles": chapter_titles, "assigned": assigned, "excluded": excluded, "chunks": [c["chunk_id"] for c in chunks]}

# --------------------------------------------------------------------------- build
def build():
    con = db.connect()
    book = {"edition_id": EDITION, "locale": "fa", "title_fa": PUB["book_title_fa"], "title_en": PUB["book_title_en"], "compiler": PUB["compiler"], "website": PUB["website"],
            "attribution": "ترجمه، تدوین و بازآرایی فارسی: محمدعلی کهن‌دژ — بر پایه اسناد و انتشارات اصلی NIST و منابع مشخص‌شده در هر فصل — وب‌سایت: kohandezh.com",
            "hash_spec": PROV["spec_version"], "built_at": db.now(), "parts": []}
    units, content_ids, citation_registry, source_map = [], {}, {}, []
    md = [f"# {book['title_fa']}", "", book["attribution"], ""]
    # assemble every source document once (one approved chunk → exactly one unit), then refuse to build on any violation
    by_doc: dict[str, list[dict]] = {}
    for part in STRUCT["parts"]:
        for ch in part["chapters"]:
            if ch["kind"] == "source":
                by_doc.setdefault(ch["source_doc"], []).append(ch)
    alog: list[str] = []
    ASSEMBLY = {dk: assemble_doc(con, dk, chs, alog) for dk, chs in by_doc.items()}
    problems = []
    for dk, A in ASSEMBLY.items():
        cnt = collections.Counter(A["assigned"])
        dup = sorted(c for c, n in cnt.items() if n > 1); miss = [c for c in A["chunks"] if c not in cnt and c not in A["excluded"]]
        if dup or miss:
            problems.append(f"{dk}: duplicates={dup[:5]} missing={miss[:5]}")
    for line in alog:
        print("assembly:", line)
    if problems:
        raise SystemExit("master assembly invariant violated (one chunk → one unit): " + "; ".join(problems))
    coverage = {"chunks_expected": sum(len(A["chunks"]) for A in ASSEMBLY.values()), "chunks_in_master": sum(len(A["assigned"]) for A in ASSEMBLY.values()),
                "excluded_chunks": {c: r for A in ASSEMBLY.values() for c, r in A["excluded"].items()}, "assembly_notes": alog}
    for part in STRUCT["parts"]:
        P = {"part_id": part["id"], "slug": part["slug"], "title_fa": part["title_fa"], "title_en": part.get("title_en", ""), "sources": part["sources"], "chapters": []}
        md += [f"\n\n# {part['id']} — {part['title_fa']}", ""]
        for ch in part["chapters"]:
            C = {"chapter_id": ch["id"], "slug": ch["slug"], "origin": ch["origin"], "kind": ch["kind"], "title_fa": ch.get("title_fa") or "", "title_en": ch.get("title_en", ""),
                 "source_doc": ch.get("source_doc"), "sections": []}
            sections = []
            if ch["kind"] == "source":
                dk = ch["source_doc"]
                sections = ASSEMBLY[dk]["by_chapter"].get(ch["id"], [])
                C["title_fa"] = C["title_fa"] or ASSEMBLY[dk]["chapter_titles"].get(ch["id"], "")
            else:
                ep = ROOT / "work" / "editorial" / part["id"] / f"{ch['id']}.json"
                if ep.exists():
                    ed = json.load(open(ep, encoding="utf-8"))
                    C["title_fa"] = ed.get("title_fa") or C["title_fa"]
                    for s in ed.get("sections", []):
                        sections.append({"title_fa": s["title_fa"], "title_en": s.get("title_en", ""), "origin": s.get("origin", ch["origin"]), "fa_text": s["fa_text"], "label_fa": s.get("label_fa", ch.get("label_fa")),
                                         "source_documents": s.get("derived_from_source_ids", part["sources"]), "source_blocks": s.get("source_blocks", []), "source_pages": [],
                                         "derived_from_content_ids": s.get("derived_from_content_ids", []), "template": s.get("template"), "chunk_ids": []})
            if not C["title_fa"] and ch["kind"] == "source":
                C["title_fa"] = DOC_TITLES.get(ch["source_doc"], {}).get("title_fa", "")
            if not C["title_fa"]:
                C["title_fa"] = (sections[0]["title_fa"] if sections and ch["kind"] == "source" and not ch["sections"] else "") or ch.get("label_fa") or ch.get("title_en", "")
            md += [f"\n## {ch['id']} — {C['title_fa']}", ""]
            for si, s in enumerate(sections, 1):
                # Persian publication text keeps its Gregorian source date and adds
                # the exact Iran-calendar equivalent (or a month span when no day
                # is available). This occurs before hashing so every downstream
                # artifact and provenance record represents the localized text.
                s["fa_text"] = localize_persian_dates(s["fa_text"])
                structural = f"KDJ-AI-{EDITION}-{part['id']}-{ch['id']}-S{si:02d}"
                h = content_hash(structural, "fa", s["origin"], s["title_fa"], s["title_en"], s["fa_text"])
                cid = f"{structural}-{h[:8].upper()}"
                unit = {"content_id": cid, "structural_id": structural, "citation_id": structural, "locale": "fa", "concept_ids": [], "part_id": part["id"], "chapter_id": ch["id"], "section_id": f"S{si:02d}",
                        "title_fa": s["title_fa"], "title_en": s["title_en"], "origin": s["origin"], "label_fa": s.get("label_fa"), "fa_text": s["fa_text"], "source_documents": s["source_documents"],
                        "source_blocks": s["source_blocks"], "source_pages": s["source_pages"], "source_section": s.get("source_section"), "derived_from_content_ids": s.get("derived_from_content_ids", []),
                        "references": [], "tables": [], "figures": [], "canonical_url": f"{PUB['website']}/fa/ai-book/{part['slug']}/{ch['slug']}/#{structural}", "content_hash": f"sha256:{h}",
                        "chunk_ids": s.get("chunk_ids", []), "translator_models": s.get("translator_models", []), "escalated": s.get("escalated", False)}
                C["sections"].append(unit); units.append(unit)
                content_ids[cid] = {"structural_id": structural, "part": part["id"], "chapter": ch["id"], "section": f"S{si:02d}", "origin": s["origin"], "hash": h, "locale": "fa"}
                citation_registry[structural] = {"content_id": cid, "title_fa": s["title_fa"], "title_en": s["title_en"], "origin": s["origin"], "canonical_url": unit["canonical_url"],
                                                 "citation_fa": f"محمدعلی کهن‌دژ، {PUB['book_title_fa']}، شناسه بخش: {structural}", "source_documents": s["source_documents"]}
                for dk in s["source_documents"]:
                    source_map.append({"content_id": cid, "structural_id": structural, "origin": s["origin"], "source_doc": dk, "source_series": SRC.get(dk, {}).get("series_identifier", ""),
                                       "source_title": SRC.get(dk, {}).get("title", ""), "source_section": s.get("source_section", ""), "source_pages": s["source_pages"], "source_blocks_n": len(s["source_blocks"])})
                label = f" [{s.get('label_fa')}]" if s.get("label_fa") else ""
                md += [f"\n### {s['title_fa']}{label}", f"<!-- KDJ-PROVENANCE:{cid} origin={s['origin']} -->", "", s["fa_text"], ""]
            P["chapters"].append(C)
        book["parts"].append(P)
    # hashes & merkle
    part_hashes = []; tree = {"spec": PROV["spec_version"], "parts": []}
    for P in book["parts"]:
        ch_hashes = []; tp = {"part_id": P["part_id"], "chapters": []}
        for C in P["chapters"]:
            sec_hashes = [s["content_hash"].split(":")[1] for s in C["sections"]]
            root, _ = merkle(sec_hashes)
            C["chapter_hash"] = root; ch_hashes.append(root)
            tp["chapters"].append({"chapter_id": C["chapter_id"], "chapter_hash": root, "sections": [{"content_id": s["content_id"], "hash": s["content_hash"]} for s in C["sections"]]})
        proot, _ = merkle(ch_hashes); P["part_hash"] = proot; part_hashes.append(proot); tp["part_hash"] = proot; tree["parts"].append(tp)
    book_root, _ = merkle(part_hashes)
    book["merkle_root"] = book_root; tree["merkle_root"] = book_root; tree["leaf_rule"] = PROV["merkle"]["leaf"]; tree["node_rule"] = PROV["merkle"]["node"]
    book["coverage"] = coverage; book["units"] = len(units)
    # write
    (ROOT / "master").mkdir(exist_ok=True); (ROOT / "provenance").mkdir(exist_ok=True)
    (ROOT / "master" / "book.json").write_text(json.dumps(book, ensure_ascii=False, indent=1), encoding="utf-8")
    (ROOT / "master" / "book.md").write_text("\n".join(md), encoding="utf-8")
    (ROOT / "provenance" / "content_ids.json").write_text(json.dumps({"edition_id": EDITION, "count": len(content_ids), "ids": content_ids}, ensure_ascii=False, indent=1), encoding="utf-8")
    (ROOT / "provenance" / "hashes.json").write_text(json.dumps({"spec": PROV["spec_version"], "units": {u["content_id"]: u["content_hash"] for u in units}, "master_json_sha256": hashlib.sha256((ROOT / "master" / "book.json").read_bytes()).hexdigest(), "master_md_sha256": hashlib.sha256((ROOT / "master" / "book.md").read_bytes()).hexdigest()}, ensure_ascii=False, indent=1), encoding="utf-8")
    (ROOT / "provenance" / "citation-registry.json").write_text(json.dumps({"edition_id": EDITION, "count": len(citation_registry), "registry": citation_registry}, ensure_ascii=False, indent=1), encoding="utf-8")
    (ROOT / "provenance" / "merkle-tree.json").write_text(json.dumps(tree, ensure_ascii=False, indent=1), encoding="utf-8")
    (ROOT / "provenance" / "merkle-root.txt").write_text(book_root + "\n", encoding="utf-8")
    (ROOT / "master" / "source_map.json").write_text(json.dumps(source_map, ensure_ascii=False, indent=1), encoding="utf-8")
    with open(ROOT / "master" / "source_map.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=list(source_map[0].keys()) if source_map else ["content_id"]); w.writeheader()
        for r in source_map:
            w.writerow({**r, "source_pages": " ".join(map(str, r["source_pages"]))})
    (ROOT / "master" / "citation_map.json").write_text(json.dumps({u["content_id"]: u["citation_id"] for u in units}, ensure_ascii=False, indent=1), encoding="utf-8")
    bib = [{"doc_key": k, "series_identifier": d.get("series_identifier"), "title": d.get("title"), "subtitle": d.get("subtitle"), "authors": d.get("authors"), "organization": d.get("organization"),
            "publication_date": d.get("publication_date"), "publication_type": d.get("publication_type"), "publication_status": d.get("publication_status"), "doi": d.get("doi"), "doi_url": d.get("doi_url"),
            "sha256": d["sha256"], "pages": d["pages"], "filename": d["filename"]} for k, d in SRC.items()]
    (ROOT / "master" / "bibliography.json").write_text(json.dumps({"note": "Built only from verified source metadata (qa/source_audit.json); nothing inferred.", "entries": bib}, ensure_ascii=False, indent=1), encoding="utf-8")
    con.execute("DELETE FROM content_units WHERE locale='fa'")
    for i, u in enumerate(units):
        con.execute("INSERT INTO content_units(content_id,structural_id,locale,part_id,chapter_id,section_id,title_fa,title_en,origin,order_no,canonical_text,content_hash,source_blocks,source_pages,meta_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    (u["content_id"], u["structural_id"], "fa", u["part_id"], u["chapter_id"], u["section_id"], u["title_fa"], u["title_en"], u["origin"], i, canonical_text(u["fa_text"]), u["content_hash"], json.dumps(u["source_blocks"]), json.dumps(u["source_pages"]), json.dumps({"chunk_ids": u["chunk_ids"]})))
    db.event("master_built", {"units": len(units), "merkle_root": book_root, "coverage": coverage}, con)
    print(f"master built: {len(units)} content units, merkle_root={book_root[:16]}…, coverage={coverage}")

if __name__ == "__main__":
    build()
