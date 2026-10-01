# Local audit remediation checkpoint — 2026-09-29

## Scope and completion

Single-agent local work; no push/merge/deploy, production changes, provider configuration, corpus edits or new Opus prompt. Existing user changes preserved. UI design not restyled.

Active checkout: `/Users/emperor/Documents/AI/kohandezh.com/.claude/worktrees/kohandezh-reader-data-phase-9-8f7e49`.
Branch: `claude/kohandezh-reader-data-phase-9-8f7e49`.

Restored the existing Docker Desktop daemon using its documented CLI; initial 45-second startup command timed out but subsequent Docker inventory and HTTP checks confirmed recovery. Existing WordPress/database containers and persistent volumes reused. Verified theme/plugin mounts point to this worktree; canonical corpus mount remains read-only.

Completed Search pagination in PHP engine, request adapter and WordPress template. Accessible previous/next links retain the search query. Out-of-range pages clamp to the actual last page. Glossary promotion moves an item in the complete ranking rather than overwriting a result; no lost or duplicate matches across pages. Empty/unavailable responses retain page metadata. Tests include full result enumeration for free-text and ID-prefix searches and malformed page inputs.

## Verification evidence

- `npm run test:ai-book`: PASS (artifact, repository, routes, Reader, Search, catalog, graph, Ask, PDF and static-shell generator).
- `npm run test:release`: PASS (site links, frontend, names, right-click behavior, publication and release package checks). This is not a production deployment/build attestation.
- Actual HTTP/HTML check: query `حاکمیت` returns 158 matches; page 1 has 20 results and next link; page 2 has 20 results and previous/next links; requested page 9999 clamps to page 8 with 18 results and previous link. All returned HTTP 200. Links use `/fa/books/ai-governance/search/` and retain the query.
- Browser QA: BLOCKED. Two cua_repl attempts failed on browser webview attachment/timeouts. No screenshot, visual pass or mobile pass claimed.
- Fresh 13-route HTTP smoke: 11 return 200; `cite/` and `verify/` return 404. HTTP 200 alone is not functional acceptance (Ask/PDF request remain provider-gated).
- Final targeted Search regression rerun and template PHP lint: PASS. Whitespace diff check: PASS.
- Existing audit remains the source of unresolved findings: `READER_AUDIT_2026-09-27.md`.

## Files changed in this batch

Runtime/test: `class-kbk-ai-book-search.php`, `class-kbk-ai-book.php`, `templates/ai-book.php`, `ai-book-search.test.php`. Plus checkpoint documentation. Other dirty files predate this batch; the shell generator is part of the existing test command. Do not bulk-stage them.

Pre-checkpoint working-tree snapshot: 52 dirty/untracked entries; tracked diff 42 files, 1845 insertions, 161 deletions. These totals include earlier work, not just this batch. No local commit created.

## Remaining priorities

1. Real cite/verify routes: routing currently lacks these views, despite generated visual shells. Implement against validated citation/artifact data; distinguish hashes, provenance and cryptographic signatures and never fabricate authenticity success.
2. Browser verification of this pagination, then meaningful Search titles and clean bounded snippets; Ask passage wording and deduplication.
3. Map figure placeholders to real source assets. Do not generate substitute scientific diagrams.
4. Missing glossary definitions and contradictory approval-status presentation.
5. Reader small-screen toolbar, whole-book navigation, heading hierarchy and accessibility.
6. Persian calendar/timezone consistency, PDF usability/performance, factual SEO/GEO metadata and regression review.
7. Owner review; new Opus handover prompt only when owner approves. Provider setup and production remain deferred.

## Exact next local task

From the checkout above, inspect existing citation and integrity contracts and `KBK_AI_Book::rewrite_rules()` / view allowlist / template. Add failing route tests for `/fa/books/ai-governance/cite/` and `/verify/` before implementing honest data-backed views. Keep canonical data read-only and test each batch. Reconnect cua_repl for actual visual verification; do not claim HTTP parsing is visual QA.
