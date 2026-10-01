#!/usr/bin/env python3
"""One-off: warm work/kg_cache in parallel so tools/kg.py main() becomes cache-only."""
import json, sys, time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import kg

book = json.load(open(ROOT / "master" / "book.json", encoding="utf-8"))
src = {d["doc_key"]: d for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]}
blocks_by_doc = {}
for dk in src:
    p = ROOT / "sources" / "extracted" / f"{dk}.blocks.jsonl"
    if p.exists():
        blocks_by_doc[dk] = {b["block_id"]: b for b in (json.loads(l) for l in open(p, encoding="utf-8"))}
units = [S for P in book["parts"] for C in P["chapters"] for S in C["sections"] if S["fa_text"].strip()]
todo = [u for u in units if not (kg.CACHE / (u["content_id"] + ".json")).exists()]
print(f"kg warm: {len(todo)}/{len(units)} units to extract", flush=True)
done = [0]
def work(u):
    try:
        kg.extract_unit(u, blocks_by_doc)
    except Exception as e:
        print("ERR", u["content_id"], str(e)[:100], flush=True)
    done[0] += 1
    if done[0] % 10 == 0: print(f"kg warm: {done[0]}/{len(todo)}", flush=True)
t0 = time.time()
with ThreadPoolExecutor(max_workers=6) as ex:
    list(ex.map(work, todo))
print(f"kg warm DONE in {time.time()-t0:.0f}s", flush=True)
