/**
 * Employer-preview enhancement for the career timeline.
 *
 * ─── WHAT THIS NO LONGER DOES ──────────────────────────────────────────────
 *
 * Until Phase 7 this script deleted every static `.timeline-item` and rebuilt
 * the whole career history from a JavaScript array. That made the rendered CV
 * and the crawlable CV two different documents: seven employers existed only
 * here, two current roles existed only in the HTML and were destroyed on load,
 * and four job titles contradicted between them.
 *
 * The career data now lives in assets/data/cv.json and is rendered into the
 * static HTML by _tooling/cv/build.py. This file only ENHANCES what is already
 * on the page: it attaches a hover/focus preview of the verified employment
 * reference. With JavaScript off, the CV is unchanged and complete.
 *
 * Items are matched by `data-cv-id`, not by position, so reordering the
 * dataset cannot attach an employer's logo to somebody else's job.
 */
(function () {
  "use strict";

  var raw = (document.documentElement.lang || "en").toLowerCase();
  var LOCALES = ["fa", "ar", "de", "es", "fr", "tr", "zh", "ja", "ru"];
  var lang = "en";
  for (var li = 0; li < LOCALES.length; li++) {
    if (raw.indexOf(LOCALES[li]) === 0) { lang = LOCALES[li]; break; }
  }

  var LABELS = {
  "en": {
    "hint": "Hover or focus to preview employer",
    "verified": "Verified employment evidence",
    "source": "Authentic logo extracted from the supplied ACS employment reference",
    "close": "Close employer preview",
    "imageAlt": "Employer logo from the verified employment reference for"
  },
  "fa": {
    "hint": "برای نمایش کارفرما، نشانگر را نگه دارید",
    "verified": "سابقه شغلی مستند",
    "source": "لوگوی واقعی استخراج‌شده از مدرک سابقه کار ارائه‌شده برای ACS",
    "close": "بستن پیش‌نمایش کارفرما",
    "imageAlt": "لوگوی کارفرما از مدرک معتبر سابقه کار مربوط به"
  },
  "ar": {
    "hint": "مرّر المؤشر أو ركّز لمعاينة جهة العمل",
    "verified": "دليل توظيف موثّق",
    "source": "شعار أصلي مستخرج من خطاب التوظيف المعتمد (ACS)",
    "close": "إغلاق معاينة جهة العمل",
    "imageAlt": "شعار جهة العمل من خطاب التوظيف الموثّق لـ"
  },
  "de": {
    "hint": "Zum Vorschauen des Arbeitgebers bewegen oder fokussieren",
    "verified": "Geprüfter Beschäftigungsnachweis",
    "source": "Originallogo aus dem eingereichten ACS-Arbeitszeugnis",
    "close": "Arbeitgebervorschau schließen",
    "imageAlt": "Arbeitgeberlogo aus dem geprüften Arbeitszeugnis für"
  },
  "es": {
    "hint": "Pase el cursor o enfoque para previsualizar el empleador",
    "verified": "Prueba de empleo verificada",
    "source": "Logotipo auténtico extraído de la referencia laboral ACS aportada",
    "close": "Cerrar la vista previa del empleador",
    "imageAlt": "Logotipo del empleador de la referencia laboral verificada de"
  },
  "fr": {
    "hint": "Survolez ou activez pour prévisualiser l’employeur",
    "verified": "Preuve d’emploi vérifiée",
    "source": "Logo authentique extrait de l’attestation d’emploi ACS fournie",
    "close": "Fermer l’aperçu de l’employeur",
    "imageAlt": "Logo de l’employeur issu de l’attestation d’emploi vérifiée de"
  },
  "tr": {
    "hint": "İşvereni önizlemek için üzerine gelin veya odaklanın",
    "verified": "Doğrulanmış istihdam kanıtı",
    "source": "Sunulan ACS istihdam referansından alınan özgün logo",
    "close": "İşveren önizlemesini kapat",
    "imageAlt": "Şu iş yerinin doğrulanmış istihdam referansındaki işveren logosu:"
  },
  "zh": {
    "hint": "悬停或聚焦以预览雇主",
    "verified": "已核实的雇佣证明",
    "source": "取自所提交 ACS 雇佣证明的原始标识",
    "close": "关闭雇主预览",
    "imageAlt": "来自以下单位已核实雇佣证明的雇主标识："
  },
  "ja": {
    "hint": "ホバーまたはフォーカスで雇用主をプレビュー",
    "verified": "確認済みの在職証明",
    "source": "提出されたACS在職証明書から抽出した正規のロゴ",
    "close": "雇用主プレビューを閉じる",
    "imageAlt": "次の企業の確認済み在職証明に含まれる雇用主ロゴ："
  },
  "ru": {
    "hint": "Наведите курсор или установите фокус, чтобы просмотреть работодателя",
    "verified": "Проверенные сведения о трудоустройстве",
    "source": "Подлинный логотип, извлечённый из предоставленной справки ACS о трудоустройстве",
    "close": "Закрыть предпросмотр работодателя",
    "imageAlt": "Логотип работодателя из проверенной справки о трудоустройстве для"
  }
};
  var LOGOS = {
  "modaberan": "modaberan",
  "haft-tappeh": "haft-tappeh",
  "golzar-ettesal": "golzar-ettesal",
  "power-control": "power-control",
  "negareh": "negareh",
  "novin-gostar-aysa": "novin-gostar-aysa",
  "culham": "culham",
  "amen-behin": "amen-behin"
};

  var t = LABELS[lang] || LABELS.en;

  var base = (function () {
    if (window.KOHANDEZH_THEME_URI) return window.KOHANDEZH_THEME_URI.replace(/\/$/, "") + "/";
    var c = window.KDCV_CONFIG || {};
    if (typeof c.assetBase === "string" && c.assetBase) return c.assetBase.replace(/\/$/, "") + "/";
    var s = document.getElementsByTagName("script");
    for (var i = 0; i < s.length; i++) {
      var m = (s[i].src || "").match(/^(.*\/)assets\/js\/resume-timeline(?:\.min)?\.js/);
      if (m) return m[1];
    }
    return "";
  })();

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (typeof text === "string") n.textContent = text;
    return n;
  }

  function init() {
    var section = document.querySelector("#education");
    if (!section) return;

    var items = section.querySelectorAll(".timeline-item[data-cv-id]");
    if (!items.length) return;

    // Old runtime artefacts, if a cached copy of the previous script ran first.
    Array.prototype.slice.call(section.querySelectorAll(".employment-preview")).forEach(function (n) { n.remove(); });

    var panel = el("aside", "employment-preview");
    panel.id = "employment-preview";
    panel.hidden = true;
    panel.setAttribute("aria-live", "polite");

    var close = el("button", "employment-preview-close", "\u00d7");
    close.type = "button";
    close.setAttribute("aria-label", t.close);

    var media = el("div", "employment-preview-media");
    var img = document.createElement("img");
    img.width = 72;
    img.height = 72;
    img.loading = "lazy";
    img.decoding = "async";
    img.alt = "";
    media.appendChild(img);

    var title = el("p", "employment-preview-title");
    var date = el("p", "employment-preview-date");
    var verified = el("p", "employment-preview-verified", t.verified);
    var source = el("p", "employment-preview-source", t.source);

    panel.appendChild(close);
    panel.appendChild(media);
    panel.appendChild(title);
    panel.appendChild(date);
    panel.appendChild(verified);
    panel.appendChild(source);
    section.appendChild(panel);

    var open = null;

    function hide() {
      panel.classList.remove("is-visible");
      panel.hidden = true;
      if (open) { open.setAttribute("aria-expanded", "false"); open = null; }
    }

    function show(item, slug) {
      if (open === item) return;
      if (open) open.setAttribute("aria-expanded", "false");
      open = item;
      item.setAttribute("aria-expanded", "true");

      var roleEl = item.querySelector(".timeline-role");
      var dateEl = item.querySelector(".timeline-date");
      var role = roleEl ? roleEl.textContent.trim() : "";

      title.textContent = role;
      date.textContent = dateEl ? dateEl.textContent.trim() : "";
      img.alt = t.imageAlt + " " + role;

      var src = base + "assets/images/employment/" + slug + ".webp?v=1";
      if (img.getAttribute("src") !== src) img.src = src;

      var box = item.getBoundingClientRect();
      panel.style.setProperty(
        "--employment-preview-y",
        Math.max(88, Math.min(box.top, window.innerHeight - 390)) + "px"
      );

      panel.hidden = false;
      window.requestAnimationFrame(function () { panel.classList.add("is-visible"); });
    }

    close.addEventListener("click", hide);
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") hide();
    });

    Array.prototype.slice.call(items).forEach(function (item) {
      var slug = LOGOS[item.getAttribute("data-cv-id")];
      if (!slug) return;

      item.classList.add("has-employment-evidence");
      item.setAttribute("tabindex", "0");
      item.setAttribute("role", "button");
      item.setAttribute("aria-expanded", "false");
      item.setAttribute("title", t.hint);

      item.addEventListener("mouseenter", function () { show(item, slug); });
      item.addEventListener("focus", function () { show(item, slug); });
      item.addEventListener("mouseleave", hide);
      item.addEventListener("blur", hide);
      item.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); show(item, slug); }
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
