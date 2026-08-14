/*!
 * blog-search.js — client-side filtering for the blog archive.
 *
 * The archive is a static list of ~10 posts, so filtering the DOM directly is
 * both simpler and faster than shipping an index. No network, works offline.
 *
 * Persian text needs normalising before it can be matched reliably: the same
 * word can be typed with Arabic ي/ك or Persian ی/ک, with or without ZWNJ, and
 * numbers may be Persian, Arabic-Indic or Latin. Without folding these the
 * search silently fails on input that looks identical to the user.
 */
(function () {
  "use strict";

  var PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹";
  var ARABIC_DIGITS = "٠١٢٣٤٥٦٧٨٩";

  function normalize(value) {
    if (!value) return "";
    var s = String(value).toLowerCase();
    var out = "";
    for (var i = 0; i < s.length; i++) {
      var ch = s[i];
      var pd = PERSIAN_DIGITS.indexOf(ch);
      var ad = ARABIC_DIGITS.indexOf(ch);
      if (pd > -1) { out += pd; continue; }
      if (ad > -1) { out += ad; continue; }
      if (ch === "ي" || ch === "ی") { out += "ی"; continue; }
      if (ch === "ك") { out += "ک"; continue; }
      if (ch === "ۀ" || ch === "ة") { out += "ه"; continue; }
      if (ch === "أ" || ch === "إ" || ch === "آ" || ch === "ا") { out += "ا"; continue; }
      if (ch === "‌") { out += " "; continue; }   // ZWNJ -> space
      if (ch >= "ً" && ch <= "ْ") { continue; } // strip diacritics
      out += ch;
    }
    return out.replace(/\s+/g, " ").trim();
  }

  function init() {
    var input = document.getElementById("blog-search-input");
    var grid = document.querySelector(".blog-grid");
    if (!input || !grid) return;

    var status = document.getElementById("blog-search-status");
    var empty = document.getElementById("blog-search-empty");
    var clear = document.getElementById("blog-search-clear");

    var cards = [].slice.call(grid.querySelectorAll(".blog-card")).map(function (el) {
      var title = el.querySelector("h3");
      var summary = el.querySelector("p");
      var kicker = el.querySelector(".blog-card-kicker");
      return {
        el: el,
        haystack: normalize(
          [title && title.textContent,
           summary && summary.textContent,
           kicker && kicker.textContent].join(" ")
        )
      };
    });

    /* The two status strings used to come only from the markup's
       data-label-* attributes, which are authored in Persian — so on a page
       translated in place by page-i18n the live region still announced
       "۹ نوشته پیدا شد" under fully English copy. They are now resolved from
       the page's CURRENT language, with the attributes kept as the Persian
       source and the fallback. */
    var LABELS = {
      en: ["posts found", "No results found"],
      fa: ["نوشته پیدا شد", "نتیجه‌ای پیدا نشد"],
      ar: ["مقالة تم العثور عليها", "لا توجد نتائج"],
      de: ["Beiträge gefunden", "Keine Ergebnisse gefunden"],
      es: ["publicaciones encontradas", "No se encontraron resultados"],
      fr: ["articles trouvés", "Aucun résultat trouvé"],
      tr: ["yazı bulundu", "Sonuç bulunamadı"],
      zh: ["篇文章", "未找到结果"],
      ja: ["件の記事", "結果が見つかりません"],
      ru: ["записей найдено", "Ничего не найдено"]
    };

    function labels() {
      var lang = (document.documentElement.lang || "fa").toLowerCase().split("-")[0];
      if (lang === "fa" || !LABELS[lang]) {
        return [input.getAttribute("data-label-total") || LABELS.fa[0],
                input.getAttribute("data-label-none") || LABELS.fa[1]];
      }
      return LABELS[lang];
    }

    function apply() {
      var q = normalize(input.value);
      var shown = 0;

      for (var i = 0; i < cards.length; i++) {
        var match = !q || cards[i].haystack.indexOf(q) > -1;
        cards[i].el.hidden = !match;
        if (match) shown++;
      }

      if (empty) empty.hidden = shown !== 0;
      if (clear) clear.hidden = !input.value;

      if (status) {
        var lab = labels();
        status.textContent = shown === 0 ? lab[1] : shown + " " + lab[0];
      }
    }

    // Filtering ~10 nodes is cheap, but debouncing keeps the aria-live region
    // from firing on every keystroke, which screen readers announce verbatim.
    var timer = null;
    function schedule() {
      if (timer) clearTimeout(timer);
      timer = setTimeout(apply, 120);
    }

    // page-i18n translates this page after this module has run.
    window.addEventListener("kdcv:page-i18n", apply);

    input.addEventListener("input", schedule);
    input.addEventListener("search", apply);
    input.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && input.value) {
        input.value = "";
        apply();
      }
    });

    if (clear) {
      clear.addEventListener("click", function () {
        input.value = "";
        apply();
        input.focus();
      });
    }

    apply();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
