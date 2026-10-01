#!/usr/bin/env python3
"""One-off deterministic patch: remove layout-engine inline HTML (<mark>, <sup>, <sub>, …) that leaked into blocks, chunks and translations.
<sup>N</sup> footnote references become [^N] (rendered as superscripts). Idempotent."""
from __future__ import annotations
import json, re, sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

SUP = re.compile(r"<sup>\s*([^<]{1,12}?)\s*</sup>")
TAGS = re.compile(r"</?(?:mark|sup|sub|u|s|span|b|i|em|strong|font)\b[^>]*>")

def fix(s: str) -> str:
    if "<" not in s: return s
    s = SUP.sub(lambda m: f"[^{m.group(1).strip()}]", s)
    return TAGS.sub("", s)

def main():
    con = db.connect(); n_blocks = n_chunks = n_tr = 0
    for p in (ROOT / "sources" / "extracted").glob("*.blocks.jsonl"):
        rows = [json.loads(l) for l in open(p, encoding="utf-8")]
        changed = False
        for b in rows:
            t = fix(b["text"])
            if t != b["text"]:
                b["text"] = t; b["sha256"] = db.sha256_text(t); changed = True; n_blocks += 1
                con.execute("UPDATE blocks SET text=?, sha256=? WHERE block_id=?", (t, b["sha256"], b["block_id"]))
        if changed:
            with open(p, "w", encoding="utf-8") as f:
                for b in rows: f.write(json.dumps(b, ensure_ascii=False) + "\n")
    for p in (ROOT / "work" / "chunks").glob("*/CH*.json"):
        c = json.load(open(p, encoding="utf-8"))
        t = fix(c["en_text"]); cb, ca = fix(c.get("context_before", "")), fix(c.get("context_after", ""))
        if t != c["en_text"] or cb != c.get("context_before") or ca != c.get("context_after"):
            c["en_text"], c["context_before"], c["context_after"] = t, cb, ca; c["sha256"] = db.sha256_text(t); n_chunks += 1
            p.write_text(json.dumps(c, ensure_ascii=False, indent=1), encoding="utf-8")
            con.execute("UPDATE chunks SET sha256=? WHERE chunk_id=?", (c["sha256"], c["chunk_id"]))
    for r in con.execute("SELECT chunk_id, version, fa_text FROM translations WHERE fa_text LIKE '%<%'").fetchall():
        t = fix(r["fa_text"])
        if t != r["fa_text"]:
            con.execute("UPDATE translations SET fa_text=?, sha256=? WHERE chunk_id=? AND version=?", (t, db.sha256_text(t), r["chunk_id"], r["version"])); n_tr += 1
            d, ch = r["chunk_id"].split("::"); jp = ROOT / "work" / "translations" / d / f"{ch}.v{r['version']}.json"
            if jp.exists():
                j = json.load(open(jp, encoding="utf-8")); j["fa_text"] = t; j["sha256"] = db.sha256_text(t); jp.write_text(json.dumps(j, ensure_ascii=False, indent=1), encoding="utf-8")
    for r in con.execute("SELECT chunk_id, fa_text FROM approved WHERE fa_text LIKE '%<%'").fetchall():
        t = fix(r["fa_text"])
        if t != r["fa_text"]:
            con.execute("UPDATE approved SET fa_text=?, sha256=? WHERE chunk_id=?", (t, db.sha256_text(t), r["chunk_id"]))
    db.event("tags_stripped", {"blocks": n_blocks, "chunks": n_chunks, "translations": n_tr}, con)
    print(f"stripped tags: blocks={n_blocks} chunks={n_chunks} translations={n_tr}")

if __name__ == "__main__":
    main()
