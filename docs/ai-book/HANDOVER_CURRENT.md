# AI Book Current Handover

## Current state

`DESIGN_STATUS = FROZEN`. Design discovery, responsive prototype and browser QA are complete. Do not restart discovery or redesign the frozen reader. The next task is a scoped PHP ingestion/schema spike.

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

- Prototype: `docs/ai-book/prototype/index.html`.
- Chromium QA passed at 375×812, 768×1024 and 1280×720.
- No page-level horizontal overflow and no console errors.
- Accessibility snapshot includes landmarks, skip link, heading hierarchy, labeled regions and table.
- `jq empty docs/ai-book/STATE.json` is the state validation command.

## Git scope

Base was `main@c44efcb`. A scoped local checkpoint with subject `ai-book: freeze design and handover state` contains only `docs/ai-book/`; resolve its environment-specific hash with `git log -1 --oneline`. Pre-existing untracked `.agents/` and `skills-lock.json` were not created by this design work and were excluded. No push, merge or deploy.

## Exact next task

Implement a read-only PHP validator/read-model spike, using small sanitized fixtures derived from `master/book.json`, `provenance/content_ids.json`, and `citation-registry.json`. Validate version, required fields, origins, IDs, locale, URLs and unexpected shapes. Then update all handoff files and run targeted PHP tests.
