# AI Book Content Source Map

Verified canonical root: `/Users/emperor/Documents/AI/AiBook` (read-only). Website adapters must reject missing, malformed, mismatched-edition, or unsupported inputs. They must never mutate this root.

| Capability | Canonical artifact(s) | Contract and use | Public exposure |
| --- | --- | --- | --- |
| Reader | `master/book.json`, `master/book.md` | `book.json` is authoritative structured input: 7 parts, 53 chapters, 479 sections; preserve section text, origin, IDs, slugs, source links and hashes | Render bounded route/section HTML only |
| Search | `html/search/index.json`; canonical headings/text from `master/book.json` | Build a bounded Persian/English index containing title, excerpt, hierarchy, origin, source and canonical anchor | Search endpoint/index only; never raw master or corpus dump |
| Ask / RAG | `knowledge/chunks.jsonl` | 2,420 chunks; retrieve by `chunk_id`, return section `content_id`, source documents/pages/blocks and canonical URL with every answer | Server-side retrieval; do not publish raw JSONL |
| Concepts | `knowledge/entities.jsonl`, `knowledge/relations.jsonl`, `translation_memory/glossary.json` | Concept identity, labels, mentions and evidence relationships | Filtered/paginated read models |
| Knowledge Graph | `knowledge/graph.json`, `knowledge/graph.graphml` | 3,671 nodes and 10,474 edges; convert/filter only when needed; evidence belongs to edges | Bounded graph slices and accessible list, never full eager payload |
| Glossary | `translation_memory/glossary.json`, `release/09_glossary.json`, `release/08_glossary_fa_en.csv` | Approved Persian term, English term/acronym, definition, domain, sources and review status | Per-term pages/search; no reviewer secrets |
| Sources | `manifests/sources.json`, `master/source_map.json`, `master/bibliography.json` | Exactly 18 unique source documents; `source_map.json` is an array | Audited metadata and section mappings |
| Templates | `templates/TPL-*.json` | Structured template cards/forms; preserve template IDs and canonical Persian copy | Individual template views, not bulk export |
| PDF | `pdf/fa/book-fa.pdf`, `provenance/release-manifest.json` | Immutable 1,292-page canonical PDF; later viewer and controlled delivery | Dedicated viewer/delivery only; never normal page payload |
| Citation | `provenance/citation-registry.json`, `master/citation_map.json` | Registry is keyed by structural ID and includes content ID, titles, origin, URL, citation and sources | Citation formatter for book/part/chapter/section |
| Content IDs | `provenance/content_ids.json` | 479 immutable content IDs mapped to structural ID, hierarchy, origin, hash and locale | Display/lookup one ID at a time |
| Verification | `provenance/hashes.json`, `merkle-root.txt`, `merkle-tree.json`, `release-manifest.json`, `.sig`, `public-key.pem`, `verification.json` | Verify edition/hash/Merkle/signature status; current release is development-signed and requires publisher signing | Public key and safe verification result only; never private key |
| Locale | `config/multilingual.yaml`, per-section `locale` | Persian only now; future adapters must be locale-aware without inventing content | Emit only existing Persian routes/hreflang |

## Identifier boundaries

- Structural section: `KDJ-AI-2026E1-Pxx-Cxx-Sxx`.
- Immutable content: structural ID plus eight-character hash suffix.
- RAG chunk: content ID plus `#rNN`.
- Source block: `DOC::Pnnnn::SECx::Bnnnn`.
- KG entity: independent `entity_id` namespace.

These identifiers are related but never interchangeable.

