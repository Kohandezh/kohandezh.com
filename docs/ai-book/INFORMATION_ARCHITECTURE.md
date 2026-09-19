# AI Book Information Architecture

## Route ownership strategy

`/ai-book/*` is the Knowledge Hub/application shell required by the project master prompt. Existing `/fa/ai-book/*` paths remain canonical content URLs and stable citation anchors. During the visual phase, Hub routes are `noindex`; functional integration must either deep-link to canonical Persian chapter anchors or serve them without creating indexable duplicates. No existing citation URL changes.

| Route | Purpose / primary action | Desktop / mobile structure | Canonical data | Loading / empty / error state |
| --- | --- | --- | --- | --- |
| `/ai-book/` | Book identity and entry point; start reading | Hero, edition stats, seven parts, credibility; stacked cards on mobile | Book metadata and part list | Skeleton cards; release unavailable; honest error with retry |
| `/ai-book/read/` | Reading workspace; continue chapter | Sticky TOC + content + related rail; content-first with horizontal TOC/mobile drawers | `master/book.json`, citations/provenance | Section skeleton; no chapter; invalid ID/404 |
| `/ai-book/search/` | Search Persian/English content | Search/filter/results; filters collapse on mobile | Search index + canonical anchors | Progress/count; no matches with suggestions; index unavailable |
| `/ai-book/ask/` | Ask only against book evidence | Conversation + citation cards; one-column mobile composer | RAG chunks, provider abstraction | Retrieval indicator; no evidence; provider/config failure |
| `/ai-book/concepts/` | Browse concepts and relationships | Filter toolbar + cards/detail; stacked mobile | entities, glossary, relations | Card skeleton; no concepts; bounded adapter error |
| `/ai-book/graph/` | Explore graph slice | Canvas + detail, accessible list below; single column mobile | graph nodes/edges/evidence | Slice loader; no connected nodes; list fallback on graph failure |
| `/ai-book/templates/` | Find usable organizational templates | Filter + template cards | `templates/TPL-*.json` | Skeleton; no matching template; malformed template excluded |
| `/ai-book/sources/` | Audit the 18 sources and mappings | Source cards/table + detail | sources, source map, bibliography | Skeleton; no mapping; unavailable audit data |
| `/ai-book/glossary/` | Find approved Persian terminology | Bilingual search/cards/detail | approved glossary | Skeleton; no term; unsupported record excluded |
| `/ai-book/pdf/` | Read the canonical PDF | Viewer + edition panel; mobile full-width viewer | canonical PDF + release metadata | PDF.js progress; unavailable document; text/HTML alternative |
| `/ai-book/request-pdf/` | Request a personalized derivative | Form + issuance steps; form first on mobile | request/issuance records | submitting; field errors; provider/config error |
| `/ai-book/cite/` | Generate honest citations | scope/ID controls + output | citation registry | resolving; unknown ID; registry error |
| `/ai-book/verify/` | Verify edition/content/hash | input + verification card | provenance/public verification assets | checking; not found; signature status unavailable |

## Navigation model

Hub → Reader → canonical Part → Chapter → stable Section anchor. Primary navigation exposes Reading, Search, Ask and Concepts. Sources and Verification are persistent trust utilities. Graph, Glossary, Templates, PDF, Citation and Request PDF are reachable from context and the mobile menu.

Search results link to canonical section anchors with hierarchy, excerpt, origin, source and content ID. RAG answers are non-canonical response objects; every supported statement links to content IDs and reader anchors. KG nodes link to evidence-bearing sections and offer a list alternative.

## Ownership and locale

Canonical content/provenance stay read-only in the AiBook repository. WordPress owns presentation, Hub routing, validation, access control, request workflow and caches. Prefer an isolated `kohandezh-knowledge` module unless the route spike proves an adjacent plugin safer. Persian is the only active locale; no fake pages or hreflang are emitted.
