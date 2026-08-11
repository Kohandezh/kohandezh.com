/*!
 * wisdom-quotes.js — context-aware wisdom quote shown in a bubble above the
 * avatar and mirrored into the avatar chat log.
 *
 * After the avatar boots, this selects a quote from the LOCAL JSON dataset —
 * weighted by category, filtered by page context, avoiding the last 5 shown —
 * presents it above the avatar, and mirrors it into the chat log. The chat panel
 * is never opened automatically; only a deliberate avatar/bubble click opens it.
 *
 * Self-contained vanilla JS. No external services. No jQuery. Fails silent.
 *
 * Public API:
 *   window.WisdomQuotes.start(opts)        — boot (idempotent; auto-called)
 *   window.WisdomQuotes.showNext()         — force a fresh quote into the chat
 *   window.WisdomQuotes.speakQuote(text)   — speak any text through the active pipeline
 */
(function () {
  "use strict";
  if (window.WisdomQuotes && window.WisdomQuotes.__booted) return;

  var DATA_BASE = (function () {
    var scripts = document.getElementsByTagName("script");
    var me = "";
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].src || "";
      if (src.indexOf("wisdom-quotes") !== -1) { me = src; break; }
    }
    var m = me.match(/^(.*\/)assets\/js\//);
    return m ? m[1] + "assets/data/" : "assets/data/";
  })();
  var LOCALE = (document.documentElement.getAttribute("lang") || "fa").toLowerCase().split("-")[0];
  var DATA_URL = DATA_BASE + "wisdom-quotes." + LOCALE + ".json";
  // Fall back to English, not Persian: a missing dataset must not drop another
  // language's page into Persian.
  var FALLBACK_DATA_URL = DATA_BASE + "wisdom-quotes.en.json";


  // The attribution connector and the speech locale must follow the PAGE
  // language. They used to be hardcoded Persian, so an English page rendered
  // "Saadi می‌گوید:" — a Persian verb inside an English sentence.
  var SAYS = {
    en: function (a) { return a + " says:"; },
    fa: function (a) { return a + " می\u200cگوید:"; },
    ar: function (a) { return a + " يقول:"; },
    de: function (a) { return a + " sagt:"; },
    es: function (a) { return a + " dice:"; },
    fr: function (a) { return a + " dit :"; },
    tr: function (a) { return a + " diyor ki:"; },
    zh: function (a) { return a + "\u8bf4\uff1a"; },
    ja: function (a) { return a + "\u306f\u8a00\u3046\uff1a"; }
  };
  var SPEECH_LOCALE = { en: "en-US", fa: "fa-IR", ar: "ar-SA", de: "de-DE", es: "es-ES", fr: "fr-FR", tr: "tr-TR", zh: "zh-CN", ja: "ja-JP" };
  function attribute(author) { return (SAYS[LOCALE] || SAYS.en)(author); }

  var HIST_KEY = "wisdom-quotes-history-v1";
  var SESSION_KEY = "wisdom-quotes-shown-session-v1";
  var RECENT_LIMIT = 5;
  var HISTORY_LIMIT = 5;

  // page-topic → category mapping (single source of truth)
  var CONTEXT_MAP = [
    { match: /\/?(certificates?)/i, cats: ["science_engineering", "management_entrepreneurship"] },
    { match: /\/?(psn|trophy)/i, cats: ["management_entrepreneurship", "stoicism"] },
    { match: /\/?(portfolio|work|projects?)/i, cats: ["management_entrepreneurship", "science_engineering"] },
    { match: /\/?blog\//i, cats: ["iranian_wisdom", "psychology_human_behavior", "stoicism"] },
    { match: /\/?(ai|cyber|security|infra|tech|service)/i, cats: ["science_engineering", "management_entrepreneurship"] }
  ];

  var dataset = null, loadingPromise = null, booted = false;

  function ssGet(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function ssSet(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }

  function secureRandom() {
    try {
      if (window.crypto && window.crypto.getRandomValues) {
        var buf = new Uint32Array(1); window.crypto.getRandomValues(buf);
        return buf[0] / 4294967296;
      }
    } catch (e) {}
    return Math.random();
  }

  function detectCategories() {
    var path = (location.pathname || "") + " " + (document.body.dataset.pageContext || "");
    for (var i = 0; i < CONTEXT_MAP.length; i++) if (CONTEXT_MAP[i].match.test(path)) return CONTEXT_MAP[i].cats;
    return null; // all categories by weight
  }

  function validate(raw) {
    if (!raw || typeof raw !== "object" || !Array.isArray(raw.categories)) throw new Error("invalid");
    var cats = [];
    for (var i = 0; i < raw.categories.length; i++) {
      var c = raw.categories[i];
      if (!c || !c.id || !Array.isArray(c.quotes)) continue;
      var weight = typeof c.weight === "number" ? c.weight : typeof c.weight_percent === "number" ? c.weight_percent : 0;
      var qs = [];
      for (var j = 0; j < c.quotes.length; j++) {
        var q = c.quotes[j];
        if (!q || q.verified !== true || q.enabled === false) continue;
        if (!q.id || !q.text || !q.author) continue;
        qs.push({ id: String(q.id), author: String(q.author), text: String(q.text), source: q.source ? String(q.source) : "", priority: typeof q.priority === "number" ? q.priority : 5 });
      }
      if (qs.length) cats.push({ id: c.id, weight: weight, quotes: qs });
    }
    return { categories: cats };
  }

  function loadDataset() {
    if (dataset) return Promise.resolve(dataset);
    if (loadingPromise) return loadingPromise;
    function read(url) {
      return fetch(url, { credentials: "same-origin" })
        .then(function (r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
        .then(validate);
    }
    loadingPromise = read(DATA_URL)
      .catch(function () { return DATA_URL === FALLBACK_DATA_URL ? Promise.reject(new Error("fallback unavailable")) : read(FALLBACK_DATA_URL); })
      .then(function (d) { dataset = d; return d; })
      .catch(function (e) { if (console.warn) console.warn("[wisdom-quotes] load failed:", e.message); dataset = { categories: [] }; return dataset; });
    return loadingPromise;
  }

  function getHistory() { try { return JSON.parse(ssGet(HIST_KEY) || "[]"); } catch (e) { return []; } }
  function pushHistory(id) { var h = getHistory(); h.push(id); while (h.length > HISTORY_LIMIT) h.shift(); ssSet(HIST_KEY, JSON.stringify(h)); }

  function pickWeighted(items, w) {
    var total = 0; for (var i = 0; i < items.length; i++) total += Math.max(0, w(items[i]) || 0);
    if (total <= 0) return items[Math.floor(secureRandom() * items.length)];
    var roll = secureRandom() * total, acc = 0;
    for (var j = 0; j < items.length; j++) { acc += Math.max(0, w(items[j]) || 0); if (roll <= acc) return items[j]; }
    return items[items.length - 1];
  }

  var _catMap = null;
  function catOf(qid) {
    if (!_catMap && dataset) { _catMap = {}; dataset.categories.forEach(function (c) { c.quotes.forEach(function (q) { _catMap[q.id] = c.id; }); }); }
    return _catMap ? _catMap[qid] : null;
  }

  function selectQuote() {
    if (!dataset || !dataset.categories.length) return null;
    var allowed = detectCategories();
    var pool = allowed ? dataset.categories.filter(function (c) { return allowed.indexOf(c.id) !== -1; }) : dataset.categories;
    if (!pool.length) pool = dataset.categories;
    var cand = [];
    pool.forEach(function (c) { c.quotes.forEach(function (q) { cand.push({ q: q, cw: c.weight }); }); });
    if (!cand.length) return null;
    var hist = getHistory();
    var fresh = cand.filter(function (c) { return hist.indexOf(c.q.id) === -1; });
    var use = (cand.length >= (RECENT_LIMIT + 1) && fresh.length) ? fresh : cand;
    // weighted category, then weighted-by-priority quote within
    var cw = {}; use.forEach(function (c) { cw[catOf(c.q.id)] = (cw[catOf(c.q.id)] || 0) + Math.max(1, c.cw); });
    var catEntries = Object.keys(cw).map(function (k) { return { id: k, w: cw[k] }; });
    var chosen = pickWeighted(catEntries.length ? catEntries : [{ id: catOf(use[0].q.id), w: 1 }], function (e) { return e.w; });
    var inCat = use.filter(function (c) { return catOf(c.q.id) === chosen.id; });
    var pick = pickWeighted(inCat.length ? inCat : use, function (c) { return Math.max(1, c.q.priority); });
    return pick ? pick.q : null;
  }

  function formatText(q) {
    // Multi-line message for the chat bubble: author, then quoted text, then source.
    var lines = [attribute(q.author), "«" + q.text + "»"];
    if (q.source) lines.push(q.source);
    return lines.join("\n");
  }

  function reducedMotion() { try { return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches; } catch (e) { return false; } }

  /* The avatar panel owns a voice toggle (the speaker button in its header,
     aria-pressed="true" when sound is ON). Speech used to bypass it entirely,
     so the quote was read aloud even with the speaker muted — including when
     a right-click on the avatar triggered a new quote. Every speech path now
     goes through this gate. Absent toggle = treat as muted: never surprise a
     visitor with audio they did not ask for. */
  function voiceEnabled() {
    var btn = document.querySelector('.kdcv-pet-panel-header .kdcv-pet-icon-button[aria-pressed]');
    if (!btn) btn = document.querySelector('.kdcv-pet-icon-button[aria-pressed]');
    if (!btn) return false;
    return btn.getAttribute("aria-pressed") === "true";
  }

  function speakQuote(text) {
    if (!voiceEnabled()) return;

    try {
      if (!text || reducedMotion()) return;
      var vcfg = window.KDCV_VOICE || {};
      if (vcfg.provider && vcfg.provider !== "webspeech" && vcfg.endpoint && window.fetch) {
        fetch(vcfg.endpoint, { method: "POST", headers: { "Content-Type": "application/json" }, credentials: "same-origin", body: JSON.stringify({ text: text, locale: SPEECH_LOCALE[LOCALE] || "en-US", voice: vcfg.voice || "" }) })
          .then(function (r) { return r.json(); }).then(function (d) { if (d && d.audio && window.Audio) { try { window.speechSynthesis && window.speechSynthesis.cancel(); new window.Audio(d.audio).play().catch(function () {}); } catch (e) {} } }).catch(function () {});
        return;
      }
      if (!("speechSynthesis" in window) || typeof window.SpeechSynthesisUtterance !== "function") return;
      window.speechSynthesis.cancel();
      var u = new window.SpeechSynthesisUtterance(text); u.lang = SPEECH_LOCALE[LOCALE] || "en-US"; u.rate = 0.95; u.pitch = 0.95;
      var vs = window.speechSynthesis.getVoices() || [];
      var fa = vs.filter(function (v) { return String(v.lang || "").toLowerCase().indexOf("fa") === 0; });
      if (fa.length) u.voice = fa[0];
      window.speechSynthesis.speak(u);
    } catch (e) {}
  }

  function quoteText(q) { return formatText(q); }

  // Append the quote as a chat message, without opening the panel.
  function showQuoteInChat(q) {
    if (!q) return false;
    var log = document.getElementById("kdcv-pet-log");
    if (!log) return false;
    var message = quoteText(q);
    var previous = log.querySelector('[data-wisdom-id="' + String(q.id).replace(/"/g, "\\\"") + '"]');
    if (previous) return true;
    var row = document.createElement("div");
    row.className = "kdcv-pet-message kdcv-pet-message-assistant kdcv-wisdom-quote";
    row.setAttribute("data-wisdom-id", q.id);
    var bubble = document.createElement("div");
    bubble.className = "kdcv-pet-message-bubble";
    // preserve line breaks (author / quote / source)
    bubble.style.whiteSpace = "pre-line";
    bubble.textContent = message; // textContent → safe, no HTML injection
    row.appendChild(bubble);
    log.appendChild(row);
    while (log.children.length > 50) log.removeChild(log.firstElementChild);
    try { log.scrollTop = log.scrollHeight; } catch (e) {}
    return true;
  }

  /* Floating quote bubble. It is a real button so keyboard and touch users can
     activate it; activation opens the chat and leaves the same text in the log. */
  function showQuoteBubble(q) {
    if (!q) return false;
    var root = document.getElementById("kdcv-pet-root");
    if (!root) return false;
    var bubble = document.getElementById("kdcv-pet-wisdom-bubble");
    if (!bubble) {
      bubble = document.createElement("button");
      bubble.type = "button";
      bubble.id = "kdcv-pet-wisdom-bubble";
      bubble.className = "kdcv-wisdom-bubble";
      bubble.setAttribute("aria-label", "Open the avatar chat");
      root.insertBefore(bubble, root.firstChild);
      bubble.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        var text = bubble.getAttribute("data-message") || bubble.textContent.trim();
        if (window.KDCVPet && typeof window.KDCVPet.open === "function") window.KDCVPet.open();
        window.setTimeout(function () {
          var log = document.getElementById("kdcv-pet-log");
          if (!log || !text) return;
          var duplicate = Array.prototype.some.call(log.querySelectorAll("[data-kdcv-relayed]"), function (row) {
            return row.getAttribute("data-kdcv-relayed") === text;
          });
          if (duplicate) return;
          var row = document.createElement("div");
          row.className = "kdcv-pet-message kdcv-pet-message-assistant kdcv-wisdom-quote";
          row.setAttribute("data-kdcv-relayed", text);
          var message = document.createElement("div");
          message.className = "kdcv-pet-message-bubble";
          message.style.whiteSpace = "pre-line";
          message.textContent = text;
          row.appendChild(message);
          log.appendChild(row);
          log.scrollTop = log.scrollHeight;
        }, 80);
      });
    }
    bubble.setAttribute("data-message", quoteText(q));
    bubble.textContent = quoteText(q);
    bubble.hidden = false;
    bubble.setAttribute("aria-hidden", "false");
    return true;
  }

  function presentNew() {
    return loadDataset().then(function () {
      var q = selectQuote();
      if (!q) return null;
      pushHistory(q.id);
      var shown = showQuoteInChat(q) && showQuoteBubble(q);
      if (shown) speakQuote(attribute(q.author) + " " + q.text);
      return q;
    });
  }

  function waitFor(selector, cb, timeout) {
    if (document.querySelector(selector)) { cb(); return; }
    var obs = new MutationObserver(function () { if (document.querySelector(selector)) { obs.disconnect(); cb(); } });
    obs.observe(document.body, { childList: true, subtree: true });
    setTimeout(function () { obs.disconnect(); cb(); }, timeout || 8000);
  }

  function start(opts) {
    if (booted) return;
    booted = true;
    window.WisdomQuotes.__booted = true;
    opts = opts || {};
    document.addEventListener("visibilitychange", function () { if (document.hidden && "speechSynthesis" in window) window.speechSynthesis.cancel(); });
    // Wait for the avatar's chat log to exist, then show a fresh quote on each
    // page load. History still prevents the last five quotes repeating.
    waitFor("#kdcv-pet-log", function () {
      ssSet(SESSION_KEY, "1");
      setTimeout(presentNew, opts.delay || 1400);
    }, 12000);
  }

  window.WisdomQuotes = {
    __booted: false,
    start: start,
    showNext: presentNew,
    speakQuote: speakQuote,
    getConfig: function () { return { dataset: dataset, contextCategories: detectCategories() }; }
  };

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", function () { start(); });
  else start();
})();
