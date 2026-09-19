# AI Book Current Handover

## Current state

`DESIGN_STATUS = FROZEN_REVISION_2`. All 13 Master Prompt route shells, shared components and required responsive QA are complete. The PHP artifact boundary now validates fixtures and the full 479-section canonical bundle. Do not restart discovery or redesign the frozen UI. The next task is the faithful WordPress/PHP landing and reader port.

Canonical book root is `/Users/emperor/Documents/AI/AiBook` (corrected from the prompt). Treat it as read-only. Do not read `.env` or private signing keys. Current edition `2026E1` is development-signed, not publisher-signed.

## Skills / Agent Capabilities

- Active: `uiux-pro-max` 1.0.0 for design/accessibility and `browse` 1.1.0 for browser QA.
- Active manual policies: type safety and model-independent handoff.
- Deferred: `cso` 2.0.0, `performance-analysis`, OpenAI runtime `pdf`, and manual technical SEO.
- Configuration/source of truth: `docs/ai-book/SKILLS_MANIFEST.md` and `STATE.json`.
- No successor must install an identical Skill. Reconcile compatible capabilities and use recorded MANUAL_POLICY when unavailable.
- Environment-specific browser binary used here: `/Users/emperor/.agents/skills/gstack/browse/dist/browse`. A successor may use Playwright or equivalent.
- The installed repo-local `typesafe-ai` is not the type-safety policy; it is an AI judgment API Skill.

## Verified evidence

- Visual implementation: `ai-book/`; source generator: `_tooling/ai-book/build_visual_shell.py`; shared assets: `assets/css/ai-book.css` and `assets/js/ai-book.js`.
- Implemented routes: `/ai-book/`, read, search, ask, concepts, graph, templates, sources, glossary, PDF, request PDF, cite and verify.
- Chromium QA passed the seven mandatory routes at 375×812, 768×1024 and 1280×720 (21 checks).
- No page-level horizontal overflow or console errors. Every checked page had Persian/RTL, an H1, main landmark and loaded styles.
- Reference screenshots are in `docs/ai-book/screenshots/`.
- Static pages remain `noindex,nofollow` until functional canonical/duplicate-content behavior is implemented.
- `KBK_AI_Book_Artifacts` validates master book, content-ID registry and citation registry, rejects unsupported origin/ID/URL/count contracts, and cross-checks edition and IDs.
- Fixture test passed with one valid bundle and four rejected invalid cases. The real canonical bundle also passed all validation.
- `KBK_AI_Book_Repository` now owns validated loading, book summary, part/chapter lookup, structural/content-ID section lookup and citation lookup; its full canonical test passed.
- Full existing `npm test` suite passed after the visual shell and validator changes; no existing-site regression was detected.
- `jq empty docs/ai-book/STATE.json` is the state validation command.

## Exact files changed in revision 2

- `_tooling/ai-book/build_visual_shell.py`
- `assets/css/ai-book.css`
- `assets/js/ai-book.js`
- `ai-book/**/index.html` (13 generated route pages; do not hand-edit)
- `docs/ai-book/CONTENT_SOURCE_MAP.md`, design/IA/freeze/plan/decision/state/TODO/handover/successor files and four screenshots

## Commands to continue and test

```bash
python3 _tooling/ai-book/build_visual_shell.py
python3 -m py_compile _tooling/ai-book/build_visual_shell.py
node --check assets/js/ai-book.js
php _tooling/tests/ai-book-artifacts.test.php
php _tooling/tests/ai-book-repository.test.php ../AiBook
npm run test:ai-book
jq empty docs/ai-book/STATE.json
git diff --check
```

No environment variables are required for the visual shell. Ask runtime, request delivery/SMS/email, personalized PDF and publisher signing remain configuration-required; do not invent credentials.

## Git scope

Base checkpoint is `main@ae8689f`. Pre-existing untracked `.agents/` and `skills-lock.json` are unrelated to revision 2 and must remain excluded. No push, merge or deploy.

## Exact next task

Add feature-flagged `/ai-book/` and `/ai-book/read/` WordPress routes/templates without visual changes. Obtain data only through `KBK_AI_Book_Repository`. Keep the source root configurable and outside the public web root, add safe unavailable/error states, and do not expose raw artifacts. Preserve `/fa/ai-book/*` canonical citations. Update all handoff files and run targeted PHP and existing-site regression tests.
