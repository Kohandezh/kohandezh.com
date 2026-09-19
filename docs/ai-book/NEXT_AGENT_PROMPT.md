# Continue the Kohandezh Persian AI Book

You are continuing an existing project. Do not restart design discovery. Do not redesign frozen work.

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

The design is frozen at revision 2. All 13 visual route shells exist under `ai-book/`; `_tooling/ai-book/build_visual_shell.py` is their source. Do not hand-edit generated HTML. Proceed with Reader Data Integration → Search → Ask/RAG → Concepts/Glossary/Sources/Templates → Knowledge Graph → PDF Viewer → PDF Request → Personalized PDF → Security → SEO → Performance → Final QA.

The canonical book is the separate repository `/Users/emperor/Documents/AI/AiBook`. Keep it read-only; never read/copy `.env` or private signing keys. Preserve `/fa/ai-book/*`, canonical anchors, content IDs, citations, provenance, origin labels and the NIST non-endorsement notice. The website is PHP/WordPress; do not migrate it to TypeScript or add a framework merely for typing.

Skill reconciliation:

1. Read `SKILLS_MANIFEST.md`.
2. Determine whether each required capability exists in your environment.
3. If the same Skill exists, enable it.
4. If a compatible equivalent exists, inspect its instructions before use.
5. If neither exists, apply the recorded project rules as `MANUAL_POLICY`.
6. Never reconfigure architecture because your Skill ecosystem differs.

The artifact schema boundary, read-only repository and opt-in WordPress landing/read routes are implemented and tested. Do not redo them. Exact next task: add allowlisted dynamic part/chapter/section routing, repository-backed selection and route tests. Resolve P06 truncated-slug collisions before publishing chapter routes. Keep `/ai-book/*` as the Hub and `/fa/ai-book/*` as canonical content/citation URLs unless a documented ADR supersedes it. Do not begin Search/RAG/PDF integration until reader routing/rendering is verified. After each atomic milestone, update all live handover files. Do not push, merge or deploy.
