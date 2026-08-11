/**
 * Kohan Avatar — chat panel.
 *
 * A compact AI chat panel that anchors to the SIDE of the Kohan avatar and
 * opens toward the viewport interior (to the avatar's right when it sits on
 * the left, to its left when it sits on the right) — never above its head.
 * Styled as a glowing assistant. Talks to the site's AI chat REST route and
 * drives the avatar's moods through the documented kohan:avatar:mood event.
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
  var P = PACKS[LANG] || PACKS.en;
  var T = CFG.strings || {};
  // Server strings win only if the page language matches the server locale;
  // otherwise use the language pack so a Persian page never shows English.
  var useServer = LANG === "en";
  var L = {};
  ["title", "status", "placeholder", "send", "open", "close", "greeting", "error"].forEach(function (k) {
    L[k] = (useServer && T[k]) || P[k];
  });

  var root = null,   // avatar root
    launcher = null,
    panel = null,
    log = null,
    input = null,
    open = false,
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
    document.body.appendChild(launcher);
    launcher.addEventListener("click", toggle);
    positionLauncher();
  }

  function positionLauncher() {
    if (!launcher || !root) return;
    var r = root.getBoundingClientRect();
    var side = sideOfAvatar();
    launcher.style.top = r.top - 6 + "px";
    launcher.style.left = (side === "right" ? r.right - 20 : r.left - 16) + "px";
  }

  function buildPanel() {
    panel = el("section", "kohan-chat-panel", { role: "dialog", "aria-label": L.title });
    dir = document.documentElement.getAttribute("dir") || "ltr";
    panel.setAttribute("dir", dir);

    var head = el("header", "kohan-chat-head");
    var title = el("div", "kohan-chat-title");
    title.innerHTML = "<strong>" + escapeHtml(L.title) + "</strong><span>" + escapeHtml(L.status) + "</span>";
    var close = el("button", "kohan-chat-close", { type: "button", "aria-label": L.close });
    close.textContent = "×";
    close.addEventListener("click", toggle);
    head.appendChild(title);
    head.appendChild(close);

    log = el("div", "kohan-chat-log", { role: "log", "aria-live": "polite" });

    var form = el("form", "kohan-chat-form");
    input = el("input", "kohan-chat-input", {
      type: "text",
      placeholder: L.placeholder,
      autocomplete: "off",
      "aria-label": L.placeholder,
    });
    var sendBtn = el("button", "kohan-chat-send", { type: "submit", "aria-label": L.send });
    sendBtn.innerHTML =
      '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M3 20.5 21 12 3 3.5 3 10l12 2-12 2z"/></svg>';
    form.appendChild(input);
    form.appendChild(sendBtn);
    form.addEventListener("submit", onSend);

    panel.appendChild(head);
    panel.appendChild(log);
    panel.appendChild(form);
    document.body.appendChild(panel);

    addMessage("bot", L.greeting);
  }

  function escapeHtml(s) {
    var d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function addMessage(who, text) {
    var m = el("div", "kohan-chat-msg kohan-chat-" + who);
    m.textContent = text;
    log.appendChild(m);
    log.scrollTop = log.scrollHeight;
    return m;
  }

  function onSend(e) {
    e.preventDefault();
    var q = (input.value || "").trim();
    if (!q) return;
    addMessage("user", q);
    input.value = "";
    var pending = addMessage("bot", "…");
    pending.classList.add("kohan-chat-pending");
    fire("macbook-work"); // avatar reacts: generating

    var route = CFG.chatRoute;
    if (!route) {
      pending.classList.remove("kohan-chat-pending");
      pending.textContent = L.error;
      fire("angry");
      return;
    }
    fetch(route, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ message: q }),
    })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function (j) {
        pending.classList.remove("kohan-chat-pending");
        pending.textContent = (j && (j.reply || j.answer || j.message)) || L.error;
        fire("ipad-review");
        setTimeout(function () { fire("wink"); }, 1200);
      })
      .catch(function () {
        pending.classList.remove("kohan-chat-pending");
        pending.textContent = L.error;
        fire("angry");
      });
  }

  function toggle() {
    if (!panel) buildPanel();
    open = !open;
    panel.classList.toggle("is-open", open);
    launcher.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      positionPanel();
      requestAnimationFrame(positionPanel);
      setTimeout(function () { if (input) input.focus(); }, 60);
    }
  }

  function reflow() {
    positionLauncher();
    if (open) positionPanel();
  }

  function boot() {
    root = findRoot();
    if (!root) {
      // avatar may build slightly later; retry briefly.
      return void setTimeout(boot, 200);
    }
    buildLauncher();
    window.addEventListener("resize", reflow, { passive: true });
    window.addEventListener("scroll", reflow, { passive: true });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})();
