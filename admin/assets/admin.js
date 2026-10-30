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
    initExperience();
    initHomePosition();
    initSeoPreview();
    initProjectSerp();
    initStrength();
    initVideoPoster();
    initInvoiceForm();
    initCopy();
  });

  /* Copy a value (pay link) to the clipboard --------------------------------- */
  function initCopy() {
    document.querySelectorAll("[data-copy]").forEach(function (btn) {
      var input = document.getElementById(btn.getAttribute("data-copy"));
      if (!input) return;
      btn.addEventListener("click", function () {
        var label = btn.querySelector("span");
        var done = function () {
          if (!label) return;
          var old = label.textContent;
          label.textContent = "Copied";
          window.setTimeout(function () { label.textContent = old; }, 2000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(input.value).then(done, function () { input.select(); });
        } else {
          input.select();
          try { document.execCommand("copy"); done(); } catch (e) { /* the link stays selected */ }
        }
      });
    });
  }

  /* Invoice form: line items with live totals, customer picker, due date from terms.
     The server recalculates every amount on save; these totals are only a preview. */
  function initInvoiceForm() {
    var form = document.querySelector("[data-invoice-form]");
    if (!form) return;
    var rows = form.querySelector("[data-item-rows]");
    var template = document.getElementById("item-row-template");
    var taxInput = form.querySelector("[data-tax-rate]");
    var sums = form.querySelector("[data-sums]");

    // Whole cents from "1,234.5"; null when not a valid amount.
    function cents(value) {
      var m = String(value).replace(/[\s,$]/g, "").match(/^(\d{0,9})(?:\.(\d{0,2}))?$/);
      if (!m || (m[1] === "" && !m[2])) return null;
      return parseInt(m[1] || "0", 10) * 100 + parseInt(((m[2] || "") + "00").slice(0, 2), 10);
    }
    function hundredths(value) {
      var m = String(value).replace(/[\s,]/g, "").match(/^(\d{1,7})(?:\.(\d{0,2}))?$/);
      return m ? parseInt(m[1], 10) * 100 + parseInt(((m[2] || "") + "00").slice(0, 2), 10) : null;
    }
    function thousandths(value) {
      var m = String(value).replace(/[\s%]/g, "").match(/^(\d{0,3})(?:\.(\d{0,3}))?$/);
      return m ? parseInt(m[1] || "0", 10) * 1000 + parseInt(((m[2] || "") + "000").slice(0, 3), 10) : null;
    }
    function money(c) {
      var neg = c < 0;
      c = Math.abs(c);
      var dollars = Math.floor(c / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      return (neg ? "-$" : "$") + dollars + "." + ("0" + (c % 100)).slice(-2);
    }

    function recalc() {
      var subtotal = 0;
      rows.querySelectorAll("[data-item]").forEach(function (row) {
        var q = hundredths(row.querySelector("[data-item-qty]").value);
        var p = cents(row.querySelector("[data-item-price]").value);
        var out = row.querySelector("[data-item-total]");
        if (q === null || p === null) {
          out.textContent = "—";
          return;
        }
        var line = Math.floor((q * p + 50) / 100);
        subtotal += line;
        out.textContent = money(line);
      });
      var rate = taxInput ? thousandths(taxInput.value) : 0;
      var tax = rate ? Math.floor((subtotal * rate + 50000) / 100000) : 0;
      if (sums) {
        sums.querySelector("[data-sum-subtotal]").textContent = money(subtotal);
        sums.querySelector("[data-sum-tax-label]").textContent = sums.getAttribute("data-tax-label") + (rate ? " (" + (rate / 1000) + "%)" : "");
        sums.querySelector("[data-sum-tax]").textContent = money(tax);
        sums.querySelector("[data-sum-total]").textContent = money(subtotal + tax);
      }
    }

    form.addEventListener("input", recalc);
    form.addEventListener("click", function (event) {
      var btn = event.target.closest("button");
      if (!btn) return;
      var row = btn.closest("[data-item]");
      if (btn.hasAttribute("data-item-add") && template) {
        rows.appendChild(template.content.cloneNode(true));
        rows.lastElementChild.querySelector("textarea").focus();
      } else if (row && btn.hasAttribute("data-item-remove")) {
        if (rows.querySelectorAll("[data-item]").length > 1) {
          row.remove();
        } else {
          row.querySelectorAll("textarea, input").forEach(function (el) { el.value = el.hasAttribute("data-item-qty") ? "1" : ""; });
        }
      } else if (row && btn.hasAttribute("data-item-up") && row.previousElementSibling) {
        rows.insertBefore(row, row.previousElementSibling);
        btn.focus();
      } else if (row && btn.hasAttribute("data-item-down") && row.nextElementSibling) {
        rows.insertBefore(row.nextElementSibling, row);
        btn.focus();
      } else {
        return;
      }
      recalc();
    });

    // Due date follows issue date + terms until it is changed by hand.
    var issue = form.querySelector("[data-issue-date]");
    var terms = form.querySelector("[data-terms]");
    var due = form.querySelector("[data-due-date]");
    function setDue() {
      if (!issue.value || due.dataset.manual === "1") return;
      var days = parseInt(terms.options[terms.selectedIndex].getAttribute("data-days"), 10) || 0;
      var d = new Date(issue.value + "T12:00:00");
      d.setDate(d.getDate() + days);
      due.value = d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2);
    }
    if (issue && terms && due && !issue.disabled) {
      issue.addEventListener("change", setDue);
      terms.addEventListener("change", setDue);
      due.addEventListener("change", function () { due.dataset.manual = "1"; });
    }

    var pick = form.querySelector("[data-customer-pick]");
    if (pick) {
      pick.addEventListener("change", function () {
        var option = pick.options[pick.selectedIndex];
        var data;
        try { data = JSON.parse(option.getAttribute("data-customer") || "null"); } catch (e) { data = null; }
        if (!data) return;
        Object.keys(data).forEach(function (key) {
          var field = form.querySelector('[name="' + key + '"]');
          if (field) field.value = data[key] || "";
        });
      });
    }
    recalc();
  }

  /* Gallery video upload: when no poster image is chosen, take one from a frame of the video
     (the server cannot read video frames). The upload still works without this: the server then asks for a poster. */
  function initVideoPoster() {
    var form = document.querySelector("[data-video-upload]");
    if (!form || typeof DataTransfer !== "function") return;
    var fileInput = form.querySelector("[data-video-file]");
    var posterInput = form.querySelector("[data-video-poster]");
    var preview = form.querySelector("[data-video-poster-preview]");
    var autoPoster = false;

    fileInput.addEventListener("change", function () {
      var file = fileInput.files[0];
      if (!file || (posterInput.files.length && !autoPoster)) return;
      var url = URL.createObjectURL(file);
      var video = document.createElement("video");
      video.muted = true;
      video.playsInline = true;
      video.preload = "auto";
      video.addEventListener("loadedmetadata", function () {
        video.currentTime = Math.min(1, (video.duration || 2) / 2);
      });
      video.addEventListener("seeked", function () {
        var canvas = document.createElement("canvas");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext("2d").drawImage(video, 0, 0);
        canvas.toBlob(function (blob) {
          URL.revokeObjectURL(url);
          if (!blob) return;
          var transfer = new DataTransfer();
          transfer.items.add(new File([blob], "poster.jpg", { type: "image/jpeg" }));
          posterInput.files = transfer.files;
          autoPoster = true;
          preview.src = URL.createObjectURL(blob);
          preview.hidden = false;
        }, "image/jpeg", 0.9);
      }, { once: true });
      video.addEventListener("error", function () { URL.revokeObjectURL(url); });
      video.src = url;
    });
    posterInput.addEventListener("change", function () {
      autoPoster = false;
      preview.hidden = true;
    });
  }

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
        askConfirm(form.getAttribute("data-confirm"), form.getAttribute("data-confirm-ok") || "Yes, delete", function () {
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

  /* Job form: Requirement only applies to an experience range in years -------- */
  function initExperience() {
    var range = document.querySelector("[data-experience]");
    var field = document.querySelector("[data-experience-level]");
    if (!range || !field) return;
    var level = field.querySelector("select");
    function update() {
      var option = range.options[range.selectedIndex];
      var off = !range.value || option.hasAttribute("data-no-level");
      field.hidden = off;
      level.disabled = off;
    }
    range.addEventListener("change", update);
    update();
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
