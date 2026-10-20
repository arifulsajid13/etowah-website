// Apply the saved dashboard theme before the page paints (dark is the default).
(function () {
  try {
    if (localStorage.getItem("etowah-admin-theme") === "light") {
      document.documentElement.setAttribute("data-theme", "light");
    }
  } catch (e) { /* storage blocked: stay dark */ }
})();
