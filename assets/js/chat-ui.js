/*!
 * chat-ui.js — chat panel behaviour layer.
 *
 * Adds, without touching the minified ai-pet.min.js that builds the panel:
 *   1. A dismiss X on every wisdom-quote row (and on the floating bubble).
 *   2. An action row under the composer: a LANGUAGE picker for the chat, and a
 *      MIC button. Per the request, the reference component's attachment,
 *      Think and Canvas buttons are deliberately not implemented.
 *
 * Language: the chat keeps its OWN language, separate from the page locale, so
 * a visitor reading the Persian CV can ask questions in English. The choice is
 * persisted and attached to the outgoing chat request.
 *
 * Mic: uses the browser's built-in SpeechRecognition for dictation. No API key
 * is handled here — per CLAUDE.md, voice/TTS credentials live server-side in
 * the WordPress kohan-avatar plugin and are never exposed to the browser. If
 * the browser has no SpeechRecognition, the button reports that instead of
 * failing silently.
 */
(function () {
  "use strict";

  if (window.__KDCV_CHAT_UI__) return;
  window.__KDCV_CHAT_UI__ = true;

  var LOCALES = [
    { code: "fa", label: "FA", speech: "fa-IR" },
    { code: "en", label: "EN", speech: "en-US" },
    { code: "ar", label: "AR", speech: "ar-SA" },
    { code: "de", label: "DE", speech: "de-DE" },
    { code: "es", label: "ES", speech: "es-ES" },
    { code: "fr", label: "FR", speech: "fr-FR" },
    { code: "tr", label: "TR", speech: "tr-TR" },
    { code: "zh", label: "ZH", speech: "zh-CN" },
    { code: "ja", label: "JA", speech: "ja-JP" },
    { code: "ru", label: "RU", speech: "ru-RU" }
  ];

  /* Tooltips / aria-labels for the composer controls, in every locale — these
     used to exist only in English and Persian, so a German or Japanese visitor
     hovering the mic got an English label. */
  var T = {
    en: { lang: "Chat language", mic: "Speak", stop: "Stop recording", close: "Dismiss this quote", reset: "Reset position", send: "Send", noMic: "Your browser does not support speech recognition" },
    fa: { lang: "زبان گفت‌وگو", mic: "گفتن با صدا", stop: "توقف ضبط", close: "بستن این نقل‌قول", reset: "بازنشانی جایگاه", send: "ارسال", noMic: "مرورگر شما تشخیص گفتار را پشتیبانی نمی‌کند" },
    ar: { lang: "لغة المحادثة", mic: "التحدث", stop: "إيقاف التسجيل", close: "إغلاق الاقتباس", reset: "إعادة ضبط الموضع", send: "إرسال", noMic: "متصفحك لا يدعم التعرف على الكلام" },
    de: { lang: "Chat-Sprache", mic: "Sprechen", stop: "Aufnahme stoppen", close: "Zitat schließen", reset: "Position zurücksetzen", send: "Senden", noMic: "Ihr Browser unterstützt keine Spracherkennung" },
    es: { lang: "Idioma del chat", mic: "Hablar", stop: "Detener grabación", close: "Cerrar la cita", reset: "Restablecer posición", send: "Enviar", noMic: "Tu navegador no admite el reconocimiento de voz" },
    fr: { lang: "Langue du chat", mic: "Parler", stop: "Arrêter l’enregistrement", close: "Fermer la citation", reset: "Réinitialiser la position", send: "Envoyer", noMic: "Votre navigateur ne prend pas en charge la reconnaissance vocale" },
    tr: { lang: "Sohbet dili", mic: "Konuş", stop: "Kaydı durdur", close: "Alıntıyı kapat", reset: "Konumu sıfırla", send: "Gönder", noMic: "Tarayıcınız konuşma tanımayı desteklemiyor" },
    zh: { lang: "对话语言", mic: "语音输入", stop: "停止录音", close: "关闭引言", reset: "重置位置", send: "发送", noMic: "您的浏览器不支持语音识别" },
    ja: { lang: "チャットの言語", mic: "音声入力", stop: "録音を停止", close: "引用を閉じる", reset: "位置をリセット", send: "送信", noMic: "お使いのブラウザは音声認識に対応していません" },
    ru: { lang: "Язык чата", mic: "Голосовой ввод", stop: "Остановить запись", close: "Закрыть цитату", reset: "Сбросить позицию", send: "Отправить", noMic: "Ваш браузер не поддерживает распознавание речи" }
  };

  function t() {
    return T[pageLang()] || T.en;
  }
  function pageLang() {
    return (document.documentElement.lang || "en").toLowerCase().split("-")[0];
  }

  /* The chat FOLLOWS THE PAGE LANGUAGE.
     -----------------------------------------------------------------------
     It used to prefer the stored `kdcvChatLang` unconditionally, which made
     the choice sticky across languages: open the German CV once and every
     later page — including the English and Persian ones — greeted the visitor
     in German. That reads as the assistant being broken.

     A manual pick is still honoured, but it is scoped to the page language it
     was made ON (`kdcvChatLangFor`). Switch to a differently-languaged page
     and the chat follows the page again, which is what a visitor expects. */
  function chatLang() {
    var p = pageLang();
    var supported = LOCALES.some(function (l) { return l.code === p; }) ? p : "en";
    try {
      var picked = window.localStorage.getItem("kdcvChatLang");
      var pickedFor = window.localStorage.getItem("kdcvChatLangFor");
      if (picked &&
          LOCALES.some(function (l) { return l.code === picked; }) &&
          pickedFor === supported) {
        return picked;
      }
    } catch (e) {}
    return supported;
  }
  function setChatLang(code) {
    try {
      window.localStorage.setItem("kdcvChatLang", code);
      // Remember WHICH page language this choice was made on, so it does not
      // leak onto a page written in another language.
      window.localStorage.setItem("kdcvChatLangFor", pageLang());
    } catch (e) {}
    window.KDCV_CHAT_LANG = code;
    window.dispatchEvent(new CustomEvent("kdcv:chat-lang", { detail: { lang: code } }));
  }
  window.KDCV_CHAT_LANG = chatLang();

  /* ---- panel copy, per chat language ------------------------------------
     The host bundle renders the panel from document.documentElement.lang, so
     changing the CHAT language used to update the stored value and the toggle
     label while every visible string stayed in the page language — which read
     as the switcher being broken. These strings are re-applied on every
     change so the panel actually follows the choice.

     The footer label under the composer is removed outright — the panel
     header already names the assistant. */
  var PANEL = {
    en: { title: "Kohandezh CV Assistant", sub: "Answers from this page", ph: "Ask about this CV…",
          greet: "Hi! I can help you explore the career, projects, skills, education, achievements and contact details shown on this page.\n\nAsk a question or choose one of the topics below.",
          chips: { career: "Career", achievements: "Achievements", products: "Projects", skills: "Skills", education: "Education", contact: "Contact" } },
    fa: { title: "دستیار رزومهٔ کهن‌دژ", sub: "پاسخ از محتوای همین صفحه", ph: "دربارهٔ این رزومه بپرسید…",
          greet: "سلام! می‌توانم در بررسی سوابق شغلی، پروژه‌ها، مهارت‌ها، تحصیلات، دستاوردها و راه‌های تماس این صفحه کمکتان کنم.\n\nسؤالی بپرسید یا یکی از موضوع‌های زیر را انتخاب کنید.",
          chips: { career: "سوابق شغلی", achievements: "دستاوردها", products: "پروژه‌ها", skills: "مهارت‌ها", education: "تحصیلات", contact: "تماس" } },
    ar: { title: "مساعد السيرة الذاتية", sub: "إجابات من هذه الصفحة", ph: "اسأل عن هذه السيرة الذاتية…",
          greet: "مرحبًا! يمكنني مساعدتك في استكشاف المسيرة المهنية والمشاريع والمهارات والتعليم والإنجازات وبيانات التواصل الواردة في هذه الصفحة.\n\nاطرح سؤالًا أو اختر أحد الموضوعات أدناه.",
          chips: { career: "المسيرة المهنية", achievements: "الإنجازات", products: "المشاريع", skills: "المهارات", education: "التعليم", contact: "التواصل" } },
    de: { title: "Kohandezh Lebenslauf-Assistent", sub: "Antworten von dieser Seite", ph: "Fragen Sie zu diesem Lebenslauf…",
          greet: "Hallo! Ich helfe Ihnen, Werdegang, Projekte, Fähigkeiten, Ausbildung, Erfolge und Kontaktdaten auf dieser Seite zu erkunden.\n\nStellen Sie eine Frage oder wählen Sie ein Thema unten.",
          chips: { career: "Werdegang", achievements: "Erfolge", products: "Projekte", skills: "Fähigkeiten", education: "Ausbildung", contact: "Kontakt" } },
    es: { title: "Asistente de CV de Kohandezh", sub: "Respuestas desde esta página", ph: "Pregunta sobre este CV…",
          greet: "¡Hola! Puedo ayudarte a explorar la trayectoria, los proyectos, las habilidades, la formación, los logros y los datos de contacto de esta página.\n\nHaz una pregunta o elige uno de los temas de abajo.",
          chips: { career: "Trayectoria", achievements: "Logros", products: "Proyectos", skills: "Habilidades", education: "Formación", contact: "Contacto" } },
    fr: { title: "Assistant CV de Kohandezh", sub: "Réponses issues de cette page", ph: "Posez une question sur ce CV…",
          greet: "Bonjour ! Je peux vous aider à explorer le parcours, les projets, les compétences, la formation, les réalisations et les coordonnées présentés sur cette page.\n\nPosez une question ou choisissez un sujet ci-dessous.",
          chips: { career: "Parcours", achievements: "Réalisations", products: "Projets", skills: "Compétences", education: "Formation", contact: "Contact" } },
    tr: { title: "Kohandezh Özgeçmiş Asistanı", sub: "Bu sayfadan yanıtlar", ph: "Bu özgeçmiş hakkında sorun…",
          greet: "Merhaba! Bu sayfadaki kariyer, projeler, yetkinlikler, eğitim, başarılar ve iletişim bilgilerini keşfetmenize yardımcı olabilirim.\n\nBir soru sorun ya da aşağıdaki konulardan birini seçin.",
          chips: { career: "Kariyer", achievements: "Başarılar", products: "Projeler", skills: "Yetkinlikler", education: "Eğitim", contact: "İletişim" } },
    zh: { title: "科汉德兹简历助手", sub: "答案来自本页内容", ph: "询问关于这份简历的问题…",
          greet: "您好！我可以帮您了解本页展示的职业经历、项目、技能、教育背景、成就和联系方式。\n\n请提问，或从下方主题中选择。",
          chips: { career: "职业经历", achievements: "成就", products: "项目", skills: "技能", education: "教育背景", contact: "联系方式" } },
    ja: { title: "コハンデズ履歴アシスタント", sub: "このページの内容から回答します", ph: "この履歴書について質問する…",
          greet: "こんにちは。このページに掲載された職歴、プロジェクト、スキル、学歴、実績、連絡先をご案内できます。\n\n質問するか、下のトピックをお選びください。",
          chips: { career: "職歴", achievements: "実績", products: "プロジェクト", skills: "スキル", education: "学歴", contact: "連絡先" } },
    ru: { title: "CV-ассистент Кохандежа", sub: "Ответы по содержимому этой страницы", ph: "Спросите об этом резюме…",
          greet: "Здравствуйте! Я помогу вам изучить карьеру, проекты, навыки, образование, достижения и контактные данные, представленные на этой странице.\n\nЗадайте вопрос или выберите одну из тем ниже.",
          chips: { career: "Карьера", achievements: "Достижения", products: "Проекты", skills: "Навыки", education: "Образование", contact: "Контакты" } }
  };

  var RTL = { fa: 1, ar: 1 };

  function applyPanelLang(panel, code) {
    var P = PANEL[code] || PANEL.en;

    var title = panel.querySelector(".kdcv-pet-title");
    if (title) title.textContent = P.title;

    var sub = panel.querySelector(".kdcv-pet-subtitle");
    if (sub) sub.textContent = P.sub;

    var input = panel.querySelector(".kdcv-pet-input");
    if (input) input.placeholder = P.ph;
    var ta = panel.querySelector(".kdcv-pet-textarea");
    if (ta) { ta.placeholder = P.ph; ta.setAttribute("aria-label", P.ph); }

    // Only the opening message is rewritten — a real exchange must never be
    // retranslated under the visitor mid-conversation.
    var msgs = panel.querySelectorAll(".kdcv-pet-message-assistant:not(.kdcv-wisdom-quote) .kdcv-pet-message-bubble");
    if (msgs.length === 1) msgs[0].textContent = P.greet;

    panel.querySelectorAll("[data-kdcv-pet-topic]").forEach(function (chip) {
      var key = chip.getAttribute("data-kdcv-pet-topic");
      var label = chip.querySelector(".kdcv-hud-label") || chip;
      if (P.chips[key]) label.textContent = P.chips[key];
    });

    // The panel must read in the chat language's own direction, not the page's.
    panel.setAttribute("lang", code);
    panel.setAttribute("dir", RTL[code] ? "rtl" : "ltr");

    // The footer label is dropped entirely — the panel header already names
    // the assistant, so a second label under the composer was redundant.
    var note = panel.querySelector(".kdcv-pet-footer-note");
    if (note) note.remove();
  }

  function svg(paths) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" ' +
      'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + "</svg>";
  }
  var ICON_GLOBE = svg('<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/>');
  var ICON_MIC = svg('<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>');
  var ICON_RESET = svg('<path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1M3.5 4.5V10h5.5"/>');
  /* The return/enter glyph: a shaft running right, turning down, with the
     arrowhead pointing back left — the same mark a desktop chat composer uses
     to say "Enter sends". */
  var ICON_SEND = svg('<path d="M20 5v6a3 3 0 0 1-3 3H5"/><path d="m9 10-4 4 4 4"/>');

  /* ---- 1. dismissible wisdom quotes ------------------------------------- */
  function addQuoteClose(row) {
    if (row.querySelector(".kdcv-quote-close")) return;
    var bubble = row.querySelector(".kdcv-pet-message-bubble");
    if (!bubble) return;
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "kdcv-quote-close";
    btn.setAttribute("aria-label", t().close);
    btn.title = t().close;
    btn.textContent = "×";
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      row.remove();
    });
    bubble.appendChild(btn);
  }

  function addBubbleClose() {
    var bubble = document.getElementById("kdcv-pet-wisdom-bubble");
    if (!bubble || bubble.parentElement.querySelector(".kdcv-bubble-close")) return;

    /* The bubble is itself a <button>, so the dismiss cannot be nested inside
       it. It also cannot simply be a SIBLING: positioned absolutely, it would
       resolve against whatever ancestor happens to be positioned, which put it
       73px right and 111px below the bubble's corner rather than on it.
       So the bubble is wrapped in an inline-block that hugs it exactly, and
       the dismiss is positioned against THAT. */
    var wrap = bubble.parentElement;
    if (!wrap.classList.contains("kdcv-wisdom-wrap")) {
      wrap = document.createElement("span");
      wrap.className = "kdcv-wisdom-wrap";
      bubble.insertAdjacentElement("beforebegin", wrap);
      wrap.appendChild(bubble);
    }

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "kdcv-bubble-close";
    btn.setAttribute("aria-label", t().close);
    btn.title = t().close;
    btn.textContent = "×";
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      wrap.remove();
    });
    wrap.appendChild(btn);
  }

  /* ---- 2. action row ----------------------------------------------------- */
  function buildActions(panel) {
    if (panel.querySelector(".kdcv-chat-actions")) return;
    var form = panel.querySelector(".kdcv-pet-form");
    if (!form) return;

    var L = t();
    var row = document.createElement("div");
    row.className = "kdcv-chat-actions";

    /* Mic ---------------------------------------------------------------- */
    var mic = document.createElement("button");
    mic.type = "button";
    mic.className = "kdcv-chat-action kdcv-chat-mic";
    mic.setAttribute("aria-label", L.mic);
    mic.title = L.mic;
    mic.innerHTML = ICON_MIC;

    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var rec = null;
    var recording = false;

    function stopRec() {
      recording = false;
      mic.classList.remove("is-recording");
      mic.setAttribute("aria-label", L.mic);
      mic.title = L.mic;
      if (rec) { try { rec.stop(); } catch (e) {} }
    }

    mic.addEventListener("click", function () {
      if (!SR) {
        mic.title = L.noMic;
        mic.setAttribute("aria-label", L.noMic);
        return;
      }
      if (recording) { stopRec(); return; }

      rec = new SR();
      var chosen = LOCALES.filter(function (l) { return l.code === chatLang(); })[0];
      rec.lang = chosen ? chosen.speech : "en-US";
      rec.interimResults = false;
      rec.maxAlternatives = 1;

      rec.onresult = function (ev) {
        var text = (ev.results[0] && ev.results[0][0] && ev.results[0][0].transcript) || "";
        var input = panel.querySelector(".kdcv-pet-input");
        if (input && text) {
          input.value = input.value ? input.value + " " + text : text;
          // Let the host bundle notice the change.
          input.dispatchEvent(new Event("input", { bubbles: true }));
          input.focus();
        }
      };
      rec.onerror = stopRec;
      rec.onend = stopRec;

      recording = true;
      mic.classList.add("is-recording");
      mic.setAttribute("aria-label", L.stop);
      mic.title = L.stop;
      try { rec.start(); } catch (e) { stopRec(); }
    });

    /* Language ------------------------------------------------------------ */
    var wrap = document.createElement("div");
    wrap.className = "kdcv-chat-lang";

    var toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "kdcv-chat-action kdcv-chat-lang-toggle";
    toggle.setAttribute("aria-haspopup", "true");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", L.lang);
    toggle.title = L.lang;
    // Icon only, like every other control in the row. The code used to sit
    // beside the globe, which made this the one button wider than its square
    // and pushed its glyph off centre. The active language is still announced
    // through aria-label and shown as the checked item in the menu.
    toggle.innerHTML = ICON_GLOBE + '<span class="kdcv-sr-only">' + chatLang().toUpperCase() + "</span>";

    var menu = document.createElement("ul");
    menu.className = "kdcv-chat-lang-menu";
    menu.hidden = true;

    LOCALES.forEach(function (l) {
      var li = document.createElement("li");
      var b = document.createElement("button");
      b.type = "button";
      b.textContent = l.label;
      b.setAttribute("lang", l.code);
      if (l.code === chatLang()) b.setAttribute("aria-current", "true");
      b.addEventListener("click", function () {
        setChatLang(l.code);
        menu.querySelectorAll("button").forEach(function (x) { x.removeAttribute("aria-current"); });
        b.setAttribute("aria-current", "true");
        toggle.querySelector("span").textContent = l.code.toUpperCase();
        // Re-render the panel copy; without this the switcher only changed a
        // stored value and looked broken.
        try { applyPanelLang(panel, l.code); } catch (e) {}
        close();
      });
      li.appendChild(b);
      menu.appendChild(li);
    });

    function close() { menu.hidden = true; toggle.setAttribute("aria-expanded", "false"); }
    function open() { menu.hidden = false; toggle.setAttribute("aria-expanded", "true"); }

    toggle.addEventListener("click", function (e) {
      e.stopPropagation();
      menu.hidden ? open() : close();
    });
    document.addEventListener("click", function (e) { if (!wrap.contains(e.target)) close(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") close(); });

    wrap.appendChild(toggle);
    wrap.appendChild(menu);

    /* The mail/contact icon was removed from this row: the page already has
       a contact section and the chat is for asking questions, not for opening
       an email client. The host bundle's own text link is left alone. */

    /* Reset position ------------------------------------------------------
       The host bundle leaves this as a text link stranded in the footer, on
       the opposite side of the panel from the other controls. Every control
       belongs in one group on one side, so it is moved into this row. */
    var srcReset = panel.querySelector(".kdcv-pet-reset");
    var reset = document.createElement("button");
    reset.type = "button";
    reset.className = "kdcv-chat-action kdcv-chat-reset";
    reset.setAttribute("aria-label", L.reset);
    reset.title = L.reset;
    reset.innerHTML = ICON_RESET;
    reset.addEventListener("click", function (e) {
      e.preventDefault();
      if (srcReset) srcReset.click();
    });
    if (srcReset) srcReset.classList.add("kdcv-is-promoted");

    row.appendChild(mic);
    row.appendChild(reset);
    row.appendChild(wrap);

    /* Send moves out of the input and onto the end of this row, so the whole
       control set reads as one line instead of one button floating in the
       composer and the rest sitting below it. */
    var send = panel.querySelector(".kdcv-pet-send");
    if (send) {
      send.classList.add("kdcv-chat-send-inline");
      row.appendChild(send);
    }

    form.insertAdjacentElement("afterend", row);
    buildComposer(panel, form, row);
  }

  /* ---- composer shell ----------------------------------------------------
     Wraps the input and the control row in ONE rounded container, the way a
     modern chat composer reads: the field and its controls share a single
     surface instead of a bare input with a button floating beside it.

     The host bundle's <input> is deliberately reused rather than swapped for a
     <textarea>. ai-pet.min.js is minified with no source available and holds
     its own reference to that element; replacing the node would leave that
     reference stale and silently break sending. The shell is built around it. */
  function buildComposer(panel, form, row) {
    if (panel.querySelector(".kdcv-composer")) return;

    var shell = document.createElement("div");
    shell.className = "kdcv-composer";
    form.insertAdjacentElement("beforebegin", shell);
    shell.appendChild(form);
    shell.appendChild(row);

    var send = panel.querySelector(".kdcv-pet-send");
    if (!send) return;

    /* Send is type="submit". Moving it out of <form> into the control row
       ORPHANS it — an outside submit button has form === null and clicking it
       does nothing. Re-associate it explicitly via the form attribute, which
       is exactly what that attribute is for. Verified: send.form must not be
       null after this runs. */
    if (!form.id) form.id = "kdcv-pet-form";
    send.setAttribute("form", form.id);

    // The button carried a literal "→" glyph. Swap it for a proper arrow so
    // it matches the other icons instead of rendering as text.
    if (!send.querySelector("svg")) {
      send.textContent = "";
      send.innerHTML = ICON_SEND;
    }
    var LS = t();
    send.setAttribute("aria-label", LS.send);
    send.title = LS.send;

    buildTextarea(panel, form, send);
  }

  /* ---- multi-line field --------------------------------------------------
     A real auto-growing <textarea>, without breaking the host bundle.

     ai-pet.min.js is minified with no source and holds its own reference to
     the original <input>. Replacing that node would leave the reference stale
     and break sending in a way nothing would report. So the input is KEPT in
     the DOM (visually hidden, still the form's field) and a textarea is
     rendered in its place, with every keystroke mirrored onto the input plus a
     synthetic `input` event so the bundle sees exactly what it saw before.

     Enter sends, Shift+Enter inserts a newline, and IME composition is left
     alone so Japanese and Chinese input are not cut off mid-word. */
  function buildTextarea(panel, form, send) {
    if (panel.querySelector(".kdcv-pet-textarea")) return;

    var input = panel.querySelector(".kdcv-pet-input");
    if (!input) return;

    var ta = document.createElement("textarea");
    ta.className = "kdcv-pet-textarea";
    ta.rows = 1;
    ta.placeholder = input.placeholder || "";
    ta.setAttribute("aria-label", input.getAttribute("aria-label") || ta.placeholder);
    if (input.id) ta.setAttribute("aria-describedby", input.id);

    input.classList.add("kdcv-input-mirrored");
    input.setAttribute("tabindex", "-1");
    input.setAttribute("aria-hidden", "true");
    input.insertAdjacentElement("afterend", ta);

    var MAX = 160; // ~6 lines before it starts scrolling

    function grow() {
      ta.style.height = "auto";
      ta.style.height = Math.min(ta.scrollHeight, MAX) + "px";
      ta.style.overflowY = ta.scrollHeight > MAX ? "auto" : "hidden";
    }

    /* Guards the round trip. An <input> CANNOT hold newlines — assigning a
       multi-line string to one silently strips them. So when mirror() fired
       the synthetic `input` event, the back-sync listener below saw
       input.value !== ta.value, assumed the bundle had changed the field, and
       copied the stripped value back over the textarea — destroying every line
       break the moment a second line was typed. This flag makes the back-sync
       ignore echoes of our own writes. */
    var syncing = false;

    function mirror() {
      syncing = true;
      // An <input> drops "\n" outright, which would run the last word of one
      // line into the first of the next ("line one" + "line two" arriving as
      // "line oneline two"). Collapse breaks to a space so the message the
      // bundle sends still reads as sentences.
      input.value = ta.value.replace(/\s*\n+\s*/g, " ");
      // The bundle listens for `input` on its own field; without this it never
      // learns the value changed and treats the message as empty.
      input.dispatchEvent(new Event("input", { bubbles: true }));
      syncing = false;
      grow();
    }

    ta.addEventListener("input", mirror);

    ta.addEventListener("keydown", function (e) {
      if (e.key !== "Enter" || e.shiftKey || e.isComposing || e.keyCode === 229) return;
      e.preventDefault();
      if (!ta.value.trim()) return;
      mirror();
      if (typeof form.requestSubmit === "function") form.requestSubmit(send);
      else send.click();
    });

    // The bundle clears its input on send; the textarea has to follow.
    form.addEventListener("submit", function () {
      window.setTimeout(function () {
        if (!input.value) { ta.value = ""; grow(); }
      }, 0);
    });

    // Dictation writes into the input directly, so reflect that back — but
    // never echo our own writes, and never while the user is typing.
    input.addEventListener("input", function () {
      if (syncing || document.activeElement === ta) return;
      if (input.value !== ta.value) {
        ta.value = input.value;
        grow();
      }
    });

    // Clicking anywhere in the shell focuses the field, as a composer should.
    var shell = panel.querySelector(".kdcv-composer");
    if (shell) {
      shell.addEventListener("mousedown", function (e) {
        if (e.target === shell || e.target === form) { e.preventDefault(); ta.focus(); }
      });
    }

    grow();
  }

  /* ---- 3. responsive placement ------------------------------------------
     The host bundle stores the dragged position as inline `left/top` with
     !important, which an inline !important makes unbeatable from a stylesheet.
     On narrow screens the panel must become a full-width bottom sheet, so the
     inline values are cleared here and re-applied by the bundle only when the
     user drags again on a large screen. */
  function placePanel(panel) {
    var narrow = window.matchMedia("(max-width: 640px)").matches;
    if (narrow) {
      if (panel.style.left || panel.style.top) {
        panel.dataset.kdcvLeft = panel.style.left;
        panel.dataset.kdcvTop = panel.style.top;
        panel.style.removeProperty("left");
        panel.style.removeProperty("top");
      }
      panel.classList.add("kdcv-chat-sheet");
    } else {
      panel.classList.remove("kdcv-chat-sheet");
      if (!panel.style.left && panel.dataset.kdcvLeft) {
        panel.style.setProperty("left", panel.dataset.kdcvLeft.replace(/\s*!important/, ""), "important");
        panel.style.setProperty("top", panel.dataset.kdcvTop.replace(/\s*!important/, ""), "important");
      }
      keepPanelOnScreen(panel);
    }
  }

  /* Keep the panel inside the viewport.
     -----------------------------------------------------------------------
     THE BUG THIS FIXES. The host bundle positions the panel by an inline
     `top` derived from the pet's position, and chat-ui.css gives it a tall
     fixed height (`min(78dvh, 760px)`) so the conversation fills the box
     rather than the box hugging the conversation. Those two together are not
     safe: with the pet at its default spot on a 900px-tall window the panel
     was laid out at top:576 with height:590, so its bottom landed at 1166 —
     276px BELOW the fold. Everything in the bottom action row (mic, reset and
     the LANGUAGE picker) was off-screen and unreachable, and the language
     menu that opens above it was unusable.

     The height is capped to the viewport first, then the top is pulled up if
     the panel would still hang off the bottom, and finally it is stopped from
     going above the top edge. Left/width are untouched — the host owns the
     horizontal placement and dragging still works. */
  function keepPanelOnScreen(panel) {
    var PAD = 12;
    var vh = window.innerHeight || document.documentElement.clientHeight;
    if (!vh) return;

    var maxH = vh - PAD * 2;
    panel.style.setProperty("max-height", maxH + "px", "important");

    var rect = panel.getBoundingClientRect();
    if (!rect.height) return;

    /* `top` and the painted position are NOT the same number here: the panel
       carries an open/close transform, so its box lands ~31px below the `top`
       it was given. Setting `top` from a rect measurement without removing
       that offset leaves the panel short by exactly the transform each pass.
       Measure the delta and set `top` in ITS own coordinate space. */
    var used = parseFloat(window.getComputedStyle(panel).top);
    if (isNaN(used)) used = rect.top;
    var delta = rect.top - used;

    var wantTop = rect.top;
    if (wantTop + rect.height > vh - PAD) wantTop = vh - PAD - rect.height;
    if (wantTop < PAD) wantTop = PAD;

    if (Math.abs(wantTop - rect.top) > 1) {
      panel.style.setProperty("top", Math.round(wantTop - delta) + "px", "important");
    }
  }

  /* The panel's height settles a frame or two after it opens — the host bundle
     fills the log, then the composer grows. Re-clamping on a couple of frames
     catches that without polling. */
  function clampSoon() {
    var panel = document.querySelector(".kdcv-pet-panel");
    if (!panel) return;
    keepPanelOnScreen(panel);
    window.requestAnimationFrame(function () {
      var p = document.querySelector(".kdcv-pet-panel");
      if (p) keepPanelOnScreen(p);
    });
  }

  /* ---- wiring ------------------------------------------------------------ */
  function sweep() {
    document.querySelectorAll(".kdcv-wisdom-quote").forEach(addQuoteClose);
    addBubbleClose();
    var panel = document.querySelector(".kdcv-pet-panel");
    if (panel) {
      buildActions(panel);
      placePanel(panel);
      clampSoon();
      // The host bundle rebuilds the panel body on open, so the chat language
      // is re-applied on every pass rather than only when it changes.
      applyPanelLang(panel, chatLang());
    }
  }

  function init() {
    // Register the observer FIRST. sweep() touches DOM built by another
    // bundle, so if it ever throws on an unexpected node the observer must
    // already be attached — otherwise one bad pass permanently disables the
    // whole feature, which is exactly what happened during development.
    if ("MutationObserver" in window && document.body) {
      var queued = false;
      new MutationObserver(function () {
        if (queued) return;
        queued = true;
        window.requestAnimationFrame(function () { queued = false; safeSweep(); });
      }).observe(document.body, { childList: true, subtree: true });
    }

    window.addEventListener("resize", safeSweep);
    window.addEventListener("orientationchange", safeSweep);

    /* page-i18n.js translates the standalone pages AFTER this module has
       already read documentElement.lang, so without this the chat stayed in the
       page's authored language while the page around it changed. */
    window.addEventListener("kdcv:page-i18n", function () {
      window.KDCV_CHAT_LANG = chatLang();
      safeSweep();
    });

    safeSweep();

    // Belt-and-braces: the panel is created lazily and some hosts replace it
    // wholesale. A few bounded passes cover the window before the observer
    // sees anything, without polling forever.
    var tries = 0;
    var timer = window.setInterval(function () {
      safeSweep();
      if (++tries >= 10) window.clearInterval(timer);
    }, 400);
  }

  function safeSweep() {
    try {
      sweep();
    } catch (e) {
      // One malformed node must not take the rest of the panel down.
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
