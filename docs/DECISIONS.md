# Decision Records (ADR) — Kohandezh.com Knowledge Platform

> Lightweight architecture decision records. Each entry: Decision · Context · Alternatives · Rationale · Tradeoffs · Reversibility · Date. Aligned with Agent.md addendum §7.2.

---

## ADR-0001 — Layer B lives in a new plugin, not in the theme
- **Decision:** Build all Layer B (hubs, KG, news, glossary, FAQ, REST `kohandezh/v1`) in a **new plugin `kohandezh-knowledge`**, not in `kohandezhcv`.
- **Context:** Layer A has a strict static→theme sync invariant (`STATIC_WP_PARITY.md`). Putting Layer B in the theme would either require a static-source equivalent (none exists for DB-driven hub content) or break the sync parity model.
- **Alternatives:** (a) Layer B in theme `functions.php`; (b) Layer B in the existing `kohandezh-ai-hub` plugin; (c) Layer B in a new plugin.
- **Rationale:** (c) keeps Layer A parity clean and re-checkable; isolates Layer B behind a feature flag + plugin-deactivate rollback; avoids touching the pre-existing `kohandezh-ai-hub` whose scope is still unknown (PRODUCTION_UNKNOWNS U12).
- **Tradeoffs:** +1 plugin to maintain; slight conceptual split between "site" (theme) and "knowledge" (plugin). Outweighed by isolation + reversibility.
- **Reversibility:** High — deactivate plugin = Layer B gone, Layer A untouched.
- **Date:** 2026-07-25.

## ADR-0002 — Static repo root remains the canonical Layer A source
- **Decision:** Keep the static site at the repo root as source of truth; WP theme is generated.
- **Context:** Phase 0 re-flattening raised where the source of truth lives.
- **Alternatives:** Make WP the source of truth (edit in wp-admin, reverse-sync).
- **Rationale:** The static-first model is already battle-tested (prevents the 2026-07-11 drift bug class); preserves git-diffable, reviewable Layer A content; WP remains the production runtime for dynamic parts (blog, Layer B).
- **Tradeoffs:** Two-step deploy for Layer A (edit static → sync → upload theme). Acceptable; sync is now guarded + dry-runnable.
- **Reversibility:** Medium — reversing would require a reverse-sync pipeline. Not planned.
- **Date:** 2026-07-25.

## ADR-0003 — `kdcv/v1/ask` namespace stays with the `kohan-avatar` plugin
- **Decision:** Do **not** move or rename the existing `kdcv/v1/ask` route. New Layer B routes use a **new** namespace `kohandezh/v1`.
- **Context:** Audit found `kdcv/v1/ask` is owned by `kohan-avatar`, not the theme.
- **Alternatives:** Consolidate everything under one namespace.
- **Rationale:** Avoids touching a working integration; clear ownership boundary; `kohandezh/v1` reads as the knowledge-platform API.
- **Tradeoffs:** Two namespaces. Acceptable given different ownership.
- **Reversibility:** High.
- **Date:** 2026-07-25.

## ADR-0004 — Inspect `kohandezh-ai-hub` before any CPT registration
- **Decision:** Defer all CPT/taxonomy registration (Phase 5) until the pre-existing `kohandezh-ai-hub` plugin is fully read; reuse or explicitly supersede its registrations rather than duplicate.
- **Context:** A local `kohandezh-ai-hub/` plugin already exists with `admin/` + `includes/`. Unknown whether it registers CPTs/taxonomies that collide with the planned Layer B model.
- **Alternatives:** Register Layer B CPTs now and resolve collisions later.
- **Rationale:** Duplicate CPTs/taxonomies cause rewrite-rule + DB confusion; cheap to inspect first, expensive to undo.
- **Tradeoffs:** Slight delay to Phase 5 implementation.
- **Reversibility:** N/A (a sequencing rule).
- **Date:** 2026-07-25.

## ADR-0005 — `Agent.md` remains the canonical agent file (no `AGENTS.md`)
- **Decision:** Keep `Agent.md` as the single canonical agent instruction file (now including the autonomous addendum). Do **not** create a competing `AGENTS.md`.
- **Context:** Agent.md base §23 lists `AGENTS.md` as a doc to create; the autonomous addendum §3 says resolve the naming conflict first.
- **Alternatives:** (a) Create `AGENTS.md` per base §23; (b) keep `Agent.md` only.
- **Rationale:** `Agent.md` is established, referenced everywhere, and already extended by the addendum; a parallel file creates drift. Base §23's intent (a single source of truth for agents) is satisfied by `Agent.md`.
- **Tradeoffs:** Diverges from the base doc's literal §23 filename. Documented here as an explicit decision.
- **Reversibility:** High — could later `git mv Agent.md AGENTS.md` if a strong reason emerges.
- **Date:** 2026-07-25.

## ADR-0006 — Production deployment is human-gated; autonomous scope is local-only
- **Decision:** Autonomous execution covers all local, non-destructive work. Production deployment requires the full G6 gate (DEPLOYMENT_GATES.md) and is **not** performed unattended.
- **Context:** Agent.md addendum §1/§19.
- **Alternatives:** Allow autonomous production deploy.
- **Rationale:** Production has no current backup confirmation, unknown plugin state (U1–U14), and irreversible risks (permalink/homepage). Local-first with a deployment-ready package + checklist is the safe boundary.
- **Tradeoffs:** A "deployment-ready package" must be handed to a human for the final step.
- **Reversibility:** N/A (a boundary policy).
- **Date:** 2026-07-25.
