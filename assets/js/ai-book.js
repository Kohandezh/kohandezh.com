(function () {
  "use strict";
  var button = document.querySelector(".ab-menu-button");
  var menu = document.getElementById("mobile-nav");
  if (!button || !menu) return;
  button.addEventListener("click", function () {
    var open = button.getAttribute("aria-expanded") === "true";
    button.setAttribute("aria-expanded", String(!open));
    menu.hidden = open;
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !menu.hidden) {
      menu.hidden = true;
      button.setAttribute("aria-expanded", "false");
      button.focus();
    }
  });
})();
