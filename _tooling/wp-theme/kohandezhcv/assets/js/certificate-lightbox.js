/*!
 * certificate-lightbox.js — view certificates in-page instead of a new tab.
 *
 * The cards ship as plain <a href="...jpg" target="_blank"> so the archive
 * still works with JavaScript disabled and the images stay crawlable. This
 * script progressively upgrades those links into a gallery: the anchors keep
 * their href, we just intercept the click.
 */
(function () {
  "use strict";

  var LABELS = {
    fa: { close: "بستن", prev: "قبلی", next: "بعدی", of: "از", dialog: "نمایش گواهی‌نامه" },
    en: { close: "Close", prev: "Previous", next: "Next", of: "of", dialog: "Certificate viewer" }
  };

  function init() {
    var links = [].slice.call(document.querySelectorAll(".certificate-card-link"));
    if (!links.length) return;

    var lang = (document.documentElement.lang || "en").toLowerCase();
    var L = lang.indexOf("fa") === 0 ? LABELS.fa : LABELS.en;
    var rtl = document.documentElement.dir === "rtl";

    var items = links.map(function (a) {
      var card = a.closest(".certificate-card");
      var heading = card && card.querySelector("h3");
      var img = a.querySelector("img");
      return {
        href: a.getAttribute("href"),
        title: heading ? heading.textContent.trim() : (img ? img.alt : ""),
        alt: img ? img.alt : ""
      };
    });

    var index = 0;
    var lastFocused = null;

    // Built once, reused. Kept out of the DOM until first open.
    var overlay = document.createElement("div");
    overlay.className = "cert-lightbox";
    overlay.setAttribute("role", "dialog");
    overlay.setAttribute("aria-modal", "true");
    overlay.setAttribute("aria-label", L.dialog);
    overlay.hidden = true;
    overlay.innerHTML =
      '<button type="button" class="cert-lb-close" aria-label="' + L.close + '">' +
        '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>' +
      '</button>' +
      '<button type="button" class="cert-lb-nav cert-lb-prev" aria-label="' + L.prev + '">' +
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>' +
      '</button>' +
      '<button type="button" class="cert-lb-nav cert-lb-next" aria-label="' + L.next + '">' +
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>' +
      '</button>' +
      '<figure class="cert-lb-figure">' +
        '<img class="cert-lb-image" alt="">' +
        '<figcaption class="cert-lb-caption">' +
          '<span class="cert-lb-title"></span>' +
          '<span class="cert-lb-count"></span>' +
        '</figcaption>' +
      '</figure>';
    document.body.appendChild(overlay);

    var imgEl = overlay.querySelector(".cert-lb-image");
    var titleEl = overlay.querySelector(".cert-lb-title");
    var countEl = overlay.querySelector(".cert-lb-count");
    var closeBtn = overlay.querySelector(".cert-lb-close");
    var prevBtn = overlay.querySelector(".cert-lb-prev");
    var nextBtn = overlay.querySelector(".cert-lb-next");

    function render() {
      var item = items[index];
      imgEl.src = item.href;
      imgEl.alt = item.alt;
      titleEl.textContent = item.title;
      countEl.textContent = (index + 1) + " " + L.of + " " + items.length;
    }

    // In RTL the on-screen arrows are mirrored, so "next" must still mean the
    // next certificate rather than the visually-right one.
    function step(delta) {
      index = (index + delta + items.length) % items.length;
      render();
    }

    function open(i) {
      index = i;
      lastFocused = document.activeElement;
      render();
      overlay.hidden = false;
      document.body.classList.add("cert-lb-open");
      closeBtn.focus();
      document.addEventListener("keydown", onKey);
    }

    function close() {
      overlay.hidden = true;
      document.body.classList.remove("cert-lb-open");
      document.removeEventListener("keydown", onKey);
      imgEl.removeAttribute("src");
      if (lastFocused && lastFocused.focus) lastFocused.focus();
    }

    function onKey(e) {
      if (e.key === "Escape") { close(); return; }
      if (e.key === "ArrowRight") { step(rtl ? -1 : 1); return; }
      if (e.key === "ArrowLeft") { step(rtl ? 1 : -1); return; }
      if (e.key !== "Tab") return;
      // Keep focus inside the dialog while it is open.
      var focusables = [closeBtn, prevBtn, nextBtn];
      var pos = focusables.indexOf(document.activeElement);
      e.preventDefault();
      var dir = e.shiftKey ? -1 : 1;
      focusables[(Math.max(0, pos) + dir + focusables.length) % focusables.length].focus();
    }

    links.forEach(function (a, i) {
      a.addEventListener("click", function (e) {
        // Let modified clicks (new tab, download, middle-click) behave natively.
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        e.preventDefault();
        open(i);
      });
    });

    closeBtn.addEventListener("click", close);
    prevBtn.addEventListener("click", function () { step(rtl ? 1 : -1); });
    nextBtn.addEventListener("click", function () { step(rtl ? -1 : 1); });
    overlay.addEventListener("click", function (e) {
      if (e.target === overlay) close(); // backdrop only
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
