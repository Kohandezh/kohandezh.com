# AI Book Current Handover

## Current state

`DESIGN_STATUS = FROZEN_REVISION_2` (unchanged, not touched this session). This session implemented and tested two phases: **Phase 11 (Catalog)** — glossary index, sources, templates, concepts — and **Phase 12 (Knowledge Graph)** — the bounded `/ai-book/graph/` explorer plus Related Concepts/Related Sections wired into the Reader. Reader, Search and Catalog are unchanged and still green. The next task is **Ask/RAG** (cited, configuration-gated) per `CONTENT_SOURCE_MAP.md` sequencing.

Canonical book root is `/Users/emperor/Documents/AI/AiBook` (read-only). Do not read `.env` or private signing keys. Current edition `2026E1` is development-signed, not publisher-signed. All work happens in the linked worktree `.claude/worktrees/kohandezh-reader-data-phase-9-8f7e49` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`.

## What changed this session (Phase 11: Catalog — see git `b227752`)

- New `KBK_AI_Book_Catalog` (`includes/class-kbk-ai-book-catalog.php`): one fail-closed `load()` validating glossary/sources manifest/bibliography/source map/templates/entities before any view reads them; hand-built bounded read models only. Glossary index sorted by Persian preferred term (safe keys only — reviewer secrets structurally absent, tested); sources = 18 audited documents with bibliography merge and per-doc section counts summing to exactly 660; templates = 20 cards + fail-closed details (own origin label `editorial_template_ir`, distinct from the three content origins); concepts = 3,671 entities, type filter (20 types), 60/page pagination with clamping, entity details with mentions resolved into Reader links capped at 12 and the raw mention list never exposed.
- Five new validators (`validate_sources_manifest/bibliography/source_map/template/entity`), `kbk_template/kbk_entity/kbk_type/kbk_page` query vars (strict allowlists), views `sources`/`templates`/`concepts` + full glossary index, one additive token-based CSS block (`.ab-term-list`), new `ai-book-catalog.test.php` (8 blocks) + extended route test.

## What changed this session (Phase 12: Knowledge Graph)

- New `KBK_AI_Book_Graph` (`includes/class-kbk-ai-book-graph.php`) over `knowledge/graph.json` (4.8MB: 3,671 nodes, 10,474 edges, 17 relation types; no dangling endpoints — validator-enforced). The graph artifact is loaded **lazily** — only `/ai-book/graph/` views trigger it; the Reader wiring deliberately does NOT load it (ADR-AB-0012).
- `/ai-book/graph/` overview: honest stats + relation legend (`.ab-term-list` reuse) + pointer to the concepts list. Node view (`?kbk_entity=E:…`): safe identity model, **depth-1 only** neighborhood capped at 24 edges (`MAX_NEIGHBORHOOD_EDGES`) with an honest total, deterministic SVG ring visualization capped at 12 nodes (`MAX_VIZ_NODES`, inline positioned, existing frozen `.ab-graph-canvas`/`.ab-node` styling, `role="img"` with the accessible list as the real reference), evidence quotes bounded at 160 chars, every anchor and endpoint resolved through the repository (fail-safe when absent).
- **Reader wiring**: `related_map(array $content_ids)` computes per-section Related Concepts (≤6, ranked by global mention count then ID — deterministic) and Related Sections (≤4, co-mention frequency over the section's top-25 concept pool, self and same-view siblings excluded, repository-resolved) in ONE bounded scan over the catalog's validated entities. Rendered per section in the read view via the existing `$read_link`/entity links; sections without concepts render no block (honest).
- New validator `validate_graph()` (edition match, node reuse of `validate_entity`, edge endpoints must exist, relation must be declared, content IDs of this edition, origin `{source, editorial}`, evidence quote ≤200 / block_id ≤128). Corrupted graph fails closed WITHOUT corrupting the catalog (tested).
- `KBK_AI_Book`: `graph` view + 8th rewrite, `graph()`/`graph_status()` (CONFIG_REQUIRED / ARTIFACT_INVALID degradation), node lookup reuses the strict `kbk_entity` allowlist.
- `assets/css/ai-book.css`: one additive token-based block (`.ab-related-inline` + node truncation).
- New test `_tooling/tests/ai-book-graph.test.php` (7 blocks, real canonical data): stats, safe node model, bounded repository-resolvable neighborhoods, related wiring caps + sibling exclusion on a real coverage-selected chapter, 8 validator tamper fixtures, 4 corrupted-graph fail-closed paths, route-layer hygiene. Route test extended to 8 rewrites + graph view + CONFIG_REQUIRED states. Wired into `test:ai-book`.

## Skills / Agent Capabilities

- See `docs/ai-book/SKILLS_MANIFEST.md` (reconciled 2026-09-19). No new Skills were needed for either phase; `MANUAL_POLICY` covered PHP type safety and SEO-safe noindex behavior.
- No successor must install an identical Skill or the generic `claude-flow`/`sparc`/`swarm` catalogue — unrelated to this project.

## Verified evidence

- `php _tooling/tests/ai-book-artifacts.test.php` → PASS
- `php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-wordpress-route.test.php` → PASS (8 rewrites, catalog/graph query vars)
- `php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-search.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-catalog.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-graph.test.php /Users/emperor/Documents/AI/AiBook` → PASS (new)
- `npm run test:ai-book` → PASS end to end (7 test suites + visual shell build)
- `npm test` (full existing site suite) → PASS, zero regression
- `php -l` clean on all touched PHP files
- No local WordPress install exists to browser-test end to end (gotcha 1); verified via isolated PHP unit tests consistent with prior sessions. The static visual shells were already Chromium-verified in the design freeze (21 checks).

## Files changed this session

- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-catalog.php` (new, Phase 11)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-graph.php` (new, Phase 12)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php` (6 new validators)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php` (views: glossary index/sources/templates/concepts/graph; query vars; catalog+graph accessors)
- `_tooling/wp-theme/kohandezh-knowledge/templates/ai-book.php` (all new branches + Reader related wiring)
- `assets/css/ai-book.css` (two additive blocks; propagates to the plugin copy at build time)
- `_tooling/tests/ai-book-catalog.test.php` (new), `_tooling/tests/ai-book-graph.test.php` (new), `_tooling/tests/ai-book-wordpress-route.test.php`
- `kohandezh-knowledge.php` (loader lines), `package.json` (`test:ai-book` chain)
- `docs/ai-book/*` handover files

## Commands to continue and test

```bash
php -l _tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-graph.php
php _tooling/tests/ai-book-artifacts.test.php
php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-wordpress-route.test.php
php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-search.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-catalog.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-graph.test.php /Users/emperor/Documents/AI/AiBook
npm run test:ai-book
npm test
jq empty docs/ai-book/STATE.json
git diff --check
```

WordPress activation still requires `wp-config.php`: `KBK_FEATURE_AI_BOOK=true` and `KBK_AI_BOOK_ROOT` pointing at the canonical repo. Keep `KBK_AI_BOOK_INDEXABLE` unset/false. Ask runtime, request delivery, personalized PDF and publisher signing remain configuration-required; do not invent credentials.

## Git scope

Base checkpoint for this session: `ff6884a` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`. Checkpoints so far: `b227752` (Phase 11 catalog), Phase 12 graph commit (see `git log`). Untracked `.agents/`/`skills-lock.json` in the main checkout are unrelated and excluded. No push, merge or deploy.

## Exact next task

Cited Ask/RAG integration per `docs/ai-book/CONTENT_SOURCE_MAP.md`: bounded retrieval over `release/*rag*`/`knowledge/chunks.jsonl` with citation-enforced answers (every claim anchored to a content ID resolvable via the repository), provider strictly configuration-required (no invented credentials), honest CONFIG_REQUIRED/unavailable states, no raw chunk dumps. Then the PDF viewer/request workflow. Per `NEXT_AGENT_PROMPT.md` sequencing; update all handover files after the phase.
