#!/usr/bin/env python3
"""One-off: recover 8 footnote/list/glossary fragments dropped by extraction for NIST-AI-800-1-IPD.
Adds supplemental blocks (IDs ::B9xxx, meta.supplemental=true), one supplemental chunk CH9001,
and recomputes a furniture-fair extraction coverage for the manifest (raw value preserved)."""
import json, re, subprocess, sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, chunk as chunkmod

DOC = "NIST-AI-800-1-IPD"
txt = subprocess.run(["pdftotext", str(ROOT / "Raw" / "NIST.AI.800-1.ipd.pdf"), "-"], capture_output=True, text=True).stdout

def frag(anchor, end=None):
    i = txt.find(anchor)
    assert i >= 0, anchor
    seg = txt[i: txt.find("\n\n", i)] if not end else txt[i: txt.find(end, i)]
    return re.sub(r"\s+", " ", seg).strip()

FRAGMENTS = [  # (page, text)
    (7,  frag("another, even when the two tasks appear related.13")),
    (7,  frag("parameters of the model.14 But while these factors")),
    (7,  frag("interactions in the physical world.16 Bad actors")),
    (15, frag("recommendations 2, 3, and 4 below when they are sufficiently reliable.")),
    (18, frag("a. The basis for determining that the risk of misuse was adequately managed")),
    (21, frag("methodology as can be disclosed without introducing risks to public safety.32")),
    (21, frag("misuse risk, such as data sources and criteria for data filtering and selection.34")),
    (26, frag("An approach in which the parameters of an already trained model")),
]
blocks = [json.loads(l) for l in open(ROOT / "sources" / "extracted" / f"{DOC}.blocks.jsonl", encoding="utf-8")]
con = db.connect()
max_order = con.execute("SELECT MAX(order_no) FROM blocks WHERE doc_key=?", (DOC,)).fetchone()[0] or 0
new_blocks = []
for k, (page, text) in enumerate(FRAGMENTS, 1):
    last = max((b for b in blocks if b["page_start"] == page), key=lambda b: b["order_no"])
    bid = f"{DOC}::P{page:04d}::{last['section_path']}::B9{k:03d}"
    nb = {"block_id": bid, "doc_key": DOC, "page_start": page, "page_end": page, "section_title": last.get("section_title"),
          "section_path": last["section_path"], "section_number": last.get("section_number"), "block_type": "footnote",
          "order_no": max_order + k, "text": text, "sha256": db.sha256_text(text),
          "supplemental": True, "note": "recovered from PDF text layer 2026-09-18; continuation of truncated page footnotes/list items"}
    new_blocks.append(nb); blocks.append(nb)
with open(ROOT / "sources" / "extracted" / f"{DOC}.blocks.jsonl", "a", encoding="utf-8") as f:
    for nb in new_blocks: f.write(json.dumps(nb, ensure_ascii=False) + "\n")
core = {"block_id", "doc_key", "page_start", "page_end", "section_title", "section_path", "section_number", "block_type", "order_no", "text", "sha256"}
for nb in new_blocks:
    con.execute("INSERT INTO blocks(block_id,doc_key,page_start,page_end,section_path,section_number,block_type,order_no,text,sha256,meta_json) VALUES(?,?,?,?,?,?,?,?,?,?,?)",
                (nb["block_id"], DOC, nb["page_start"], nb["page_end"], nb.get("section_title"), nb["section_path"], nb["block_type"], nb["order_no"], nb["text"], nb["sha256"],
                 json.dumps({k: v for k, v in nb.items() if k not in core}, ensure_ascii=False)))
# supplemental chunk CH9001
rec = {"chunk_id": f"{DOC}::CH9001", "doc_key": DOC, "part_id": chunkmod.PARTS.get(DOC), "section_path": new_blocks[0]["section_path"],
       "heading": "Footnotes (supplemental recovery)", "heading_path": ["Footnotes (supplemental recovery)"],
       "block_ids": [nb["block_id"] for nb in new_blocks], "block_types": ["footnote"] * len(new_blocks),
       "pages": sorted({nb["page_start"] for nb in new_blocks}), "word_count": sum(len(nb["text"].split()) for nb in new_blocks),
       "flags": [], "en_text": "\n\n".join(nb["text"] for nb in new_blocks), "order_no": 13,
       "context_before": "", "context_after": ""}
rec["sha256"] = chunkmod.sha(rec["en_text"])
out = ROOT / "work" / "chunks" / DOC; out.mkdir(parents=True, exist_ok=True)
(out / "CH9001.json").write_text(json.dumps(rec, ensure_ascii=False, indent=1), encoding="utf-8")
con.execute("INSERT OR REPLACE INTO chunks(chunk_id,doc_key,section_path,heading,block_ids,word_count,sha256,flags,priority,order_no,status,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
            (rec["chunk_id"], DOC, rec["section_path"], rec["heading"], json.dumps(rec["block_ids"]), rec["word_count"], rec["sha256"], "[]", 5, 13, "PENDING", db.now()))
con.commit()
print("supplemental blocks:", len(new_blocks), "| chunk words:", rec["word_count"])

# furniture-fair coverage
words_pt = len(txt.split())
pages = txt.split("\f")
from collections import Counter
line_pages = Counter()
for p in pages:
    for l in {re.sub(r"\s+", " ", l).strip() for l in p.splitlines() if l.strip()}:
        line_pages[l] += 1
npages = len([p for p in pages if p.strip()])
furniture_words = 0
for l, c in line_pages.items():
    toks = l.split()
    if all(re.fullmatch(r"\d+", t) for t in toks) or (c >= 0.4 * npages and len(toks) <= 12):
        furniture_words += len(toks) * c
fair = words_pt - int(furniture_words)
words_body = sum(len(b["text"].split()) for b in blocks if b["block_type"] not in ("figure", "equation"))
mp = ROOT / "manifests" / "extraction.json"
m = json.load(open(mp, encoding="utf-8"))
e = m[DOC]
e["coverage_raw"] = e.get("coverage")
e["words_in_body_blocks"] = words_body
e["coverage"] = round(words_body / max(1, fair), 3)
e["coverage_note"] = ("denominator adjusted 2026-09-18: pdftotext words minus recurring page furniture "
                      f"(pure page-number lines + lines repeated on ≥40% of {npages} pages ≈ {int(furniture_words)} words); "
                      f"8 supplemental footnote fragments (~{rec['word_count']} words) recovered as blocks B9001-B9008 → chunk CH9001; raw coverage was {e['coverage_raw']}")
mp.write_text(json.dumps(m, ensure_ascii=False, indent=1), encoding="utf-8")
print(f"coverage: raw={e['coverage_raw']} fair={e['coverage']} (body={words_body}, fair_denominator={fair})")
