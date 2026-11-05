/**
 * Kohan Avatar — talk: the speech bubble and what goes in it.
 *
 *  - Click the avatar: he talks about the section you are reading, or says
 *    one of this page's 2–3 quotations, picked at random (never the same
 *    line twice in a row, and every line is used before any repeats).
 *  - Click a section of the page: he looks at it and says what it is about.
 *  - On a book page every rendered section (section[id^="KDJ-"]) is a topic;
 *    its quotations are sentences from the book text itself.
 *  - Arriving on a new page during the visit: a short "now we are on …".
 *
 * Everything here is read from the page the visitor is looking at, or from
 * the theme's verified wisdom-quotes dataset — nothing is generated.
 *
 * Public: window.KohanTalk.say(text, opts), .page(), .currentSection(),
 *         .sectionInfo(el), .hide()
 */
(function () {
  "use strict";
  if (window.__KOHAN_TALK__) return;
  window.__KOHAN_TALK__ = true;

  var CFG = window.KohanAvatarConfig || {};
  var OPTS = CFG.options || {};
  if (OPTS.talk === false) return;
  if (window.matchMedia && window.matchMedia("(max-width: 560px)").matches) return;
  var reduced = !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);

  var LANG = (document.documentElement.getAttribute("lang") || "en").slice(0, 2).toLowerCase();
  var DIR = document.documentElement.getAttribute("dir") || (/^(fa|ar)$/.test(LANG) ? "rtl" : "ltr");

  /* ---- strings --------------------------------------------------------- */
  var S = {
    fa: {
      remark: ["به‌طور خلاصه: {s}", "این بخش می‌گوید: {s}", "خلاصه‌اش این است: {s}"],
      remarkBare: ["این بخش «{t}» است. اگر سؤالی درباره‌اش داری، بپرس.", "به «{t}» رسیدیم. می‌خواهی بیشتر بدانی؟"],
      more: "ادامه در گفت‌وگو", next: "یکی دیگر", close: "بستن", section: "بخش", fromBook: "از متن کتاب",
      greet: "سلام! روی من کلیک کن تا دربارهٔ همین بخش برایت بگویم.",
      arrived: "حالا در «{t}» هستیم. روی من کلیک کن یا روی هر بخش، تا درباره‌اش بگویم.",
      ask: "دربارهٔ «{t}» بیشتر توضیح بده.",
      says: "{a}",
    },
    en: {
      remark: ["In short: {s}", "This part says: {s}", "The gist: {s}"],
      remarkBare: ["This part is «{t}». Ask me anything about it.", "We're at «{t}». Want to know more?"],
      more: "Ask me more", next: "Another one", close: "Close", section: "Section", fromBook: "From the book",
      greet: "Hi! Click me and I'll tell you about the part you're reading.",
      arrived: "Now we're on «{t}». Click me, or any section, and I'll tell you about it.",
      ask: "Tell me more about «{t}».",
      says: "{a}",
    },
    ar: {
      remark: ["باختصار: {s}", "يقول هذا القسم: {s}"],
      remarkBare: ["هذا القسم «{t}». اسألني عنه ما تشاء."],
      more: "تابع في المحادثة", next: "واحدة أخرى", close: "إغلاق", section: "قسم", fromBook: "من نص الكتاب",
      greet: "مرحبًا! انقر عليّ وسأحدثك عن هذا القسم.",
      arrived: "نحن الآن في «{t}». انقر عليّ أو على أي قسم لأحدثك عنه.",
      ask: "حدثني أكثر عن «{t}».", says: "{a}",
    },
    de: {
      remark: ["Kurz gesagt: {s}", "Dieser Teil sagt: {s}"],
      remarkBare: ["Dieser Teil heißt «{t}». Frag mich gern dazu."],
      more: "Mehr im Chat", next: "Noch eins", close: "Schließen", section: "Abschnitt", fromBook: "Aus dem Buch",
      greet: "Hallo! Klick mich an, dann erzähle ich dir von diesem Abschnitt.",
      arrived: "Jetzt sind wir bei «{t}». Klick mich oder einen Abschnitt an.",
      ask: "Erzähl mir mehr über «{t}».", says: "{a}",
    },
    es: {
      remark: ["En resumen: {s}", "Esta parte dice: {s}"],
      remarkBare: ["Esta parte es «{t}». Pregúntame lo que quieras."],
      more: "Seguir en el chat", next: "Otra", close: "Cerrar", section: "Sección", fromBook: "Del libro",
      greet: "¡Hola! Haz clic en mí y te cuento sobre esta sección.",
      arrived: "Ahora estamos en «{t}». Haz clic en mí o en cualquier sección.",
      ask: "Cuéntame más sobre «{t}».", says: "{a}",
    },
    fr: {
      remark: ["En bref : {s}", "Cette partie dit : {s}"],
      remarkBare: ["Cette partie s’intitule «{t}». Pose-moi tes questions."],
      more: "Continuer dans le chat", next: "Une autre", close: "Fermer", section: "Section", fromBook: "Extrait du livre",
      greet: "Bonjour ! Clique sur moi et je te parle de cette section.",
      arrived: "Nous voici sur «{t}». Clique sur moi ou sur une section.",
      ask: "Parle-moi davantage de «{t}».", says: "{a}",
    },
    tr: {
      remark: ["Kısaca: {s}", "Bu bölüm diyor ki: {s}"],
      remarkBare: ["Bu bölüm «{t}». Merak ettiğini sor."],
      more: "Sohbette devam et", next: "Bir tane daha", close: "Kapat", section: "Bölüm", fromBook: "Kitaptan",
      greet: "Merhaba! Bana tıkla, bu bölümü anlatayım.",
      arrived: "Şimdi «{t}» sayfasındayız. Bana ya da bir bölüme tıkla.",
      ask: "«{t}» hakkında daha fazla anlat.", says: "{a}",
    },
    zh: {
      remark: ["简而言之：{s}", "这一部分讲的是：{s}"],
      remarkBare: ["这一部分是「{t}」。有问题尽管问我。"],
      more: "在对话中继续", next: "换一条", close: "关闭", section: "部分", fromBook: "摘自本书",
      greet: "你好！点我，我来介绍你正在看的部分。",
      arrived: "现在来到「{t}」。点我或点任意部分，我来讲讲。",
      ask: "请再多介绍一下「{t}」。", says: "{a}",
    },
    ja: {
      remark: ["要するに：{s}", "このセクションの内容：{s}"],
      remarkBare: ["ここは「{t}」です。何でも聞いてください。"],
      more: "チャットで続ける", next: "もう一つ", close: "閉じる", section: "セクション", fromBook: "本文より",
      greet: "こんにちは！クリックすると、このセクションを紹介します。",
      arrived: "「{t}」に来ました。私か各セクションをクリックしてください。",
      ask: "「{t}」についてもっと教えて。", says: "{a}",
    },
    ru: {
      remark: ["Коротко: {s}", "Суть раздела: {s}"],
      remarkBare: ["Это раздел «{t}». Спросите меня о нём."],
      more: "Продолжить в чате", next: "Ещё одну", close: "Закрыть", section: "Раздел", fromBook: "Из книги",
      greet: "Привет! Нажмите на меня — расскажу об этом разделе.",
      arrived: "Мы на странице «{t}». Нажмите на меня или на любой раздел.",
      ask: "Расскажи подробнее о «{t}».", says: "{a}",
    },
  };
  var L = S[LANG] || S.en;

  // Hand-written one-liners for the CV page blocks (their ids are stable).
  // They describe what the block IS — no facts that could drift from the CV.
  var CV_BLOCKS = {
    fa: {
      home: "این صفحهٔ اصلی رزومهٔ من است؛ از این‌جا می‌توانی به همهٔ بخش‌ها سر بزنی.",
      about: "این‌جا معرفی کوتاهی از من و مسیر کاری‌ام است.",
      certificates: "این‌جا گواهی‌نامه‌ها و دوره‌هایی است که گذرانده‌ام.",
      education: "سوابق تحصیلی و تجربه‌های کاری‌ام در این بخش آمده است.",
      work: "نمونه‌کارها و پروژه‌هایی که رویشان کار کرده‌ام این‌جاست.",
      service: "خدماتی که ارائه می‌دهم را این‌جا می‌بینی؛ روی هر کدام بزن تا جزئیاتش باز شود.",
      tech: "ابزارها و فناوری‌هایی که با آن‌ها کار می‌کنم.",
      blog: "تازه‌ترین یادداشت‌های وبلاگ؛ هر کدام را باز کن تا کاملش را بخوانی.",
      testimonial: "نظر همکاران و کسانی که با هم کار کرده‌ایم.",
      demo: "نمونه‌های زنده‌ای که می‌توانی امتحانشان کنی.",
      contact: "برای تماس با من، فرم و راه‌های ارتباطی این‌جاست.",
    },
    en: {
      home: "This is the front page of my CV — every section starts from here.",
      about: "A short introduction to me and my career.",
      certificates: "The certificates and courses I have completed.",
      education: "My education and work experience.",
      work: "Selected work and projects I have been part of.",
      service: "The services I offer — open any of them for the details.",
      tech: "The tools and technologies I work with.",
      blog: "The latest notes from the blog — open one to read it in full.",
      testimonial: "What colleagues and clients have said.",
      demo: "Live demos you can try.",
      contact: "The contact form and every way to reach me.",
    },
  };

  function shortTitle(t) {
    t = clean(t).split(/\s[·|–—]\s/)[0];
    return t.length > 56 ? t.slice(0, 54).replace(/\s+\S*$/, "") + "…" : t;
  }
  function fmt(tpl, map) {
    return String(tpl).replace(/\{(\w)\}/g, function (m, k) { return map[k] != null ? map[k] : ""; });
  }
  function rnd(a) { return a[Math.floor(Math.random() * a.length)]; }
  function clean(s) { return String(s || "").replace(/\s+/g, " ").trim(); }
  function hash(str) {
    var h = 2166136261;
    for (var i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 16777619); }
    return h >>> 0;
  }
  function seeded(seed) {
    var s = seed || 1;
    return function () { s ^= s << 13; s ^= s >>> 17; s ^= s << 5; return ((s >>> 0) % 100000) / 100000; };
  }

  /* ---- page and section model ----------------------------------------- */
  var SECTION_SEL = [
    "[data-kohan-topic]",
    ".ab-reader section[id]",
    "section[data-ab-section]",
    ".ab-section",
    "div[id].flat-spacing",
    "main section",
    "article section",
    "section[id]",
    "article",
  ].join(",");
  var SKIP_SEL = "header, nav, footer, aside, .kohan-avatar-root, .kohan-bubble, .kohan-chat-panel, .ab-toc, .ab-reader-index, .ab-section-omitted, .offcanvas, #kdcv-waiting, .linkedin-noscript";

  function pageKind() {
    var b = document.body;
    if (document.querySelector(".ab-reader")) return "book-reader";
    if (b.classList.contains("kbk-ai-book-page")) return "book";
    var ctx = window.KDCV_PAGE_CONTEXT;
    if (ctx && ctx.type) return ctx.type;
    if (b.classList.contains("single-post") || b.classList.contains("single")) return "blog-post";
    return "page";
  }

  function textOf(el, removeSel) {
    if (!el) return "";
    var c = el.cloneNode(true);
    c.querySelectorAll(removeSel || ".ab-edition-badge, .ab-views, [class*='badge'], script, style, svg, button").forEach(function (n) { n.remove(); });
    return clean(c.textContent);
  }

  function pageTitle() {
    var h1 = document.querySelector("main h1, .ab-page-hero h1, h1");
    var t = h1 ? textOf(h1) : "";
    if (!t || t.length > 120) {
      t = clean(document.title.split(/\s[|–—-]\s/)[0]);
    }
    return t;
  }

  function sentences(text) {
    var parts = String(text).match(/[^.!?؟。！？\n]+[.!?؟。！？]*/g) || [];
    return parts.map(clean).filter(function (s) { return s.length > 0; });
  }

  var infoCache = typeof WeakMap === "function" ? new WeakMap() : null;
  function sectionInfo(el) {
    if (!el) return null;
    if (infoCache && infoCache.has(el)) return infoCache.get(el);
    var id = el.getAttribute("data-ab-section") || el.id || "";
    var h = el.querySelector("h1, h2, h3, h4");
    var title = clean(el.getAttribute("data-kohan-topic") || (h ? textOf(h) : ""));
    title = title.replace(/^ادامهٔ بخش\s*/, "").replace(/\s*—\s*بخش\s.*$/, "").replace(/^«|»$/g, "");
    var bits = [];
    var len = 0;
    el.querySelectorAll("p, li, blockquote, dd, .ab-reader-content > div").forEach(function (n) {
      if (len > 1800 || n.closest(SKIP_SEL)) return;
      if (n.closest("li") && n.tagName !== "LI") return;
      var t = textOf(n);
      if (t.length < 25) return;
      bits.push(t);
      len += t.length;
    });
    var text = bits.join(" ");
    var sents = sentences(text).filter(function (s) { return s.length >= 30; });
    // About one sentence: the first, plus the second only if the first is
    // very short. A bubble is a line of speech, not a paragraph.
    var summary = sents[0] || "";
    if (summary.length < 50 && sents[1]) summary += " " + sents[1];
    if (summary.length > 160) summary = summary.slice(0, 156).replace(/\s+\S*$/, "") + "…";
    // Quotable lines: complete sentences of a readable length, preferring
    // the ones that share words with the section title.
    var words = title.split(/\s+/).filter(function (w) { return w.length > 2; });
    var quotes = sents
      .filter(function (s) { return s.length >= 55 && s.length <= 230 && !/^[\d\-•*]/.test(s) && !/https?:/.test(s); })
      .map(function (s) {
        var score = 0;
        words.forEach(function (w) { if (s.indexOf(w) !== -1) score += 2; });
        if (/[.!؟?。]$/.test(s)) score += 1;
        return { s: s, score: score };
      })
      .sort(function (a, b) { return b.score - a.score; })
      .slice(0, 3)
      .map(function (q) { return q.s; });
    var info = { el: el, id: id, title: title, summary: summary, quotes: quotes, text: text.slice(0, 1200) };
    if (!title && !summary) info = null;
    if (infoCache) infoCache.set(el, info);
    return info;
  }

  function candidates() {
    var list = [];
    document.querySelectorAll(SECTION_SEL).forEach(function (el) {
      if (el.closest(SKIP_SEL)) return;
      // Prefer the innermost meaningful block: drop an element that merely
      // wraps other candidates (e.g. <main> sections inside an article).
      list.push(el);
    });
    return list.filter(function (el) {
      for (var i = 0; i < list.length; i++) {
        if (list[i] !== el && el.contains(list[i]) && sectionInfo(list[i])) return false;
      }
      return !!sectionInfo(el);
    });
  }

  function currentSection() {
    if (focusSection && Date.now() - focusAt < 25000 && document.contains(focusSection)) return focusSection;
    var best = null, bestScore = -1;
    var vh = window.innerHeight;
    candidates().forEach(function (el) {
      var r = el.getBoundingClientRect();
      if (r.bottom < 0 || r.top > vh || r.height === 0) return;
      var visible = Math.min(r.bottom, vh) - Math.max(r.top, 0);
      // The block crossing the reading line (40% down) wins ties.
      var line = vh * 0.4;
      var score = visible / vh + (r.top <= line && r.bottom >= line ? 1 : 0);
      if (score > bestScore) { bestScore = score; best = el; }
    });
    return best;
  }

  /* ---- quotations: 2–3 per page --------------------------------------- */
  var CONTEXT_CATS = [
    { match: /\/(certificates?)/i, cats: ["science_engineering", "management_entrepreneurship"] },
    { match: /\/(psn|trophy)/i, cats: ["management_entrepreneurship", "stoicism"] },
    { match: /\/(portfolio|work|projects?)/i, cats: ["management_entrepreneurship", "science_engineering"] },
    { match: /\/(books?|ai-book)\//i, cats: ["science_engineering", "management_entrepreneurship", "stoicism"] },
    { match: /\/blog\//i, cats: ["iranian_wisdom", "psychology_human_behavior", "stoicism"] },
  ];
  var datasetP = null;
  function loadDataset() {
    if (datasetP) return datasetP;
    var base = CFG.quotesBase || "";
    if (!base) return (datasetP = Promise.resolve(null));
    var get = function (lang) {
      return fetch(base + "wisdom-quotes." + lang + ".json", { credentials: "same-origin" })
        .then(function (r) { return r.ok ? r.json() : null; });
    };
    datasetP = get(LANG).catch(function () { return null; }).then(function (d) {
      return d || (LANG !== "en" ? get("en").catch(function () { return null; }) : null);
    });
    return datasetP;
  }

  function pageQuotes() {
    return loadDataset().then(function (d) {
      var path = location.pathname + location.search.replace(/[?&](kbk_section|ab_section)=[^&]*/g, "");
      var out = [];
      if (d && Array.isArray(d.categories)) {
        var cats = null;
        for (var i = 0; i < CONTEXT_CATS.length; i++) if (CONTEXT_CATS[i].match.test(path)) { cats = CONTEXT_CATS[i].cats; break; }
        var pool = [];
        d.categories.forEach(function (c) {
          if (cats && cats.indexOf(c.id) === -1) return;
          (c.quotes || []).forEach(function (q) {
            if (q && q.enabled !== false && q.verified !== false && q.text) pool.push(q);
          });
        });
        if (!pool.length) d.categories.forEach(function (c) { (c.quotes || []).forEach(function (q) { if (q && q.text) pool.push(q); }); });
        // Seeded by the page address: the same page keeps the same 2–3
        // quotations for everyone, different pages get different ones.
        var r = seeded(hash(path) || 7);
        pool.sort(function (a, b) { return (a.id < b.id ? -1 : 1); });
        for (var k = pool.length - 1; k > 0; k--) { var j = Math.floor(r() * (k + 1)); var t = pool[k]; pool[k] = pool[j]; pool[j] = t; }
        var count = 2 + (hash(path + "#n") % 2); // 2 or 3
        pool.slice(0, count).forEach(function (q) {
          out.push({ kind: "quote", key: "q:" + q.id, text: q.text, cite: fmt(L.says, { a: q.author || "" }), source: q.source || "" });
        });
      }
      return out;
    });
  }

  /* ---- bubble ---------------------------------------------------------- */
  // A small speech bubble just above his head, its tail on his hair, opening
  // toward the middle of the screen. The words fade in one by one (letter
  // by letter makes Persian glyphs change shape as their neighbours arrive,
  // so the line twitches). A thin line along the bottom shows how long it
  // stays; hovering holds it.
  var bubble = null, textEl, labelEl, citeEl, moreBtn, nextBtn, timerEl, hideTimer = 0, revealTimers = [], current = null, hoverHold = false;
  var lifeMs = 0, lifeStart = 0, heldMs = 0, holdAt = 0;
  var ICON_CHAT = '<svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path fill="currentColor" d="M12 3C6.5 3 2 6.7 2 11.2c0 2.5 1.4 4.7 3.6 6.2-.1.9-.5 2.1-1.4 3.1 1.6-.2 3.2-.9 4.4-1.9 1 .3 2.1.4 3.4.4 5.5 0 10-3.7 10-8.2S17.5 3 12 3Z"/></svg>';
  var ICON_NEXT = '<svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="M20 12a8 8 0 1 1-2.3-5.6M20 4v4.5h-4.5"/></svg>';

  function avatarRoot() {
    return window.KohanAvatar && window.KohanAvatar.root ? window.KohanAvatar.root() : document.querySelector(".kohan-avatar-root");
  }
  function avatarVisible() {
    var r = avatarRoot();
    return !!(r && r.getAttribute("data-kohan-eye-hidden") !== "true" && r.getAttribute("data-kohan-hidden") !== "true");
  }
  function chatOpen() {
    return !!(window.KohanChat && window.KohanChat.isOpen && window.KohanChat.isOpen());
  }

  function buildBubble() {
    if (bubble) return;
    bubble = document.createElement("div");
    bubble.className = "kohan-bubble";
    bubble.setAttribute("role", "status");
    bubble.setAttribute("aria-live", "polite");
    bubble.setAttribute("dir", DIR);
    bubble.setAttribute("lang", LANG);
    bubble.innerHTML =
      '<button type="button" class="kohan-bubble-close"><span aria-hidden="true">×</span></button>' +
      '<p class="kohan-bubble-label"></p><p class="kohan-bubble-text"></p><p class="kohan-bubble-cite"></p>' +
      '<div class="kohan-bubble-actions"><button type="button" class="kohan-bubble-more">' + ICON_CHAT + '<span></span></button>' +
      '<button type="button" class="kohan-bubble-next">' + ICON_NEXT + '<span></span></button></div>' +
      '<i class="kohan-bubble-timer" aria-hidden="true"></i>';
    textEl = bubble.querySelector(".kohan-bubble-text");
    labelEl = bubble.querySelector(".kohan-bubble-label");
    citeEl = bubble.querySelector(".kohan-bubble-cite");
    moreBtn = bubble.querySelector(".kohan-bubble-more");
    nextBtn = bubble.querySelector(".kohan-bubble-next");
    timerEl = bubble.querySelector(".kohan-bubble-timer");
    var closeBtn = bubble.querySelector(".kohan-bubble-close");
    closeBtn.setAttribute("aria-label", L.close);
    closeBtn.title = L.close;
    moreBtn.querySelector("span").textContent = L.more;
    nextBtn.querySelector("span").textContent = L.next;
    closeBtn.addEventListener("click", hide);
    nextBtn.addEventListener("click", function () { talkAbout(currentSection(), true); });
    moreBtn.addEventListener("click", function () {
      var item = current;
      hide();
      if (!window.KohanChat) return;
      var topic = item && item.topic ? item.topic : pageTitle();
      window.KohanChat.open({ question: fmt(L.ask, { t: topic }) });
    });
    // Hovering pauses the countdown (and its hairline); leaving resumes it
    // from where it stopped, so the line and the real timeout agree.
    var hold = function () {
      if (hoverHold) return;
      hoverHold = true;
      holdAt = Date.now();
      bubble.classList.add("is-held");
      clearTimeout(hideTimer);
    };
    var release = function () {
      if (!hoverHold) return;
      hoverHold = false;
      bubble.classList.remove("is-held");
      if (!bubble.classList.contains("is-done")) return;
      heldMs += Date.now() - holdAt;
      armHide(Math.max(1500, lifeMs - (Date.now() - lifeStart - heldMs)));
    };
    bubble.addEventListener("pointerenter", hold);
    bubble.addEventListener("pointerleave", release);
    bubble.addEventListener("focusin", hold);
    bubble.addEventListener("focusout", release);
    document.body.appendChild(bubble);
    var r = avatarRoot();
    if (r) r.addEventListener("kohan:moved", position);
    window.addEventListener("resize", position, { passive: true });
  }

  function position() {
    if (!bubble || !bubble.classList.contains("is-open")) return;
    var r = avatarRoot();
    if (!r) return;
    var a = r.getBoundingClientRect();
    var bw = bubble.offsetWidth, bh = bubble.offsetHeight;
    var head = a.left + a.width / 2;
    // Open toward the middle of the screen: the tail sits near the
    // bubble's edge on HIS side, so the box never crowds his far side.
    var towardLeft = head > window.innerWidth / 2;
    var left = towardLeft ? head + 34 - bw : head - 34;
    left = Math.max(10, Math.min(left, window.innerWidth - bw - 10));
    // Resting on his hair: the tail's tip just touches the top of his head.
    var top = a.top - bh - 4;
    // At small avatar sizes the chat button (stacked on the control column)
    // can reach above his head; then step up just enough to clear it.
    var launcher = document.querySelector(".kohan-chat-launcher");
    if (launcher) {
      var lr = launcher.getBoundingClientRect();
      var overlapsX = lr.right > left && lr.left < left + bw;
      if (overlapsX && lr.top < top + bh + 6 && lr.bottom > top) top = lr.top - bh - 6;
    }
    var below = top < 8;
    if (below) top = a.bottom + 14;
    top = Math.max(8, Math.min(top, window.innerHeight - bh - 8));
    bubble.style.left = Math.round(left) + "px";
    bubble.style.top = Math.round(top) + "px";
    var tail = Math.max(22, Math.min(bw - 22, head - left));
    bubble.style.setProperty("--kohan-tail-x", Math.round(tail) + "px");
    bubble.setAttribute("data-place", below ? "below" : "above");
  }

  function armHide(ms) {
    clearTimeout(hideTimer);
    if (hoverHold) return;
    hideTimer = window.setTimeout(hide, ms);
  }

  function clearReveal() {
    revealTimers.forEach(clearTimeout);
    revealTimers = [];
  }

  function hide() {
    clearTimeout(hideTimer);
    clearReveal();
    if (window.KohanAvatar && window.KohanAvatar.talk) window.KohanAvatar.talk(false);
    if (bubble) bubble.classList.remove("is-open", "is-done");
  }

  function say(item) {
    if (!item || !item.text || !avatarVisible()) return;
    // The chat panel is open: what he says goes into the conversation, not
    // into a second box on top of it. A greeting is simply skipped.
    if (chatOpen()) {
      if (item.kind !== "greet" && window.KohanChat.say) window.KohanChat.say(item.text, item.cite);
      return;
    }
    buildBubble();
    current = item;
    clearTimeout(hideTimer);
    clearReveal();
    bubble.classList.remove("is-done");
    bubble.setAttribute("data-kind", item.kind || "remark");
    labelEl.textContent = item.label || "";
    labelEl.hidden = !item.label;
    citeEl.textContent = item.cite ? "— " + item.cite : "";
    citeEl.title = item.source || "";
    citeEl.hidden = !item.cite;
    moreBtn.hidden = !window.KohanChat || item.noChat === true;
    nextBtn.hidden = item.noNext === true;
    // Words as spans, all present from the start (so the box has its final
    // size and never grows under the reader), shown one after another.
    textEl.textContent = "";
    var words = [];
    String(item.text).split(/(\s+)/).forEach(function (w) {
      if (!w) return;
      if (/^\s+$/.test(w)) { textEl.appendChild(document.createTextNode(w)); return; }
      var sp = document.createElement("span");
      sp.className = "kohan-w";
      sp.textContent = w;
      textEl.appendChild(sp);
      words.push(sp);
    });
    var life = Math.min(15000, Math.max(6000, item.text.length * 65));
    bubble.style.setProperty("--kohan-bubble-life", life + "ms");
    // Restart the entrance (and the timer line) even if it was already open.
    bubble.classList.remove("is-open");
    void bubble.offsetWidth;
    bubble.classList.add("is-open");
    position();
    if (window.KohanAvatar && window.KohanAvatar.talk) window.KohanAvatar.talk(true);
    var done = function () {
      words.forEach(function (w) { w.classList.add("is-in"); });
      lifeMs = life;
      lifeStart = Date.now();
      heldMs = 0;
      if (hoverHold) holdAt = lifeStart;
      bubble.classList.add("is-done");
      if (window.KohanAvatar && window.KohanAvatar.talk) window.KohanAvatar.talk(false);
      armHide(life);
    };
    if (reduced) return done();
    var step = Math.max(28, Math.min(90, 1500 / Math.max(1, words.length)));
    words.forEach(function (w, i) {
      revealTimers.push(window.setTimeout(function () { w.classList.add("is-in"); }, 140 + i * step));
    });
    revealTimers.push(window.setTimeout(done, 140 + words.length * step + 120));
  }

  /* ---- what to say ----------------------------------------------------- */
  var shown = {}; // per page: keys already said
  var lastKey = "";
  var quotesP = null;

  function sectionItems(info) {
    if (!info) return [];
    var items = [];
    var cvMap = CV_BLOCKS[LANG];
    var cv = cvMap && info.el && info.el.id ? cvMap[info.el.id] : null;
    var title = info.title || pageTitle();
    if (cv) {
      items.push({ kind: "remark", key: "r:" + info.id, text: cv, label: shortTitle(title), topic: title });
    } else if (info.summary) {
      items.push({ kind: "remark", key: "r:" + info.id, text: fmt(rnd(L.remark), { t: title, s: info.summary }), label: shortTitle(title), topic: title });
    } else if (title) {
      items.push({ kind: "remark", key: "r:" + info.id, text: fmt(rnd(L.remarkBare), { t: shortTitle(title) }), topic: title });
    }
    var bookish = /^book/.test(pageKind()) || pageKind() === "blog-post";
    if (bookish) {
      info.quotes.slice(0, 2).forEach(function (q, i) {
        items.push({ kind: "book-quote", key: "b:" + info.id + ":" + i, text: "«" + q + "»", label: shortTitle(title), cite: L.fromBook, topic: title });
      });
    }
    return items;
  }

  function choose(items) {
    var fresh = items.filter(function (it) { return !shown[it.key] && it.key !== lastKey; });
    if (!fresh.length) {
      items.forEach(function (it) { delete shown[it.key]; });
      fresh = items.filter(function (it) { return it.key !== lastKey; });
      if (!fresh.length) fresh = items;
    }
    // The remark about the section comes first; after that, any line.
    var remark = fresh.filter(function (it) { return it.kind === "remark"; })[0];
    var it = remark && !shown[remark.key] ? remark : fresh[Math.floor(Math.random() * fresh.length)];
    shown[it.key] = true;
    lastKey = it.key;
    return it;
  }

  function talkAbout(el, anyLine) {
    if (!quotesP) quotesP = pageQuotes();
    quotesP.then(function (pageQs) {
      var info = sectionInfo(el);
      var items = sectionItems(info).concat(pageQs);
      if (!items.length) items = [{ kind: "remark", key: "greet", text: L.greet, noNext: true }];
      var it = choose(anyLine ? items : items);
      gesture(it);
      say(it);
    });
  }

  function gesture(it) {
    var A = window.KohanAvatar;
    if (!A || !A.play) return;
    if (it.kind === "book-quote") A.play("ipad");
    else if (it.kind === "remark" && Math.random() < 0.5) A.group("talk");
  }

  /* ---- triggers -------------------------------------------------------- */
  var focusSection = null, focusAt = 0, lastSectionTalkAt = 0;

  window.addEventListener("kohan:avatar:click", function (e) {
    e.preventDefault(); // tell the engine we answer clicks
    if (bubble && bubble.classList.contains("is-open") && current && current.kind === "greet") hide();
    talkAbout(currentSection(), false);
  });

  // A click on a section of the page: he looks there and says what it is.
  document.addEventListener("click", function (e) {
    if (OPTS.sectionTalk === false || !avatarVisible()) return;
    if (e.defaultPrevented || e.button !== 0 || !(e.target instanceof Element)) return;
    var t = e.target;
    if (t.closest("a, button, input, select, textarea, label, summary, [role='button'], [role='tab'], [contenteditable], video, audio, iframe, " + SKIP_SEL)) return;
    var sel = window.getSelection && String(window.getSelection() || "").trim();
    if (sel) return; // the visitor is selecting text, not talking to us
    var el = t.closest(SECTION_SEL);
    while (el && el.closest(SKIP_SEL)) el = el.parentElement && el.parentElement.closest(SECTION_SEL);
    if (!el || !sectionInfo(el)) return;
    var now = Date.now();
    var same = el === focusSection;
    if (now - lastSectionTalkAt < 4000) return;
    if (same && now - lastSectionTalkAt < 30000) return;
    focusSection = el;
    focusAt = now;
    lastSectionTalkAt = now;
    if (window.KohanAvatar && window.KohanAvatar.lookAt) window.KohanAvatar.lookAt(e.clientX, e.clientY);
    window.setTimeout(function () {
      // A section click always opens with what the section is about.
      var info = sectionInfo(el);
      var items = sectionItems(info);
      if (!items.length) return;
      var it = items[0];
      shown[it.key] = true;
      lastKey = it.key;
      say(it);
    }, 260);
  }, false);

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && bubble && bubble.classList.contains("is-open")) hide();
  });

  // Arrival line: once per page per visit.
  function arrival() {
    if (OPTS.arrivalTalk === false) return;
    var key = "kohanTalkSeen:" + location.pathname + location.search;
    var visited = 0;
    try {
      if (sessionStorage.getItem(key)) return;
      sessionStorage.setItem(key, "1");
      visited = parseInt(sessionStorage.getItem("kohanTalkPages") || "0", 10) + 1;
      sessionStorage.setItem("kohanTalkPages", String(visited));
    } catch (er) {}
    // The chat panel, when it reopens on its own, already greets the page.
    try { if (JSON.parse(sessionStorage.getItem("kohanChat.v3") || "{}").open) return; } catch (er) {}
    var t = pageTitle();
    var text = visited > 1 && t ? fmt(L.arrived, { t: t }) : L.greet;
    window.setTimeout(function () {
      if (bubble && bubble.classList.contains("is-open")) return;
      say({ kind: "greet", key: "greet", text: text, noNext: false });
    }, 1600);
  }

  function boot() {
    if (!avatarRoot()) return void window.setTimeout(boot, 250);
    quotesP = pageQuotes();
    var r = avatarRoot();
    var ready = function () { arrival(); };
    if (r.hasAttribute("data-ready")) ready();
    else {
      var mo = new MutationObserver(function () {
        if (r.hasAttribute("data-ready")) { mo.disconnect(); ready(); }
      });
      mo.observe(r, { attributes: true, attributeFilter: ["data-ready"] });
    }
    window.addEventListener("kohan:avatar:eye", function (e) { if (e.detail && e.detail.hidden) hide(); });
  }

  window.KohanTalk = {
    say: function (text, opts) { say({ kind: "custom", key: "c:" + hash(String(text)), text: String(text), label: opts && opts.label, cite: opts && opts.cite, noNext: true, noChat: !!(opts && opts.noChat) }); },
    hide: hide,
    page: function () { return { title: pageTitle(), kind: pageKind(), url: location.href, lang: LANG }; },
    currentSection: function () { return sectionInfo(currentSection()); },
    sectionInfo: sectionInfo,
  };

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();
