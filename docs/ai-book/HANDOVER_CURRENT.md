# AI Book Current Handover

## Current state

`DESIGN_STATUS = FROZEN_REVISION_2` (unchanged, not touched this session). The Search subsystem is implemented and tested: `/ai-book/search/` is now a live WordPress view backed by `KBK_AI_Book_Search`, plus a minimal `/ai-book/glossary/` deep-link target for glossary result cards. Do not restart discovery or redesign the frozen UI. The next task is **Concepts/Glossary/Sources/Templates** data integration per `docs/ai-book/CONTENT_SOURCE_MAP.md`, then the Knowledge Graph.

Canonical book root is `/Users/emperor/Documents/AI/AiBook` (read-only). Do not read `.env` or private signing keys. Current edition `2026E1` is development-signed, not publisher-signed. All work happens in the linked worktree `.claude/worktrees/kohandezh-reader-data-phase-9-8f7e49` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`.

## What changed this session (Phase 10: Search)

- New `KBK_AI_Book_Search` (`includes/class-kbk-ai-book-search.php`): in-memory normalized linear scan over the pipeline's purpose-built bounded index `html/search/index.json` (479 entries — one per canonical section) plus the approved glossary `release/09_glossary.json` (435 terms). **No Elasticsearch/OpenSearch** — the corpus is tiny; PHP scan is the simplest architecture-compatible solution (ADR-AB-0010).
- Searches: section/Persian titles, English titles, Persian body text (via the pipeline's keyword fields + bounded snippet), English terms, acronyms, source document IDs, content/structural IDs (exact, case-insensitive, and prefix modes), part titles (dedicated part cards), chapter titles (dedicated chapter cards), and glossary (Persian preferred term, English term, acronym, approved alternatives, definitions, concept ID). Rejected glossary aliases participate in matching **never**; they are editorial decisions, not content.
- Persian normalization shared by query and index: Arabic yeh/kaf/hamza forms → Persian, Persian/Arabic-Indic digits → ASCII, diacritics/tatweel/ZWNJ/directional marks stripped, case-folded ASCII.
- Results are bounded view models: title, snippet segments (`{text, mark}` — the template escapes text and owns the `<mark>` markup, so highlighting cannot inject HTML), part/chapter hierarchy, origin label (all three editorial layers preserved), source doc IDs (capped at 3), content ID, external canonical citation URL, and identifiers that resolve to a stable Reader deep link (`/ai-book/read/?kbk_part=…&kbk_chapter=…&kbk_section=…#…`). Glossary cards deep-link to `/ai-book/glossary/?kbk_concept=…`.
- Ranking: AND token semantics, weighted fields (title 10 > acronym 8 > title_en 7 > keywords/docs 4 > chapter 3 > part 2 > body 1–3), exact-phrase bonuses for part (+70)/chapter (+55)/section titles (+4), glossary exact preferred-term equality 14 vs containment 12. **Glossary representation rule:** when matches overflow the page and no glossary card survived the top-N slice, the best glossary match takes the last slot — dictionary hits can never be crowded out entirely.
- Safety contracts (all tested): queries sanitized (NUL/control chars stripped, invalid UTF-8 downgraded to printable ASCII, 80-char engine cap / 120-char route cap); results ≤ limit (default 20, hard max 50); section results carry ONLY the pipeline-authored bounded snippet (≤2000 chars, validator-enforced) — never `fa_text`; glossary results never expose `reviewer`/`review_mode`/`rationale`/`rejected_fa`; corrupted/mismatched artifacts fail closed to `ARTIFACT_INVALID` with an honest empty state; missing configuration degrades to `CONFIG_REQUIRED`.
- `KBK_AI_Book_Artifacts` gained `validate_search_index()` and `validate_glossary()` (strict shapes, edition matching, canonical-URL fragment checks, snippet bound, status allowlist `approved|proposed|english_only`).
- `KBK_AI_Book`: `search` + `glossary` views (rewrite rules, view allowlist), `kbk_q` / `kbk_concept` query vars, `requested_search_query()` (sanitized free text — queries are text, not identifiers), `requested_concept()` (strict allowlist), `search_engine()`/`search_status()`/`current_search()`/`current_glossary_term()`, all fail-closed like the reader.
- `templates/ai-book.php`: search view (frozen hero/form copy, guidance / no-result / results states, result cards with type-specific meta and highlighted segments) and the minimal glossary term view (breadcrumb, term, definition, alternatives, sources, concept ID, status label; unknown term → fail-closed not-found; no term → pointer to search). Additive helpers `$render_segments`/`$read_link`.
- `assets/css/ai-book.css`: ONE additive token-based rule (`.ab-results mark` using the frozen `--ab-accent`/`--ab-ink` pair, same accent/ink treatment as `.ab-brand-mark`). This is not a design change; the frozen search shell itself promises highlighted matches. No other CSS/JS touched. Note: `build_visual_shell.py` copies this source file over the plugin's `assets/ai-book.css` on every build — edit the source, never the copy.
- `KBK_AI_Book_Repository::root()` public accessor added (search reads its default artifact paths from the validated root).
- New test `_tooling/tests/ai-book-search.test.php` (15 blocks, real canonical data): Persian query (سوگیری) with mandatory highlight, Persian AND query, English query, acronym query with visible RMF evidence, glossary query surfacing RISK with secrets-absence assertions, source-doc query, exact structural/content-ID resolution (case-insensitive) + P06 prefix mode, part/chapter title cards, honest no-result state, six hostile/malformed inputs, bounded-output/no-body-leak checks, repository-resolvable deep links for every result, `find_term` deep-link resolution, corrupted-artifact fail-closed, 8 validator tamper fixtures, and route-layer query hygiene.
- Extended `_tooling/tests/ai-book-wordpress-route.test.php`: 4 rewrite rules, search/glossary view detection, `CONFIG_REQUIRED` search state, query-var sanitization/allowlist tests. Reader-routing test just gained the search-class require (no behavior change).
- `package.json`: search test wired into `test:ai-book`.

## Skills / Agent Capabilities

- See `docs/ai-book/SKILLS_MANIFEST.md` (reconciled 2026-09-19). No new Skills were needed for Search; `MANUAL_POLICY` covered PHP type safety and SEO-safe noindex behavior.
- No successor must install an identical Skill or the generic `claude-flow`/`sparc`/`swarm` catalogue — unrelated to this project.

## Verified evidence

- `php _tooling/tests/ai-book-artifacts.test.php` → PASS
- `php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-wordpress-route.test.php` → PASS (extended)
- `php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook` → PASS
- `php _tooling/tests/ai-book-search.test.php /Users/emperor/Documents/AI/AiBook` → PASS (new)
- `npm run test:ai-book` → PASS end to end
- `npm test` (full existing site suite) → PASS, zero regression
- `php -l` clean on all touched PHP files
- No local WordPress install exists to browser-test the search page end to end (gotcha 1); verified via isolated PHP unit tests consistent with prior sessions. The static visual search shell was already Chromium-verified in the design freeze (21 checks).

## Files changed this session

- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-search.php` (new)
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php`
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php`
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php`
- `_tooling/wp-theme/kohandezh-knowledge/templates/ai-book.php`
- `assets/css/ai-book.css` (one additive rule; propagates to the plugin copy at build time)
- `_tooling/tests/ai-book-search.test.php` (new)
- `_tooling/tests/ai-book-wordpress-route.test.php`, `_tooling/tests/ai-book-reader-routing.test.php`
- `kohandezh-knowledge.php` (loader line for the search class)
- `package.json` (`test:ai-book` chain)
- `docs/ai-book/*` handover files

## Commands to continue and test

```bash
php -l _tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-search.php
php _tooling/tests/ai-book-artifacts.test.php
php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-wordpress-route.test.php
php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-search.test.php /Users/emperor/Documents/AI/AiBook
npm run test:ai-book
npm test
jq empty docs/ai-book/STATE.json
git diff --check
```

WordPress activation still requires `wp-config.php`: `KBK_FEATURE_AI_BOOK=true` and `KBK_AI_BOOK_ROOT` pointing at the canonical repo. Keep `KBK_AI_BOOK_INDEXABLE` unset/false. Ask runtime, request delivery, personalized PDF and publisher signing remain configuration-required; do not invent credentials.

## Git scope

Base checkpoint for this session: `4c1c998` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`. Untracked `.agents/`/`skills-lock.json` in the main checkout are unrelated and excluded. No push, merge or deploy.

## Exact next task

Concepts/Glossary/Sources/Templates data integration per `docs/ai-book/CONTENT_SOURCE_MAP.md`: enrich the glossary view into real term browsing (per-term data from the validated glossary, still no reviewer secrets), build the Sources page from `manifests/sources.json`/`master/source_map.json`/`master/bibliography.json` (exactly 18 documents), Templates from `templates/TPL-*.json` (bounded cards, no bulk export), and Concepts from `knowledge/entities.jsonl` (filtered/paginated read models). Then the bounded Knowledge Graph explorer and wiring Related Concepts/Related Sections into the Reader. Do not begin Ask/RAG or PDF integration until those are verified, per `NEXT_AGENT_PROMPT.md` sequencing.
