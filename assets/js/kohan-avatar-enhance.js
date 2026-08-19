/**
 * Kohan avatar enhancements (static site) — additive, no edits to the
 * minified avatar bundle. Two behaviours:
 *
 *  1. Chat panel opens BESIDE the avatar (toward the side with more room),
 *     never above its head. It keeps the existing panel's own content/menu;
 *     only its position is corrected, re-applied whenever the bundle
 *     recomputes (guarded so it can't loop).
 *
 *  2. The avatar can WALK the page: double-click anywhere walks it there;
 *     arrow keys / WASD move it while it is focused. Uses transforms, stays
 *     inside the viewport, and never fights an in-progress native drag.
 */
(function () {
  "use strict";
  if (window.__KOHAN_ENHANCE__) return;
  window.__KOHAN_ENHANCE__ = true;

  /* Two avatars can own the page. The theme mounts `.kdcv-pet-root`
     (ai-pet.js); the kohan-avatar PLUGIN mounts `.kohan-avatar-root` and
     sets `__KDCV_PET_BOOTSTRAPPED__` so the theme one never builds. On a
     production WordPress install the plugin wins, so a selector that only
     names the theme root matches nothing: boot() retried forever, the
     double-click tween and the arrow-key loop never bound, and
     `data-kohan-walking` was therefore never written — which is the single
     attribute kohan-drive.js watches, so the car was built and then waited
     for a signal that could not arrive. Both roots are named here.
     Everything below uses generic DOM APIs on the root, so either works. */
  var ROOT_SEL = ".kdcv-pet-root, .kohan-avatar-root";
  var PANEL_SEL = ".kdcv-pet-panel";

  function root() { return document.querySelector(ROOT_SEL); }

  /* ---------------- 1. chat panel: open beside, never above --------------- */
  var applying = false;

  function positionPanel(panel) {
    var r = root();
    if (!panel || !r) return;
    var a = r.getBoundingClientRect();
    var pw = panel.offsetWidth || 340;
    var ph = panel.offsetHeight || 300;
    var gap = 14;
    var side = window.innerWidth - a.right >= a.left ? "right" : "left";
    var left = side === "right" ? a.right + gap : a.left - gap - pw;
    // align the panel's bottom near the avatar's bottom so it never rides up
    // over the head; then clamp inside the viewport.
    var top = a.bottom - ph;
    top = Math.max(12, Math.min(top, window.innerHeight - ph - 12));
    left = Math.max(12, Math.min(left, window.innerWidth - pw - 12));

    var curLeft = parseFloat(panel.style.left);
    var curTop = parseFloat(panel.style.top);
    if (Math.abs(curLeft - left) < 1 && Math.abs(curTop - top) < 1) return; // already ours
    applying = true;
    panel.style.setProperty("left", left + "px", "important");
    panel.style.setProperty("top", top + "px", "important");
    panel.setAttribute("data-kohan-side", side);
    requestAnimationFrame(function () { applying = false; });
  }

  function watchPanel(panel) {
    positionPanel(panel);
    var mo = new MutationObserver(function () {
      if (applying) return;
      positionPanel(panel);
    });
    mo.observe(panel, { attributes: true, attributeFilter: ["style"] });
    // reposition on layout changes while open
    var onView = function () { positionPanel(panel); };
    window.addEventListener("resize", onView, { passive: true });
    window.addEventListener("scroll", onView, { passive: true });
    // stop watching once the panel is removed
    var gone = new MutationObserver(function () {
      if (!document.body.contains(panel)) {
        mo.disconnect();
        gone.disconnect();
        window.removeEventListener("resize", onView);
        window.removeEventListener("scroll", onView);
      }
    });
    gone.observe(document.body, { childList: true, subtree: true });
  }

  // Detect the panel whenever the bundle creates it.
  var bodyMO = new MutationObserver(function (muts) {
    for (var i = 0; i < muts.length; i++) {
      for (var j = 0; j < muts[i].addedNodes.length; j++) {
        var n = muts[i].addedNodes[j];
        if (n.nodeType !== 1) continue;
        var panel = n.matches && n.matches(PANEL_SEL) ? n : n.querySelector && n.querySelector(PANEL_SEL);
        if (panel) watchPanel(panel);
      }
    }
  });

  /* ---------------- 2. walking (double-click + keyboard) ------------------ */
  var positioned = false,
    walkEpoch = 0,
    keys = {},
    keyRAF = 0;

  function size() {
    var r = root().getBoundingClientRect();
    return { w: r.width || 92, h: r.height || 92 };
  }
  function enableFree() {
    var r = root();
    if (positioned) return;
    var b = r.getBoundingClientRect();
    r.classList.add("kdcv-pet-has-position");
    r.style.setProperty("inset", "auto", "important");
    r.style.setProperty("left", b.left + "px", "important");
    r.style.setProperty("top", b.top + "px", "important");
    r.style.setProperty("transform", "none", "important");
    positioned = true;
  }
  function clampX(x) { return Math.max(4, Math.min(x, window.innerWidth - size().w - 4)); }
  function clampY(y) { return Math.max(4, Math.min(y, window.innerHeight - size().h - 4)); }
  function setPos(x, y) {
    var r = root();
    r.style.setProperty("left", clampX(x) + "px", "important");
    r.style.setProperty("top", clampY(y) + "px", "important");
  }
  function cur() { var b = root().getBoundingClientRect(); return { x: b.left, y: b.top }; }

  function walkTo(tx, ty) {
    var r = root();
    if (!r) return;
    enableFree();
    var ep = ++walkEpoch;
    var s = size();
    var start = cur();
    var gx = clampX(tx - s.w / 2),
      gy = clampY(ty - s.h / 2);
    var dist = Math.hypot(gx - start.x, gy - start.y);
    var dur = Math.max(200, (dist / 480) * 1000);
    var t0 = performance.now();
    r.setAttribute("data-kohan-walking", gx >= start.x ? "right" : "left");
    function tick(now) {
      if (ep !== walkEpoch) return;
      var p = Math.min(1, (now - t0) / dur);
      var e = p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2;
      setPos(start.x + (gx - start.x) * e, start.y + (gy - start.y) * e);
      if (p < 1) requestAnimationFrame(tick);
      else r.removeAttribute("data-kohan-walking");
    }
    requestAnimationFrame(tick);
  }

  function keyLoop() {
    var dx = (keys.right ? 1 : 0) - (keys.left ? 1 : 0);
    var dy = (keys.down ? 1 : 0) - (keys.up ? 1 : 0);
    var r = root();
    if ((dx === 0 && dy === 0) || !r) {
      keyRAF = 0;
      if (r) r.removeAttribute("data-kohan-walking");
      return;
    }
    enableFree();
    walkEpoch++; // cancel any click-walk
    r.setAttribute("data-kohan-walking", dx < 0 ? "left" : "right");
    var p = cur();
    setPos(p.x + dx * 6, p.y + dy * 6);
    keyRAF = requestAnimationFrame(keyLoop);
  }
  function mapKey(k) {
    // Arrow keys only — letters collide with normal typing, this isn't a game.
    return k === "ArrowLeft" ? "left" :
      k === "ArrowRight" ? "right" :
      k === "ArrowUp" ? "up" :
      k === "ArrowDown" ? "down" : null;
  }

  function boot() {
    var r = root();
    if (!r) return void setTimeout(boot, 250);

    bodyMO.observe(document.body, { childList: true, subtree: true });
    // if a panel is already open, catch it
    var existing = document.querySelector(PANEL_SEL);
    if (existing) watchPanel(existing);

    // make the avatar keyboard-focusable for walking
    if (!r.hasAttribute("tabindex")) r.setAttribute("tabindex", "0");

    document.addEventListener("dblclick", function (e) {
      if (!(e.target instanceof Element)) return;
      if (e.target.closest(ROOT_SEL)) return;
      if (e.target.closest("a,button,input,textarea,select,[contenteditable]")) return;
      walkTo(e.clientX, e.clientY);
    });
    document.addEventListener("keydown", function (e) {
      // Never hijack keystrokes meant for a text field — the chat panel's
      // input lives inside the same avatar root, so containment alone
      // isn't enough to tell "focused on the avatar" from "typing in it".
      var ae = document.activeElement;
      if (ae && (ae.tagName === "INPUT" || ae.tagName === "TEXTAREA" || ae.isContentEditable)) return;
      var r2 = root();
      if (!r2 || !r2.contains(ae) && ae !== r2) return;
      var m = mapKey(e.key);
      if (!m) return;
      e.preventDefault();
      keys[m] = true;
      if (!keyRAF) keyRAF = requestAnimationFrame(keyLoop);
    });
    document.addEventListener("keyup", function (e) {
      var m = mapKey(e.key);
      if (m) keys[m] = false;
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})();

/* ---- decorative controls built by ai-pet.min.js -------------------------
   `.kdcv-pet-nudge-button` is an empty, tabindex="-1" hit-target that exists
   only to catch a drag. It has no accessible name because it has no meaning to
   announce — so it is hidden from assistive tech rather than given a fake one.
   ai-pet.min.js is minified with no source, so this is done from here. */
(function () {
  "use strict";
  function hideDecorative() {
    var els = document.querySelectorAll(".kdcv-pet-nudge-button:not([aria-hidden])");
    for (var i = 0; i < els.length; i++) {
      if (!els[i].textContent.trim() && !els[i].getAttribute("aria-label")) {
        els[i].setAttribute("aria-hidden", "true");
      }
    }
  }
  if ("MutationObserver" in window) {
    // setTimeout, not rAF: rAF is paused while the tab is hidden, and the pet
    // mounts on a 3s timer that keeps running in a background tab.
    new MutationObserver(function () { window.setTimeout(hideDecorative, 0); })
      .observe(document.documentElement, { subtree: true, childList: true });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", hideDecorative, { once: true });
  } else {
    hideDecorative();
  }
})();
