(function () {
  "use strict";

  var root = document.documentElement;

  document.addEventListener("DOMContentLoaded", function () {
    initTheme();
    initDrawer();
    initToasts();
    initConfirm();
    initCounters();
    initSlugs();
    initServiceToggles();
    initHomePosition();
    initSeoPreview();
    initProjectSerp();
    initStrength();
  });

  /* Project SEO: Google preview showing the automatic values when fields are empty */
  function initProjectSerp() {
    var serp = document.querySelector("[data-project-serp]");
    if (!serp) return;
    var field = function (id) { return document.getElementById(id); };
    var title = field("p-title");
    var location = field("p-location");
    var summary = field("p-summary");
    var seoTitle = field("p-seo-title");
    var seoDesc = field("p-seo-desc");
    var slug = field("p-slug");
    var host = serp.getAttribute("data-host");
    var note = document.querySelector("[data-serp-note]");

    function update() {
      var autoTitle = [title.value.trim(), location.value.trim(), "Etowah Construction Group"].filter(Boolean).join(" | ");
      var usingAuto = [];
      if (!seoTitle.value.trim()) usingAuto.push("title");
      if (!seoDesc.value.trim()) usingAuto.push("description");
      serp.querySelector("[data-serp-title]").textContent = seoTitle.value.trim() || autoTitle;
      serp.querySelector("[data-serp-desc]").textContent = seoDesc.value.trim() || summary.value.trim();
      serp.querySelector("[data-serp-url]").textContent = host + "/projects/" + (slug.value.trim() || "…");
      if (note) {
        note.textContent = usingAuto.length
          ? "Showing the automatic " + usingAuto.join(" and ") + ". Google may shorten or rewrite them."
          : "Google may shorten or rewrite the title and description.";
      }
    }
    [title, location, summary, seoTitle, seoDesc, slug].forEach(function (el) {
      if (el) el.addEventListener("input", update);
    });
    update();
  }

  /* Theme toggle (remembered per browser) ------------------------------------ */
  function initTheme() {
    var btn = document.querySelector("[data-theme-toggle]");
    if (!btn) return;
    function label() {
      var light = root.getAttribute("data-theme") === "light";
      btn.setAttribute("aria-label", light ? "Switch to dark theme" : "Switch to light theme");
    }
    label();
    btn.addEventListener("click", function () {
      var next = root.getAttribute("data-theme") === "light" ? "dark" : "light";
      root.setAttribute("data-theme", next);
      try { localStorage.setItem("etowah-admin-theme", next); } catch (e) { /* ignore */ }
      label();
    });
  }

  /* Sidebar drawer under 900px ------------------------------------------------ */
  function initDrawer() {
    var openBtn = document.querySelector("[data-drawer-open]");
    var backdrop = document.querySelector(".drawer-backdrop");
    if (!openBtn) return;
    function set(open) {
      document.body.classList.toggle("drawer-open", open);
      openBtn.setAttribute("aria-expanded", String(open));
      if (backdrop) backdrop.hidden = !open;
      if (open) {
        var first = document.querySelector("#sidebar a");
        if (first) first.focus();
      }
    }
    openBtn.addEventListener("click", function () { set(true); });
    document.querySelectorAll("[data-drawer-close]").forEach(function (el) {
      el.addEventListener("click", function () { set(false); openBtn.focus(); });
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && document.body.classList.contains("drawer-open")) {
        set(false);
        openBtn.focus();
      }
    });
  }

  /* Toasts ------------------------------------------------------------------- */
  function initToasts() {
    document.querySelectorAll("[data-toast]").forEach(function (toast) {
      function hide() {
        toast.classList.add("is-hiding");
        window.setTimeout(function () { toast.remove(); }, 300);
      }
      var close = toast.querySelector("[data-toast-close]");
      if (close) close.addEventListener("click", hide);
      if (!toast.classList.contains("toast--error")) window.setTimeout(hide, 6000);
    });
  }

  /* Custom confirm dialog ---------------------------------------------------- */
  var dialog;
  function askConfirm(text, okLabel, onOk) {
    dialog = dialog || document.getElementById("confirm-dialog");
    if (!dialog || typeof dialog.showModal !== "function") {
      onOk();
      return;
    }
    dialog.querySelector("[data-confirm-text]").textContent = text;
    dialog.querySelector("[data-confirm-ok]").textContent = okLabel || "Confirm";
    dialog.returnValue = "";
    dialog.showModal();
    dialog.addEventListener("close", function handler() {
      dialog.removeEventListener("close", handler);
      if (dialog.returnValue === "ok") onOk();
    });
  }

  function initConfirm() {
    // Single buttons inside a larger form (e.g. "Remove" on a gallery photo).
    document.querySelectorAll("button[data-confirm]").forEach(function (btn) {
      btn.addEventListener("click", function (event) {
        if (btn.dataset.confirmed === "1") return;
        event.preventDefault();
        askConfirm(btn.getAttribute("data-confirm"), "Yes, remove", function () {
          btn.dataset.confirmed = "1";
          if (btn.form.requestSubmit) {
            btn.form.requestSubmit(btn);
          } else {
            btn.click();
          }
        });
      });
    });
    document.querySelectorAll("form[data-confirm]").forEach(function (form) {
      form.addEventListener("submit", function (event) {
        if (form.dataset.confirmed === "1") return;
        event.preventDefault();
        askConfirm(form.getAttribute("data-confirm"), "Yes, delete", function () {
          form.dataset.confirmed = "1";
          form.submit();
        });
      });
    });
  }

  /* Live character counters -------------------------------------------------- */
  function initCounters() {
    document.querySelectorAll("[data-counter]").forEach(function (input) {
      var max = parseInt(input.getAttribute("data-counter"), 10);
      var out = document.querySelector('[data-counter-for="' + input.id + '"]');
      if (!out) return;
      function update() {
        var len = input.value.length;
        out.textContent = len + " / " + max;
        out.classList.toggle("is-over", len > max);
      }
      input.addEventListener("input", update);
      update();
    });
  }

  /* Slug from title ---------------------------------------------------------- */
  function slugify(text) {
    return text.toLowerCase().replace(/&/g, " and ").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 100);
  }
  function initSlugs() {
    document.querySelectorAll("[data-slug-source]").forEach(function (title) {
      var slug = document.querySelector(title.getAttribute("data-slug-source"));
      if (!slug) return;
      // Typing in the slug field stops the automatic copy from the title.
      slug.addEventListener("input", function () {
        slug.removeAttribute("data-auto-slug");
        slug.value = slug.value.toLowerCase().replace(/[^a-z0-9-]+/g, "-");
      });
      title.addEventListener("input", function () {
        if (slug.hasAttribute("data-auto-slug")) slug.value = slugify(title.value);
      });
    });
  }

  /* Project form: per-service card options ----------------------------------- */
  function initServiceToggles() {
    document.querySelectorAll("[data-service-toggle]").forEach(function (box) {
      var panel = document.getElementById(box.getAttribute("data-service-toggle"));
      if (!panel) return;
      box.addEventListener("change", function () { panel.hidden = !box.checked; });
    });
  }

  /* Project form: homepage spot already taken -------------------------------- */
  function initHomePosition() {
    var form = document.querySelector("[data-project-form]");
    var select = form && form.querySelector("[data-home-used]");
    if (!select) return;
    var used = {};
    try { used = JSON.parse(select.getAttribute("data-home-used")) || {}; } catch (e) { used = {}; }
    form.addEventListener("submit", function (event) {
      var current = used[select.value];
      if (!current || form.dataset.confirmed === "1") return;
      event.preventDefault();
      askConfirm("“" + current + "” is in this homepage spot now. Move it off the homepage and put this project there instead?", "Yes, move it", function () {
        form.dataset.confirmed = "1";
        form.submit();
      });
    });
  }

  /* Pages & SEO: Google preview ---------------------------------------------- */
  function initSeoPreview() {
    document.querySelectorAll("[data-preview]").forEach(function (input) {
      var target = document.querySelector("[data-preview-" + input.getAttribute("data-preview") + "]");
      if (!target) return;
      input.addEventListener("input", function () { target.textContent = input.value; });
    });
  }

  /* Password strength hint --------------------------------------------------- */
  function initStrength() {
    document.querySelectorAll("[data-strength]").forEach(function (input) {
      var out = document.querySelector('[data-strength-for="' + input.id + '"]');
      if (!out) return;
      input.addEventListener("input", function () {
        var v = input.value;
        var score = 0;
        if (v.length >= 16) score++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
        if (/\d/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        var level = v.length < 12 ? "weak" : score >= 3 ? "strong" : "ok";
        out.setAttribute("data-level", level);
        out.textContent = v ? (level === "weak" ? "Too short" : level === "ok" ? "Okay" : "Strong") : "";
      });
    });
  }
})();
