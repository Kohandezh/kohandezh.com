# Kohan Avatar — Agent Handoff

## Current Status

Avatar-only implementation and validation are complete for the current flattened artwork. The canonical Canvas controller and WordPress mirror contain transition blending, delta-time micro-motion, natural composed activities, on-demand supplemental loading, visibility-aware rendering, and deterministic cleanup. Production assets use cache version `v=60`; the installable WordPress archive is rebuilt and verified. ChatBox and all non-avatar UI remain untouched and out of scope.

## Last Completed Checkpoint

2026-08-08 — Completed live avatar QA: 40 rapid activity interruptions, 12 visitor-click interruptions, direct-walk, hide/show, classic/animated, background-tab resume, responsive screenshots, DOM singleton checks, WordPress parity and archive validation. All avatar-focused tests pass and the live runtime returns to `idle/native` without page errors.

## Next Action

For future motion changes, edit only `assets/js/kohan-avatar.js`, rebuild its minified sibling, bump the avatar query version, run the focused test, then use the official WordPress sync. Do not edit the generated theme controller by hand.

## Blockers

- No blocker to the requested local avatar work.
- The approved character artwork is flattened raster art, not a skeletal or layered model. New motion must reuse approved atlas/supplemental frames and may not deform or regenerate the face.

## Validation Status

- Local server: PASS — PHP server listening at `http://localhost:8735/`.
- Baseline visual inspection: PASS — avatar visible at bottom-right, controls outside the avatar, ChatBox unchanged.
- JavaScript syntax: PASS — readable and minified production controller.
- Avatar runtime tests: PASS — 6/6 in `kohan-avatar-runtime.test.js`.
- WordPress synchronization: PASS — dry run reports 0 create / 0 update / 0 delete; source/theme controller hashes match.
- WordPress archive: PASS — `kohandezhcv.zip` rebuilt at 31.5 MB; `unzip -t` reports no errors.
- Browser state/interruption QA: PASS — 12 live click interruptions plus 40 rapid programmatic interruptions; one canvas/control/root; final `idle/native`; zero page errors.
- Direct manipulation: PASS — double-click walk starts and clears; hide/show and classic/animated restore correctly.
- Background tab: PASS — visible-tab return resumed a natural `vision` reaction with the controller active.
- Responsive rendering: PASS at 390×844, 820×1180, 1280×720 and 1920×1080. At 390px the control row measured x=190…390, with no horizontal overflow.
- Reduced motion: implementation and focused invariant test PASS. Manual OS-level reduced-motion visual emulation was unavailable in the in-app browser.
- Performance isolation: headless full-page RAF averaged 50.553 ms dynamic and 50.830 ms with Kohan static, showing no measurable avatar-specific cadence cost in the throttled page. Blank headless baseline was 16.660 ms. Broader page animations dominate that headless cadence.
- Full repository suite: 28 PASS / 2 FAIL. Both failures are pre-existing out-of-scope JSON-LD validator objections to `SoftwareApplication` / `ProfessionalService` on `/` and `/fa.html`; all avatar, static, PHP, sync and live-route checks pass.

## Scope and Safety Boundary

- Modify only avatar files inside `/Users/emperor/Documents/AI/kohandezh.com`.
- Never write to Elecomp-Pet, Haj-Pet, or Pet-Inotex; they are read-only references only.
- Do not modify ChatBox markup, ChatBox styling, knowledge retrieval, site palette, homepage design, navigation, content, forms, or unrelated WordPress code.
- Static root files are authoritative. Mirror avatar runtime files to `_tooling/wp-theme/kohandezhcv/` and rebuild the theme archive through the existing deterministic pipeline.
- The generated WordPress PHP templates are not hand-edited.

## Authoritative Artwork

- `avatar/main.PNG` — front identity reference.
- `avatar/view1.PNG` — turnaround, expressions, action poses, and material details.
- `avatar/view2.PNG` — additional turnaround, expressions, walk, polite, and welcoming poses.
- `assets/kohan/spritesheet.webp` — approved 11×8 runtime atlas derived from the source identity.
- `assets/kohan/supplemental/` — approved extracted supplemental runtime poses.
- `assets/kohan/avatar-sheets/` — approved source sheets for supplemental sequences.

Identity lock: retain the same face, hair, Kali sweatshirt, black pants, black trail shoes, and black/red watch. Do not generate or introduce a replacement character.

## Current Architecture

- Host: existing WordPress/static AI-pet widget mounts `.kdcv-pet-root` and ChatBox.
- Renderer: Canvas 2D, one `requestAnimationFrame` loop in `assets/js/kohan-avatar.js`.
- Native atlas: 192×208 cells, 11 rows, up to 8 frames per row.
- Supplemental rendering: individual WebP frames loaded through `SUPPLEMENTAL_INFO`.
- State: one controller instance exposed as `window.KohanAvatar`.
- Interactions: pointer look, click actions, drag reactions, chat-open greeting, AI request lifecycle, BYE goodbye, size/classic/eye controls.
- Movement extension: `assets/js/kohan-avatar-enhance.js` handles double-click/keyboard walking and ChatBox placement. ChatBox placement is not to be changed.
- WordPress mirror: `_tooling/wp-theme/kohandezhcv/assets/{js,css,kohan}`.

Production parity hashes at the completed checkpoint:

- Readable controller: `3b1c18822e5c42a5b27d6bcbbb9fe45c540823bcf00cb58e40832719e6f3a75b`
- Minified controller: `cfa03dcce1e7cf74a7613c8fd4e4864f5d9718a5d552c4938bfd2c0353ff1358`

## Baseline Findings

1. Pose/state changes replace the current frame immediately, causing visible snapping between unrelated silhouettes.
2. Every supplemental frame is preloaded during startup, decoding roughly thirty images even when unused.
3. The RAF loop keeps running while the document is hidden and while the avatar is hidden/classic/static.
4. Idle aliases such as watch-check, hand-on-chest, whistle, hands-in-pockets, looking-sky, and nap mostly reuse one native row and do not yet form natural multi-step gestures.
5. Event listeners are created with anonymous callbacks and are not fully removed by `destroy()`.
6. The current activity epoch prevents state overlap, but interrupted async waits still resolve later; they are guarded and do not overwrite the active state.

Items 1–6 were addressed in the canonical controller during this checkpoint. The flattened-art limitation remains.

## Target Improvements

- Short crossfade between old and new rendered frames without face deformation.
- Delta-time procedural breathing/settling and restrained movement appropriate to each state.
- More source-safe composed gestures: stretch, deep-thought, curious scan, work cycle, celebration, and power nap.
- On-demand/idle asset warming rather than startup decoding of all supplemental frames.
- Pause/resume with safe timestamp reset for visibility, hidden, classic, static, and reduced-motion conditions.
- Fully tracked listeners/observers/timers and deterministic cleanup.
- Preserve one RAF chain and prevent duplicate boot/attachment.

## Public API Compatibility

Existing methods must remain compatible: `play`, `playActivity`, `setMood`, `stop`, `reset`, `destroy`, `refreshAssets`, `debug`, `isAllowed`, `getState`, `setStatic`, `getMode`, `cycleMoods`, `triggerRandom`, `availableMoods`, `setSize`, `getSize`, `toggleClassic`, `toggleHidden`, `isHidden`, `isClassic`, and `setOptions`.

## Commands

- Local server: `./run-dev.sh`
- JavaScript syntax: `node --check assets/js/kohan-avatar.js`
- Focused avatar test: `node --test _tooling/avatar/Kohan-Artwork/qa/kohan-avatar-runtime.test.js`
- Legacy reference-controller test: `node --test _tooling/avatar/Kohan-Artwork/qa/kohan-idle-controller.test.js`
- Full checks: `bash _tooling/tests/run.sh`
- WordPress dry run: `python3 _tooling/wp-theme/sync-from-static.py --dry-run`
- WordPress sync/build: `python3 _tooling/wp-theme/sync-from-static.py`
- PHP lint: `find _tooling/wp-theme/kohandezhcv -name '*.php' -print0 | xargs -0 -n1 php -l`

## Change Log

### 2026-08-08 — WordPress sync and live completion checkpoint

- Files added: `_tooling/avatar/Kohan-Artwork/qa/kohan-avatar-runtime.test.js`.
- Files modified: canonical/minified avatar controller, avatar cache references in mapped static pages, generated WordPress avatar assets/templates, `kohandezhcv.zip`, and `Avatar.md`.
- Result: canonical and WordPress controller hashes match; `v=60` is live; archive integrity and zero-drift sync pass.
- Browser results: natural click actions observed (`ipad-review`, `macbook-work`, `vision`, `deep-thought`, `waving`, `work-cycle`, `stretch`); 40-interruption stress ended at `idle/native`; direct walk, hidden/static pause/resume and background-tab recovery passed.
- Responsive results: screenshots reviewed at 390×844, 820×1180 and 1920×1080; no avatar clipping or horizontal control overflow was measured.
- Tests: focused avatar 6/6 PASS; full suite 28 PASS / 2 unrelated JSON-LD FAIL; PHP lint, JS syntax, live routes, sync parity and ZIP integrity PASS.
- Known non-avatar issue: the repository JSON-LD validator rejects existing schema types on English/Persian pages. Do not fix this from an avatar-only task.

### 2026-08-08 — Natural motion and lifecycle checkpoint

- Files modified: `assets/js/kohan-avatar.js`, `assets/js/kohan-avatar.min.js`, primary static HTML avatar cache references, and `Avatar.md`.
- Reason: remove sudden pose replacement, reduce startup decoding/main-thread work, add natural non-weapon activities, and prevent hidden/disposed render work.
- Result: added stretch, curious scan, deep thought, work cycle, celebration, and power nap compositions; crossfade snapshots; delta-time micro-motion; on-demand supplemental loading; visibility-aware RAF pause/resume; listener registry cleanup; and weapon-free random pools.
- Tests run: readable and minified JavaScript syntax checks passed. Live WordPress synchronization and browser sequence QA remain next.
- Next command: `node --test _tooling/avatar/Kohan-Artwork/qa/kohan-avatar-runtime.test.js` after adding the focused runtime assertions.

### 2026-08-08 — Initial avatar audit

- Files created: `Avatar.md`.
- Files inspected: authoritative PNGs, runtime atlas/manifests, `assets/js/kohan-avatar.js`, enhancement JS, avatar CSS, QA tests, WordPress sync script, and local page.
- Result: confirmed source-of-truth/mirror architecture and identified snap, eager-load, lifecycle, and natural-action improvements.
- Tests: baseline server and visual rendering confirmed; implementation tests pending.
- Rollback: remove `Avatar.md` only; no runtime file changed at this checkpoint.
