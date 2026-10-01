(function () {
  "use strict";
  var root = document.documentElement;
  var themeButton = document.querySelector(".ab-theme-toggle");
  var themeLabel = themeButton ? themeButton.querySelector(".ab-theme-label") : null;
  var themeIcon = themeButton ? themeButton.querySelector(".ab-theme-icon") : null;
  var themeStorageKey = "darkMode";
  var legacyThemeStorageKey = "kbk-ai-book-theme";

  function storedTheme() {
    try {
      var value = window.localStorage.getItem(themeStorageKey);
      var legacyValue = window.localStorage.getItem(legacyThemeStorageKey);
      if (legacyValue === "light" || legacyValue === "dark") {
        window.localStorage.setItem(themeStorageKey, legacyValue === "light" ? "disabled" : "enabled");
        window.localStorage.removeItem(legacyThemeStorageKey);
        return legacyValue;
      }
      if (value === "enabled" || value === "dark") return "dark";
      if (value === "disabled" || value === "light") return "light";
      return null;
    } catch (error) {
      return null;
    }
  }

  function preferredTheme() {
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark";
  }

  function applyTheme(theme, persist) {
    var isLight = theme === "light";
    root.setAttribute("data-ab-theme", isLight ? "light" : "dark");
    root.setAttribute("data-kdcv-theme", isLight ? "light" : "dark");
    document.body.classList.toggle("light-mode", isLight);
    document.body.classList.toggle("dark-mode", !isLight);
    if (themeButton) {
      themeButton.setAttribute("aria-pressed", String(isLight));
      themeButton.setAttribute("aria-label", isLight ? "فعال‌کردن حالت تیره" : "فعال‌کردن حالت روشن");
    }
    if (themeLabel) themeLabel.textContent = isLight ? "حالت تیره" : "حالت روشن";
    if (themeIcon) themeIcon.textContent = isLight ? "☾" : "☀";
    if (persist) {
      try {
        window.localStorage.setItem(themeStorageKey, isLight ? "disabled" : "enabled");
      } catch (error) {
        // The selected theme still applies for this page when storage is unavailable.
      }
    }
  }

  var savedTheme = storedTheme();
  applyTheme(root.getAttribute("data-ab-theme") || savedTheme || preferredTheme(), false);
  if (themeButton) {
    themeButton.addEventListener("click", function () {
      applyTheme(root.getAttribute("data-ab-theme") === "light" ? "dark" : "light", true);
    });
  }
  if (window.matchMedia) {
    window.matchMedia("(prefers-color-scheme: light)").addEventListener("change", function (event) {
      if (!storedTheme()) applyTheme(event.matches ? "light" : "dark", false);
    });
  }

  // Progressive reader preferences: only presentation is stored, never book data.
  if (document.body.classList.contains("ab-view-read") && document.querySelector(".ab-prose")) {
    var prefs = { size: 18, line: "2", wide: true, focus: true, paper: false };
    try {
      var saved = JSON.parse(window.localStorage.getItem("kbk-reader-preferences") || "null");
      if (saved && typeof saved === "object") {
        if (Number.isInteger(saved.size) && saved.size >= 16 && saved.size <= 26) prefs.size = saved.size;
        if (["1.8", "2", "2.2"].indexOf(saved.line) !== -1) prefs.line = saved.line;
        ["wide", "focus", "paper"].forEach(function (key) { if (typeof saved[key] === "boolean") prefs[key] = saved[key]; });
      }
    } catch (error) { /* Storage is optional. */ }
    var readingBar = document.createElement("nav");
    readingBar.className = "ab-reading-tools";
    readingBar.setAttribute("aria-label", "تنظیمات مطالعه");
    readingBar.innerHTML = '<button type="button" data-reader="focus"></button><button type="button" data-reader="index" aria-expanded="false" aria-controls="ab-reading-index">فهرست فصل</button><div class="ab-reading-size"><button type="button" data-reader="smaller" aria-label="کوچک‌کردن نوشته">A−</button><output aria-live="polite"></output><button type="button" data-reader="larger" aria-label="بزرگ‌کردن نوشته">A+</button></div><button type="button" data-reader="width"></button><label>فاصلهٔ خطوط <select aria-label="فاصلهٔ خطوط"><option value="1.8">فشرده</option><option value="2">معمولی</option><option value="2.2">باز</option></select></label><label>رنگ صفحه <select data-reader="appearance" aria-label="رنگ صفحه"><option value="light">روشن</option><option value="dark">تیره</option><option value="paper">کاغذی</option></select></label><button type="button" data-reader="reset">بازنشانی</button>';
    document.querySelector(".ab-main").before(readingBar);
    var compactIndex = document.querySelector(".ab-reader-index");
    var indexButton = readingBar.querySelector('[data-reader="index"]');
    if (compactIndex) compactIndex.id = "ab-reading-index";
    else indexButton.hidden = true;
    function closeReadingIndex() {
      if (compactIndex) compactIndex.open = false;
      indexButton.setAttribute("aria-expanded", "false");
    }
    function renderPreferences(persist) {
      document.body.classList.add("ab-reader-enhanced");
      document.body.classList.toggle("ab-reading-focus", prefs.focus);
      document.body.classList.toggle("ab-reading-wide", prefs.wide);
      document.body.classList.toggle("ab-reading-paper", prefs.paper);
      document.body.style.setProperty("--ab-reader-size", prefs.size + "px");
      document.body.style.setProperty("--ab-reader-line", prefs.line);
      readingBar.querySelector("output").textContent = prefs.size.toLocaleString("fa-IR");
      readingBar.querySelector('[data-reader="smaller"]').disabled = prefs.size <= 16;
      readingBar.querySelector('[data-reader="larger"]').disabled = prefs.size >= 26;
      readingBar.querySelector('[data-reader="focus"]').textContent = prefs.focus ? "بازگشت به سایت" : "حالت مطالعه";
      readingBar.querySelector('[data-reader="focus"]').setAttribute("aria-pressed", String(prefs.focus));
      readingBar.querySelector('[data-reader="width"]').textContent = prefs.wide ? "عرض: باز" : "عرض: استاندارد";
      readingBar.querySelector('[data-reader="width"]').setAttribute("aria-pressed", String(prefs.wide));
      readingBar.querySelector("select").value = prefs.line;
      readingBar.querySelector('[data-reader="appearance"]').value = prefs.paper ? "paper" : root.getAttribute("data-ab-theme");
      if (persist) { try { window.localStorage.setItem("kbk-reader-preferences", JSON.stringify(prefs)); } catch (error) { /* Optional. */ } }
    }
    readingBar.addEventListener("click", function (event) {
      var control = event.target.closest("button[data-reader]");
      if (!control) return;
      var action = control.getAttribute("data-reader");
      if (action === "index" && compactIndex) {
        compactIndex.open = !compactIndex.open;
        indexButton.setAttribute("aria-expanded", String(compactIndex.open));
        if (compactIndex.open) compactIndex.querySelector("a").focus();
        return;
      }
      if (action === "smaller") prefs.size = Math.max(16, prefs.size - 1);
      if (action === "larger") prefs.size = Math.min(26, prefs.size + 1);
      if (action === "focus") { prefs.focus = !prefs.focus; closeReadingIndex(); }
      if (action === "width") prefs.wide = !prefs.wide;
      if (action === "reset") prefs = { size: 18, line: "2", wide: true, focus: true, paper: false };
      renderPreferences(true);
    });
    readingBar.addEventListener("change", function (event) {
      if (event.target.getAttribute("data-reader") === "appearance") {
        prefs.paper = event.target.value === "paper";
        applyTheme(prefs.paper ? "light" : event.target.value, true);
      } else prefs.line = event.target.value;
      renderPreferences(true);
    });
    if (compactIndex) compactIndex.addEventListener("click", function (event) {
      var link = event.target.closest("a");
      if (!link) return;
      closeReadingIndex();
      var target = document.getElementById(link.hash.slice(1));
      if (target) { target.setAttribute("tabindex", "-1"); target.focus({ preventScroll: true }); }
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && compactIndex && compactIndex.open) { closeReadingIndex(); indexButton.focus(); }
    });
    if (themeButton) themeButton.addEventListener("click", function () { prefs.paper = false; renderPreferences(true); });
    if (prefs.paper) applyTheme("light", false);
    renderPreferences(false);
    if (window.ResizeObserver) new ResizeObserver(function () {
      root.style.setProperty("--ab-reader-toolbar-height", readingBar.offsetHeight + "px");
    }).observe(readingBar);
  }

  // Keep the chapter index aligned with reading position, without rewriting URLs.
  var sections = Array.from(document.querySelectorAll(".ab-prose > section[id]"));
  var sectionLinks = Array.from(document.querySelectorAll('.ab-toc > a[href^="#"], .ab-reader-index nav a[href^="#"]'));
  if (sections.length && sectionLinks.length) {
    var framePending = false;
    function updateReadingLocation() {
      framePending = false;
      var header = document.querySelector(".ab-reading-tools") || document.querySelector(".ab-header");
      var threshold = (header ? header.getBoundingClientRect().bottom : 74) + 40;
      var current = sections[0];
      sections.forEach(function (section) {
        if (section.getBoundingClientRect().top <= threshold) current = section;
      });
      sectionLinks.forEach(function (link) {
        var active = link.getAttribute("href") === "#" + current.id;
        link.classList.toggle("is-current", active);
        if (active) link.setAttribute("aria-current", "location");
        else link.removeAttribute("aria-current");
      });
    }
    function queueReadingLocation() {
      if (!framePending) {
        framePending = true;
        window.requestAnimationFrame(updateReadingLocation);
      }
    }
    window.addEventListener("scroll", queueReadingLocation, { passive: true });
    window.addEventListener("hashchange", queueReadingLocation);
    window.addEventListener("resize", queueReadingLocation);
    window.addEventListener("load", queueReadingLocation);
    queueReadingLocation();
  }

  var button = document.querySelector(".ab-menu-button");
  var menu = document.getElementById("mobile-nav");
  if (!button || !menu) return;
  button.addEventListener("click", function () {
    var open = button.getAttribute("aria-expanded") === "true";
    button.setAttribute("aria-expanded", String(!open));
    menu.hidden = open;
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !menu.hidden) {
      menu.hidden = true;
      button.setAttribute("aria-expanded", "false");
      button.focus();
    }
  });
})();
