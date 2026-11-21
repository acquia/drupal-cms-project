/**
 * @file accordion.js
 * Accordion SDC: expand/collapse panels.
 * Multiple panels can be open at once; closing one does not affect others.
 */
(function () {
  function openPanel(item) {
    var panel = item.querySelector("[data-accordion-panel]");
    var trigger = item.querySelector("[data-accordion-trigger]");
    if (!panel || !trigger) return;
    panel.removeAttribute("hidden");
    trigger.setAttribute("aria-expanded", "true");
    item.classList.add("accordion__item--open");
  }

  function closePanel(item) {
    var panel = item.querySelector("[data-accordion-panel]");
    var trigger = item.querySelector("[data-accordion-trigger]");
    if (!panel || !trigger) return;
    panel.setAttribute("hidden", "");
    trigger.setAttribute("aria-expanded", "false");
    item.classList.remove("accordion__item--open");
  }

  function isOpen(item) {
    var trigger = item.querySelector("[data-accordion-trigger]");
    return trigger && trigger.getAttribute("aria-expanded") === "true";
  }

  function toggleItem(item) {
    if (isOpen(item)) {
      closePanel(item);
    } else {
      openPanel(item);
    }
  }

  function handleTriggerClick(e) {
    var trigger = e.currentTarget;
    var item = trigger.closest("[data-accordion-item]");
    if (!item) return;
    e.preventDefault();
    toggleItem(item);
  }

  function handleCloseClick(e) {
    var btn = e.currentTarget;
    var item = btn.closest("[data-accordion-item]");
    if (!item) return;
    e.preventDefault();
    closePanel(item);
  }

  function bindItem(item) {
    if (item.getAttribute("data-accordion-initialized") === "true") return;
    item.setAttribute("data-accordion-initialized", "true");

    var trigger = item.querySelector("[data-accordion-trigger]");
    var close = item.querySelector("[data-accordion-close]");

    if (trigger) {
      trigger.addEventListener("click", handleTriggerClick);
    }
    if (close) {
      close.addEventListener("click", handleCloseClick);
    }

    // Open panel if marked as default expanded (via prop / data-accordion-open-default).
    if (item.hasAttribute("data-accordion-open-default")) {
      openPanel(item);
    }
  }

  function init() {
    var items = document.querySelectorAll("[data-accordion-item]");
    items.forEach(bindItem);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  if (typeof Drupal !== "undefined" && Drupal.behaviors) {
    Drupal.behaviors.accordionSdc = {
      attach: function (context) {
        var items = context.querySelectorAll
          ? context.querySelectorAll("[data-accordion-item]")
          : [];
        items.forEach(bindItem);
      },
    };
  }
})();
