# AI Book Frozen Design Specification

## Reader experience

The primary experience is a calm, evidence-first Persian reader. Desktop keeps book position, reading content, and local page context visible without compressing the text column. Tablet removes the secondary rail. Mobile turns the TOC into a horizontally scrollable chapter selector and leaves all reading content in one logical flow.

Reader measure is 52–70 characters for prose. Body text should be at least 16px with Persian-friendly line height around 1.9. Headings must remain readable with mixed Persian/English terms. Sticky UI may not cover focused headings.

## Page templates

1. Book home: edition identity, scope/non-endorsement, seven parts, task entry points.
2. Part index: description, sources, ordered chapters, origin/coverage summary.
3. Chapter reader: frozen prototype composition plus real section anchors.
4. Glossary: Persian term, English equivalent, definition, linked sections/concepts.
5. Sources: 18 audited sources and source-to-book mapping.
6. Templates: browsable template index and individual structured templates.
7. Search: query, bounded filters, result excerpts and canonical anchors.
8. Verification: edition/public-key status, content-ID/hash verification, honest development/publisher-signature status.
9. Ask/RAG, KG, PDF and request pages: Phase B functional surfaces using the same shell.

## Content origin presentation

Every section exposes a machine-readable and visible origin. Translation is the default prose treatment. Editorial synthesis and Iran localization use distinct labeled callouts; labels cannot rely on color alone. Derived sections link to their source content IDs. Citations show source document/page/block evidence when available.

## Interaction contracts

- TOC and in-page links use stable canonical anchors.
- Drawers/dialogs trap focus, close with Escape, restore focus, and prevent background interaction.
- Search is operable by keyboard and announces result counts.
- RAG response streaming, if used, never hides citations and distinguishes answer failure from no evidence.
- KG begins with filtered, list-accessible views; visualization is an enhancement, not the only interface.
- PDF viewer has a text alternative and never loads inside ordinary chapters.

## Data contract policy

This is PHP/WordPress. Do not introduce TypeScript. Define PHP value objects/arrays with documented shapes where consistent with the plugin, validate external JSON/JSONL at ingestion, reject unsupported schema versions, normalize locale and URL data, and keep raw canonical data immutable. Required domains: Book, Part, Chapter, Section, Content ID, Content Origin, Source, Concept, Search Result, RAG Citation, KG Node/Edge, PDF Request/Issuance, Citation, Provenance, and Locale.
