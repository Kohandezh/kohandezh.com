/*!
 * blog-post-enhance.js — reading-progress bar + share controls for blog posts.
 *
 * Both are injected rather than hand-written into each post so that the ten
 * existing posts (and every future one) stay in sync without touching markup.
 * The script no-ops on any page without a .blog-article, so it is safe to load
 * from the archive page too.
 */
(function () {
  "use strict";

  var LABELS = {
    fa: {
      share: "هم‌رسانی این نوشته",
      linkedin: "اشتراک در لینکدین",
      x: "اشتراک در ایکس",
      telegram: "اشتراک در تلگرام",
      whatsapp: "اشتراک در واتساپ",
      copy: "کپی نشانی",
      copied: "کپی شد",
      progress: "میزان پیشرفت مطالعه"
    },
    en: {
      share: "Share this post",
      linkedin: "Share on LinkedIn",
      x: "Share on X",
      telegram: "Share on Telegram",
      whatsapp: "Share on WhatsApp",
      copy: "Copy link",
      copied: "Copied",
      progress: "Reading progress"
    },
    /* The posts are translated in place by page-i18n, so these injected
       controls have to follow all nine languages, not just fa/en — otherwise
       a German reader got "هم‌رسانی این نوشته" above German body copy. */
    ar: {
      share: "شارك هذه المقالة", linkedin: "مشاركة على لينكدإن", x: "مشاركة على إكس",
      telegram: "مشاركة على تلغرام", whatsapp: "مشاركة على واتساب",
      copy: "نسخ الرابط", copied: "تم النسخ", progress: "تقدّم القراءة"
    },
    de: {
      share: "Diesen Beitrag teilen", linkedin: "Auf LinkedIn teilen", x: "Auf X teilen",
      telegram: "Auf Telegram teilen", whatsapp: "Auf WhatsApp teilen",
      copy: "Link kopieren", copied: "Kopiert", progress: "Lesefortschritt"
    },
    es: {
      share: "Compartir esta publicación", linkedin: "Compartir en LinkedIn", x: "Compartir en X",
      telegram: "Compartir en Telegram", whatsapp: "Compartir en WhatsApp",
      copy: "Copiar enlace", copied: "Copiado", progress: "Progreso de lectura"
    },
    fr: {
      share: "Partager cet article", linkedin: "Partager sur LinkedIn", x: "Partager sur X",
      telegram: "Partager sur Telegram", whatsapp: "Partager sur WhatsApp",
      copy: "Copier le lien", copied: "Copié", progress: "Progression de lecture"
    },
    tr: {
      share: "Bu yazıyı paylaş", linkedin: "LinkedIn’de paylaş", x: "X’te paylaş",
      telegram: "Telegram’da paylaş", whatsapp: "WhatsApp’ta paylaş",
      copy: "Bağlantıyı kopyala", copied: "Kopyalandı", progress: "Okuma ilerlemesi"
    },
    zh: {
      share: "分享这篇文章", linkedin: "分享到领英", x: "分享到 X",
      telegram: "分享到 Telegram", whatsapp: "分享到 WhatsApp",
      copy: "复制链接", copied: "已复制", progress: "阅读进度"
    },
    ja: {
      share: "この記事を共有", linkedin: "LinkedIn で共有", x: "X で共有",
      telegram: "Telegram で共有", whatsapp: "WhatsApp で共有",
      copy: "リンクをコピー", copied: "コピーしました", progress: "読了率"
    }
  };

  function t() {
    var lang = (document.documentElement.lang || "fa").toLowerCase().split("-")[0];
    return LABELS[lang] || LABELS.en;
  }

  function svg(paths, extra) {
    var s = '<svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true" focusable="false" ' +
      (extra || 'fill="currentColor"') + ">" + paths + "</svg>";
    return s;
  }

  var ICONS = {
    linkedin: svg('<path d="M4.98 3.5A2.5 2.5 0 1 1 0 3.5a2.5 2.5 0 0 1 4.98 0zM.4 8.4h4.2V24H.4zM8.5 8.4h4v2.1h.06c.56-1.06 1.93-2.18 3.97-2.18 4.25 0 5.03 2.8 5.03 6.43V24h-4.2v-6.8c0-1.62-.03-3.7-2.25-3.7-2.26 0-2.6 1.76-2.6 3.58V24H8.5z"/>'),
    x: svg('<path d="M18.24 2.25h3.31l-7.23 8.26L22.5 21.75h-6.6l-5.18-6.77-5.93 6.77H1.47l7.73-8.84L1.5 2.25h6.77l4.68 6.19zm-1.16 17.52h1.83L7.01 4.13H5.05z"/>'),
    telegram: svg('<path d="M23.07 3.32 19.6 20.2c-.26 1.16-.95 1.44-1.92.9l-5.3-3.9-2.56 2.46c-.28.28-.52.52-1.07.52l.38-5.4L18.9 6.1c.43-.38-.09-.6-.66-.22L6.1 13.5.83 11.85c-1.14-.36-1.16-1.14.24-1.7L21.6 1.68c.95-.35 1.78.22 1.47 1.64z"/>'),
    whatsapp: svg('<path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.65-2.05-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.6-.92-2.2-.24-.58-.48-.5-.67-.5h-.57c-.2 0-.52.07-.8.37s-1.05 1.02-1.05 2.5 1.08 2.9 1.23 3.1c.15.2 2.1 3.2 5.08 4.5.71.3 1.26.48 1.7.62.71.23 1.36.2 1.87.12.57-.08 1.75-.72 2-1.4.25-.7.25-1.28.17-1.4-.07-.13-.27-.2-.57-.35zM12.04 21.8h-.01a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.71.97.99-3.62-.23-.37a9.79 9.79 0 0 1-1.5-5.23c0-5.41 4.4-9.81 9.82-9.81a9.75 9.75 0 0 1 6.94 2.88 9.72 9.72 0 0 1 2.87 6.94c0 5.41-4.4 9.82-9.82 9.82zM20.4 3.6A11.66 11.66 0 0 0 12.04 0C5.6 0 .36 5.24.36 11.68c0 2.06.54 4.07 1.56 5.84L.26 24l6.63-1.74a11.66 11.66 0 0 0 5.15 1.2h.01c6.44 0 11.68-5.24 11.68-11.68 0-3.12-1.22-6.05-3.42-8.26z"/>'),
    copy: svg('<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
      'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'),
    check: svg('<path d="m5 13 4 4L19 7"/>',
      'fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"')
  };

  function buildProgress(article, L) {
    var bar = document.createElement("div");
    bar.className = "blog-progress";
    bar.setAttribute("role", "progressbar");
    bar.setAttribute("aria-label", L.progress);
    bar.setAttribute("aria-valuemin", "0");
    bar.setAttribute("aria-valuemax", "100");
    bar.setAttribute("aria-valuenow", "0");

    var fill = document.createElement("div");
    fill.className = "blog-progress-fill";
    bar.appendChild(fill);
    document.body.appendChild(bar);

    var ticking = false;
    function update() {
      ticking = false;
      var rect = article.getBoundingClientRect();
      var vh = window.innerHeight || document.documentElement.clientHeight;
      // Distance scrolled through the article body, clamped to 0..1.
      var total = rect.height - vh;
      var pct = total <= 0 ? 1 : (-rect.top) / total;
      pct = Math.max(0, Math.min(1, pct));
      var val = Math.round(pct * 100);
      fill.style.transform = "scaleX(" + pct + ")";
      bar.setAttribute("aria-valuenow", String(val));
    }
    function onScroll() {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    }
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll, { passive: true });
    update();
  }

  function buildShare(article, L) {
    var url = (document.querySelector('link[rel="canonical"]') || {}).href || location.href;
    var title = (document.querySelector('meta[property="og:title"]') || {}).content || document.title;
    var u = encodeURIComponent(url);
    var tt = encodeURIComponent(title);

    var targets = [
      { key: "linkedin", href: "https://www.linkedin.com/sharing/share-offsite/?url=" + u },
      { key: "x", href: "https://twitter.com/intent/tweet?url=" + u + "&text=" + tt },
      { key: "telegram", href: "https://t.me/share/url?url=" + u + "&text=" + tt },
      { key: "whatsapp", href: "https://wa.me/?text=" + tt + "%20" + u }
    ];

    var wrap = document.createElement("div");
    wrap.className = "blog-share";

    var heading = document.createElement("span");
    heading.className = "blog-share-label";
    heading.textContent = L.share;
    wrap.appendChild(heading);

    var list = document.createElement("div");
    list.className = "blog-share-actions";

    targets.forEach(function (target) {
      var a = document.createElement("a");
      a.className = "blog-share-btn";
      a.href = target.href;
      a.target = "_blank";
      a.rel = "noopener noreferrer";
      a.setAttribute("aria-label", L[target.key]);
      a.title = L[target.key];
      a.innerHTML = ICONS[target.key];
      list.appendChild(a);
    });

    var copy = document.createElement("button");
    copy.type = "button";
    copy.className = "blog-share-btn blog-share-copy";
    copy.setAttribute("aria-label", L.copy);
    copy.title = L.copy;
    copy.innerHTML = ICONS.copy;

    copy.addEventListener("click", function () {
      function done() {
        copy.classList.add("is-copied");
        copy.innerHTML = ICONS.check;
        copy.setAttribute("aria-label", L.copied);
        copy.title = L.copied;
        setTimeout(function () {
          copy.classList.remove("is-copied");
          copy.innerHTML = ICONS.copy;
          copy.setAttribute("aria-label", L.copy);
          copy.title = L.copy;
        }, 2000);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done, function () {});
      } else {
        // execCommand is deprecated but remains the only fallback on
        // non-secure origins, where navigator.clipboard is undefined.
        var ta = document.createElement("textarea");
        ta.value = url;
        ta.setAttribute("readonly", "");
        ta.style.position = "fixed";
        ta.style.opacity = "0";
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand("copy"); done(); } catch (e) {}
        document.body.removeChild(ta);
      }
    });

    list.appendChild(copy);
    wrap.appendChild(list);

    var footer = article.querySelector(".blog-article-footer");
    if (footer) footer.parentNode.insertBefore(wrap, footer);
    else article.appendChild(wrap);
  }

  function init() {
    var article = document.querySelector(".blog-article");
    if (!article) return;
    var L = t();
    buildShare(article, L);
    // The progress bar is motion that conveys position, but users who ask for
    // reduced motion still benefit from it; only the fill transition is dropped
    // (handled in CSS), not the indicator itself.
    buildProgress(article, L);
  }

  /* page-i18n translates the post after this module has already injected its
     controls, so the labels are re-applied on that event. The existing nodes
     are removed first — rebuilding is cheaper and less error-prone than
     hunting down each aria-label and title in place. */
  window.addEventListener("kdcv:page-i18n", function () {
    var old = document.querySelector(".blog-share");
    var bar = document.querySelector(".blog-progress");
    if (old && old.parentNode) old.parentNode.removeChild(old);
    if (bar && bar.parentNode) bar.parentNode.removeChild(bar);
    init();
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
