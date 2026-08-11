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
    image.src = "assets/images/item/sako.webp?v=1";
    image.width = 34;
    image.height = 34;
    image.alt = copy.alt;
    image.loading = "lazy";
    icon.appendChild(image);
    content.appendChild(icon);
    content.appendChild(element("p", "timeline-role fw-medium text-black-72", copy.role));
    content.appendChild(element("p", "timeline-desc text-body-3 text-black-56", copy.desc));
    item.appendChild(content);
    timeline.insertBefore(item, timeline.firstElementChild || null);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", addEntry);
  else addEntry();
})();
