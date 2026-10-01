# AI Book Repository Discovery

Last verified: 2026-09-19

## Boundaries

- Website root: `/Users/emperor/Documents/AI/kohandezh.com`.
- Canonical book root: `/Users/emperor/Documents/AI/AiBook`. The prompt's `Document/Ai/AiBook` path does not exist; this corrected path is verified.
- The two roots are separate Git repositories. Canonical book artifacts are read-only inputs to the website integration.
- Never read or copy the book `.env`, development private signing key, or other secrets.

## Existing website stack

The website is a static-source-generated PHP/WordPress system, not a TypeScript application. Root static HTML is the Layer-A source of truth. `_tooling/wp-theme/sync-from-static.py` generates the WordPress theme. Additive knowledge features live in `_tooling/wp-theme/kohandezh-knowledge/` and must not add global cost or behavior to Layer A.

Relevant implementation:

- `assets/css/blog.css`: strongest existing long-form token and component baseline.
- `assets/css/knowledge.css`: logical-property RTL patterns and accessible tabs.
- `assets/fonts/estedad/` and `assets/fonts/iransansx/`: local Persian fonts.
- `_tooling/wp-theme/kohandezh-knowledge/`: isolated Layer-B plugin, routes, CPTs, taxonomies, REST, and schema.
- `_tooling/wp-theme/kohandezh-ai-hub/`: pre-existing AI provider/chat plugin; inspect before Ask/RAG integration.

No TypeScript migration is authorized. PHP typing, sanitization, validation, and the repository's current conventions are the target.

## Canonical book inventory

The final Persian release is edition `2026E1`: 7 parts, 53 chapters, 479 canonical content sections, 64 crawlable RTL HTML pages plus utility pages, 2,427 RAG chunks, and a knowledge graph with 3,671 nodes and 10,474 edges. Primary inputs are:

- `master/book.json` and `master/book.md`
- `provenance/content_ids.json` and `citation-registry.json`
- `knowledge/chunks.jsonl`, `graph.json`, `entities.jsonl`, `relations.jsonl`
- glossary, source map, templates, public verification files, PDF, and release manifest

Origins must remain visible: 365 `source_translation`, 53 `editorial_synthesis`, and 61 `editorial_localization_ir` sections. Preserve the NIST non-endorsement notice.

## Constraints and risks

1. Preserve canonical `/fa/ai-book/.../` URLs and stable anchors/IDs.
2. Validate every external JSON/JSONL artifact at ingestion; shapes differ (`book.units` is a number, source map is an array, registries are keyed objects).
3. Never confuse 479 content IDs, 2,427 RAG chunk IDs, or KG entity IDs.
4. Do not copy generated book HTML/CSS wholesale into global `/assets`; adapt semantics into namespaced plugin components.
5. Resolve the existing `/knowledge/` static-page versus CPT-archive ownership collision before adding rewrites.
6. Investigate the three P06 chapters that currently share a truncated generated route before importing them.
7. Lazy-load/filter the KG; never render 10,474 edges at once, especially on mobile.
8. Keep the 1,292-page PDF off normal page payloads; PDF delivery is Phase B.
9. Current release is development-signed and requires the publisher signing key before being described as publisher-signed.
