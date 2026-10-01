# AI Book Decisions

## ADR-AB-0016 — Additive AI Governance namespace with source-stable identifiers

- **Decision:** Publish the Persian product discovery layer under `/fa/books/ai-governance/`, matching the site's locale-first URL convention. Preserve `/books/ai-governance/*` and `/ai-book/*` as compatibility rewrites and `/fa/ai-book/*` as the immutable citation namespace. Do not publish routes or `hreflang` entries for untranslated locales. Present `GOVERN n.n` sections as `AI Governance n.n — حاکمیت هوش مصنوعی n.n`; retain the source `GOVERN` code in `title_en`, section paths and provenance.
- **Context:** The owner requested a searchable bilingual AI Governance identity, a book folder under kohandezh.com, and an honest news/analysis layer for government-affiliated and enterprise audiences.
- **Rationale:** A dedicated entity-rich pillar page provides a clear search target without mutating source IDs, breaking citations, duplicating the book corpus, or generating agency doorway pages.
- **SEO/GEO:** Preferred canonical URLs, Book/WebPage/Chapter/CollectionPage JSON-LD, a gated sitemap provider and a source-only news collection are implemented. Rankings are not promised.
- **Freeze note:** Existing frozen components and tokens are reused; no visual redesign.
- **Date:** 2026-09-21

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

## ADR-AB-0012 — Bounded depth-1 graph explorer with lazy artifact loading; Reader Related wiring derived from catalog mentions, not the graph artifact

- **Decision:** `/ai-book/graph/` is served by `KBK_AI_Book_Graph` over validated `knowledge/graph.json`. The explorer is depth-1 only (a node's direct edges), capped at 24 rendered edges with an honest total, with a deterministic SVG ring (≤12 nodes, inline-positioned, existing frozen canvas/node styles, accessible list as the real reference). The 4.8MB graph artifact loads lazily — only graph views trigger it. Reader Related Concepts/Related Sections are computed from the catalog's validated `entities.jsonl` mentions in one bounded scan (concepts ≤6 by global mention count; sections ≤4 by co-mention frequency, self and same-view siblings excluded), never from the graph artifact.
- **Context:** The prompt requires a bounded accessible KG explorer and Reader related-content wiring without bulk export or raw JSON dumps. The graph has 60-degree hub nodes (a depth-2 walk explodes quadratically) and `entities.jsonl` already carries every mention needed for co-mention relatedness.
- **Alternatives:** (a) Depth-2 neighborhood — rejected: hub nodes (REFERENCES with 5,291 edges) make even depth-1+counting heavy and depth-2 unbounded in practice; depth-1 with an honest "n direct edges" total is the truthful bound. (b) Loading graph.json on every Reader view for relatedness — rejected: +4.8MB parse per chapter view for data derivable from the 1.24MB entity mentions the catalog already validates; the two datasets may be re-derived from each other, so mention-based wiring cannot contradict the graph. (c) Client-side force-directed JS — rejected: adds JS dependencies to a frozen no-JS design; server-rendered SVG + list is deterministic, accessible, and cacheable. (d) Uncapped related lists — rejected: bounded page weight and honest truncation everywhere.
- **Rules (tested):** `validate_graph()` enforces edition match, existing endpoints, declared relations, edition content-ID anchors, origin `{source, editorial}`, evidence quote ≤200 / block_id ≤128; node models reuse the entity allowlists; corrupted graph fails closed WITHOUT corrupting the catalog (catalog and graph are separate validated stores); unknown/hostile `kbk_entity` fails to honest not-found.
- **Freeze note:** additive CSS only (`.ab-related-inline` + node label truncation), frozen tokens; SVG/inline positioning reuses the frozen `.ab-graph-canvas`/`.ab-node` component styling. Design status remains FROZEN_REVISION_2.
- **Reversibility:** High — the explorer is one class behind `KBK_AI_Book::graph()`; a future edge-indexed store can replace `load()` without touching views.
- **Date:** 2026-09-19

## ADR-AB-0013 — Ask: always-available local cited retrieval over the release corpus; generation strictly configuration-gated under a used ⊆ offered citation contract

- **Decision:** `/ai-book/ask/` is served by `KBK_AI_Book_Ask` over validated `release/14_rag_corpus_fa.jsonl` (identical to `knowledge/chunks.jsonl`; 2,420 chunks). Retrieval is local and always available: shared Persian normalization, AND token semantics, bounded scoring (label 8 / keywords 4 / body ≤3 / label-phrase +6), ≤6 results (`MAX_RETRIEVAL`), excerpts ≤360 chars rendered as `{text, mark}` highlight segments, part/chapter hierarchy, origin labels, ≤3 source docs, canonical citation URL in the `/fa/ai-book/` namespace with the section fragment, and a `reader_resolvable` flag resolved through the repository. Raw `fa_text` never leaves the class. Answer generation requires `KBK_AI_BOOK_ASK_ENDPOINT` + `KBK_AI_BOOK_ASK_API_KEY` (optional `KBK_AI_BOOK_ASK_MODEL`); the provider response is validated (`validate_provider_response`: bounded non-empty answer ≤4000 chars, no control characters, `used` must be a subset of the offered retrieval content IDs, bounded model string) and any violation, HTTP failure or non-2xx degrades honestly (`PROVIDER_INVALID` / `PROVIDER_UNAVAILABLE`) — the view then shows cited passages only. Status ladder: `CONFIG_REQUIRED` → `PROVIDER_REQUIRED` → `READY`. New validator `validate_rag_chunk()` (chunk_id `<content_id>#r\d{1,3}`, edition-matched content/structural IDs, `language=fa`, content-origin allowlist, fa_text 1–5000, P/C/S ID shapes, canonical namespace + section fragment). Ninth rewrite `^ai-book/ask/?$`; reuses the sanitized `kbk_q` query var.
- **Context:** The prompt requires a cited question-answering view where every claim is anchored to repository-resolvable content IDs, with the provider optional and honest about absence. `label_fa` is null on 2,200/2,420 chunks (display falls back to the validated `section` title), and the corpus `canonical_url` fragment is the structural ID, not the content ID — the validator enforces exactly that shape.
- **Alternatives:** (a) Shipping a generation provider key/URL by default — rejected: no credentials exist locally and none may be invented; retrieval-only is genuinely useful and honest. (b) Trusting provider `used` citations — rejected: a provider could cite IDs never offered; the subset contract makes invented citations structurally impossible (tested). (c) Serving provider text unfiltered — rejected: bounded, control-character-free output only; anything else is discarded, never partially rendered. (d) Loading the whole corpus into memory per request — rejected: a single streamed scan of the 2,420-chunk JSONL per query with early bounded accumulation keeps memory and latency predictable.
- **Rules (tested):** retrieval AND semantics with honest empty state; hostile input neutralized; no raw corpus text leakage (including inside `mark` segments); provider unreachable/2xx-malformed/invalid-citation paths all degrade to cited-passage-only views; corrupted corpus fails closed (`ARTIFACT_INVALID`) without affecting other views; route-layer ask state is CONFIG_REQUIRED without `KBK_AI_BOOK_ROOT` and PROVIDER_REQUIRED with it.
- **Freeze note:** no new CSS — the ask view reuses frozen components (`.ab-ask-form`, `.ab-message.ab-message-answer`, `.ab-citations`, `.ab-fallback`, `.ab-card`). Design status remains FROZEN_REVISION_2.
- **Reversibility:** High — retrieval and generation are separated behind one status ladder; swapping providers or adding a remote retrieval index later changes only `ask()`'s provider call.
- **Date:** 2026-09-19

## ADR-AB-0014 — PDF viewer: manifest-verified local stream, native browser embedding, no third-party viewer

- **Decision:** `/ai-book/pdf/` is served by `KBK_AI_Book_Pdf` over `provenance/release-manifest.json` + `release/06_book_fa.pdf` (38,422,820 bytes). Both must validate before any fact is served: new `validate_release_manifest()` (edition match, bounded publisher/build/merkle fields, artifact map 1..32 with `{bytes, sha256}` per artifact, the PDF artifact mandatory) and `validate_pdf_file()` (`%PDF-` header + exact byte-size match). The viewer panel shows real edition facts (edition, development-sign status, truncated merkle root and SHA-256, byte size, build date); the stage embeds `<object type="application/pdf" data="{home_url('/ai-book/pdf/file/')}">` with a download + text-alternative fallback. The file itself is streamed by the WordPress route (`^ai-book/pdf/file/?$`) with `Content-Type: application/pdf`, `noindex` `X-Robots-Tag`, inline disposition, `Accept-Ranges: none` — the stream plan is a pure, testable structure; the executor is a thin template_redirect hook. The interactive PDF.js toolbar of the frozen shell stays honestly disabled (no JS dependency added).
- **Context:** CONTENT_SOURCE_MAP marks the PDF as "dedicated viewer/delivery only; never normal page payload", and the prompt forbids third-party cloud embedding that leaks reader data. The canonical URL namespace and the noindex posture (ADR-AB-0008) must extend to the raw file.
- **Alternatives:** (a) Google Docs/OneDrive-style hosted viewer — rejected: reader IPs and behavior would leak to a third party. (b) Vendored PDF.js — rejected: adds a JS framework to the frozen no-JS design; native browser rendering is deterministic and free. (c) sha256 verification per request — rejected: a 38MB hash per view is wasteful; size+header checks guard every request, the full hash is verified offline in the test suite, and the manifest hash is displayed for reader-side verification.
- **Rules (tested):** missing manifest → CONFIG_REQUIRED; tampered manifest (short sha, negative bytes, missing PDF artifact) → ARTIFACT_INVALID; truncated/mismatched PDF → ARTIFACT_INVALID with an empty stream plan; degraded plans carry no path/headers; stream headers never leak local filesystem paths; the viewer degrades honestly with a text alternative pointing at `/ai-book/read/`.
- **Freeze note:** one additive token-based CSS block (`.ab-pdf-embed`); all other classes are frozen shell components. Design status remains FROZEN_REVISION_2.
- **Reversibility:** High — the stream executor and viewer consume the pure plan/meta models; a range-capable streamer can replace the executor without touching validation.
- **Date:** 2026-09-20

## ADR-AB-0017 — Owner-directed navigation hierarchy and responsive disclosure (freeze revision 3)

- **Decision:** The shared Kohandezh menu is a non-sticky, single-row utility bar above the sticky AI Book header. On book routes it omits the redundant active product item, PSN and legal links; at mobile width it additionally omits Portfolio and Certificates, leaving the owner, Blog and Knowledge entry points. The book disclosure now contains nine primary destinations and positions itself from the live header rectangle whenever it opens, resizes or scrolls.
- **Context:** Owner review found the prior stacked two-row site menu visually unbalanced, poorly adjusted and not user-friendly. It competed with the book's product navigation and its fixed menu offset overlapped the header at page top.
- **Accessibility:** Touch targets remain at least 44px; disclosure state uses `aria-expanded`, current destinations use `aria-current`, Escape returns focus to the trigger, links/outside clicks close the panel, and keyboard activation is verified.
- **Verification:** Chromium at 1280×720, 768×1024 and 375×812; zero console errors; versioned CSS/JS return HTTP 200; tablet disclosure contains nine links, opens 14px beneath the measured header bottom and closes with Escape. PHP/JS syntax, route tests, release regression suite and CSS parity pass.
- **Freeze impact:** This is the owner-approved `FROZEN_REVISION_3`. It changes navigation hierarchy only; content, typography, component tokens, canonical data and route contracts remain frozen.
- **Date:** 2026-09-23

## ADR-AB-0018 — Long-form Reader layout and safe semantic source rendering (freeze revision 4)

- **Decision:** The Reader uses two desktop columns only: a sticky chapter TOC and a centered article capped at 760px/72ch. At widths up to 1100px the rail is replaced by a native `<details>` chapter index. The chapter hero is compact and content layers become typographic reading blocks rather than nested cards. Canonical `fa_text` remains immutable; an escape-first, dependency-free renderer recognizes only headings, ordered/unordered lists, blockquotes, pipe tables, emphasis/code and figure markers.
- **Context:** Owner review found the desktop empty third rail, permanently narrow tablet content, horizontal mobile TOC, oversized hero and visible Markdown markers unsuitable for reading a long Persian book on the web.
- **Safety and accessibility:** All source fragments pass through `htmlspecialchars` before allowlisted elements are emitted, and final output still passes through `wp_kses_post`. The route test proves script input remains escaped. Native disclosure semantics require no JavaScript; targets are at least 44px and current section links use `aria-current="location"`.
- **Verification:** PHP lint and the complete AI Book test suite pass. Live WordPress was checked at 1440×1000, 886px and 390×844: no horizontal overflow or console warnings/errors, 760px desktop article, 20/20 compact index links visible when opened, and no raw Markdown heading markers.
- **Freeze impact:** This is `FROZEN_REVISION_4`. It changes Reader layout and rendering presentation only; canonical text, content IDs, provenance, route contracts and design tokens remain unchanged.
- **Date:** 2026-09-23

## ADR-AB-0019 — Human-readable Governance section titles (freeze revision 5)

- **Decision:** Keep each stable English section code (`AI Governance n.n`) and replace the duplicated Persian number with a concise subject derived from the section's first normative statement. Apply the presentation label consistently to landing cards, desktop and compact TOCs, section H2 headings and section-specific document titles. Suppress only generic source headings matching `GOVERN n.n` or `حاکمیت n.n` in the rendered body.
- **Context:** The former labels repeated the code in Persian (`AI Governance 1.1 — حاکمیت هوش مصنوعی ۱.۱`) but did not tell a general reader what the section covers.
- **Data boundary:** The mapping is typed PHP presentation policy over validated section records. Canonical `title_fa`, `fa_text`, content IDs, citations, search corpus and provenance artifacts remain read-only and unchanged.
- **Verification:** Route tests cover the S02 subject and ensure body filtering removes only duplicated code headings. Live WordPress confirms the descriptive S02 label in the TOC, H2 and deep-link `<title>`, no duplicate GOVERN heading and no horizontal overflow.
- **Freeze impact:** This is `FROZEN_REVISION_5`; presentation wording only, with no route, token, layout or canonical-data change.
- **Date:** 2026-09-24

## ADR-AB-0020 — Full-bleed reading surface and shared Light/Dark mode (freeze revision 6)

- **Decision:** Make the Reader background and main surface span the viewport, remove the rounded hero/related-content frames, and preserve a centered 760px/72ch text measure. Add an accessible appearance toggle that follows `prefers-color-scheme` when no preference exists and then persists through Kohandezh's existing `localStorage.darkMode` convention (`enabled`/`disabled`).
- **Context:** Owner review found the remaining card framing too similar to a dashboard and requested the feeling of reading a book, plus Light Mode.
- **Accessibility:** Light Mode uses a warm paper background and high-contrast text/accent tokens; the button exposes its action and pressed state, remains at least 44×44px, supports keyboard focus, and updates without navigation. The preference is set before styles render to avoid a theme flash.
- **Integration:** `data-ab-theme` owns book tokens, `data-kdcv-theme` remains owned by shared site chrome, and both consume the same stored preference. A one-time migration removes the short-lived `kbk-ai-book-theme` key so conflicting body classes cannot recur.
- **Verification:** Live WordPress checks at 1440×1000, 886px and 390×844: main width equals viewport, desktop article remains 760px, mobile toggle is 44×44px, choice persists after reload, site/book theme signals agree, no overflow and no console warnings/errors. JS syntax, asset parity and route regression checks pass.
- **Freeze impact:** This is `FROZEN_REVISION_6`; presentation and appearance only, with no content, route or provenance change.
- **Date:** 2026-09-24

## ADR-AB-0016 — Integrate the frozen product with shared Kohandezh chrome; advertise it only in Persian navigation

- **Decision:** WordPress AI Book views enqueue the active Kohandezh theme's `page-chrome` CSS/JS before the scoped book assets, mount a dedicated shared-site footer after the preserved book disclaimer, and retain the existing internal book header/navigation. The Persian `/fa/` desktop/mobile navigation and footer expose one direct link to `/fa/books/ai-governance/`; the shared `page-chrome` menu exposes the same product entry only when WordPress supplies its URL. No entry is emitted for static builds or untranslated locales.
- **Context:** The book content and routes were imported locally, but its standalone shell made the product appear disconnected from the parent site's navigation. The owner required the book to live inside the Kohandezh theme while preserving the frozen product design.
- **Constraints:** No redesign, copy change, framework migration, fake locale, production push or deployment. The scoped AI Book stylesheet owns its dark product surface and only adds compatibility rules for the shared navigation/footer.
- **Verification:** Local WordPress at port 8890 returns all shared/book assets with HTTP 200; the accessibility snapshot contains both the site menu and internal product navigation; Chromium screenshots at 375×812, 768×1024 and 1280×720 show the integrated shell; console errors and failed network requests are zero. The `/fa/` rendered navigation and footer expose the product link; untranslated locale templates were not modified.
- **Reversibility:** High — remove the two theme-asset enqueues, the dedicated shared footer mount and the three Persian navigation links without touching book data or routes.
- **Date:** 2026-09-23

## ADR-AB-0015 — PDF request intake: storage-free, per-field validated, provider-gated with an opaque bounded reference contract

- **Decision:** `/ai-book/request-pdf/` is served by `KBK_AI_Book_Request`. Nothing is persisted locally — ever. Submissions validate per field first (name optional ≤80; email required, RFC-valid, ≤120; `use` enum {personal, education, research}; reason optional ≤500; consent must be exactly `1`), returning `INPUT_INVALID` with per-field codes; only clean submissions reach the provider (`KBK_AI_BOOK_REQUEST_ENDPOINT` + `KBK_AI_BOOK_REQUEST_API_KEY`, 30s timeout, bearer key never logged). The provider response contract (`validate_provider_response`): bounded JSON ≤2048, `status` ∈ {ok, rejected}, and on `ok` a reference that is 1..32 chars of `[A-Za-z0-9_-]` — anything else is discarded (`PROVIDER_INVALID`); transport errors and non-2xx degrade to `PROVIDER_UNAVAILABLE`; explicit rejection is an honest `REJECTED` state. Status truth for the view: without configuration the submit button is disabled and the state is `CONFIG_REQUIRED` — the form never pretends to submit.
- **Context:** The prompt requires a request/issuance workflow with configuration-required delivery, no invented credentials, and storage-free bounded behavior; the issuance pipeline itself remains deferred and human-gated.
- **Alternatives:** (a) Storing requests in the WordPress database — rejected: PII storage without a reviewed retention policy violates the storage-free contract; the provider owns the record. (b) Accepting free-text `use` — rejected: enum keeps the provider payload normalized and bounded. (c) Echoing provider errors verbatim — rejected: only four honest states are rendered; provider internals never reach the page.
- **Rules (tested):** every invalid field is flagged independently; valid input without a provider never fakes success; the provider payload is exactly `{edition, name, email, use, reason}`; the API key appears only in the Authorization header; all four provider outcomes (unavailable/invalid/rejected/submitted) render distinct honest states; reposted values are sanitized on redisplay.
- **Freeze note:** no new CSS — the form and process aside reuse frozen `.ab-form-layout`/`.ab-request-form`/`.ab-form-grid`/`.ab-consent`/`.ab-process` components. Design status remains FROZEN_REVISION_2.
- **Reversibility:** High — intake is one class behind `KBK_AI_Book::request_engine()`; adding a real issuance pipeline later replaces the provider call only.
- **Date:** 2026-09-20
# ADR-AB-0021 — Docs-style Reader candidate (2026-09-25)

Owner explicitly requested a GitHub Docs-inspired Reader, including its green navigation marker. Reference: https://docs.github.com/en/rest/authentication/authenticating-to-the-rest-api?apiVersion=2026-03-10#basic-authentication . Measured current dark marker `#5fed83`; use `#1a7f37` for light-mode contrast. Scope CSS to Reader, preserve Persian font, RTL, shared site chrome, all content/provenance and existing route contracts. Keep two columns rather than adding an empty third rail. Remove continuous source border; retain origin label. Tighten paragraph rhythm and separate section headings with rules. Track active chapter section through passive/rAF-throttled scroll handling without rewriting URLs. Existing root scroll padding plus section margin yields ~112px anchor offset. No framework/type-system migration. Candidate awaits owner approval; revision 6 is the previous frozen baseline. No production action authorized by this UI request.
# ADR-AB-0022 — Personal reading workspace

Owner approved implementing the proposed uncluttered book-reader experience. Use progressive enhancement (existing no-JS reader remains), no dependencies or framework migration. Default focus mode hides site chrome with an always-available return control; width remains bounded at 1100px for legibility, standard 760px. Local preferences accept only bounded integer font size, allowlisted line spacings and booleans. Paper is Reader-only; shared dark/light preference remains compatible. Chapter disclosure closes on selection/Escape with focus handling. This is an owner-review candidate, not a production release or browser Fullscreen API feature. Canonical book contents untouched.
# Audit remediation — lossless search pagination (2026-09-29)

Preserve the PHP search API's existing first two arguments; add an optional integer page argument. Return page/pages, clamp to actual last page, use existing allowlisted kbk_page. Promote glossary within the full ranked sequence before slicing, rather than replacing the final first-page result; this preserves every result without cross-page duplication. Reuse existing button styles and server-rendered links, with no JS dependency, framework migration or canonical content edits. Full-corpus/ID-prefix pagination tests cover count, uniqueness and bounds. Visual review remains pending, not a new design freeze.

# Revision 9 — local presentation and link repair

Separate immutable corpus citation URLs from website navigation. Render current local/site Reader links; redirect only repository-resolvable legacy part/chapter paths, fail closed on unknown/ambiguous paths. Browser retains section fragment. Use dir=auto for source paragraphs/list items; no source rewriting. Covers are presentation assets, not replacements for canonical PDFs. QR is deterministic and decoded in a test, not generated artwork. Reuse original 512px logo without inventing a new identity. Blog is editorial, independently attributed and non-official; no SEO/GEO ranking guarantees. No production writes.
