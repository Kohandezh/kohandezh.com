# AI Book Current Handover

## Current state

`DESIGN_STATUS = FROZEN_REVISION_2` (unchanged, not touched this session). This session implemented and tested three phases: **Phase 11 (Catalog)**, **Phase 12 (Knowledge Graph)**, and **Phase 13 (Ask/RAG)** — the `/ai-book/ask/` view with local cited retrieval over the RAG corpus and a strictly configuration-gated answer provider. All prior subsystems are unchanged and still green. The next task is the **PDF viewer/request workflow** per `CONTENT_SOURCE_MAP.md` sequencing.

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

## Skills / Agent Capabilities

- See `docs/ai-book/SKILLS_MANIFEST.md` (reconciled 2026-09-19). No new Skills were needed for any phase this session; `MANUAL_POLICY` covered PHP type safety and SEO-safe noindex behavior.
- No successor must install an identical Skill or the generic `claude-flow`/`sparc`/`swarm` catalogue — unrelated to this project.

## Verified evidence

- `php _tooling/tests/ai-book-artifacts.test.php` → PASS
- `php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-wordpress-route.test.php` → PASS (9 rewrites, catalog/graph/ask states)
- `php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-search.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-catalog.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-graph.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-ask.test.php /Users/emperor/Documents/AI/AiBook` → PASS (new)
- `npm run test:ai-book` → PASS end to end (8 test suites + visual shell build)
- `npm test` (full existing site suite) → PASS, zero regression
- `php -l` clean on all touched PHP files
- No local WordPress install exists to browser-test end to end (gotcha 1); verified via isolated PHP unit tests consistent with prior sessions. The provider HTTP path is stubbed in tests — no live provider exists locally and none must be invented.

## Files changed this session

- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-catalog.php` (new, Phase 11)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-graph.php` (new, Phase 12)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-ask.php` (new, Phase 13)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php` (7 new validators)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php` (views: glossary index/sources/templates/concepts/graph/ask; query vars; catalog+graph+ask accessors)
- `_tooling/wp-theme/kohandezh-knowledge/templates/ai-book.php` (all new branches + Reader related wiring)
- `assets/css/ai-book.css` (two additive blocks, Phases 11–12; none for ask)
- `_tooling/tests/ai-book-catalog.test.php` (new), `_tooling/tests/ai-book-graph.test.php` (new), `_tooling/tests/ai-book-ask.test.php` (new), `_tooling/tests/ai-book-wordpress-route.test.php`
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
npm run test:ai-book
npm test
jq empty docs/ai-book/STATE.json
git diff --check
```

WordPress activation still requires `wp-config.php`: `KBK_FEATURE_AI_BOOK=true` and `KBK_AI_BOOK_ROOT` pointing at the canonical repo. Keep `KBK_AI_BOOK_INDEXABLE` unset/false. Ask generation additionally needs `KBK_AI_BOOK_ASK_ENDPOINT` + `KBK_AI_BOOK_ASK_API_KEY` (never commit them). Request delivery, personalized PDF and publisher signing remain configuration-required; do not invent credentials.

## Git scope

Base checkpoint for this session: `ff6884a` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`. Checkpoints so far: `b227752` (Phase 11 catalog), `8c63eec` (Phase 12 graph), Phase 13 ask commit (see `git log`). Untracked `.agents/`/`skills-lock.json` in the main checkout are unrelated and excluded. No push, merge or deploy.

## Exact next task

PDF viewer/request workflow per `docs/ai-book/CONTENT_SOURCE_MAP.md`: `/ai-book/pdf/` viewer over `release/06_book_fa.pdf` (honest degradation where the file/config is absent; no third-party cloud embedding that leaks reader data) and `/ai-book/request-pdf/` request workflow (bounded, storage-free, configuration-required delivery — no invented credentials; explicit consent checkbox honored). Personalized PDF remains deferred. Then Security → SEO → Performance gates per sequencing; update all handover files after the phase.
