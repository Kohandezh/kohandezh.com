#!/usr/bin/env python3
"""Phases 29–31 — release manifest, Ed25519 signing, verification package, release/ deliverables (MasterPrompt §38, §41, §48, §87).
Publisher key: env KDJ_PUBLISHER_PRIVATE_KEY (path to 32-byte raw/hex or PEM seed). If absent → DEVELOPMENT key (state/dev-signing-key.pem, gitignored)
and PUBLISHER_SIGNING_KEY_REQUIRED is reported. Private keys are never logged or copied."""
from __future__ import annotations
import hashlib, json, os, shutil, subprocess, sys, zipfile
from pathlib import Path
import yaml
from nacl.signing import SigningKey
from nacl.encoding import HexEncoder
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

PROV = yaml.safe_load(open(ROOT / "config" / "provenance.yaml", encoding="utf-8"))
PUB = yaml.safe_load(open(ROOT / "config" / "publishing.yaml", encoding="utf-8"))
REL = ROOT / "release"

def sha_file(p: Path) -> str:
    h = hashlib.sha256()
    with open(p, "rb") as f:
        for chunk in iter(lambda: f.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()

def load_signing_key() -> tuple[SigningKey, str]:
    env = os.environ.get(PROV["signing"]["publisher_key_path_env"])
    if env and Path(env).exists():
        raw = Path(env).read_bytes().strip()
        try:
            seed = bytes.fromhex(raw.decode()) if len(raw) == 64 else raw
        except Exception:
            seed = raw
        return SigningKey(seed[:32]), "PUBLISHER"
    dev = ROOT / PROV["signing"]["dev_key_path"]
    if dev.exists():
        return SigningKey(bytes.fromhex(dev.read_text().strip().splitlines()[-1])), "DEVELOPMENT"
    sk = SigningKey.generate()
    dev.parent.mkdir(parents=True, exist_ok=True)
    dev.write_text("# DEVELOPMENT Ed25519 seed — NOT the کهن‌دژ publisher identity. Never commit.\n" + sk.encode(HexEncoder).decode() + "\n", encoding="utf-8")
    os.chmod(dev, 0o600)
    return sk, "DEVELOPMENT"

def zip_dir(src: Path, dst: Path):
    with zipfile.ZipFile(dst, "w", zipfile.ZIP_DEFLATED) as z:
        for p in sorted(src.rglob("*")):
            if p.is_file(): z.write(p, p.relative_to(src.parent))

def main(release_id: str):
    REL.mkdir(exist_ok=True)
    con = db.connect()
    deliver = {
        "01_source_manifest.json": ROOT / "manifests" / "sources.json", "02_source_hashes.sha256": ROOT / "manifests" / "source_hashes.sha256",
        "03_book_master_fa.md": ROOT / "master" / "book.md", "04_book_master_fa.json": ROOT / "master" / "book.json",
        "05_book_fa.docx": ROOT / "docx" / "fa" / "book-fa.docx", "06_book_fa.pdf": ROOT / "pdf" / "fa" / "book-fa.pdf",
        "08_glossary_fa_en.csv": ROOT / "translation_memory" / "glossary.csv", "09_glossary.json": ROOT / "translation_memory" / "glossary.json",
        "10_translation_memory_fa.jsonl": ROOT / "translation_memory" / "fa" / "tm_fa.jsonl", "11_source_mapping.csv": ROOT / "master" / "source_map.csv",
        "12_source_mapping.json": ROOT / "master" / "source_map.json", "13_citation_registry.json": ROOT / "provenance" / "citation-registry.json",
        "14_rag_corpus_fa.jsonl": ROOT / "knowledge" / "chunks.jsonl", "15_knowledge_graph.json": ROOT / "knowledge" / "graph.json",
        "16_knowledge_graph.graphml": ROOT / "knowledge" / "graph.graphml", "17_bibliography.json": ROOT / "master" / "bibliography.json",
        "20_content_ids.json": ROOT / "provenance" / "content_ids.json", "21_merkle_tree.json": ROOT / "provenance" / "merkle-tree.json",
        "22_merkle_root.txt": ROOT / "provenance" / "merkle-root.txt",
    }
    html_zip = REL / "07_html_book_fa.zip"
    if (ROOT / "html" / "fa").exists():
        zip_dir(ROOT / "html" / "fa", html_zip)
    missing = []
    for name, src in deliver.items():
        if src.exists(): shutil.copy2(src, REL / name)
        else: missing.append(name)
    # metrics & model usage
    usage = [dict(r) for r in con.execute("SELECT provider, model, purpose, COUNT(*) calls, SUM(input_tokens) input_tokens, SUM(output_tokens) output_tokens, SUM(CASE WHEN status='ok' THEN 0 ELSE 1 END) errors FROM model_usage GROUP BY provider, model, purpose")]
    gpt_text = sum(u["calls"] for u in usage if u["provider"] == "openai" and u["purpose"] != "image")
    (REL / "19_model_usage.json").write_text(json.dumps({"GPT_TEXT_USAGE": gpt_text, "usage": usage}, ensure_ascii=False, indent=1), encoding="utf-8")
    st = {r["status"]: r["n"] for r in con.execute("SELECT status, COUNT(*) n FROM chunks GROUP BY status")}
    rv = con.execute("SELECT mode, COUNT(*) n FROM reviews GROUP BY mode").fetchall()
    book = json.load(open(ROOT / "master" / "book.json", encoding="utf-8"))
    metrics = {"chunks_by_status": st, "reviews": {r["mode"]: r["n"] for r in rv}, "approved": con.execute("SELECT COUNT(*) FROM approved").fetchone()[0],
               "escalated": con.execute("SELECT COUNT(*) FROM approved WHERE escalated=1").fetchone()[0], "failed_jobs": con.execute("SELECT COUNT(*) FROM jobs WHERE status='FAILED'").fetchone()[0],
               "retried_jobs": con.execute("SELECT COUNT(*) FROM jobs WHERE retry_count>0").fetchone()[0], "content_units": book.get("units"),
               "persian_words": sum(len(S["fa_text"].split()) for P in book["parts"] for C in P["chapters"] for S in C["sections"]),
               "parts": len(book["parts"]), "chapters": sum(len(P["chapters"]) for P in book["parts"]), "sections": sum(len(C["sections"]) for P in book["parts"] for C in P["chapters"]),
               "glossary_terms": con.execute("SELECT COUNT(*) FROM terms").fetchone()[0]}
    (REL / "18_translation_metrics.json").write_text(json.dumps(metrics, ensure_ascii=False, indent=1), encoding="utf-8")
    # manifest
    artifacts = {p.name: {"sha256": sha_file(p), "bytes": p.stat().st_size} for p in sorted(REL.iterdir()) if p.is_file() and not p.name.startswith(("23_", "24_", "25_", "26_", "28_"))}
    tool_versions = {"python": sys.version.split()[0], "pymupdf": __import__("pymupdf").__version__, "pandoc": subprocess.run(["pandoc", "--version"], capture_output=True, text=True).stdout.split("\n")[0] if shutil.which("pandoc") else "n/a"}
    manifest = {"edition_id": PROV["edition_id"], "release_id": release_id, "build_date": db.now(), "publisher": PUB["brand"], "website": PUB["website"], "compiler": PUB["compiler"],
                "hash_spec": PROV["spec_version"], "source_hashes": {d["filename"]: d["sha256"] for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]},
                "master_json_sha256": sha_file(ROOT / "master" / "book.json"), "master_md_sha256": sha_file(ROOT / "master" / "book.md"), "merkle_root": book["merkle_root"],
                "artifacts": artifacts, "model_routing_summary": {"orchestrator": "claude-fable-5-1 (single instance)", "translation": sorted({u["model"] for u in usage if u["purpose"] == "translation"}),
                "review": "antigravity/claude-sonnet-4-6 (REVIEW_MAX)", "escalation": "antigravity/claude-opus-4-6-thinking (REVIEW_ULTRA)", "GPT_TEXT_USAGE": gpt_text},
                "prompt_versions": {"translate": "translate-v1", "review": "review-v1"}, "tool_versions": tool_versions, "missing_deliverables": missing}
    mpath = REL / "23_release_manifest.json"
    mpath.write_text(json.dumps(manifest, ensure_ascii=False, indent=1, sort_keys=True), encoding="utf-8")
    shutil.copy2(mpath, ROOT / "provenance" / "release-manifest.json")
    sk, kind = load_signing_key()
    sig = sk.sign(mpath.read_bytes()).signature
    (REL / "24_release_manifest.sig").write_bytes(sig.hex().encode() + b"\n"); shutil.copy2(REL / "24_release_manifest.sig", ROOT / "provenance" / "release-manifest.sig")
    pub_hex = sk.verify_key.encode(HexEncoder).decode()
    pem = f"-----BEGIN KDJ ED25519 PUBLIC KEY ({kind})-----\n{pub_hex}\n-----END KDJ ED25519 PUBLIC KEY ({kind})-----\n"
    (REL / "25_public_key.pem").write_text(pem, encoding="utf-8"); (ROOT / "provenance" / "public-key.pem").write_text(pem, encoding="utf-8")
    verification = {"edition_id": PROV["edition_id"], "release_id": release_id, "publication_date": manifest["build_date"], "merkle_root": book["merkle_root"], "signature_kind": kind,
                    "signing_status": "SIGNED_BY_PUBLISHER" if kind == "PUBLISHER" else "PUBLISHER_SIGNING_KEY_REQUIRED (development-signed release candidate)",
                    "public_key_ed25519_hex": pub_hex, "manifest_sha256": sha_file(mpath), "manifest_signature_hex": sig.hex(), "artifact_hashes": artifacts,
                    "hash_spec": PROV["spec_version"], "verify_instructions": ["1. sha256sum each artifact and compare with artifact_hashes.",
                        "2. Verify Ed25519 signature of 23_release_manifest.json bytes with public_key_ed25519_hex (python: nacl.signing.VerifyKey(bytes.fromhex(pk)).verify(manifest_bytes, bytes.fromhex(sig))).",
                        "3. Recompute the Merkle root from 04_book_master_fa.json with tools/verify_release.py and compare with merkle_root.",
                        "4. A section belongs to this edition iff its content_id and content_hash appear in 20_content_ids.json / 21_merkle_tree.json."]}
    (REL / "26_verification.json").write_text(json.dumps(verification, ensure_ascii=False, indent=1), encoding="utf-8"); shutil.copy2(REL / "26_verification.json", ROOT / "provenance" / "verification.json")
    with open(REL / "28_checksums.sha256", "w", encoding="utf-8") as f:
        for p in sorted(REL.iterdir()):
            if p.is_file() and p.name != "28_checksums.sha256": f.write(f"{sha_file(p)}  {p.name}\n")
    db.event("release_packaged", {"release_id": release_id, "signature_kind": kind, "missing": missing, "GPT_TEXT_USAGE": gpt_text}, con)
    print(json.dumps({"release_id": release_id, "signature": kind, "signing_status": verification["signing_status"], "artifacts": len(artifacts), "missing": missing, "GPT_TEXT_USAGE": gpt_text, "merkle_root": book["merkle_root"]}, ensure_ascii=False, indent=1))

if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else "RC1")
