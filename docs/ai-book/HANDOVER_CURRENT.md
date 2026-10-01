# AI Book Current Handover

## Latest checkpoint — 2026-09-29

Local audit remediation only. Read `AUDIT_REMEDIATION_2026-09-29.md` before older status below. Search now has lossless server-rendered pagination, query preservation, safe page clamping and regression coverage. Local Docker preview is running on port 8890 with the existing worktree mounts. Full AI Book and release regression suites passed. Visual QA remains unverified because the browser webview could not attach. No push, merge, deployment, provider configuration or canonical content changes. No new Opus handover prompt has been authored; owner requested approval first.

Skills / Agent Capabilities: existing `investigate` and `uiux-pro-max` instructions informed root-cause-first and accessible-control checks; PHP type safety remained MANUAL_POLICY. No installations or global skill/tool changes. Work is single-agent. Next priority: real cite/verify routes, then search presentation and figure resolution. Existing historical completion/freezing claims are not proof of current functionality.

## Current state

**Latest: REVISION_9_OWNER_REVIEW (2026-09-26).** Local presentation and link fixes supersede the historical state below. Front/back WebP covers, verified QR, shared logo.webp, related-reading lists and LTR English references implemented. Old known canonical paths redirect locally with 301; source corpus remains immutable. The public book landing still returns 404: nothing deployed. A real local blog post is linked from the landing and news view. See BOOK_PRESENTATION_REVIEW.md for assets, reproduction and verification. Both test:release and test:ai-book pass. Next: owner visual review; no push/merge/deploy.

**Latest: revision 8, local owner-review candidate.** Reader now defaults to a distraction-free viewport workspace (not browser Fullscreen API). Shared headers/footers hide until “بازگشت به سایت”; text remains unboxed. Sticky controls expose A−/A+ (16–26px), 760/1100px measure, three line spacings, light/dark/paper, reset and chapter disclosure. Validated preferences persist in `kbk-reader-preferences`; shared light/dark still uses `darkMode`. No canonical text edited. Source CSS/JS mirrored by the generator. Browser verified 390px and 1440px, both widths, return-to-site, paper/dark/light, 19px/2.2 persistence after reload, index selection/closure; no captured console errors. Review candidate supersedes revision-7 status below. No push/deploy.

**Latest override (2026-09-25): `REVISION_7_OWNER_REVIEW`.** Owner requested GitHub Docs-like spacing, layout and menu green. Reader now has a quiet 280px RTL chapter rail, 760px article, unboxed provenance content, ruled headings, 17px/1.95 desktop paragraphs (16px mobile), and active-location tracking on scroll. Reference active marker measured in the supplied GitHub Docs page as `rgb(95,237,131)` / `#5fed83`; light mode uses accessible `#1a7f37` on white. Theme state also updates shared chrome's root attribute. Earlier revision-6 freeze statements below are historical; revision 7 awaits owner review. No book text, IDs or source artifacts changed. Tests: `npm run test:ai-book`, JavaScript syntax and diff whitespace PASS. Actual local WP inspected at 1440, 768 and 390px: no horizontal overflow; both themes, 20-link compact index, S02 anchor/active marker checked; no captured console errors/warnings. No push/merge/deploy. Next: owner reviews the local Reader.

**Latest local verification (2026-09-24):** owner review requested a full-screen book-reading feel and Light Mode, producing `FROZEN_REVISION_6`. The Reader main surface is full viewport width, while its typographic measure remains 760px/72ch; the title hero and related-content card frames are removed in favor of quiet rules and whitespace. A 44px accessible Light/Dark control follows system preference on first visit, persists through the existing site-wide `darkMode` key, and synchronizes `data-ab-theme`, `data-kdcv-theme` and body classes. Live checks at 1440px, 886px and 390px show no overflow or console warnings/errors. Light Mode uses a warm paper surface rather than pure white. Canonical data is unchanged. The local stack remains at `http://localhost:8890`; no push, merge or production deployment was performed.

`DESIGN_STATUS = FROZEN_REVISION_6`. All functional subsystems and prior Security, Technical SEO and Performance gates remain complete. Revision 6 changes only Reader presentation and introduces a shared persistent appearance control; it does not modify canonical book data, IDs, provenance or route contracts. The remaining items are human-gated: publisher signing, production deployment, and deferred personalized-PDF issuance.

Canonical book root is `/Users/emperor/Documents/AI/AiBook`. Treat release/signing inputs as protected; do not read `.env` or private signing keys. Current edition `2026E1` is development-signed, not publisher-signed. All website work happens in the linked worktree `.claude/worktrees/kohandezh-reader-data-phase-9-8f7e49` on branch `claude/kohandezh-reader-data-phase-9-8f7e49`.

## What changed this session (Phase 11: Catalog — see git `b227752`)

- New `KBK_AI_Book_Catalog` (`includes/class-kbk-ai-book-catalog.php`): one fail-closed `load()` validating glossary/sources manifest/bibliography/source map/templates/entities before any view reads them; hand-built bounded read models only. Glossary index sorted by Persian preferred term (safe keys only — reviewer secrets structurally absent, tested); sources = 18 audited documents with bibliography merge and per-doc section counts summing to exactly 660; templates = 20 cards + fail-closed details (own origin label `editorial_template_ir`, distinct from the three content origins); concepts = 3,671 entities, type filter (20 types), 60/page pagination with clamping, entity details with mentions resolved into Reader links capped at 12 and the raw mention list never exposed.
- Five new validators (`validate_sources_manifest/bibliography/source_map/template/entity`), `kbk_template/kbk_entity/kbk_type/kbk_page` query vars (strict allowlists), views `sources`/`templates`/`concepts` + full glossary index, one additive token-based CSS block (`.ab-term-list`), new `ai-book-catalog.test.php` (8 blocks) + extended route test.

## What changed this session (Phase 12: Knowledge Graph — see git `8c63eec`)

- New `KBK_AI_Book_Graph` over `knowledge/graph.json` (3,671 nodes, 10,474 edges, 17 relation types; validator-enforced no dangling endpoints). Graph artifact loads lazily (graph views only). `/ai-book/graph/` overview (honest stats + relation legend) and depth-1 node neighborhoods capped at 24 edges with deterministic SVG ring (≤12 nodes) + accessible list; evidence quotes bounded at 160; every anchor/endpoint repository-resolved.
- Reader wiring: `related_map()` computes per-section Related Concepts (≤6, deterministic ranking) and Related Sections (≤4, co-mention frequency, self/siblings excluded) in one bounded scan over catalog entities; rendered per section via existing helpers; ADR-AB-0012.

## What changed this session (Phase 13: Ask/RAG)

- New `KBK_AI_Book_Ask` (`includes/class-kbk-ai-book-ask.php`): **local cited retrieval always available** — a bounded normalized scan (shared Persian normalization, AND token semantics, weights label 8 / keywords 4 / body ≤3 / label-phrase +6) over `release/14_rag_corpus_fa.jsonl` (currently 2,427 validated chunks). Retrieval models: ≤6 per query (`MAX_RETRIEVAL`), excerpts ≤360 chars as `{text, mark}` highlight segments (template escapes, owns `<mark>`), part/chapter hierarchy, origin labels, source docs (≤3), citation URL (`/fa/ai-book/` namespace with section fragment, validator-enforced), `reader_resolvable` flag with repository resolution — raw `fa_text` never leaves the class (tested).
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

## Final browser/accessibility QA (2026-09-20)

**PASS** — see `docs/ai-book/FINAL_QA.md`. 13 static shells × 3 viewports = 39/39 page-load checks (zero console errors, no horizontal overflow, `dir=rtl`, one skip link). Keyboard: skip link is the first Tab target and Enter moves focus into `<main>`; landmarks/census verified on the Reader. View-level DOM assertions pass on search/ask/request-pdf/graph/pdf (pdf toolbar controls honestly disabled). One finding fixed: QA-1 — the shell builder omitted `role="img"` on `.ab-graph-canvas` (live template already had it); fixed in `build_visual_shell.py`, shells regenerated and re-verified. 12 evidence screenshots in `docs/ai-book/screenshots/` (DOM assertions are the pass criteria; PNGs preserved for human review). A later local WordPress stack is now available and the locale-prefixed landing/Reader/Search/Ask/PDF/Request routes have also been browser/HTTP verified.

## Production Build (2026-09-20)

**VERIFIED.** Production-facing build surface checked end to end: (1) theme asset copies byte-identical to sources (`_tooling/wp-theme/kohandezh-knowledge/assets/ai-book.css` = `assets/css/ai-book.css`, 19,593 bytes; `ai-book.js` 600B), enqueued via `plugins_url` on ai-book views only (route-test asserted); (2) loader order intact in `kohandezh-knowledge.php:60-68` — artifacts → repository → search → catalog → graph → ask → pdf → request → ai-book; (3) `php -l` clean on all 8 ai-book includes, the template, and the plugin bootstrap; (4) `npm run test:ai-book` (10 suites) and `npm test` fully green immediately before the phase; (5) visual shell regeneration is deterministic — `git status ai-book/` clean before and after a rebuild; (6) hygiene: no `console.log`/`debugger` in shipped JS, no `error_log`/`var_dump`/`print_r` in ai-book includes, `git diff --check` clean, working tree clean before the docs commit.

## Skills / Agent Capabilities

- See `docs/ai-book/SKILLS_MANIFEST.md`. PHP type safety remains `MANUAL_POLICY`; the narrow `marketing:seo-audit` Skill was activated for the AI Governance discovery/canonical review. The 2026-09-23 theme-integration pass used `uiux-pro-max` within the frozen design and the local `browse` Skill for real-browser desktop/tablet/mobile verification. The PDF phase used only byte/manifest inspection — no PDF-creation Skill was activated.
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
- Final browser/accessibility QA → PASS (39/39 page-load checks, keyboard sweeps, QA-1 fixed; `docs/ai-book/FINAL_QA.md`)
- `npm test` (full existing site suite) → PASS, zero regression
- `php -l` clean on all touched PHP files
- Local WordPress is running at `http://localhost:8890` and the current locale-first routes were verified there. The provider HTTP path remains stubbed in tests — no live provider exists locally and none must be invented.

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
- `_tooling/ai-book/build_visual_shell.py` (QA-1: `role="img"` on the graph canvas), regenerated `ai-book/*` shells, `docs/ai-book/screenshots/` (12 PNGs, new)
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

No agent-executable tasks remain. The remaining work is **human-gated** and must not be simulated locally: (1) **publisher signing** — the current edition `2026E1` is development-signed; signing requires the real publisher signing key in the canonical repo (never read `.env`/private keys); (2) **production deployment** — activate `KBK_FEATURE_AI_BOOK=true` + `KBK_AI_BOOK_ROOT` in real `wp-config.php`, keep `KBK_AI_BOOK_INDEXABLE` unset until the go-live decision, and configure the ask/request provider endpoints+keys as environment secrets (never committed); (3) **personalized PDF issuance** — deferred by scope decision. A successor agent should only pick up work if a human explicitly re-opens a phase; otherwise update handover docs only when something actually changes.

## Performance review gate (2026-09-20)

**PASS** — see `docs/ai-book/PERFORMANCE_REVIEW.md` for the measured per-view cost map (bundle 30ms, catalog 30ms, graph 83ms, search 42ms, ask retrieval ~207ms, PDF meta 0.4ms, related_map 1ms; payload 19.6KB CSS + 600B JS). One finding fixed in-gate: PERF-1 — the 38MB PDF stream now carries a strong sha256-derived ETag with `Cache-Control: private, max-age=0, must-revalidate` and answers `If-None-Match` with a bodyless 304 (pure matcher unit-tested; repeat opens cost one conditional request, zero file reads; no public caching). Security report carries an addendum for the cache-control evolution.
