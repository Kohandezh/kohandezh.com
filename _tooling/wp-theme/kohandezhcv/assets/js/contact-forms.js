/*!
 * contact-forms.js — validation and AJAX submit for the contact forms.
 *
 * THE BUG THIS FIXES
 * Both forms POST to api.web3forms.com. main.js wires jQuery-validate to
 * #contactform only — #demoform had NO handler at all, so it performed a
 * NATIVE form submit. Both forms also carried `novalidate`, which switches off
 * every `required` attribute in the markup.
 *
 * Together that meant: click Send on a completely empty demo form, the browser
 * posts it, and web3forms answers with its own "Form submitted successfully!"
 * page. An empty enquiry was delivered and the visitor was told it worked.
 *
 * `novalidate` is now gone from the markup, and this module guarantees the
 * rest: nothing is sent unless the browser says the form is valid, the POST is
 * done over fetch so the visitor stays on the page, and the result is reported
 * in the page's own language.
 */
(function () {
  "use strict";

  if (window.__KDCV_FORMS__) return;
  window.__KDCV_FORMS__ = true;

  var T = {
    en: { ok: "Thank you — your message has been sent. I'll get back to you shortly.",
          err: "Sending failed. Please try again, or email Kohandezh@hotmail.com directly.",
          sending: "Sending…", fix: "Please complete the required fields." },
    fa: { ok: "سپاسگزارم — پیام شما ارسال شد. به‌زودی پاسخ می‌دهم.",
          err: "ارسال ناموفق بود. دوباره تلاش کنید یا مستقیم به Kohandezh@hotmail.com ایمیل بزنید.",
          sending: "در حال ارسال…", fix: "لطفاً فیلدهای الزامی را کامل کنید." },
    ar: { ok: "شكرًا لك — تم إرسال رسالتك. سأعود إليك قريبًا.",
          err: "فشل الإرسال. حاول مرة أخرى أو راسل Kohandezh@hotmail.com مباشرة.",
          sending: "جارٍ الإرسال…", fix: "يرجى إكمال الحقول المطلوبة." },
    de: { ok: "Vielen Dank — Ihre Nachricht wurde gesendet. Ich melde mich in Kürze.",
          err: "Senden fehlgeschlagen. Bitte erneut versuchen oder direkt an Kohandezh@hotmail.com schreiben.",
          sending: "Wird gesendet…", fix: "Bitte füllen Sie die Pflichtfelder aus." },
    es: { ok: "Gracias — tu mensaje se ha enviado. Te responderé en breve.",
          err: "Error al enviar. Inténtalo de nuevo o escribe a Kohandezh@hotmail.com.",
          sending: "Enviando…", fix: "Completa los campos obligatorios, por favor." },
    fr: { ok: "Merci — votre message a été envoyé. Je vous réponds très vite.",
          err: "Échec de l'envoi. Réessayez ou écrivez à Kohandezh@hotmail.com.",
          sending: "Envoi…", fix: "Merci de compléter les champs obligatoires." },
    tr: { ok: "Teşekkürler — mesajınız gönderildi. En kısa sürede döneceğim.",
          err: "Gönderilemedi. Tekrar deneyin ya da Kohandezh@hotmail.com adresine yazın.",
          sending: "Gönderiliyor…", fix: "Lütfen zorunlu alanları doldurun." },
    zh: { ok: "谢谢 — 您的消息已发送，我会尽快回复。",
          err: "发送失败。请重试，或直接发送邮件至 Kohandezh@hotmail.com。",
          sending: "发送中…", fix: "请填写必填项。" },
    ja: { ok: "ありがとうございます — メッセージを送信しました。追ってご連絡します。",
          err: "送信に失敗しました。再試行するか、Kohandezh@hotmail.com までご連絡ください。",
          sending: "送信中…", fix: "必須項目をご入力ください。" }
  };

  function t() {
    var l = (document.documentElement.lang || "en").toLowerCase().split("-")[0];
    return T[l] || T.en;
  }

  /* Where the submission actually goes.
     ------------------------------------------------------------------
     The forms' `action` is the public Web3Forms endpoint, and the access key
     used to travel with them in a hidden input — readable by anyone viewing
     source, and reusable by anyone to post through the owner's account.

     On WordPress (production) the key now lives in a server-side option and the
     POST goes to a same-origin proxy instead; the hidden input has been removed
     from the markup entirely. On the static build there is no server to hold a
     secret, so the form falls back to its own `action` and the proxy simply
     does not exist. `window.KDCV_WP` is printed by functions.php, so its
     presence is the reliable "am I on WordPress" test. */
  function endpoint(form) {
    var wp = window.KDCV_WP;
    if (wp && wp.rest) return wp.rest + "kohandezh/v1/contact";
    return form.getAttribute("action") || "https://api.web3forms.com/submit";
  }

  function notice(form, message, ok) {
    var old = form.querySelector(".kdcv-form-alert");
    if (old) old.remove();

    var box = document.createElement("div");
    box.className = "kdcv-form-alert " + (ok ? "is-ok" : "is-error");
    box.textContent = message;
    box.setAttribute("role", ok ? "status" : "alert");
    box.setAttribute("aria-live", ok ? "polite" : "assertive");
    form.insertBefore(box, form.firstChild);
    try { box.scrollIntoView({ block: "nearest", behavior: "smooth" }); } catch (e) {}
    return box;
  }

  function wire(form) {
    if (!form || form.dataset.kdcvForm) return;
    form.dataset.kdcvForm = "1";

    // Belt and braces: even if the attribute creeps back into the markup, the
    // form must never fall back to a native, unvalidated POST.
    form.removeAttribute("novalidate");

    form.addEventListener("submit", function (e) {
      // Always take over the submit — a native POST is what produced the
      // silent empty enquiries.
      e.preventDefault();
      e.stopPropagation();

      var L = t();

      if (!form.checkValidity()) {
        notice(form, L.fix, false);
        if (typeof form.reportValidity === "function") form.reportValidity();
        var bad = form.querySelector(":invalid");
        if (bad) { try { bad.focus(); } catch (ex) {} }
        return;
      }

      var button = form.querySelector('[type="submit"]');
      var label = button ? button.textContent : "";
      if (button) { button.disabled = true; button.textContent = L.sending; }

      fetch(endpoint(form), {
        method: "POST",
        body: new FormData(form),
        headers: { Accept: "application/json" }
      })
        .then(function (r) { return r.json().catch(function () { return { success: r.ok }; }); })
        .then(function (data) {
          if (data && data.success) {
            notice(form, L.ok, true);
            form.reset();
          } else {
            notice(form, (data && data.message) || L.err, false);
          }
        })
        .catch(function () { notice(form, L.err, false); })
        .then(function () {
          if (button) { button.disabled = false; button.textContent = label; }
        });
    });
  }

  function init() {
    var forms = document.querySelectorAll("form.form-contact, #contactform, #demoform");
    for (var i = 0; i < forms.length; i++) wire(forms[i]);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
