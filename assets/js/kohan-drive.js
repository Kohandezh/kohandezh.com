/*!
 * kohan-drive.js — the avatar drives instead of walking.
 *
 * WHY IT IS A SEPARATE FILE
 * kohan-avatar-enhance.js already owns movement: double-click walks the avatar
 * to a point, arrow keys nudge it while focused. Both of those paths set ONE
 * attribute on the avatar root — data-kohan-walking="left|right" — and remove
 * it when the motion ends. That attribute is the entire integration surface, so
 * drive mode observes it and needs no edit to the movement code. Nothing about
 * double-click, arrow keys, dragging or the chat panel changes.
 *
 * THE SEQUENCE
 *   idle      avatar visible, no car
 *   entering  car appears with the dihedral door UP, avatar hidden
 *   driving   door closed, car faces the direction of travel
 *   exiting   door swings UP again, then the car fades and the avatar returns
 *
 * The car sprite already contains the driver, so the walking avatar is hidden
 * while driving rather than composited on top of the car.
 *
 * FAILURE BEHAVIOUR
 * If the sprites do not load, driveReady stays false and every hook becomes a
 * no-op: the avatar simply walks exactly as it does today. A missing image can
 * never leave the visitor with an invisible avatar.
 */
(function () {
  "use strict";

  if (window.__KOHAN_DRIVE__) return;
  window.__KOHAN_DRIVE__ = true;

  var ROOT_SEL = ".kdcv-pet-root, .kohan-avatar-root";
  var BASE = (window.KDCV_WP && window.KDCV_WP.assets) ||
    ((/\/(blog|portfolio)\//.test(window.location.pathname) ? "../" : "") + "assets/");
  var SRC = {
    left:  BASE + "kohan/car/kohan-car-drive.webp",
    right: BASE + "kohan/car/kohan-car-drive-right.webp",
    door:  BASE + "kohan/car/kohan-car-door.webp"
  };

  // Door animation length. Kept in sync with --kohan-car-door-ms in the CSS;
  // if the visitor asked for reduced motion both collapse to zero.
  var REDUCED = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var DOOR_MS = REDUCED ? 0 : 420;

  var ready = false, car = null, state = "idle", exitTimer = 0, enterTimer = 0;

  function root() { return document.querySelector(ROOT_SEL); }

  /* Preload all three frames before enabling. Swapping to an unloaded image
     would flash an empty box in the middle of the animation. */
  function preload(done) {
    var left = 3, ok = true;
    Object.keys(SRC).forEach(function (k) {
      var im = new Image();
      im.onload = function () { if (!--left) done(ok); };
      im.onerror = function () { ok = false; if (!--left) done(ok); };
      im.src = SRC[k];
    });
  }

  function build() {
    var r = root();
    if (!r || car) return;
    car = document.createElement("div");
    car.className = "kohan-car";
    car.setAttribute("aria-hidden", "true"); // decorative; the avatar carries the semantics
    car.dataset.kohanCar = "idle";
    r.appendChild(car);
    /* Force the initial style to be computed BEFORE any state change.
       Without this the element is inserted and switched to "entering" inside
       the same task, so the transition has no starting value to run from and
       the computed opacity stays pinned at the base 0 — the car is fully
       wired, correctly positioned, and simply never visible. Reading a layout
       property flushes style so the first transition has something to leave. */
    void car.offsetHeight;
  }

  function setFrame(which) {
    if (!car) return;
    car.style.backgroundImage = "url(" + SRC[which] + ")";
  }

  function enter(dir) {
    if (!ready || state === "driving" || state === "entering") return;
    window.clearTimeout(exitTimer);
    build();
    if (!car) return;
    state = "entering";
    var r = root();
    r.setAttribute("data-kohan-driving", "");
    // Door up first, then closed — the car is already moving underneath, which
    // reads as "getting in on the roll" rather than a dead pause.
    setFrame("door");
    car.dataset.kohanCar = "entering";
    car.dataset.kohanDir = dir;
    window.clearTimeout(enterTimer);
    enterTimer = window.setTimeout(function () {
      if (state !== "entering") return;
      state = "driving";
      setFrame(dir === "left" ? "left" : "right");
      car.dataset.kohanCar = "driving";
    }, DOOR_MS);
  }

  function steer(dir) {
    if (state !== "driving" || !car) return;
    if (car.dataset.kohanDir === dir) return;
    car.dataset.kohanDir = dir;
    setFrame(dir === "left" ? "left" : "right");
  }

  function exit() {
    if (!ready || !car || state === "idle" || state === "exiting") return;
    state = "exiting";
    window.clearTimeout(enterTimer);
    setFrame("door");            // door swings up to let him out
    car.dataset.kohanCar = "exiting";
    exitTimer = window.setTimeout(function () {
      state = "idle";
      car.dataset.kohanCar = "idle";
      var r = root();
      if (r) r.removeAttribute("data-kohan-driving");
    }, DOOR_MS + 160);
  }

  /* The single integration point: data-kohan-walking is written by BOTH the
     double-click tween and the arrow-key loop in kohan-avatar-enhance.js, and
     removed the moment either finishes. */
  function watch() {
    var r = root();
    if (!r) return void setTimeout(watch, 300);
    build();

    new MutationObserver(function () {
      var dir = r.getAttribute("data-kohan-walking");
      if (dir) {
        if (state === "driving") steer(dir);
        else enter(dir);
      } else if (state === "driving" || state === "entering") {
        exit();
      }
    }).observe(r, { attributes: true, attributeFilter: ["data-kohan-walking"] });

    // A drag is a hand-carry, not a drive: bail out so the car does not hang
    // around while the visitor is physically moving the avatar.
    r.addEventListener("pointerdown", function () { if (state !== "idle") exit(); }, true);
  }

  preload(function (ok) {
    ready = ok;
    if (ok) watch();
    // if !ok the avatar keeps walking exactly as before — no console noise,
    // nothing visibly broken.
  });
})();
