/*!
 * bs-lite.js — replaces bootstrap.min.js (60 KB) with the two components this
 * site actually uses.
 *
 * AUDIT BEFORE REMOVAL
 * Every `data-bs-*` attribute across every page was counted:
 *     data-bs-toggle="collapse"   36   (the services accordion)
 *     data-bs-toggle="offcanvas"   9   (the language menu)
 *     data-bs-dismiss="offcanvas"  9
 *     data-bs-parent               36  (accordion grouping)
 * No modal, dropdown, tab, tooltip, popover, carousel or scrollspy. Only the
 * nine CV pages loaded the bundle at all.
 *
 * BOOTSTRAP'S CSS STAYS. It carries the grid (.container/.row/.col-lg-*) that
 * the whole layout is built on, plus the .collapse/.offcanvas base rules this
 * file drives. Only the JavaScript is replaced.
 *
 * The CLASS CONTRACT is reproduced exactly, because other scripts and the
 * stylesheet depend on it:
 *   collapse  — `.collapse`, `.collapse.show`, `.collapsing` (height animated),
 *               trigger gets `.collapsed` + aria-expanded
 *               (accessibility-enhancements.js observes `.collapsed`)
 *   offcanvas — `.offcanvas.show`, a `.offcanvas-backdrop.fade.show` element,
 *               body scroll locked while open
 *
 * Behaviour kept: accordion grouping via data-bs-parent, Esc to close the
 * offcanvas, backdrop click to close, focus returned to the trigger.
 */
(function () {
  "use strict";

  if (window.__KDCV_BSLITE__) return;
  window.__KDCV_BSLITE__ = true;

  var DURATION = 350; // matches Bootstrap's .35s transition

  function reduced() {
    return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function targetOf(trigger) {
    var sel = trigger.getAttribute("data-bs-target") || trigger.getAttribute("href");
    if (!sel || sel === "#") return null;
    try { return document.querySelector(sel); } catch (e) { return null; }
  }

  /* ---- collapse ---------------------------------------------------------- */

  function triggersFor(panel) {
    if (!panel.id) return [];
    var all = document.querySelectorAll('[data-bs-toggle="collapse"]');
    var out = [];
    for (var i = 0; i < all.length; i++) {
      if (targetOf(all[i]) === panel) out.push(all[i]);
    }
    return out;
  }

  function markTriggers(panel, expanded) {
    triggersFor(panel).forEach(function (t) {
      t.classList.toggle("collapsed", !expanded);
      t.setAttribute("aria-expanded", expanded ? "true" : "false");
    });
  }

  function isOpen(panel) {
    return panel.classList.contains("show");
  }

  function show(panel) {
    if (panel.dataset.bsBusy === "1" || isOpen(panel)) return;

    // Accordion: opening one panel closes its siblings in the same parent.
    var parentSel = panel.getAttribute("data-bs-parent");
    if (parentSel) {
      var parent = document.querySelector(parentSel);
      if (parent) {
        parent.querySelectorAll(".collapse.show").forEach(function (other) {
          if (other !== panel && other.getAttribute("data-bs-parent") === parentSel) hide(other);
        });
      }
    }

    if (reduced()) {
      panel.classList.add("show");
      markTriggers(panel, true);
      return;
    }

    panel.dataset.bsBusy = "1";
    panel.classList.remove("collapse");
    panel.classList.add("collapsing");
    panel.style.height = "0px";
    markTriggers(panel, true);

    // Read the natural height, then animate to it.
    var target = panel.scrollHeight;
    window.requestAnimationFrame(function () { panel.style.height = target + "px"; });

    window.setTimeout(function () {
      panel.classList.remove("collapsing");
      panel.classList.add("collapse", "show");
      panel.style.height = "";
      panel.dataset.bsBusy = "0";
    }, DURATION);
  }

  function hide(panel) {
    if (panel.dataset.bsBusy === "1" || !isOpen(panel)) return;

    if (reduced()) {
      panel.classList.remove("show");
      markTriggers(panel, false);
      return;
    }

    panel.dataset.bsBusy = "1";
    panel.style.height = panel.getBoundingClientRect().height + "px";
    // Force a reflow so the browser has a start value to animate from.
    void panel.offsetHeight;

    panel.classList.remove("collapse", "show");
    panel.classList.add("collapsing");
    panel.style.height = "0px";
    markTriggers(panel, false);

    window.setTimeout(function () {
      panel.classList.remove("collapsing");
      panel.classList.add("collapse");
      panel.style.height = "";
      panel.dataset.bsBusy = "0";
    }, DURATION);
  }

  function toggleCollapse(panel) {
    if (isOpen(panel)) hide(panel);
    else show(panel);
  }

  /* ---- offcanvas --------------------------------------------------------- */

  var backdrop = null;
  var openPanel = null;
  var lastTrigger = null;

  function makeBackdrop() {
    var el = document.createElement("div");
    el.className = "offcanvas-backdrop fade";
    document.body.appendChild(el);
    // Next frame so the opacity transition has a starting point.
    window.requestAnimationFrame(function () { el.classList.add("show"); });
    el.addEventListener("click", function () { closeOffcanvas(); });
    return el;
  }

  function openOffcanvas(panel, trigger) {
    if (!panel) return;
    // Idempotent: kdcv-interaction-fix.js (generated by the tooling) also opens
    // this panel, so `.show` may already be set by the time we run. Bail only
    // if WE already own it, otherwise take ownership so the close path works.
    if (panel === openPanel) return;
    if (openPanel) closeOffcanvas();

    openPanel = panel;
    lastTrigger = trigger || null;

    panel.classList.add("showing");
    panel.classList.add("show");
    panel.removeAttribute("aria-hidden");
    panel.setAttribute("role", panel.getAttribute("role") || "dialog");
    panel.setAttribute("aria-modal", "true");

    backdrop = makeBackdrop();
    document.body.style.overflow = "hidden";

    window.setTimeout(function () { panel.classList.remove("showing"); }, DURATION);

    // Move focus into the panel so keyboard users land inside it.
    var focusable = panel.querySelector(
      'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (focusable) { try { focusable.focus(); } catch (e) {} }

    if (trigger) trigger.setAttribute("aria-expanded", "true");
  }

  function closeOffcanvas() {
    // Close whatever is open, even if another script opened it — otherwise a
    // panel opened by kdcv-interaction-fix.js could never be dismissed once
    // Bootstrap's own JS was removed.
    var panel = openPanel || document.querySelector(".offcanvas.show");
    if (!panel) return;
    openPanel = null;

    panel.classList.remove("show");
    panel.setAttribute("aria-hidden", "true");
    panel.removeAttribute("aria-modal");

    if (backdrop) {
      var b = backdrop;
      backdrop = null;
      b.classList.remove("show");
      window.setTimeout(function () { if (b.parentNode) b.parentNode.removeChild(b); }, DURATION);
    }

    document.body.style.overflow = "";
    // Clear the artifacts the generated interaction-fix leaves behind, so the
    // panel is fully reset no matter which script opened it.
    document.body.classList.remove("offcanvas-open");
    panel.style.transform = "";
    panel.style.visibility = "";

    if (lastTrigger) {
      lastTrigger.setAttribute("aria-expanded", "false");
      try { lastTrigger.focus(); } catch (e) {}
      lastTrigger = null;
    }
  }

  /* ---- wiring ------------------------------------------------------------ */

  // Delegated, so panels injected later still work without re-binding.
  document.addEventListener("click", function (e) {
    var toggle = e.target.closest ? e.target.closest("[data-bs-toggle]") : null;
    if (toggle) {
      var kind = toggle.getAttribute("data-bs-toggle");
      var panel = targetOf(toggle);
      if (kind === "collapse" && panel) {
        e.preventDefault();
        toggleCollapse(panel);
        return;
      }
      if (kind === "offcanvas" && panel) {
        e.preventDefault();
        if (panel === openPanel) closeOffcanvas();
        else openOffcanvas(panel, toggle);
        return;
      }
    }

    var dismiss = e.target.closest ? e.target.closest('[data-bs-dismiss="offcanvas"]') : null;
    if (dismiss) {
      e.preventDefault();
      closeOffcanvas();
    }
  });

  document.addEventListener("keydown", function (e) {
    // Same reasoning as closeOffcanvas: the panel may have been opened by the
    // generated interaction-fix script, in which case `openPanel` is null and
    // gating on it would leave Escape dead.
    if (e.key !== "Escape") return;
    if (openPanel || document.querySelector(".offcanvas.show")) closeOffcanvas();
  });

  /* ADOPTION.
     kdcv-interaction-fix.js (generated by the tooling, so it cannot be edited
     here) opens the language panel itself by adding `.show` directly. When it
     wins the race we never created a backdrop, so clicking outside could not
     close the panel. Watching for `.show` lets us adopt a panel we did not
     open and still give it a backdrop and a scroll lock.

     Note this gap predates the Bootstrap removal: this build of Bootstrap
     needs jQuery's `$.Event`, which the jQuery shim does not provide, so
     `Offcanvas.show()` threw and the backdrop was never created there either. */
  function adopt() {
    var panel = document.querySelector(".offcanvas.show");
    if (!panel) return;
    if (panel === openPanel) return;

    openPanel = panel;
    if (!backdrop) {
      backdrop = makeBackdrop();
      document.body.style.overflow = "hidden";
    }
  }

  if ("MutationObserver" in window) {
    new MutationObserver(function () {
      // setTimeout, not rAF: rAF is paused while the tab is hidden.
      window.setTimeout(adopt, 0);
    }).observe(document.documentElement, {
      subtree: true, attributes: true, attributeFilter: ["class"]
    });
  }

  // Bootstrap sets the initial aria-expanded from the markup; mirror that so
  // a panel that starts open is reported correctly.
  function syncInitial() {
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (t) {
      var panel = targetOf(t);
      if (panel) {
        var open = isOpen(panel);
        t.classList.toggle("collapsed", !open);
        t.setAttribute("aria-expanded", open ? "true" : "false");
      }
    });
    document.querySelectorAll(".offcanvas").forEach(function (p) {
      if (!p.classList.contains("show")) p.setAttribute("aria-hidden", "true");
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", syncInitial, { once: true });
  } else {
    syncInitial();
  }
})();
