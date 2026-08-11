/*!
 * pearl-button.js — applies the Pearl pill to the section label chips.
 *
 * The reference component owns its markup (a .wrap > p with two sparkle
 * spans). These pills already exist in nine locale files, so instead of
 * rewriting that markup the class is applied here and the two structural
 * pieces the effect needs are added: a sparkle node and a label wrapper for
 * the gradient mask.
 */
(function () {
  "use strict";

  if (window.__KDCV_PEARL__) return;
  window.__KDCV_PEARL__ = true;

  var TARGETS = [
    { sel: ".sect-tag", extra: "" },
    { sel: ".professional-role__badge", extra: "" },
    { sel: ".timeline-date", extra: "kdcv-pearl--chip" },
    { sel: "[data-kdcv-pearl]", extra: "" }
  ];

  function reducedMotion() {
    return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function enhance(el, extra) {
    if (el.dataset.kdcvPearlDone) return;
    el.dataset.kdcvPearlDone = "1";

    el.classList.add("kdcv-pearl");
    if (extra) el.classList.add(extra);

    // Wrap the loose text in a label node so the mask applies to the words
    // only — not to the icon, which would look like a rendering fault.
    var label = null;
    for (var i = 0; i < el.childNodes.length; i++) {
      var n = el.childNodes[i];
      if (n.nodeType === 3 && n.nodeValue.trim()) {
        label = document.createElement("span");
        label.className = "kdcv-pearl-label";
        label.textContent = n.nodeValue.trim();
        el.replaceChild(label, n);
        break;
      }
    }
    if (!label) return;

    // The sparkle is decorative: it must never reach the accessibility tree,
    // or every section label would be announced with a stray glyph.
    if (!reducedMotion()) {
      var spark = document.createElement("span");
      spark.className = "kdcv-pearl-spark";
      spark.setAttribute("aria-hidden", "true");
      el.insertBefore(spark, label);
    }
  }

  function sweep() {
    TARGETS.forEach(function (t) {
      var list = document.querySelectorAll(t.sel);
      for (var i = 0; i < list.length; i++) {
        try { enhance(list[i], t.extra); } catch (e) {}
      }
    });
  }

  function init() {
    if ("MutationObserver" in window && document.body) {
      var queued = false;
      new MutationObserver(function () {
        if (queued) return;
        queued = true;
        // setTimeout, not rAF: rAF is paused in a hidden tab.
        window.setTimeout(function () { queued = false; sweep(); }, 60);
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
