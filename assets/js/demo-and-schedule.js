(function () {
    "use strict";

    function initDemoToggle() {
        var section = document.getElementById("demo");
        if (!section) return;
        var buttons = section.querySelectorAll(".demo-mode-btn");
        var notes = section.querySelectorAll("[data-mode-note]");
        var ctas = section.querySelectorAll("[data-mode-cta]");
        var modeField = document.getElementById("demo-mode-field");

        buttons.forEach(function (btn) {
            btn.addEventListener("click", function () {
                buttons.forEach(function (b) {
                    b.classList.remove("is-active");
                    b.setAttribute("aria-selected", "false");
                });
                btn.classList.add("is-active");
                btn.setAttribute("aria-selected", "true");

                var mode = btn.dataset.mode;
                notes.forEach(function (n) { n.hidden = n.dataset.modeNote !== mode; });
                ctas.forEach(function (c) { c.hidden = c.dataset.modeCta !== mode; });
                if (modeField) {
                    var label = btn.querySelector("span");
                    modeField.value = label ? label.textContent : mode;
                }
            });
        });
    }

    function initScheduleEmbed() {
        document.querySelectorAll(".schedule-embed").forEach(function (el) {
            var url = el.dataset.bookingUrl;
            if (!url) return;
            var iframe = el.querySelector("iframe");
            if (iframe) iframe.src = url;
            el.classList.add("is-ready");
        });
    }

    initDemoToggle();
    initScheduleEmbed();
}());
