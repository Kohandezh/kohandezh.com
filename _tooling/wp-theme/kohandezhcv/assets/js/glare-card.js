/*!
 * glare-card.js — vanilla port of the <GlareCard> pointer logic.
 *
 * The reference component is React: refs hold the glare/background/rotate
 * state and a pointermove handler writes them back as CSS custom properties.
 * This project has no React and no build step, so the same maths runs against
 * the existing DOM and the same custom properties defined in glare-card.css.
 *
 * Applied to the LinkedIn recommendation cards, which are injected by
 * linkedin-content.js AFTER this script runs — hence the MutationObserver.
 */
(function () {
  "use strict";

  if (window.__KDCV_GLARE__) return;
  window.__KDCV_GLARE__ = true;

  var SELECTOR = ".linkedin-recommendation-card";
  var ROTATE_FACTOR = 0.4;

  // Touch devices have no hover, so the effect would only ever be triggered by
  // an incidental tap mid-scroll. Skip the wiring entirely there.
  function interactive() {
    return !window.matchMedia || window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  }
  function reducedMotion() {
    return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function layers(card) {
    if (card.querySelector(".kdcv-glare")) return;
    ["kdcv-glare-sheen", "kdcv-glare-foil"].forEach(function (cls) {
      var el = document.createElement("div");
      el.className = "kdcv-glare " + cls;
      el.setAttribute("aria-hidden", "true");
      card.appendChild(el);
    });
  }

  function enhance(card) {
    if (card.dataset.kdcvGlare) return;
    card.dataset.kdcvGlare = "1";
    layers(card);

    var inside = false;

    card.addEventListener("pointermove", function (event) {
      var rect = card.getBoundingClientRect();
      if (!rect.width || !rect.height) return;

      var px = (100 / rect.width) * (event.clientX - rect.left);
      var py = (100 / rect.height) * (event.clientY - rect.top);
      var dx = px - 50;
      var dy = py - 50;

      card.style.setProperty("--m-x", px + "%");
      card.style.setProperty("--m-y", py + "%");
      card.style.setProperty("--bg-x", (50 + px / 4 - 12.5) + "%");
      card.style.setProperty("--bg-y", (50 + py / 3 - 16.67) + "%");

      if (!reducedMotion()) {
        card.style.setProperty("--r-x", (-(dx / 3.5) * ROTATE_FACTOR) + "deg");
        card.style.setProperty("--r-y", ((dy / 2) * ROTATE_FACTOR) + "deg");
      }
    });

    card.addEventListener("pointerenter", function () {
      inside = true;
      // The reference drops the transition to 0s shortly after entry so the
      // tilt tracks the cursor 1:1 instead of lagging behind it.
      window.setTimeout(function () {
        if (inside) card.style.setProperty("--duration", "0s");
      }, 300);
    });

    card.addEventListener("pointerleave", function () {
      inside = false;
      card.style.removeProperty("--duration");
      card.style.setProperty("--r-x", "0deg");
      card.style.setProperty("--r-y", "0deg");
    });
  }

  function sweep() {
    var cards = document.querySelectorAll(SELECTOR);
    for (var i = 0; i < cards.length; i++) enhance(cards[i]);
  }

  function init() {
    if (!interactive()) return;

    if ("MutationObserver" in window && document.body) {
      var queued = false;
      new MutationObserver(function () {
        if (queued) return;
        queued = true;
        window.requestAnimationFrame(function () { queued = false; sweep(); });
      }).observe(document.body, { childList: true, subtree: true });
    }

    sweep();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
