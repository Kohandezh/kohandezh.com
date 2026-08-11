# Security Policy — Kohandezh.com Knowledge Platform

> Security requirements for Layer A (preserve existing hardening) and Layer B (new plugin). WP + secure-SDLC best practices. Aligned with Agent.md base §19 and addendum §16.

## 1. Existing Layer A hardening (in `kohandezhcv/functions.php`) — preserve

- `DISALLOW_FILE_EDIT` (no in-browser theme/plugin editor).
- xmlrpc disabled; `wp_generator`/rsd/wlwmanifest/shortlink removed.
- REST `/wp/v2/users` + `?author=` enumeration blocked for anonymous.
- Security headers: HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy (CSP currently Report-Only).
- `.htaccess` hardening: blocks `readme.html`, `license.txt`, `wp-config-sample.php`, `wp-content/debug.log`.
- ZAI_API_KEY stored in `wp-config.php` (never in repo/JS).

**Layer B must not weaken any of the above.**

## 2. Layer B plugin security requirements (`kohandezh-knowledge`)

### Input/output
- All input validated + sanitized (`sanitize_*`). All output escaped (`esc_*`). DB queries prepared (`$wpdb->prepare`).
- REST responses output-escaped; no private meta exposed.

### Auth & authorization
- Capability checks before any write (`current_user_can('edit_kdcv_knowledge')` etc.).
- Nonces on all forms + state-changing AJAX.
- REST write endpoints require auth + capability; **read** endpoints public but field-filtered.

### REST specifics
- Permission callbacks on every route (not just `__return_true` for writes).
- Pagination bounded (`per_page` ≤ 100).
- Rate-aware (honor existing limits; add where missing).
- No secret/private data in responses; stable versioning (`kohandezh/v1`).

### HTTP / external fetching (news pipeline)
- SSRF protection: destination must match the allowlisted source domains (`kdcv_source`); validate URL scheme/host; block private IP ranges (10/8, 172.16/12, 192.168/16, 127/8, ::1, link-local).
- Timeouts on all remote requests (`timeout=10`). Retry limits (≤3) with backoff.
- MIME validation on any fetched media; size caps.
- wp_remote_get/post only; never `file_get_contents` on remote URLs.

### Secrets
- API keys only in `wp-config.php` constants or the WP secrets manager — **never** in JS, HTML, logs, or git.
- No secrets logged. Error logs scrubbed.

### Cron / queues
- Cron lock protection; idempotent jobs; duplicate-job prevention (claim key + `wp_options` lock).

### File uploads
- No user file uploads in Layer B MVP. If added later: MIME allowlist, random filenames, stored outside webroot where possible, image re-encoding.

## 3. Content security

- User-generated/external content rendered with escaping + wp_kses (no raw HTML from sources).
- News content quarantined as draft; never rendered unreviewed.

## 4. Dependency & supply chain

- Minimal dependencies; prefer WP core APIs.
- Any vendored JS/CSS reviewed + integrity-hashed where fetched from CDN.
- No `eval`/`exec`/`system`/`create_function`.

## 5. Logging & observability

- Structured error logging to `error_log` with `[kdcv-kb]` prefix.
- No PII, no secrets, no full request bodies.
- View counters (existing pattern) continue to exclude logged-in users.

## 6. Checks before each release (Phase 12 automated where possible)

- [ ] PHP lint all plugin files
- [ ] `grep` for forbidden patterns: `eval(`, `exec(`, `file_get_contents(.*http`, hardcoded keys
- [ ] REST permission review
- [ ] Nonce verification present on all forms
- [ ] No `unverified` claims in public endpoints
- [ ] No new global assets loaded on Layer A
- [ ] SSRF allowlist enforced
