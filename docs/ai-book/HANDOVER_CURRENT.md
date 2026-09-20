# AI Book Current Handover

## Current state

`DESIGN_STATUS = FROZEN_REVISION_2` (unchanged, not touched this session). The Catalog subsystem is implemented and tested: `/ai-book/glossary/` is now a full term index (plus per-term deep-link view), and `/ai-book/sources/`, `/ai-book/templates/` and `/ai-book/concepts/` are live WordPress views backed by the new `KBK_AI_Book_Catalog`. Search and Reader are unchanged and still green. The next task is the **bounded Knowledge Graph explorer** and wiring Related Concepts/Related Sections into the Reader.

Canonical book root is `/Users/emperor/Documents/AI/AiBook` (read-only). Do not read `.env` or private signing keys. Current edition `2026E1` is development-signed, not publisher-signed. All work happens in the linked worktree `.claude/worktrees/kohandezh-reader-data-phase-9-8f7e49` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`.

## What changed this session (Phase 11: Catalog — Concepts/Glossary/Sources/Templates)

- New `KBK_AI_Book_Catalog` (`includes/class-kbk-ai-book-catalog.php`): one fail-closed `load()` that validates every catalog artifact before any view may read it; any corruption flips the whole catalog to `ARTIFACT_INVALID` with empty read models (no partial data — tested). Read models are hand-built field by field.
- `glossary_terms()`: all approved terms (435+) from `release/09_glossary.json` sorted by Persian preferred term; safe key set only (`concept_id, preferred_fa, english, acronym, status, domain`) — reviewer metadata (`reviewer/review_mode/rationale/rejected_fa`) can never leak (tested).
- `sources()`: the 18 audited documents from `manifests/sources.json` merged with `master/bibliography.json` (organization, publication type/status/date, DOI URL) plus per-document section counts computed from `master/source_map.json` (sum must equal 660 — tested). Manifest↔bibliography and source_map↔book cross-checks fail closed.
- `templates()/template()`: 20 editorial template cards (`templates/TPL-*.json`) sorted by ID; detail view exposes `title_fa/note_fa/fields(name_fa,name_en,description_fa,example_fa)/derived_from(capped 3)/origin`; unknown IDs fail closed to null. Template validator enforces its own origin allowlist — `editorial_template_ir` is a template-provenance label, deliberately distinct from the three content origins.
- `entity_types()/entities()/entity()`: filtered read models over `knowledge/entities.jsonl` (3,671 entities, 20 types). List pages: 60/page (`ENTITIES_PER_PAGE`), page numbers clamped into range, unknown type → honest empty page. Type filter + pagination preserved across links. Entity detail: labels, origin (`source|editorial` allowlist), documents (capped 3), honest `mentions_total`, `definition_en`, and mentions resolved through `KBK_AI_Book_Repository::find_section()` into Reader deep links capped at 12 (`MAX_ENTITY_MENTIONS`) — the raw mention list never leaves the class (tested).
- `KBK_AI_Book_Artifacts` gained `validate_sources_manifest()`, `validate_bibliography()`, `validate_source_map()`, `validate_template()`, `validate_entity()` (strict shapes; entity mentions must be real content IDs of the active edition; sha256 hex; template ID pattern `TPL-P\d{2}-\d{1,2}`).
- `KBK_AI_Book`: `sources` + `templates` + `concepts` views (rewrite rules — now 7 total — and view allowlist), `kbk_template`/`kbk_entity`/`kbk_type`/`kbk_page` query vars, `requested_template()` (strict `TPL-P\d{2}-\d{1,2}` allowlist), `requested_entity()` (strict `E:<Type>:<slug>` allowlist), `requested_entity_type()`, `requested_page()` (digits only, falls back to 1), `catalog()`/`catalog_status()` with `CONFIG_REQUIRED` degradation.
- `templates/ai-book.php`: glossary index view (term grid with Persian term + Latin acronym/English counterpart), sources card list (18 non-link articles with provenance meta + sha256 prefix + optional DOI link), templates index + detail (field layers), concepts type-filtered paginated card list + entity detail (mention section cards resolving to stable Reader links via the existing `$read_link` helper). Unknown type/page → honest not-found; catalog invalid/config-missing → honest data-status hero.
- `assets/css/ai-book.css`: ONE additive token-based block (`.ab-term-list` grid, panel/line/accent tokens only) for the glossary term grid. Propagates to the plugin copy at build time — edit the source, never the copy.
- New test `_tooling/tests/ai-book-catalog.test.php` (8 blocks, real canonical data): sorted safe glossary list with secrets-absence assertion, 18 sources with 660-count reconciliation, 20 templates with fail-closed detail, entity type counts (Concept=696)/pagination/clamping, entity detail with repository-resolved bounded mentions, 10 validator tamper fixtures, six corrupted-artifact fail-closed paths on a temp artifact root (sources/bibliography/source-map desync/glossary/entities/templates), and route-layer query-var hygiene.
- Extended `_tooling/tests/ai-book-wordpress-route.test.php`: 7 rewrite rules, sources/templates/concepts view detection, `CONFIG_REQUIRED` catalog state, template/entity/type/page allowlist tests.
- `package.json`: catalog test wired into `test:ai-book`.

## Skills / Agent Capabilities

- See `docs/ai-book/SKILLS_MANIFEST.md` (reconciled 2026-09-19). No new Skills were needed for Search; `MANUAL_POLICY` covered PHP type safety and SEO-safe noindex behavior.
- No successor must install an identical Skill or the generic `claude-flow`/`sparc`/`swarm` catalogue — unrelated to this project.

## Verified evidence

- `php _tooling/tests/ai-book-artifacts.test.php` → PASS
- `php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-wordpress-route.test.php` → PASS (extended: 7 rewrites, catalog query vars)
- `php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-search.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-catalog.test.php /Users/emperor/Documents/AI/AiBook` → PASS (new)
- `npm run test:ai-book` → PASS end to end (now includes the catalog test)
- `npm test` (full existing site suite) → PASS, zero regression
- `php -l` clean on all touched PHP files
- No local WordPress install exists to browser-test the catalog pages end to end (gotcha 1); verified via isolated PHP unit tests consistent with prior sessions. The static visual shells for sources/templates/concepts/glossary were already Chromium-verified in the design freeze (21 checks).

## Files changed this session

- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-catalog.php` (new)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php` (5 new validators)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php` (3 views, 4 query vars, catalog accessors)
- `_tooling/wp-theme/kohandezh-knowledge/templates/ai-book.php` (glossary index + sources + templates + concepts branches)
- `assets/css/ai-book.css` (one additive block; propagates to the plugin copy at build time)
- `_tooling/tests/ai-book-catalog.test.php` (new)
- `_tooling/tests/ai-book-wordpress-route.test.php`
- `kohandezh-knowledge.php` (loader line for the catalog class)
- `package.json` (`test:ai-book` chain)
- `docs/ai-book/*` handover files

## Commands to continue and test

```bash
php -l _tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-catalog.php
php _tooling/tests/ai-book-artifacts.test.php
php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-wordpress-route.test.php
php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-search.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-catalog.test.php /Users/emperor/Documents/AI/AiBook
npm run test:ai-book
npm test
jq empty docs/ai-book/STATE.json
git diff --check
```

WordPress activation still requires `wp-config.php`: `KBK_FEATURE_AI_BOOK=true` and `KBK_AI_BOOK_ROOT` pointing at the canonical repo. Keep `KBK_AI_BOOK_INDEXABLE` unset/false. Ask runtime, request delivery, personalized PDF and publisher signing remain configuration-required; do not invent credentials.

## Git scope

Base checkpoint for this session: `ff6884a` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`. Untracked `.agents/`/`skills-lock.json` in the main checkout are unrelated and excluded. No push, merge or deploy.

## Exact next task

Bounded Knowledge Graph explorer per `docs/ai-book/CONTENT_SOURCE_MAP.md`: build `/ai-book/graph/` over `knowledge/graph.json` (3,671 nodes = safe entity models, 10,474 edges `{source, relation, target, content_id, origin, evidence}`, 17 relation types) with strict bounds (no bulk export, capped neighborhoods, no bulk JSON dumps), then wire Related Concepts/Related Sections into the Reader section view. Do not begin Ask/RAG or PDF integration until the graph explorer is verified, per `NEXT_AGENT_PROMPT.md` sequencing.
