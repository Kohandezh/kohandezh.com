/**
 * Kohan Avatar — chat panel.
 *
 * A compact AI chat panel that anchors to the SIDE of the Kohan avatar and
 * opens toward the viewport interior (to the avatar's right when it sits on
 * the left, to its left when it sits on the right) — never above its head.
 * Styled as a glowing assistant. Talks to the site's AI chat REST route and
 * drives the avatar's moods through the documented kohan:avatar:mood event.
 *
 * v3: the conversation lives in sessionStorage for the whole visit. On the
 * next page the history is still there, the panel reopens if it was open,
 * and Kohan says where the visitor has arrived. Every question carries the
 * page, the section in view and the last turns, so answers are grounded
 * (kdcv/v1/ask retrieves from the whole site, the book included) and can
 * cite their sources.
 *
 * Public: window.KohanChat.open({question}), .ask(q), .close(), .clear()
 *
 * Enqueued only when options.chat is enabled.
 */
(function () {
  "use strict";
  if (window.__KOHAN_CHAT__) return;
  window.__KOHAN_CHAT__ = true;

  var CFG = window.KohanAvatarConfig || {};
  var OPTS = CFG.options || {};
  if (!OPTS.chat) return;

  // Language-aware defaults keyed off the page <html lang>. Server-provided
  // CFG.strings still win when present (they're already translated in PHP).
  var LANG = (document.documentElement.getAttribute("lang") || "en").slice(0, 2).toLowerCase();
  var PACKS = {
    en: {
      title: "Kohan", status: "AI assistant", placeholder: "Ask me anything…",
      send: "Send", open: "Open chat", close: "Close chat",
      greeting: "Hi! I'm Kohan. Ask me about the work, projects or how to get in touch.",
      error: "I couldn't reach the assistant right now. Please use the contact form.",
    },
    fa: {
      title: "کهن", status: "دستیار هوش مصنوعی", placeholder: "هرچی می‌خوای بپرس…",
      send: "ارسال", open: "باز کردن گفتگو", close: "بستن گفتگو",
      greeting: "سلام! من کهن هستم. درباره‌ی کارها، پروژه‌ها یا راه‌های تماس ازم بپرس.",
      error: "الان نتونستم به دستیار وصل بشم. لطفاً از فرم تماس استفاده کن.",
    },
    ar: {
      title: "كوهان", status: "مساعد الذكاء الاصطناعي", placeholder: "اسألني أي شيء…",
      send: "إرسال", open: "فتح المحادثة", close: "إغلاق المحادثة",
      greeting: "مرحبًا! أنا كوهان. اسألني عن الأعمال والمشاريع أو طرق التواصل.",
      error: "تعذّر الوصول إلى المساعد الآن. يُرجى استخدام نموذج الاتصال.",
    },
  };
  // The line under the title names the human the twin speaks for, not the
  // assistant's short handle.
  var NAMES = {
    "en": "Mohammad Kohandezh",
    "de": "Mohammad Kohandezh",
    "es": "Mohammad Kohandezh",
    "fr": "Mohammad Kohandezh",
    "tr": "Mohammad Kohandezh",
    "fa": "محمد کهن‌دژ",
    "ar": "محمد كهن‌دژ",
    "zh": "穆罕默德·科汉德泽",
    "ja": "モハンマド・コハンデジュ",
    "ru": "Мохаммад Кохандеж"
  };

  // Panel title. The assistant is presented as the owner's digital twin, so
  // the header names it that way in every language the site serves.
  var TITLES = {
    "en": "Chat with Kohandezh's digital twin",
    "fa": "صحبت با دوقلوی دیجیتال کهن‌دژ",
    "ar": "دردشة مع التوأم الرقمي لكهندژ",
    "de": "Chat mit Kohandezhs digitalem Zwilling",
    "es": "Habla con el gemelo digital de Kohandezh",
    "fr": "Discutez avec le jumeau numérique de Kohandezh",
    "tr": "Kohandezh’in dijital ikiziyle sohbet",
    "zh": "与科汉德泽的数字分身对话",
    "ja": "コハンデジュのデジタルツインと対話",
    "ru": "Чат с цифровым двойником Кохандежа"
  };

  // Voice, call and live-avatar controls. They are rendered DISABLED on
  // purpose: the features are not built yet, and a grey, clearly-inert
  // control is honest about that while reserving its place in the layout,
  // so turning them on later is a one-line change and not a redesign.
  var SOON = {
    "en": [
      "Voice input",
      "Voice call",
      "Video call with the live avatar",
      "coming soon"
    ],
    "fa": [
      "ورودی صوتی",
      "تماس صوتی",
      "تماس تصویری با آواتار زنده",
      "به‌زودی"
    ],
    "ar": [
      "إدخال صوتي",
      "مكالمة صوتية",
      "مكالمة مرئية مع الأفاتار المباشر",
      "قريبًا"
    ],
    "de": [
      "Spracheingabe",
      "Sprachanruf",
      "Videoanruf mit dem Live-Avatar",
      "in Kürze"
    ],
    "es": [
      "Entrada de voz",
      "Llamada de voz",
      "Videollamada con el avatar en vivo",
      "próximamente"
    ],
    "fr": [
      "Saisie vocale",
      "Appel vocal",
      "Appel vidéo avec l’avatar en direct",
      "bientôt"
    ],
    "tr": [
      "Sesli giriş",
      "Sesli arama",
      "Canlı avatarla görüntülü arama",
      "çok yakında"
    ],
    "zh": [
      "语音输入",
      "语音通话",
      "与实时分身视频通话",
      "即将推出"
    ],
    "ja": [
      "音声入力",
      "音声通話",
      "ライブアバターとビデオ通話",
      "近日公開"
    ],
    "ru": [
      "Голосовой ввод",
      "Голосовой звонок",
      "Видеозвонок с живым аватаром",
      "скоро"
    ]
  };
  var SOON_LIST = SOON[LANG] || SOON.en;

  var P = PACKS[LANG] || PACKS.en;
  var T = CFG.strings || {};
  // Server strings win only if the page language matches the server locale;
  // otherwise use the language pack so a Persian page never shows English.
  var useServer = LANG === "en";
  var L = {};
  ["title", "status", "placeholder", "send", "open", "close", "greeting", "error"].forEach(function (k) {
    L[k] = (useServer && T[k]) || P[k];
  });
  // The header shows the full "digital twin" title in the page language; the
  // short name stays as the status line underneath it.
  L.status = NAMES[LANG] || NAMES.en;
  L.title = TITLES[LANG] || TITLES.en;

  // The six opening questions the theme's own chat UI has always offered,
  // copied verbatim from assets/js/chat-ui.js so the two never disagree.
  // They vanished from WordPress when the theme stopped loading ai-pet.js
  // (the plugin now owns the avatar), which left this panel with nothing
  // between the greeting and the input box.
  var TOPICS = {
    en: ["Career", "Achievements", "Projects", "Skills", "Education", "Contact"],
    fa: ["سوابق شغلی", "دستاوردها", "پروژه‌ها", "مهارت‌ها", "تحصیلات", "تماس"],
    ar: ["المسيرة المهنية", "الإنجازات", "المشاريع", "المهارات", "التعليم", "التواصل"],
    de: ["Werdegang", "Erfolge", "Projekte", "Fähigkeiten", "Ausbildung", "Kontakt"],
    es: ["Trayectoria", "Logros", "Proyectos", "Habilidades", "Formación", "Contacto"],
    fr: ["Parcours", "Réalisations", "Projets", "Compétences", "Formation", "Contact"],
    tr: ["Kariyer", "Başarılar", "Projeler", "Yetkinlikler", "Eğitim", "İletişim"],
    zh: ["职业经历", "成就", "项目", "技能", "教育背景", "联系方式"],
    ja: ["職歴", "実績", "プロジェクト", "スキル", "学歴", "連絡先"],
    ru: ["Карьера", "Достижения", "Проекты", "Навыки", "Образование", "Контакты"],
  };
  var TOPIC_LIST = TOPICS[LANG] || TOPICS.en;


  // v3 strings: memory, page context, sources.
  var MORE = {
    fa: { clear: "پاک کردن گفتگو", nowOn: "حالا در «{t}» هستی.", hint: "هر سؤالی دربارهٔ این صفحه یا هر جای سایت داری بپرس.", sources: "منابع", place: "📍 {t}",
          chips: { book: ["خلاصهٔ این بخش", "نکات کلیدی این بخش", "این بخش به کدام منبع استناد می‌کند؟"], page: ["این صفحه دربارهٔ چیست؟"] },
          q: { "خلاصهٔ این بخش": "بخش «{t}» را خلاصه کن.", "نکات کلیدی این بخش": "نکات کلیدی بخش «{t}» چیست؟", "این بخش به کدام منبع استناد می‌کند؟": "بخش «{t}» به کدام منبع یا استاندارد استناد می‌کند؟", "این صفحه دربارهٔ چیست؟": "صفحهٔ «{t}» دربارهٔ چیست؟" } },
    en: { clear: "Clear conversation", nowOn: "You're now on «{t}».", hint: "Ask me anything about this page — or anything else on the site.", sources: "Sources", place: "📍 {t}",
          chips: { book: ["Summarise this section", "Key points", "Which source does it cite?"], page: ["What is this page about?"] },
          q: { "Summarise this section": "Summarise the section «{t}».", "Key points": "What are the key points of «{t}»?", "Which source does it cite?": "Which source or standard does «{t}» rely on?", "What is this page about?": "What is the page «{t}» about?" } },
    ar: { clear: "مسح المحادثة", nowOn: "أنت الآن في «{t}».", hint: "اسألني عن هذه الصفحة أو عن أي شيء في الموقع.", sources: "المصادر", place: "📍 {t}",
          chips: { book: ["لخّص هذا القسم", "النقاط الرئيسية"], page: ["عمّ تتحدث هذه الصفحة؟"] },
          q: { "لخّص هذا القسم": "لخّص قسم «{t}».", "النقاط الرئيسية": "ما النقاط الرئيسية في «{t}»؟", "عمّ تتحدث هذه الصفحة؟": "عمّ تتحدث صفحة «{t}»؟" } },
    de: { clear: "Unterhaltung löschen", nowOn: "Du bist jetzt auf «{t}».", hint: "Frag mich etwas zu dieser Seite oder zur ganzen Website.", sources: "Quellen", place: "📍 {t}",
          chips: { book: [], page: ["Worum geht es auf dieser Seite?"] }, q: { "Worum geht es auf dieser Seite?": "Worum geht es auf der Seite «{t}»?" } },
    es: { clear: "Borrar conversación", nowOn: "Ahora estás en «{t}».", hint: "Pregúntame sobre esta página o sobre todo el sitio.", sources: "Fuentes", place: "📍 {t}",
          chips: { book: [], page: ["¿De qué trata esta página?"] }, q: { "¿De qué trata esta página?": "¿De qué trata la página «{t}»?" } },
    fr: { clear: "Effacer la conversation", nowOn: "Vous êtes sur «{t}».", hint: "Posez-moi une question sur cette page ou sur tout le site.", sources: "Sources", place: "📍 {t}",
          chips: { book: [], page: ["De quoi parle cette page ?"] }, q: { "De quoi parle cette page ?": "De quoi parle la page «{t}» ?" } },
    tr: { clear: "Sohbeti temizle", nowOn: "Şu an «{t}» sayfasındasın.", hint: "Bu sayfa ya da sitenin tamamı hakkında sor.", sources: "Kaynaklar", place: "📍 {t}",
          chips: { book: [], page: ["Bu sayfa ne hakkında?"] }, q: { "Bu sayfa ne hakkında?": "«{t}» sayfası ne hakkında?" } },
    zh: { clear: "清除对话", nowOn: "你现在在「{t}」。", hint: "关于本页或整个网站，尽管问我。", sources: "来源", place: "📍 {t}",
          chips: { book: [], page: ["这个页面讲什么？"] }, q: { "这个页面讲什么？": "「{t}」这个页面讲什么？" } },
    ja: { clear: "会話を消去", nowOn: "いまは「{t}」にいます。", hint: "このページやサイト全体について何でも聞いてください。", sources: "出典", place: "📍 {t}",
          chips: { book: [], page: ["このページは何について？"] }, q: { "このページは何について？": "「{t}」のページは何について書かれていますか？" } },
    ru: { clear: "Очистить разговор", nowOn: "Вы на странице «{t}».", hint: "Спросите об этой странице или о чём угодно на сайте.", sources: "Источники", place: "📍 {t}",
          chips: { book: [], page: ["О чём эта страница?"] }, q: { "О чём эта страница?": "О чём страница «{t}»?" } },
  };
  var M = MORE[LANG] || MORE.en;
  var reduced = !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);
  function fmt(tpl, t) { return String(tpl).replace("{t}", t || ""); }

  /* ---- session memory -------------------------------------------------- */
  var STORE = "kohanChat.v3";
  var MAX_MSGS = 40;
  var here = location.pathname + location.search.replace(/[?&](kbk_section|ab_section)=[^&]*/g, "");
  function load() {
    try {
      var s = JSON.parse(sessionStorage.getItem(STORE) || "null");
      if (s && Array.isArray(s.msgs)) return s;
    } catch (e) {}
    return { msgs: [], open: false, path: "" };
  }
  var mem = load();
  function save() {
    try {
      mem.msgs = mem.msgs.slice(-MAX_MSGS);
      sessionStorage.setItem(STORE, JSON.stringify(mem));
    } catch (e) {}
  }

  var root = null,   // avatar root
    launcher = null,
    panel = null,
    log = null,
    input = null,
    chips = null,
    open = false,
    busy = false,
    dir = "ltr";

  function fire(mood) {
    try {
      window.dispatchEvent(new CustomEvent("kohan:avatar:mood", { detail: { mood: mood, returnTo: "idle" } }));
    } catch (e) {}
  }

  function el(tag, cls, attrs) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (attrs) Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    return n;
  }

  function findRoot() {
    return document.querySelector(".kohan-avatar-root");
  }

  function pageInfo() {
    var T = window.KohanTalk;
    var p = T ? T.page() : { title: document.title, kind: "page", url: location.href, lang: LANG };
    var sec = T ? T.currentSection() : null;
    return {
      url: location.href.split("#")[0],
      title: (p.title || "").slice(0, 200),
      kind: p.kind,
      section: sec ? { id: (sec.id || "").slice(0, 80), title: (sec.title || "").slice(0, 200), summary: (sec.summary || "").slice(0, 400) } : null,
    };
  }

  function sideOfAvatar() {
    // Which side of the avatar has more room -> panel opens there (never above).
    var r = root.getBoundingClientRect();
    var spaceRight = window.innerWidth - r.right;
    var spaceLeft = r.left;
    return spaceRight >= spaceLeft ? "right" : "left";
  }

  function positionPanel() {
    if (!panel || !root) return;
    var r = root.getBoundingClientRect();
    var side = sideOfAvatar();
    var gap = 14;
    var pw = panel.offsetWidth || 340;
    var ph = panel.offsetHeight || 440;
    var left = side === "right" ? r.right + gap : r.left - gap - pw;
    // vertical: align panel bottom near avatar bottom, clamp into viewport.
    var top = r.bottom - ph;
    top = Math.max(12, Math.min(top, window.innerHeight - ph - 12));
    left = Math.max(12, Math.min(left, window.innerWidth - pw - 12));
    panel.style.left = left + "px";
    panel.style.top = top + "px";
    panel.setAttribute("data-side", side);
  }

  function buildLauncher() {
    launcher = el("button", "kohan-chat-launcher", {
      type: "button",
      "aria-label": L.open,
      "aria-expanded": "false",
    });
    launcher.innerHTML =
      '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">' +
      '<path fill="currentColor" d="M12 3C6.5 3 2 6.7 2 11.2c0 2.5 1.4 4.7 3.6 6.2-.1.9-.5 2.1-1.4 3.1 1.6-.2 3.2-.9 4.4-1.9 1 .3 2.1.4 3.4.4 5.5 0 10-3.7 10-8.2S17.5 3 12 3Z"/></svg>';
    if (mem.msgs.length) launcher.setAttribute("data-has-history", "");
    document.body.appendChild(launcher);
    launcher.addEventListener("click", function () { toggle(); });
    positionLauncher();
  }

  function positionLauncher() {
    if (!launcher || !root) return;
    // Sit directly above the control column and share its centre line.
    var col = root.querySelector(".kohan-size-controls");
    if (col) {
      var c = col.getBoundingClientRect();
      if (c.width && c.height) {
        var size = launcher.offsetWidth || 28;
        launcher.style.top = c.top - size - 6 + "px";
        launcher.style.left = c.left + (c.width - size) / 2 + "px";
        return;
      }
    }
    var r = root.getBoundingClientRect();
    var side = sideOfAvatar();
    launcher.style.top = r.top - 6 + "px";
    launcher.style.left = (side === "right" ? r.right - 20 : r.left - 16) + "px";
  }

  // The avatar MOVES (drag, throw, walk); follow it, one layout read a frame.
  var followTimer = 0;
  function followAvatar() {
    if (followTimer) return;
    followTimer = window.setTimeout(function () {
      followTimer = 0;
      positionLauncher();
      if (open) positionPanel();
    }, 16);
  }

  function buildPanel() {
    panel = el("section", "kohan-chat-panel", { role: "dialog", "aria-label": L.title });
    dir = document.documentElement.getAttribute("dir") || "ltr";
    panel.setAttribute("dir", dir);

    var head = el("header", "kohan-chat-head");
    if (CFG.chatAvatar) {
      head.appendChild(el("img", "kohan-chat-face", {
        src: CFG.chatAvatar, alt: "", width: "40", height: "40", loading: "lazy", decoding: "async",
      }));
    }
    var title = el("div", "kohan-chat-title");
    title.innerHTML = "<strong>" + escapeHtml(L.title) + "</strong><span>" + escapeHtml(L.status) + "</span>";
    var clear = el("button", "kohan-chat-clear", { type: "button", "aria-label": M.clear, title: M.clear });
    clear.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm-3 6h12l-1 12H7L6 9zm4 2v8h2v-8h-2zm4 0v8h2v-8h-2z"/></svg>';
    clear.addEventListener("click", clearAll);
    var close = el("button", "kohan-chat-close", { type: "button", "aria-label": L.close });
    close.textContent = "×";
    close.addEventListener("click", function () { toggle(false); });
    head.appendChild(title);
    head.appendChild(clear);
    head.appendChild(close);

    log = el("div", "kohan-chat-log", { role: "log", "aria-live": "polite" });

    var form = el("form", "kohan-chat-form");
    input = el("input", "kohan-chat-input", {
      type: "text", placeholder: L.placeholder, autocomplete: "off", "aria-label": L.placeholder, maxlength: "500",
    });
    var tools = el("div", "kohan-chat-tools");
    [
      ["mic", SOON_LIST[0], "M12 15a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-2.08A7 7 0 0 0 19 12z"],
      ["call", SOON_LIST[1], "M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .57 3.6 1 1 0 0 1-.25 1z"],
      ["video", SOON_LIST[2], "M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11z"],
    ].forEach(function (spec) {
      var b = el("button", "kohan-chat-tool kohan-chat-tool--" + spec[0], {
        type: "button", disabled: "disabled", "aria-disabled": "true",
        title: spec[1] + " — " + SOON_LIST[3], "aria-label": spec[1] + " — " + SOON_LIST[3],
      });
      b.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><path fill="currentColor" d="' + spec[2] + '"/></svg>';
      tools.appendChild(b);
    });
    var sendBtn = el("button", "kohan-chat-send", { type: "submit", "aria-label": L.send });
    sendBtn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M3 20.5 21 12 3 3.5 3 10l12 2-12 2z"/></svg>';
    form.appendChild(tools);
    form.appendChild(input);
    form.appendChild(sendBtn);
    form.addEventListener("submit", onSend);

    chips = el("div", "kohan-chat-chips");

    panel.appendChild(head);
    panel.appendChild(log);
    panel.appendChild(chips);
    panel.appendChild(form);
    document.body.appendChild(panel);
    panel.addEventListener("keydown", function (e) { if (e.key === "Escape") toggle(false); });

    // Replay the visit's conversation, then greet (first time) or say where
    // the visitor has arrived (a new page with history behind it).
    if (!mem.msgs.length) {
      record("bot", L.greeting);
    } else {
      mem.msgs.forEach(function (m) { render(m); });
      if (mem.path && mem.path !== here) arrivedNote();
    }
    mem.path = here;
    save();
    renderChips();
  }

  // One short line: where the visitor is, and an invitation. The section's
  // summary is NOT pasted in — that is what the chips and questions are for.
  function arrivedNote() {
    var p = pageInfo();
    var t = shortTitle(p.title);
    record("note", fmt(M.place, t));
    record("bot", fmt(M.nowOn, t) + " " + M.hint);
  }

  function shortTitle(t) {
    t = String(t || "").split(/\s[·|–—]\s/)[0].trim();
    return t.length > 60 ? t.slice(0, 58).replace(/\s+\S*$/, "") + "…" : t;
  }

  function renderChips() {
    if (!chips) return;
    chips.textContent = "";
    var p = pageInfo();
    var list = [];
    var topic = p.section && p.section.title ? p.section.title : p.title;
    if (/^book/.test(p.kind) && M.chips.book.length) list = list.concat(M.chips.book.map(function (c) { return { label: c, q: fmt(M.q[c] || c, topic) }; }));
    else if (M.chips.page.length && p.kind !== "cv") list = list.concat(M.chips.page.map(function (c) { return { label: c, q: fmt(M.q[c] || c, p.title) }; }));
    TOPIC_LIST.forEach(function (label) { list.push({ label: label, q: label }); });
    list.forEach(function (c) {
      var chip = el("button", "kohan-chat-chip", { type: "button" });
      chip.textContent = c.label;
      chip.addEventListener("click", function () { ask(c.q); });
      chips.appendChild(chip);
    });
  }

  function escapeHtml(s) {
    var d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function safeUrl(u) {
    try {
      var x = new URL(u, location.href);
      if (x.protocol !== "https:" && x.protocol !== "http:") return "";
      return x.href;
    } catch (e) { return ""; }
  }

  // Text with [n] markers -> the text, with each marker a small link to
  // source n. Built from text nodes, never innerHTML.
  function citedText(parent, text, sources, fade) {
    var parts = String(text).split(/(\[\d{1,2}\])/);
    var words = [];
    parts.forEach(function (part) {
      var m = /^\[(\d{1,2})\]$/.exec(part);
      if (m && sources && sources[+m[1] - 1] && safeUrl(sources[+m[1] - 1].url)) {
        var a = el("a", "kohan-chat-cite", { href: safeUrl(sources[+m[1] - 1].url), title: sources[+m[1] - 1].title || "" });
        a.textContent = m[1];
        parent.appendChild(a);
        words.push(a);
        return;
      }
      if (m) return; // a marker with no source: drop it
      if (!fade) { parent.appendChild(document.createTextNode(part)); return; }
      // Word by word: Persian letters change shape as their neighbours
      // arrive, so revealing letter by letter makes the line twitch.
      part.split(/(\s+)/).forEach(function (w) {
        if (!w) return;
        if (/^\s+$/.test(w)) { parent.appendChild(document.createTextNode(w)); return; }
        var sp = el("span", "kohan-w");
        sp.textContent = w;
        parent.appendChild(sp);
        words.push(sp);
      });
    });
    return words;
  }

  function revealWords(words) {
    if (!words.length) return;
    if (reduced) { words.forEach(function (w) { w.classList.add("is-in"); }); return; }
    var step = Math.max(14, Math.min(55, 1600 / words.length));
    words.forEach(function (w, i) {
      setTimeout(function () {
        w.classList.add("is-in");
        if (log && i % 6 === 0) log.scrollTop = log.scrollHeight;
      }, i * step);
    });
    setTimeout(function () { if (log) log.scrollTop = log.scrollHeight; }, words.length * step + 60);
  }

  function render(m, animate) {
    if (!log) return null;
    var cls = m.w === "note" ? "kohan-chat-note" : "kohan-chat-msg kohan-chat-" + m.w;
    var n = el("div", cls);
    if (animate && !reduced) n.classList.add("is-new");
    var body = el("div", "kohan-chat-text");
    n.appendChild(body);
    var cards = m.k === "cards" && m.s && m.s.length;
    var fade = !!animate && m.w === "bot";
    // Cards: the lead line only; the passages are the cards below.
    var text = cards ? String(m.t).split("\n")[0] : m.t;
    var words = citedText(body, text, cards ? null : m.s, fade);
    if (cards) {
      var list = el("div", "kohan-chat-sources kohan-chat-cards");
      m.s.slice(0, 3).forEach(function (s) {
        var href = safeUrl(s.url);
        if (!href) return;
        var a = el("a", "kohan-chat-source kohan-chat-card", { href: href });
        var t = el("strong");
        t.textContent = s.title || href;
        a.appendChild(t);
        if (s.excerpt) {
          var ex = el("span");
          ex.textContent = s.excerpt;
          a.appendChild(ex);
        }
        list.appendChild(a);
      });
      n.appendChild(list);
    } else if (m.s && m.s.length) {
      var src = el("div", "kohan-chat-sources");
      m.s.slice(0, 4).forEach(function (s, i) {
        var href = safeUrl(s.url);
        if (!href) return;
        var a = el("a", "kohan-chat-source", { href: href, title: s.title || href });
        var num = el("b");
        num.textContent = String(i + 1);
        a.appendChild(num);
        a.appendChild(document.createTextNode(s.title || href));
        src.appendChild(a);
      });
      n.appendChild(src);
    }
    log.appendChild(n);
    log.scrollTop = log.scrollHeight;
    if (fade) revealWords(words);
    return n;
  }

  function record(who, text, sources, kind) {
    var m = { w: who, t: String(text || ""), s: sources && sources.length ? sources : undefined };
    if (kind) m.k = kind;
    mem.msgs.push(m);
    save();
    if (launcher) launcher.setAttribute("data-has-history", "");
    return render(m, true);
  }

  function clearAll() {
    mem = { msgs: [], open: open, path: here };
    save();
    if (log) log.textContent = "";
    record("bot", L.greeting);
    renderChips();
    if (input) input.focus();
  }

  function onSend(e) {
    e.preventDefault();
    var q = (input.value || "").trim();
    if (!q) return;
    input.value = "";
    ask(q);
  }

  function history() {
    return mem.msgs
      .filter(function (m) { return m.w === "user" || m.w === "bot"; })
      .slice(-9, -1) // the question being asked is the last entry
      .map(function (m) { return { role: m.w === "user" ? "user" : "assistant", text: m.t.slice(0, 600) }; });
  }

  // One send path for the input box, the chips and KohanChat.ask().
  function ask(q) {
    q = (q || "").trim().slice(0, 500);
    if (!q || busy) return;
    if (!panel) buildPanel();
    if (!open) toggle(true);
    record("user", q);
    var pending = render({ w: "bot", t: "" });
    pending.classList.add("kohan-chat-pending");
    pending.innerHTML = '<span class="kohan-chat-dots" aria-label="…"><i></i><i></i><i></i></span>';
    busy = true;
    fire("thinking");

    var route = CFG.chatRoute;
    var fail = function () {
      busy = false;
      pending.remove();
      record("bot", L.error);
      fire("angry");
    };
    if (!route) return fail();
    fetch(route, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({
        question: q,
        locale: LANG,
        source: "visitor",
        page: pageInfo(),
        history: history(),
      }),
    })
      .then(function (r) { return r.json().catch(function () { return null; }).then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        var j = res.j;
        var text = j && (j.answer || j.reply || j.message);
        if (!text) return fail();
        busy = false;
        pending.remove();
        var sources = j && Array.isArray(j.sources) ? j.sources.filter(function (s) { return s && s.url; }) : [];
        var cards = j.mode === "extractive" && sources.some(function (s) { return s.excerpt; });
        record("bot", text, sources, cards ? "cards" : "");
        fire("talking");
      })
      .catch(fail);
  }

  function toggle(force) {
    if (!panel) buildPanel();
    open = typeof force === "boolean" ? force : !open;
    panel.classList.toggle("is-open", open);
    launcher.setAttribute("aria-expanded", open ? "true" : "false");
    mem.open = open;
    save();
    if (open) {
      if (window.KohanTalk) window.KohanTalk.hide();
      renderChips();
      positionPanel();
      requestAnimationFrame(positionPanel);
      setTimeout(function () { if (input) input.focus({ preventScroll: true }); }, 60);
    }
  }

  function reflow() {
    positionLauncher();
    if (open) positionPanel();
  }

  window.KohanChat = {
    open: function (opts) {
      if (!launcher) return;
      toggle(true);
      if (opts && opts.question) ask(opts.question);
    },
    ask: function (q) { if (launcher) ask(q); },
    // The avatar's own line, said while the panel is open, goes in the log.
    say: function (text, cite) {
      if (!panel) buildPanel();
      record("bot", cite ? text + "\n— " + cite : text);
    },
    isOpen: function () { return open; },
    close: function () { if (panel) toggle(false); },
    clear: clearAll,
  };

  function boot() {
    root = findRoot();
    if (!root) {
      // avatar may build slightly later; retry briefly.
      return void setTimeout(boot, 200);
    }
    buildLauncher();
    window.addEventListener("resize", reflow, { passive: true });
    window.addEventListener("scroll", reflow, { passive: true });
    root.addEventListener("kohan:moved", followAvatar);
    if (window.ResizeObserver) {
      var ro = new ResizeObserver(followAvatar);
      ro.observe(root);
      var col = root.querySelector(".kohan-size-controls");
      if (col) ro.observe(col);
    }
    if (window.MutationObserver) {
      new MutationObserver(followAvatar).observe(root, {
        attributes: true,
        attributeFilter: ["style", "class", "data-position"],
      });
    }
    // Open on the last page? Open again here, once the avatar has painted.
    if (mem.open) {
      var reopen = function () { toggle(true); };
      if (root.hasAttribute("data-ready")) setTimeout(reopen, 300);
      else {
        var mo = new MutationObserver(function () {
          if (root.hasAttribute("data-ready")) { mo.disconnect(); setTimeout(reopen, 300); }
        });
        mo.observe(root, { attributes: true, attributeFilter: ["data-ready"] });
      }
    } else if (mem.msgs.length && mem.path !== here) {
      // Closed, but there is a conversation: note the new page for when the
      // visitor opens it, without opening anything now.
      buildPanel();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})();
