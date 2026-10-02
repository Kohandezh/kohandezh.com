(function () {
  "use strict";
  var root = document.documentElement;
  var themeButton = document.querySelector(".ab-theme-toggle");
  var themeLabel = themeButton ? themeButton.querySelector(".ab-theme-label") : null;
  var themeIcon = themeButton ? themeButton.querySelector(".ab-theme-icon") : null;
  var themeStorageKey = "darkMode";
  var legacyThemeStorageKey = "kbk-ai-book-theme";
  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function faDigits(value) {
    return String(value).replace(/[0-9]/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹".charAt(+d); });
  }
  function storageGet(key) {
    try { return window.localStorage.getItem(key); } catch (error) { return null; }
  }
  function storageSet(key, value) {
    try { window.localStorage.setItem(key, value); } catch (error) { /* Storage is optional. */ }
  }

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
    if (persist) storageSet(themeStorageKey, isLight ? "disabled" : "enabled");
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

  // Chapter page map: old links (chapter URL + #STRUCTURAL-ID) land on the page
  // that holds the section, without a server round trip per anchor.
  var chapterMap = null;
  var mapNode = document.getElementById("ab-chapter-map");
  if (mapNode) {
    try { chapterMap = JSON.parse(mapNode.textContent || "null"); } catch (error) { chapterMap = null; }
  }
  function samePageUrl(url) {
    try { return new URL(url, window.location.href).origin === window.location.origin; } catch (error) { return false; }
  }
  function followHashToPage() {
    if (!chapterMap || !chapterMap.sections || !window.location.hash) return false;
    var id;
    try { id = decodeURIComponent(window.location.hash.slice(1)); } catch (error) { return false; }
    if (!id || document.getElementById(id)) return false;
    // Split sections list their parts (ID-p2) and headings (ID-h7); any other
    // in-section anchor (heading, footnote) lives on its section's page.
    var page = chapterMap.sections[id] || chapterMap.sections[id.replace(/-(?:h\d+|p\d+|fn-\d+|fnref-\d+-\d+)$/, "")];
    var target = page && chapterMap.urls ? chapterMap.urls[page - 1] : null;
    if (!target || page === chapterMap.page || !samePageUrl(target)) return false;
    window.location.replace(target + "#" + encodeURIComponent(id));
    return true;
  }
  if (followHashToPage()) return;
  window.addEventListener("hashchange", followHashToPage);

  // Book menu (#mobile-nav): opened from the header button, or on phones from
  // the reading bar (the reader's header scrolls away there). It opens under
  // whichever bar is on screen; Escape closes it and returns focus.
  var menuButton = document.querySelector(".ab-menu-button");
  var bookMenu = document.getElementById("mobile-nav");
  var menuTriggers = menuButton ? [menuButton] : [];
  var menuReturn = null;
  function setBookMenu(open, trigger) {
    if (!bookMenu) return;
    bookMenu.hidden = !open;
    if (open) {
      menuReturn = trigger || menuButton;
      var top = 0;
      [document.querySelector(".ab-header"), document.querySelector(".ab-reading-tools")].forEach(function (bar) {
        if (!bar) return;
        var rect = bar.getBoundingClientRect();
        if (rect.bottom > top && rect.bottom > 0 && rect.top < window.innerHeight && window.getComputedStyle(bar).opacity !== "0") top = rect.bottom;
      });
      bookMenu.style.top = Math.round(top + 8) + "px";
    }
    menuTriggers.forEach(function (control) { control.setAttribute("aria-expanded", String(open)); });
  }

  // "Continue reading": one entry per book, written as the reader moves.
  var RESUME_KEY = "kbk-reader-resume";
  function readResume(book) {
    try {
      var all = JSON.parse(storageGet(RESUME_KEY) || "null");
      var entry = all && typeof all === "object" ? all[book] : null;
      if (!entry || typeof entry.url !== "string" || !samePageUrl(entry.url)) return null;
      return entry;
    } catch (error) {
      return null;
    }
  }
  function writeResume(book, entry) {
    var all = {};
    try { all = JSON.parse(storageGet(RESUME_KEY) || "null") || {}; } catch (error) { all = {}; }
    if (typeof all !== "object") all = {};
    all[book] = entry;
    storageSet(RESUME_KEY, JSON.stringify(all));
  }
  // Each book keeps its own entry: the slug comes from the chapter map on a
  // chapter page, from the slot itself on the library shelf and landings.
  var currentBook = chapterMap && typeof chapterMap.book === "string" && chapterMap.book ? chapterMap.book : "ai-governance";
  Array.prototype.forEach.call(document.querySelectorAll("[data-ab-resume]"), function (slot) {
    var entry = readResume(slot.getAttribute("data-ab-book") || currentBook);
    if (!entry) return;
    var mode = slot.getAttribute("data-ab-resume");
    if (mode === "chapter") {
      if (!chapterMap || entry.chapter !== chapterMap.chapter || window.location.hash) return;
      var firstSection = document.querySelector(".ab-prose > section[id]:not(.ab-section-omitted)");
      if (entry.page === chapterMap.page && firstSection && entry.section === firstSection.id) return;
    }
    var link = document.createElement("a");
    link.className = mode === "chapter" ? "ab-resume-link" : "ab-button ab-resume-link";
    link.href = entry.url;
    link.textContent = "ادامهٔ مطالعه";
    if (entry.title) {
      var detail = document.createElement("span");
      detail.className = "ab-resume-title";
      detail.textContent = (mode === "chapter" ? "از «" : "«") + entry.title + "»";
      link.appendChild(document.createTextNode(" "));
      link.appendChild(detail);
    }
    slot.appendChild(link);
    slot.hidden = false;
  });

  // Section citation: the link already works; copying is an enhancement.
  var liveRegion = document.createElement("p");
  liveRegion.className = "ab-visually-hidden";
  liveRegion.setAttribute("aria-live", "polite");
  document.body.appendChild(liveRegion);
  if (navigator.clipboard && window.isSecureContext !== false) {
    Array.prototype.forEach.call(document.querySelectorAll(".ab-copy[data-ab-copy]"), function (button) {
      button.hidden = false;
      button.addEventListener("click", function () {
        var url = button.getAttribute("data-ab-copy");
        navigator.clipboard.writeText(url).then(function () {
          button.textContent = "کپی شد";
          liveRegion.textContent = "پیوند این بخش کپی شد.";
          window.setTimeout(function () { button.textContent = "کپی پیوند"; }, 2000);
        }, function () {
          liveRegion.textContent = "کپی انجام نشد؛ پیوند مستقیم را باز کنید.";
        });
      });
    });
  }

  var readerRoot = document.querySelector(".ab-reader");
  var prose = document.querySelector(".ab-reader .ab-prose");
  var toolbar = null;

  // Reader preferences: presentation only, never book data. Version 2 changed
  // the defaults (standard width, site chrome visible); a stored object that
  // predates it only keeps choices that differ from the old defaults.
  if (document.body.classList.contains("ab-view-read") && prose) {
    var PREF_KEY = "kbk-reader-preferences";
    var PREF_VERSION = 2;
    var defaults = {
      size: 18,
      line: "2",
      wide: readerRoot.getAttribute("data-default-wide") === "1",
      focus: readerRoot.getAttribute("data-default-focus") === "1",
      paper: false
    };
    var prefs = Object.assign({}, defaults);
    (function loadPreferences() {
      var saved = null;
      try { saved = JSON.parse(storageGet(PREF_KEY) || "null"); } catch (error) { saved = null; }
      if (!saved || typeof saved !== "object") return;
      if (Number.isInteger(saved.size) && saved.size >= 16 && saved.size <= 26) prefs.size = saved.size;
      if (["1.8", "2", "2.2"].indexOf(saved.line) !== -1) prefs.line = saved.line;
      if (typeof saved.paper === "boolean") prefs.paper = saved.paper;
      if (saved.v === PREF_VERSION) {
        if (typeof saved.wide === "boolean") prefs.wide = saved.wide;
        if (typeof saved.focus === "boolean") prefs.focus = saved.focus;
      } else {
        if (saved.wide === false) prefs.wide = false;
        if (saved.focus === false) prefs.focus = false;
      }
    })();

    toolbar = document.createElement("nav");
    toolbar.className = "ab-reading-tools";
    toolbar.setAttribute("aria-label", "ابزار مطالعه");
    toolbar.innerHTML =
      '<div class="ab-progress" role="progressbar" aria-label="پیشرفت مطالعهٔ این صفحه" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div>' +
      '<button type="button" class="ab-tool ab-tool-menu" data-reader="menu" aria-expanded="false" aria-controls="mobile-nav">فهرست کتاب</button>' +
      '<button type="button" class="ab-tool" data-reader="index" aria-expanded="false" aria-controls="ab-reading-index">فهرست فصل</button>' +
      '<p class="ab-reading-title" aria-live="off"></p>' +
      '<button type="button" class="ab-tool ab-tool-wide" data-reader="focus"></button>' +
      '<button type="button" class="ab-tool ab-tool-aa" data-reader="settings" aria-expanded="false" aria-controls="ab-reading-panel" aria-label="تنظیمات نمایش متن">Aa</button>' +
      '<div class="ab-reading-panel" id="ab-reading-panel" role="group" aria-label="تنظیمات نمایش متن" hidden>' +
        '<div class="ab-panel-row"><span class="ab-panel-label">اندازهٔ متن</span><div class="ab-reading-size"><button type="button" data-reader="smaller" aria-label="کوچک‌کردن نوشته">A−</button><output aria-live="polite"></output><button type="button" data-reader="larger" aria-label="بزرگ‌کردن نوشته">A+</button></div></div>' +
        '<label class="ab-panel-row"><span class="ab-panel-label">فاصلهٔ خطوط</span><select data-reader="line"><option value="1.8">فشرده</option><option value="2">معمولی</option><option value="2.2">باز</option></select></label>' +
        '<label class="ab-panel-row"><span class="ab-panel-label">رنگ صفحه</span><select data-reader="appearance"><option value="light">روشن</option><option value="dark">تیره</option><option value="paper">کاغذی</option></select></label>' +
        '<div class="ab-panel-row"><span class="ab-panel-label">عرض متن</span><div class="ab-segment"><button type="button" data-reader="narrow">استاندارد</button><button type="button" data-reader="wide">باز</button></div></div>' +
        '<div class="ab-panel-row"><span class="ab-panel-label">حالت مطالعه</span><button type="button" data-reader="focus-panel"></button></div>' +
        '<div class="ab-panel-row ab-panel-end"><button type="button" data-reader="reset">بازنشانی</button><button type="button" data-reader="close">بستن</button></div>' +
      "</div>";
    document.querySelector(".ab-main").before(toolbar);

    var panel = toolbar.querySelector(".ab-reading-panel");
    var settingsButton = toolbar.querySelector('[data-reader="settings"]');
    var indexButton = toolbar.querySelector('[data-reader="index"]');
    var progress = toolbar.querySelector(".ab-progress");
    var progressFill = progress.querySelector("span");
    var titleSlot = toolbar.querySelector(".ab-reading-title");
    var toolbarMenu = toolbar.querySelector('[data-reader="menu"]');
    if (bookMenu) menuTriggers.push(toolbarMenu);
    else toolbarMenu.hidden = true;
    var compactIndex = document.querySelector(".ab-reader-index");
    if (compactIndex) compactIndex.id = "ab-reading-index";
    else indexButton.hidden = true;

    function setPanel(open) {
      panel.hidden = !open;
      settingsButton.setAttribute("aria-expanded", String(open));
      if (open) {
        closeReadingIndex();
        var first = panel.querySelector("button, select");
        if (first) first.focus();
      }
    }
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
      panel.querySelector("output").textContent = faDigits(prefs.size);
      panel.querySelector('[data-reader="smaller"]').disabled = prefs.size <= 16;
      panel.querySelector('[data-reader="larger"]').disabled = prefs.size >= 26;
      var focusLabel = prefs.focus ? "نمایش سرصفحهٔ سایت" : "حالت مطالعه";
      toolbar.querySelector('[data-reader="focus"]').textContent = focusLabel;
      toolbar.querySelector('[data-reader="focus"]').setAttribute("aria-pressed", String(prefs.focus));
      panel.querySelector('[data-reader="focus-panel"]').textContent = prefs.focus ? "روشن" : "خاموش";
      panel.querySelector('[data-reader="focus-panel"]').setAttribute("aria-pressed", String(prefs.focus));
      panel.querySelector('[data-reader="narrow"]').setAttribute("aria-pressed", String(!prefs.wide));
      panel.querySelector('[data-reader="wide"]').setAttribute("aria-pressed", String(prefs.wide));
      panel.querySelector('[data-reader="line"]').value = prefs.line;
      panel.querySelector('[data-reader="appearance"]').value = prefs.paper ? "paper" : root.getAttribute("data-ab-theme");
      if (persist) {
        storageSet(PREF_KEY, JSON.stringify({ v: PREF_VERSION, size: prefs.size, line: prefs.line, wide: prefs.wide, focus: prefs.focus, paper: prefs.paper }));
      }
    }
    toolbar.addEventListener("click", function (event) {
      var control = event.target.closest("button[data-reader]");
      if (!control) return;
      var action = control.getAttribute("data-reader");
      if (action === "settings") { setPanel(panel.hidden); return; }
      if (action === "menu") {
        setPanel(false);
        closeReadingIndex();
        setBookMenu(bookMenu.hidden, control);
        if (!bookMenu.hidden) {
          var firstLink = bookMenu.querySelector('a[aria-current="page"]') || bookMenu.querySelector("a");
          if (firstLink) firstLink.focus();
        }
        return;
      }
      if (action === "close") { setPanel(false); settingsButton.focus(); return; }
      if (action === "index" && compactIndex) {
        setPanel(false);
        setBookMenu(false);
        compactIndex.open = !compactIndex.open;
        indexButton.setAttribute("aria-expanded", String(compactIndex.open));
        if (compactIndex.open) {
          var current = compactIndex.querySelector("a.is-current") || compactIndex.querySelector("a");
          if (current) current.focus();
        }
        return;
      }
      if (action === "smaller") prefs.size = Math.max(16, prefs.size - 1);
      if (action === "larger") prefs.size = Math.min(26, prefs.size + 1);
      if (action === "focus" || action === "focus-panel") { prefs.focus = !prefs.focus; closeReadingIndex(); }
      if (action === "narrow") prefs.wide = false;
      if (action === "wide") prefs.wide = true;
      if (action === "reset") {
        prefs = Object.assign({}, defaults);
        applyTheme(storedTheme() || preferredTheme(), false);
      }
      renderPreferences(true);
    });
    toolbar.addEventListener("change", function (event) {
      var kind = event.target.getAttribute("data-reader");
      if (kind === "appearance") {
        prefs.paper = event.target.value === "paper";
        applyTheme(prefs.paper ? "light" : event.target.value, true);
      } else if (kind === "line") {
        prefs.line = event.target.value;
      }
      renderPreferences(true);
    });
    document.addEventListener("click", function (event) {
      if (!panel.hidden && !toolbar.contains(event.target)) setPanel(false);
      if (compactIndex && compactIndex.open && !compactIndex.contains(event.target) && !toolbar.contains(event.target)) closeReadingIndex();
    });
    if (compactIndex) compactIndex.addEventListener("click", function (event) {
      var link = event.target.closest("a");
      if (!link) return;
      closeReadingIndex();
      if (link.hash && link.getAttribute("href").charAt(0) === "#") {
        var target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
        if (target) { target.setAttribute("tabindex", "-1"); target.focus({ preventScroll: true }); }
      }
    });
    document.addEventListener("keydown", function (event) {
      if (event.key !== "Escape") return;
      if (!panel.hidden) { setPanel(false); settingsButton.focus(); }
      else if (compactIndex && compactIndex.open) { closeReadingIndex(); indexButton.focus(); }
    });
    if (themeButton) themeButton.addEventListener("click", function () { prefs.paper = false; renderPreferences(true); });
    if (prefs.paper) applyTheme("light", false);
    renderPreferences(false);
    if (window.ResizeObserver) new ResizeObserver(function () {
      root.style.setProperty("--ab-reader-toolbar-height", toolbar.offsetHeight + "px");
    }).observe(toolbar);

    // Hide the toolbar while reading down, bring it back on any scroll up.
    var lastY = window.scrollY;
    var hideFrame = false;
    function updateToolbarVisibility() {
      hideFrame = false;
      var y = window.scrollY;
      var busy = !panel.hidden || (compactIndex && compactIndex.open) || (bookMenu && !bookMenu.hidden) || toolbar.contains(document.activeElement);
      if (busy || y < 160 || y < lastY - 4) document.body.classList.remove("ab-tools-hidden");
      else if (y > lastY + 4) document.body.classList.add("ab-tools-hidden");
      lastY = y;
    }
    window.addEventListener("scroll", function () {
      if (!hideFrame) { hideFrame = true; window.requestAnimationFrame(updateToolbarVisibility); }
    }, { passive: true });
    toolbar.addEventListener("focusin", function () { document.body.classList.remove("ab-tools-hidden"); });

    // Reading progress for this page of the chapter.
    var progressFrame = false;
    function updateProgress() {
      progressFrame = false;
      var rect = prose.getBoundingClientRect();
      var span = rect.height - window.innerHeight;
      var ratio = span > 0 ? Math.min(1, Math.max(0, -rect.top / span)) : (rect.top < window.innerHeight ? 1 : 0);
      var pct = Math.round(ratio * 100);
      progressFill.style.transform = "scaleX(" + ratio.toFixed(3) + ")";
      progress.setAttribute("aria-valuenow", String(pct));
      progress.setAttribute("aria-valuetext", faDigits(pct) + "٪");
    }
    function queueProgress() {
      if (!progressFrame) { progressFrame = true; window.requestAnimationFrame(updateProgress); }
    }
    window.addEventListener("scroll", queueProgress, { passive: true });
    window.addEventListener("resize", queueProgress);
    window.addEventListener("load", queueProgress);
    queueProgress();
  }

  // Scrollspy: the TOC follows the section being read (aria-current), the
  // toolbar shows its title, and the position is kept for "continue reading".
  var sections = Array.prototype.slice.call(document.querySelectorAll(".ab-prose > section[id]:not(.ab-section-omitted)"));
  var sectionLinks = Array.prototype.slice.call(document.querySelectorAll('.ab-toc > a[href^="#"], .ab-reader-index nav a[href^="#"]'));
  if (sections.length) {
    var sidebar = document.querySelector(".ab-reader > .ab-toc");
    var titleTarget = toolbar ? toolbar.querySelector(".ab-reading-title") : null;
    var spyFrame = false;
    var currentId = null;
    var resumeTimer = 0;
    function headerOffset() {
      var bar = document.querySelector(".ab-reading-tools");
      var header = document.querySelector(".ab-header");
      var bottom = 0;
      [bar, header].forEach(function (el) {
        if (!el) return;
        var r = el.getBoundingClientRect();
        if (r.bottom > bottom && r.top < 1) bottom = r.bottom;
      });
      return bottom;
    }
    function updateReadingLocation() {
      spyFrame = false;
      var threshold = Math.max(headerOffset() + 24, window.innerHeight * 0.3);
      var current = sections[0];
      for (var i = 0; i < sections.length; i++) {
        if (sections[i].getBoundingClientRect().top <= threshold) current = sections[i];
        else break;
      }
      if (current.id === currentId) return;
      currentId = current.id;
      sectionLinks.forEach(function (link) {
        var active = link.getAttribute("href") === "#" + encodeURIComponent(current.id) || link.getAttribute("href") === "#" + current.id;
        link.classList.toggle("is-current", active);
        if (active) {
          link.setAttribute("aria-current", "location");
          if (sidebar && sidebar.contains(link) && sidebar.scrollHeight > sidebar.clientHeight) {
            var top = link.offsetTop - sidebar.offsetTop;
            if (top < sidebar.scrollTop || top > sidebar.scrollTop + sidebar.clientHeight - link.offsetHeight) {
              sidebar.scrollTop = Math.max(0, top - sidebar.clientHeight / 3);
            }
          }
        } else {
          link.removeAttribute("aria-current");
        }
      });
      var heading = current.querySelector("h2");
      var title = heading ? heading.textContent.trim() : "";
      if (titleTarget) titleTarget.textContent = title;
      if (chapterMap && chapterMap.urls) {
        window.clearTimeout(resumeTimer);
        resumeTimer = window.setTimeout(function () {
          writeResume(currentBook, {
            url: chapterMap.urls[chapterMap.page - 1] + "#" + encodeURIComponent(current.id),
            title: title || chapterMap.title,
            chapter: chapterMap.chapter,
            part: chapterMap.part,
            page: chapterMap.page,
            section: current.id,
            t: Date.now()
          });
        }, 900);
      }
    }
    function queueReadingLocation() {
      if (!spyFrame) {
        spyFrame = true;
        window.requestAnimationFrame(updateReadingLocation);
      }
    }
    if ("IntersectionObserver" in window) {
      // The observed band ends at the same 30% line the current section is
      // judged by, so a crossing in either direction triggers one update.
      var observer = new IntersectionObserver(queueReadingLocation, { rootMargin: "0px 0px -70% 0px", threshold: 0 });
      sections.forEach(function (section) { observer.observe(section); });
    } else {
      window.addEventListener("scroll", queueReadingLocation, { passive: true });
    }
    window.addEventListener("hashchange", queueReadingLocation);
    window.addEventListener("resize", queueReadingLocation);
    window.addEventListener("load", queueReadingLocation);
    queueReadingLocation();
  }

  if (reduceMotion) root.classList.add("ab-reduced-motion");

  if (!bookMenu) return;
  if (menuButton) menuButton.addEventListener("click", function () {
    setBookMenu(bookMenu.hidden, menuButton);
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !bookMenu.hidden) {
      setBookMenu(false);
      if (menuReturn) menuReturn.focus();
    }
  });
  document.addEventListener("click", function (event) {
    if (bookMenu.hidden || bookMenu.contains(event.target)) return;
    if (menuTriggers.some(function (control) { return control.contains(event.target); })) return;
    setBookMenu(false);
  });
})();
