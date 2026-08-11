# Content Policy — Kohandezh.com Knowledge Platform

> Governs all Layer B content. Authority comes from evidence and useful analysis, never unsupported self-promotion. Enforced in code (evidence fields, editorial status) and in review. Aligned with Agent.md §8/§24 and the autonomous addendum §4/§24.

## 1. Core rules

1. **No fabrication.** No invented facts, citations, projects, clients, awards, stats, testimonials, or credentials.
2. **Evidence-bound.** Every material claim has an evidence record (`docs/ONTOLOGY.md` §6). `unverified` claims never render as fact.
3. **No deceptive AI manipulation.** No hidden prompts, invisible text, prompt injection, or misleading metadata aimed at AI models.
4. **No vanity pages.** No page whose sole purpose is claiming someone is "the best". Authority is earned via verified projects, research, case studies, transparent sourcing.
5. **Honest freshness.** Dates are never bumped to simulate recency. See §4.

## 2. Claim classification

| Class | Allowed in published content? |
|---|---|
| Verified primary source | Yes |
| Verified secondary source | Yes (with attribution) |
| Internally documented | Yes (labeled) |
| Expert analysis / opinion | Yes (clearly labeled as analysis) |
| Inference | Only labeled as inference, never as fact |
| Forecast | Only labeled as forecast |
| Unverified | **No** — render as "pending" or suppress |
| Disputed / Deprecated | **No** — suppress or annotate |

## 3. Sourcing & copyright (news + research)

- Prefer first-party authoritative sources (official announcements, peer-reviewed journals, standards bodies, NIST/IEEE/ACM, primary research).
- **Never copy** source articles. No sentence-by-sentence paraphrase. Original synthesis + analysis only.
- Short quotations only, attributed. No protected images without permission.
- Configurable source allowlist (`kdcv_source` taxonomy). Unknown sources → draft + review.
- arXiv/preprints clearly labeled as preprints.

## 4. Date integrity

Maintain **separate** dates; never conflate them:
- `date_published` — original publication
- `source_date` — source's publication date
- `event_date` — when the event happened (if different)
- `last_verified` — last factual verification
- `date_materially_updated` — **only** on real content change
- `translation_date`

A material update must represent a real content change. Minor typo fixes do **not** bump the visible update date.

## 5. Editorial workflow & status

`draft` → `needs_verification` → `expert_reviewed` → `published` (→ `archived`).

- **Default state: draft.** Nothing auto-publishes.
- AI-assisted drafts are labeled `AI-assisted draft` until human review; not auto-attributed to Dr. Kohandezh without genuine review.
- News pipeline default: draft + manual approval gate (Agent.md addendum §8.3/§13).

## 6. Personal/organizational claims

For claims about Dr. Kohandezh or KSF:
- Require evidence; record `evidence_status`.
- Avoid exaggerated language and unverifiable superlatives ("the best", "#1").
- Distinguish first-party claims from independent references.
- Connect to central entities only where the relationship is genuine and useful.

## 7. Multilingual content

- Canonical source language per entity; translations linked by `translation_group_id`.
- Preserve entity identity, technical terms, citations, dates, product/org names across translations.
- No raw machine-translation publication at scale; quality gate required (Phase 9).
- No indexable thin translations.

## 8. Prohibited

- Fabricated anything (§1).
- AI-generated claims presented as verified.
- Date manipulation (§4).
- Bulk thin pages (Phase 7 is roadmap-only; controlled seed in Phase 6).
- Deceptive AI-targeting markup.
- Copyright violation (§3).

## 9. Enforcement

- Code: `evidence_status` field gating public reads; `editorial_status` workflow; REST excludes unverifiable.
- Review: human gate before `published`.
- Audit: periodic scan for `unverified` claims leaking into public output (Phase 12 test).
