# AI Book Information Architecture

## Canonical route family

Preserve the existing public namespace and anchors:

- `/fa/ai-book/` — book home
- `/fa/ai-book/{part-slug}/` — seven part indexes
- `/fa/ai-book/{part-slug}/{chapter-slug}/` — chapter pages
- `/fa/ai-book/glossary/`, `/sources/`, `/templates/`, `/search/`
- `/ai-book/verify/` — edition/content verification (existing canonical form)
- PDF viewer/request routes are reserved for Phase B and must not mutate the master PDF.

Current part slugs: `foundations`, `ai-risk-management`, `ai-security`, `trust-bias-explainability`, `genai-evaluation`, `ai-law`, `standards`.

## Navigation model

Book home → Part → Chapter → stable Section anchor. Global book utilities expose Search, Glossary, Sources, Templates, Verification, and later Ask/RAG, Knowledge Graph, and PDF. Breadcrumbs remain explicit and crawlable.

Search results link to the canonical section anchor and show part/chapter, excerpt, origin, and source. RAG answers are not canonical content; each claim must point to one or more canonical content IDs. KG nodes link back to evidence-bearing sections.

## Ownership

Canonical content and provenance remain in the AiBook repository. The website integration consumes validated release artifacts. WordPress owns presentation, routing, access control, request workflow, and caches; it does not rewrite the book master.

Implement the book as an isolated subsystem of `kohandezh-knowledge` unless a route-ownership spike proves an adjacent plugin safer. Either choice must keep assets and hooks Layer-B-only. Do not create a new framework.

## Locale architecture

Persian is the only active book locale. Future locales share translation groups and stable conceptual relationships, but no route or hreflang is emitted until real localized content exists.
