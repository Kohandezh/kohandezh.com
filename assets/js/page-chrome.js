/*!
 * page-chrome.js — gives every standalone page the same controls as the CV
 * pages: dark/light toggle, language switcher, and (via its own tags) the
 * Kohan avatar.
 *
 * The CV pages build these in markup; the standalone pages (Certificates, PSN,
 * blog, videos, portfolio, privacy, terms) each have their own bespoke header.
 * Rather than hand-duplicating the same cluster into ~20 files across nine
 * locales, the controls are injected into whichever header container the page
 * already has. One implementation, one place to fix.
 *
 * Theme state is shared with the CV pages through the SAME localStorage key
 * and the SAME body class (`dark-mode`) that main.js uses, so switching theme
 * here and navigating back keeps the choice.
 */
(function () {
  "use strict";

  if (window.__KDCV_CHROME__) return;
  window.__KDCV_CHROME__ = true;

  // Ordered by preference: the first container found becomes the mount point.
  var MOUNTS = [
    "[data-kdcv-chrome]",
    ".certificate-top-actions",
    ".psn-top-actions",
    ".blog-header-actions",
    ".vid-nav",
    ".portfolio-actions"
  ];

  var LOCALES = [
    { code: "en", label: "EN", page: "index.html", dir: "ltr" },
    { code: "fa", label: "FA", page: "fa.html", dir: "rtl" },
    { code: "ar", label: "AR", page: "ar.html", dir: "rtl" },
    { code: "de", label: "DE", page: "de.html", dir: "ltr" },
    { code: "es", label: "ES", page: "es.html", dir: "ltr" },
    { code: "fr", label: "FR", page: "fr.html", dir: "ltr" },
    { code: "tr", label: "TR", page: "tr.html", dir: "ltr" },
    { code: "zh", label: "ZH", page: "zh.html", dir: "ltr" },
    { code: "ja", label: "JA", page: "ja.html", dir: "ltr" }
  ];

  var LABELS = {
    en: { theme: "Appearance", language: "Language" },
    fa: { theme: "حالت نمایش", language: "زبان" },
    ar: { theme: "المظهر", language: "اللغة" },
    de: { theme: "Darstellung", language: "Sprache" },
    es: { theme: "Apariencia", language: "Idioma" },
    fr: { theme: "Apparence", language: "Langue" },
    tr: { theme: "Görünüm", language: "Dil" },
    zh: { theme: "外观", language: "语言" },
    ja: { theme: "外観", language: "言語" }
  };

  /* Site menu + footer wording, per locale. The standalone pages used to carry
     a hand-written English footer nav regardless of the page language, which is
     what produced the mixed-language pages (an English label under a Persian
     heading). One dictionary, applied to every page, keeps each page in ONE
     language. */
  var NAV = {
    en: { brand: "Mohammad Ali Kohandezh", blog: "Blog", portfolio: "Portfolio", certificates: "Certificates", psn: "PSN Trophy Room", privacy: "Privacy Policy", terms: "Terms of Use", rights: "All rights reserved", copy: "© 2026 Mohammad Ali Kohandezh", back: "Back to CV" },
    fa: { brand: "محمدعلی کهن‌دژ", blog: "وبلاگ", portfolio: "نمونه‌کارها", certificates: "گواهی‌نامه‌ها", psn: "اتاق افتخارات PSN", privacy: "سیاست حریم خصوصی", terms: "شرایط استفاده", rights: "تمامی حقوق محفوظ است", copy: "© ۱۴۰۵ محمدعلی کهن‌دژ", back: "بازگشت به رزومه" },
    ar: { brand: "محمد علي كهن‌دژ", blog: "المدونة", portfolio: "الأعمال", certificates: "الشهادات", psn: "قاعة جوائز PSN", privacy: "سياسة الخصوصية", terms: "شروط الاستخدام", rights: "جميع الحقوق محفوظة", copy: "© 2026 محمد علي كهن‌دژ", back: "العودة إلى السيرة الذاتية" },
    de: { brand: "Mohammad Ali Kohandezh", blog: "Blog", portfolio: "Portfolio", certificates: "Zertifikate", psn: "PSN-Trophäenraum", privacy: "Datenschutzerklärung", terms: "Nutzungsbedingungen", rights: "Alle Rechte vorbehalten", copy: "© 2026 Mohammad Ali Kohandezh", back: "Zurück zum Lebenslauf" },
    es: { brand: "Mohammad Ali Kohandezh", blog: "Blog", portfolio: "Portafolio", certificates: "Certificados", psn: "Sala de trofeos PSN", privacy: "Política de privacidad", terms: "Términos de uso", rights: "Todos los derechos reservados", copy: "© 2026 Mohammad Ali Kohandezh", back: "Volver al currículum" },
    fr: { brand: "Mohammad Ali Kohandezh", blog: "Blog", portfolio: "Portfolio", certificates: "Certificats", psn: "Salle des trophées PSN", privacy: "Politique de confidentialité", terms: "Conditions d’utilisation", rights: "Tous droits réservés", copy: "© 2026 Mohammad Ali Kohandezh", back: "Retour au CV" },
    tr: { brand: "Mohammad Ali Kohandezh", blog: "Blog", portfolio: "Portföy", certificates: "Sertifikalar", psn: "PSN Ödül Odası", privacy: "Gizlilik Politikası", terms: "Kullanım Şartları", rights: "Tüm hakları saklıdır", copy: "© 2026 Mohammad Ali Kohandezh", back: "Özgeçmişe dön" },
    zh: { brand: "穆罕默德·阿里·科汉德兹", blog: "博客", portfolio: "作品集", certificates: "证书", psn: "PSN 奖杯室", privacy: "隐私政策", terms: "使用条款", rights: "版权所有", copy: "© 2026 穆罕默德·阿里·科汉德兹", back: "返回简历" },
    ja: { brand: "モハンマド・アリ・コハンデズ", blog: "ブログ", portfolio: "ポートフォリオ", certificates: "資格・認定", psn: "PSN トロフィールーム", privacy: "プライバシーポリシー", terms: "利用規約", rights: "無断転載を禁じます", copy: "© 2026 モハンマド・アリ・コハンデズ", back: "履歴書に戻る" }
  };

  // key -> path, relative to the site root. depth() prefixes sub-directories.
  var MENU = [
    { key: "blog", href: "blog/index.html" },
    { key: "portfolio", href: "portfolio/index.html" },
    { key: "certificates", href: "Certificates.html" },
    { key: "psn", href: "PSN.html" },
    { key: "privacy", href: "privacy.html" },
    { key: "terms", href: "terms.html" }
  ];

  function depth() {
    // blog/ and portfolio/ live one level down; their links need "../".
    return /\/(blog|portfolio)\//.test(window.location.pathname) ? "../" : "";
  }

  /* ---- URL bases ---------------------------------------------------------
     On the STATIC build the pages sit beside the assets, so relative paths
     work. On WORDPRESS they do not: pages live at pretty slugs (/psn/,
     /certificates/) and assets under wp-content/themes/, so "assets/…"
     resolves to /psn/assets/… and 404s, and "Certificates.html" resolves to
     /psn/Certificates.html.

     functions.php prints window.KDCV_WP with the real bases, so when this runs
     on WordPress it uses absolute URLs and otherwise falls back to relative. */
  function wp() {
    return (window.KDCV_WP && window.KDCV_WP.assets) ? window.KDCV_WP : null;
  }

  function assetUrl(rel) {
    var w = wp();
    return w ? w.assets + rel : depth() + "assets/" + rel;
  }

  /* key is a MENU key ("blog", "psn", …) or a locale code for a CV page. */
  function pageUrl(key, staticPath) {
    var w = wp();
    if (w && w.pages) {
      if (w.pages[key]) return w.pages[key];
      if (key === "en" && w.pages.home) return w.pages.home;
    }
    return depth() + staticPath;
  }

  function currentLocale() {
    var q = new URLSearchParams(window.location.search).get("lang");
    if (q) {
      for (var i = 0; i < LOCALES.length; i++) if (LOCALES[i].code === q.toLowerCase()) return q.toLowerCase();
    }
    try {
      var stored = window.localStorage.getItem("siteLang");
      if (stored) {
        for (var j = 0; j < LOCALES.length; j++) if (LOCALES[j].code === stored) return stored;
      }
    } catch (e) {}
    var d = (document.documentElement.lang || "en").toLowerCase().split("-")[0];
    for (var k = 0; k < LOCALES.length; k++) if (LOCALES[k].code === d) return d;
    return "en";
  }

  /* ---- theme ------------------------------------------------------------ */
  function isDark() {
    try {
      var v = window.localStorage.getItem("darkMode");
      if (v !== null) return v === "enabled";
    } catch (e) {}
    return document.body.classList.contains("dark-mode");
  }

  function applyTheme(dark) {
    document.body.classList.toggle("dark-mode", dark);
    // These pages use their own palettes keyed off a data attribute, so both
    // signals are set: the class for shared CSS, the attribute for page CSS.
    document.documentElement.setAttribute("data-kdcv-theme", dark ? "dark" : "light");
    try { window.localStorage.setItem("darkMode", dark ? "enabled" : "disabled"); } catch (e) {}
  }

  function buildTheme(L) {
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "kdcv-chrome-btn kdcv-chrome-theme";
    btn.setAttribute("aria-label", L.theme);
    btn.title = L.theme;
    btn.innerHTML =
      '<svg class="kdcv-ico-sun" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
      '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.6v2.2M12 19.2v2.2M2.6 12h2.2M19.2 12h2.2M5.4 5.4l1.6 1.6M17 17l1.6 1.6M18.6 5.4 17 7M7 17l-1.6 1.6"/></svg>' +
      '<svg class="kdcv-ico-moon" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5a8.5 8.5 0 1 0 10.7 10.7Z"/></svg>';

    function sync() {
      var dark = isDark();
      btn.setAttribute("aria-pressed", dark ? "true" : "false");
    }
    btn.addEventListener("click", function () {
      applyTheme(!isDark());
      sync();
    });
    sync();
    return btn;
  }

  /* Two theme conventions exist on this site and they were fighting.

     portfolio.js toggles `body.light` and writes localStorage.darkMode, but
     never touches `body.dark-mode`. page-chrome sets `dark-mode` from the same
     storage key. Result on the portfolio page: class="dark-mode light" — the
     page's own button did nothing visible, because every shared component
     keys off `dark-mode` (per CLAUDE.md, `body.light` matches nothing in the
     shared CSS).

     Rather than rewrite the page's script, mirror its state: whenever `light`
     is toggled, `dark-mode` is set to the opposite. One button, both
     conventions in agreement. */
  function mirrorNativeTheme() {
    var body = document.body;
    if (!body) return;

    var syncing = false;
    function sync() {
      if (syncing) return;
      syncing = true;
      var light = body.classList.contains("light");
      body.classList.toggle("dark-mode", !light);
      document.documentElement.setAttribute("data-kdcv-theme", light ? "light" : "dark");
      try { window.localStorage.setItem("darkMode", light ? "disabled" : "enabled"); } catch (e) {}
      syncing = false;
    }

    sync();
    new MutationObserver(sync).observe(body, { attributes: true, attributeFilter: ["class"] });
  }

  /* ---- language --------------------------------------------------------- */
  function buildLanguage(L, loc) {
    var wrap = document.createElement("div");
    wrap.className = "kdcv-chrome-lang";

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "kdcv-chrome-btn kdcv-chrome-lang-toggle";
    btn.setAttribute("aria-haspopup", "true");
    btn.setAttribute("aria-expanded", "false");
    btn.setAttribute("aria-label", L.language);
    btn.title = L.language;
    btn.textContent = loc.toUpperCase();

    var menu = document.createElement("ul");
    menu.className = "kdcv-chrome-lang-menu";
    menu.hidden = true;

    // Certificates translates in place via ?lang=; the other standalone pages
    // have no per-locale variant, so the switcher takes the visitor to that
    // language's CV page rather than pretending the current page is localized.
    var inPlace = /Certificates\.html$/i.test(window.location.pathname);

    LOCALES.forEach(function (l) {
      var li = document.createElement("li");
      var a = document.createElement("a");
      a.className = "kdcv-chrome-lang-item";
      a.textContent = l.label;
      a.setAttribute("lang", l.code);
      a.href = inPlace
        ? window.location.pathname + "?lang=" + l.code
        : pageUrl(l.code, l.page);
      if (l.code === loc) a.setAttribute("aria-current", "true");
      a.addEventListener("click", function () {
        try { window.localStorage.setItem("siteLang", l.code); } catch (e) {}
      });
      li.appendChild(a);
      menu.appendChild(li);
    });

    function close() { menu.hidden = true; btn.setAttribute("aria-expanded", "false"); }
    function open() { menu.hidden = false; btn.setAttribute("aria-expanded", "true"); }

    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      menu.hidden ? open() : close();
    });
    document.addEventListener("click", function (e) {
      if (!wrap.contains(e.target)) close();
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") close();
    });

    wrap.appendChild(btn);
    wrap.appendChild(menu);
    return wrap;
  }

  /* ---- shared site menu + footer ---------------------------------------- */
  function homeHref(loc) {
    for (var i = 0; i < LOCALES.length; i++) {
      if (LOCALES[i].code === loc) return pageUrl(loc, LOCALES[i].page);
    }
    return pageUrl("en", "index.html");
  }

  /* Pages that translate IN PLACE from ?lang=. The others (privacy, terms,
     blog, videos — Persian only; PSN — English only) have no second version to
     switch to, so tagging their links would promise a translation that does not
     exist. */
  var TRANSLATES_IN_PLACE = { certificates: 1, portfolio: 1, privacy: 1, terms: 1, psn: 1, blog: 1 };

  function withLang(href, key, loc) {
    if (!TRANSLATES_IN_PLACE[key]) return href;
    return href + (href.indexOf("?") === -1 ? "?" : "&") + "lang=" + encodeURIComponent(loc);
  }

  function menuLinks(N, loc) {
    var here = window.location.pathname.replace(/\/+$/, "/index.html");
    var out = [];
    MENU.forEach(function (m) {
      var a = document.createElement("a");
      a.href = withLang(pageUrl(m.key, m.href), m.key, loc);
      a.textContent = N[m.key];
      // Portfolio and the blog live one level down; match on the tail so the
      // current page is marked whichever directory we are in.
      if (here.toLowerCase().indexOf("/" + m.href.toLowerCase()) !== -1) a.setAttribute("aria-current", "page");
      out.push(a);
    });
    return out;
  }

  /* The menu and footer must follow the language the page is actually WRITTEN
     in, not the visitor's stored preference. These standalone pages have one
     URL and one authored language; keying off `siteLang` produced an English
     menu bolted onto a Persian page — the exact language mixing being fixed. */
  function contentLocale() {
    var authored = (document.documentElement.lang || "en").toLowerCase().split("-")[0];
    var known = function (c) {
      for (var i = 0; i < LOCALES.length; i++) if (LOCALES[i].code === c) return true;
      return false;
    };
    if (!known(authored)) authored = "en";

    /* Pages that translate IN PLACE (marked `data-kdcv-i18n="in-place"` on
       <html>) end up in the visitor's language, so the chrome must follow the
       visitor — ?lang first, then the language they were last reading.

       Every other page has ONE authored language and no translation to switch
       to. There the chrome stays in the page's own language; following the
       visitor would put an English menu on a Persian document, which is the
       language-mixing this site is explicitly meant to avoid. */
    if (document.documentElement.getAttribute("data-kdcv-i18n") !== "in-place") {
      return authored;
    }
    var q = new URLSearchParams(window.location.search).get("lang");
    if (q && known(q.toLowerCase())) return q.toLowerCase();
    try {
      var stored = window.localStorage.getItem("siteLang");
      if (stored && known(stored)) return stored;
    } catch (e) {}
    return authored;
  }

  function buildNav(loc) {
    if (document.querySelector(".kdcv-nav")) return;
    // Ordered probe, not a comma-list: querySelector returns the first match in
    // DOCUMENT order, which on some pages is a section <header> inside <main>.
    var HEADERS = [".portfolio-header", ".psn-topbar", ".blog-header", ".certificate-topbar", ".certificate-header", ".vid-header", "body > header"];
    var header = null;
    for (var h = 0; h < HEADERS.length && !header; h++) header = document.querySelector(HEADERS[h]);
    if (!header) return;

    // The page's own partial nav is superseded by the unified one; leaving it
    // in place would show two menus with different item sets.
    var own = header.querySelector("nav.blog-nav, nav.psn-nav, nav.vid-nav-links, nav.certificate-nav");
    if (own) own.remove();

    var N = NAV[loc] || NAV.en;
    var nav = document.createElement("nav");
    nav.className = "kdcv-nav";
    nav.setAttribute("aria-label", N.brand);

    var home = document.createElement("a");
    home.className = "kdcv-nav-home";
    home.href = homeHref(loc);
    home.textContent = N.brand;
    nav.appendChild(home);

    menuLinks(N, loc).forEach(function (a) { nav.appendChild(a); });
    header.insertAdjacentElement("afterend", nav);
  }

  /* Rewrites the shared footer block in the page language. The markup already
     exists on most pages (hand-written, English); this replaces its text and
     builds the block from scratch where it is missing entirely. */
  /* The PAGE footer, which is not always the first <footer> in the document.
     privacy.html and terms.html each carry an ARTICLE footer half-way down the
     page, and `document.querySelector("footer")` picked that one — so the
     shared block was built mid-article while the real footer at the bottom kept
     its own hand-written tail. Named page footers are probed first; the last
     <footer> in the document is the fallback, never the first. */
  function footerHost() {
    var NAMED = ".blog-footer, .portfolio-footer, .certificate-footer, .psn-footer, .vid-footer";
    var named = document.querySelectorAll(NAMED);
    if (named.length) return named[named.length - 1];
    var all = document.querySelectorAll("footer");
    return all.length ? all[all.length - 1] : null;
  }

  function localizeFooter(loc) {
    var N = NAV[loc] || NAV.en;
    var host = footerHost();
    var foot = host ? host.querySelector(".kdcv-foot") : document.querySelector(".kdcv-foot");

    if (!foot) {
      if (!host) return;
      foot = document.createElement("div");
      foot.className = "kdcv-foot";
      foot.innerHTML =
        '<a class="kdcv-foot-brand" href="' + homeHref(loc) + '">' +
        '<img src="' + assetUrl("images/logo/logo.png") + '" width="36" height="36" alt="">' +
        "<span></span></a>" +
        '<nav class="kdcv-foot-nav"></nav>' +
        '<p class="kdcv-foot-legal"></p>';
      host.insertBefore(foot, host.firstChild);
    }

    // The brand is the wordmark IMAGE in a circle, matching the home page's
    // .tf-footer .f-logo exactly — one lockup that reads identically in every
    // locale. The name still reaches assistive tech via alt/aria-label.
    var brand = foot.querySelector(".kdcv-foot-brand");
    if (brand) {
      brand.setAttribute("aria-label", N.brand);
      brand.classList.add("kdcv-foot-brand--mark");
      var span = brand.querySelector("span");
      if (span) span.remove();
      var img = brand.querySelector("img");
      if (img) {
        img.src = assetUrl("images/logo/footer-logo.webp");
        img.alt = N.brand;
        img.removeAttribute("width");
        img.removeAttribute("height");
        img.removeAttribute("style");
      }
    }

    /* The home page footer carries a wordmark above the nav, so the shared
       footer does too — as TEXT.

       It used to inject the home page's own <svg>, which turned out to draw
       the word "Isak": the brand name of the template this theme was built
       from, not this site's. On the home page it sits at near-zero contrast
       and nobody had noticed it; injected here at full opacity it put a
       stranger's brand across five pages. Both SVGs (the second spelled
       "Folio") have been removed site-wide. */
    if (!foot.querySelector(".kdcv-foot-wordmark")) {
      var mark = document.createElement("div");
      mark.className = "kdcv-foot-wordmark kdcv-wordmark";
      mark.setAttribute("aria-hidden", "true");
      mark.textContent = "KOHANDEZH";
      foot.insertBefore(mark, foot.firstChild);
    }

    var fnav = foot.querySelector(".kdcv-foot-nav");
    if (fnav) {
      fnav.setAttribute("aria-label", N.brand);
      fnav.textContent = "";
      menuLinks(N, loc).forEach(function (a) { fnav.appendChild(a); });
    }

    var legal = foot.querySelector(".kdcv-foot-legal");
    if (legal) legal.innerHTML = "";
    if (legal) {
      legal.appendChild(document.createTextNode(N.rights));
      legal.appendChild(document.createElement("br"));
      legal.appendChild(document.createTextNode(N.copy));
    }

    /* Every page footer used to keep its own tail after the shared block — a
       second copyright line plus a "back to top" or "back to home" link, worded
       differently on each page. That is what made the footers look like five
       different footers. The tail is dropped so the block IS the footer,
       identically on every page. Belt-and-braces: the markup no longer carries
       one either, but the WordPress templates are regenerated from these files
       and a stale copy would otherwise reappear. */
    if (host) {
      Array.prototype.slice.call(host.childNodes).forEach(function (n) {
        if (n !== foot) host.removeChild(n);
      });
    }
  }

  function init() {
    var loc = currentLocale();

    // Menu and footer are page-wide and must run even on a page that has no
    // control mount — that is exactly where the untranslated English footer
    // used to survive.
    var content = contentLocale();
    try { buildNav(content); } catch (e) {}
    try { localizeFooter(content); } catch (e) {}
    applyTheme(isDark());

    var mount = null;
    for (var i = 0; i < MOUNTS.length && !mount; i++) mount = document.querySelector(MOUNTS[i]);
    if (!mount) return;
    if (mount.querySelector(".kdcv-chrome-btn")) return; // already mounted

    var L = LABELS[loc] || LABELS.en;

    /* Some pages ship their OWN theme toggle and language picker — the
       portfolio page has both, wired by portfolio.js. Injecting a second pair
       gave it two sun buttons and two FA pickers side by side. So each control
       is added only if the page has no equivalent, and the page's own wiring
       is left untouched. */
    var hasTheme = document.querySelector("#themeToggle, .toggle-switch-mode, .kdcv-chrome-theme");
    var hasLang = document.querySelector("#languageSelect, .language-wrap, .lang-item, .kdcv-chrome-lang");

    if (!hasTheme || !hasLang) {
      var group = document.createElement("div");
      group.className = "kdcv-chrome";
      group.setAttribute("role", "group");
      group.setAttribute("aria-label", L.theme + " / " + L.language);
      if (!hasTheme) group.appendChild(buildTheme(L));
      if (!hasLang) group.appendChild(buildLanguage(L, loc));

      // Prepend so the controls sit before the page's own primary action.
      mount.insertBefore(group, mount.firstChild);
    }

    // A page with its own toggle keeps it; we only keep the two class
    // conventions in agreement. Otherwise we own the theme outright.
    if (document.querySelector("#themeToggle, .toggle-switch-mode")) mirrorNativeTheme();
    else applyTheme(isDark());
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
