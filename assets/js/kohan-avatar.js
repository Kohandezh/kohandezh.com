/*!
 * Kohan Avatar — interactive sprite character for the kohandezh.com pet widget.
 *
 * Replaces the static .kdcv-pet-image with an animated sprite canvas, mapped to
 * the website's chat lifecycle events. Self-contained: no jQuery, no WordPress,
 * no external dependencies. Lazy-loaded AFTER the host pet widget is ready.
 *
 * Public API:
 *   window.KohanAvatar.setMood(name, opts)
 *   window.KohanAvatar.play(name, opts)
 *   window.KohanAvatar.stop()
 *   window.KohanAvatar.reset()
 *   window.KohanAvatar.destroy()
 *   window.KohanAvatar.refreshAssets()
 *   window.KohanAvatar.debug(bool)
 *
 * Listens for:
 *   window event "kohan:avatar:mood" { detail: { mood, duration, loop, returnTo } }
 *   document event "kdcvpet:ready"   (host pet ready)
 *   document event "kdcvpet:localechange"
 *
 * Source assets live at /assets/kohan/ and are served by the dev server or
 * by the WordPress plugin in production.
 */
(function () {
  "use strict";
  if (window.__KOHAN_AVATAR_BOOTSTRAPPED__) return;
  window.__KOHAN_AVATAR_BOOTSTRAPPED__ = true;

  // ----------------------------------------------------------------- config
  var ASSET_BASE = (function () {
    // Resolve relative to this script so it works under /assets/ and /wp-content/themes/...
    var scripts = document.getElementsByTagName("script");
    var me = "";
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].src || "";
      if (src.indexOf("kohan-avatar") !== -1) { me = src; break; }
    }
    // assets/js/kohan-avatar.js -> ../../kohan/
    var m = me.match(/^(.*\/)assets\/js\//);
    if (m) return m[1] + "assets/kohan/";
    // fallback: same directory as the script (assets/js/ -> ../kohan/)
    var slash = me.lastIndexOf("/");
    return (slash !== -1 ? me.slice(0, slash + 1) : "") + "../kohan/";
  })();

  var CELL_W = 192, CELL_H = 208;
  var ROWS = 11, COLS = 8;

  // Atlas row index for each native mood.
  var MOOD_ROW = {
    "idle":            0,
    "running-right":   1,
    "running-left":    2,
    "waving":          3,
    "jumping":         4,
    "failed":          5,             // also: angry / annoyed
    "waiting":         6,             // also: confused
    "running":         7,             // also: macbook-work / focused
    "review":          8              // also: ipad-review / reviewing
  };

  // Native aliases (canonical name -> native row mood)
  var NATIVE_ALIASES = {
    "angry":         "failed",
    "annoyed":       "failed",
    "confused":      "waiting",
    "vision":        "waiting",
    "vision-headset":"waiting",
    "macbook-work":  "running",
    "focused":       "running",
    "ipad-review":   "review",
    "reviewing":     "review",
    "looking":       "__pointer__",   // special
    "calm":          "idle",
    "friendly":      "waving",
    "excited":       "jumping",
    "moving-right":  "running-right",
    "moving-left":   "running-left"
  };

  // Native frame counts per row (column usage).
  var NATIVE_FRAMES = {
    0: 7, 1: 8, 2: 8, 3: 4, 4: 5, 5: 8, 6: 6, 7: 6, 8: 6, 9: 8, 10: 8
  };

  // Per-row playback FPS (cycles per second). Tuned for natural motion.
  var NATIVE_FPS = {
    0: 6,                            // idle: slow breathe
    1: 12, 2: 12,                    // running: brisk
    3: 8,                            // waving: medium
    4: 10,                           // jumping
    5: 8,                            // failed/angry
    6: 8,                            // waiting/confused
    7: 12,                           // macbook work
    8: 10,                           // ipad review
    9: 0, 10: 0                      // pointer-look: static (no cycle)
  };

  // 16 pointer-look directions (from spritesheet.json). Index = direction id.
  var LOOK_DIRECTIONS = [
    { deg:   0,   row: 9, col: 0 },
    { deg:  22.5, row: 9, col: 1 },
    { deg:  45,   row: 9, col: 2 },
    { deg:  67.5, row: 9, col: 3 },
    { deg:  90,   row: 9, col: 4 },
    { deg: 112.5, row: 9, col: 5 },
    { deg: 135,   row: 9, col: 6 },
    { deg: 157.5, row: 9, col: 7 },
    { deg: 180,   row: 10, col: 0 },
    { deg: 202.5, row: 10, col: 1 },
    { deg: 225,   row: 10, col: 2 },
    { deg: 247.5, row: 10, col: 3 },
    { deg: 270,   row: 10, col: 4 },
    { deg: 292.5, row: 10, col: 5 },
    { deg: 315,   row: 10, col: 6 },
    { deg: 337.5, row: 10, col: 7 }
  ];

  // Supplemental moods (loaded on demand). Frame counts from supplemental-sprites.json.
  var SUPPLEMENTAL_INFO = {
    "wink":              { frames: 1, fps: 0,  src: "wink-{i}.webp" },
    "angry-still":       { frames: 1, fps: 0,  src: "angry-still-{i}.webp" },
    "confused-vision":   { frames: 1, fps: 0,  src: "confused-vision-{i}.webp" },
    "guarded":           { frames: 4, fps: 4,  src: "guarded-{i}.webp" },
    "russian-roulette":  { frames: 6, fps: 4,  src: "russian-roulette-{i}.webp" },
    "drag-annoyed":      { frames: 6, fps: 10, src: "drag-annoyed-{i}.webp" },
    "fall-scared":       { frames: 6, fps: 10, src: "fall-scared-{i}.webp" },
    "goodbye-smoke":     { frames: 7, fps: 8,  src: "goodbye-smoke-{i}.webp" }
  };

  // Compatibility aliases per the avatar spec.
  var SUPPLEMENTAL_ALIASES = {
    "chamber-camera": "russian-roulette"
  };

  // Moods that contain weapon imagery — never trigger randomly, require explicit event.
  var WEAPON_MOODS = { "russian-roulette": true, "guarded": true };

  // Identity-safe activities. Every entry uses either the approved main atlas
  // or one of the approved supplemental sheets; rejected face variants never
  // enter this runtime.
  var CLICK_ACTIONS = [
    "confused-vision", "ipad-review", "jumping", "macbook-work", "vision",
    "walk", "waving", "wink", "stretch", "curious-scan", "deep-thought",
    "work-cycle", "celebrate"
  ];
  var IDLE_ACTIONS = [
    "ipad-review", "macbook-work", "vision", "walk", "waving", "wink",
    "watch-check", "hand-on-chest", "whistle", "hands-in-pockets",
    "looking-sky", "nap", "stretch", "curious-scan", "deep-thought",
    "work-cycle", "power-nap"
  ];
  var IDLE_MIN_MS = 30000;
  var IDLE_MAX_MS = 75000;
  var SPECIAL_ACTIVITIES = {
    "walk": true,
    "watch-check": true,
    "hand-on-chest": true,
    "whistle": true,
    "hands-in-pockets": true,
    "looking-sky": true,
    "nap": true,
    "stretch": true,
    "curious-scan": true,
    "deep-thought": true,
    "work-cycle": true,
    "celebrate": true,
    "power-nap": true,
    "goodbye": true
  };

  function pick(items) {
    return items[Math.floor(Math.random() * items.length)];
  }

  function wait(ms) {
    return new Promise(function (resolve) {
      window.setTimeout(resolve, ms);
    });
  }

  // Build a single allowlist of every accepted mood name.
  var ALLOWED_MOODS = {};
  Object.keys(MOOD_ROW).forEach(function (k) { ALLOWED_MOODS[k] = "native"; });
  Object.keys(NATIVE_ALIASES).forEach(function (k) { ALLOWED_MOODS[k] = "native-alias"; });
  Object.keys(SUPPLEMENTAL_INFO).forEach(function (k) { ALLOWED_MOODS[k] = "supplemental"; });
  Object.keys(SUPPLEMENTAL_ALIASES).forEach(function (k) { ALLOWED_MOODS[k] = "supplemental-alias"; });
  Object.keys(SPECIAL_ACTIVITIES).forEach(function (k) { ALLOWED_MOODS[k] = "activity"; });

  // ------------------------------------------------------------- controller
  function KohanAvatar() {
    var self = this;
    self.ready = false;
    self.destroyed = false;
    self.debugMode = false;
    self.reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    self.root = null;            // .kdcv-pet-root
    self.trigger = null;         // .kdcv-pet-trigger
    self.shell = null;           // .kdcv-pet-image-shell
    self.canvas = null;
    self.ctx = null;
    self.debugBadge = null;
    self.sizeControls = null;
    self.sizeButtons = null;
    self.avatarSizes = [88, 110, 132, 154, 176];
    self.avatarSize = 132;

    self.atlas = null;           // Image object with the spritesheet
    self.atlasReady = false;

    self.supplemental = {};      // mood name -> { frames: [Image, ...], loaded: true }
    self.supplementalLoading = {}; // in-flight promises

    // Active playback state
    self.state = {
      mood: "idle",
      kind: "native",            // native | supplemental | pointer | hidden
      row: 0,
      col: 0,
      frames: 7,
      fps: 6,
      loop: true,
      startTime: 0,
      duration: Infinity,        // mood duration before returnTo
      returnTo: "idle",
      pointerDir: 0,
      frameOrder: null,
      frameDelays: null,
      hidden: false
    };

    self.tickHandle = 0;
    self.returnTimer = 0;
    self.pointerLookEnabled = true;
    self.pointerLookActive = false;
    self.pointerLookLastEvent = 0;
    self.pointerLookIdleTimer = 0;
    self.autonomousTimer = 0;
    self.responseWorkTimer = 0;
    self.responseCompleteTimer = 0;
    self.idleCount = 0;
    self.activityEpoch = 0;
    self.isDragging = false;
    self.goodbyeRestoreTimer = 0;
    self._pointerGesture = null;
    self.byePending = false;
    self.transitionCanvas = null;
    self.transitionStartedAt = 0;
    self.transitionDuration = 0;
    self._listeners = [];
    self._visibilityPaused = false;
    self._lastFrameNow = 0;
    self._motionTime = 0;

    self.optionFlags = {
      enablePointerLook: true,
      enableSupplemental: true,
      enableWeaponMoods: false,   // OFF by default per spec
      enableDragMoods: true
    };
  }

  KohanAvatar.prototype.log = function () {
    if (!this.debugMode) return;
    var args = Array.prototype.slice.call(arguments);
    args.unshift("[Kohan]");
    try { console.log.apply(console, args); } catch (e) {}
  };

  KohanAvatar.prototype.resolveMood = function (mood) {
    if (!mood || typeof mood !== "string") return null;
    mood = mood.toLowerCase().trim();
    if (MOOD_ROW.hasOwnProperty(mood))   return { name: mood, kind: "native", key: mood };
    if (NATIVE_ALIASES.hasOwnProperty(mood)) {
      var nk = NATIVE_ALIASES[mood];
      if (nk === "__pointer__") return { name: mood, kind: "pointer", key: "pointer" };
      return { name: mood, kind: "native", key: nk };
    }
    if (SUPPLEMENTAL_INFO.hasOwnProperty(mood))
      return { name: mood, kind: "supplemental", key: mood };
    if (SUPPLEMENTAL_ALIASES.hasOwnProperty(mood))
      return { name: mood, kind: "supplemental", key: SUPPLEMENTAL_ALIASES[mood] };
    if (SPECIAL_ACTIVITIES.hasOwnProperty(mood))
      return { name: mood, kind: "activity", key: mood };
    return null;
  };

  KohanAvatar.prototype.isAllowed = function (mood) {
    return !!this.resolveMood(mood);
  };

  // ------------------------------------------------------------- bootstrap
  KohanAvatar.prototype.boot = function () {
    var self = this;
    if (self.destroyed) return;

    // The host-ready event and the fallback poll below both race to attach.
    // Without this guard, both can succeed (event fires while a poll tick is
    // also in flight), calling attach() twice on the same root and doubling
    // every DOM node it creates — canvas, size buttons, the lot. `attached`
    // short-circuits locally; the DOM marker also catches a second
    // KohanAvatar instance ever attaching to the same host element.
    var attached = false;
    var pollId = 0;

    var tryAttach = function () {
      if (attached) return true;
      var root = document.getElementById("kdcv-pet-root") ||
                 document.querySelector(".kdcv-pet-root");
      if (root && root.hasAttribute("data-kohan-attached")) { attached = true; return true; }
      var trigger = document.getElementById("kdcv-pet-trigger") ||
                    (root && root.querySelector(".kdcv-pet-trigger"));
      var shell = trigger && trigger.querySelector(".kdcv-pet-image-shell");
      if (!root || !trigger || !shell) return false;
      root.setAttribute("data-kohan-attached", "true");
      attached = true;
      self.attach(root, trigger, shell);
      return true;
    };

    if (tryAttach()) { self.start(); return; }

    // Listen for the host event.
    document.addEventListener("kdcvpet:ready", function () {
      // Defer one frame so the host finishes mounting.
      window.requestAnimationFrame(function () {
        if (!tryAttach()) {
          // Host might mark ready before the DOM is fully painted; retry a few times.
          var n = 0;
          var id = window.setInterval(function () {
            if (tryAttach() || ++n >= 20) window.clearInterval(id);
          }, 150);
        } else {
          self.start();
          if (pollId) window.clearInterval(pollId);
        }
      });
    });

    // Fallback: poll for 6s in case the host event was missed.
    var attempts = 0;
    pollId = window.setInterval(function () {
      if (self.ready || attached || attempts++ > 40) { window.clearInterval(pollId); return; }
      if (tryAttach()) { window.clearInterval(pollId); self.start(); }
    }, 150);
  };

  KohanAvatar.prototype.attach = function (root, trigger, shell) {
    var self = this;
    self.root = root;
    self.trigger = trigger;
    self.shell = shell;

    // Build canvas
    var canvas = document.createElement("canvas");
    canvas.className = "kohan-canvas";
    canvas.setAttribute("role", "img");
    canvas.setAttribute("aria-label", "Kohan avatar");
    canvas.width = CELL_W;
    canvas.height = CELL_H;

    var kohanRoot = document.createElement("div");
    kohanRoot.className = "kohan-root";
    kohanRoot.appendChild(canvas);

    // Insert into the existing image-shell (which already has the halo ::before).
    shell.appendChild(kohanRoot);

    // Debug badge
    var badge = document.createElement("span");
    badge.className = "kohan-debug-badge";
    badge.setAttribute("data-visible", "false");
    trigger.appendChild(badge);

    // Small, separate size controls. They live beside the avatar rather than
    // inside its trigger, so +/- never opens, drags, or randomizes the pet.
    var sizeControls = document.createElement("div");
    sizeControls.className = "kohan-size-controls";

    /* Drop any control group built before we attached.
     *
     * assets/js/kdcv-interaction-fix.js (generated by the site's background
     * automation, loaded after this file) builds a fallback set of size
     * controls when it can't find ours. Its guard checks for an existing
     * `.kohan-size-controls`, but `window.KohanAvatar` exists from script load
     * while these controls are only created here in attach() — so it can run
     * in that window, see the API but no DOM, and build its own. The result
     * was six size buttons instead of three, with the stale fallback set
     * wired to a not-yet-ready instance so clicking + changed nothing.
     * Removing any earlier group keeps exactly one owner of these controls.
     */
    root.querySelectorAll(".kohan-size-controls").forEach(function (stale) {
      // Rescue the eye first — the fallback appends it into its own group.
      var strandedEye = stale.querySelector(".kohan-eye-button");
      if (strandedEye) root.appendChild(strandedEye);
      stale.remove();
    });
    sizeControls.setAttribute("role", "group");
    sizeControls.setAttribute("aria-label", "Avatar size");
    var smaller = document.createElement("button");
    smaller.type = "button";
    smaller.className = "kohan-size-button";
    smaller.textContent = "\u2212";
    smaller.setAttribute("aria-label", "Make avatar smaller");
    smaller.title = "Smaller avatar";
    var larger = document.createElement("button");
    larger.type = "button";
    larger.className = "kohan-size-button";
    larger.textContent = "+";
    larger.setAttribute("aria-label", "Make avatar larger");
    larger.title = "Larger avatar";

    // Switch button sits between smaller/larger: toggles the sprite avatar
    // off in favor of the original static pet image, and back again.
    var swap = document.createElement("button");
    swap.type = "button";
    swap.className = "kohan-size-button kohan-switch-button";
    swap.innerHTML =
      '<svg viewBox="0 0 20 20" width="13" height="13" aria-hidden="true" focusable="false">' +
      '<path fill="currentColor" d="M6.5 3.5 3 7l3.5 3.5v-2.4h6.6V6.4H6.5V3.5Zm7 5.5v2.4H6.9v1.7h6.6v2.4L17 12l-3.5-3.5Z"/></svg>';
    swap.setAttribute("aria-label", "Switch to classic avatar");
    swap.setAttribute("aria-pressed", "false");
    swap.title = "Switch avatar style";

    // Eye toggle: keeps the full control set together so the controls share one
    // aligned row/column and remain predictable on touch screens.
    var eye = document.createElement("button");
    eye.type = "button";
    eye.className = "kohan-eye-button";
    eye.setAttribute("aria-pressed", "false");
    eye.setAttribute("aria-label", "Hide avatar");
    eye.title = "Hide avatar";
    eye.innerHTML =
      '<svg class="kohan-eye-open" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M1.6 12S5.5 5.2 12 5.2 22.4 12 22.4 12 18.5 18.8 12 18.8 1.6 12 1.6 12Z"/><circle cx="12" cy="12" r="2.9"/></svg>' +
      '<svg class="kohan-eye-closed" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M2 2l20 20"/><path d="M10.6 6.4A8.5 8.5 0 0 1 12 6.3c6.5 0 10.4 6.8 10.4 6.8a17 17 0 0 1-3.3 4"/>' +
      '<path d="M6.5 7.9A16.8 16.8 0 0 0 1.6 13S5.5 19.8 12 19.8a9.6 9.6 0 0 0 4.1-.9"/><path d="M9.6 9.8a3 3 0 0 0 4.2 4.2"/></svg>';

    /* The eye is the FIRST child of the control column, so it sits directly
     * above "−" and inherits the column's own gap, width and alignment. It was
     * previously an absolutely-positioned sibling whose offset had to be
     * guessed (or measured), which left it misaligned and floating. */
    sizeControls.appendChild(eye);
    sizeControls.appendChild(smaller);
    sizeControls.appendChild(swap);
    sizeControls.appendChild(larger);
    root.appendChild(sizeControls);

    eye.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();
      self.toggleHidden();
    });
    ["pointerdown", "pointerup"].forEach(function (eventName) {
      eye.addEventListener(eventName, function (event) { event.stopPropagation(); });
    });
    self.eyeButton = eye;

    /* kdcv-interaction-fix.js also appends the eye into .kohan-size-controls.
     * That is now where it belongs, so the two no longer fight and no
     * re-parenting loop is needed. The hidden state keeps the column and the
     * eye visible and hides only the size buttons — see kohan-avatar.css. */

    ["pointerdown", "pointerup", "click"].forEach(function (eventName) {
      sizeControls.addEventListener(eventName, function (event) {
        event.stopPropagation();
      });
    });
    smaller.addEventListener("click", function (event) {
      event.preventDefault();
      self.adjustSize(-1);
    });
    larger.addEventListener("click", function (event) {
      event.preventDefault();
      self.adjustSize(1);
    });
    swap.addEventListener("click", function (event) {
      event.preventDefault();
      self.toggleClassic();
    });

    self.canvas = canvas;
    self.ctx = canvas.getContext("2d");
    self.debugBadge = badge;
    self.sizeControls = sizeControls;
    self.sizeButtons = { smaller: smaller, larger: larger };
    self.switchButton = swap;

    root.setAttribute("data-kohan-active", "true");
    self.restoreSize();
    self.restoreClassicMode();
    self.restoreHiddenMode();

    // High-DPI sizing.
    self.applyDpi();
  };

  KohanAvatar.prototype.restoreSize = function () {
    var saved = null;
    try { saved = Number(window.localStorage.getItem("kohan-avatar-size-v1")); } catch (ignore) {}
    if (this.avatarSizes.indexOf(saved) === -1) saved = 132;
    this.applySize(saved, false);
  };

  /**
   * Switch between the Kohan sprite avatar and the original static pet
   * image. Non-destructive: the sprite instance stays alive so switching
   * back is instant. The size buttons only make sense in sprite mode, so
   * they disable (not hide) while classic mode is active.
   */
  KohanAvatar.prototype.toggleClassic = function (force) {
    var self = this;
    if (!self.root) return;
    var wasActive = self.root.getAttribute("data-kohan-active") === "true";
    var toClassic = typeof force === "boolean" ? force : wasActive;
    if (toClassic) {
      self.stop();
      self.root.setAttribute("data-kohan-active", "false");
    } else {
      self.root.setAttribute("data-kohan-active", "true");
      self.enterNative("idle", { loop: true });
      self.resumeTick();
    }
    // Both avatars are now driven by the same --kohan-avatar-size variable
    // (see the :not([data-kohan-active]) rules in kohan-avatar.css), so the
    // classic image resizes with the +/- controls exactly like the sprite.
    // Previously this branch overwrote the variable with the classic image's
    // measured width and disabled both buttons, which left the static avatar
    // stuck at one size with no way to change it.
    self.applySize(self.avatarSize, false);
    if (self.switchButton) {
      self.switchButton.setAttribute("aria-pressed", toClassic ? "true" : "false");
      self.switchButton.title = toClassic ? "Switch to Kohan avatar" : "Switch to classic avatar";
      self.switchButton.setAttribute("aria-label", toClassic ? "Switch to Kohan avatar" : "Switch to classic avatar");
    }
    try { window.localStorage.setItem("kohan-avatar-classic-v1", toClassic ? "1" : "0"); } catch (ignore) {}
    window.dispatchEvent(new CustomEvent("kohan:avatar:classic", { detail: { classic: toClassic } }));
  };

  /* Hide the avatar entirely.
   *
   * "Hidden" has to mean silent, not just invisible: the chat panel and the
   * idle nudge bubbles are created by ai-pet.min.js on its own timers and
   * appended to <body>, outside this root. Hiding the avatar alone would still
   * leave speech bubbles popping up with nothing attached to them. So while
   * hidden we also tear down anything it opens and keep watching for more.
   */
  KohanAvatar.prototype.suppressChatterWhileHidden = function (on) {
    // Everything the avatar can put on screen from OUTSIDE its own root:
    // the chat panel and idle nudges from ai-pet.min.js, plus the wisdom-quote
    // bubble from wisdom-quotes.js. That last one has no awareness of the
    // hidden state at all, so a hidden avatar was still speaking Persian
    // aphorisms on its own timer.
    var STRAY = ".kdcv-pet-panel, .kdcv-pet-nudge, .kdcv-wisdom-bubble";

    function sweep() {
      document.querySelectorAll(STRAY).forEach(function (n) { n.remove(); });
    }

    if (!on) {
      if (this._chatterMO) { this._chatterMO.disconnect(); this._chatterMO = null; }
      return;
    }
    sweep();
    if (this._chatterMO || !("MutationObserver" in window)) return;
    this._chatterMO = new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        for (var j = 0; j < muts[i].addedNodes.length; j++) {
          var n = muts[i].addedNodes[j];
          if (n.nodeType !== 1) continue;
          if ((n.matches && n.matches(STRAY)) || (n.querySelector && n.querySelector(STRAY))) {
            sweep();
            return;
          }
        }
      }
    });
    this._chatterMO.observe(document.body, { childList: true, subtree: true });
  };

  KohanAvatar.prototype.toggleHidden = function (force) {
    var self = this;
    if (!self.root) return;
    var isHidden = self.root.getAttribute("data-kohan-hidden") === "true";
    var next = typeof force === "boolean" ? force : !isHidden;

    /* The eye must be a direct child of the root, never inside
     * .kohan-size-controls — that group is hidden along with the avatar, so an
     * eye nested in it would hide itself and strand the user with no way back.
     * kdcv-interaction-fix.js re-parents it into the group, so re-assert here
     * (cheap, and runs at exactly the moment it matters). */
    if (self.eyeButton && self.eyeButton.parentElement !== self.root) {
      self.root.appendChild(self.eyeButton);
    }

    self.root.setAttribute("data-kohan-hidden", next ? "true" : "false");

    if (next) {
      self.stop();
    } else if (self.root.getAttribute("data-kohan-active") === "true") {
      self.enterNative("idle", { loop: true });
      self.resumeTick();
    }

    self.suppressChatterWhileHidden(next);

    if (self.eyeButton) {
      self.eyeButton.setAttribute("aria-pressed", next ? "true" : "false");
      var label = next ? "Show avatar" : "Hide avatar";
      self.eyeButton.setAttribute("aria-label", label);
      self.eyeButton.title = label;
    }

    try { window.localStorage.setItem("kohan-avatar-hidden-v1", next ? "1" : "0"); } catch (ignore) {}
    window.dispatchEvent(new CustomEvent("kohan:avatar:hidden", { detail: { hidden: next } }));
  };

  KohanAvatar.prototype.restoreHiddenMode = function () {
    var saved = null;
    try { saved = window.localStorage.getItem("kohan-avatar-hidden-v1"); } catch (ignore) {}
    if (saved === "1") this.toggleHidden(true);
  };

  KohanAvatar.prototype.restoreClassicMode = function () {
    var saved = null;
    try { saved = window.localStorage.getItem("kohan-avatar-classic-v1"); } catch (ignore) {}
    if (saved === "1") this.toggleClassic(true);
  };

  KohanAvatar.prototype.applySize = function (size, persist) {
    var index = this.avatarSizes.indexOf(Number(size));
    if (index === -1) return false;
    this.avatarSize = this.avatarSizes[index];
    if (this.root) {
      /* The vw ceiling stops the avatar overflowing a narrow screen, but 20vw
       * is only 78px at 390px wide — below the smallest step (88px), so EVERY
       * size clamped to the same value and the +/- buttons looked broken on
       * mobile while working fine on desktop. 42vw (164px at 390px) keeps the
       * overflow guard while leaving four of the five steps visibly distinct. */
      var cap = "min(" + this.avatarSize + "px, 42vw)";
      this.root.style.setProperty("--kohan-avatar-size", cap);
      this.root.style.setProperty("--kohan-avatar-radius", "calc(" + cap + " * 0.24)");
    }
    if (this.sizeButtons) {
      this.sizeButtons.smaller.disabled = index === 0;
      this.sizeButtons.larger.disabled = index === this.avatarSizes.length - 1;
    }
    if (persist !== false) {
      try { window.localStorage.setItem("kohan-avatar-size-v1", String(this.avatarSize)); } catch (ignore) {}
    }
    var self = this;
    window.requestAnimationFrame(function () { self.applyDpi(); });
    window.dispatchEvent(new CustomEvent("kohan:avatar:size", {
      detail: { size: this.avatarSize, index: index }
    }));
    return true;
  };

  KohanAvatar.prototype.adjustSize = function (direction) {
    var index = this.avatarSizes.indexOf(this.avatarSize);
    var next = Math.max(0, Math.min(this.avatarSizes.length - 1, index + (direction < 0 ? -1 : 1)));
    return this.applySize(this.avatarSizes[next], true);
  };

  KohanAvatar.prototype.applyDpi = function () {
    var self = this;
    if (!self.canvas) return;
    var cssSize = self.shell.getBoundingClientRect().width ||
                  self.trigger.getBoundingClientRect().width || 88;
    var dpr = Math.min(window.devicePixelRatio || 1, 3);
    // internal resolution: 2x cell size for crispness, capped.
    var internal = Math.max(CELL_W, Math.round(cssSize * dpr));
    self.canvas.width = internal;
    self.canvas.height = Math.round(internal * (CELL_H / CELL_W));
    self.ctx.imageSmoothingEnabled = true;
    self.ctx.imageSmoothingQuality = "high";
    // A DPR change invalidates the captured transition frame. Keeping an old
    // bitmap here would stretch it for one blend and look like a tiny snap.
    self.transitionCanvas = null;
    self.transitionDuration = 0;
  };

  KohanAvatar.prototype.listen = function (target, type, handler, options) {
    if (!target || !target.addEventListener) return handler;
    target.addEventListener(type, handler, options);
    this._listeners.push({ target: target, type: type, handler: handler, options: options });
    return handler;
  };

  KohanAvatar.prototype.captureTransition = function (duration) {
    if (this.reduceMotion || !this.canvas || !this.ctx || !this.canvas.width || !this.canvas.height) {
      this.transitionCanvas = null;
      this.transitionDuration = 0;
      return;
    }
    if (!this.transitionCanvas) this.transitionCanvas = document.createElement("canvas");
    var snapshot = this.transitionCanvas;
    if (snapshot.width !== this.canvas.width || snapshot.height !== this.canvas.height) {
      snapshot.width = this.canvas.width;
      snapshot.height = this.canvas.height;
    }
    var snapshotContext = snapshot.getContext("2d", { alpha: true });
    snapshotContext.clearRect(0, 0, snapshot.width, snapshot.height);
    snapshotContext.drawImage(this.canvas, 0, 0);
    this.transitionStartedAt = performance.now();
    this.transitionDuration = Math.max(80, Math.min(Number(duration) || 180, 260));
  };

  KohanAvatar.prototype.pauseTick = function () {
    if (!this.tickHandle) return;
    window.cancelAnimationFrame(this.tickHandle);
    this.tickHandle = 0;
  };

  KohanAvatar.prototype.canAnimate = function () {
    return !this.destroyed && this.ready && !document.hidden && !this._visibilityPaused &&
      !this.staticMode && this.root && this.root.getAttribute("data-kohan-hidden") !== "true" &&
      this.root.getAttribute("data-kohan-active") === "true";
  };

  KohanAvatar.prototype.resumeTick = function () {
    this._lastFrameNow = 0;
    if (this.canAnimate()) this.startTick();
  };

  KohanAvatar.prototype.start = function () {
    var self = this;
    if (self.ready || self.destroyed) return;
    self.ready = true;
    window.__kohanDebug = window.__kohanDebug || [];
    window.__kohanDebug.push("start called");

    self.loadAtlas(function (ok) {
      window.__kohanDebug.push("atlas cb: ok=" + ok + " atlasReady=" + self.atlasReady);
      if (!ok) { self.log("atlas failed"); return; }
      self.log("atlas ready");
      self.enterNative("idle", { loop: true });
      self.startTick();
      window.__kohanDebug.push("tick started: handle=" + self.tickHandle + " ctx=" + !!self.ctx + " atlas=" + !!self.atlas);
      self.bindInteractions();
      // Supplemental frames stay on-demand. Eagerly decoding every optional
      // reaction at boot previously cost ~30 image decodes before a visitor
      // interacted with Kohan. The current frame remains visible while the
      // requested bundle loads, then crossfades into the ready pose.
      self.scheduleAutonomousIdle();
      self.dispatchReady();
    });
  };

  KohanAvatar.prototype.dispatchReady = function () {
    document.dispatchEvent(new CustomEvent("kohan:avatar:ready", {
      detail: { mood: this.state.mood }
    }));
  };

  // ------------------------------------------------------------- assets
  KohanAvatar.prototype.loadAtlas = function (cb) {
    var self = this;
    if (self.atlasReady) { cb(true); return; }
    var img = new Image();
    img.decoding = "async";
    img.onload = function () {
      self.atlas = img;
      self.atlasReady = true;
      cb(true);
    };
    img.onerror = function () { cb(false); };
    img.src = ASSET_BASE + "spritesheet.webp";
  };

  KohanAvatar.prototype.loadSupplemental = function (mood) {
    var self = this;
    if (self.supplemental[mood]) return Promise.resolve(self.supplemental[mood]);
    if (self.supplementalLoading[mood]) return self.supplementalLoading[mood];

    var info = SUPPLEMENTAL_INFO[mood];
    if (!info) return Promise.reject(new Error("unknown supplemental mood: " + mood));

    var p = new Promise(function (resolve, reject) {
      var frames = [];
      var remaining = info.frames;
      for (var i = 0; i < info.frames; i++) {
        (function (idx) {
          var img = new Image();
          img.decoding = "async";
          img.onload = function () {
            frames[idx] = img;
            if (--remaining === 0) {
              self.supplemental[mood] = { frames: frames, info: info };
              delete self.supplementalLoading[mood];
              resolve(self.supplemental[mood]);
            }
          };
          img.onerror = function () {
            delete self.supplementalLoading[mood];
            reject(new Error("failed to load supplemental frame " + idx + " of " + mood));
          };
          img.src = ASSET_BASE + "supplemental/" + info.src.replace("{i}", idx);
        })(i);
      }
    });

    self.supplementalLoading[mood] = p;
    return p;
  };

  KohanAvatar.prototype.refreshAssets = function () {
    var self = this;
    // Bypass cache by appending a new query string.
    self.atlasReady = false;
    self.atlas = null;
    self.supplemental = {};
    self.supplementalLoading = {};
    var bust = "?v=" + Date.now();
    var img = new Image();
    img.onload = function () {
      self.atlas = img; self.atlasReady = true;
      self.log("atlas refreshed");
      self.reset();
    };
    img.src = ASSET_BASE + "spritesheet.webp" + bust;
  };

  // ------------------------------------------------------------- playback
  KohanAvatar.prototype.enterNative = function (mood, opts) {
    var self = this;
    opts = opts || {};
    var row = MOOD_ROW[mood];
    if (typeof row !== "number") return false;

    var frames = NATIVE_FRAMES[row] || COLS;
    var fps = self.reduceMotion ? 0 : (opts.fps != null ? opts.fps : NATIVE_FPS[row]);
    var loop = opts.loop != null ? opts.loop : true;
    var duration = opts.duration != null ? opts.duration : Infinity;
    var returnTo = opts.returnTo || "idle";

    self.captureTransition(opts.transitionMs || 180);

    self.state.mood = mood;
    self.state.kind = "native";
    self.state.row = row;
    self.state.col = 0;
    self.state.frames = frames;
    self.state.fps = fps;
    self.state.loop = loop;
    self.state.startTime = performance.now();
    self.state.duration = duration;
    self.state.returnTo = returnTo;
    self.state.frameOrder = null;
    self.state.frameDelays = null;
    self.state.hidden = false;

    self.clearReturnTimer();
    if (duration !== Infinity) {
      self.returnTimer = window.setTimeout(function () {
        self.play(self.state.returnTo, { loop: true });
      }, duration);
    }
    self.updateBadge(mood);
    self.log("enterNative", mood, "row", row, "frames", frames, "fps", fps);
    self.resumeTick();
    return true;
  };

  KohanAvatar.prototype.enterPointerLook = function (dirIndex) {
    var self = this;
    if (dirIndex < 0 || dirIndex >= 16) return false;
    var dir = LOOK_DIRECTIONS[dirIndex];
    // Pointer directions update frequently, so use a shorter blend that still
    // softens the 22.5-degree atlas step without adding noticeable latency.
    self.captureTransition(95);
    self.state.mood = "pointer-look";
    self.state.kind = "pointer";
    self.state.row = dir.row;
    self.state.col = dir.col;
    self.state.frames = 1;
    self.state.fps = 0;
    self.state.loop = false;
    self.state.duration = Infinity;
    self.state.frameOrder = null;
    self.state.frameDelays = null;
    self.state.hidden = false;
    self.updateBadge("look:" + Math.round(dir.deg) + "°");
    self.resumeTick();
    return true;
  };

  KohanAvatar.prototype.enterSupplemental = function (mood, opts) {
    var self = this;
    opts = opts || {};
    if (!self.optionFlags.enableSupplemental) {
      self.log("supplemental disabled — falling back");
      var fb = ({ "wink": "waving",
                  "angry-still": "failed",
                  "confused-vision": "waiting",
                  "guarded": "failed",
                  "russian-roulette": "failed",
                  "drag-annoyed": "failed",
                  "fall-scared": "jumping",
                  "goodbye-smoke": "waving" })[mood] || "idle";
      return self.enterNative(fb, opts);
    }
    if (WEAPON_MOODS[mood] && !self.optionFlags.enableWeaponMoods) {
      self.log("weapon mood", mood, "disabled — falling back to failed");
      return self.enterNative("failed", opts);
    }
    if (self.reduceMotion) {
      // Just show frame 0 statically.
    }
    self.loadSupplemental(mood).then(function (bundle) {
      var info = bundle.info;
      var fps = self.reduceMotion ? 0 : info.fps;
      var loop = opts.loop != null ? opts.loop : false;
      var duration = opts.duration != null ? opts.duration :
                     (info.frames > 1 ? Math.ceil(info.frames / Math.max(1, fps) * 1000) + 400 : 1200);
      var returnTo = opts.returnTo || "idle";

      self.captureTransition(opts.transitionMs || 200);

      self.state.mood = mood;
      self.state.kind = "supplemental";
      self.state.row = -1;
      self.state.col = 0;
      self.state.frames = info.frames;
      self.state.fps = fps;
      self.state.loop = loop;
      self.state.startTime = performance.now();
      self.state.duration = duration;
      self.state.returnTo = returnTo;
      self.state.frameOrder = Array.isArray(opts.order) ? opts.order.slice() : null;
      self.state.frameDelays = Array.isArray(opts.delays) ? opts.delays.slice() : null;
      self.state.hidden = false;

      self.clearReturnTimer();
      if (duration !== Infinity) {
        self.returnTimer = window.setTimeout(function () {
          if (mood === "goodbye-smoke") {
            self.hide();
          } else {
            self.play(self.state.returnTo, { loop: true });
          }
        }, duration);
      }
      self.updateBadge(mood);
      self.log("enterSupplemental", mood, "frames", info.frames, "fps", fps);
      self.resumeTick();
    }).catch(function (err) {
      self.log("supplemental load failed", err);
      // Fall back to native equivalent.
      var fb = ({ "wink": "waving",
                  "angry-still": "failed",
                  "confused-vision": "waiting",
                  "guarded": "failed",
                  "russian-roulette": "failed",
                  "drag-annoyed": "failed",
                  "fall-scared": "jumping",
                  "goodbye-smoke": "waving" })[mood] || "idle";
      self.enterNative(fb, opts);
    });
    return true;
  };

  // Public: play(mood, opts)
  KohanAvatar.prototype.play = function (mood, opts) {
    var normalized = String(mood || "").toLowerCase().trim();
    if (normalized === "russian-roulette" || normalized === "guarded" ||
        SPECIAL_ACTIVITIES[normalized]) {
      return this.playActivity(normalized, opts || {});
    }
    return this.setMood(mood, opts);
  };

  KohanAvatar.prototype.setMood = function (mood, opts) {
    var self = this;
    if (self.destroyed || !self.ready) return false;
    self.activityEpoch += 1;
    if (self.root) {
      self.root.removeAttribute("data-kohan-activity");
      self.root.removeAttribute("data-kohan-patrol");
    }
    var resolved = self.resolveMood(mood);
    if (!resolved) {
      self.log("rejected mood:", mood);
      return false;
    }
    if (self.state.hidden && resolved.key !== "idle" && resolved.kind !== "supplemental") {
      // Avatar was hidden via goodbye-smoke; require an explicit reset/show.
      self.log("avatar hidden — ignoring", mood);
      return false;
    }
    if (resolved.kind === "native") return self.enterNative(resolved.key, opts || {});
    if (resolved.kind === "supplemental") return self.enterSupplemental(resolved.key, opts || {});
    if (resolved.kind === "pointer") return self.enterPointerLook(self.state.pointerDir || 0);
    if (resolved.kind === "activity") {
      self.playActivity(resolved.key, opts || {});
      return true;
    }
    return false;
  };

  KohanAvatar.prototype.beginActivity = function (name) {
    this.activityEpoch += 1;
    if (this.root) this.root.setAttribute("data-kohan-activity", name);
    this.clearReturnTimer();
    return this.activityEpoch;
  };

  KohanAvatar.prototype.activityIsCurrent = function (epoch) {
    return !this.destroyed && epoch === this.activityEpoch;
  };

  KohanAvatar.prototype.runNativeFor = async function (mood, duration, epoch, opts) {
    opts = opts || {};
    if (!this.activityIsCurrent(epoch)) return false;
    this.enterNative(mood, {
      loop: opts.loop != null ? opts.loop : true,
      duration: Infinity,
      fps: opts.fps
    });
    await wait(duration);
    return this.activityIsCurrent(epoch);
  };

  KohanAvatar.prototype.runSupplementalFor = async function (mood, duration, epoch, opts) {
    opts = opts || {};
    if (!this.activityIsCurrent(epoch)) return false;
    try {
      await this.loadSupplemental(mood);
    } catch (err) {
      this.log("supplemental preload failed", mood, err);
      return false;
    }
    if (!this.activityIsCurrent(epoch)) return false;
    this.enterSupplemental(mood, {
      loop: opts.loop != null ? opts.loop : false,
      duration: Infinity,
      order: opts.order,
      delays: opts.delays
    });
    await wait(duration);
    return this.activityIsCurrent(epoch);
  };

  KohanAvatar.prototype.finishActivity = function (epoch) {
    if (!this.activityIsCurrent(epoch)) return false;
    if (this.root) {
      this.root.removeAttribute("data-kohan-activity");
      this.root.removeAttribute("data-kohan-patrol");
    }
    this.enterNative("idle", { loop: true });
    return true;
  };

  KohanAvatar.prototype.playWalkPatrol = async function (epoch) {
    if (!this.root || !this.activityIsCurrent(epoch)) return false;
    this.root.setAttribute("data-kohan-patrol", "forward");
    if (!await this.runNativeFor("running-right", 1300, epoch)) return false;
    this.root.setAttribute("data-kohan-patrol", "back");
    if (!await this.runNativeFor("running-left", 1300, epoch)) return false;
    this.root.removeAttribute("data-kohan-patrol");
    return true;
  };

  KohanAvatar.prototype.playRussianRoulette = async function (epoch) {
    var fire = Math.random() < 0.5;
    var order = fire ? [0, 1, 2, 1, 2, 3, 5] : [0, 1, 2, 1, 2, 3, 4];
    var delays = [420, 360, 260, 260, 260, 700, fire ? 560 : 900];
    var duration = delays.reduce(function (sum, value) { return sum + value; }, 0) + 300;
    this.optionFlags.enableWeaponMoods = true;
    var played = await this.runSupplementalFor("russian-roulette", duration, epoch, {
      order: order,
      delays: delays
    });
    this.optionFlags.enableWeaponMoods = false;
    if (!played) return false;

    if (fire) {
      if (!await this.runSupplementalFor("fall-scared", 1460, epoch, {
        order: [0, 1, 2, 3],
        delays: [230, 230, 300, 500]
      })) return false;
      if (!await this.runSupplementalFor("confused-vision", 1450, epoch)) return false;
    } else {
      // Dry chamber: an identity-safe wink stands in for the requested
      // relieved laugh without introducing a different generated face.
      if (!await this.runSupplementalFor("wink", 950, epoch)) return false;
    }
    return true;
  };

  KohanAvatar.prototype.playGoodbye = async function (epoch, opts) {
    opts = opts || {};
    if (!await this.runNativeFor("waving", 900, epoch)) return false;
    if (!await this.runSupplementalFor("goodbye-smoke", 1900, epoch, {
      order: [0, 1, 2, 3, 4, 5, 6],
      delays: [260, 250, 300, 260, 250, 250, 330]
    })) return false;
    if (!this.activityIsCurrent(epoch)) return false;
    this.hide();
    var self = this;
    window.clearTimeout(self.goodbyeRestoreTimer);
    if (opts.restoreAfter !== false) {
      self.goodbyeRestoreTimer = window.setTimeout(function () {
        if (!self.destroyed) self.reset();
      }, opts.restoreAfter || 6000);
    }
    return true;
  };

  KohanAvatar.prototype.playActivity = function (name, opts) {
    var self = this;
    opts = opts || {};
    name = String(name || "").toLowerCase();
    var epoch = self.beginActivity(name);

    return (async function () {
      var ok = true;
      if (name === "russian-roulette") {
        ok = await self.playRussianRoulette(epoch);
      } else if (name === "walk") {
        ok = await self.playWalkPatrol(epoch);
      } else if (name === "goodbye") {
        return self.playGoodbye(epoch, opts);
      } else if (name === "angry-still" || name === "wink" ||
                 name === "confused-vision" || name === "guarded") {
        if (name === "guarded") self.optionFlags.enableWeaponMoods = true;
        ok = await self.runSupplementalFor(name, opts.duration || 1450, epoch);
        if (name === "guarded") self.optionFlags.enableWeaponMoods = false;
      } else if (name === "ipad-review") {
        ok = await self.runNativeFor("review", opts.duration || 2500, epoch);
      } else if (name === "macbook-work") {
        ok = await self.runNativeFor("running", opts.duration || 2600, epoch);
      } else if (name === "vision") {
        ok = await self.runNativeFor("waiting", opts.duration || 2400, epoch);
      } else if (name === "jumping") {
        ok = await self.runNativeFor("jumping", opts.duration || 1500, epoch);
      } else if (name === "waving") {
        ok = await self.runNativeFor("waving", opts.duration || 1650, epoch);
      } else if (name === "watch-check" || name === "hands-in-pockets") {
        ok = await self.runNativeFor("waiting", opts.duration || 1800, epoch);
      } else if (name === "hand-on-chest") {
        ok = await self.runNativeFor("idle", opts.duration || 1800, epoch, { fps: 3 });
      } else if (name === "whistle") {
        ok = await self.runNativeFor("waving", opts.duration || 1500, epoch, { fps: 5 });
      } else if (name === "looking-sky") {
        if (self.activityIsCurrent(epoch)) self.enterPointerLook(12);
        await wait(opts.duration || 1500);
        ok = self.activityIsCurrent(epoch);
      } else if (name === "nap") {
        ok = await self.runNativeFor("idle", opts.duration || 2200, epoch, { fps: 1 });
      } else if (name === "stretch") {
        ok = await self.runNativeFor("idle", 380, epoch, { fps: 3 });
        if (ok) ok = await self.runNativeFor("waving", 720, epoch, { fps: 5 });
        if (ok) ok = await self.runNativeFor("idle", 520, epoch, { fps: 4 });
      } else if (name === "curious-scan") {
        var scanDirections = [14, 15, 0, 1, 2, 1, 0, 15];
        for (var si = 0; si < scanDirections.length && self.activityIsCurrent(epoch); si++) {
          self.enterPointerLook(scanDirections[si]);
          await wait(si === 0 || si === scanDirections.length - 1 ? 260 : 150);
        }
        ok = self.activityIsCurrent(epoch);
      } else if (name === "deep-thought") {
        ok = await self.runNativeFor("waiting", 1050, epoch, { fps: 5 });
        if (ok) ok = await self.runNativeFor("review", 1450, epoch, { fps: 7 });
        if (ok) ok = await self.runNativeFor("waiting", 620, epoch, { fps: 4 });
      } else if (name === "work-cycle") {
        ok = await self.runNativeFor("waiting", 520, epoch, { fps: 5 });
        if (ok) ok = await self.runNativeFor("running", 1800, epoch, { fps: 9 });
        if (ok) ok = await self.runNativeFor("review", 1150, epoch, { fps: 7 });
        if (ok) ok = await self.runSupplementalFor("wink", 720, epoch);
      } else if (name === "celebrate") {
        ok = await self.runNativeFor("waving", 680, epoch, { fps: 7 });
        if (ok) ok = await self.runNativeFor("jumping", 860, epoch, { fps: 8 });
        if (ok) ok = await self.runSupplementalFor("wink", 720, epoch);
      } else if (name === "power-nap") {
        ok = await self.runNativeFor("idle", 2100, epoch, { fps: 1 });
        if (ok) ok = await self.runNativeFor("waiting", 560, epoch, { fps: 4 });
        if (ok) ok = await self.runNativeFor("idle", 460, epoch, { fps: 4 });
      } else {
        ok = false;
      }

      if (ok && opts.returnTo !== false) self.finishActivity(epoch);
      return ok;
    })();
  };

  KohanAvatar.prototype.scheduleAutonomousIdle = function (delay) {
    var self = this;
    window.clearTimeout(self.autonomousTimer);
    if (self.destroyed || self.reduceMotion) return;
    var nextDelay = delay != null ? delay :
      IDLE_MIN_MS + Math.floor(Math.random() * (IDLE_MAX_MS - IDLE_MIN_MS + 1));
    self.autonomousTimer = window.setTimeout(async function () {
      if (self.destroyed) return;
      var blocked = document.hidden || self.isDragging || self.state.hidden ||
        !self.root || self.root.classList.contains("kdcv-pet-is-open") ||
        self.state.mood !== "idle";
      if (blocked) {
        self.scheduleAutonomousIdle(10000);
        return;
      }
      self.idleCount += 1;
      // Natural autonomous motion only. Weapon-themed supplemental moods stay
      // available through the explicit public API but are never chosen by an
      // idle timer or a visitor click.
      await self.playActivity(pick(IDLE_ACTIONS), { source: "idle" });
      self.scheduleAutonomousIdle();
    }, nextDelay);
  };

  KohanAvatar.prototype.stop = function () {
    var self = this;
    self.clearReturnTimer();
    self.state.fps = 0;
    self.state.loop = false;
    self.pauseTick();
  };

  KohanAvatar.prototype.reset = function () {
    var self = this;
    self.activityEpoch += 1;
    self.state.hidden = false;
    if (self.root) {
      self.root.removeAttribute("data-kohan-hidden");
      self.root.removeAttribute("data-kohan-activity");
      self.root.removeAttribute("data-kohan-patrol");
    }
    self.enterNative("idle", { loop: true });
    self.resumeTick();
  };

  KohanAvatar.prototype.hide = function () {
    var self = this;
    self.state.hidden = true;
    if (self.root) self.root.setAttribute("data-kohan-hidden", "true");
    self.pauseTick();
  };

  KohanAvatar.prototype.destroy = function () {
    var self = this;
    self.destroyed = true;
    self.stop();
    if (self.tickHandle) window.cancelAnimationFrame(self.tickHandle);
    if (self.returnTimer) window.clearTimeout(self.returnTimer);
    if (self.pointerLookIdleTimer) window.clearTimeout(self.pointerLookIdleTimer);
    if (self.autonomousTimer) window.clearTimeout(self.autonomousTimer);
    if (self.responseWorkTimer) window.clearTimeout(self.responseWorkTimer);
    if (self.responseCompleteTimer) window.clearTimeout(self.responseCompleteTimer);
    if (self.goodbyeRestoreTimer) window.clearTimeout(self.goodbyeRestoreTimer);
    if (self._cycleTimer) window.clearTimeout(self._cycleTimer);
    self.unbindInteractions();
    self.transitionCanvas = null;
    if (self.canvas && self.canvas.parentNode) {
      self.canvas.parentNode.removeChild(self.canvas);
    }
    if (self.debugBadge && self.debugBadge.parentNode) {
      self.debugBadge.parentNode.removeChild(self.debugBadge);
    }
    if (self.sizeControls && self.sizeControls.parentNode) {
      self.sizeControls.parentNode.removeChild(self.sizeControls);
    }
    if (self.root) self.root.removeAttribute("data-kohan-active");
    if (window.KohanAvatar === self.publicApi) {
      try { delete window.KohanAvatar; } catch (e) { window.KohanAvatar = undefined; }
    }
  };

  KohanAvatar.prototype.clearReturnTimer = function () {
    if (this.returnTimer) { window.clearTimeout(this.returnTimer); this.returnTimer = 0; }
  };

  KohanAvatar.prototype.updateBadge = function (text) {
    if (!this.debugBadge) return;
    this.debugBadge.textContent = text;
    this.debugBadge.setAttribute("data-visible", this.debugMode ? "true" : "false");
  };

  KohanAvatar.prototype.debug = function (on) {
    this.debugMode = !!on;
    if (this.debugBadge) {
      this.debugBadge.setAttribute("data-visible", this.debugMode ? "true" : "false");
    }
  };

  // ------------------------------------------------------------- static / dynamic
  /**
   * Static mode: freeze the current frame. Animation stops but the avatar
   * stays visible. Useful for screenshots, low-power mode, or as a fallback
   * when full motion isn't desirable.
   *
   * Dynamic mode (default): full sprite animation + pointer-look + lifecycle
   * mood changes from the chat system.
   */
  KohanAvatar.prototype.setStatic = function (on) {
    var self = this;
    self.staticMode = !!on;
    if (self.staticMode) {
      // Freeze: store current fps and zero it so the tick renders the same frame.
      if (!self._savedFps) self._savedFps = self.state.fps;
      self.state.fps = 0;
      // Keep the already-painted frame and release the RAF chain.
      self.pauseTick();
      self.log("setStatic(true) — animation frozen");
    } else {
      // Restore fps and re-enter current mood to restart the cycle.
      if (self._savedFps) {
        self.state.fps = self._savedFps;
        self._savedFps = null;
      }
      // Reset startTime so animation picks up from frame 0 cleanly.
      self.state.startTime = performance.now();
      self.resumeTick();
      self.log("setStatic(false) — animation resumed");
    }
    return self.staticMode ? "static" : "dynamic";
  };

  KohanAvatar.prototype.getMode = function () {
    return this.staticMode ? "static" : "dynamic";
  };

  /**
   * Test helper: cycle through every available mood for demo / QA.
   * Returns a canceller function.
   */
  KohanAvatar.prototype.cycleMoods = function (perMood) {
    var self = this;
    perMood = perMood || 1800;
    var moods = [
      "idle", "waving", "jumping", "failed", "waiting",
      "macbook-work", "ipad-review", "running-right", "running-left",
      "vision", "walk", "wink", "angry-still", "confused-vision",
      "guarded", "russian-roulette", "drag-annoyed", "fall-scared",
      "watch-check", "hand-on-chest", "whistle", "hands-in-pockets",
      "looking-sky", "nap", "stretch", "curious-scan", "deep-thought",
      "work-cycle", "celebrate", "power-nap", "goodbye"
    ];
    var i = 0;
    var cancelled = false;
    var next = function () {
      if (cancelled || self.destroyed) return;
      var m = moods[i % moods.length];
      self.play(m, { duration: perMood, returnTo: m });
      i++;
      self._cycleTimer = window.setTimeout(next, perMood);
    };
    next();
    return function cancel() { cancelled = true; if (self._cycleTimer) window.clearTimeout(self._cycleTimer); };
  };

  // ------------------------------------------------------------- tick / render
  KohanAvatar.prototype.startTick = function () {
    var self = this;
    if (self.tickHandle) return;
    var tick = function (now) {
      if (self.destroyed || !self.canAnimate()) {
        self.tickHandle = 0;
        return;
      }
      self.frame(now);
      self.tickHandle = window.requestAnimationFrame(tick);
    };
    self.tickHandle = window.requestAnimationFrame(tick);
  };

  KohanAvatar.prototype.motionFor = function () {
    if (this.reduceMotion) return { x: 0, y: 0, scaleX: 1, scaleY: 1, rotate: 0 };
    var mood = this.state.mood;
    var t = this._motionTime;
    var breath = Math.sin(t * Math.PI * 2 / 4200);
    var settle = Math.sin(t * Math.PI * 2 / 2700 + 0.8);
    var motion = { x: 0, y: -0.45 * breath, scaleX: 1, scaleY: 1 + 0.006 * breath, rotate: 0 };

    if (mood === "waiting" || mood === "pointer-look") {
      motion.rotate = settle * 0.004;
      motion.x = settle * 0.45;
    } else if (mood === "running" || mood === "review") {
      motion.y = -0.7 + Math.sin(t * Math.PI * 2 / 1100) * 0.55;
      motion.rotate = Math.sin(t * Math.PI * 2 / 1800) * 0.003;
    } else if (mood === "waving") {
      motion.rotate = Math.sin(t * Math.PI * 2 / 1300) * 0.004;
    } else if (mood === "failed" || mood === "angry-still") {
      motion.x = Math.sin(t * Math.PI * 2 / 620) * 0.35;
    } else if (mood === "wink" || mood === "confused-vision") {
      motion.y = -0.8 + settle * 0.35;
    }
    return motion;
  };

  KohanAvatar.prototype.frame = function (now) {
    var self = this;
    self._frameCount = (self._frameCount || 0) + 1;
    var delta = self._lastFrameNow ? Math.min(50, Math.max(0, now - self._lastFrameNow)) : 0;
    self._lastFrameNow = now;
    self._motionTime += delta;
    if (!self.atlasReady || !self.ctx) {
      self._lastSkip = "early: atlas=" + self.atlasReady + " ctx=" + !!self.ctx;
      return;
    }

    var s = self.state;
    var col = 0, row = 0, src = null, sx = 0, sy = 0, sw = CELL_W, sh = CELL_H;

    if (s.kind === "native" || s.kind === "pointer") {
      // Compute current column based on FPS and elapsed time.
      if (s.fps > 0 && s.frames > 1) {
        var elapsed = now - s.startTime;
        var frameDur = 1000 / s.fps;
        var idx = Math.floor(elapsed / frameDur);
        if (s.loop) {
          col = idx % s.frames;
        } else {
          col = Math.min(idx, s.frames - 1);
        }
      } else {
        col = s.kind === "pointer" ? s.col : (s.col || 0);
      }
      row = s.row;
      src = self.atlas;
      sx = col * CELL_W;
      sy = row * CELL_H;
    } else if (s.kind === "supplemental") {
      var bundle = self.supplemental[s.mood];
      if (!bundle) { self._lastSkip = "no bundle: " + s.mood; return; }
      var idx = 0;
      if (s.frameOrder && s.frameOrder.length) {
        var orderedElapsed = Math.max(0, now - s.startTime);
        var orderedDelays = s.frameDelays || [];
        var orderedTotal = 0;
        for (var oi = 0; oi < s.frameOrder.length; oi++) {
          orderedTotal += orderedDelays[oi] || orderedDelays[orderedDelays.length - 1] || 220;
        }
        if (s.loop && orderedTotal > 0) orderedElapsed = orderedElapsed % orderedTotal;
        var orderedCursor = 0;
        for (var oj = 0; oj < s.frameOrder.length; oj++) {
          orderedCursor += orderedDelays[oj] || orderedDelays[orderedDelays.length - 1] || 220;
          idx = s.frameOrder[oj];
          if (orderedElapsed < orderedCursor) break;
        }
      } else if (s.fps > 0 && bundle.info.frames > 1) {
        var elapsed = now - s.startTime;
        var frameDur = 1000 / s.fps;
        idx = Math.floor(elapsed / frameDur);
        if (!s.loop) idx = Math.min(idx, bundle.info.frames - 1);
        else idx = idx % bundle.info.frames;
      }
      src = bundle.frames[idx];
      if (!src) { self._lastSkip = "no frame: " + s.mood; return; }
      sx = 0; sy = 0; sw = src.naturalWidth || CELL_W; sh = src.naturalHeight || CELL_H;
    } else {
      self._lastSkip = "kind=" + s.kind;
      return;
    }

    var ctx = self.ctx;
    var cw = self.canvas.width, ch = self.canvas.height;
    ctx.clearRect(0, 0, cw, ch);
    var srcW = src.naturalWidth || src.width;
    if (!srcW) { self._lastSkip = "src width=0"; return; }
    self._lastSkip = "DREW col=" + col + " row=" + row + " cw=" + cw + " ch=" + ch + " srcW=" + srcW;
    var motion = self.motionFor();
    var pivotX = cw * 0.5;
    var pivotY = ch * 0.93;
    ctx.save();
    ctx.translate(pivotX + motion.x * (cw / CELL_W), pivotY + motion.y * (ch / CELL_H));
    ctx.rotate(motion.rotate);
    ctx.scale(motion.scaleX, motion.scaleY);
    ctx.translate(-pivotX, -pivotY);
    ctx.drawImage(src, sx, sy, sw, sh, 0, 0, cw, ch);
    ctx.restore();

    if (self.transitionCanvas && self.transitionDuration > 0) {
      var progress = Math.min(1, Math.max(0, (now - self.transitionStartedAt) / self.transitionDuration));
      // Smoothstep gives a gentle shoulder at both ends and avoids the flash
      // caused by linear alpha around high-contrast silhouettes.
      var eased = progress * progress * (3 - 2 * progress);
      if (progress < 1) {
        ctx.save();
        ctx.globalAlpha = 1 - eased;
        ctx.drawImage(self.transitionCanvas, 0, 0, cw, ch);
        ctx.restore();
      } else {
        self.transitionDuration = 0;
        self.transitionCanvas = null;
      }
    }
  };

  // ------------------------------------------------------------- interactions
  KohanAvatar.prototype.bindInteractions = function () {
    var self = this;
    if (self._bound) return;
    self._bound = true;

    var root = self.root;
    var trigger = self.trigger;

    // 1) pointer-look (hover follow) — document-level, throttled via rAF.
    var lookPending = false, lastClientX = 0, lastClientY = 0;
    var lookFlush = function () {
      lookPending = false;
      if (!self.optionFlags.enablePointerLook) return;
      if (self.state.kind === "supplemental") return;        // supplemental interrupts look
      if (self.state.mood === "drag-annoyed" || self.state.mood === "fall-scared") return;
      if (root && root.hasAttribute("data-kohan-activity")) return;
      // Don't override while a non-idle native mood is playing briefly.
      var transient = ["waving","jumping","failed","running","review","waiting"].indexOf(self.state.mood) !== -1;
      if (transient && self.state.duration !== Infinity) return;

      var r = trigger.getBoundingClientRect();
      var cx = r.left + r.width / 2;
      var cy = r.top + r.height / 2;
      var dx = lastClientX - cx;
      var dy = lastClientY - cy;
      var dist = Math.hypot(dx, dy);
      // Only track when pointer is within ~360px of the avatar.
      if (dist > 360) {
        if (self.state.kind === "pointer" && !self.pointerLookActive) {
          self.enterNative("idle", { loop: true });
        }
        return;
      }
      // angle: 0° = right, 90° = down (screen y grows downward), measured CW.
      var ang = Math.atan2(dy, dx) * 180 / Math.PI;
      if (ang < 0) ang += 360;
      // Snap to nearest of 16 directions.
      var idx = Math.round(ang / 22.5) % 16;
      if (self.state.kind !== "pointer" || self.state.pointerDir !== idx) {
        self.state.pointerDir = idx;
        self.enterPointerLook(idx);
      }
      self.pointerLookActive = true;
      window.clearTimeout(self.pointerLookIdleTimer);
      self.pointerLookIdleTimer = window.setTimeout(function () {
        self.pointerLookActive = false;
        if (self.state.kind === "pointer") self.enterNative("idle", { loop: true });
      }, 1200);
    };
    self._lookFlush = lookFlush;

    self.listen(document, "pointermove", function (e) {
      lastClientX = e.clientX; lastClientY = e.clientY;
      if (!lookPending) {
        lookPending = true;
        window.requestAnimationFrame(lookFlush);
      }
    }, { passive: true });

    self.listen(document, "pointerout", function (e) {
      if (!e.relatedTarget) {
        window.clearTimeout(self.pointerLookIdleTimer);
        if (self.state.kind === "pointer") self.enterNative("idle", { loop: true });
      }
    });

    // 2) Pointer gesture: a real click starts one random approved action.
    // Moving at least 6px belongs to the host drag controller and is not a click.
    self.listen(trigger, "pointerdown", function (e) {
      if (!e.isPrimary || e.button !== 0) return;
      self._pointerGesture = {
        id: e.pointerId,
        x: e.clientX,
        y: e.clientY,
        moved: false,
        wasOpen: root.classList.contains("kdcv-pet-is-open")
      };
    }, true);
    self.listen(trigger, "pointermove", function (e) {
      var g = self._pointerGesture;
      if (!g || g.id !== e.pointerId || g.moved) return;
      if (Math.hypot(e.clientX - g.x, e.clientY - g.y) >= 6) g.moved = true;
    }, true);
    var finishGesture = function (e) {
      var g = self._pointerGesture;
      if (!g || g.id !== e.pointerId) return;
      self._pointerGesture = null;
      if (!g.moved && e.type === "pointerup") {
        // The host toggles its chat panel on the following click event.
        // Run after that event so the random attitude remains visible.
        window.setTimeout(function () {
          var justClosed = g.wasOpen && !root.classList.contains("kdcv-pet-is-open");
          if (!self.destroyed && !self.isDragging && !justClosed) {
            self.playActivity(pick(CLICK_ACTIONS), { source: "click" });
          }
        }, 80);
      }
    };
    self.listen(trigger, "pointerup", finishGesture, true);
    self.listen(trigger, "pointercancel", finishGesture, true);
    self.listen(trigger, "click", function (e) {
      // Keyboard activation has no preceding pointer gesture.
      if (e.detail === 0) {
        window.setTimeout(function () {
          if (!self.destroyed) self.playActivity(pick(CLICK_ACTIONS), { source: "keyboard" });
        }, 80);
      }
    });

    // 3) Drag mood: watch the host pet's drag class.
    var draggingPrev = false;
    var dragObserver = new MutationObserver(function () {
      if (!root) return;
      var dragging = root.classList.contains("kdcv-pet-dragging");
      if (dragging === draggingPrev) return;
      draggingPrev = dragging;
      if (!self.optionFlags.enableDragMoods) return;
      if (dragging) {
        // Drag starts: randomly annoyed or afraid of falling.
        self.isDragging = true;
        self.beginActivity("drag");
        self.clearReturnTimer();
        self.enterSupplemental(Math.random() < 0.5 ? "fall-scared" : "drag-annoyed", {
          loop: true,
          duration: Infinity
        });
      } else {
        self.isDragging = false;
        // Drag ends: randomly angry or confused, then idle.
        self.playActivity(Math.random() < 0.5 ? "angry-still" : "confused-vision", {
          duration: 1100,
          source: "drag-release"
        });
      }
    });
    dragObserver.observe(root, { attributes: true, attributeFilter: ["class"] });
    self._dragObserver = dragObserver;

    // 4) Chat open → greet. Closing the panel never hides the avatar;
    // wave/snap/smoke is reserved exclusively for the exact word "BYE".
    var openPrev = false;
    var openObserver = new MutationObserver(function () {
      if (!root) return;
      var isOpen = root.classList.contains("kdcv-pet-is-open");
      if (isOpen === openPrev) return;
      openPrev = isOpen;
      if (isOpen) {
        self.playActivity("waving", { duration: 1200, source: "open" });
      }
    });
    openObserver.observe(root, { attributes: true, attributeFilter: ["class"] });
    self._openObserver = openObserver;

    // Exact BYE submit → wave, snap, smoke, disappear. Capture runs before
    // the host clears the input, so this also works if the request later
    // falls back to the page-local answer path instead of fetch().
    var chatForm = root.querySelector(".kdcv-pet-form");
    var chatInput = root.querySelector(".kdcv-pet-input");
    if (chatForm && chatInput) {
      self.listen(chatForm, "submit", function () {
        if (String(chatInput.value || "").trim().toUpperCase() !== "BYE") return;
        self.byePending = true;
        self.playActivity("goodbye", { restoreAfter: 6000, source: "bye" });
      }, true);
    }

    // 5) Hook AI lifecycle (fetch monkey-patch for the askUrl endpoint).
    self.patchFetch();

    // 6) Custom event bridge.
    self.listen(window, "kohan:avatar:mood", function (e) {
      var d = e && e.detail || {};
      if (!d.mood) return;
      self.play(d.mood, {
        duration: d.duration,
        loop: d.loop,
        returnTo: d.returnTo || "idle"
      });
    });

    // 7) Locale change: no-op for sprite, but reset mood to be safe.
    self.listen(document, "kdcvpet:localechange", function () {
      if (self.state.kind === "pointer" || self.state.mood === "idle") return;
      // locale change shouldn't disturb mood much; leave alone.
    });

    // 8) Resize handling.
    self.listen(window, "resize", function () { self.applyDpi(); }, { passive: true });
    if (window.visualViewport) {
      self.listen(window.visualViewport, "resize", function () { self.applyDpi(); }, { passive: true });
    }

    self.listen(document, "visibilitychange", function () {
      self._visibilityPaused = document.hidden;
      if (document.hidden) {
        self.pauseTick();
      } else {
        self.state.startTime = performance.now();
        self.resumeTick();
      }
    });
  };

  KohanAvatar.prototype.unbindInteractions = function () {
    var self = this;
    if (self._dragObserver) self._dragObserver.disconnect();
    if (self._openObserver) self._openObserver.disconnect();
    if (self._unpatchFetch) self._unpatchFetch();
    self._listeners.forEach(function (binding) {
      binding.target.removeEventListener(binding.type, binding.handler, binding.options);
    });
    self._listeners = [];
  };

  KohanAvatar.prototype.patchFetch = function () {
    var self = this;
    if (window.__KOHAN_FETCH_PATCHED__) return;
    window.__KOHAN_FETCH_PATCHED__ = true;
    var origFetch = window.fetch;
    if (!origFetch) return;

    var askUrl = (window.KDCV_CONFIG && window.KDCV_CONFIG.askUrl) || "/wp-json/kdcv/v1/ask";

    var patchedFetch = function (input, init) {
      var url = typeof input === "string" ? input :
                (input && input.url) ? input.url : "";
      if (url && url.indexOf(askUrl) !== -1) {
        var isBye = self.byePending;
        if (!isBye && init && typeof init.body === "string") {
          try {
            var requestBody = JSON.parse(init.body);
            isBye = String(requestBody.question || "").trim().toUpperCase() === "BYE";
          } catch (ignore) {}
        }
        if (isBye) {
          self.byePending = false;
          return origFetch.apply(this, arguments);
        }
        // AI request lifecycle begins.
        self.playActivity("confused-vision", { duration: 600, source: "response-start" });
        window.clearTimeout(self.responseWorkTimer);
        self.responseWorkTimer = window.setTimeout(function () {
          self.responseWorkTimer = 0;
          if (self.destroyed) return;
          if (self.state.mood === "confused-vision" || self.state.mood === "idle") {
            self.playActivity("macbook-work", { duration: 30000, source: "response-working" });
          }
        }, 600);
        return origFetch.apply(this, arguments).then(function (resp) {
          // Clone + parse to determine success.
          resp.clone().json().then(function (data) {
            if (data && data.available) {
              self.playActivity("ipad-review", { duration: 1800, source: "response-review" });
              window.clearTimeout(self.responseCompleteTimer);
              self.responseCompleteTimer = window.setTimeout(function () {
                self.responseCompleteTimer = 0;
                if (self.destroyed) return;
                self.playActivity("wink", { duration: 800, source: "response-complete" });
              }, 1800);
            } else {
              self.playActivity("angry-still", { duration: 2000, source: "response-failed" });
            }
          }).catch(function () {
            // response wasn't JSON, treat as success
            self.playActivity("ipad-review", { duration: 1500, source: "response-review" });
          });
          return resp;
        }).catch(function (err) {
          self.playActivity("angry-still", { duration: 2500, source: "response-error" });
          throw err;
        });
      }
      return origFetch.apply(this, arguments);
    };
    window.fetch = patchedFetch;

    self._unpatchFetch = function () {
      if (window.fetch === patchedFetch) {
        window.fetch = origFetch;
      }
      window.__KOHAN_FETCH_PATCHED__ = false;
    };
  };

  // ------------------------------------------------------------- exports
  var instance = new KohanAvatar();
  var publicApi = {
    setMood:        function (m, o) { return instance.setMood(m, o); },
    play:           function (m, o) { return instance.play(m, o); },
    stop:           function () { return instance.stop(); },
    reset:          function () { return instance.reset(); },
    destroy:        function () { return instance.destroy(); },
    refreshAssets:  function () { return instance.refreshAssets(); },
    debug:          function (on) { return instance.debug(on); },
    isAllowed:      function (m) { return instance.isAllowed(m); },
    getState:       function () { return Object.assign({}, instance.state); },
    setStatic:      function (on) { return instance.setStatic(on); },
    getMode:        function () { return instance.getMode(); },
    cycleMoods:     function (p) { return instance.cycleMoods(p); },
    playActivity:   function (m, o) { return instance.playActivity(m, o); },
    triggerRandom:  function () { return instance.playActivity(pick(CLICK_ACTIONS), { source: "api" }); },
    availableMoods: function () { return Object.keys(ALLOWED_MOODS).sort(); },
    setSize:         function (size) { return instance.applySize(size, true); },
    getSize:         function () { return instance.avatarSize; },
    toggleClassic:   function (force) { return instance.toggleClassic(force); },
    toggleHidden:    function (force) { return instance.toggleHidden(force); },
    isHidden:        function () { return !!instance.root && instance.root.getAttribute("data-kohan-hidden") === "true"; },
    isClassic:       function () { return instance.root && instance.root.getAttribute("data-kohan-active") !== "true"; },
    setOptions:     function (o) {
      if (!o) return;
      Object.keys(o).forEach(function (k) {
        if (instance.optionFlags.hasOwnProperty(k)) {
          instance.optionFlags[k] = !!o[k];
        }
      });
    }
  };
  instance.publicApi = publicApi;
  window.KohanAvatar = publicApi;

  // Auto-boot
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () { instance.boot(); });
  } else {
    instance.boot();
  }
})();
