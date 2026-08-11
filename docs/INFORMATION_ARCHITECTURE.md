# Information Architecture — Layer B

> Additive URL architecture for the Knowledge Platform. No existing Layer A URL is altered. All routes conflict-checked against the static site, sitemap, and theme rewrites (see `URL_INVENTORY.md`). Aligned with Agent.md addendum §8.

## 1. Chosen Layer B top-level routes

| Route | Purpose | Conflict check |
|---|---|---|
| `/enterprise-ai/` | Enterprise AI hub landing + cluster root | ✅ absent on disk, no theme slug, no rewrite |
| `/quantum/` | Quantum computing hub landing + cluster root | ✅ absent |
| `/knowledge/` | Cross-domain knowledge index (all CPT archives) | ✅ absent |
| `/research/` | Research articles | ✅ absent |
| `/case-studies/` | Case studies | ✅ absent |
| `/insights/` | Analysis/opinion (clearly labeled) | ✅ absent |
| `/news/` | News index (AI + Quantum channels) | ✅ absent |
| `/glossary/` | DefinedTerm glossary | ✅ absent |
| `/faq/` | FAQ knowledge base (FAQPage) | ✅ absent |
| `/entity/{slug}` | Stable canonical entity URI (KG) | ✅ absent (new) |

REST: `kohandezh/v1/{entities,topics,articles,graph}` (new namespace; existing `kdcv/v1` untouched — ADR-0003).

> No Layer A route is changed. Verified: no static file/folder, no theme page slug (`kdcv_required_pages`), no WP rewrite, no taxonomy base collides with the above.

## 2. Topic clusters

### 2.1 Enterprise AI cluster (`kdcv_ai_domain`)
Enterprise AI · AI Strategy · Generative AI · AI Agents · Multi-Agent Systems · LLMs · RAG · Vector Databases · Knowledge Graphs · AI Governance · Responsible AI · AI Security · Private AI · AI Infrastructure · Enterprise Automation · AI in Healthcare/Government/Banking/Insurance/Telecom/Manufacturing/Energy/Education/Retail/Logistics · Digital Transformation.

### 2.2 Quantum cluster (`kdcv_quantum_domain`)
Quantum Computing · Quantum Hardware · Quantum Algorithms · Quantum Networking · Quantum AI · Quantum Machine Learning · Quantum Security · Post-Quantum Cryptography · Quantum Startups · Quantum Investments · Quantum Cloud Platforms · Quantum Research · Quantum Companies · Quantum Education · Quantum Timeline · Quantum Industry Adoption.

### 2.3 Cross-cutting taxonomy (`kdcv_industry`, `kdcv_tech`)
Industries and technologies link **both** clusters (e.g., "AI in Banking" + "Quantum for Banking" share the `banking` industry term), enabling cross-cluster internal linking.

## 3. News architecture (channels)

Under `/news/`, separate but related channels (filterable via `kdcv_topic`/domain flags):
- AI News · Enterprise AI News · Quantum News · AI Security News · Post-Quantum Security News · Research News · Product & Platform News · Regulation & Governance News.

News connects to evergreen knowledge pages via `related_entities` (ONTOLOGY §5) — never an isolated stream. Each news article links to its parent topic(s); each topic page surfaces related news.

## 4. URL patterns

- Hub landing: `/{hub}/` (e.g., `/enterprise-ai/`)
- Topic/Pillar: `/{hub}/{topic-slug}/` (e.g., `/enterprise-ai/rag/`)
- Article: `/knowledge/{slug}/` (knowledge CPT) — single URL space, classified by taxonomy (not per-hub URL) to avoid duplicate content.
- News: `/news/{slug}/`
- Glossary: `/glossary/{term-slug}/`
- Entity: `/entity/{entity-slug}/`
- API: `/wp-json/kohandezh/v1/…`

## 5. Sitemap integration

Layer B CPT archives + entity pages added to a sitemap provider at Phase 6/13. If production uses an SEO plugin (PRODUCTION_UNKNOWNS U2), defer to its sitemap; otherwise extend the static `sitemap.xml` generation. No existing sitemap entries removed.

## 6. Implementation note
Routes are produced by the `kohandezh-knowledge` plugin (ADR-0001) via CPT `has_archive` + `rewrite` + a virtual `entity` endpoint. All conditionally loaded — Layer A untouched.
