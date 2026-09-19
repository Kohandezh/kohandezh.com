# AI Book Current Handover

## Current state

`DESIGN_STATUS = FROZEN_REVISION_2` (unchanged, not touched this session). The WordPress reader now performs allowlisted, repository-backed dynamic part/chapter/section routing — all 53 chapters across all 7 parts are reachable, not just P01/C01. Do not restart discovery or redesign the frozen UI. The next task is **Search** (see `docs/ai-book/CONTENT_SOURCE_MAP.md`'s Search row).

Canonical book root is `/Users/emperor/Documents/AI/AiBook` (read-only). Do not read `.env` or private signing keys. Current edition `2026E1` is development-signed, not publisher-signed.

## What changed this session (Phase 9: Reader Data Integration)

- `KBK_AI_Book_Repository::find_chapter()` now matches by stable `chapter_id` first, falls back to slug only when the slug is unambiguous within the part, and returns `null` (fail closed, never guesses) on an ambiguous slug. Resolves the P06 (`C43`/`C44`/`C45` share one truncated slug) collision — see **ADR-AB-0009** in `DECISIONS.md`, which also closes out the paired `/knowledge/` ownership question (no real collision: that's an unrelated pre-existing Layer B CPT archive).
- Added `KBK_AI_Book_Repository::adjacent_chapters()` for stable prev/next navigation within a part.
- `KBK_AI_Book` gained `kbk_part` / `kbk_chapter` / `kbk_section` public query vars, an `IDENTIFIER_PATTERN` allowlist (`requested_identifier()`), and `current_reader_selection()`, which resolves the current `/ai-book/read/` request against the repository with documented fail-closed rules (unknown part/chapter never guesses; a cross-chapter `section` query var is silently ignored rather than erroring).
- `templates/ai-book.php` now renders whichever part/chapter is resolved (previously hardcoded to P01/C01), plus a breadcrumb (`.ab-breadcrumb`) and prev/next chapter nav (`.ab-prev-next`) — both CSS classes already existed in the frozen stylesheet, unused until now, so no CSS/JS changed. A "part not found" and a "chapter not found, falling back" state render instead of guessing content.
- New test `_tooling/tests/ai-book-reader-routing.test.php`: default selection, explicit valid part+chapter, unknown part, unknown chapter in a known part, the P06 ambiguous-slug fail-closed path (through the public query-var route, not just the repository unit test), a resolving section deep link, a cross-chapter section query var being ignored, a malformed/oversized identifier never reaching the repository, and `adjacent_chapters()`.
- Extended `_tooling/tests/ai-book-repository.test.php` with direct P06 ID-vs-slug and `adjacent_chapters()` coverage.
- Fixed `package.json`'s `test:ai-book` script: it hardcoded a relative `../AiBook` path that only resolved when run from the un-nested repo root; it now uses `${KBK_AI_BOOK_ROOT:-/Users/emperor/Documents/AI/AiBook}` (still overridable, correct by default from a worktree) and runs the new reader-routing test.
- Reconciled `SKILLS_MANIFEST.md` for this session's actual environment (see that file's header note) — no architecture change, just accurate tooling records.

## Skills / Agent Capabilities

- See `docs/ai-book/SKILLS_MANIFEST.md` — reconciled for this session (Claude Sonnet 5, 2026-09-19). Active: `uiux-pro-max` for design/accessibility; built-in browser pane in place of the prior session's `browse` binary; `MANUAL_POLICY` for type safety, SEO and handoff. Deferred: `cso`/`security-review`, `anthropic-skills:pdf`.
- No successor must install an identical Skill or the large generic `claude-flow`/`sparc`/`swarm` plugin catalogue visible in some sessions — it is unrelated to this project and was deliberately not activated.

## Verified evidence

- `php _tooling/tests/ai-book-artifacts.test.php` → PASS
- `php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook` → PASS (now includes P06 + adjacent_chapters coverage)
- `php _tooling/tests/ai-book-wordpress-route.test.php` → PASS (unchanged, still exercises hooks/rewrites/allowlist/noindex/config-state/assets/template)
- `php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook` → PASS (new)
- `npm run test:ai-book` → PASS end to end, including the static visual-shell rebuild
- `npm test` (full existing site suite) → PASS, 394 crawler assertions + release/publication/sitemap tests, **zero regression**
- `php -l` clean on all touched PHP files
- No local WordPress install exists to browser-test `/ai-book/read/?kbk_part=...&kbk_chapter=...` end to end (per gotcha 1, `dev-server.php` has no WP core/rewrite engine); verified instead via isolated PHP unit tests that stub the exact WP functions this code calls (`get_query_var`, `add_rewrite_rule`, etc.), consistent with how the prior session verified the route/hook layer.

## Files changed this session

- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php`
- `_tooling/wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php`
- `_tooling/wp-theme/kohandezh-knowledge/templates/ai-book.php`
- `_tooling/tests/ai-book-repository.test.php`
- `_tooling/tests/ai-book-reader-routing.test.php` (new)
- `package.json` (`test:ai-book` script path fix + new test wired in)
- `docs/ai-book/SKILLS_MANIFEST.md`, `DECISIONS.md`, `TODO.md`, `STATE.json`, this file

## Commands to continue and test

```bash
php -l _tooling/wp-theme/kohandezh-knowledge/templates/ai-book.php
php _tooling/tests/ai-book-artifacts.test.php
php _tooling/tests/ai-book-repository.test.php /Users/emperor/Documents/AI/AiBook
php _tooling/tests/ai-book-wordpress-route.test.php
php _tooling/tests/ai-book-reader-routing.test.php /Users/emperor/Documents/AI/AiBook
npm run test:ai-book
npm test
jq empty docs/ai-book/STATE.json
git diff --check
```

No environment variables are required for the static visual shell or for any of the PHP unit tests above (the repository test/reader-routing test take the AiBook path as an argument). WordPress reader activation still requires explicit local configuration in `wp-config.php`: `KBK_FEATURE_AI_BOOK=true` and `KBK_AI_BOOK_ROOT` pointing to the canonical repo outside the public root. Keep `KBK_AI_BOOK_INDEXABLE` unset/false until canonical and duplicate-content review passes. Ask runtime, request delivery/SMS/email, personalized PDF and publisher signing remain configuration-required; do not invent credentials.

## Git scope

Base checkpoint for this session was `main@4fb19d0` (already includes the prior session's artifact-boundary/repository/WP-route work). Pre-existing untracked `.agents/` and `skills-lock.json`, if present, are unrelated and must remain excluded. No push, merge or deploy.

## Exact next task

Implement Search (`/ai-book/search/`, already visually frozen) against `html/search/index.json` / `master/book.json` per `CONTENT_SOURCE_MAP.md`'s Search row: build a bounded Persian/English index (title, excerpt, hierarchy, origin, source, canonical anchor), never serve the raw master/corpus. Cover it with an isolated PHP test the same way the reader was. Do not begin Ask/RAG or the Knowledge Graph until Search is verified, per `NEXT_AGENT_PROMPT.md`'s sequencing.
