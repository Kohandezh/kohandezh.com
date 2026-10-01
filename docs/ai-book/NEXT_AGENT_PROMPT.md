# Continue the Kohandezh Persian AI Book

You are continuing an existing project. Do not restart design discovery. Do not redesign frozen work.

Latest override: REVISION_9_OWNER_REVIEW. Read BOOK_PRESENTATION_REVIEW.md and the top of HANDOVER_CURRENT.md before historical notes below. Current task is local owner review, not production release. Preserve canonical corpus and existing dirty work. Public landing is still 404; do not claim deployed. Local article import is reproducible using the host-guarded seed-local-article.php, not an automatic production migration.

Latest revision is now `REVISION_8_OWNER_REVIEW`: owner-authorized distraction-free Reader with persistent font/spacing/width/theme controls. Read latest handover before the historical revision-7 notes. Next is local owner feedback, not release. No canonical book edits or production action.

Latest override 2026-09-25: Reader revision 7 is implemented locally and awaits owner visual review (`REVISION_7_OWNER_REVIEW`), superseding historical revision-6 freeze notes below. It follows the owner's GitHub Docs reference; source CSS/JS are in `assets/`, mirrored by `build_visual_shell.py`. Preserve canonical book content. Do not deploy or push for this UI request. Next task: obtain owner feedback on the local Reader, not a new design discovery.

First read, in order:

1. `docs/ai-book/STATE.json`
2. `docs/ai-book/HANDOVER_CURRENT.md`
3. `docs/ai-book/CONTENT_SOURCE_MAP.md`
4. `docs/ai-book/NEXT_AGENT_PROMPT.md`
5. `docs/ai-book/DESIGN_FREEZE.md`
6. `docs/ai-book/DESIGN_SYSTEM_SNAPSHOT.md`
7. `docs/ai-book/IMPLEMENTATION_PLAN.md`
8. `docs/ai-book/SKILLS_MANIFEST.md`

Then inspect only the targeted Git diff and relevant files. Preserve unrelated changes.

The design is frozen at revision 6. Revision 6 makes the Reader a full-bleed book surface, removes card framing from the title and related-reading blocks, preserves the readable 72ch line measure, and adds a persistent system-aware Light/Dark control integrated with Kohandezh's shared `darkMode` preference. Do not restore the boxed Reader or introduce a separate theme storage key. Revision 5 remains the approved subject-title policy, revision 4 the responsive Reader structure, and revision 3 the site/product navigation hierarchy. All 13 visual route shells exist under `ai-book/`; `_tooling/ai-book/build_visual_shell.py` is their source. Do not hand-edit generated HTML. All agent-executable implementation and QA phases are complete; do not restart that sequence.

The canonical book is the separate repository `/Users/emperor/Documents/AI/AiBook`. Keep it read-only; never read/copy `.env` or private signing keys. Preserve `/fa/ai-book/*`, canonical anchors, content IDs, citations, provenance, origin labels and the owner-approved authorship/education notice. The website is PHP/WordPress; do not migrate it to TypeScript or add a framework merely for typing.

Skill reconciliation:

1. Read `SKILLS_MANIFEST.md`.
2. Determine whether each required capability exists in your environment.
3. If the same Skill exists, enable it.
4. If a compatible equivalent exists, inspect its instructions before use.
5. If neither exists, apply the recorded project rules as `MANUAL_POLICY`.
6. Never reconfigure architecture because your Skill ecosystem differs.

The artifact schema boundary, read-only repository, opt-in WordPress routes, all functional subsystems, review gates and Production Build are implemented and tested. The frozen AI Book now also uses the shared Kohandezh site navigation/footer and is linked from the Persian-only `/fa/` desktop/mobile navigation and footer (ADR-AB-0016); untranslated locale templates remain unchanged. The persistent local stack is `_tooling/wp-local/ai-book-compose.yml` at `http://localhost:8890` and reuses the existing named volumes. Do not redo completed phases. Remaining work is human-gated: publisher signing (needs the real publisher key — current edition is development-signed), production deployment (`KBK_FEATURE_AI_BOOK` + `KBK_AI_BOOK_ROOT` + provider secrets in real `wp-config.php`, never committed), and the deferred personalized-PDF issuance. Do not simulate or invent credentials. Keep `/ai-book/*` as compatibility Hub routes, `/fa/books/ai-governance/*` as the Persian product namespace, and `/fa/ai-book/*` as canonical content/citation URLs unless a documented ADR supersedes them. If a human re-opens a phase, update all live handover files after each atomic milestone. Do not push, merge or deploy.
