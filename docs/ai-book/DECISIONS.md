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

## ADR-AB-0009 — Resolve the P06 chapter-slug collision by ID-first lookup, fail-closed on ambiguity

- **Decision:** `KBK_AI_Book_Repository::find_chapter()` now matches the stable `chapter_id` first (always unambiguous within a part) and only falls back to a slug match when exactly one chapter in the part carries it; an ambiguous slug returns `null` instead of guessing. All Hub navigation this project generates (`/ai-book/read/?kbk_part=...&kbk_chapter=...`) links by `chapter_id`, never by slug.
- **Context:** P06's chapters C43, C44 and C45 all reduce to the identical truncated slug `artificial-intelligence-and-the-courts-materials-for-judges-`, and — separately, in the external canonical data itself — all three chapters' `canonical_url` values share the exact same `/fa/ai-book/ai-law/.../` page path, differing only by URL fragment (`#KDJ-AI-2026E1-P06-C4{3,4,5}-S01`). That external citation-URL shape is part of the immutable, already-published `citation-registry.json`/`content_ids.json` contract and is out of this project's control; each *section's* fragment is still unique, so per-section outbound citation links (already emitted per-section by the reader template) are unaffected.
- **Alternatives:** (a) Guess the first slug match — rejected, silently serves the wrong chapter's content under another chapter's link. (b) Invent a new disambiguating slug/suffix for routing — rejected, would diverge from the frozen external identifiers and title text with no source authority to do so. (c) Block all P06 chapter routing until the external data is fixed — rejected, unnecessary: ID-based routing already fully and correctly serves all three chapters today.
- **Rationale:** Fail closed on genuine ambiguity (mirrors ADR-AB-0007's stance at the routing layer instead of the artifact-validation layer); never fabricate a disambiguating identifier the source data doesn't provide; real content stays reachable via its stable ID.
- **Scope:** This closes the collision for the `/ai-book/read/` Hub built in Phase 9. It does not build or touch a `/fa/ai-book/*` route — no such route exists in this codebase; that string is only ever read as an external citation link target. Building a `/fa/ai-book/*` canonical page for this three-chapter cluster (were that ever undertaken) would face the same shared-page-path shape and needs its own explicit decision at that time — out of scope here.
- **Also resolved:** the TODO's paired `/knowledge/` ownership question. `KBK_Routes` already owns a pre-existing `/knowledge/` CPT-archive route (Layer B "Knowledge Hub", unrelated claim/evidence content). The AI Book registers routes only under `/ai-book/*` and never touches `/knowledge/`; the two `knowledge/` mentions (the CPT archive slug and the canonical repo's `knowledge/*.jsonl` data folder) are unrelated same-named things, not a routing collision.
- **Reversibility:** High; a future ADR can add a disambiguating routing scheme if the source data itself changes.
- **Date:** 2026-09-19

## ADR-AB-0010 — Search as an in-PHP bounded scan of pipeline-built artifacts, with a guaranteed glossary slot and a minimal glossary deep-link target

- **Decision:** `/ai-book/search/` is served by `KBK_AI_Book_Search`, which consumes the pipeline's purpose-built bounded index `html/search/index.json` (one validated entry per canonical section, ≤2000-char snippet) plus the approved glossary `release/09_glossary.json`, does a normalized in-PHP linear scan per request, and returns bounded view models only. Glossary matches get a guaranteed representation rule: when matches overflow the page and no glossary card survived the top-N slice, the best glossary match displaces the last slot. Search-result glossary cards deep-link to a new minimal `/ai-book/glossary/?kbk_concept=…` term view. One token-based CSS rule (`.ab-results mark`) was added.
- **Context:** The prompt requires searching part/chapter/section titles, Persian body, English terms, acronyms, glossary, source IDs and content IDs, with highlighted snippets and stable deep links, while never exposing raw corpus/JSONL/dumps. The pipeline already ships `html/search/index.json` for exactly this purpose (bounded snippets, keyword fields, acronyms, docs, hierarchy). No local WordPress install exists to host a search daemon; the site is shared PHP hosting.
- **Alternatives:** (a) Elasticsearch/OpenSearch — rejected: 479 bounded entries + 435 terms do not need an external engine, and it would violate "simplest solution compatible with the existing architecture" and add an operational dependency. (b) Searching `master/book.json` bodies directly — rejected: the master file carries full section text; scanning it at request time risks accidental full-body exposure and is slower; the bounded index is the intended surface. (c) Storing pre-highlighted HTML snippets — rejected: highlighting must be derived from the actual query; segments (`{text, mark}`) let the template escape text and own all markup, so highlight injection is impossible by construction. (d) Deferring glossary search results until the Glossary phase — rejected: the prompt requires glossary searchability and stable deep links now; a dead link would be a guessing-free but broken surface, so the minimal fail-closed term view (breadcrumb, term, definition, alternatives, sources, concept ID, status; unknown ID → not-found; no ID → pointer to search) is added as the target. Full glossary browsing remains the next phase's job.
- **Ranking rules (tested):** AND token semantics; weights title 10 / acronym 8 / title_en 7 / keywords+docs 4 / chapter 3 / part 2 / body ≤3; exact-phrase bonuses part +70, chapter +55, section +4; glossary exact preferred-term equality 14 beats containment 12; concept-ID token 20; identifier-shaped queries short-circuit to exact/prefix ID resolution. Persian normalization (yeh/kaf/hamza, digits, diacritics, ZWNJ, directional marks) is shared by query and index. Rejected glossary aliases never match; reviewer metadata never renders.
- **Freeze note:** the additive `.ab-results mark` rule uses the frozen `--ab-accent`/`--ab-ink` token pair (the same accent/ink treatment as `.ab-brand-mark`). The frozen search shell itself promises highlighted matches; no other CSS/JS or layout changed. Design status remains FROZEN_REVISION_2.
- **Reversibility:** High — the engine is one class behind `KBK_AI_Book::search_engine()`; a future backend (e.g., DB-backed index) can replace it without touching routes/templates.
- **Date:** 2026-09-19

## ADR-AB-0008 — Keep WordPress book routing opt-in and non-indexable by default

- **Decision:** `KBK_FEATURE_AI_BOOK` defaults false; a readable `KBK_AI_BOOK_ROOT` is mandatory; `KBK_AI_BOOK_INDEXABLE` defaults false.
- **Context:** The implementation is local, canonical artifacts live outside the public root, and duplicate/canonical behavior is not yet production-approved.
- **Alternatives:** Hardcode the local path; silently fall back to sample content; index incomplete routes.
- **Rationale:** Prevents secrets/path leakage, guessed content and premature search indexing.
- **Tradeoffs:** Local WordPress needs explicit configuration before routes activate.
- **Reversibility:** High after final SEO/security gates.
- **Date:** 2026-09-19

## ADR-AB-0011 — Catalog as one fail-closed validated loader with hand-built bounded read models for glossary, sources, templates and concepts

- **Decision:** `KBK_AI_Book_Catalog` loads and validates every catalog artifact in a single `load()` pass (`release/09_glossary.json`, `manifests/sources.json`, `master/bibliography.json`, `master/source_map.json`, `templates/TPL-*.json`, `knowledge/entities.jsonl`); any corruption flips the entire catalog to `ARTIFACT_INVALID` and every getter returns empty — no partial data. Read models are hand-built field by field. `/ai-book/glossary/` becomes a full sorted term index; `/ai-book/sources/`, `/ai-book/templates/` and `/ai-book/concepts/` become live views. New validators: `validate_sources_manifest`, `validate_bibliography`, `validate_source_map`, `validate_template`, `validate_entity`. New query vars `kbk_template`/`kbk_entity`/`kbk_type`/`kbk_page`, all strict allowlists. One additive token-based CSS block (`.ab-term-list`).
- **Context:** The prompt requires glossary browsing, the 18 audited sources, 20 editorial templates and the 3,671-entity concept catalog as bounded WordPress views, without raw corpus/JSONL exposure and with editorial provenance preserved. `entities.jsonl` carries `definition_en` (3,660 entities) and mention lists (capped 38) that reference real content IDs; the reader can resolve those into stable deep links.
- **Alternatives:** (a) Lazy per-artifact loading so one corrupt file degrades only its own view — rejected: partial catalogs would silently present e.g. sources without concepts, undermining the honest-state contract; one validated snapshot is simpler to reason about and test. (b) Reusing `KBK_AI_Book_Search::find_term()` for glossary browse — rejected: search models carry snippet fields for matching, not browse fields; the catalog owns the sorted index view. (c) Exposing raw entity JSON (mentions included) to templates — rejected: bounded read models only; entity detail caps resolved sections at 12 and reports the honest total. (d) Rendering mentions without resolution — rejected: unresolved content IDs are not links users can act on; resolution through `find_section()` guarantees the link target exists (tested for every rendered link).
- **Rules (tested):** glossary terms expose exactly `{concept_id, preferred_fa, english, acronym, status, domain}`, sorted by `preferred_fa`, reviewer secrets structurally absent; sources = 18 documents with bibliography merge and per-doc section counts summing to exactly 660; template origin allowlist is the distinct provenance label `editorial_template_ir` (NOT one of the three content origins); entity origins allowlist `{source, editorial}`; entity mentions must be content IDs of the active edition; entities paginate at 60/page with clamping; unknown filters/pages/types fail to honest empty states, never guesses; `CONFIG_REQUIRED` when unconfigured.
- **Freeze note:** `.ab-term-list` uses only frozen tokens (`--ab-panel`, `--ab-line`, `--ab-accent`, radius, ease) with the same card language as `.ab-toc`/`.ab-results`; no layout or component redesign. Design status remains FROZEN_REVISION_2.
- **Reversibility:** High — the catalog is one class behind `KBK_AI_Book::catalog()`; views consume read models only.
- **Date:** 2026-09-19
