/**
 * Kohan Avatar — self-contained browser runtime (v3).
 *
 * Renders the single interactive "Kohan" character from the authoritative
 * 8x11 sprite atlas (192x208 cells) plus horizontal WebP strips. Every
 * visual comes from approved artwork; this file only decides WHICH frame
 * shows, WHEN, and how the character moves between frames.
 *
 * What makes it smooth (v3):
 *   - two stacked sprite layers: a new frame fades in over the old one
 *     (60–140 ms), so act changes and head turns never "pop";
 *   - every strip is decoded BEFORE its first frame is shown — no blank
 *     flash the first time an act plays;
 *   - idle is alive: CSS breathing on the compositor plus a natural blink
 *     from the atlas idle row, instead of one frozen cell;
 *   - pointer-look looks where the pointer actually is (the atlas counts
 *     look angles clockwise from UP; v2 counted from RIGHT, so the eyes were
 *     always 90 degrees off), with a dead zone and hysteresis, and NEVER
 *     reacts while the pointer is on the character itself — that was the
 *     "he jumps around when I hover" bug;
 *   - drag is a held-by-the-collar pendulum driven by pointer velocity, a
 *     release can throw him, and he lands with squash-and-stretch.
 *
 * Acts come in groups (click / idle / wait / drag / drop / talk). Extra
 * artwork strips are declared server-side in assets/kohan/acts.json and
 * arrive through window.KohanAvatarConfig.acts, so new poses need no JS.
 *
 * Public API (see README):
 *   window.KohanAvatar.setMood(name, opts) / play(name, opts)
 *   window.KohanAvatar.stop() / reset() / destroy() / refreshAssets()
 *   window.KohanAvatar.lookAt(x, y)        — turn the head toward a point
 *   window.KohanAvatar.talk(on)            — talking gesture loop on/off
 *   window.KohanAvatar.group(name)         — play a random act of a group
 *   window.KohanAvatar.root()              — the avatar element
 *
 * Events out: kohan:avatar:click {x,y}, kohan:moved, kohan:avatar:state.
 * Events in:  kohan:avatar:mood {mood, returnTo}.
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
  if (OPTS.enabled === false) return;

  // CSS hides the floating companion on narrow screens. Stop before building
  // it so those visitors do not download the sprite atlas needlessly.
  if (window.matchMedia && window.matchMedia("(max-width: 560px)").matches) return;

  var prefersReduced =
    !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);

  /* ---- atlas contract (mirrors assets/kohan/spritesheet.json) ---------- */
  var COLS = 8,
    ROWS = 11;
  var ATLAS_URL = CFG.atlasUrl || BASE + "/spritesheet.webp";
  var NEUTRAL = { row: 0, col: 6 };
  var BLINK = { row: 0, col: 4 };

  // 16 look directions. spritesheet.json: 0deg = UP, clockwise; rows 9 and 10.
  var LOOK = [];
  (function () {
    for (var i = 0; i < 16; i++) LOOK.push({ row: 9 + (i >> 3), col: i & 7 });
  })();

  /* ---- strips ---------------------------------------------------------- */
  var STRIPS = {
    angry: { url: BASE + "/supplemental/kohan-angry.webp", n: 1 },
    colt: { url: BASE + "/supplemental/kohan-colt-threat.webp", n: 4 },
    confused: { url: BASE + "/supplemental/kohan-confused.webp", n: 1 },
    dragAnnoyed: { url: BASE + "/supplemental/kohan-drag-annoyed.webp", n: 6 },
    fallScared: { url: BASE + "/supplemental/kohan-fall-scared.webp", n: 6 },
    goodbye: { url: BASE + "/supplemental/kohan-goodbye.webp", n: 7 },
    roulette: { url: BASE + "/supplemental/kohan-russian-roulette.webp", n: 6 },
    wink: { url: BASE + "/supplemental/kohan-wink.webp", n: 1 },
  };

  /* ---- acts -----------------------------------------------------------
     frames: list of {row,col} (atlas) or strip indices; ms: one number or a
     per-frame list; blend: crossfade ms between frames; motion: a CSS motion
     class for the body while the act plays; loops; hold: ms on the last frame. */
  function atlasRow(row, cols) {
    return cols.map(function (c) { return { row: row, col: c }; });
  }
  var ACTS = {
    wave: { frames: atlasRow(3, [0, 1, 2, 1, 2, 3]), ms: 170, blend: 70, motion: "talk" },
    jump: { frames: atlasRow(4, [0, 1, 2, 3, 4]), ms: [120, 110, 170, 120, 160], blend: 50, motion: "hop" },
    laptop: { frames: atlasRow(7, [0, 1, 2, 3, 4, 5]), ms: 240, loops: 2, blend: 110 },
    ipad: { frames: atlasRow(8, [0, 1, 2, 3, 4, 5]), ms: 260, loops: 2, blend: 110 },
    vision: { frames: atlasRow(6, [0, 1, 2, 3, 4, 5]), ms: 260, loops: 2, blend: 110 },
    stern: { frames: atlasRow(5, [0, 1, 2, 3, 4, 5, 6, 7]), ms: 190, blend: 90 },
    angry: { strip: "angry", frames: [0], ms: 1150, blend: 120, motion: "shake" },
    wink: { strip: "wink", frames: [0], ms: 850, blend: 120 },
    confused: { strip: "confused", frames: [0], ms: 1300, blend: 120, motion: "dizzy" },
    colt: { strip: "colt", frames: [0, 1, 2, 3], ms: [400, 350, 500, 850], blend: 90, hold: 250 },
    goodbyeWave: { strip: "goodbye", frames: [0, 1, 2, 3, 4, 5, 6], ms: [340, 320, 420, 380, 360, 360, 700], blend: 80, hold: 250 },
    // Looking around: the head-turn cells as a slow, blended glance.
    lookAround: {
      frames: [LOOK[12], LOOK[13], LOOK[14], NEUTRAL, LOOK[4], LOOK[3], LOOK[2], NEUTRAL, BLINK, NEUTRAL],
      ms: [420, 300, 520, 260, 420, 300, 520, 260, 120, 300], blend: 130, motion: "sway",
    },
    // Reading the page below him — the look-down cells, for a "thinking" beat.
    ponder: {
      frames: [LOOK[9], LOOK[8], LOOK[7], LOOK[8], BLINK, NEUTRAL],
      ms: [500, 700, 500, 600, 120, 260], blend: 140,
    },
    // Drag acts: loop while held.
    dragAnnoyed: { strip: "dragAnnoyed", frames: [1, 2, 3, 4, 5, 4, 3, 2], ms: 170, blend: 60, whileDragging: true },
    fallScared: { strip: "fallScared", frames: [0, 1, 2, 3, 4, 5], ms: [200, 200, 240, 300, 180, 220], blend: 60, whileDragging: true },
    // Dangling: neutral cells while the pendulum (drag physics) does the work.
    dangle: { frames: [NEUTRAL, NEUTRAL, BLINK, NEUTRAL, LOOK[7], LOOK[8], LOOK[9], NEUTRAL], ms: [600, 400, 110, 300, 260, 400, 260, 300], blend: 120, whileDragging: true },
    // Drop acts: land, then a short reaction.
    landSquash: { frames: atlasRow(4, [3, 4, 0]).concat([NEUTRAL]), ms: [90, 160, 160, 260], blend: 60, motion: "land" },
    landDizzy: { strip: "confused", frames: [0], ms: 1100, blend: 90, motion: "dizzy" },
    landHuff: { frames: atlasRow(5, [0, 1, 2, 3]), ms: 170, blend: 80, motion: "shake" },
  };

  var GROUPS = {
    click: ["wave", "wink", "jump", "laptop", "ipad", "confused", "lookAround"],
    idle: ["ipad", "laptop", "vision", "walk", "wave", "wink", "lookAround"],
    wait: ["lookAround", "ponder", "ipad", "vision"],
    drag: ["dragAnnoyed", "fallScared", "dangle"],
    drop: ["landSquash", "landDizzy", "landHuff"],
    talk: ["wave", "ipad"],
    think: ["laptop", "ponder"],
  };

  // Server-declared extra strips (assets/kohan/acts.json). Each one becomes a
  // named act and joins its group; a missing or malformed entry is skipped.
  (function registerExtraActs() {
    var list = Array.isArray(CFG.acts) ? CFG.acts : [];
    list.forEach(function (a) {
      if (!a || typeof a.name !== "string" || !/^[a-z][a-z0-9-]{1,40}$/.test(a.name)) return;
      if (typeof a.url !== "string" || !a.url) return;
      var n = parseInt(a.frames, 10);
      if (!(n >= 1 && n <= 24)) return;
      var key = "x:" + a.name;
      STRIPS[key] = { url: a.url, n: n };
      var order = Array.isArray(a.order) && a.order.length ? a.order.filter(function (i) { return i >= 0 && i < n; }) : null;
      if (!order || !order.length) { order = []; for (var i = 0; i < n; i++) order.push(i); }
      var ms = Array.isArray(a.frameMs) ? a.frameMs.map(Number) : Number(a.frameMs) || 200;
      ACTS[a.name] = {
        strip: key, frames: order, ms: ms, blend: Number(a.blend) || 90,
        loops: Math.max(1, Math.min(6, parseInt(a.loops, 10) || 1)),
        motion: typeof a.motion === "string" ? a.motion : undefined,
        whileDragging: a.group === "drag",
        hold: Number(a.hold) || 0,
      };
      var g = typeof a.group === "string" && GROUPS[a.group] ? a.group : null;
      if (g) {
        // A purpose-made pose is a better fit than a borrowed one: give it
        // double weight in its group.
        GROUPS[g].push(a.name, a.name);
      }
    });
  })();

  // Lifecycle mood -> internal act name.
  var MOOD_MAP = {
    idle: "idle",
    "macbook-work": "laptop",
    "ipad-review": "ipad",
    confused: "confused",
    "confused-vision": "vision",
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
    "looking-around": "lookAround",
    thinking: "@think",
    talking: "@talk",
    waiting: "@wait",
  };

  function pick(a, avoid) {
    if (!a || !a.length) return null;
    if (a.length > 1 && avoid) {
      var pool = a.filter(function (x) { return x !== avoid; });
      if (pool.length) a = pool;
    }
    return a[Math.floor(Math.random() * a.length)];
  }

  /* ---- runtime state --------------------------------------------------- */
  var root = null,
    rig = null,
    stage = null,
    body = null,
    shadow = null,
    layers = [],
    topLayer = 0,
    timers = [],
    idleTimer = 0,
    waitTimer = 0,
    blinkTimer = 0,
    epoch = 0,
    idleCount = 0,
    hidden = false,
    destroyed = false,
    dragging = false,
    hovering = false,
    talking = false,
    lastAct = "",
    lastUserActivity = Date.now(),
    curLook = -1,
    lastLookChange = 0;

  function later(fn, ms) {
    var id = window.setTimeout(function () {
      timers = timers.filter(function (t) { return t !== id; });
      fn();
    }, ms);
    timers.push(id);
    return id;
  }
  function sleep(ms) {
    return new Promise(function (r) { later(r, ms); });
  }
  function clearTimers() {
    timers.forEach(clearTimeout);
    timers = [];
  }

  function state() {
    return root ? root.getAttribute("data-avatar-state") : "";
  }
  function setState(s) {
    if (!root) return;
    if (root.getAttribute("data-avatar-state") === s) return;
    root.setAttribute("data-avatar-state", s);
    try { window.dispatchEvent(new CustomEvent("kohan:avatar:state", { detail: { state: s } })); } catch (e) {}
  }

  /* ---- image preloading ------------------------------------------------ */
  var loaded = {};
  function ensureLoaded(url) {
    if (loaded[url]) return loaded[url];
    loaded[url] = new Promise(function (resolve) {
      var im = new Image();
      im.decoding = "async";
      var settled = false;
      var done = function (ok) { if (!settled) { settled = true; resolve(ok); } };
      im.onload = function () {
        // decode() warms the bitmap so the first frame paints without a
        // hitch, but Chrome can leave it pending forever in a hidden or
        // background tab. Never wait on it for more than a moment.
        if (im.decode) {
          im.decode().then(function () { done(true); }, function () { done(true); });
          window.setTimeout(function () { done(true); }, 250);
        } else done(true);
      };
      im.onerror = function () { done(false); };
      im.src = url;
    });
    return loaded[url];
  }

  /* ---- frame painter (two layers, crossfade) --------------------------- */
  function cellPosition(rw, cl) {
    var x = COLS === 1 ? 0 : (cl / (COLS - 1)) * 100;
    var y = ROWS === 1 ? 0 : (rw / (ROWS - 1)) * 100;
    return x + "% " + y + "%";
  }
  function atlasFrame(cell) {
    return { img: ATLAS_URL, size: COLS * 100 + "% " + ROWS * 100 + "%", pos: cellPosition(cell.row, cell.col) };
  }
  function stripFrame(stripKey, index) {
    var s = STRIPS[stripKey];
    var x = s.n === 1 ? 0 : (index / (s.n - 1)) * 100;
    return { img: s.url, size: s.n * 100 + "% 100%", pos: x + "% 0%" };
  }
  function applyFrame(el, f) {
    var img = 'url("' + f.img + '")';
    if (el.__img !== img) { el.style.backgroundImage = img; el.__img = img; }
    if (el.__size !== f.size) { el.style.backgroundSize = f.size; el.__size = f.size; }
    if (el.__pos !== f.pos) { el.style.backgroundPosition = f.pos; el.__pos = f.pos; }
  }
  var fadeTimer = 0;
  function paint(f, blendMs) {
    if (!layers.length) return;
    var cur = layers[topLayer];
    if (cur.__img === 'url("' + f.img + '")' && cur.__pos === f.pos && cur.__size === f.size) return;
    if (!blendMs || prefersReduced || hidden || !cur.animate) {
      applyFrame(cur, f);
      cur.style.opacity = "1";
      layers[1 - topLayer].style.opacity = "0";
      return;
    }
    var next = layers[1 - topLayer];
    applyFrame(next, f);
    next.style.zIndex = "2";
    cur.style.zIndex = "1";
    next.style.opacity = "1";
    // The new frame fades in ON TOP of the old one, which stays fully opaque
    // underneath — coverage never dips, so there is no see-through flicker.
    try { next.animate([{ opacity: 0 }, { opacity: 1 }], { duration: blendMs, easing: "ease-out" }); } catch (e) {}
    clearTimeout(fadeTimer);
    var old = cur;
    fadeTimer = window.setTimeout(function () { old.style.opacity = "0"; }, blendMs + 20);
    topLayer = 1 - topLayer;
  }
  function paintCell(cell, blendMs) { paint(atlasFrame(cell), blendMs); }
  function paintNeutral(blendMs) { curLook = -1; paintCell(NEUTRAL, blendMs); }

  function setMotion(name) {
    if (!body) return;
    if (name) body.setAttribute("data-motion", name);
    else body.removeAttribute("data-motion");
  }

  /* ---- DOM ------------------------------------------------------------- */
  function build() {
    if (root) return;
    root = document.createElement("div");
    root.className = "kohan-avatar-root";
    root.setAttribute("data-avatar-mascot", "true");
    root.setAttribute("data-avatar-id", "kohan");
    root.setAttribute("data-avatar-state", "loading");
    root.setAttribute("role", "button");
    root.setAttribute("tabindex", "0");
    root.setAttribute("aria-label", CFG.ariaLabel || "Kohan avatar");
    root.setAttribute("data-position", OPTS.position || "bottom-left");
    root.style.setProperty("--kohan-scale", parseFloat(OPTS.scale) || 1);

    shadow = document.createElement("div");
    shadow.className = "kohan-shadow";
    shadow.setAttribute("aria-hidden", "true");
    rig = document.createElement("div");
    rig.className = "kohan-rig";
    stage = document.createElement("div");
    stage.className = "kohan-stage";
    body = document.createElement("div");
    body.className = "kohan-body";
    for (var i = 0; i < 2; i++) {
      var l = document.createElement("div");
      l.className = "kohan-layer";
      l.setAttribute("aria-hidden", "true");
      l.style.opacity = i === 0 ? "1" : "0";
      body.appendChild(l);
      layers.push(l);
    }
    stage.appendChild(body);
    rig.appendChild(stage);
    root.appendChild(shadow);
    root.appendChild(rig);
    document.body.appendChild(root);
    paintNeutral(0);
    buildControls();
    wireInteractions();
  }

  /* ---- act player ------------------------------------------------------ */
  function cancelAnimation() {
    epoch += 1;
    setMotion(null);
  }

  function playAct(name) {
    var act = ACTS[name];
    if (!act || !root) return Promise.resolve(false);
    cancelAnimation();
    var ep = epoch;
    var stripKey = act.strip;
    var ready = stripKey ? ensureLoaded(STRIPS[stripKey].url) : ensureLoaded(ATLAS_URL);
    return ready.then(function (ok) {
      if (ep !== epoch) return false;
      if (!ok) return false; // missing artwork: skip the act, never show a hole
      lastAct = name;
      setMotion(act.motion || null);
      var loops = act.loops || 1;
      var loop = 0;
      function frameAt(i) {
        var fr = act.frames[i];
        return stripKey ? stripFrame(stripKey, fr) : atlasFrame(fr);
      }
      function msAt(i) {
        return Array.isArray(act.ms) ? (act.ms[i] != null ? act.ms[i] : act.ms[act.ms.length - 1]) : act.ms;
      }
      function cycle(i) {
        if (ep !== epoch) return Promise.resolve(false);
        if (i >= act.frames.length) {
          loop += 1;
          var more = act.whileDragging ? dragging : loop < loops;
          if (more) return cycle(0);
          return (act.hold ? sleep(act.hold) : Promise.resolve()).then(function () { return ep === epoch; });
        }
        // The fade between two frames scales with how long a frame stays:
        // a slow act (laptop, iPad) morphs from pose to pose, a quick one
        // (jump, run) stays crisp. The act's own blend is the floor.
        var ms = msAt(i);
        var blend = Math.min(170, Math.max(act.blend || 0, Math.round(ms * 0.55)));
        if (i === 0 && loop === 0) blend = Math.max(blend, 110);
        paint(frameAt(i), blend);
        return sleep(ms).then(function () { return cycle(i + 1); });
      }
      return cycle(0).then(function (ok2) {
        if (ep === epoch) setMotion(null);
        return ok2;
      });
    });
  }

  // Site-capped patrol: two short visible steps right, two back, restore origin.
  function playWalkPatrol() {
    if (!root) return Promise.resolve(false);
    cancelAnimation();
    var ep = epoch;
    var route = [
      { row: 1, col: 0, x: 4 }, { row: 1, col: 2, x: 9 }, { row: 1, col: 4, x: 14 }, { row: 1, col: 6, x: 18 },
      { row: 2, col: 0, x: 14 }, { row: 2, col: 2, x: 9 }, { row: 2, col: 4, x: 4 }, { row: 2, col: 6, x: 0 },
    ];
    root.setAttribute("data-kohan-patrol", "forward");
    var k = 0;
    function step() {
      if (ep !== epoch) { rig.style.removeProperty("transform"); root.removeAttribute("data-kohan-patrol"); return Promise.resolve(false); }
      if (k >= route.length) {
        rig.style.removeProperty("transform");
        root.removeAttribute("data-kohan-patrol");
        return Promise.resolve(true);
      }
      var s = route[k];
      paintCell(s, 50);
      rig.style.transform = "translateX(" + s.x + "px)";
      return sleep(130).then(function () { k += 1; return step(); });
    }
    return step();
  }

  function playRoulette() {
    var fire = Math.random() < (OPTS.fireProbability != null ? OPTS.fireProbability : 0.5);
    ACTS.__roulette = {
      strip: "roulette",
      frames: fire ? [0, 1, 2, 1, 2, 3, 5] : [0, 1, 2, 1, 2, 3, 4],
      ms: [420, 360, 260, 260, 260, 700, fire ? 560 : 900],
      blend: 70,
      hold: 300,
    };
    return playAct("__roulette").then(function (played) {
      if (played && fire) {
        ACTS.__fall = { strip: "fallScared", frames: [0, 1, 2, 3], ms: [230, 230, 300, 500], blend: 60, hold: 200, motion: "land" };
        return playAct("__fall").then(function () { return playAct("confused"); });
      }
      return played;
    });
  }

  function playGoodbye() {
    return playAct("goodbyeWave").then(function () {
      if (root) {
        root.setAttribute("data-kohan-hidden", "true");
        setState("hidden");
      }
      return true;
    });
  }

  function playNamed(name) {
    if (!name) return Promise.resolve(false);
    if (name.charAt(0) === "@") return playNamed(pick(GROUPS[name.slice(1)] || [], lastAct));
    if (name === "walk") return playWalkPatrol();
    if (name === "roulette") return playRoulette();
    if (name === "goodbye") return playGoodbye();
    if (name === "idle") return Promise.resolve(true);
    return playAct(name);
  }

  function toIdle(blendMs) {
    if (!root || destroyed) return;
    if (state() === "hidden") return;
    setState("idle");
    setMotion(null);
    paintNeutral(blendMs == null ? 140 : blendMs);
    if (talking) startTalkLoop();
  }

  // Run an act, then return to idle.
  function runAction(name, returnTo) {
    if (!root || destroyed) return Promise.resolve(false);
    if (state() === "hidden" && name !== "reset") return Promise.resolve(false);
    setState("busy");
    return Promise.resolve(playNamed(name)).then(function (r) {
      if (name !== "goodbye" && state() !== "hidden" && !dragging) {
        if (returnTo && returnTo !== "idle") setState(returnTo);
        toIdle();
      }
      return r;
    });
  }

  /* ---- idle life: blink ------------------------------------------------- */
  function scheduleBlink() {
    clearTimeout(blinkTimer);
    if (prefersReduced || destroyed) return;
    blinkTimer = window.setTimeout(function () {
      if (!hidden && state() === "idle" && !dragging && curLook < 0) {
        paintCell(BLINK, 40);
        window.setTimeout(function () {
          if (state() === "idle" && curLook < 0) paintCell(NEUTRAL, 60);
          // Occasionally a double blink, like people do.
          if (Math.random() < 0.18) {
            window.setTimeout(function () {
              if (state() === "idle" && curLook < 0) {
                paintCell(BLINK, 30);
                window.setTimeout(function () { if (state() === "idle" && curLook < 0) paintCell(NEUTRAL, 50); }, 110);
              }
            }, 160);
          }
        }, 120);
      }
      scheduleBlink();
    }, 2600 + Math.random() * 3600);
  }

  /* ---- pointer-look (16 directions) ------------------------------------ */
  var lastPointer = null,
    lookRAF = 0,
    lookResetTimer = 0;

  function lookIndexFor(x, y) {
    var rect = root.getBoundingClientRect();
    var cx = rect.left + rect.width / 2;
    var cy = rect.top + rect.height * 0.22; // the head, not the belly
    var dx = x - cx,
      dy = y - cy;
    if (Math.hypot(dx, dy) < rect.height * 0.7) return -1; // too close: face front
    // Screen atan2 is 0 at RIGHT; the atlas is 0 at UP, clockwise.
    var bearing = (Math.atan2(dy, dx) * 180) / Math.PI + 90;
    bearing = (bearing + 360) % 360;
    if (curLook >= 0) {
      var centre = curLook * 22.5;
      var diff = Math.abs(((bearing - centre + 540) % 360) - 180);
      if (diff < 17) return curLook; // hysteresis: hold the current direction
    }
    return Math.round(bearing / 22.5) % 16;
  }

  var lookRetry = 0;
  function applyLook(idx, blend, force) {
    if (idx === curLook) return;
    var now = performance.now();
    var wait = 110 - (now - lastLookChange);
    if (wait > 0 && !force) {
      // Never flicker between cells — but do not drop the turn either: look
      // again once the dwell is over, or a quick sweep ends facing nowhere.
      clearTimeout(lookRetry);
      lookRetry = window.setTimeout(function () {
        if (!lookRAF) lookRAF = requestAnimationFrame(lookFrame);
      }, wait + 5);
      return;
    }
    lastLookChange = now;
    curLook = idx;
    if (idx < 0) paintCell(NEUTRAL, blend || 160);
    else paintCell(LOOK[idx], blend || 90);
  }

  function lookFrame() {
    lookRAF = 0;
    if (hovering && state() === "idle" && curLook >= 0 && !talking) applyLook(-1, 120, true);
    if (!root || destroyed || dragging || hovering || talking || hidden) return;
    if (state() !== "idle" || !lastPointer || OPTS.pointerLook === false) return;
    applyLook(lookIndexFor(lastPointer.x, lastPointer.y));
    clearTimeout(lookResetTimer);
    // Pointer at rest for a while: look back at the visitor.
    lookResetTimer = window.setTimeout(function () {
      if (state() === "idle" && !dragging && !talking) applyLook(-1, 220);
    }, 2600);
  }

  function onDocPointerMove(e) {
    lastUserActivity = Date.now();
    if (e.pointerType === "touch") return;
    lastPointer = { x: e.clientX, y: e.clientY };
    if (!lookRAF && !prefersReduced) lookRAF = requestAnimationFrame(lookFrame);
  }

  /* ---- talking gesture ------------------------------------------------- */
  var talkEpoch = 0;
  // Talking: he faces the visitor and the CSS "talk" motion gives the head
  // its bob. No frame swapping — the idle cells are near-identical, and
  // swapping them only produced a faint flicker.
  function startTalkLoop() {
    ++talkEpoch;
    curLook = -1;
    paintCell(NEUTRAL, 140);
    setMotion("talk");
  }
  function setTalking(on) {
    // A no-op when nothing changes: closing a bubble that had finished
    // typing must not snap the head back to the front mid pointer-look.
    if (talking === !!on) return;
    talking = !!on;
    if (!root) return;
    root.toggleAttribute("data-talking", talking);
    if (talking) {
      curLook = -1;
      if (state() === "idle") startTalkLoop();
    } else {
      talkEpoch++;
      if (state() === "idle") { setMotion(null); paintNeutral(140); }
    }
  }

  /* ---- drag physics ---------------------------------------------------- */
  var pointer = null,
    phys = { angle: 0, vel: 0, target: 0 },
    physRAF = 0,
    samples = [],
    pendingPos = null,
    posRAF = 0;

  // While dragged he trails the pointer by a frame or two (exponential
  // catch-up), which reads as weight rather than a sticker glued to the
  // cursor. Reduced motion: no trailing, he sits exactly under the pointer.
  var followPos = null;
  function followTick() {
    posRAF = 0;
    if (!pendingPos || !root) return;
    if (!followPos || prefersReduced) {
      var r0 = root.getBoundingClientRect();
      followPos = prefersReduced ? { x: pendingPos.x, y: pendingPos.y } : { x: r0.left, y: r0.top };
    }
    var k = prefersReduced ? 1 : 0.38;
    followPos.x += (pendingPos.x - followPos.x) * k;
    followPos.y += (pendingPos.y - followPos.y) * k;
    var done = Math.abs(pendingPos.x - followPos.x) < 0.5 && Math.abs(pendingPos.y - followPos.y) < 0.5;
    if (done) followPos = { x: pendingPos.x, y: pendingPos.y };
    var pos = placeRoot(followPos.x, followPos.y);
    announceMove();
    if (done) {
      persistPosition(pos);
      if (!dragging) { followPos = null; pendingPos = null; }
      var cb = settleCb;
      settleCb = null;
      if (cb) cb();
      return;
    }
    posRAF = requestAnimationFrame(followTick);
  }
  // After release: let the catch-up finish (it is a few frames), then land.
  var settleCb = null;
  function settleFollow() {
    return new Promise(function (resolve) {
      if (!pendingPos) return resolve();
      settleCb = resolve;
      if (!posRAF) posRAF = requestAnimationFrame(followTick);
      window.setTimeout(function () { if (settleCb === resolve) { settleCb = null; resolve(); } }, 400);
    });
  }
  function stopFollow() {
    if (posRAF) cancelAnimationFrame(posRAF);
    posRAF = 0;
    followPos = null;
    pendingPos = null;
    settleCb = null;
  }

  function placeRoot(x, y) {
    var b = root.getBoundingClientRect();
    var maxX = window.innerWidth - b.width;
    var maxY = window.innerHeight - b.height;
    var nx = Math.max(0, Math.min(maxX, x));
    var ny = Math.max(0, Math.min(maxY, y));
    root.style.setProperty("inset", "auto", "important");
    root.style.setProperty("left", nx + "px", "important");
    root.style.setProperty("top", ny + "px", "important");
    root.removeAttribute("data-position");
    positioned = true;
    // Near the right edge there is no room for the control column on his
    // right — flip it to his left so it can never be pushed off screen.
    root.setAttribute("data-kohan-side", nx + b.width + 44 > window.innerWidth ? "right" : "left");
    return { x: nx, y: ny };
  }
  function persistPosition(pos) {
    try { window.localStorage.setItem("kohanAvatarPos", JSON.stringify(pos)); } catch (er) {}
  }
  function announceMove() {
    root.dispatchEvent(new CustomEvent("kohan:moved", { bubbles: false }));
  }

  // A damped spring pulls the body toward a tilt set by the drag velocity —
  // a held-by-the-collar pendulum. Runs only while it has something to do.
  function physTick() {
    physRAF = 0;
    if (!stage) return;
    var k = 0.12, damp = 0.78;
    phys.vel = (phys.vel + (phys.target - phys.angle) * k) * damp;
    phys.angle += phys.vel;
    var stretch = dragging ? 1.035 : 1;
    stage.style.transform = "rotate(" + phys.angle.toFixed(2) + "deg) scale(" + (2 - stretch).toFixed(3) + "," + stretch.toFixed(3) + ")";
    if (!dragging) phys.target = 0;
    if (dragging || Math.abs(phys.angle) > 0.05 || Math.abs(phys.vel) > 0.05) {
      physRAF = requestAnimationFrame(physTick);
    } else {
      phys.angle = 0; phys.vel = 0;
      stage.style.removeProperty("transform");
    }
  }
  function kickPhys() {
    if (!physRAF && !prefersReduced) physRAF = requestAnimationFrame(physTick);
  }

  // Throw: glide with friction, bounce softly off the viewport edges.
  // No gravity: the page is not a floor, and dropping him to the bottom edge
  // after every flick would undo where the visitor chose to put him.
  function throwFrom(vx, vy) {
    var b = root.getBoundingClientRect();
    var x = b.left, y = b.top;
    var t0 = performance.now(), last = t0;
    return new Promise(function (resolve) {
      function step(now) {
        var dt = Math.min(32, now - last) / 1000;
        last = now;
        x += vx * dt;
        y += vy * dt;
        var maxX = window.innerWidth - b.width, maxY = window.innerHeight - b.height;
        if (x < 0) { x = 0; vx = Math.abs(vx) * 0.45; }
        if (x > maxX) { x = maxX; vx = -Math.abs(vx) * 0.45; }
        if (y < 0) { y = 0; vy = Math.abs(vy) * 0.45; }
        if (y > maxY) { y = maxY; vy = -Math.abs(vy) * 0.35; }
        var friction = Math.pow(0.04, dt); // ~96% of the speed gone per second
        vx *= friction;
        vy *= friction;
        phys.target = Math.max(-20, Math.min(20, -vx / 60));
        kickPhys();
        var pos = placeRoot(x, y);
        announceMove();
        if (Math.hypot(vx, vy) < 80 || now - t0 > 900) {
          persistPosition(pos);
          resolve();
          return;
        }
        requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    });
  }

  /* ---- interactions ---------------------------------------------------- */
  function wireInteractions() {
    document.addEventListener("pointermove", onDocPointerMove, { passive: true });
    ["scroll", "keydown", "pointerdown", "wheel"].forEach(function (ev) {
      window.addEventListener(ev, function () { lastUserActivity = Date.now(); }, { passive: true, capture: true });
    });

    // Hover: calm. He faces the visitor and lifts a touch — no frame flipping.
    root.addEventListener("pointerenter", function (e) {
      if (e.pointerType === "touch") return;
      hovering = true;
      root.setAttribute("data-hover", "");
      clearTimeout(lookRetry);
      if (state() === "idle" && !talking) applyLook(-1, 120, true);
    });
    root.addEventListener("pointerleave", function () {
      hovering = false;
      root.removeAttribute("data-hover");
    });

    // Real click -> talk (the talk module listens) + a friendly gesture.
    root.addEventListener("click", function (e) {
      if (dragJustEnded) return;
      if (e.target instanceof Element && e.target.closest(".kohan-size-controls")) return;
      fireClick(e.clientX, e.clientY);
    });
    root.addEventListener("keydown", function (e) {
      if (e.target !== root) return;
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        var r = root.getBoundingClientRect();
        fireClick(r.left + r.width / 2, r.top + r.height / 2);
      }
    });

    // Drag via Pointer Events + capture.
    document.addEventListener("pointerdown", function (e) {
      if (e.button !== 0 || !(e.target instanceof Element)) return;
      if (!e.target.closest('[data-avatar-mascot="true"]')) return;
      // Never capture a press on an interactive child: capture retargets the
      // click composed from it onto the ROOT, so the eye and +/- buttons
      // would stop working for real mice.
      if (e.target.closest("button, a, input, textarea, .kohan-size-controls, .kohan-chat-launcher, .kohan-chat-panel, .kohan-bubble")) return;
      if (OPTS.drag === false) return;
      var b = root.getBoundingClientRect();
      pointer = { id: e.pointerId, x: e.clientX, y: e.clientY, ox: e.clientX - b.left, oy: e.clientY - b.top };
      samples = [{ x: e.clientX, y: e.clientY, t: performance.now() }];
      try { root.setPointerCapture(e.pointerId); } catch (er) {}
    }, true);

    document.addEventListener("pointermove", function (e) {
      if (!pointer || pointer.id !== e.pointerId) return;
      if (!dragging) {
        if (Math.hypot(e.clientX - pointer.x, e.clientY - pointer.y) < 6) return;
        dragging = true;
        hovering = false;
        root.setAttribute("data-dragging", "");
        cancelAnimation();
        setState("drag");
        playAct(pick(GROUPS.drag, lastAct));
      }
      var now = performance.now();
      samples.push({ x: e.clientX, y: e.clientY, t: now });
      while (samples.length > 2 && now - samples[0].t > 90) samples.shift();
      var s0 = samples[0];
      var dt = Math.max(1, now - s0.t);
      var vx = ((e.clientX - s0.x) / dt) * 1000;
      // Held from above: moving right swings the feet left.
      phys.target = Math.max(-24, Math.min(24, -vx / 45));
      kickPhys();
      pendingPos = { x: e.clientX - pointer.ox, y: e.clientY - pointer.oy };
      if (!posRAF) posRAF = requestAnimationFrame(followTick);
    }, true);

    var dragJustEnded = false;
    function finish(e) {
      if (!pointer || pointer.id !== e.pointerId) return;
      var wasDrag = dragging;
      pointer = null;
      try { root.releasePointerCapture(e.pointerId); } catch (er) {}
      if (!wasDrag) return;
      dragJustEnded = true;
      window.setTimeout(function () { dragJustEnded = false; }, 60);
      var now = performance.now();
      var s0 = samples[0] || { x: e.clientX, y: e.clientY, t: now };
      var dt = Math.max(16, now - s0.t);
      var vx = ((e.clientX - s0.x) / dt) * 1000;
      var vy = ((e.clientY - s0.y) / dt) * 1000;
      dragging = false;
      root.removeAttribute("data-dragging");
      cancelAnimation();
      var thrown = Math.hypot(vx, vy) > 650 && !prefersReduced;
      if (thrown) stopFollow(); // the throw carries on from where he is
      (thrown ? throwFrom(vx * 0.9, vy * 0.9) : settleFollow()).then(function () {
        phys.target = 0;
        kickPhys();
        setState("busy");
        return playAct(thrown ? "landSquash" : pick(GROUPS.drop, lastAct));
      }).then(function () { toIdle(); });
    }
    document.addEventListener("pointerup", finish, true);
    document.addEventListener("pointercancel", finish, true);

    document.addEventListener("visibilitychange", function () {
      hidden = document.hidden;
    });
  }

  var lastClickAt = 0;
  function fireClick(x, y) {
    var now = Date.now();
    if (now - lastClickAt < 350) return;
    lastClickAt = now;
    lastUserActivity = now;
    var handled = false;
    try {
      var ev = new CustomEvent("kohan:avatar:click", { detail: { x: x, y: y }, cancelable: true });
      handled = !window.dispatchEvent(ev); // a listener called preventDefault()
    } catch (e) {}
    if (state() !== "idle") return;
    // The talk module gestures for itself; without it, play a random act.
    if (!handled) runAction(pick(GROUPS.click, lastAct));
  }

  /* ---- idle + waiting loops ------------------------------------------- */
  function scheduleIdle() {
    if (prefersReduced || destroyed) return;
    var range = OPTS.idleRangeMs || [30000, 75000];
    var wait = range[0] + Math.floor(Math.random() * (range[1] - range[0] + 1));
    idleTimer = window.setTimeout(function () {
      idleTick().then(scheduleIdle);
    }, wait);
  }
  function idleTick() {
    if (!root || destroyed || hidden || dragging || talking) return Promise.resolve();
    if (state() !== "idle") return Promise.resolve();
    idleCount += 1;
    var every = OPTS.rouletteEvery || 3;
    var doRoulette = OPTS.weaponMoods !== false && idleCount % every === 0;
    return runAction(doRoulette ? "roulette" : pick(GROUPS.idle, lastAct));
  }

  // Waiting: the visitor has gone quiet (no pointer, scroll or keys). He
  // passes the time with one of the "wait" acts, then checks again.
  function scheduleWait() {
    if (prefersReduced || destroyed) return;
    waitTimer = window.setTimeout(function () {
      var quiet = Date.now() - lastUserActivity;
      if (quiet > 18000 && !hidden && !dragging && !talking && state() === "idle") {
        runAction(pick(GROUPS.wait, lastAct)).then(scheduleWait);
        return;
      }
      scheduleWait();
    }, 6000);
  }

  /* ---- public API ------------------------------------------------------ */
  var API = {
    setMood: function (name, opts) {
      return runAction(MOOD_MAP[name] || name, (opts && opts.returnTo) || "idle");
    },
    play: function (name, opts) {
      return runAction(MOOD_MAP[name] || name, (opts && opts.returnTo) || "idle");
    },
    group: function (g) {
      if (!GROUPS[g]) return Promise.resolve(false);
      return runAction("@" + g);
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
        if (rig) rig.style.removeProperty("transform");
        setState("idle");
        paintNeutral(120);
      }
    },
    lookAt: function (x, y) {
      if (!root || state() !== "idle" || dragging) return;
      curLook = -2; // force a change
      lastLookChange = 0;
      applyLook(lookIndexFor(x, y), 120);
      clearTimeout(lookResetTimer);
      lookResetTimer = window.setTimeout(function () { if (state() === "idle" && !talking) applyLook(-1, 220); }, 1800);
    },
    talk: function (on) { setTalking(on); },
    root: function () { return root; },
    acts: function () { return Object.keys(ACTS).filter(function (k) { return k.charAt(0) !== "_"; }); },
    groups: function () { return JSON.parse(JSON.stringify(GROUPS)); },
    destroy: function () {
      destroyed = true;
      cancelAnimation();
      clearTimers();
      clearTimeout(idleTimer);
      clearTimeout(waitTimer);
      clearTimeout(blinkTimer);
      if (lookRAF) cancelAnimationFrame(lookRAF);
      if (physRAF) cancelAnimationFrame(physRAF);
      if (root && root.parentNode) root.parentNode.removeChild(root);
      root = null;
    },
    refreshAssets: function () {
      return fetch(BASE + "/version.json?ts=" + Date.now(), { cache: "no-store" })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (v) {
          var bust = v && v.hash ? v.hash.slice(0, 8) : String(Date.now());
          ATLAS_URL = BASE + "/spritesheet.webp?v=" + bust;
          loaded = {};
          if (root) paintNeutral(0);
          return v;
        })
        .catch(function () { return null; });
    },
  };

  /* ---- eye / size controls, beside the avatar -------------------------- */
  var SCALES = [0.7, 0.85, 1, 1.2, 1.4];
  var SCALE_KEY = "kohanAvatarScale";
  var CTRL_TEXT = {
    en: { group: "Avatar controls", bigger: "Bigger", smaller: "Smaller", hide: "Hide avatar", show: "Show avatar" },
    fa: { group: "کنترل‌های آواتار", bigger: "بزرگ‌تر", smaller: "کوچک‌تر", hide: "پنهان کردن آواتار", show: "نمایش آواتار" },
    ar: { group: "عناصر التحكم", bigger: "أكبر", smaller: "أصغر", hide: "إخفاء الشخصية", show: "إظهار الشخصية" },
  };
  function ctrlText() {
    var l = (document.documentElement.getAttribute("lang") || "en").slice(0, 2).toLowerCase();
    return CTRL_TEXT[l] || CTRL_TEXT.en;
  }
  // Snap to the NEAREST step: the admin setting is a free float.
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
    eye.innerHTML =
      '<svg class="kohan-eye-open" viewBox="0 0 24 24" width="17" height="17" aria-hidden="true">' +
        '<path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ' +
          'd="M1.8 12S5.6 5.5 12 5.5 22.2 12 22.2 12 18.4 18.5 12 18.5 1.8 12 1.8 12z"/>' +
        '<circle cx="12" cy="12" r="3.1" fill="none" stroke="currentColor" stroke-width="1.8"/>' +
      "</svg>" +
      '<svg class="kohan-eye-closed" viewBox="0 0 24 24" width="17" height="17" aria-hidden="true">' +
        '<path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ' +
          'd="M3 5l18 14M1.8 12S5.6 5.5 12 5.5c1.9 0 3.6.6 5 1.4M22.2 12s-1.4 2.4-3.9 4.1c-1.7 1.2-3.8 2.4-6.3 2.4-1.2 0-2.3-.2-3.3-.6"/>' +
      "</svg>";
    eye.setAttribute("aria-pressed", "false");
    var minus = ctrlButton("kohan-size-button", t.smaller, "−");
    var plus = ctrlButton("kohan-size-button", t.bigger, "+");
    var index = readScaleIndex();
    function applyIndex(i) {
      index = Math.max(0, Math.min(SCALES.length - 1, i));
      var v = SCALES[index];
      root.style.setProperty("--kohan-scale", String(v), "important");
      writeScale(v);
      minus.disabled = index === 0;
      plus.disabled = index === SCALES.length - 1;
      announceMove();
    }
    minus.addEventListener("click", function (e) { e.preventDefault(); e.stopPropagation(); applyIndex(index - 1); });
    plus.addEventListener("click", function (e) { e.preventDefault(); e.stopPropagation(); applyIndex(index + 1); });
    eye.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      // Its own attribute, not data-kohan-hidden (goodbye's opacity:0 would
      // fade this column too and strand the visitor).
      var nowHidden = root.getAttribute("data-kohan-eye-hidden") !== "true";
      if (nowHidden) root.setAttribute("data-kohan-eye-hidden", "true");
      else root.removeAttribute("data-kohan-eye-hidden");
      try { window.sessionStorage.setItem("kohanAvatarEyeHidden", nowHidden ? "1" : "0"); } catch (er) {}
      eye.setAttribute("aria-pressed", nowHidden ? "true" : "false");
      var label = nowHidden ? t.show : t.hide;
      eye.setAttribute("aria-label", label);
      eye.title = label;
      try { window.dispatchEvent(new CustomEvent("kohan:avatar:eye", { detail: { hidden: nowHidden } })); } catch (er) {}
    });
    wrap.appendChild(eye);
    wrap.appendChild(minus);
    wrap.appendChild(plus);
    root.appendChild(wrap);
    applyIndex(index);
    // The eye choice holds for the visit, so a hidden avatar stays hidden
    // when the visitor moves to the next page.
    try {
      if (window.sessionStorage.getItem("kohanAvatarEyeHidden") === "1") eye.click();
    } catch (er) {}
  }

  window.KohanAvatar = API;

  /* ---- lifecycle event bridge ----------------------------------------- */
  window.addEventListener("kohan:avatar:mood", function (e) {
    var d = (e && e.detail) || {};
    if (!d.mood || typeof d.mood !== "string") return;
    // Only allow-listed moods/acts (defence against injected metadata).
    if (!(d.mood in MOOD_MAP) && !ACTS[d.mood]) return;
    if (state() === "drag" || state() === "hidden") return;
    API.play(d.mood, { returnTo: d.returnTo || "idle" });
  });

  /* ---- free walking (keyboard + double-click) -------------------------- */
  var walkEpoch = 0,
    keysDown = {},
    keyRAF = 0,
    positioned = false,
    walkFrame = 0,
    walkFrameAt = 0;

  function avatarSize() {
    var r = root.getBoundingClientRect();
    return { w: r.width || 132, h: r.height || 143 };
  }
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
    announceMove();
  }
  function curPos() {
    var r = root.getBoundingClientRect();
    return { x: r.left, y: r.top };
  }
  // A running frame for the direction (1 right, -1 left), all 8 strides.
  function stepRunFrame(dir, now) {
    if (now - walkFrameAt < 85) return;
    walkFrameAt = now;
    walkFrame = (walkFrame + 1) % 8;
    paintCell({ row: dir >= 0 ? 1 : 2, col: walkFrame }, 40);
    root.setAttribute("data-kohan-walking", dir >= 0 ? "right" : "left");
  }
  function endWalk() {
    root.removeAttribute("data-kohan-walking");
    setState("idle");
    paintNeutral(140);
    persistPosition(curPos());
  }
  function walkTo(tx, ty) {
    if (!root || destroyed || state() === "hidden") return;
    enableFreePosition();
    cancelAnimation();
    setState("busy");
    var ep = ++walkEpoch;
    var start = curPos();
    var s = avatarSize();
    var goalX = clampX(tx - s.w / 2);
    var goalY = clampY(ty - s.h / 2);
    var dir = goalX >= start.x ? 1 : -1;
    var t0 = performance.now();
    var dist = Math.hypot(goalX - start.x, goalY - start.y);
    var dur = Math.max(260, (dist / 460) * 1000);
    function tick(now) {
      if (ep !== walkEpoch || destroyed) return;
      var p = Math.min(1, (now - t0) / dur);
      var e = p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2;
      setPos(start.x + (goalX - start.x) * e, start.y + (goalY - start.y) * e);
      stepRunFrame(dir, now);
      if (p < 1) requestAnimationFrame(tick);
      else endWalk();
    }
    requestAnimationFrame(tick);
  }
  function keyLoop(now) {
    var dx = 0, dy = 0;
    if (keysDown.left) dx -= 1;
    if (keysDown.right) dx += 1;
    if (keysDown.up) dy -= 1;
    if (keysDown.down) dy += 1;
    if (dx === 0 && dy === 0) {
      keyRAF = 0;
      endWalk();
      return;
    }
    enableFreePosition();
    walkEpoch++;
    setState("busy");
    var pos = curPos();
    setPos(pos.x + dx * 6, pos.y + dy * 6);
    stepRunFrame(dx || 1, now || performance.now());
    keyRAF = requestAnimationFrame(keyLoop);
  }
  function mapKey(k) {
    return k === "ArrowLeft" ? "left" : k === "ArrowRight" ? "right" : k === "ArrowUp" ? "up" : k === "ArrowDown" ? "down" : null;
  }
  function onKeyDown(e) {
    if (!root || destroyed) return;
    var ae = document.activeElement;
    if (ae && (ae.tagName === "INPUT" || ae.tagName === "TEXTAREA" || ae.isContentEditable)) return;
    if (ae !== root) return; // only when the avatar is focused
    var mapped = mapKey(e.key);
    if (!mapped) return;
    e.preventDefault();
    if (!keyRAF) cancelAnimation();
    keysDown[mapped] = true;
    if (!keyRAF) keyRAF = requestAnimationFrame(keyLoop);
  }
  function onKeyUp(e) {
    var mapped = mapKey(e.key);
    if (mapped) keysDown[mapped] = false;
  }
  function wireWalking() {
    document.addEventListener("dblclick", function (e) {
      if (!(e.target instanceof Element)) return;
      if (e.target.closest(".kohan-avatar-root, .kohan-bubble, .kohan-chat-panel")) return;
      if (e.target.closest("a,button,input,textarea,select,label,[contenteditable],p,li,h1,h2,h3,h4,td,pre,code,blockquote")) return; // double-click on text selects a word
      walkTo(e.clientX, e.clientY);
    });
    document.addEventListener("keydown", onKeyDown);
    document.addEventListener("keyup", onKeyUp);
    window.addEventListener("resize", function () {
      if (positioned) { var p = curPos(); setPos(p.x, p.y); }
    }, { passive: true });
  }

  /* ---- boot ------------------------------------------------------------ */
  function removeLegacy() {
    document.querySelectorAll(".kdcv-pet-root").forEach(function (n) {
      if (n.parentNode) n.parentNode.removeChild(n);
    });
  }

  function restorePosition() {
    var raw = null;
    try { raw = window.localStorage.getItem("kohanAvatarPos"); } catch (er) {}
    if (!raw) return;
    try {
      var pos = JSON.parse(raw);
      if (typeof pos.x === "number" && typeof pos.y === "number") placeRoot(pos.x, pos.y);
    } catch (er) {}
  }

  function boot() {
    removeLegacy();
    build();
    restorePosition();
    wireWalking();
    ensureLoaded(ATLAS_URL).then(function () {
      if (destroyed) return;
      root.setAttribute("data-ready", "");
      toIdle(0);
      // Warm the strips the next interactions are most likely to need.
      var warm = function () {
        ["dragAnnoyed", "fallScared", "wink", "confused"].forEach(function (k) { if (STRIPS[k]) ensureLoaded(STRIPS[k].url); });
        Object.keys(STRIPS).forEach(function (k) { if (k.indexOf("x:") === 0) ensureLoaded(STRIPS[k].url); });
      };
      if (window.requestIdleCallback) window.requestIdleCallback(warm, { timeout: 4000 });
      else window.setTimeout(warm, 2500);
    });
    scheduleBlink();
    scheduleIdle();
    scheduleWait();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})();
