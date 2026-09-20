# AI Book Current Handover

## Current state

`DESIGN_STATUS = FROZEN_REVISION_2` (unchanged, not touched this session). This session implemented and tested four phases: **Phase 11 (Catalog)**, **Phase 12 (Knowledge Graph)**, **Phase 13 (Ask/RAG)**, and **Phase 14 (PDF viewer + request intake)** — the `/ai-book/pdf/` viewer with a manifest-verified local stream and the storage-free, configuration-gated `/ai-book/request-pdf/` intake. All prior subsystems are unchanged and still green. The Security and Technical SEO review gates are complete (both PASS). The next task is the **Performance review gate**, then Final QA and Production Build per sequencing.

Canonical book root is `/Users/emperor/Documents/AI/AiBook` (read-only). Do not read `.env` or private signing keys. Current edition `2026E1` is development-signed, not publisher-signed. All work happens in the linked worktree `.claude/worktrees/kohandezh-reader-data-phase-9-8f7e49` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`.

## What changed this session (Phase 11: Catalog — see git `b227752`)

- New `KBK_AI_Book_Catalog` (`includes/class-kbk-ai-book-catalog.php`): one fail-closed `load()` validating glossary/sources manifest/bibliography/source map/templates/entities before any view reads them; hand-built bounded read models only. Glossary index sorted by Persian preferred term (safe keys only — reviewer secrets structurally absent, tested); sources = 18 audited documents with bibliography merge and per-doc section counts summing to exactly 660; templates = 20 cards + fail-closed details (own origin label `editorial_template_ir`, distinct from the three content origins); concepts = 3,671 entities, type filter (20 types), 60/page pagination with clamping, entity details with mentions resolved into Reader links capped at 12 and the raw mention list never exposed.
- Five new validators (`validate_sources_manifest/bibliography/source_map/template/entity`), `kbk_template/kbk_entity/kbk_type/kbk_page` query vars (strict allowlists), views `sources`/`templates`/`concepts` + full glossary index, one additive token-based CSS block (`.ab-term-list`), new `ai-book-catalog.test.php` (8 blocks) + extended route test.

## What changed this session (Phase 12: Knowledge Graph — see git `8c63eec`)

- New `KBK_AI_Book_Graph` over `knowledge/graph.json` (3,671 nodes, 10,474 edges, 17 relation types; validator-enforced no dangling endpoints). Graph artifact loads lazily (graph views only). `/ai-book/graph/` overview (honest stats + relation legend) and depth-1 node neighborhoods capped at 24 edges with deterministic SVG ring (≤12 nodes) + accessible list; evidence quotes bounded at 160; every anchor/endpoint repository-resolved.
- Reader wiring: `related_map()` computes per-section Related Concepts (≤6, deterministic ranking) and Related Sections (≤4, co-mention frequency, self/siblings excluded) in one bounded scan over catalog entities; rendered per section via existing helpers; ADR-AB-0012.

## What changed this session (Phase 13: Ask/RAG)

- New `KBK_AI_Book_Ask` (`includes/class-kbk-ai-book-ask.php`): **local cited retrieval always available** — a bounded normalized scan (shared Persian normalization, AND token semantics, weights label 8 / keywords 4 / body ≤3 / label-phrase +6) over `release/14_rag_corpus_fa.jsonl` (2,420 validated chunks). Retrieval models: ≤6 per query (`MAX_RETRIEVAL`), excerpts ≤360 chars as `{text, mark}` highlight segments (template escapes, owns `<mark>`), part/chapter hierarchy, origin labels, source docs (≤3), citation URL (`/fa/ai-book/` namespace with section fragment, validator-enforced), `reader_resolvable` flag with repository resolution — raw `fa_text` never leaves the class (tested).
- **Answer generation is configuration-required**: without `KBK_AI_BOOK_ASK_ENDPOINT`/`KBK_AI_BOOK_ASK_API_KEY` (optional `KBK_AI_BOOK_ASK_MODEL`), the view honestly shows cited passages only (`PROVIDER_REQUIRED`). With config, the provider call (`wp_remote_post`, 30s timeout, key never logged) must pass `validate_provider_response()`: bounded non-empty answer ≤4000 chars, no control characters, `used` ⊆ offered content IDs, bounded model string — any violation is discarded (`PROVIDER_INVALID`); unreachable/2xx-malformed degrade to `PROVIDER_UNAVAILABLE`. The citation contract means the provider cannot invent references outside the retrieved context (tested with stubbed HTTP: unreachable/invalid/valid paths).
- New validator `validate_rag_chunk()` (chunk_id must be `<content_id>#r\d{1,3}`, edition-matched content/structural IDs, `language=fa`, content-origin allowlist, fa_text 1–5000 chars, P/C/S ID shapes, canonical URL in the canonical namespace with the section fragment).
- `KBK_AI_Book`: `ask` view + 9th rewrite, `ask()`/`ask_status()`/`current_ask()` (CONFIG_REQUIRED → PROVIDER_REQUIRED → READY progression; reuses the sanitized `kbk_q` query var).
- `templates/ai-book.php`: ask view (frozen hero copy, form, answer block when present, honest provider-error note, cited passage cards with Reader deep links + canonical citation links, honest no-match fallback). No new CSS needed (reuses `.ab-ask-form`/`.ab-message`/`.ab-citations`/`.ab-fallback` frozen components).
- New test `_tooling/tests/ai-book-ask.test.php` (8 blocks, real canonical corpus): retrieval + repository resolution + no-body-leak, honest empty states, hostile inputs, provider response contract (7 invalid fixtures), rag chunk validator (8 tamper fixtures), stubbed provider paths (unreachable/invalid/valid), corrupted-corpus fail-closed, route-layer hygiene. Route test extended to 9 rewrites + ask CONFIG_REQUIRED states. Wired into `test:ai-book`.

## What changed this session (Phase 14: PDF viewer + request intake)

- New `KBK_AI_Book_Pdf` (`includes/class-kbk-ai-book-pdf.php`): fail-closed facts over `provenance/release-manifest.json` + `release/06_book_fa.pdf`. Two new validators (`validate_release_manifest`, `validate_pdf_file`: `%PDF-` header + exact byte-size match). `/ai-book/pdf/` shows real edition facts (edition, dev-sign status, truncated merkle root/SHA-256, bytes, build date) and embeds the file via `<object>` pointing at the local stream route `^ai-book/pdf/file/?$` — native browser rendering, no third-party viewer, PDF.js toolbar honestly disabled (ADR-AB-0014). The stream executor (template_redirect) emits typed, noindexed headers from a pure testable plan; the full sha256 is verified offline in the test suite.
- New `KBK_AI_Book_Request` (`includes/class-kbk-ai-book-request.php`): storage-free intake — per-field validation (name ≤80 optional, email ≤120 + filter_var, `use` enum, reason ≤500, consent exactly `1`), then provider forwarding only when `KBK_AI_BOOK_REQUEST_ENDPOINT`/`KBK_AI_BOOK_REQUEST_API_KEY` exist. Response contract: `{status: ok|rejected, reference: ≤32 opaque}`; violations/unreachable/non-2xx degrade honestly (ADR-AB-0015). Without configuration the submit button is disabled and the state is honest CONFIG_REQUIRED. Nothing is persisted locally; the API key appears only in the Authorization header.
- `KBK_AI_Book`: `pdf`/`request-pdf` views + rewrites 10–12 (`^ai-book/pdf/?$`, `^ai-book/request-pdf/?$`, `^ai-book/pdf/file/?$` with `kbk_pdf_file` query var), `pdf()`/`pdf_status()`, `request_engine()`/`request_status()`, `current_request()` (sanitized repost values + bounded submission outcome), `maybe_stream_pdf()` executor hook.
- Template: real pdf view (edition panel, honest degraded states, text alternative to the Reader) and real request view (working POST form with per-field error rendering, four provider states, success reference, process aside); one additive CSS block (`.ab-pdf-embed`).
- New test `_tooling/tests/ai-book-pdf.test.php` (5 blocks, real canonical data incl. offline sha256 verification of the 38MB file): manifest-backed meta, typed noindexed stream plan, tampered manifest/file fail-closed paths (invalid manifest, missing manifest, wrong header, size mismatch), bounded intake field errors, honest unconfigured state, provider contract (unavailable/invalid contract fixtures/rejected/ok with stubbed HTTP). Route test extended to 12 rewrites + pdf/request states. Wired into `test:ai-book`.

## Skills / Agent Capabilities

- See `docs/ai-book/SKILLS_MANIFEST.md` (reconciled 2026-09-19). No new Skills were needed for any phase this session; `MANUAL_POLICY` covered PHP type safety and SEO-safe noindex behavior. The PDF phase used only byte/manifest inspection — no PDF-creation Skill was activated.
- No successor must install an identical Skill or the generic `claude-flow`/`sparc`/`swarm` catalogue — unrelated to this project.

## Verified evidence

- `php _tooling/tests/ai-book-artifacts.test.php` → PASS
- `php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-wordpress-route.test.php` → PASS (9 rewrites, catalog/graph/ask states)
- `php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-search.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-catalog.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-graph.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-ask.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-pdf.test.php /Users/emperor/Documents/AI/AiBook` → PASS (new; includes offline sha256 verification of `release/06_book_fa.pdf` against the release manifest)
- `npm run test:ai-book` → PASS end to end (10 test suites + visual shell build)
- `npm test` (full existing site suite) → PASS, zero regression
- `php -l` clean on all touched PHP files
- No local WordPress install exists to browser-test end to end (gotcha 1); verified via isolated PHP unit tests consistent with prior sessions. The provider HTTP path is stubbed in tests — no live provider exists locally and none must be invented.

## Files changed this session

- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-catalog.php` (new, Phase 11)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-graph.php` (new, Phase 12)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-ask.php` (new, Phase 13)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-pdf.php` (new, Phase 14)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-request.php` (new, Phase 14)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php` (9 new validators)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php` (views: glossary index/sources/templates/concepts/graph/ask/pdf/request-pdf; query vars incl. `kbk_pdf_file`; catalog+graph+ask+pdf+request accessors; stream executor)
- `_tooling/wp-theme/kohandezh-knowledge/templates/ai-book.php` (all new branches + Reader related wiring)
- `assets/css/ai-book.css` (three additive blocks, Phases 11–14)
- `_tooling/tests/ai-book-catalog.test.php` (new), `_tooling/tests/ai-book-graph.test.php` (new), `_tooling/tests/ai-book-ask.test.php` (new), `_tooling/tests/ai-book-pdf.test.php` (new), `_tooling/tests/ai-book-wordpress-route.test.php`
- `kohandezh-knowledge.php` (loader lines), `package.json` (`test:ai-book` chain)
- `docs/ai-book/*` handover files

## Commands to continue and test

```bash
php -l _tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-ask.php
php _tooling/tests/ai-book-artifacts.test.php
php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-wordpress-route.test.php
php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-search.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-catalog.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-graph.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-ask.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-pdf.test.php /Users/emperor/Documents/AI/AiBook
npm run test:ai-book
npm test
jq empty docs/ai-book/STATE.json
git diff --check
```

WordPress activation still requires `wp-config.php`: `KBK_FEATURE_AI_BOOK=true` and `KBK_AI_BOOK_ROOT` pointing at the canonical repo. Keep `KBK_AI_BOOK_INDEXABLE` unset/false. Ask generation needs `KBK_AI_BOOK_ASK_ENDPOINT` + `KBK_AI_BOOK_ASK_API_KEY` (optional `KBK_AI_BOOK_ASK_MODEL`); PDF request delivery needs `KBK_AI_BOOK_REQUEST_ENDPOINT` + `KBK_AI_BOOK_REQUEST_API_KEY` — never commit any of them. Personalized PDF issuance and publisher signing remain deferred and human-gated; do not invent credentials.

## Git scope

Base checkpoint for this session: `ff6884a` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`. Checkpoints so far: `b227752` (Phase 11 catalog), `8c63eec` (Phase 12 graph), `b08426a` (Phase 13 ask/RAG), Phase 14 pdf/request commit (see `git log`). Untracked `.agents/`/`skills-lock.json` in the main checkout are unrelated and excluded. No push, merge or deploy.

## Exact next task

Technical SEO review gate over `/ai-book/*`: verify the noindex-default posture end to end (pages + PDF stream), canonical `/fa/ai-book/` namespace integrity (no duplicate/competing canonicals), no fake locale pages, honest/absent structured data, and sitemap exclusion while `KBK_AI_BOOK_INDEXABLE` is unset. Then Performance gate, Final browser/accessibility QA and Production Build per sequencing. Personalized PDF issuance remains deferred. Update all handover files after the gate.

## Technical SEO review gate (2026-09-20)

**PASS** — see `docs/ai-book/SEO_REVIEW.md`. One finding fixed in-gate: SEO-1 — AI Book graph/concept views reused the shared `kbk_entity` query var, which made `KBK_Routes::is_entity_request()` classify them as Layer B (Layer B JSON-LD would print and the Layer B template filter would 404 those URLs). Fixed in `is_entity_request()` (ai-book views never enter Layer B); regression-tested both directions; Layer B site suite still green. Notes: platform robots.txt/llms.txt out of feature scope; the indexability flip (`KBK_AI_BOOK_INDEXABLE=true`) explicitly requires a future canonical/sitemap/content review first.
