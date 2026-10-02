# Kohandezh AI Reference Book — production pipeline

> Bundle format (frozen contract for every book): [`../BOOK-FORMAT.md`](../BOOK-FORMAT.md).

Persian (fa-IR) integrated reference book built from 18 NIST AI publications. Governing specification: `MasterPrompt.MD`. Owner: محمدعلی کهن‌دژ · kohandezh.com.

## Layout

| path | content |
|---|---|
| `config/` | model policy (GPT text models denied), model inventory, translation/review/style/publishing/provenance/multilingual settings |
| `sources/original` · `sources/extracted` · `sources/pages` · `sources/tables` | the 18 PDFs (SHA-256 in `manifests/source_hashes.sha256`), typed block extraction (`*.blocks.jsonl`, immutable IDs `DOC::Pnnnn::SECx::Bnnnn`), per-page text, structured tables |
| `manifests/` | sources (audited metadata), extraction stats, tables/figures/pages manifests |
| `taxonomy/` | book structure (parts → chapters → sections), terminology candidates, Persian document titles |
| `translation_memory/` | glossary (JSON/CSV, approved/rejected terms), Persian TM (`fa/tm_fa.jsonl`) |
| `work/chunks` · `work/translations` · `work/reviews` · `work/escalations` · `work/checkpoints` · `work/editorial` | semantic chunks, first-pass translations (versioned), Claude reviews, Opus escalations, approvals, editorial/localization units |
| `state/project.db` + `work/logs/events.jsonl` | persistent, resumable state (SQLite WAL + append-only event log); `work/logs/model_usage.jsonl` is the model ledger |
| `master/` | canonical `book.json` / `book.md`, bibliography, source map, citation map |
| `provenance/` | content IDs, hashes, Merkle tree/root, citation registry, release manifest + Ed25519 signature, public key, verification |
| `knowledge/` | RAG corpus, entities/relations, graph JSON/GraphML |
| `html/` · `docx/` · `pdf/` · `images/` · `templates/` · `release/` | build outputs and the 28 release deliverables |

## Tools (run with `.venv313/bin/python`)

`tools/audit_sources.py` → `extract2.py` → `normalize_sources.py` → `chunk.py` → `book_structure.py` → `terms_mine.py {mine,propose,review,export}` → `benchmark.py` → `pipeline.py run` (translate → structural check → terminology lint → Claude Sonnet 4.6 REVIEW_MAX → Opus 4.6 REVIEW_ULTRA escalation → approval + TM) → `master.py` → `editorial.py` → `master.py` → `rag.py` · `kg.py` · `build_html.py` · `build_docx.py` · `build_pdf.py` · `images.py` → `release.py` → `qa_final.py` → `verify_release.py`.

`pipeline.py status` shows progress; `pipeline.py reset-inflight` recovers after a crash. Reviews pause automatically when the Antigravity quota is exhausted (`tools/resume_reviews.sh` resumes them).

## Model routing

Orchestrator Fable 5.1 (single instance) · translation GLM-5.3-flash → Gemini-3.1-flash-lite → GLM-5-turbo · review Claude Sonnet 4.6 Thinking (via Antigravity) · escalation Claude Opus 4.6 Thinking · images OpenAI image models only · **GPT text models: denied** (`config/model-policy.yaml`, enforced by `tools/policy.py`).
