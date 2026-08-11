# News Architecture — Layer B

> Design for the AI & Quantum news system. **Foundation implemented** (`KBK_News` + `fixtures/sources.json` + `kbk_source` taxonomy + REST `/sources`). Auto-fetching + auto-publishing are **OFF by default** and never reach production unattended. Aligned with Agent.md addendum §13.

## 1. Channels (under `/news/`)
AI News · Enterprise AI News · Quantum News · AI Security News · Post-Quantum Security News · Research News · Product & Platform News · Regulation & Governance News. Filterable via `kbk_topic` + domain flags (`kbk_ai_domain` / `kbk_quantum_domain`).

## 2. Pipeline stages
```
Collect → Normalize → Deduplicate → Rank → Verify → Extract Entities
  → Connect to KG → Draft → Analyze → Cite → Review → Translate → Schedule → Publish → Monitor
```
- **Collect**: only from the allowlisted `kbk_source` registry; SSRF-guarded (`KBK_News::is_safe_remote_url`).
- **Verify**: source credibility check; preprints (arXiv) flagged.
- **Draft**: created as `kbk_news`, `post_status=draft`, `evidence_status=unverified`.
- **Review**: human gate before `published` (CONTENT_POLICY §5).
- **Publish**: never automatic to production.

## 3. Per-article content requirements
original headline · source attribution · publication date · event date (if different) · factual summary · technical explanation · enterprise impact · risk analysis · opportunity analysis · AI context · quantum context · cybersecurity implications · related evergreen topics · sources · update history · editorial status.

## 4. Freshness rules (no artificial date bumps)
Maintain separately: `date_published` · source date · event date · `kbk_last_reviewed` · `date_materially_updated` · translation date. A material update must reflect real content change.

## 5. Duplicate detection
Match on: canonical source URL · normalized title · entity overlap · event identity · semantic similarity · publication window. Multiple sources → one synthesized event record with multiple citations.

## 6. Copyright
No source copying. No sentence-by-sentence paraphrase. Short attributed quotes only. Original synthesis + analysis. No protected images without permission.

## 7. Implementation status (Phase 8)
- ✅ `fixtures/sources.json` registry (16 AI + 10 Quantum sources).
- ✅ `kbk_source` taxonomy seeded by `KBK_News::install_sources()` on activation.
- ✅ REST `GET kohandezh/v1/sources` (public, read-only).
- ✅ SSRF guard `KBK_News::is_safe_remote_url()` (scheme + private-host + allowlist).
- ✅ `KBK_News::ingest($url)` — creates **draft + unverified** post; gated behind `KBK_FEATURE_NEWS_FETCH` (OFF by default → no network calls in MVP).
- ⏳ Collectors, NLP analysis, dedupe, scheduler — future phases; behind flags; never auto-publish.

## 8. Feature flags (wp-config.php)
`KBK_FEATURE_NEWS` (CPT on/off) · `KBK_FEATURE_NEWS_FETCH` (ingestion on/off, default **false**).
