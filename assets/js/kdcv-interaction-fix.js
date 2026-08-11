(function () {
  "use strict";

  var localeLabels = {
    en: { theme: "Appearance", language: "Language", smaller: "Make avatar smaller", larger: "Make avatar larger", switch: "Switch avatar style", size: "Avatar size", hide: "Hide avatar", show: "Show avatar" },
    fa: { theme: "حالت نمایش", language: "زبان", smaller: "کوچک‌تر کردن آواتار", larger: "بزرگ‌تر کردن آواتار", switch: "تغییر سبک آواتار", size: "اندازه آواتار", hide: "مخفی کردن آواتار", show: "نمایش آواتار" },
    ar: { theme: "المظهر", language: "اللغة", smaller: "تصغير الصورة الرمزية", larger: "تكبير الصورة الرمزية", switch: "تبديل نمط الصورة الرمزية", size: "حجم الصورة الرمزية", hide: "إخفاء الصورة الرمزية", show: "إظهار الصورة الرمزية" },
    de: { theme: "Darstellung", language: "Sprache", smaller: "Avatar verkleinern", larger: "Avatar vergrößern", switch: "Avatar-Stil wechseln", size: "Avatargröße", hide: "Avatar ausblenden", show: "Avatar anzeigen" },
    es: { theme: "Apariencia", language: "Idioma", smaller: "Avatar verkleinern", larger: "Avatar vergrößern", switch: "Cambiar estilo del avatar", size: "Tamaño del avatar", hide: "Ocultar avatar", show: "Mostrar avatar" },
    fr: { theme: "Apparence", language: "Langue", smaller: "Réduire l’avatar", larger: "Agrandir l’avatar", switch: "Changer le style de l’avatar", size: "Taille de l’avatar", hide: "Masquer l’avatar", show: "Afficher l’avatar" },
    tr: { theme: "Görünüm", language: "Dil", smaller: "Avatarı küçült", larger: "Avatarı büyüt", switch: "Avatar stilini değiştir", size: "Avatar boyutu", hide: "Avatarı gizle", show: "Avatarı göster" },
    zh: { theme: "外观", language: "语言", smaller: "缩小头像", larger: "放大头像", switch: "切换头像样式", size: "头像大小", hide: "隐藏头像", show: "显示头像" },
    ja: { theme: "外観", language: "言語", smaller: "アバターを小さくする", larger: "アバターを大きくする", switch: "アバタースタイルを切り替える", size: "アバターサイズ", hide: "アバターを隠す", show: "アバターを表示" }
  };
  var avatarSizes = [88, 110, 132, 154, 176];

  function currentLocale() {
    return (document.documentElement.getAttribute("lang") || "en").toLowerCase().split("-")[0];
  }

  function labels() {
    return localeLabels[currentLocale()] || localeLabels.en;
  }

  function closest(element, selector) {
    return element && element.closest ? element.closest(selector) : null;
  }

  function setTheme(dark) {
    var body = document.body;
    if (!body) return;
    body.classList.toggle("dark-mode", dark);
    try { window.localStorage.setItem("darkMode", dark ? "enabled" : "disabled"); } catch (ignore) {}
    document.querySelectorAll(".toggle-switch-mode").forEach(function (button) {
      button.classList.toggle("active", dark);
      button.setAttribute("aria-pressed", dark ? "true" : "false");
    });
    document.querySelectorAll(".image-switch").forEach(function (image) {
      var light = image.getAttribute("data-light") || image.getAttribute("data-light-original");
      var darkSource = image.getAttribute("data-dark");
      if (light && !image.getAttribute("data-light-original")) image.setAttribute("data-light-original", light);
      if (dark && darkSource) image.setAttribute("src", darkSource);
      else if (!dark && light) image.setAttribute("src", light);
    });
  }

  function targetFromLanguageButton(button) {
    var selector = button && (button.getAttribute("data-bs-target") || button.getAttribute("href"));
    if (!selector || selector.charAt(0) !== "#") selector = "#languageMenu";
    try { return document.querySelector(selector) || document.getElementById("languageMenu"); } catch (ignore) { return document.getElementById("languageMenu"); }
  }

  function openLanguage(button) {
    var target = targetFromLanguageButton(button);
    if (!target) return;
    if (window.bootstrap && window.bootstrap.Offcanvas) {
      try {
        window.bootstrap.Offcanvas.getOrCreateInstance(target).show();
        return;
      } catch (ignore) {
        // The bundled Bootstrap/jQuery shim can fail before applying `.show`.
        // Fall through to the small CSS-only fallback below.
      }
    }
    target.classList.add("show");
    target.removeAttribute("aria-hidden");
    target.style.visibility = "visible";
    target.style.transform = "translateX(0)";
    target.setAttribute("aria-hidden", "false");
    target.setAttribute("aria-modal", "true");
    target.setAttribute("role", "dialog");
    document.body.classList.add("offcanvas-open");
  }

  function closeMobileMenu() {
    document.querySelectorAll(".nav-mobile-list, .overlay-pop").forEach(function (element) { element.classList.remove("open"); });
    document.body.classList.remove("overflow-hidden");
    document.querySelectorAll(".btn-mobile-menu").forEach(function (button) { button.classList.remove("close"); });
    document.querySelectorAll(".action-open-mobile").forEach(function (button) { button.setAttribute("aria-expanded", "false"); });
  }

  function toggleMobileMenu(force) {
    var list = document.querySelector(".nav-mobile-list");
    if (!list) return;
    var open = typeof force === "boolean" ? force : !list.classList.contains("open");
    list.classList.toggle("open", open);
    document.querySelectorAll(".overlay-pop").forEach(function (element) { element.classList.toggle("open", open); });
    document.body.classList.toggle("overflow-hidden", open);
    document.querySelectorAll(".btn-mobile-menu").forEach(function (button) { button.classList.toggle("close", open); });
    document.querySelectorAll(".action-open-mobile").forEach(function (button) { button.setAttribute("aria-expanded", open ? "true" : "false"); });
  }

  function ensureMenuLabels() {
    var text = labels();
    document.querySelectorAll(".toggle-switch-mode").forEach(function (button) {
      button.setAttribute("aria-label", text.theme);
      button.removeAttribute("title");
    });
    document.querySelectorAll(".btn-setting-color, .mobile-menu-language").forEach(function (button) {
      button.setAttribute("aria-label", text.language);
      button.removeAttribute("title");
    });
    document.querySelectorAll(".action-open-mobile").forEach(function (button) {
      if (!button.hasAttribute("aria-expanded")) button.setAttribute("aria-expanded", "false");
    });
  }

  /*
   * Keep the language and appearance controls in the same utility group even
   * when the legacy main bundle fails before its DOM-move routine runs.
   */
  function ensureMenuUtilities() {
    var leftBar = document.querySelector(".tf-left-bar");
    var language = leftBar && leftBar.querySelector(".btn-setting-color");
    var navTop = document.querySelector(".sidebar-tools .nav-top");
    var theme = navTop && navTop.querySelector(".toggle-switch-mode");
    if (!language || !navTop || !theme) return false;

    navTop.classList.add("menu-utilities");
    theme.classList.add("menu-utility-control", "menu-utility-theme");
    language.classList.add("menu-utility-control", "menu-utility-language");
    theme.setAttribute("aria-label", labels().theme);
    language.setAttribute("aria-label", labels().language);
    theme.removeAttribute("title");
    language.removeAttribute("title");

    if (language.parentElement !== navTop) navTop.appendChild(language);
    if (leftBar && !leftBar.children.length) leftBar.remove();

    /* Re-create the mobile pair if main.js stopped before cloning it. */
    var mobileRoot = document.querySelector(".nav-mobile-item");
    if (mobileRoot && !mobileRoot.querySelector(".mobile-menu-utilities")) {
      var text = labels();
      var utilities = document.createElement("li");
      utilities.className = "mobile-menu-utilities";
      var mobileTheme = document.createElement("button");
      mobileTheme.type = "button";
      mobileTheme.className = "mobile-menu-utility toggle-switch-mode";
      mobileTheme.setAttribute("aria-label", text.theme);
      mobileTheme.innerHTML = '<i class="icon icon-light" aria-hidden="true"></i><span>' + text.theme + "</span>";
      var mobileLanguage = language.cloneNode(true);
      mobileLanguage.className = "mobile-menu-utility mobile-menu-language";
      mobileLanguage.removeAttribute("title");
      mobileLanguage.innerHTML = '<span class="language-button-label" aria-hidden="true">' + currentLocale().toUpperCase() + "</span><span>" + text.language + "</span>";
      utilities.appendChild(mobileTheme);
      utilities.appendChild(mobileLanguage);
      mobileRoot.insertBefore(utilities, mobileRoot.firstChild);
    }
    return true;
  }

  function makeAvatarButton(className, text, label) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = className;
    button.textContent = text;
    button.setAttribute("aria-label", label);
    button.title = label;
    return button;
  }

  function ensureAvatarControls() {
    var root = document.getElementById("kdcv-pet-root");
    var api = window.KohanAvatar;
    if (!root || !api || typeof api.setSize !== "function") return false;
    var existingControls = root.querySelector(".kohan-size-controls");
    var existingEye = root.querySelector(".kohan-eye-button");
    if (existingControls) {
      if (existingEye && existingEye.parentElement !== existingControls) existingControls.appendChild(existingEye);
      return true;
    }
    var text = labels();
    var controls = document.createElement("div");
    controls.className = "kohan-size-controls kohan-size-controls-fallback";
    controls.setAttribute("role", "group");
    controls.setAttribute("aria-label", text.size);
    var smaller = makeAvatarButton("kohan-size-button", "−", text.smaller);
    var swap = makeAvatarButton("kohan-size-button kohan-switch-button", "⇄", text.switch);
    var larger = makeAvatarButton("kohan-size-button", "+", text.larger);
    swap.setAttribute("aria-pressed", api.isClassic && api.isClassic() ? "true" : "false");
    controls.appendChild(smaller);
    controls.appendChild(swap);
    controls.appendChild(larger);
    if (existingEye) controls.appendChild(existingEye);
    root.appendChild(controls);

    function update() {
      var size = typeof api.getSize === "function" ? Number(api.getSize()) : 132;
      var index = avatarSizes.indexOf(size);
      if (index < 0) index = 2;
      smaller.disabled = index === 0;
      larger.disabled = index === avatarSizes.length - 1;
      swap.setAttribute("aria-pressed", api.isClassic && api.isClassic() ? "true" : "false");
    }
    function stop(event) { event.stopPropagation(); }
    ["pointerdown", "pointerup", "click"].forEach(function (eventName) { controls.addEventListener(eventName, stop); });
    smaller.addEventListener("click", function (event) {
      event.preventDefault();
      var index = avatarSizes.indexOf(Number(api.getSize && api.getSize()));
      api.setSize(avatarSizes[Math.max(0, index - 1)] || 88);
      update();
    });
    larger.addEventListener("click", function (event) {
      event.preventDefault();
      var index = avatarSizes.indexOf(Number(api.getSize && api.getSize()));
      api.setSize(avatarSizes[Math.min(avatarSizes.length - 1, index + 1)] || 176);
      update();
    });
    swap.addEventListener("click", function (event) {
      event.preventDefault();
      if (typeof api.toggleClassic === "function") api.toggleClassic();
      update();
    });
    update();
    return true;
  }

  function bindLanguageButtons() {
    document.querySelectorAll(".btn-setting-color, .mobile-menu-language").forEach(function (button) {
      if (button.getAttribute("data-kdcv-language-fix") === "true") return;
      button.setAttribute("data-kdcv-language-fix", "true");
      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.__kdcvLanguageHandled = true;
        event.stopPropagation();
        openLanguage(button);
      }, true);
    });
  }

  /* Handle locale selection without relying on the broken Bootstrap/jQuery
     event chain. The destination is supplied by each page's data-href. */
  function bindLanguageItems() {
    document.querySelectorAll(".lang-item").forEach(function (item) {
      if (item.getAttribute("data-kdcv-locale-fix") === "true") return;
      item.setAttribute("data-kdcv-locale-fix", "true");
      item.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var locale = (item.getAttribute("data-lang") || "").toLowerCase();
        if (!locale) return;
        try {
          window.localStorage.setItem("siteLang", locale);
          window.sessionStorage.setItem("kdcvExplicitLocale", locale);
        } catch (ignore) {}
        var root = document.documentElement;
        var current = currentLocale();
        var href = item.getAttribute("data-href");
        if (href && locale !== current) {
          window.location.href = href;
          return;
        }
        document.querySelectorAll(".lang-item").forEach(function (entry) { entry.classList.remove("is-active"); });
        item.classList.add("is-active");
        root.setAttribute("lang", locale);
        root.setAttribute("dir", item.getAttribute("data-dir") || (locale === "fa" || locale === "ar" ? "rtl" : "ltr"));
        var menu = document.getElementById("languageMenu");
        if (menu) {
          menu.classList.remove("show");
          menu.style.visibility = "";
          menu.style.transform = "";
          menu.setAttribute("aria-hidden", "true");
          document.body.classList.remove("offcanvas-open");
        }
        ensureMenuLabels();
        ensureMenuUtilities();
      }, true);
    });
  }

  /* The legacy navigation bundle is optional on a few pages. Keep the hash
     links usable with a small native fallback, while preserving the existing
     smooth-scroll behavior when it is present. */
  function bindNavigationLinks() {
    document.querySelectorAll(".sidebar-tools .scroll-link, .nav-mobile-list .scroll-link").forEach(function (link) {
      if (link.getAttribute("data-kdcv-nav-fix") === "true") return;
      link.setAttribute("data-kdcv-nav-fix", "true");
      link.addEventListener("click", function (event) {
        var href = link.getAttribute("href") || "";
        if (href.charAt(0) !== "#") return;
        var target = document.querySelector(href);
        if (!target) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        try { target.scrollIntoView({ behavior: "smooth", block: "start" }); } catch (ignore) { target.scrollIntoView(); }
        try { window.history.replaceState(null, "", href); } catch (ignoreHistory) {}
        document.querySelectorAll(".sidebar-tools .item-link, .nav-mobile-list .item-link").forEach(function (item) { item.classList.remove("active"); });
        link.classList.add("active");
        closeMobileMenu();
      }, true);
    });
  }

  /* Bootstrap collapse is unreliable with the local jQuery shim. Use a
     small, accessible accordion implementation for the service tiles. */
  function bindServiceAccordions() {
    document.querySelectorAll("#accordion-service .accordion-action[data-bs-target]").forEach(function (action) {
      var selector = action.getAttribute("data-bs-target");
      var target = selector && document.querySelector(selector);
      if (!target) return;
      if (action.getAttribute("data-kdcv-service-fix") !== "true") {
        action.setAttribute("data-kdcv-service-fix", "true");
        action.setAttribute("tabindex", "0");
        action.addEventListener("click", function (event) {
          event.preventDefault();
          event.stopImmediatePropagation();
          var open = !target.classList.contains("show");
          document.querySelectorAll("#accordion-service .accordion-action[data-bs-target]").forEach(function (otherAction) {
            var otherTarget = document.querySelector(otherAction.getAttribute("data-bs-target"));
            if (!otherTarget) return;
            var isCurrent = otherTarget === target;
            otherTarget.classList.remove("collapsing");
            otherTarget.classList.toggle("show", isCurrent && open);
            otherTarget.style.display = isCurrent && open ? "block" : "none";
            otherAction.classList.toggle("collapsed", !(isCurrent && open));
            otherAction.setAttribute("aria-expanded", isCurrent && open ? "true" : "false");
          });
        }, true);
        action.addEventListener("keydown", function (event) {
          if (event.key !== "Enter" && event.key !== " ") return;
          event.preventDefault();
          action.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        });
      }
      var isOpen = target.classList.contains("show");
      action.classList.toggle("collapsed", !isOpen);
      action.setAttribute("aria-expanded", isOpen ? "true" : "false");
      target.style.display = isOpen ? "block" : "none";
    });
  }

  function ensureCounters() {
    var lang = currentLocale();
    document.querySelectorAll(".counter .number[data-to]").forEach(function (number) {
      var target = Number(number.getAttribute("data-to"));
      if (!Number.isFinite(target)) return;
      var value = number.textContent.trim();
      if (value !== "0" && value !== "") return;
      try {
        number.textContent = target.toLocaleString(lang === "fa" ? "fa-IR" : (lang === "ar" ? "ar" : "en-US"));
      } catch (ignore) {
        number.textContent = String(target);
      }
    });
  }

  function relayNudgeToChat(message) {
    if (!message) return;
    var log = document.querySelector("#kdcv-pet-log");
    if (!log) return;
    var alreadyRelayed = Array.prototype.some.call(
      log.querySelectorAll("[data-kdcv-relayed]"),
      function (row) { return row.getAttribute("data-kdcv-relayed") === message; }
    );
    if (alreadyRelayed) return;
    var row = document.createElement("div");
    row.className = "kdcv-pet-message kdcv-pet-message-assistant";
    row.setAttribute("data-kdcv-relayed", message);
    var bubble = document.createElement("div");
    bubble.className = "kdcv-pet-message-bubble";
    bubble.textContent = message;
    row.appendChild(bubble);
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
  }

  /* Keep proactive copy as a bubble above the avatar. It is intentionally
     inert until clicked; clicking the bubble opens the chat and mirrors the
     exact text into the chat log. */
  function bindNudgeToChat(nudge) {
    if (!nudge || nudge.getAttribute("data-chat-relay-bound") === "true") return;
    var button = nudge.querySelector(".kdcv-pet-nudge-button");
    var message = button && button.textContent.trim();
    if (!message) return;
    nudge.setAttribute("data-chat-relay-bound", "true");
    button.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var text = button.textContent.trim();
      if (window.KDCVPet && typeof window.KDCVPet.open === "function") window.KDCVPet.open();
      window.setTimeout(function () { relayNudgeToChat(text); }, 80);
    }, true);
  }

  function start() {
    ensureMenuLabels();
    ensureMenuUtilities();
    bindLanguageButtons();
    bindLanguageItems();
    bindNavigationLinks();
    bindServiceAccordions();
    ensureCounters();
    setTheme(document.body.classList.contains("dark-mode"));
    document.addEventListener("click", function (event) {
      var theme = closest(event.target, ".toggle-switch-mode");
      if (theme) {
        event.preventDefault();
        event.stopImmediatePropagation();
        setTheme(!document.body.classList.contains("dark-mode"));
        return;
      }
      var language = closest(event.target, ".btn-setting-color, .mobile-menu-language");
      if (language) {
        event.preventDefault();
        if (event.__kdcvLanguageHandled) return;
        event.__kdcvLanguageHandled = true;
        event.stopPropagation();
        openLanguage(language);
        return;
      }
      var overlay = closest(event.target, ".overlay-pop");
      if (overlay) {
        event.preventDefault();
        event.stopImmediatePropagation();
        closeMobileMenu();
        return;
      }
      var hamburger = closest(event.target, ".action-open-mobile > .tf-btn-icon");
      if (hamburger) {
        event.preventDefault();
        event.stopImmediatePropagation();
        toggleMobileMenu();
      }
    }, true);
    document.addEventListener("keydown", function (event) {
      var hamburger = closest(event.target, ".action-open-mobile");
      if (!hamburger || (event.key !== "Enter" && event.key !== " ")) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      toggleMobileMenu();
    }, true);
    document.addEventListener("kdcvpet:ready", function () {
      ensureAvatarControls();
      ensureMenuUtilities();
      bindLanguageButtons();
      bindLanguageItems();
      bindNavigationLinks();
      bindServiceAccordions();
      ensureCounters();
      var nudge = document.querySelector(".kdcv-pet-nudge");
      if (nudge) bindNudgeToChat(nudge);
    });
    var observer = new MutationObserver(function (records) {
      records.forEach(function (record) {
        Array.prototype.forEach.call(record.addedNodes || [], function (node) {
          if (node.nodeType !== 1) return;
          if (node.matches && node.matches(".kdcv-pet-nudge")) bindNudgeToChat(node);
          if (node.querySelector) {
            var nudge = node.querySelector(".kdcv-pet-nudge");
            if (nudge) bindNudgeToChat(nudge);
          }
        });
      });
      var nudge = document.querySelector(".kdcv-pet-nudge");
      if (nudge) bindNudgeToChat(nudge);
      ensureAvatarControls();
      bindLanguageButtons();
      bindNavigationLinks();
      ensureCounters();
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["hidden", "aria-hidden", "class", "style"]
    });
    var attempts = 0;
    var timer = window.setInterval(function () {
      attempts += 1;
      if (ensureAvatarControls() || attempts > 40) window.clearInterval(timer);
      ensureMenuUtilities();
      bindLanguageItems();
      bindServiceAccordions();
    }, 150);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
