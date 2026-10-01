#!/usr/bin/env python3
"""Public verification CLI (MasterPrompt §48). Usage: python verify_release.py <release_dir>
Checks: artifact SHA-256 vs manifest · Ed25519 manifest signature vs public key · Merkle root recomputed from master JSON · content_id ↔ hash consistency.
Only depends on: hashlib, json, PyNaCl (pip install pynacl)."""
from __future__ import annotations
import hashlib, json, sys, unicodedata, re
from pathlib import Path

ZW = re.compile("[​‍﻿⁠‎‏‪-‮⁦-⁩]")
def canonical_text(s):
    s = unicodedata.normalize("NFC", s or "").replace("\r\n", "\n").replace("\r", "\n"); s = ZW.sub("", s)
    s = "\n".join(re.sub(r"[ \t ]+", " ", l).rstrip() for l in s.split("\n")); return re.sub(r"\n{3,}", "\n\n", s).strip("\n")
def h256(b: bytes) -> str: return hashlib.sha256(b).hexdigest()
def merkle(hs):
    if not hs: return h256(b"\x00")
    lvl = [h256(b"\x00" + bytes.fromhex(h)) for h in hs]
    while len(lvl) > 1:
        lvl = [h256(b"\x01" + bytes.fromhex(lvl[i]) + bytes.fromhex(lvl[i + 1])) if i + 1 < len(lvl) else lvl[i] for i in range(0, len(lvl), 2)]
    return lvl[0]

def main(d: Path):
    ok = True
    man = json.load(open(d / "23_release_manifest.json", encoding="utf-8"))
    for name, a in man["artifacts"].items():
        p = d / name
        got = h256(p.read_bytes()) if p.exists() else None
        good = got == a["sha256"]; ok &= good
        print(f"[{'OK' if good else 'FAIL'}] {name} sha256")
    try:
        from nacl.signing import VerifyKey
        pk = [l for l in (d / "25_public_key.pem").read_text().splitlines() if l and not l.startswith("-----")][0]
        sig = bytes.fromhex((d / "24_release_manifest.sig").read_text().strip())
        VerifyKey(bytes.fromhex(pk)).verify((d / "23_release_manifest.json").read_bytes(), sig)
        print("[OK] Ed25519 signature valid for public key", pk[:16] + "…")
    except Exception as e:
        ok = False; print("[FAIL] signature:", e)
    book = json.load(open(d / "04_book_master_fa.json", encoding="utf-8"))
    parts = []
    for P in book["parts"]:
        chs = []
        for C in P["chapters"]:
            secs = []
            for S in C["sections"]:
                payload = "\n".join([S["structural_id"], S["locale"], S["origin"], canonical_text(S["title_fa"]), canonical_text(S["title_en"]), canonical_text(S["fa_text"])])
                h = h256(payload.encode("utf-8"))
                if f"sha256:{h}" != S["content_hash"] or not S["content_id"].endswith(h[:8].upper()):
                    ok = False; print("[FAIL] content hash mismatch", S["content_id"])
                secs.append(h)
            chs.append(merkle(secs))
        parts.append(merkle(chs))
    root = merkle(parts)
    good = root == man["merkle_root"] == (d / "22_merkle_root.txt").read_text().strip(); ok &= good
    print(f"[{'OK' if good else 'FAIL'}] Merkle root {root[:16]}… matches manifest")
    print("VERIFICATION", "PASS" if ok else "FAIL")
    return 0 if ok else 1

if __name__ == "__main__":
    sys.exit(main(Path(sys.argv[1] if len(sys.argv) > 1 else "release")))
