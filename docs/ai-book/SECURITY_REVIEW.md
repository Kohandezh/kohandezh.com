# AI Book Security Review Gate

- **Date:** 2026-09-20
- **Scope:** the entire `/ai-book/*` surface shipped by `kohandezh-knowledge` — repository/artifact boundary, Reader routing, Search, Catalog, Graph, Ask/RAG, PDF viewer/stream, request intake, template, asset/robots hooks.
- **Verdict:** **PASS** with two findings fixed in this gate (`SEC-1`, `SEC-2` below) and one documented limitation.
- **Method:** manual checklist audit of all nine `includes/class-kbk-ai-book-*.php` classes plus the template, cross-checked against the 10-suite test chain (`npm run test:ai-book`, all PASS after fixes). Automated tooling was not required — every surface is plain PHP with a single input boundary.

## Checklist and findings

| Area | Check | Result |
| --- | --- | --- |
| Filesystem | No request input ever reaches a filesystem function. All artifact paths are repository-root + fixed relative constants; identifiers are only compared against in-memory allowlists (ADR-AB-0009), never interpolated into paths. | PASS |
| Filesystem | Stream executor reads only the validator-approved path (`%PDF-` header + exact manifest byte-size), `fopen 'rb'`, chunked with `connection_aborted()`. | PASS |
| Path traversal | `realpath`'d root; no `..` handling needed because no user string joins a path. | PASS |
| Injection | No `extract`, `eval`, `unserialize`, `exec`, `shell_exec`, `base64_decode`, no `$wpdb`, no redirects anywhere in the subsystem. All JSON parsed with `json_decode` (no XML/YAML → no XXE). | PASS |
| Input boundaries | Every query var passes a strict regex allowlist (`kbk_q` control-char strip + 480-byte cap, `kbk_part/chapter/section/concept/template/entity/type/page/pdf_file`); POST intake validates per field with bounded lengths and enum `use`. | PASS |
| XSS | Template uses `esc_html`/`esc_attr`/`esc_url`/`esc_textarea`/`wp_kses_post(wpautop(esc_html(...)))` on every dynamic value (82 escape calls); retrieval excerpts are escaped `{text, mark}` segments where only the class owns `<mark>`. | PASS |
| Header injection | Stream headers are built from constants plus the edition matched against `^\d{4}E\d+$` — no CRLF-capable input reaches `header()`. | PASS |
| Secrets | API keys (`KBK_AI_BOOK_ASK_*`, `KBK_AI_BOOK_REQUEST_*`) exist only in Authorization headers and config constants; never logged, rendered, or included in error states. Provider transport errors are collapsed to static honest states (`PROVIDER_UNAVAILABLE`), discarding `WP_Error` details. Stream headers/plan never leak local filesystem paths (tested). | PASS |
| Provider trust | Ask: generated text must pass the bounded contract and `used ⊆ offered` citation check — invented citations are structurally impossible. Request: `{status, reference}` contract with opaque `[A-Za-z0-9_-]{1,32}` reference; all four outcomes render distinct honest states. | PASS |
| CSRF | Request POST is the only state-changing surface. **SEC-2 (fixed):** added `wp_nonce_field`/`wp_verify_nonce` (`kbk_ai_book_request` action); forged/expired nonces return `INPUT_INVALID` with a session error before any validation or provider call (tested in both suites). | FIXED |
| Caching | **SEC-1 (fixed):** stream `Cache-Control` token corrected `nostore` → `no-store`. | FIXED |
| Response control | All `/ai-book/*` views and the raw PDF stream are `noindex, nofollow` by default (`KBK_AI_BOOK_INDEXABLE` opt-in, ADR-AB-0008); stream adds `X-Robots-Tag` so the file itself is covered even when linked from elsewhere. | PASS |
| Fail-closed | Every subsystem degrades to `CONFIG_REQUIRED`/`ARTIFACT_INVALID` with empty bounded read models (tested per subsystem with tampered fixtures); no partial data is ever served. | PASS |
| DoS bounds | Search index 1.3MB, entity mentions 1.24MB (graph artifact 4.8MB is lazy, graph views only), corpus scan streamed line-by-line with bounded accumulation, pagination clamped at 60, neighborhoods capped at 24, quotes at 160, answers at 4000 chars. The 38MB PDF streams with `no-store` (no server-side range handling; browsers handle full-body responses). | PASS |
| Clickjacking | Not addressed by the AI Book layer — the site-wide header policy (if any) is a platform concern outside this feature's scope; noted for the production hardening checklist. | NOTE |
| Rate limiting | The request intake is storage-free by design (ADR-AB-0015), so per-IP throttling cannot live here honestly; enforcement is delegated to the provider endpoint and must be part of its production configuration. | NOTE |

## Evidence

- `php _tooling/tests/ai-book-pdf.test.php /Users/emperor/Documents/AI/AiBook` → PASS (includes forged-nonce rejection and offline sha256 verification of the canonical PDF)
- `php _tooling/tests/ai-book-wordpress-route.test.php` → PASS (includes forged-nonce → `INPUT_INVALID` session error before provider call)
- `npm run test:ai-book` → PASS (10 suites + visual shell build)
- Grep audits documented above (filesystem ops, dangerous constructs, secret exposure, unescaped template output) all clean.

## Residual risks (accepted, documented)

1. **Rate limiting** — delegated to the provider endpoint (see NOTE above); revisit only if a same-site issuance pipeline is ever built.
2. **Clickjacking headers** — platform-level; add to the production WordPress hardening checklist.
3. **Provider availability** — an attacker can cause the site to attempt outbound provider calls for valid nonces only; the nonce requirement (fresh per page render) and the 30s timeout bound this to a nuisance, not an amplification vector.

## Addendum (Performance gate, 2026-09-20)

The stream's `Cache-Control` evolved from `no-store` (SEC-1) to `private, max-age=0, must-revalidate` with a strong sha256-derived `ETag` (PERF-1, see `PERFORMANCE_REVIEW.md`): privacy posture unchanged (no shared/proxy caching), integrity now verifiable via the ETag itself, and conditional 304 responses skip the file read entirely. The `X-Robots-Tag: noindex, nofollow` header is also sent on 304 responses.
