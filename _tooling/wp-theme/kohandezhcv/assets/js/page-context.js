/*!
 * page-context.js — tells the Kohan avatar what page it is standing on.
 *
 * Without this the avatar answers from generic site knowledge, so on a blog
 * post or the certificate archive it talks about the CV instead of what the
 * visitor is actually reading. This derives a compact description of the
 * CURRENT page from what is already in the markup — no per-page hand-authoring
 * to drift out of date — and publishes it as `window.KDCV_PAGE_CONTEXT`.
 *
 * Schema (documented in CLAUDE.md):
 *   {
 *     url:     string   canonical or current URL
 *     type:    string   "cv" | "blog-post" | "blog-index" | "certificates"
 *                       | "portfolio" | "psn" | "videos" | "legal" | "page"
 *     lang:    string   BCP-47 base code, e.g. "fa"
 *     title:   string   page heading
 *     summary: string   meta description or first paragraph
 *     topics:  string[]  headings / keywords, deduped, max 12
 *     published: string|null  ISO date for articles
 *   }
 *
 * A page may set `window.KDCV_PAGE_CONTEXT` itself before this loads; that
 * hand-written value always wins.
 */
(function () {
  "use strict";

  if (window.KDCV_PAGE_CONTEXT && window.KDCV_PAGE_CONTEXT.title) return;

  function meta(sel, attr) {
    var el = document.querySelector(sel);
    if (!el) return "";
    return (el.getAttribute(attr || "content") || "").trim();
  }

  function text(el) {
    return el ? (el.textContent || "").replace(/\s+/g, " ").trim() : "";
  }

  function detectType() {
    var p = window.location.pathname;
    if (/\/blog\/index\.html$|\/blog\/?$/i.test(p)) return "blog-index";
    if (/\/blog\//i.test(p)) return "blog-post";
    if (/Certificates\.html$/i.test(p)) return "certificates";
    if (/\/portfolio\//i.test(p)) return "portfolio";
    if (/PSN\.html$/i.test(p)) return "psn";
    if (/videos\.html$/i.test(p)) return "videos";
    if (/(privacy|terms)\.html$/i.test(p)) return "legal";
    if (/(index|fa|ar|de|es|fr|tr|zh|ja)\.html$|\/$/i.test(p)) return "cv";
    return "page";
  }

  function collectTopics() {
    var out = [];
    var seen = Object.create(null);

    function push(v) {
      v = (v || "").replace(/\s+/g, " ").trim();
      if (!v || v.length > 90) return;
      var k = v.toLowerCase();
      if (seen[k]) return;
      seen[k] = 1;
      out.push(v);
    }

    meta('meta[name="keywords"]').split(",").forEach(push);
    // Section headings describe the page far better than keywords alone.
    document.querySelectorAll("main h2, article h2, section h2").forEach(function (h) {
      push(text(h));
    });
    return out.slice(0, 12);
  }

  function firstParagraph() {
    var p = document.querySelector(
      ".blog-article-body p, .certificate-note-copy p, main p, article p"
    );
    var t = text(p);
    return t.length > 320 ? t.slice(0, 317) + "…" : t;
  }

  var h1 = document.querySelector("h1");

  window.KDCV_PAGE_CONTEXT = {
    url: (document.querySelector('link[rel="canonical"]') || {}).href || window.location.href,
    type: detectType(),
    lang: (document.documentElement.lang || "en").toLowerCase().split("-")[0],
    title: text(h1) || meta('meta[property="og:title"]') || document.title,
    summary: meta('meta[name="description"]') || firstParagraph(),
    topics: collectTopics(),
    published: meta('meta[property="article:published_time"]') || null
  };

  // Let the avatar (or anything else) react if it booted first.
  window.dispatchEvent(new CustomEvent("kdcv:page-context", {
    detail: window.KDCV_PAGE_CONTEXT
  }));

  /* Attach the context to the avatar's chat request.
   *
   * The chat lives in ai-pet.min.js, which ships minified with no source, so
   * the payload can't be extended at the call site. It makes exactly one
   * fetch, to window.KDCV_CONFIG.askUrl. This wrapper adds a `page` field to
   * THAT request only — every other request passes through untouched, and if
   * anything about the body is unexpected the original request is sent
   * unmodified rather than risking a broken call.
   */
  if (window.__KOHAN_FETCH_PATCHED__ || typeof window.fetch !== "function") return;
  window.__KOHAN_FETCH_PATCHED__ = true;

  var nativeFetch = window.fetch.bind(window);

  window.fetch = function (input, init) {
    try {
      var cfg = window.KDCV_CONFIG || {};
      var ask = cfg.askUrl;
      var url = typeof input === "string" ? input : (input && input.url) || "";
      if (ask && url && url.indexOf(ask) === 0 && init && typeof init.body === "string") {
        var payload = JSON.parse(init.body);
        if (payload && typeof payload === "object" && !payload.page) {
          payload.page = window.KDCV_PAGE_CONTEXT;
          // The chat keeps its own language, chosen in the panel's action row
          // and independent of the page locale (chat-ui.js).
          if (window.KDCV_CHAT_LANG) payload.lang = window.KDCV_CHAT_LANG;
          init = Object.assign({}, init, { body: JSON.stringify(payload) });
        }
      }
    } catch (e) {
      /* Body wasn't JSON, or config is absent — send the request as-is. */
    }
    return nativeFetch(input, init);
  };
})();
