# AI Book Decisions

## ADR-AB-0001 — Preserve the canonical book namespace

- **Decision:** Preserve `/fa/ai-book/*`, existing slugs, stable anchors and content IDs.
- **Context:** External citations and provenance already point to these URLs.
- **Alternatives:** New `/book/` namespace; import as generic `/knowledge/` posts.
- **Rationale:** Avoid broken citations and duplicate content.
- **Tradeoffs:** Existing P06 route collision must be resolved deliberately.
- **Reversibility:** Low after publication.
- **Date:** 2026-09-19

## ADR-AB-0002 — Integrate through isolated WordPress/PHP Layer B

- **Decision:** Prefer a scoped module within `kohandezh-knowledge`, subject to a route-ownership spike.
- **Context:** The current stack is PHP/WordPress and Layer A is protected.
- **Alternatives:** TypeScript app; generated HTML wholesale; unrelated new framework.
- **Rationale:** Reuses established isolation, tokens and deployment model.
- **Tradeoffs:** Requires careful artifact ingestion and route testing.
- **Reversibility:** Medium; an adjacent plugin remains possible before implementation.
- **Date:** 2026-09-19

## ADR-AB-0003 — Validate canonical artifacts at ingestion

- **Decision:** Treat AiBook JSON/JSONL as external immutable data and validate documented shapes before use.
- **Context:** Artifacts have different shapes and ID namespaces.
- **Alternatives:** Trust files and access fields ad hoc.
- **Rationale:** Prevents silent corruption and contract drift.
- **Tradeoffs:** Adds validator fixtures and explicit import failures.
- **Reversibility:** High.
- **Date:** 2026-09-19

## ADR-AB-0004 — Freeze existing-site visual extension

- **Decision:** Use dark/lime site tokens, Persian local fonts, evidence-first cards and responsive 3/2/1-column reader.
- **Context:** The generated book UI is semantically useful but visually separate.
- **Alternatives:** Copy generated book CSS; redesign the website.
- **Rationale:** Consistent, additive, accessible integration.
- **Tradeoffs:** Production components must adapt generated semantics.
- **Reversibility:** Visual changes require freeze revision.
- **Date:** 2026-09-19

## ADR-AB-0005 — Supersede the incomplete first freeze

- **Decision:** Reopen revision 1, implement all 13 route shells, run the required cross-viewport QA, then issue freeze revision 2.
- **Context:** The full Master Prompt defines design Done as complete landing/reader/search/ask/graph/knowledge/PDF/request shells; revision 1 covered only a reader sample.
- **Alternatives:** Treat the sample as sufficient and begin backend work.
- **Rationale:** A successor must inherit a finished visual product, not design missing pages.
- **Tradeoffs:** Adds a static generator and generated shell before WordPress integration.
- **Reversibility:** High; generator output is isolated and local.
- **Date:** 2026-09-19

## ADR-AB-0006 — Separate Hub routes from canonical content URLs

- **Decision:** Use `/ai-book/*` for application surfaces while preserving `/fa/ai-book/*` as canonical content/citation URLs.
- **Context:** The Master Prompt requires Hub routes but the finalized book already publishes stable Persian citation URLs.
- **Alternatives:** Replace existing canonical routes; duplicate indexable text.
- **Rationale:** Satisfies the product IA without breaking provenance.
- **Tradeoffs:** Integration must prevent duplicate indexation and make deep-link behavior explicit.
- **Reversibility:** Medium before production.
- **Date:** 2026-09-19

## ADR-AB-0007 — Reject canonical data before rendering when contracts drift

- **Decision:** All book, content-ID and citation JSON crosses `KBK_AI_Book_Artifacts`; edition, count, IDs, origins, locale, canonical URL and cross-registry relationships are mandatory.
- **Context:** Canonical artifacts are external to the website and have distinct shapes and namespaces.
- **Alternatives:** Ad-hoc array access; trust the build outputs without runtime/build validation.
- **Rationale:** Fail closed before malformed or mismatched provenance becomes public HTML.
- **Tradeoffs:** A new edition/schema may require an explicit validator update.
- **Reversibility:** High, but weakening validation requires a new ADR.
- **Date:** 2026-09-19

## ADR-AB-0008 — Keep WordPress book routing opt-in and non-indexable by default

- **Decision:** `KBK_FEATURE_AI_BOOK` defaults false; a readable `KBK_AI_BOOK_ROOT` is mandatory; `KBK_AI_BOOK_INDEXABLE` defaults false.
- **Context:** The implementation is local, canonical artifacts live outside the public root, and duplicate/canonical behavior is not yet production-approved.
- **Alternatives:** Hardcode the local path; silently fall back to sample content; index incomplete routes.
- **Rationale:** Prevents secrets/path leakage, guessed content and premature search indexing.
- **Tradeoffs:** Local WordPress needs explicit configuration before routes activate.
- **Reversibility:** High after final SEO/security gates.
- **Date:** 2026-09-19
