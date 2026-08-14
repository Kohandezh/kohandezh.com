/*!
 * clock.js — self-contained local date/time for every page.
 *
 * No external service, no network, no timezone database: `Intl.DateTimeFormat`
 * is built into the browser and already knows the visitor's own timezone and
 * calendar. A fetch to a time API would be slower, would leak the visit, and
 * would break offline — the device clock is the right source here.
 *
 * Formats in the PAGE's language (Persian pages get the Solar Hijri calendar
 * via fa-IR, Japanese gets 2026年8月2日, etc). main.js used to render this with
 * a hardcoded en-GB / en-US format, so every non-English page showed an English
 * date; that implementation stands down when this file is present.
 *
 * Markup contract (already present on the CV pages):
 *   <div class="time-local"><p class="date"></p><p class="clock"></p></div>
 */
(function () {
  "use strict";

  // Tell main.js's legacy clock to stand down — see gate in its p() helper.
  window.__KDCV_CLOCK_ACTIVE__ = true;

  var LOCALES = {
    en: "en-US", fa: "fa-IR", ar: "ar-EG", de: "de-DE", es: "es-ES",
    fr: "fr-FR", tr: "tr-TR", ja: "ja-JP", zh: "zh-CN", ru: "ru-RU"
  };

  function locale() {
    var raw = (document.documentElement.getAttribute("lang") || "en").toLowerCase();
    return LOCALES[raw.split("-")[0]] || "en-US";
  }

  function start() {
    var nodes = document.querySelectorAll(".time-local");
    if (!nodes.length) return;

    var loc = locale();
    var dateFmt, timeFmt;
    try {
      dateFmt = new Intl.DateTimeFormat(loc, { weekday: "short", month: "short", day: "numeric" });
      // hour12:false keeps the readout compact and unambiguous across locales.
      timeFmt = new Intl.DateTimeFormat(loc, { hour: "2-digit", minute: "2-digit", hour12: false });
    } catch (e) {
      return; // Leave the markup empty rather than render something wrong.
    }

    function tick() {
      var now = new Date();
      var d = dateFmt.format(now);
      var t = timeFmt.format(now);
      for (var i = 0; i < nodes.length; i++) {
        var dEl = nodes[i].querySelector(".date");
        var tEl = nodes[i].querySelector(".clock");
        if (dEl && dEl.textContent !== d) dEl.textContent = d;
        if (tEl && tEl.textContent !== t) tEl.textContent = t;
      }
    }

    tick();
    // Align to the next minute boundary, then tick once a minute: the readout
    // has no seconds, so a 1s interval would be 60x the wakeups for no gain.
    //
    // start() can be called again when the page language changes, so the old
    // timers are cleared first — otherwise each call would leave another
    // interval running against a stale formatter.
    if (timers.boundary) window.clearTimeout(timers.boundary);
    if (timers.minute) window.clearInterval(timers.minute);
    timers.boundary = window.setTimeout(function () {
      tick();
      timers.minute = window.setInterval(tick, 60000);
    }, (60 - new Date().getSeconds()) * 1000);
  }

  var timers = {};

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });
  } else {
    start();
  }

  /* The formatters are built ONCE from documentElement.lang, but page-i18n.js
     translates the standalone pages after this module has already run — so the
     clock kept rendering the page's authored locale ("یکشنبه ۱۸ مرداد") next to
     a fully English page. Rebuilding on that event re-reads the language.
     `started` is reset so start() re-runs its setup rather than bailing. */
  window.addEventListener("kdcv:page-i18n", function () {
    start();
  });
})();
