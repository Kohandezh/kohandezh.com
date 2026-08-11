# NEXT-SESSION-PROMPT.md — kohandezh.com handoff

> **Paste this entire file to the next Claude session as the first message, OR just say:**
> *"Read `NEXT-SESSION-PROMPT.md` at the repo root and execute it."*

---

## 0. READ FIRST (already auto-loaded, but re-confirm)

- `CLAUDE.md` — project conventions (esp. **convention #7: never re-add jQuery, use the shim**)
- `WORK-LOG.md` — full P0→P4 history with verification evidence
- `memory/MEMORY.md` (project memory) — current-state pointers

The repo is the **source of truth**. Production (kohandezh.com on LiteSpeed) is
**STALE** — the P0–P4 zip (`_tooling/wp-theme/kohandezhcv.zip`) was never uploaded.
Everything below is LOCAL work unless a deploy is explicitly requested.

---

## 1. CRITICAL CONSTRAINTS (do NOT violate)

1. **Never edit `*.min.{js,css}` directly** — edit source, then `./build.sh`.
2. **Never edit `_tooling/wp-theme/kohandezhcv/*.php` templates** — they are auto-generated. Edit the `*.html` at repo root, then run `python3 _tooling/wp-theme/sync-from-static.py`.
3. **`functions.php` is hand-maintained** — edit directly.
4. **Cache-bust every `.min` change**: bump `?v=N` in all 9 HTML files.
5. **`/` (English) must stay bot-indexable** — locale-router v2 handles this; don't break it.
6. **jQuery has been REMOVED.** Do NOT re-add `jquery.min.js` or `jquery-validate.js`.
   They are replaced by **`assets/js/jquery.shim.js`** (~14KB). All consumer files
   (`main.js`, `gsapAnimation.js`, `countto.js`, `carousel.js`, `animation-change-text.js`)
   run unchanged on the shim. If a script needs a jQuery method the shim lacks,
   **add it to `jquery.shim.js`** and re-minify with `terser` — never restore real jQuery.
7. **`robots.txt` (15 AI bots + catch-all) is kept in sync with the `robots_txt` filter in `functions.php`** — edit BOTH together.
8. **Do NOT re-encode `assets/media/phase11-award.mp4`** — re-encoding bloats it. The other 3 media files are fine to re-encode.
9. **Test in real headless Chrome**, not jsdom — jsdom lacks `matchMedia`/`fetch`/SVG geometry and gives false errors.

---

## 2. VERIFIED BASELINE (completed 2026-08-02, do not redo)

| # | Done | Evidence |
|---|---|---|
| 1 | robots.txt = 15 AI bots + catch-all (synced static ↔ functions.php) | 16 UA lines both sides |
| 2 | 404 arcade reachable at 3 layers | dev-server.php serves 404.html w/ HTTP 404; `404.php` auto-generated; 4 stubs force specific games |
| 3 | Media re-encoded 40MB→24MB | phase-11 20→11MB, interview 8.1→4MB, mp3 5.8→2.3MB; phase11-award left as-is |
| 4 | jQuery removed via shim (209KB→14KB/page) | 0 JS errors in real Chrome en/fa/ja; 0 stale refs |
| 5 | All docs + theme in sync | zip 30.1MB; all theme PHP lint clean |

---

## 3. TASK LIST — execute in this order (highest-value, lowest-risk first)

### GROUP A — Quick text/config fixes (do first)

**A1. Button label fix (English homepage).**
On `index.html` (and only the English canonical — other locales keep their own
translation), change the certificate-archive button/CTA text:
- **FROM:** "Credential Archive"
- **TO:**   "Open Full Certificate Archives"
Find it in `index.html` (grep `Credential Archive`). Verify the same element on
the 8 other locales uses an already-correct localized string; only English is wrong.

**A2. Self-contained clock on EVERY page (no external service).**
Every CV page (and ideally standalone pages) must show the current time, and it
must **not depend on any external API/service**. There is already a `.time-local`
clock in `main.js` (the `p()` function uses `toLocaleTimeString` + `toLocaleDateString`
— pure `Intl`, already self-contained). Tasks:
- Verify `.time-local` element exists on all 9 CV pages + standalone pages; add it where missing.
- Confirm `main.js`'s clock runs on every page that loads `main.js`.
- For per-country/per-locale correctness, format with the visitor's locale +
  `Intl.DateTimeFormat` (no timezone DB fetch — use the browser's local zone).
- Do NOT add any `<script src="…clock-api…">` or fetch to a time service.

**A3. Menu + Footer content spec (ALL pages, consistent).**
The navigation menu must contain (in addition to existing items):
- **Brand:** "Mohammad Ali Kohandezh"
- Blog · Portfolio · Certificates · PSN Trophy Room · Privacy Policy · Terms of Use
The footer must contain:
- The **logo** (same treatment as the main homepage footer)
- "All rights reserved"
- "© 2026 Mohammad Ali Kohandezh"
Apply consistently across all 9 CV pages + standalone (PSN, Certificates, videos,
portfolio, blog). *Clarify with user if Privacy Policy / Terms of Use belong in the
main nav or just the footer (usual convention: footer).*

---

### GROUP B — Design unification (medium effort, high polish)

**B1. Footer consistency.**
Make the footer identical in structure across every page (logo + nav + copyright).
Several standalone pages currently have a thinner/different footer than the homepage.
Lift the homepage footer pattern to all pages.

**B2. Unify all CTAs to the "Request a Call" button design.**
The reference design = the **"Request a Call"** button (`.tf-btn-action` family in the
contact section — inspect it first). Apply that design language to ALL of these,
**each keeping its own size / font-size / color** (don't make them visually identical —
match the pattern, scale per context):
- Contact · Request a Demo · LinkedIn Recommendations · Blog
- Tech Stack · Work Highlights · Education & Experience · Certificate
- Leadership · Professional Membership · Community Service · About Me

**Recommended approach:** define a single button component (e.g. reuse/extend the
existing `.tf-btn-action` / shiny-button classes with size modifiers like
`--sm / --md / --lg` and color variants) rather than restyling each button ad-hoc.
This keeps alignment/spacing consistent and maintainable. Verify alignment at
375 / 768 / 1440 widths.

**B3. Blog tiles must NOT move on hover (stability).**
Homepage blog tiles (`.blog-local-item`) must be visually stable — no layout shift,
no sibling reflow — on hover/animation. (Separate from B4: the *hover effect* should
appear, but the tile itself must not jump/resize/push neighbors.)

**B4. Blog-tile hover effect (carry over from prior session — VERIFY STATE FIRST).**
A prior session was refactoring `assets/js/pixel-canvas.js` so that late-rendered
`.blog-local-item` tiles (re-rendered by `home-blog-feed.js` after pixel-canvas runs)
also receive the effect. The intended fix: export an `attach()` function + a
`MutationObserver` on the blog list.
- First `git diff` / read `pixel-canvas.js` to see whether the refactor landed.
- If incomplete, finish it: guard against double-attach, initial scan, observe the
  `.blog-local-list` for added `.blog-local-item` nodes and attach the effect.
- Rebuild `pixel-canvas.min.js` + bump `?v`.

---

### GROUP C — Page architecture (bigger; scope carefully)

**C1. Nav chrome on ALL pages.**
Menu, **Kohan avatar**, language switcher, and dark/light-mode toggle must be visible
on **every** page — including standalone pages (PSN, Certificates, videos, portfolio,
blog index + posts, 404) where the full nav chrome is currently incomplete. Audit
which pages are missing which control, then bring them to parity with the CV pages.

**C2. Avatar content-awareness.**
The Kohan avatar's chat must speak about **that specific page's** content (not generic
site text). Suggested mechanism: each page sets
`window.KDCV_PAGE_CONTEXT = { title, summary, topics: [...] }`; the avatar chat
prompt builder reads it to ground responses. Wire this for homepage + each standalone
page. (The avatar backend lives in the `kohan-avatar` plugin / `kdcv/v1/ask` REST route.)

**C3. Certificates.html — translation + content accuracy (TWO problems).**
1. **Content accuracy FIRST** (user flagged: "i guess its not right"). Specific concerns
   to verify against source evidence: **MCPS month mismatches** and a **2011 MCTS
   artifact**. Correct the facts before translating — translating wrong content doubles the work.
2. **9-language translation.** `Certificates.html` is a single page (no per-locale
   variants by design) but is linked from all locales with `?lang=`. Add a translation
   layer: build a curated `data-i18n` dictionary + `certificates-i18n.js` that swaps the
   explanatory block's text based on `?lang=`. **Do NOT auto-translate** (accuracy risk) —
   provide hand-curated translations for the explanatory text in all 9 locales.

---

## 4. IN-PROGRESS FROM PRIOR SESSION — verify before continuing

- **`pixel-canvas.js`** refactor (see B4) — may be half-applied. Check current source.
- **Certificates.html** translation/accuracy (see C3) — not started or reverted.

Before acting on any item, `grep` / read the current file to confirm what's actually
on disk vs. what the prior session intended.

---

## 5. OPEN QUESTIONS — ask the user before proceeding

1. **MK logo on the profile picture:** add it, or remove it? (Ambiguous in the request.
   Currently the hero portrait is an AI/Quantum poster with no MK logo.)
2. **Certificates content:** is the source data correct, or should the user supply
   corrected dates (MCPS month, the 2011 MCTS)? Don't guess — ask.
3. **Privacy Policy / Terms of Use:** main nav, or footer-only?

---

## 6. ADDITIONAL SUGGESTIONS (from the prior reviewer — optional, propose to user)

1. **Button design system** (do this as part of B2): one `.kdcv-btn` base + modifiers
   (`--sm/--md/--lg`, `--primary/--ghost/--accent`) instead of restyling 12 buttons
   piecemeal. Pays off every time a future button is added.
2. **Deploy the zip early.** Production is stale since P0. Suggest the user upload
   `_tooling/wp-theme/kohandezhcv.zip` (wp-admin → Appearance → Themes → Add New →
   Upload) so the 209KB/page jQuery win, 404 fix, and robots expansion go live — then
   continue local work. Don't accumulate more un-deployed batches.
3. **JPG/PNG → WebP** is the biggest remaining asset win (images dir is 21MB). Pairs
   well with the design pass in Group B since you're touching markup anyway.
4. **Clock as a shared component** (A2): a single `assets/js/clock.js` loaded in the
   common script bundle, data-driven (`data-clock` / `data-date` elements), so it's
   trivially on every page. Avoids per-page duplication.
5. **Avatar content-awareness** (C2): a `KDCV_PAGE_CONTEXT` global is the lightest
   mechanism; document the schema in `CLAUDE.md` once added so future pages set it.

---

## 7. DEPLOY REMINDER

After any change: `./build.sh` (if JS/CSS source changed) →
`python3 _tooling/wp-theme/sync-from-static.py` (if HTML changed) →
upload `_tooling/wp-theme/kohandezhcv.zip` via wp-admin. Bump `KDCV_CONTENT_SCHEMA_VERSION`
in `functions.php` only if you add new `admin_init` migration work.

---

## 8. WHEN YOU HIT A LIMIT / get stuck

Stop, write the current state to `WORK-LOG.md` (what's done, what's mid-flight, what
blocked you), update `memory/MEMORY.md` with a one-line pointer, and tell the user
exactly where to resume. Don't leave half-applied changes un-documented.
