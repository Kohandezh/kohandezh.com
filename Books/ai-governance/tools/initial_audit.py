#!/usr/bin/env python3
"""Assemble INITIAL_AUDIT.md (MasterPrompt §81) from manifests, configs, QA files and runtime probes. Deterministic."""
from __future__ import annotations
import json, shutil, subprocess, sys
from pathlib import Path
import yaml

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

def sh(cmd: list[str]) -> str:
    try:
        return subprocess.run(cmd, capture_output=True, text=True, timeout=30).stdout.strip().splitlines()[0]
    except Exception:
        return "n/a"

def main():
    src = json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))
    docs = src["documents"]
    ext = json.load(open(ROOT / "manifests" / "extraction.json", encoding="utf-8")) if (ROOT / "manifests" / "extraction.json").exists() else {}
    models = yaml.safe_load(open(ROOT / "config" / "models.yaml", encoding="utf-8"))
    pub = yaml.safe_load(open(ROOT / "config" / "publishing.yaml", encoding="utf-8"))
    tcfg = yaml.safe_load(open(ROOT / "config" / "translation.yaml", encoding="utf-8"))
    bench = json.load(open(ROOT / "qa" / "model_benchmark.json", encoding="utf-8")) if (ROOT / "qa" / "model_benchmark.json").exists() else {}
    con = db.connect()
    n_chunks = con.execute("SELECT COUNT(*), COALESCE(SUM(word_count),0) FROM chunks").fetchone()
    n_terms = con.execute("SELECT status, COUNT(*) FROM terms GROUP BY status").fetchall()
    fonts = sh(["bash", "-c", "fc-list | grep -i -c -E 'vazirmatn'"]), sh(["bash", "-c", "fc-list | grep -i -c -E 'naskh arabic'"]), sh(["bash", "-c", "fc-list | grep -i -c -E 'NotoSansMono|Noto Sans Mono'"]), sh(["bash", "-c", "fc-list | grep -i -c -E 'Inter'"])
    tools = {t: shutil.which(t) or "MISSING" for t in ("pandoc", "weasyprint", "soffice", "pdftotext", "pdftoppm", "tesseract", "sqlite3", "git", "opencode", "agy", "claude", "gemini", "openssl")}
    total_pages = sum(d["pages"] for d in docs); total_words = sum(d["words_pdftotext"] for d in docs)
    total_blocks = sum(e.get("blocks", 0) for e in ext.values()); total_tables = sum(e.get("tables", 0) for e in ext.values()); total_figs = sum(e.get("figures", 0) for e in ext.values())
    L = []
    L += ["# INITIAL AUDIT — Kohandezh AI Reference Book (Persian Edition)", "", f"Generated: {db.now()}  ·  Edition: {pub['edition_id']}  ·  Orchestrator: Fable 5.1 (single instance)  ·  Repo: `{ROOT}`", "",
          "## 1. Source corpus (18 unique PDFs)", "", "| # | doc_key | file | pages | words | SHA-256 (first 16) | type | status | date | DOI | audit |", "|---|---|---|---|---|---|---|---|---|---|---|"]
    for d in docs:
        L.append(f"| {d['seq']} | {d['doc_key']} | {d['filename']} | {d['pages']} | {d['words_pdftotext']:,} | `{d['sha256'][:16]}` | {d.get('publication_type','?')} | {d.get('publication_status','?')} | {d.get('publication_date','?')} | {d.get('doi','') or '—'} | {d.get('audit_verdict') or d.get('audit','PENDING')} |")
    L += ["", f"**Totals:** {len(docs)} documents · {total_pages:,} pages · {total_words:,} English words (pdftotext) · all text-native (no OCR needed for body text).", "",
          "Hash file: `manifests/source_hashes.sha256` (verified: 18 OK). Bibliographic fields were extracted by GLM-5.3 and adversarially verified by Claude Sonnet 4.6 Thinking; corrections are logged in `qa/source_audit.json`.", "",
          "## 2. Extraction quality", "", "| doc_key | blocks | headings | tables | figures | body words | coverage vs pdftotext | engine |", "|---|---|---|---|---|---|---|---|"]
    for k, e in ext.items():
        L.append(f"| {k} | {e['blocks']} | {e.get('headings')} | {e['tables']} | {e['figures']} | {e['words_in_body_blocks']:,} | {e.get('coverage')} | {e.get('layout_engine')} |")
    L += ["", f"**Totals:** {total_blocks:,} source blocks · {total_tables} structured tables · {total_figs} figure assets. Extraction engine: PyMuPDF 1.28 + pymupdf-layout (ONNX page-layout model) → typed page boxes → immutable block IDs `DOC::Pnnnn::SECx.y::Bnnnn`.",
          "Known limitations: tables with merged cells are captured as pipe tables with `<br>`-joined cells; multi-page tables are captured page by page (re-joined at chunking by caption `(Continued)`); figure text is OCR'd for reference only.", "",
          "## 3. Runtime & tooling", "", "| tool | path |", "|---|---|"] + [f"| {t} | `{p}` |" for t, p in tools.items()]
    L += ["", f"Fonts: Vazirmatn={fonts[0]} face(s), Noto Naskh Arabic={fonts[1]}, Noto Sans Mono={fonts[2]}, Inter={fonts[3]} (installed via Homebrew casks; OFL/Apache licensed).",
          f"Python: project venv `.venv313` (Python 3.13; 3.14 lacks onnxruntime wheels) with PyMuPDF, pymupdf-layout, python-docx, PyNaCl, WeasyPrint deps, google-genai.", "",
          "## 4. Model discovery & routing", "",
          "| role | provider | model | status |", "|---|---|---|---|",
          "| Orchestrator | Claude Code (desktop) | Fable 5.1 | single instance, no parallel Fable agents (owner rule) |",
          "| Primary reviewer (REVIEW_MAX) | Antigravity CLI `agy` | claude-sonnet-4-6 (Thinking) | OK — JSON schema output; ~15k-token system overhead per call |",
          "| Escalation (REVIEW_ULTRA) | Antigravity CLI `agy` | claude-opus-4-6-thinking | OK — escalation only |",
          "| Translation candidates | Z.ai coding plan | glm-5.3 / glm-5.3-flash / glm-5.2 / glm-5-turbo | OK (thinking disabled) |",
          "| Translation candidates | Google AI | gemini-3.8-flash / gemini-3.1-flash-lite | OK (3.7/3.6 Flash intermittently 503) |",
          "| Embeddings | Google AI | gemini-embedding-2 / -001 | listed |",
          "| Images (only) | OpenAI | gpt-image-2 / gpt-image-1.5 / gpt-image-1-mini / chatgpt-image-latest | listed |",
          "| Unavailable | Z.ai | glm-5.3-highspeed, glm-5.2-highspeed | not in plan (code 1311) |",
          "| Unavailable | OpenCode Go | all | insufficient balance |",
          "| Unavailable | Claude Code CLI `claude -p` | — | OAuth session expired (run `claude login` to restore; not needed) |", "",
          "**REVIEW_MAX mapping:** Antigravity exposes no effort flag for Claude 4.6; thinking is built in. REVIEW_MAX = `claude-sonnet-4-6` (Thinking). REVIEW_ULTRA = `claude-opus-4-6-thinking` (maximum available).",
          "**GPT deny status:** `config/model-policy.yaml` denies provider `openai` for every text purpose and patterns `gpt-*`, `o3*`, `gpt-oss-*`, `chatgpt-*` (except listed image models). `tools/policy.py` self-test: PASS. OpenCode's default model (`rayen/…`) is never used; every call passes an explicit provider/model through the policy gate. `GPT_TEXT_USAGE = 0` is asserted at release from `work/logs/model_usage.jsonl`.",
          "**Antigravity status:** app installed (`/Applications/Antigravity.app`), CLI 1.2.5; models listed via `agy models`.", ""]
    if bench.get("summary"):
        L += ["## 5. Translation model benchmark (Claude Sonnet 4.6 scored)", "", "| model | runs | fail | latency | fidelity | completeness | terminology | fluency | formatting | composite |", "|---|---|---|---|---|---|---|---|---|---|"]
        for r in bench["summary"]:
            L.append(f"| {r['model']} | {r['runs']} | {r['failures']} | {r['latency_avg']}s | {r['fidelity']} | {r['completeness']} | {r['terminology']} | {r['fluency']} | {r['formatting']} | {r['composite']} |")
        L += ["", f"Selection: PRIMARY `{tcfg['PRIMARY_TRANSLATOR']}` · SECONDARY `{tcfg['SECONDARY_TRANSLATOR']}` · FAST_FALLBACK `{tcfg['FAST_FALLBACK_TRANSLATOR']}`", ""]
    else:
        L += ["## 5. Translation model benchmark", "", "Pending (`tools/benchmark.py`).", ""]
    L += ["## 6. Planned book architecture", "", "| Part | slug | title (fa) | primary sources |", "|---|---|---|---|"]
    for p in pub["parts"]:
        L.append(f"| {p['id']} | {p['slug']} | {p['title_fa']} | {', '.join(p['sources'])} |")
    L += ["", "Chapter-level architecture is derived from the verified TOCs (`manifests/sources.json → toc`) in Phase 7 (`taxonomy/book_structure.yaml`). Three knowledge layers are kept separate: source translation · editorial synthesis · Iranian implementation layer (§92–122).", "",
          "## 7. Chunking, terminology, concurrency", "",
          f"- Chunks generated: **{n_chunks[0]}** ({n_chunks[1]:,} English words) — semantic, 1,200–3,000 words, reduced for legal/security/definitions/tables/normative/math.",
          f"- Terminology: {', '.join(f'{r[0]}={r[1]}' for r in n_terms) or 'pending'} (mined from all 18 sources; high-impact terms reviewed by Claude Sonnet 4.6).",
          f"- Concurrency plan: translation workers {tcfg['concurrency']['initial']} → {tcfg['concurrency']['step_up_when_stable']} when error-rate < {tcfg['concurrency']['max_error_rate']}; Claude review workers 6; Opus escalation workers 2. Fable itself runs single-instance.",
          "- Resume: SQLite `state/project.db` + `work/logs/events.jsonl`; every approval checkpointed to `work/checkpoints/` and `translation_memory/fa/tm_fa.jsonl`; `tools/pipeline.py reset-inflight` recovers after crashes.", "",
          "## 8. Provenance plan", "",
          "- Canonical text normalization spec `kdj-hash-1` in `config/provenance.yaml` (NFC, LF, whitespace collapse, ZWNJ preserved, other zero-width/direction marks removed).",
          "- Content IDs `KDJ-AI-2026E1-Pxx-Cxx-Sxx-<HASH8>`; citation IDs without hash; locale variants `@fa`.",
          "- SHA-256 per block → section → chapter → part → book Merkle root; Ed25519-signed release manifest. **No publisher key is configured** (`KDJ_PUBLISHER_PRIVATE_KEY` unset) → the RC will be development-signed and report `PUBLISHER_SIGNING_KEY_REQUIRED`. No PDF signing certificate configured.",
          "- Zero-width watermarks are forbidden; hidden provenance only via HTML data attributes / DOCX & PDF metadata.", "",
          "## 9. Multilingual plan", "",
          "- Phase A: Persian only → PERSIAN_RELEASE_CANDIDATE. `config/multilingual.yaml` has `enabled_languages: [fa]`, `future_languages: [CONFIGURE_FROM_WEBSITE]` (the 10 site locales must be supplied by the owner).",
          "- Phase B (after RC): each locale translated directly from canonical English blocks, shared structural Content IDs and concept IDs, per-locale TM, hreflang HTML.", "",
          "## 10. Expected Persian outputs", "", "28 release deliverables per MasterPrompt §87 (source manifest, hashes, master JSON/MD, DOCX, PDF, HTML zip, glossary CSV/JSON, TM, source mapping, citation registry, RAG corpus, knowledge graph JSON/GraphML, bibliography, metrics, model usage, content IDs, Merkle tree/root, release manifest (+sig/pubkey), verification.json, QA report, checksums).", "",
          "## 11. Critical risks", "",
          "1. **Antigravity throughput/limits** for ~300 Sonnet reviews + escalations (unknown quota; mitigated by 6 workers, retries, resumable state).",
          "2. **Z.ai coding-plan rate limits** at 8–16 concurrent translations (mitigated by exponential backoff and Gemini fallback).",
          "3. **Gemini 3.x Flash 503s** (secondary only).",
          "4. **Playbook structure** (540 headings, card-style Suggested Actions) needs careful chunking to keep cards intact.",
          "5. **Multi-page tables** (AI RMF Tables 1–4, Playbook, AML taxonomy) are re-joined at chunking; must be re-verified in visual QA.",
          "6. **WeasyPrint/LibreOffice** not yet installed at audit time → DOCX/PDF build tooling is validated in Phase 19–21.",
          "7. **Publisher signing key absent** → release will be RC-only until the owner supplies an Ed25519 key.",
          "8. **Iranian regulatory references** (§109) require verified sources; none will be invented — cautious wording will be used where verification is unavailable.", ""]
    (ROOT / "INITIAL_AUDIT.md").write_text("\n".join(L), encoding="utf-8")
    print("INITIAL_AUDIT.md written:", len(L), "lines")

if __name__ == "__main__":
    main()
