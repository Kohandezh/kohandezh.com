#!/usr/bin/env python3
"""Retranslate chunks whose current translation has a hard structural failure (feedback = the failing checks), keeping them REVIEW_QUEUED.
No Claude calls. Up to 2 attempts per chunk."""
from __future__ import annotations
import json, sys
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, translate  # noqa: E402

def fix(chunk_id: str) -> str:
    con = db.connect()
    for attempt in range(2):
        tr = con.execute("SELECT structural_check, version FROM translations t JOIN chunks c ON c.chunk_id=t.chunk_id AND c.current_version=t.version WHERE t.chunk_id=?", (chunk_id,)).fetchone()
        st = json.loads(tr["structural_check"] or "{}")
        if not st.get("hard_fail"):
            con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id)); return f"{chunk_id}: clean at v{tr['version']}"
        fb = "Automated structural check failed on the previous attempt: " + json.dumps([i for i in st["issues"] if i["severity"] == "hard"], ensure_ascii=False)[:1200] + \
             "\nFix these precisely: keep every table row, heading, list item, number and URL; never output Chinese/Japanese/Korean characters."
        try:
            rec = translate.translate_chunk(chunk_id, feedback=fb, con=con)
        except Exception as e:
            con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id)); return f"{chunk_id}: error {str(e)[:100]}"
        if not rec["structural"]["hard_fail"]:
            con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id)); return f"{chunk_id}: fixed at v{rec['version']} ({rec['model']})"
    con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
    return f"{chunk_id}: still hard-fail after retries → reviewer will handle"

def main():
    con = db.connect()
    ids = [r["chunk_id"] for r in con.execute("SELECT t.chunk_id FROM translations t JOIN chunks c ON c.chunk_id=t.chunk_id AND c.current_version=t.version WHERE c.status='REVIEW_QUEUED' AND json_extract(t.structural_check,'$.hard_fail')=1")]
    print("hard-fail chunks:", len(ids), flush=True)
    with ThreadPoolExecutor(max_workers=6) as ex:
        for line in ex.map(fix, ids):
            print(line, flush=True)
    db.event("hardfails_retranslated", {"count": len(ids)}, con)

if __name__ == "__main__":
    main()
