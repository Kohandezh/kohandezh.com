/**
 * Kohan Avatar — self-contained browser runtime.
 *
 * Renders the single interactive "Kohan" character from the authoritative
 * 8x11 sprite atlas (192x208 cells, sprite v2) plus the pre-processed
 * supplemental WebP strips. Ported from the authoritative Codex runtime
 * controller into a framework-free IIFE for WordPress, driven entirely by
 * asset URLs injected server-side through window.KohanAvatarConfig — the
 * browser never sees a filesystem path.
 *
 * Public API (see README):
 *   window.KohanAvatar.setMood(name, opts)
 *   window.KohanAvatar.play(name, opts)
 *   window.KohanAvatar.stop()
 *   window.KohanAvatar.reset()
 *   window.KohanAvatar.destroy()
 *   window.KohanAvatar.refreshAssets()
 *
 * Reacts to: window.dispatchEvent(new CustomEvent('kohan:avatar:mood',{detail:{...}}))
 */
(function () {
  "use strict";
  // Distinct from the legacy theme avatar's __KOHAN_AVATAR_BOOTSTRAPPED__ flag,
  // which the plugin pre-empts from the head to disable the old avatar.
  if (window.__KOHAN_AVATAR_PLUGIN__) return;
  window.__KOHAN_AVATAR_PLUGIN__ = true;

  var CFG = window.KohanAvatarConfig || {};
  var BASE = (CFG.assetBase || "").replace(/\/$/, "");
  if (!BASE) return; // nothing to load without a server-provided asset base

  var OPTS = CFG.options || {};
  var enabled = OPTS.enabled !== false;
  if (!enabled) return;

  var prefersReduced =
    window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---- atlas contract (mirrors assets/kohan/spritesheet.json) ---------- */
  var COLS = 8,
    ROWS = 11,
    CELL_W = 192,
    CELL_H = 208;

  // Native atlas actions rendered by shifting background-position on the root.
  var STANDARD_ACTIONS = {
    jump: { row: 4, frames: 5, frameMs: 150, loops: 2 },
    laptop: { row: 7, frames: 6, frameMs: 240, loops: 2 },
    ipad: { row: 8, frames: 6, frameMs: 260, loops: 2 },
    vision: { row: 6, frames: 6, frameMs: 260, loops: 2 },
    wave: { row: 3, frames: 4, frameMs: 220, loops: 2 },
  };

  // Supplemental strips (single-file horizontal frame strips, WP-enqueued).
  var SUPPLEMENTAL = {
    angry: BASE + "/supplemental/kohan-angry.webp",
    colt: BASE + "/supplemental/kohan-colt-threat.webp",
    confused: BASE + "/supplemental/kohan-confused.webp",
    dragAnnoyed: BASE + "/supplemental/kohan-drag-annoyed.webp",
    fallScared: BASE + "/supplemental/kohan-fall-scared.webp",
    goodbye: BASE + "/supplemental/kohan-goodbye.webp",
    roulette: BASE + "/supplemental/kohan-russian-roulette.webp",
    wink: BASE + "/supplemental/kohan-wink.webp",
  };

  // 16 look directions -> atlas cell (from spritesheet.json). rows 9 & 10.
  var LOOK = [];
  (function () {
    for (var i = 0; i < 8; i++) LOOK.push({ row: 9, col: i });
    for (var j = 0; j < 8; j++) LOOK.push({ row: 10, col: j });
  })();
  var NEUTRAL = { row: 0, col: 6 };

  var CLICK_ACTIONS = [
    "wink", "wave", "jump", "angry", "confused",
    "laptop", "ipad", "guarded", "roulette", "walk",
  ];
  var IDLE_ACTIONS = ["ipad", "laptop", "vision", "walk", "wave", "wink"];

  // Lifecycle mood -> internal action name.
  var MOOD_MAP = {
    idle: "idle",
    "macbook-work": "laptop",
    "ipad-review": "ipad",
    confused: "confused",
    "confused-vision": "confused",
    wink: "wink",
    waving: "wave",
    wave: "wave",
    jumping: "jump",
    angry: "angry",
    "angry-still": "angry",
    guarded: "colt",
    "russian-roulette": "roulette",
    "walking-patrol": "walk",
    "goodbye-smoke": "goodbye",
    "running-right": "walk",
    "running-left": "walk",
  };

  var sleep = function (ms) {
    return new Promise(function (r) {
      timers.push(window.setTimeout(r, ms));
    });
  };
  var pick = function (a) {
    return a[Math.floor(Math.random() * a.length)];
  };

  /* ---- runtime state --------------------------------------------------- */
  var root = null,
    atlas = null,
    timers = [],
    rafId = 0,
    idleTimer = 0,
    epoch = 0,
    cleanupActive = null,
    idleCount = 0,
    pointer = null,
    dragging = false,
    hidden = false,
    destroyed = false,
    lookRAF = 0;

  function clearTimers() {
    timers.forEach(clearTimeout);
    timers = [];
  }

  function cancelAnimation() {
    epoch += 1;
    if (cleanupActive) {
      try { cleanupActive(); } catch (e) {}
    }
    cleanupActive = null;
  }

  function cellPosition(rw, cl) {
    // background-size is COLS*100% x ROWS*100%; position as percentages.
    var x = COLS === 1 ? 0 : (cl / (COLS - 1)) * 100;
    var y = ROWS === 1 ? 0 : (rw / (ROWS - 1)) * 100;
    return x + "% " + y + "%";
  }

  /* ---- DOM ------------------------------------------------------------- */
  function build() {
    if (root) return;
    root = document.createElement("div");
    root.className = "kohan-avatar-root";
    root.setAttribute("data-avatar-mascot", "true");
    root.setAttribute("data-avatar-id", "kohan");
    root.setAttribute("data-avatar-state", "idle");
    root.setAttribute("role", "button");
    root.setAttribute("tabindex", "0");
    root.setAttribute("aria-label", CFG.ariaLabel || "Kohan avatar");
    root.style.setProperty("--kohan-cols", COLS);
    root.style.setProperty("--kohan-rows", ROWS);
    root.style.backgroundImage = 'url("' + BASE + '/spritesheet.webp")';
    root.style.backgroundSize = COLS * 100 + "% " + ROWS * 100 + "%";
    root.style.backgroundPosition = cellPosition(NEUTRAL.row, NEUTRAL.col);

    var pos = OPTS.position || "bottom-left";
    root.setAttribute("data-position", pos);
    var scale = parseFloat(OPTS.scale) || 1;
    root.style.setProperty("--kohan-scale", scale);

    document.body.appendChild(root);
    buildControls();

    // Preload the atlas so first paint has no flash.
    atlas = new Image();
    atlas.src = BASE + "/spritesheet.webp";

    wireInteractions();
  }

  function setIdleBackground() {
    if (!root) return;
    root.style.backgroundPosition = cellPosition(NEUTRAL.row, NEUTRAL.col);
  }

  /* ---- background-position (atlas) renderer ---------------------------- */
  function beginBackgroundOverride() {
    var original = root.style.getPropertyValue("background-position");
    var priority = root.style.getPropertyPriority("background-position");
    var restored = false;
    return {
      set: function (p) {
        root.style.setProperty("background-position", p, "important");
      },
      restore: function () {
        if (restored) return;
        restored = true;
        if (original) root.style.setProperty("background-position", original, priority);
        else setIdleBackground();
      },
    };
  }

  /* ---- overlay (supplemental strip) renderer --------------------------- */
  function beginOverlay(url, frameCount) {
    var originalImage = root.style.getPropertyValue("background-image");
    var priority = root.style.getPropertyPriority("background-image");
    var overlay = document.createElement("div");
    overlay.setAttribute("data-kohan-overlay", "true");
    overlay.style.cssText =
      "position:absolute;inset:0;z-index:20;pointer-events:none;" +
      'background-image:url("' + url + '");background-repeat:no-repeat;' +
      "background-size:" + frameCount * 100 + "% 100%;background-position:0% 0%;";
    root.style.setProperty("background-image", "none", "important");
    root.appendChild(overlay);
    var restored = false;
    return {
      set: function (index) {
        var x = frameCount === 1 ? 0 : (index / (frameCount - 1)) * 100;
        overlay.style.backgroundPosition = x + "% 0%";
      },
      restore: function () {
        if (restored) return;
        restored = true;
        overlay.remove();
        if (originalImage) root.style.setProperty("background-image", originalImage, priority);
        else root.style.removeProperty("background-image");
      },
    };
  }

  /* ---- players --------------------------------------------------------- */
  function playStandard(name) {
    var spec = STANDARD_ACTIONS[name];
    if (!spec || !root) return Promise.resolve(false);
    cancelAnimation();
    var ep = epoch;
    var r = beginBackgroundOverride();
    cleanupActive = r.restore;
    var loop = 0,
      index = 0;
    function step() {
      if (ep !== epoch) return Promise.resolve(false);
      if (loop >= spec.loops) {
        r.restore();
        cleanupActive = null;
        return Promise.resolve(true);
      }
      r.set(cellPosition(spec.row, index));
      return sleep(spec.frameMs).then(function () {
        index += 1;
        if (index >= spec.frames) { index = 0; loop += 1; }
        return step();
      });
    }
    return step();
  }

  function playOverlay(o) {
    if (!root) return Promise.resolve(false);
    cancelAnimation();
    var ep = epoch;
    var r = beginOverlay(SUPPLEMENTAL[o.asset], o.frameCount);
    cleanupActive = r.restore;
    var loops = o.loops || 1,
      loop = 0;
    function cycle() {
      var i = 0;
      function frame() {
        if (ep !== epoch) return Promise.resolve(false);
        if (i >= o.order.length) return Promise.resolve(true);
        r.set(o.order[i]);
        var d = o.delays[i] != null ? o.delays[i] : o.delays[o.delays.length - 1] || 220;
        return sleep(d).then(function () { i += 1; return frame(); });
      }
      return frame().then(function (ok) {
        if (ok === false) return false;
        loop += 1;
        var more = o.whileDragging ? dragging : loop < loops;
        if (ep === epoch && more) return cycle();
        return true;
      });
    }
    return cycle().then(function (ok) {
      if (ok === false) return false;
      var tail = o.holdMs && ep === epoch ? sleep(o.holdMs) : Promise.resolve();
      return tail.then(function () {
        if (ep === epoch) { r.restore(); cleanupActive = null; }
        return true;
      });
    });
  }

  // Site-capped patrol: two short visible steps right, two back, restore origin.
  function playWalkPatrol() {
    if (!root) return Promise.resolve(false);
    cancelAnimation();
    var ep = epoch;
    var bg = beginBackgroundOverride();
    var originTransform = root.style.getPropertyValue("transform");
    var originPriority = root.style.getPropertyPriority("transform");
    var restore = function () {
      bg.restore();
      if (originTransform) root.style.setProperty("transform", originTransform, originPriority);
      else root.style.removeProperty("transform");
    };
    cleanupActive = restore;
    root.setAttribute("data-kohan-patrol", "forward");
    // 2 steps right (row 1), 2 steps back (row 2); ≤ 24px so it stays put.
    var route = [
      { row: 1, frame: 0, x: 8 },
      { row: 1, frame: 2, x: 16 },
      { row: 2, frame: 0, x: 8 },
      { row: 2, frame: 2, x: 0 },
    ];
    var k = 0;
    function step() {
      if (ep !== epoch) { root.removeAttribute("data-kohan-patrol"); return Promise.resolve(false); }
      if (k >= route.length) {
        restore();
        root.removeAttribute("data-kohan-patrol");
        cleanupActive = null;
        return Promise.resolve(true);
      }
      var s = route[k];
      bg.set(cellPosition(s.row, s.frame));
      root.style.setProperty("transform", "translateX(" + s.x + "px)", "important");
      return sleep(260).then(function () { k += 1; return step(); });
    }
    return step();
  }

  function playRoulette() {
    var fire = Math.random() < (OPTS.fireProbability != null ? OPTS.fireProbability : 0.5);
    return playOverlay({
      asset: "roulette",
      frameCount: 6,
      order: fire ? [0, 1, 2, 1, 2, 3, 5] : [0, 1, 2, 1, 2, 3, 4],
      delays: [420, 360, 260, 260, 260, 700, fire ? 560 : 900],
      holdMs: 300,
    }).then(function (played) {
      if (played && fire) {
        return playOverlay({
          asset: "fallScared",
          frameCount: 6,
          order: [0, 1, 2, 3],
          delays: [230, 230, 300, 500],
          holdMs: 200,
        }).then(function () {
          return playNamed("confused"); // IDK reaction, then idle
        });
      }
      return played;
    });
  }

  function playNamed(name) {
    if (STANDARD_ACTIONS[name]) return playStandard(name);
    if (name === "walk") return playWalkPatrol();
    if (name === "roulette") return playRoulette();
    if (name === "angry")
      return playOverlay({ asset: "angry", frameCount: 1, order: [0], delays: [1250], holdMs: 350 });
    if (name === "wink")
      return playOverlay({ asset: "wink", frameCount: 1, order: [0], delays: [850], holdMs: 200 });
    if (name === "confused")
      return playOverlay({ asset: "confused", frameCount: 1, order: [0], delays: [1300], holdMs: 300 });
    if (name === "colt")
      return playOverlay({ asset: "colt", frameCount: 4, order: [0, 1, 2, 3], delays: [400, 350, 500, 850], holdMs: 250 });
    if (name === "goodbye") return playGoodbye();
    return Promise.resolve(false);
  }

  function playGoodbye() {
    return playOverlay({
      asset: "goodbye",
      frameCount: 7,
      order: [0, 1, 2, 3, 4, 5, 6],
      delays: [340, 320, 420, 380, 360, 360, 700],
      holdMs: 250,
    }).then(function () {
      if (root) {
        root.setAttribute("data-kohan-hidden", "true");
        root.setAttribute("data-avatar-state", "hidden");
      }
      return true;
    });
  }

  /* ---- pointer-look (16 directions), hover only ------------------------ */
  function onPointerLook(e) {
    if (!root || dragging || destroyed) return;
    if (root.getAttribute("data-avatar-state") !== "idle") return;
    if (lookRAF) return;
    lookRAF = requestAnimationFrame(function () {
      lookRAF = 0;
      var rect = root.getBoundingClientRect();
      var cx = rect.left + rect.width / 2;
      var cy = rect.top + rect.height / 2;
      var ang = Math.atan2(e.clientY - cy, e.clientX - cx) * (180 / Math.PI);
      if (ang < 0) ang += 360;
      var idx = Math.round(ang / 22.5) % 16;
      var cell = LOOK[idx];
      root.style.backgroundPosition = cellPosition(cell.row, cell.col);
    });
  }
  function resetLook() {
    if (!root || destroyed) return;
    if (root.getAttribute("data-avatar-state") === "idle") setIdleBackground();
  }

  /* ---- interactions ---------------------------------------------------- */
  function wireInteractions() {
    // Hover: pointer-look only, never continuous jumping.
    root.addEventListener("pointermove", onPointerLook, { passive: true });
    root.addEventListener("pointerleave", resetLook, { passive: true });

    // Real click -> one random action (suppressed if it was a drag).
    root.addEventListener("click", function () {
      if (dragging) return;
      if (root.getAttribute("data-avatar-state") !== "idle") return;
      runAction(pick(CLICK_ACTIONS));
    });
    root.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") { e.preventDefault(); runAction(pick(CLICK_ACTIONS)); }
    });

    // Drag via Pointer Events + capture.
    document.addEventListener("pointerdown", function (e) {
      if (e.button !== 0 || !(e.target instanceof Element)) return;
      if (!e.target.closest('[data-avatar-mascot="true"]')) return;
      // Never capture a press on an interactive child. setPointerCapture
      // retargets every later pointer event — and the click composed from
      // them — onto the ROOT, so with capture in place a real mouse press on
      // the eye or +/- buttons produced a click on the avatar instead of the
      // button. (A programmatic el.click() bypasses pointer events entirely,
      // which is how this survived testing.)
      if (e.target.closest("button, a, input, textarea, .kohan-size-controls, .kohan-chat-launcher, .kohan-chat-panel")) return;
      pointer = { id: e.pointerId, x: e.clientX, y: e.clientY, ox: 0, oy: 0 };
      var b = root.getBoundingClientRect();
      pointer.ox = e.clientX - b.left;
      pointer.oy = e.clientY - b.top;
      dragging = false;
      try { root.setPointerCapture(e.pointerId); } catch (er) {}
    }, true);

    /* The drag used to play the scared overlay and nothing else — no code
       ever updated the root's position, so the character stayed put while
       the pointer left without him. The overlay stays; the movement is new. */
    function placeRoot(x, y) {
      var b = root.getBoundingClientRect();
      var maxX = window.innerWidth - b.width;
      var maxY = window.innerHeight - b.height;
      var nx = Math.max(0, Math.min(maxX, x));
      var ny = Math.max(0, Math.min(maxY, y));
      root.style.setProperty("inset", "auto", "important");
      root.style.setProperty("left", nx + "px", "important");
      root.style.setProperty("top", ny + "px", "important");
      // Near the right edge there is no room for the control column on his
      // right — flip it to his left so it can never be pushed off screen.
      root.setAttribute("data-kohan-side",
        nx + b.width + 44 > window.innerWidth ? "right" : "left");
      return { x: nx, y: ny };
    }

    function persistPosition(pos) {
      try { window.localStorage.setItem("kohanAvatarPos", JSON.stringify(pos)); } catch (er) {}
    }

    // Restore a previous drag's position, clamped to the current viewport.
    (function restorePosition() {
      var raw = null;
      try { raw = window.localStorage.getItem("kohanAvatarPos"); } catch (er) {}
      if (!raw) return;
      try {
        var pos = JSON.parse(raw);
        if (typeof pos.x === "number" && typeof pos.y === "number") placeRoot(pos.x, pos.y);
      } catch (er) {}
    })();

    document.addEventListener("pointermove", function (e) {
      if (!pointer || pointer.id !== e.pointerId) return;
      if (!dragging) {
        if (Math.hypot(e.clientX - pointer.x, e.clientY - pointer.y) < 6) return;
        dragging = true;
        var scared = Math.random() < 0.5;
        root.setAttribute("data-avatar-state", "drag");
        playOverlay({
          asset: scared ? "fallScared" : "dragAnnoyed",
          frameCount: 6,
          order: [0, 1, 2, 3, 4, 5],
          delays: [180, 180, 180, 180, 180, 240],
          whileDragging: true,
        });
      }
      // Keep the grab point under the finger: position = pointer - grab offset.
      var pos = placeRoot(e.clientX - pointer.ox, e.clientY - pointer.oy);
      persistPosition(pos);
      root.dispatchEvent(new CustomEvent("kohan:moved", { bubbles: false }));
    }, true);

    function finish(e) {
      if (!pointer || pointer.id !== e.pointerId) return;
      var wasDrag = dragging;
      pointer = null;
      try { root.releasePointerCapture(e.pointerId); } catch (er) {}
      if (wasDrag) {
        cancelAnimation();
        // Settle briefly into angry, then idle.
        playNamed("angry").then(function () {
          dragging = false;
          toIdle();
        });
      } else {
        dragging = false;
      }
    }
    document.addEventListener("pointerup", finish, true);
    document.addEventListener("pointercancel", finish, true);

    document.addEventListener("visibilitychange", function () {
      hidden = document.hidden;
    });
  }

  function toIdle() {
    if (!root || destroyed) return;
    if (root.getAttribute("data-avatar-state") === "hidden") return;
    root.setAttribute("data-avatar-state", "idle");
    setIdleBackground();
  }

  // Run an action then return to idle.
  function runAction(name, returnTo) {
    if (!root || destroyed) return Promise.resolve(false);
    if (root.getAttribute("data-avatar-state") === "hidden" && name !== "reset") return Promise.resolve(false);
    root.setAttribute("data-avatar-state", "busy");
    return Promise.resolve(playNamed(name)).then(function (r) {
      if (name !== "goodbye") root.setAttribute("data-avatar-state", returnTo || "idle");
      if (name !== "goodbye") setIdleBackground();
      return r;
    });
  }

  /* ---- idle loop ------------------------------------------------------- */
  function scheduleIdle() {
    if (prefersReduced || destroyed) return;
    var range = OPTS.idleRangeMs || [30000, 75000];
    var wait = range[0] + Math.floor(Math.random() * (range[1] - range[0] + 1));
    idleTimer = window.setTimeout(function () {
      idleTick().then(scheduleIdle);
    }, wait);
  }
  function idleTick() {
    if (!root || destroyed || hidden || dragging) return Promise.resolve();
    if (root.getAttribute("data-avatar-state") !== "idle") return Promise.resolve();
    idleCount += 1;
    var every = OPTS.rouletteEvery || 3;
    var doRoulette = OPTS.weaponMoods !== false && idleCount % every === 0;
    root.setAttribute("data-avatar-state", "busy");
    var p = doRoulette ? playRoulette() : playNamed(pick(IDLE_ACTIONS));
    return Promise.resolve(p).then(function () {
      toIdle();
    });
  }

  /* ---- public API ------------------------------------------------------ */
  var API = {
    setMood: function (name, opts) {
      var action = MOOD_MAP[name] || name;
      return runAction(action, (opts && opts.returnTo) || "idle");
    },
    play: function (name, opts) {
      var action = MOOD_MAP[name] || name;
      return runAction(action, (opts && opts.returnTo) || "idle");
    },
    stop: function () {
      cancelAnimation();
      toIdle();
    },
    reset: function () {
      cancelAnimation();
      if (root) {
        root.removeAttribute("data-kohan-hidden");
        root.removeAttribute("data-kohan-patrol");
        root.style.removeProperty("transform");
        root.setAttribute("data-avatar-state", "idle");
        setIdleBackground();
      }
    },
    destroy: function () {
      destroyed = true;
      cancelAnimation();
      clearTimers();
      if (idleTimer) clearTimeout(idleTimer);
      if (lookRAF) cancelAnimationFrame(lookRAF);
      if (root && root.parentNode) root.parentNode.removeChild(root);
      root = null;
    },
    refreshAssets: function () {
      // Re-read version.json (cache-busted) and reload the atlas image.
      return fetch(BASE + "/../version.json?ts=" + Date.now(), { cache: "no-store" })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (v) {
          if (root) {
            var bust = v && v.hash ? v.hash.slice(0, 8) : Date.now();
            root.style.backgroundImage = 'url("' + BASE + "/spritesheet.webp?v=" + bust + '")';
          }
          return v;
        })
        .catch(function () { return null; });
    },
  };
  /* ---- eye / size controls, beside the avatar --------------------------
     These used to live in the THEME (kohan-avatar.js + kdcv-interaction-fix.js)
     and were gated on `#kdcv-pet-root`, an element that exists only on the
     static build. On WordPress this plugin owns the avatar, that id is never
     in the document, and the controls were therefore never created at all.
     They belong to whoever owns the avatar, so they are built here.

     Placement is BESIDE the avatar's box — a column pinned to the edge that
     faces the middle of the screen, so it never sits on top of the character
     and never runs off the edge it is docked to. */
  var SCALES = [0.7, 0.85, 1, 1.2, 1.4];
  var SCALE_KEY = "kohanAvatarScale";
  var CTRL_TEXT = {
    en: { group: "Avatar controls", bigger: "Bigger", smaller: "Smaller", hide: "Hide avatar", show: "Show avatar" },
    fa: { group: "کنترل‌های آواتار", bigger: "بزرگ‌تر", smaller: "کوچک‌تر", hide: "پنهان کردن آواتار", show: "نمایش آواتار" },
    ar: { group: "عناصر التحكم", bigger: "أكبر", smaller: "أصغر", hide: "إخفاء الشخصية", show: "إظهار الشخصية" }
  };

  function ctrlText() {
    var l = (document.documentElement.getAttribute("lang") || "en").slice(0, 2).toLowerCase();
    return CTRL_TEXT[l] || CTRL_TEXT.en;
  }

  /* Snap to the NEAREST step rather than requiring an exact hit. The admin
     setting is a free float (0.9, 1.05, …), so an exact lookup returned -1 and
     the "smaller" button came up disabled on load — which is what "resize does
     not work" looked like from the outside. An index is the state; the float
     is only how it is stored. */
  function nearestIndex(v) {
    var best = 2, dist = Infinity;
    for (var i = 0; i < SCALES.length; i++) {
      var d = Math.abs(SCALES[i] - v);
      if (d < dist) { dist = d; best = i; }
    }
    return best;
  }

  function readScaleIndex() {
    var v = NaN;
    try { v = parseFloat(window.localStorage.getItem(SCALE_KEY)); } catch (e) {}
    if (!isFinite(v)) v = parseFloat(OPTS.scale);
    if (!isFinite(v)) v = 1;
    return nearestIndex(v);
  }

  function writeScale(v) {
    try { window.localStorage.setItem(SCALE_KEY, String(v)); } catch (e) {}
  }

  function ctrlButton(cls, label, glyph) {
    var b = document.createElement("button");
    b.type = "button";
    b.className = cls;
    b.setAttribute("aria-label", label);
    b.title = label;
    if (glyph) b.textContent = glyph;
    // The root is draggable (cursor: grab, touch-action: none), so a press on a
    // control must never begin a drag of the character underneath it.
    ["pointerdown", "mousedown", "touchstart"].forEach(function (ev) {
      b.addEventListener(ev, function (e) { e.stopPropagation(); }, { passive: true });
    });
    return b;
  }

  function buildControls() {
    if (!root || root.querySelector(".kohan-size-controls")) return;
    var t = ctrlText();

    var wrap = document.createElement("div");
    wrap.className = "kohan-size-controls";
    wrap.setAttribute("role", "group");
    wrap.setAttribute("aria-label", t.group);

    var eye = ctrlButton("kohan-eye-button", t.hide, "");
    eye.innerHTML = '<span class="kohan-eye-open" aria-hidden="true">◉</span>' +
                    '<span class="kohan-eye-closed" aria-hidden="true">◌</span>';
    eye.setAttribute("aria-pressed", "false");

    var minus = ctrlButton("kohan-size-button", t.smaller, "−");
    var plus  = ctrlButton("kohan-size-button", t.bigger, "+");

    var index = readScaleIndex();

    function applyIndex(i) {
      index = Math.max(0, Math.min(SCALES.length - 1, i));
      var v = SCALES[index];
      // setProperty with a priority so nothing in either stylesheet can pin the
      // custom property and make the buttons look inert.
      root.style.setProperty("--kohan-scale", String(v), "important");
      writeScale(v);
      minus.disabled = index === 0;
      plus.disabled = index === SCALES.length - 1;
    }

    minus.addEventListener("click", function (e) {
      e.preventDefault();
      applyIndex(index - 1);
    });
    plus.addEventListener("click", function (e) {
      e.preventDefault();
      applyIndex(index + 1);
    });
    eye.addEventListener("click", function (e) {
      e.preventDefault();
      // Deliberately NOT `data-kohan-hidden` — that one belongs to the
      // goodbye-smoke sequence, which sets `opacity: 0` on the root. Opacity
      // applies to the whole subtree, so reusing it would fade this control
      // column to nothing as well and strand the visitor with no way to bring
      // the avatar back. Our attribute removes the sprite and leaves the
      // column at full strength.
      var nowHidden = root.getAttribute("data-kohan-eye-hidden") !== "true";
      if (nowHidden) root.setAttribute("data-kohan-eye-hidden", "true");
      else root.removeAttribute("data-kohan-eye-hidden");
      eye.setAttribute("aria-pressed", nowHidden ? "true" : "false");
      var label = nowHidden ? t.show : t.hide;
      eye.setAttribute("aria-label", label);
      eye.title = label;
    });

    wrap.appendChild(eye);
    wrap.appendChild(minus);
    wrap.appendChild(plus);
    root.appendChild(wrap);
    applyIndex(index);
  }

  window.KohanAvatar = API;

  /* ---- lifecycle event bridge ----------------------------------------- */
  window.addEventListener("kohan:avatar:mood", function (e) {
    var d = (e && e.detail) || {};
    if (!d.mood) return;
    // Only accept allow-listed moods (defence against injected metadata).
    if (!(d.mood in MOOD_MAP) && !STANDARD_ACTIONS[d.mood] && !SUPPLEMENTAL[d.mood]) return;
    API.play(d.mood, { returnTo: d.returnTo || "idle" });
  });

  /* ---- boot ------------------------------------------------------------ */
  // Remove any legacy avatar container the old theme may have left behind
  // (its scripts are pre-empted from the head, but a stale node could remain).
  function removeLegacy() {
    document.querySelectorAll(".kdcv-pet-root").forEach(function (n) {
      if (n.parentNode) n.parentNode.removeChild(n);
    });
  }

  /* ---- free walking (keyboard + double-click) -------------------------- */
  // The avatar can leave its corner and walk around the page. Horizontal
  // motion plays the approved running-right / running-left rows; it always
  // stays inside the viewport and returns to idle when it stops.
  var walkEpoch = 0,
    keysDown = {},
    keyRAF = 0,
    positioned = false;

  function avatarSize() {
    var r = root.getBoundingClientRect();
    return { w: r.width || 132, h: r.height || 143 };
  }

  // Switch from corner-anchored to explicit left/top so JS can move it freely.
  function enableFreePosition() {
    if (positioned) return;
    var r = root.getBoundingClientRect();
    root.removeAttribute("data-position");
    root.style.setProperty("inset", "auto", "important");
    root.style.setProperty("left", r.left + "px", "important");
    root.style.setProperty("top", r.top + "px", "important");
    positioned = true;
  }

  function clampX(x) {
    var s = avatarSize();
    return Math.max(4, Math.min(x, window.innerWidth - s.w - 4));
  }
  function clampY(y) {
    var s = avatarSize();
    return Math.max(4, Math.min(y, window.innerHeight - s.h - 4));
  }

  function setPos(x, y) {
    root.style.setProperty("left", clampX(x) + "px", "important");
    root.style.setProperty("top", clampY(y) + "px", "important");
  }
  function curPos() {
    var r = root.getBoundingClientRect();
    return { x: r.left, y: r.top };
  }

  // Show a running frame for the given horizontal direction (dir: 1 right, -1 left).
  var walkFrame = 0;
  function stepRunFrame(dir) {
    var row = dir >= 0 ? 1 : 2; // 1 = running-right, 2 = running-left
    walkFrame = (walkFrame + 1) % 5; // 5 stride frames
    root.style.setProperty("background-position", cellPosition(row, walkFrame), "important");
  }

  // Double-click anywhere (not on the avatar) -> walk there.
  function walkTo(tx, ty) {
    if (!root || destroyed) return;
    enableFreePosition();
    cancelAnimation();
    root.setAttribute("data-avatar-state", "busy");
    var ep = ++walkEpoch;
    var start = curPos();
    var s = avatarSize();
    var goalX = clampX(tx - s.w / 2);
    var goalY = clampY(ty - s.h / 2);
    var dir = goalX >= start.x ? 1 : -1;
    var t0 = performance.now();
    var speed = 480; // px/sec
    var dist = Math.hypot(goalX - start.x, goalY - start.y);
    var dur = Math.max(220, (dist / speed) * 1000);
    var frameClock = 0;
    function tick(now) {
      if (ep !== walkEpoch || destroyed) return;
      var p = Math.min(1, (now - t0) / dur);
      var e = p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2; // easeInOut
      setPos(start.x + (goalX - start.x) * e, start.y + (goalY - start.y) * e);
      if (now - frameClock > 120) { stepRunFrame(dir); frameClock = now; }
      if (p < 1) requestAnimationFrame(tick);
      else { setIdleBackground(); root.setAttribute("data-avatar-state", "idle"); }
    }
    requestAnimationFrame(tick);
  }

  // Keyboard: arrow keys / WASD nudge the avatar; running animation while held.
  function keyLoop() {
    var dx = 0, dy = 0;
    if (keysDown.left) dx -= 1;
    if (keysDown.right) dx += 1;
    if (keysDown.up) dy -= 1;
    if (keysDown.down) dy += 1;
    if (dx === 0 && dy === 0) {
      keyRAF = 0;
      setIdleBackground();
      root.setAttribute("data-avatar-state", "idle");
      return;
    }
    enableFreePosition();
    cancelAnimation();
    walkEpoch++; // cancel any click-walk in progress
    root.setAttribute("data-avatar-state", "busy");
    var step = 6;
    var pos = curPos();
    setPos(pos.x + dx * step, pos.y + dy * step);
    if (dx !== 0) stepRunFrame(dx);
    keyRAF = requestAnimationFrame(keyLoop);
  }

  function onKeyDown(e) {
    if (!root || destroyed) return;
    // Never hijack keystrokes meant for a text field.
    var ae = document.activeElement;
    if (ae && (ae.tagName === "INPUT" || ae.tagName === "TEXTAREA" || ae.isContentEditable)) return;
    // Only when the avatar is focused, so we never hijack page typing.
    if (ae !== root) return;
    var k = e.key;
    // Arrow keys only — letters collide with normal typing, this isn't a game.
    var mapped =
      k === "ArrowLeft" ? "left" :
      k === "ArrowRight" ? "right" :
      k === "ArrowUp" ? "up" :
      k === "ArrowDown" ? "down" : null;
    if (!mapped) return;
    e.preventDefault();
    keysDown[mapped] = true;
    if (!keyRAF) keyRAF = requestAnimationFrame(keyLoop);
  }
  function onKeyUp(e) {
    var k = e.key;
    var mapped =
      k === "ArrowLeft" ? "left" :
      k === "ArrowRight" ? "right" :
      k === "ArrowUp" ? "up" :
      k === "ArrowDown" ? "down" : null;
    if (mapped) keysDown[mapped] = false;
  }

  function wireWalking() {
    document.addEventListener("dblclick", function (e) {
      if (e.target instanceof Element && e.target.closest(".kohan-avatar-root")) return;
      if (e.target instanceof Element && e.target.closest("a,button,input,textarea,select,[contenteditable]")) return;
      walkTo(e.clientX, e.clientY);
    });
    document.addEventListener("keydown", onKeyDown);
    document.addEventListener("keyup", onKeyUp);
    // Keep inside bounds on viewport resize.
    window.addEventListener("resize", function () {
      if (positioned) { var p = curPos(); setPos(p.x, p.y); }
    }, { passive: true });
  }

  function boot() {
    removeLegacy();
    build();
    wireWalking();
    if (atlas.complete) toIdle();
    else atlas.addEventListener("load", toIdle, { once: true });
    scheduleIdle();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})();
