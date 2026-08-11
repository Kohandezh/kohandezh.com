# Ontology & Taxonomy — Kohandezh.com Knowledge Platform

> A **practical** ontology (implementable in WordPress CPTs + post meta + one claims table), not an academic abstraction. Stable IDs, explicit relationships, evidence-bound. Aligned with Agent.md addendum §9.

## 1. Design principles

1. **Stable canonical IDs.** Every entity has a URI: `https://kohandezh.com/entity/{slug}` (deterministic, immutable).
2. **Evidence-bound.** No claim about a real entity is published without an evidence record (Phase 4 claim/evidence model).
3. **Schema.org-aligned where valid** — but graph concepts are NOT forced into invalid schema properties; internal JSON carries the full graph, JSON-LD carries the valid subset.
4. **WordPress-native.** Entities are CPT posts; relationships are post-meta or a lightweight link table; taxonomies classify.

## 2. Entity types (CPTs)

> Phase 5 will register only what no existing equivalent provides. Inspect `kohandezh-ai-hub` first to avoid duplicates.

| Type | CPT slug (proposed) | Notes |
|---|---|---|
| Knowledge Article (pillar/cluster) | `kdcv_knowledge` | main Layer B content |
| News Article | `kdcv_news` | event-driven, time-bounded |
| Case Study | `kdcv_case` | evidence-backed |
| Research | `kdcv_research` | publication-linked |
| Project | `kdcv_project` | real projects only |
| Person | `kdcv_person` | central: Dr. Kohandezh + referenced people |
| Organization | `kdcv_org` | KSF, partners, vendors (neutral) |
| Glossary Term | `kdcv_glossary` | `DefinedTerm` |

### Fields (post meta) common to all entities
`entity_id` (canonical URI) · `summary` · `language` · `translation_group_id` · `schema_type` · `evidence_status` · `last_reviewed` · `date_published` · `date_materially_updated` · `editorial_status` · `related_entities` (list) · `source_refs` (list).

## 3. Central entities (verified, stable IDs)

| Entity | Canonical ID | Evidence basis |
|---|---|---|
| Dr. Mohammad Ali Kohandezh | `https://kohandezh.com/entity/mohammad-ali-kohandezh` | llms.txt + CV (first-party) |
| Kohan System Farda (KSF) | `https://kohandezh.com/entity/kohan-system-farda` | llms.txt (ksf.ir) |
| Padyar | `https://kohandezh.com/entity/padyar` | Agent.md mentions; **verify before publish** |

> No claims about central entities beyond what is sourced in `llms.txt`/CV. Unverified items stay `evidence_status=unverified`, never displayed as fact.

## 4. Taxonomies

| Taxonomy | Slug | Applies to | Purpose |
|---|---|---|---|
| Topic | `kdcv_topic` | knowledge/news/case/research | primary classification, cluster root |
| Industry | `kdcv_industry` | all | healthcare/banking/gov/… |
| Technology | `kdcv_tech` | knowledge/case/research | RAG, LLM, QKD, … |
| AI Domain | `kdcv_ai_domain` | knowledge/news | EA cluster flag |
| Quantum Domain | `kdcv_quantum_domain` | knowledge/news | Quantum cluster flag |
| Evidence Status | `kdcv_evidence` | all | vocabulary (see §6) |
| Content Type | `kdcv_content_type` | all | pillar/cluster/glossary/faq/news |
| News Source | `kdcv_source` | news | allowlisted source org |

## 5. Relationship types (predicates)

Implemented as directed edges in a `kdcv_graph` link table (`subject_id`, `predicate`, `object_id`, `claim_id`, `weight`) + mirrored into post meta for fast queries.

`founded` · `leads` · `worksFor` · `created` · `developed` · `authored` · `published` · `spokeAt` · `participatedIn` · `advises` · `specializesIn` · `relatedTo` · `usesTechnology` · `appliesToIndustry` · `solvesProblem` · `supportedByEvidence` · `mentionedBy` · `cites` · `derivedFrom` · `translatedFrom` · `updates` · `supersedes` · `partOfTopicCluster` · `relatedCaseStudy` · `relatedProject` · `relatedOrganization`.

## 6. Claim & evidence model

Every material claim is a row in `kdcv_claims`:

| Field | Example |
|---|---|
| `claim_id` | `c_0014` |
| `subject` | entity URI |
| `predicate` | `developed` |
| `object` | entity URI |
| `evidence_url` | primary source URL (or internal ref) |
| `evidence_type` | `primary`/`secondary`/`internal`/`user`/`unverified`/`disputed`/`deprecated` |
| `verification_status` | `verified`/`pending`/`disputed`/`deprecated` |
| `confidence` | 0.0–1.0 |
| `first_verified` / `last_verified` | dates |
| `reviewer` | WP user ID |
| `notes` | text |

**Rule:** `evidence_type=unverified` claims are never rendered as established facts; they render with a "pending verification" badge or are suppressed.

## 7. Machine-readable outputs

- **JSON-LD** (valid subset): `Person`, `Organization`, `Article`, `NewsArticle`, `TechArticle`, `FAQPage`, `BreadcrumbList`, `DefinedTerm`, `ItemList`. Stable `@id` = canonical entity URI.
- **Internal entity JSON** (full graph): `REST GET /wp-json/kohandezh/v1/entities/{id}` returns entity + relationships + claims.
- **Topic graph export**: `REST GET /wp-json/kohandezh/v1/graph`.
- **Sitemap integration**: Layer B CPT archives added to a sitemap provider (respecting any existing SEO plugin — see PRODUCTION_UNKNOWNS U2).
- **llms.txt integration**: append Layer B hub URLs to `llms.txt` (Phase 6/13).

## 8. Topic clusters (seed — full map in Phase 3/7)

- **Enterprise AI cluster:** Enterprise AI, AI Strategy, Generative AI, AI Agents, Multi-Agent Systems, LLMs, RAG, Vector DBs, Knowledge Graphs, AI Governance, Responsible AI, AI Security, Private AI, AI Infrastructure.
- **Quantum cluster:** Quantum Computing, Quantum Hardware/Algorithms/Networking, Quantum AI, Quantum ML, Post-Quantum Cryptography, Quantum Cloud.
- **Cross-cutting:** industry applications (healthcare, banking, government, …) as `kdcv_industry` terms linking both clusters.

## 9. Validation (Phase 12)

- JSON-LD parses (`json_decode` + schema shape check).
- Every published entity has non-empty `entity_id` + `evidence_status`.
- No orphan edges (subject/object exist).
- `unverified` claims never in public reads.
