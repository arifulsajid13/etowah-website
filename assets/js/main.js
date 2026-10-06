// Paste a form service URL (e.g. Formspree) here to send submissions directly.
const FORM_ENDPOINT = "";

(function () {
  "use strict";

  var root = document.documentElement;
  root.classList.remove("no-js");
  root.classList.add("js");

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var mobileQuery = window.matchMedia("(max-width: 959.98px)");

  document.addEventListener("DOMContentLoaded", function () {
    initHeader();
    initReveal();
    initContactForm();
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

  /* Contact form
     ======================================================================== */

  function initContactForm() {
    var form = document.querySelector("[data-contact-form]");
    if (!form) return;

    var status = form.querySelector(".form-status");
    var submitBtn = form.querySelector("button[type='submit']");
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    var rules = [
      { name: "name", message: "Please enter your full name." },
      { name: "email", message: "Please enter your email address.", invalid: "Please enter a valid email address, like name@company.com." },
      { name: "message", message: "Please tell us about your project." }
    ];

    function fieldError(input) {
      return document.getElementById(input.id + "-error");
    }

    function showError(input, message) {
      var wrapper = input.closest(".field");
      var error = fieldError(input);
      wrapper.classList.add("has-error");
      input.setAttribute("aria-invalid", "true");
      error.textContent = message;
      error.hidden = false;
    }

    function clearError(input) {
      var wrapper = input.closest(".field");
      var error = fieldError(input);
      wrapper.classList.remove("has-error");
      input.removeAttribute("aria-invalid");
      error.textContent = "";
      error.hidden = true;
    }

    function checkField(rule) {
      var input = form.elements[rule.name];
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
      input.addEventListener("input", function () {
        if (input.getAttribute("aria-invalid") === "true" && !checkField(rule)) clearError(input);
      });
    });

    function setStatus(html, isError) {
      status.innerHTML = html;
      status.classList.toggle("is-error", !!isError);
      status.hidden = false;
    }

    function value(name) {
      var el = form.elements[name];
      return el ? el.value.trim() : "";
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      status.hidden = true;
      if (!validate()) return;

      if (FORM_ENDPOINT) {
        var label = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.textContent = "Sending…";

        fetch(FORM_ENDPOINT, {
          method: "POST",
          body: new FormData(form),
          headers: { Accept: "application/json" }
        })
          .then(function (response) {
            if (!response.ok) throw new Error("Request failed");
            var success = document.createElement("div");
            success.className = "form-success";
            success.setAttribute("role", "status");
            success.setAttribute("tabindex", "-1");
            success.textContent = "Thank you — your message has been sent. Kenny will be in touch shortly.";
            form.replaceWith(success);
            success.focus();
          })
          .catch(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = label;
            setStatus(
              'Sorry — your message could not be sent. Please try again, or email <a href="mailto:Kenny@etowahconstruction.com">Kenny@etowahconstruction.com</a> directly.',
              true
            );
          });
        return;
      }

      var lines = [
        "Full Name: " + value("name"),
        "Company: " + (value("company") || "Not provided"),
        "Email: " + value("email"),
        "Phone: " + (value("phone") || "Not provided"),
        "Project Type: " + (value("project_type") || "Not provided"),
        "Project Location: " + (value("location") || "Not provided"),
        "",
        "Message:",
        value("message")
      ];
      var subject = "Project Inquiry — " + value("name");
      var href = "mailto:Kenny@etowahconstruction.com" +
        "?subject=" + encodeURIComponent(subject) +
        "&body=" + encodeURIComponent(lines.join("\n"));

      window.location.href = href;
      setStatus(
        'Your email app has been opened with your message. If it didn’t open, email <a href="mailto:Kenny@etowahconstruction.com">Kenny@etowahconstruction.com</a> directly.',
        false
      );
    });
  }
})();
