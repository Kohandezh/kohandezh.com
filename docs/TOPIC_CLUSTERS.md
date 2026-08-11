# Topic Clusters — Layer B

> The cluster architecture connecting the Enterprise AI Hub, the Quantum Hub, and cross-cutting industries/technologies. Each cluster = 1 pillar (root) + supporting articles + glossary + FAQ + related news. Aligned with Agent.md addendum §8.1/§8.2 and the `kbk_topic`/`kbk_ai_domain`/`kbk_quantum_domain`/`kbk_industry`/`kbk_tech` taxonomies.

## Cluster model

```
Hub (CollectionPage)
└─ Pillar (root article, kbk_knowledge)
   ├─ Cluster articles (kbk_knowledge, kbk_case, kbk_research)
   ├─ Glossary terms (kbk_glossary, DefinedTerm) linked via kbk_topic
   ├─ FAQ entries (FAQPage) linked via kbk_topic
   └─ News (kbk_news) linked via kbk_topic + domain flag
```

All elements share a `kbk_topic` term → one query surfaces the cluster. Cross-cluster links flow through shared `kbk_industry`/`kbk_tech` terms.

## Enterprise AI clusters (kbk_ai_domain)
Strategy · Governance · Responsible AI · AI Security · Generative AI · LLMs · RAG · Vector Databases · Knowledge Graphs · AI Agents · Multi-Agent Systems · AI Infrastructure · Private AI · Enterprise Automation · AI ROI · AI Centers of Excellence.

## Quantum clusters (kbk_quantum_domain)
Fundamentals · Hardware · Algorithms · Software · Networking · Quantum AI · Quantum ML · Post-Quantum Cryptography · Quantum Key Distribution · Quantum Cloud · Quantum Readiness · PQC Migration.

## Cross-cutting (kbk_industry × both hubs)
Healthcare · Banking · Insurance · Government · Telecom · Manufacturing · Energy · Education · Retail · Logistics — each industry links to BOTH the relevant AI pillar(s) and Quantum pillar(s), enabling:
- `/industry/healthcare/` surfaces "AI in Healthcare" + "Quantum for Healthcare"
- bidirectional internal linking (a P3 AI pillar links up to its industry, which links to the Quantum equivalent).

## Cross-cutting (kbk_tech)
Transformer · Diffusion · Vector Embedding · HNSW · Knowledge Graph · Quantum Annealing · Lattice Cryptography — technologies cited by multiple pillars, each with its own glossary entry.

## Cluster seed (Phase 6 fixtures → expand)
- glossary: generative-ai, large-language-model, retrieval-augmented-generation, quantum-computing, post-quantum-cryptography ✅
- cluster roots (drafts): ai-strategy, quantum-readiness ✅ (need review + evidence before publish)

## Maintenance
- Each cluster has an owner reviewer + `last_reviewed` date.
- Quarterly freshness audit; stale clusters flagged `needs_verification`.
- No cluster published unless root pillar + ≥2 supporting pieces + ≥1 glossary term exist.
