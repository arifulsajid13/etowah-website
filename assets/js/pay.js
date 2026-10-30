/* Pay pages: print button on the printable invoice, auto-submit to the hosted payment page. */
(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-print]").forEach(function (btn) {
      btn.addEventListener("click", function () { window.print(); });
    });
    var form = document.querySelector("form[data-auto-submit]");
    if (form) form.submit();
  });
})();
