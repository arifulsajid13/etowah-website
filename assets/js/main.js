(function () {
  "use strict";

  var root = document.documentElement;
  root.classList.remove("no-js");
  root.classList.add("js");

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var mobileQuery = window.matchMedia("(max-width: 1023.98px)");

  document.addEventListener("DOMContentLoaded", function () {
    initHeader();
    initReveal();
    initForms();
  });

  /* Header: scroll shadow, Services dropdown, mobile menu
     ======================================================================== */

  function initHeader() {
    var header = document.querySelector(".site-header");
    if (!header) return;

    var onScroll = function () {
      header.classList.toggle("is-scrolled", window.scrollY > 4);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });

    var dropdown = header.querySelector(".has-dropdown");
    var dropdownBtn = dropdown && dropdown.querySelector(".dropdown-toggle");
    var navToggle = header.querySelector(".nav-toggle");
    var nav = header.querySelector(".site-nav");

    // Services dropdown ----------------------------------------------------
    var openedByHover = false;
    var suppressFocusOpen = false;

    function setDropdown(open) {
      dropdown.classList.toggle("is-open", open);
      dropdownBtn.setAttribute("aria-expanded", String(open));
      if (!open) openedByHover = false;
    }
    function dropdownIsOpen() {
      return dropdownBtn.getAttribute("aria-expanded") === "true";
    }

    if (dropdown && dropdownBtn) {
      dropdownBtn.addEventListener("click", function (event) {
        // A mouse click on a menu that hover already opened keeps it open.
        if (!mobileQuery.matches && event.detail > 0 && openedByHover) {
          openedByHover = false;
          return;
        }
        setDropdown(!dropdownIsOpen());
      });

      dropdown.addEventListener("mouseenter", function () {
        if (mobileQuery.matches || dropdownIsOpen()) return;
        setDropdown(true);
        openedByHover = true;
      });
      dropdown.addEventListener("mouseleave", function () {
        if (mobileQuery.matches) return;
        if (!dropdown.contains(document.activeElement)) setDropdown(false);
      });

      dropdown.addEventListener("pointerdown", function () {
        suppressFocusOpen = true;
      });
      dropdown.addEventListener("focusin", function () {
        if (mobileQuery.matches || suppressFocusOpen) return;
        setDropdown(true);
      });
      dropdown.addEventListener("focusout", function (event) {
        if (dropdown.contains(event.relatedTarget)) return;
        suppressFocusOpen = false;
        if (!mobileQuery.matches) setDropdown(false);
      });

      dropdown.addEventListener("keydown", function (event) {
        // On mobile, Escape is left to close the whole menu.
        if (event.key === "Escape" && dropdownIsOpen() && !mobileQuery.matches) {
          event.stopPropagation();
          suppressFocusOpen = true;
          setDropdown(false);
          dropdownBtn.focus();
        }
      });

      document.addEventListener("click", function (event) {
        if (!mobileQuery.matches && !dropdown.contains(event.target)) setDropdown(false);
      });
    }

    // Mobile menu ----------------------------------------------------------
    if (!navToggle || !nav) return;

    function menuIsOpen() {
      return navToggle.getAttribute("aria-expanded") === "true";
    }

    function setMenu(open) {
      navToggle.setAttribute("aria-expanded", String(open));
      navToggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
      header.classList.toggle("menu-is-open", open);
      document.body.classList.toggle("menu-open", open);
      if (open) {
        var first = nav.querySelector("a, button");
        if (first) first.focus();
      }
    }

    navToggle.addEventListener("click", function () {
      setMenu(!menuIsOpen());
    });

    document.addEventListener("keydown", function (event) {
      if (!menuIsOpen()) return;

      if (event.key === "Escape") {
        setMenu(false);
        navToggle.focus();
        return;
      }

      // Keep keyboard focus inside the open menu.
      if (event.key === "Tab") {
        var focusable = [navToggle].concat(
          Array.prototype.filter.call(nav.querySelectorAll("a, button"), function (el) {
            return el.offsetParent !== null;
          })
        );
        var firstEl = focusable[0];
        var lastEl = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === firstEl) {
          event.preventDefault();
          lastEl.focus();
        } else if (!event.shiftKey && document.activeElement === lastEl) {
          event.preventDefault();
          firstEl.focus();
        }
      }
    });

    nav.addEventListener("click", function (event) {
      if (event.target.closest("a") && menuIsOpen()) setMenu(false);
    });

    var onBreakpointChange = function () {
      if (!mobileQuery.matches && menuIsOpen()) setMenu(false);
      if (dropdown) setDropdown(false);
    };
    if (mobileQuery.addEventListener) {
      mobileQuery.addEventListener("change", onBreakpointChange);
    } else if (mobileQuery.addListener) {
      mobileQuery.addListener(onBreakpointChange);
    }
  }

  /* Fade-up on scroll
     ======================================================================== */

  function initReveal() {
    document.querySelectorAll("[data-stagger]").forEach(function (group) {
      Array.prototype.forEach.call(group.children, function (child, index) {
        child.classList.add("reveal");
        child.dataset.revealDelay = String(index * 80);
      });
    });

    var items = document.querySelectorAll(".reveal");
    if (!items.length) return;

    if (reduceMotion || !("IntersectionObserver" in window)) {
      items.forEach(function (el) {
        el.classList.remove("reveal");
      });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var delay = parseInt(el.dataset.revealDelay || "0", 10);
        if (delay) el.style.transitionDelay = delay + "ms";
        el.classList.add("is-visible");
        observer.unobserve(el);

        // Hand the element back to its own hover transitions once revealed.
        window.setTimeout(function () {
          el.classList.remove("reveal", "is-visible");
          el.style.transitionDelay = "";
        }, delay + 700);
      });
    }, { rootMargin: "0px 0px -40px 0px", threshold: 0.12 });

    items.forEach(function (el) {
      observer.observe(el);
    });
  }

  /* Contact and job application forms
     ======================================================================== */

  var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var resumeTypes = /\.(pdf|docx?)$/i;
  var resumeMaxBytes = 5 * 1024 * 1024;

  var formConfigs = {
    contact: {
      selector: "[data-contact-form]",
      rules: [
        { name: "name", message: "Please enter your full name." },
        { name: "email", message: "Please enter your email address.", invalid: "Please enter a valid email address, like name@company.com." },
        { name: "message", message: "Please tell us about your project." }
      ]
    },
    apply: {
      selector: "[data-apply-form]",
      rules: [
        { name: "name", message: "Please enter your full name." },
        { name: "email", message: "Please enter your email address.", invalid: "Please enter a valid email address, like name@company.com." },
        { name: "resume", message: "Please attach your resume.", file: true }
      ]
    }
  };

  function initForms() {
    Object.keys(formConfigs).forEach(function (key) {
      document.querySelectorAll(formConfigs[key].selector).forEach(function (form) {
        initForm(form, formConfigs[key].rules);
      });
    });
  }

  function initForm(form, rules) {
    var status = form.querySelector(".form-status");
    var submitBtn = form.querySelector("button[type='submit']");

    function fieldError(input) {
      return document.getElementById(input.id + "-error");
    }

    function showError(input, message) {
      var error = fieldError(input);
      if (!error) return;
      input.closest(".field").classList.add("has-error");
      input.setAttribute("aria-invalid", "true");
      error.textContent = message;
      error.hidden = false;
    }

    function clearError(input) {
      var error = fieldError(input);
      if (!error) return;
      input.closest(".field").classList.remove("has-error");
      input.removeAttribute("aria-invalid");
      error.textContent = "";
      error.hidden = true;
    }

    function checkField(rule) {
      var input = form.elements[rule.name];
      if (rule.file) {
        var file = input.files && input.files[0];
        if (!file) return rule.message;
        if (!resumeTypes.test(file.name)) return "Please upload your resume as a PDF, DOC, or DOCX file.";
        if (file.size > resumeMaxBytes) return "That file is too large. The limit is 5 MB.";
        return "";
      }
      var value = input.value.trim();
      if (!value) return rule.message;
      if (rule.name === "email" && !emailPattern.test(value)) return rule.invalid;
      return "";
    }

    function validate() {
      var firstInvalid = null;
      rules.forEach(function (rule) {
        var input = form.elements[rule.name];
        var message = checkField(rule);
        if (message) {
          showError(input, message);
          if (!firstInvalid) firstInvalid = input;
        } else {
          clearError(input);
        }
      });
      if (firstInvalid) firstInvalid.focus();
      return !firstInvalid;
    }

    rules.forEach(function (rule) {
      var input = form.elements[rule.name];
      input.addEventListener(rule.file ? "change" : "input", function () {
        if (input.getAttribute("aria-invalid") === "true" && !checkField(rule)) clearError(input);
      });
    });

    // Fallback message with the phone number and email from the site settings.
    function showFailure(message) {
      status.textContent = "";
      if (message) {
        status.textContent = message;
      } else {
        var phone = form.getAttribute("data-error-phone") || "";
        var email = form.getAttribute("data-error-email") || "";
        var tel = document.createElement("a");
        tel.href = "tel:+1" + phone.replace(/\D/g, "").replace(/^1(?=\d{10}$)/, "");
        tel.textContent = phone;
        var mail = document.createElement("a");
        mail.href = "mailto:" + email;
        mail.textContent = email;
        status.append("Something went wrong. Please call ", tel, " or email ", mail, " directly.");
      }
      status.classList.add("is-error");
      status.hidden = false;
    }

    function showServerErrors(errors) {
      var firstInvalid = null;
      Object.keys(errors).forEach(function (name) {
        var input = form.elements[name];
        if (input && fieldError(input)) {
          showError(input, errors[name]);
          if (!firstInvalid) firstInvalid = input;
        }
      });
      if (firstInvalid) {
        firstInvalid.focus();
      } else {
        showFailure(Object.keys(errors).map(function (k) { return errors[k]; }).join(" "));
      }
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      status.hidden = true;
      if (!validate()) return;

      var label = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.textContent = "Sending…";

      function reset() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = label;
      }

      fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: { Accept: "application/json" },
        credentials: "same-origin"
      })
        .then(function (response) {
          return response.json().catch(function () { return { ok: false }; });
        })
        .then(function (data) {
          if (data && data.ok) {
            var success = document.createElement("div");
            success.className = "form-success";
            success.setAttribute("role", "status");
            success.setAttribute("tabindex", "-1");
            success.textContent = data.message;
            form.replaceWith(success);
            success.focus();
            return;
          }
          reset();
          if (data && data.errors) {
            showServerErrors(data.errors);
          } else {
            showFailure(data && data.message);
          }
        })
        .catch(function () {
          reset();
          showFailure("");
        });
    });
  }
})();
