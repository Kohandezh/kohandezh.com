# Kohan Avatar

A self-contained WordPress plugin that renders a single interactive character
avatar — **Kohan** — from the authoritative sprite atlas. It replaces any prior
floating avatar shipped by the theme. All assets are copied into the plugin and
served through WordPress URL helpers; the browser never loads a local
filesystem path.

## v3 (3.0.0) — smooth motion, talk, memory, grounded answers

- **Smooth:** two sprite layers crossfade every frame change (60–140 ms);
  strips are decoded before their first frame; idle breathes on the
  compositor and blinks from the atlas idle row; a ground shadow sells the
  hop, the hover lift and the landing.
- **Hover is calm.** Pointer-look runs from the document, never while the
  pointer is on the character, with a dead zone, 17° hysteresis and a 110 ms
  dwell. Look angles follow `spritesheet.json` (0° = UP, clockwise) — v2
  counted from RIGHT, so the eyes were always 90° off.
- **Drag** is a held-by-the-collar pendulum (spring on pointer velocity); a
  flick throws him with friction and soft edge bounces; he lands with
  squash-and-stretch and one of the `drop` acts.
- **Acts in groups:** `click`, `idle`, `wait` (the visitor has been quiet
  for 18 s), `drag`, `drop`, `talk`, `think`. New poses are WebP strips in
  `assets/kohan/acts/`, declared in `assets/kohan/acts.json` and built with
  `scripts/build-act-strip.py` from a generated pose sheet (background
  removal incl. fake checkerboards, one scale per strip, feet on the atlas
  baseline). No JS change needed; a missing file is skipped.
- **Talk** (`kohan-avatar-talk.js`): clicking the avatar talks about the
  section in view or says one of the page's 2–3 quotations (seeded by the
  page address from the theme's verified `wisdom-quotes.*.json`; on book
  pages, sentences from the book section itself). Clicking a section makes
  him look at it and say what it is. A short line on arrival, once per page.
- **Chat memory:** the conversation lives in `sessionStorage` for the visit;
  on the next page the panel reopens if it was open and says where the
  visitor is. Every question sends the page, the section in view and the
  last 8 turns to `kdcv/v1/ask`, which answers from the whole site (AI Hub
  ≥ 1.2.0: posts, pages, profiles and — with Knowledge ≥ 0.7.4 — every book
  section) and returns numbered source links.

Settings → Kohan Avatar adds two switches: *Speech bubble* and *section
clicks*.

## What it does

- Renders one Kohan avatar from the 8×11 atlas (`spritesheet.webp`, 192×208
  cells, sprite v2) plus pre-processed supplemental WebP strips.
- Idle loop (random 30–75 s per action); every third completed idle interval
  plays `russian-roulette`. Drag time and hidden-tab time never count as a
  completed interval.
- Hover → a calm lift; pointer-look (16 directions) only while the pointer
  is AWAY from the character (see v3 above).
- Real click → the talk module answers (bubble + gesture); without it, one
  random act from the click group, then back to idle.
  A drag suppresses the click action.
- Drag via Pointer Events + pointer capture → `drag-annoyed` / `fall-scared`,
  settling into angry then idle on release. Stays inside the viewport.
- `walking-patrol` moves two short steps toward screen-right and two back,
  restoring the exact starting transform (built only from the approved
  running-right / running-left rows).
- `goodbye-smoke` waves, snaps, vanishes into smoke, and stays hidden until an
  explicit `reset()`/show event.
- Respects `prefers-reduced-motion`; `aria-label="Kohan avatar"`; no focus trap;
  preloads only the idle atlas; cleans up timers/listeners/RAF on `destroy()`.

## Files

```
kohan-avatar/
  kohan-avatar.php                     bootstrap
  includes/class-kohan-avatar.php      enqueue, config, settings, legacy removal
  includes/class-kohan-avatar-rest.php /version (public) + /sync (manage_options)
  admin/settings.php                   Settings > Kohan Avatar
  assets/kohan/                        authoritative atlas + metadata + supplemental/
    spritesheet.webp / spritesheet.json / moods.json / pet.json / MOODS.md
    supplemental/kohan-*.webp
    version.json                       (generated) combined SHA-256
    supplemental-sprites.json          (generated) per-strip hashes + timing
  assets/css/kohan-avatar.css
  assets/js/kohan-avatar.js            controller (window.KohanAvatar)
  assets/js/kohan-avatar-events.js     lifecycle → mood bridge (opt-in)
  assets/js/kohan-avatar-talk.js       speech bubble, section talk, quotations
  assets/js/kohan-avatar-chat.js       chat panel with visit-long memory
  assets/kohan/acts.json + acts/       extra pose strips (generated)
  scripts/build-act-strip.py           pose sheet -> strip + acts.json entry
  scripts/sync-kohan-assets.sh         refresh assets from authoritative source
  README.md
```

## Install / activate

1. Copy the `kohan-avatar/` directory into `wp-content/plugins/`.
2. In **Plugins**, activate **Kohan Avatar**.
3. Configure under **Settings → Kohan Avatar** (enabled, position, scale, idle
   delay, drag, pointer-look, supplemental moods, weapon moods, response-event
   mapping, fire probability). The deployed asset hash is shown there.

The plugin disables the old theme avatar automatically (dequeues
`ai-pet` / `kohan-avatar` theme scripts/styles) so only one avatar exists.

## Sync / build

```bash
# from a trusted server shell / CI (source path is fixed inside the script,
# override only via KOHAN_SRC):
bash wp-content/plugins/kohan-avatar/scripts/sync-kohan-assets.sh
```

The script verifies the source, validates JSON, checks the 8×11/192×208 atlas
contract, copies only approved assets, skips unchanged files, writes
`version.json` (combined SHA-256) and `supplemental-sprites.json`, flushes the
WP object cache if `wp-cli` is present, and exits non-zero on any error.

An authenticated admin can also trigger it from **Settings → Kohan Avatar →
Sync / Refresh** (REST `POST kohan-avatar/v1/sync`, requires `manage_options` +
REST nonce). No unauthenticated refresh endpoint exists. On hosts where
`shell_exec` is disabled, run the script from your deploy pipeline instead.

For a remote site: stage and package the plugin locally, then deploy through the
site's existing trusted workflow. Do not assume the macOS source path exists on
production — the copied `assets/kohan/` is fully self-contained.

## Public JavaScript API

```js
window.KohanAvatar.setMood(mood, { returnTo });
window.KohanAvatar.play(mood, { returnTo });
window.KohanAvatar.stop();
window.KohanAvatar.reset();       // also un-hides after goodbye
window.KohanAvatar.destroy();
window.KohanAvatar.refreshAssets();
```

Drive it from real lifecycle events:

```js
window.dispatchEvent(new CustomEvent('kohan:avatar:mood', {
  detail: { mood: 'macbook-work', returnTo: 'idle' }
}));
```

Moods are validated against the allowlist in both PHP and JS. Semantic metadata
(`{ "avatarMood": "wink" }`) is honored only if it passes the allowlist.

## Response-event integration points

When **response-event mapping** is enabled, `kohan-avatar-events.js` wraps
`window.fetch` **only** for requests whose URL contains the configured chat REST
route (`rest_url('kdcv/v1/chat')`, from the sibling `kohandezh-ai-hub` plugin):

- request in flight → `macbook-work`
- JSON response with `{ avatarMood }` on the allowlist → that mood
- otherwise ok → `ipad-review` then `wink`
- error / non-ok → `angry`

No other requests are intercepted. If your chat route differs, adjust
`chatRoute` in `Kohan_Avatar::enqueue_front()`.

## Security

Options are sanitized and clamped; admin output is escaped; the sync action
requires `manage_options` and the REST nonce; no visitor-supplied filesystem
path is ever accepted; no `eval`; no remote scripts; every mood name is
validated. The authoritative source path lives only in the server-side script.

## Atlas rows (from spritesheet.json)

`0 idle · 1 running-right · 2 running-left · 3 waving · 4 jumping ·
5 angry · 6 confused (headset) · 7 macbook · 8 ipad · 9–10 pointer-look (16)`.
