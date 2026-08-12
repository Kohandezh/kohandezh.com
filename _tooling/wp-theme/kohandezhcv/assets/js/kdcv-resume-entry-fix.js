(function () {
  "use strict";

  var locales = {
    fa: {
      date: "۱۴۰۵/۱۲/۲۴",
      role: "Developer — سکوی ملی متن‌باز هوش مصنوعی",
      desc: "توسعه‌دهنده سکوی ملی متن‌باز هوش مصنوعی.",
      alt: "لوگوی سکوی ملی متن‌باز هوش مصنوعی"
    },
    en: {
      date: "1405/12/24",
      role: "Developer — National Open-Source AI Platform",
      desc: "Developer on the National Open-Source AI Platform.",
      alt: "National Open-Source AI Platform logo"
    },
    ar: {
      date: "1405/12/24",
      role: "Developer — المنصة الوطنية مفتوحة المصدر للذكاء الاصطناعي",
      desc: "مطور في المنصة الوطنية مفتوحة المصدر للذكاء الاصطناعي.",
      alt: "شعار المنصة الوطنية مفتوحة المصدر للذكاء الاصطناعي"
    },
    de: {
      date: "1405/12/24",
      role: "Developer — Nationale Open-Source-KI-Plattform",
      desc: "Entwickler der nationalen Open-Source-KI-Plattform.",
      alt: "Logo der nationalen Open-Source-KI-Plattform"
    },
    es: {
      date: "1405/12/24",
      role: "Developer — Plataforma nacional de IA de código abierto",
      desc: "Desarrollador de la plataforma nacional de IA de código abierto.",
      alt: "Logotipo de la plataforma nacional de IA de código abierto"
    },
    fr: {
      date: "1405/12/24",
      role: "Developer — Plateforme nationale d’IA open source",
      desc: "Développeur de la plateforme nationale d’IA open source.",
      alt: "Logo de la plateforme nationale d’IA open source"
    },
    tr: {
      date: "1405/12/24",
      role: "Developer — Ulusal Açık Kaynaklı Yapay Zekâ Platformu",
      desc: "Ulusal açık kaynaklı yapay zekâ platformunda geliştirici.",
      alt: "Ulusal açık kaynaklı yapay zekâ platformu logosu"
    },
    zh: {
      date: "1405/12/24",
      role: "Developer — 国家开源人工智能平台",
      desc: "国家开源人工智能平台开发者。",
      alt: "国家开源人工智能平台标志"
    },
    ja: {
      date: "1405/12/24",
      role: "Developer — 国立オープンソースAIプラットフォーム",
      desc: "国立オープンソースAIプラットフォームの開発者。",
      alt: "国立オープンソースAIプラットフォームのロゴ"
    }
  };

  function locale() {
    return (document.documentElement.getAttribute("lang") || "en").toLowerCase().split("-")[0];
  }

  function element(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (typeof text === "string") node.textContent = text;
    return node;
  }

  function assetBase() {
    var configured = window.KOHANDEZH_THEME_URI ||
      (window.KDCV_CONFIG && window.KDCV_CONFIG.assetBase);
    if (typeof configured === "string" && configured) {
      return configured.replace(/\/$/, "") + "/";
    }
    var scripts = document.getElementsByTagName("script");
    for (var i = scripts.length - 1; i >= 0; i -= 1) {
      var src = scripts[i].src || "";
      var match = src.match(/^(.*\/)assets\/js\/kdcv-resume-entry-fix(?:\.min)?\.js(?:\?.*)?$/);
      if (match) return match[1];
    }
    return "/";
  }

  function addProfileMessengers() {
    var social = document.querySelector(".sidebar-user .user-social");
    if (!social || social.querySelector("[data-kdcv-messenger]")) return;

    // The product/site shortcut is already represented in the work section.
    // Keep the compact profile rail limited to personal channels.
    var companyLink = social.querySelector('a[href="https://ksf.ir"], a[href="https://ksf.ir/"]');
    if (companyLink && companyLink.parentElement) companyLink.parentElement.remove();

    if (!document.getElementById("kdcv-messenger-theme")) {
      var style = document.createElement("style");
      style.id = "kdcv-messenger-theme";
      style.textContent = ".sidebar-user .user-social .kdcv-messenger-link{position:relative;overflow:hidden;color:#fff;border:1px solid rgba(255,255,255,.18);box-shadow:0 8px 18px rgba(0,0,0,.14)}.sidebar-user .user-social .kdcv-messenger-link:hover,.sidebar-user .user-social .kdcv-messenger-link:focus-visible{color:#fff;transform:translateY(-2px) scale(1.04)}.kdcv-messenger-link svg,.kdcv-messenger-brand-image{display:block;width:21px;height:21px;object-fit:contain}.kdcv-messenger-brand-image{filter:brightness(0) invert(1)}body.dark-mode .sidebar-user .user-social .kdcv-messenger-link{background:#fff!important;color:#080808!important;border-color:rgba(0,0,0,.12);box-shadow:0 8px 18px rgba(0,0,0,.34)}body.dark-mode .kdcv-messenger-brand-image{filter:grayscale(1) brightness(0)}";
      document.head.appendChild(style);
    }

    var services = [
      { name: "Telegram", href: "https://t.me/kohandezh", className: "telegram", color: "#229ed9", icon: "telegram" },
      { name: "Bale", href: "https://ble.ir/kohandezh", className: "bale", color: "#08a88a", icon: "bale" },
      { name: "Eitaa", href: "https://eitaa.com/kohandezhh", className: "eitaa", color: "#e37600", icon: "eitaa" },
      { name: "WhatsApp", href: "https://wa.me/18106662283", className: "whatsapp", color: "#25d366", icon: "whatsapp" }
    ];

    function vector(path) {
      var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
      var shape = document.createElementNS("http://www.w3.org/2000/svg", "path");
      svg.setAttribute("viewBox", "0 0 24 24");
      svg.setAttribute("focusable", "false");
      svg.setAttribute("aria-hidden", "true");
      shape.setAttribute("fill", "currentColor");
      shape.setAttribute("d", path);
      svg.appendChild(shape);
      return svg;
    }

    function brandIcon(service) {
      if (service.icon === "bale" || service.icon === "eitaa") {
        var image = document.createElement("img");
        image.className = "kdcv-messenger-brand-image";
        image.width = 22;
        image.height = 22;
        image.alt = "";
        image.decoding = "async";
        image.src = assetBase() + "assets/images/social/" + service.icon + (service.icon === "bale" ? "-logo.png" : ".svg");
        return image;
      }
      if (service.icon === "telegram") return vector("M23.1 3.3 19.6 20.2c-.26 1.16-.95 1.44-1.92.9l-5.3-3.9-2.56 2.46c-.28.28-.52.52-1.07.52l.38-5.4L18.9 6.1c.43-.38-.09-.6-.66-.22L6.1 13.5.83 11.85c-1.14-.36-1.16-1.14.24-1.7L21.6 1.68c.95-.35 1.78.22 1.47 1.64z");
      if (service.icon === "whatsapp") return vector("M12.04 2.16a9.8 9.8 0 0 0-8.3 15l-1.47 5.36 5.5-1.44a9.8 9.8 0 1 0 4.27-18.92zm0 17.82a8.03 8.03 0 0 1-4.1-1.13l-.3-.18-3.26.86.87-3.18-.2-.33a8.04 8.04 0 1 1 6.99 3.96zm4.4-6.03c-.24-.12-1.43-.7-1.65-.78-.22-.08-.38-.12-.54.12-.16.24-.62.78-.76.94-.14.16-.28.18-.52.06-.24-.12-1.01-.37-1.92-1.18-.71-.63-1.18-1.4-1.32-1.64-.14-.24-.02-.37.1-.48.1-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.3-.74-1.78-.2-.47-.4-.4-.54-.4h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.7 2.6 4.12 3.65.57.24 1.02.39 1.37.5.58.19 1.1.16 1.51.1.46-.07 1.43-.59 1.63-1.15.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28z");
      return vector("M3 5.5A2.5 2.5 0 0 1 5.5 3h13A2.5 2.5 0 0 1 21 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 18.5zm2 .4v.35l7 5.25 7-5.25V5.9zm14 2.85-6.4 4.8a1 1 0 0 1-1.2 0L5 8.75v9.75c0 .28.22.5.5.5h13a.5.5 0 0 0 .5-.5z");
    }

    services.forEach(function (service) {
      var item = document.createElement("li");
      var link = document.createElement("a");
      item.setAttribute("data-kdcv-messenger", service.className);
      link.className = "kdcv-messenger-link kdcv-messenger-link--" + service.className;
      link.href = service.href;
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      link.setAttribute("aria-label", service.name + " — " + (service.name === "WhatsApp" ? "+1 810 666 2283" : "@kohandezh"));
      link.title = link.getAttribute("aria-label");
      link.style.backgroundColor = service.color;
      link.style.color = "#fff";
      link.appendChild(brandIcon(service));
      item.appendChild(link);
      social.appendChild(item);
    });
  }

  function addEntry() {
    var section = document.querySelector("#education");
    var timeline = section && section.querySelector(".timeline");
    if (!timeline || timeline.querySelector("[data-kdcv-sako]")) return;

    var copy = locales[locale()] || locales.en;
    var item = element("div", "timeline-item effectFade fadeUp no-div timeline-item--work");
    item.setAttribute("data-kdcv-sako", "true");
    item.appendChild(element("p", "timeline-date text-black-56", copy.date));
    item.appendChild(element("div", "timeline-dot"));

    var content = element("div", "timeline-content");
    var icon = element("div", "icon");
    var image = document.createElement("img");
    var base = assetBase();
    image.src = base + "assets/images/item/sako.webp?v=2";
    image.width = 34;
    image.height = 34;
    image.alt = copy.alt;
    image.loading = "lazy";
    icon.appendChild(image);
    content.appendChild(icon);
    content.appendChild(element("p", "timeline-role fw-medium text-black-72", copy.role));
    content.appendChild(element("p", "timeline-desc text-body-3 text-black-56", copy.desc));

	var figure = element("figure", "sako-project-visual");
	// Keep the injected visual out of the timeline icon column even when an
	// intermediary serves an older cached stylesheet during a rolling deploy.
	figure.style.gridColumn = "1 / -1";
	figure.style.width = "100%";
	var visual = document.createElement("img");
	visual.src = base + "assets/images/portfolio/sako-platform-concept.webp?v=2";
	visual.width = 1536;
	visual.height = 1024;
	visual.loading = "lazy";
	visual.decoding = "async";
	visual.alt = copy.alt + " — conceptual editorial visualization";
	figure.appendChild(visual);
	content.appendChild(figure);
    item.appendChild(content);
    timeline.insertBefore(item, timeline.firstElementChild || null);
  }

  // On small screens the fixed companion must never cover the CEO headline.
  // It becomes available as soon as the visitor has moved past the hero.
  function guardMobileHero() {
    var hero = document.querySelector("#home");
    if (!hero) return;
    var queued = false;
    function update() {
      queued = false;
      var mobile = window.matchMedia("(max-width: 767.98px)").matches;
      var rect = hero.getBoundingClientRect();
      document.body.classList.toggle("kdcv-mobile-hero-active", mobile && rect.bottom > 120 && rect.top < window.innerHeight);
    }
    function schedule() {
      if (!queued) {
        queued = true;
        window.requestAnimationFrame(update);
      }
    }
    update();
    window.addEventListener("scroll", schedule, { passive: true });
    window.addEventListener("resize", schedule, { passive: true });
  }

	function init() { addEntry(); addProfileMessengers(); guardMobileHero(); }
	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
	else init();
})();
