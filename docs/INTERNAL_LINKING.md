# Internal Linking Strategy — Layer B

> Contextual, semantic internal linking across the Knowledge Platform and back to Layer A. No spammy/excessive links; descriptive anchors. Aligned with Agent.md base §14.

## Linking rules
1. Every pillar links to: its hub, parent/child topics, ≥2 glossary terms, related case studies/research, and relevant news.
2. Every news article links back to ≥1 evergreen pillar + the relevant entity page.
3. Every glossary term links to its parent pillar + sibling terms.
4. Layer B → Layer A: a single, optional, contextual link from the hub/About to Dr. Kohandezh's CV only where genuinely relevant (his verified work). Never forced.
5. Layer A → Layer B: an additive, minimal link from the homepage "knowledge" area to `/enterprise-ai/` + `/quantum/` (Phase 13 homepage-additive gate; reversible; only if it doesn't hurt Core Web Vitals). **Not done until explicitly approved per the homepage-protection policy.**
6. Anchors are descriptive (the topic name), never "click here".
7. Entity pages cross-link via `kbk_related_entities` (ontology §5 predicates).

## Anchor inventory (conventions)
- Pillar → pillar: anchor = target pillar title.
- Pillar → glossary: anchor = the term.
- News → pillar: anchor = the pillar topic name.
- Entity → entity: anchor = target entity name, predicate implied by context.

## Bidirectional integrity (Phase 12 test)
- If A links to B, B's "related" should acknowledge A where appropriate (not strictly symmetric for hub→leaf, but symmetric for entity↔entity relationships).
- Broken-link scan runs in the test runner (no href → nonexistent route/file).

## Related-content logic (plugin)
- `KBK_REST` already returns `topics`/`industries`/`related_entity_ids` per entity → a client (or server render) can build "Related" sections by matching shared `kbk_topic` terms.
- Future: a small `KBK_Related` helper to compute top-N related entities by shared-term count (cached, bounded) — Phase 6+ enhancement, conditional load only on single entity view.

## Schema expression
- `BreadcrumbList` (already emitted by KBK_Schema) encodes the hub→pillar→entity trail.
- `@id` cross-references in JSON-LD express `isPartOf` and entity identity (ONTOLOGY §7).

## Limits
- ≤ ~8 contextual internal links per pillar body (quality over quantity).
- No automated mass-link injection into Layer A.
- No links fabricated between entities that have no genuine relationship.
