# AI Book Implementation Plan

## Phase 9 — Reader data integration (next)

1. Add a Layer-B-only book module and feature flag.
2. Define PHP schema validators/read models for book, provenance, citations, locale and origins.
3. Add deterministic import/build command using only current final artifacts.
4. Resolve P06 duplicate/truncated route mapping and `/knowledge/` ownership before rewrites.
5. Port the frozen static shell to isolated WordPress/PHP templates and connect book home, part index and chapter reader.
6. Add unit/fixture tests for malformed artifacts, IDs, URLs and escaping.

## Phase 10–12 — Discovery and knowledge surfaces

Implement bounded search, glossary, sources and templates. Search results must return typed/documented result shapes and canonical anchors without exposing raw internal corpus fields.

## Phase 11 — Ask/RAG

Integrate with existing AI provider boundaries only after inspecting `kohandezh-ai-hub`. Retrieve bounded evidence, return citation objects, enforce no-evidence behavior, and link every answer to canonical content IDs.

## Phase 13 — Knowledge graph

Serve filtered/paginated nodes and edges. Provide an accessible list/table alternative. Lazy-load visualization and cap mobile payloads.

## Phase 16–17 — PDF and requests

Activate PDF skill/policy. Build dedicated viewer and controlled request/issuance workflow. Preserve master immutability and record derivative/watermark provenance. Never claim DRM guarantees.

## Final gates — deferred reviews

Security review → technical SEO → performance → final browser/accessibility QA. No deploy, merge, or push in this task.
