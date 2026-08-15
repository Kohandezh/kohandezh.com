/*!
 * knowledge-tabs.js — the AI / Quantum switcher on the Knowledge page.
 *
 * A real ARIA tablist rather than two anchors: roving tabindex, arrow-key
 * navigation, Home/End, and aria-selected kept in sync so a screen reader
 * announces "2 of 2" instead of reading both trees back to back.
 *
 * The panel that is not shown carries the plain `hidden` attribute. NOTE for
 * anyone extending this: page-i18n.js reveals every `[data-i18n][hidden]`
 * node once it finds a translation for it, so the panel elements must never
 * take a data-i18n key of their own — only their children do. Otherwise a
 * language switch would silently un-hide both trees at once.
 *
 * The chosen tab is reflected in the URL hash (#ai / #quantum) so a specific
 * tree can be linked to directly — useful when the link is going into a
 * proposal — and restored on load.
 */
(function () {
  "use strict";

  var list = document.querySelector(".kh-tabs");
  if (!list) return;

  var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
  if (tabs.length < 2) return;

  var HASH = { "kh-tab-ai": "#ai", "kh-tab-q": "#quantum" };

  function panelOf(tab) {
    return document.getElementById(tab.getAttribute("aria-controls"));
  }

  function select(tab, opts) {
    opts = opts || {};
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute("aria-selected", on ? "true" : "false");
      // Roving tabindex: only the active tab is in the tab order, so Tab moves
      // PAST the tablist rather than through every tab in it.
      t.setAttribute("tabindex", on ? "0" : "-1");
      var p = panelOf(t);
      if (p) {
        if (on) { p.removeAttribute("hidden"); }
        else { p.setAttribute("hidden", ""); }
      }
    });

    if (opts.focus) tab.focus();

    if (opts.hash !== false && HASH[tab.id]) {
      try {
        // replaceState, not a hash assignment: changing location.hash would
        // scroll the panel under the sticky header on every switch.
        window.history.replaceState(null, "", HASH[tab.id]);
      } catch (e) {}
    }
  }

  tabs.forEach(function (tab) {
    tab.addEventListener("click", function () { select(tab, { focus: false }); });

    tab.addEventListener("keydown", function (e) {
      var i = tabs.indexOf(tab);
      var next = null;
      // Arrow direction follows the writing direction, so the Persian and
      // Arabic pages step through the tabs the way they read.
      var rtl = (document.documentElement.getAttribute("dir") || "ltr") === "rtl";
      var fwd = rtl ? "ArrowLeft" : "ArrowRight";
      var back = rtl ? "ArrowRight" : "ArrowLeft";

      if (e.key === fwd || e.key === "ArrowDown") next = tabs[(i + 1) % tabs.length];
      else if (e.key === back || e.key === "ArrowUp") next = tabs[(i - 1 + tabs.length) % tabs.length];
      else if (e.key === "Home") next = tabs[0];
      else if (e.key === "End") next = tabs[tabs.length - 1];
      else return;

      e.preventDefault();
      select(next, { focus: true });
    });
  });

  // Deep link: #quantum opens the quantum tree on load.
  var want = (window.location.hash || "").toLowerCase();
  var initial = tabs[0];
  tabs.forEach(function (t) {
    if (HASH[t.id] && HASH[t.id] === want) initial = t;
  });
  select(initial, { focus: false, hash: false });
})();
