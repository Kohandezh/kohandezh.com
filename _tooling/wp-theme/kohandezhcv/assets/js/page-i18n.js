/*!
 * page-i18n.js — translates the single-language standalone pages in place.
 *
 * WHY THIS EXISTS
 * privacy, terms, PSN, videos and the blog index were each authored in ONE
 * language (Persian, except PSN which is English). Every other page follows the
 * visitor's language, so walking through the menu changed language halfway —
 * the exact complaint this fixes. Certificates and portfolio already translated
 * themselves; this brings the rest to the same behaviour with one mechanism
 * instead of a bespoke script per page.
 *
 * HOW IT WORKS
 *   1. The page declares itself:  <html data-kdcv-i18n="in-place"
 *                                       data-kdcv-i18n-page="privacy"
 *                                       data-kdcv-i18n-source="fa">
 *   2. Every translatable LEAF block carries data-i18n="k01" … Leaf means it
 *      contains no other block, so the value is the element's innerHTML and
 *      inline <strong>/<code>/<a> survive translation without a key each.
 *   3. Strings live in assets/data/i18n/<page>.json as { locale: { key: html } }
 *      — fetched only when the wanted locale differs from the source, so a
 *      Persian reader on a Persian page costs no request at all.
 *
 * The dictionary is data, not markup, so adding a language is a JSON edit.
 */
(function () {
  "use strict";

  if (window.__KDCV_PAGE_I18N__) return;
  window.__KDCV_PAGE_I18N__ = true;

  var root = document.documentElement;
  if (root.getAttribute("data-kdcv-i18n") !== "in-place") return;

  var PAGE = root.getAttribute("data-kdcv-i18n-page");
  if (!PAGE) return;

  var SOURCE = (root.getAttribute("data-kdcv-i18n-source") || "fa").toLowerCase();
  var SUPPORTED = ["en", "fa", "ar", "de", "es", "fr", "tr", "zh", "ja"];
  var RTL = { fa: 1, ar: 1 };

  function known(code) {
    return SUPPORTED.indexOf(code) > -1;
  }

  /* Same order as certificates-i18n.js and page-chrome.js, so every locale
     decision on the site agrees: explicit ?lang, then the language the visitor
     was last reading, then whatever this page was authored in. */
  function wanted() {
    try {
      var q = new URLSearchParams(window.location.search).get("lang");
      if (q && known(q.toLowerCase())) return q.toLowerCase();
    } catch (e) {}
    try {
      var stored = (window.localStorage.getItem("siteLang") || "").toLowerCase();
      if (known(stored)) return stored;
    } catch (e) {}
    return SOURCE;
  }

  function base() {
    // blog/ and portfolio/ sit one level down.
    var wp = window.KDCV_WP && window.KDCV_WP.assets;
    if (wp) return wp;
    return (/\/(blog|portfolio)\//.test(window.location.pathname) ? "../" : "") + "assets/";
  }

  function setDir(loc) {
    root.setAttribute("lang", loc);
    root.setAttribute("dir", RTL[loc] ? "rtl" : "ltr");
  }

  function apply(dict, loc) {
    var missing = 0;
    var nodes = document.querySelectorAll("[data-i18n]");
    for (var i = 0; i < nodes.length; i++) {
      var key = nodes[i].getAttribute("data-i18n");
      var value = dict[key];
      if (typeof value !== "string") { missing++; continue; }
      nodes[i].innerHTML = value;
    }

    /* Attributes, not just content. A translated post still announced
       "بازگشت به صفحه اصلی محمدعلی کهن‌دژ" to a screen reader because the
       label lives in an attribute, which innerHTML never touches. Nodes opt in
       with data-i18n-aria="key" (aria-label) or data-i18n-title="key". */
    var attrs = [["data-i18n-aria", "aria-label"], ["data-i18n-title", "title"],
                 ["data-i18n-alt", "alt"]];
    for (var a = 0; a < attrs.length; a++) {
      var an = document.querySelectorAll("[" + attrs[a][0] + "]");
      for (var b = 0; b < an.length; b++) {
        var av = dict[an[b].getAttribute(attrs[a][0])];
        if (typeof av === "string") an[b].setAttribute(attrs[a][1], av);
      }
    }

    /* The Persian legal pages carry a trailing "English summary" section that
       only existed BECAUSE the page had no translation. Now that the body is
       rendered in the reader's own language it is redundant in every locale,
       so it is removed rather than shown twice. */
    var extra = document.querySelectorAll("[data-kdcv-i18n-drop]");
    for (var j = 0; j < extra.length; j++) extra[j].remove();

    /* Title and description are part of the page's language too — leaving them
       in the source language means a German reader's browser tab, bookmark and
       search snippet all still say Persian. */
    // Any node that only makes sense once translated (e.g. the blog's
    // "articles are in Persian" note) ships hidden and is revealed here.
    var reveal = document.querySelectorAll("[data-i18n][hidden]");
    for (var r = 0; r < reveal.length; r++) {
      if (typeof dict[reveal[r].getAttribute("data-i18n")] === "string") {
        reveal[r].removeAttribute("hidden");
      }
    }

    if (dict.__title) document.title = dict.__title;
    if (dict.__desc) {
      var meta = document.querySelector('meta[name="description"]');
      if (meta) meta.setAttribute("content", dict.__desc);
    }

    setDir(loc);
    document.body.setAttribute("data-kdcv-locale", loc);
    if (missing) {
      // Loud in the console, silent for the visitor: a partially translated
      // page still reads, and the gap is a data fix, not a code fix.
      if (window.console && window.console.warn) {
        window.console.warn("[page-i18n] " + PAGE + "/" + loc + ": " + missing + " key(s) missing");
      }
    }
    window.dispatchEvent(new CustomEvent("kdcv:page-i18n", { detail: { page: PAGE, lang: loc } }));
  }

  function run() {
    var loc = wanted();

    // Already in the right language: the markup IS the source. Still normalise
    // lang/dir so the chat, dates and chrome all read the same value.
    if (loc === SOURCE) {
      setDir(SOURCE);
      document.body.setAttribute("data-kdcv-locale", SOURCE);
      window.dispatchEvent(new CustomEvent("kdcv:page-i18n", { detail: { page: PAGE, lang: SOURCE } }));
      return;
    }

    fetch(base() + "data/i18n/" + PAGE + ".json", { credentials: "same-origin" })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (all) {
        if (!all || !all[loc]) return;   // no dictionary for this locale: leave the source text
        apply(all[loc], loc);
      })
      .catch(function () { /* offline or blocked: the source language still reads */ });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run, { once: true });
  } else {
    run();
  }
})();
