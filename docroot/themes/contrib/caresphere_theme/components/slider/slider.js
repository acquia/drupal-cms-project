/**
 * @file slider.js
 * Slider SDC — Figma desktop 1-9547, mobile 1-9299.
 * Wraps each slot child in .swiper-slide, then initializes Swiper (mobile: 144px/24px; desktop: 405.3px/32px).
 */
(function () {
  function initSlider(container) {
    if (container.getAttribute("data-slider-initialized") === "true") return;
    if (typeof window.Swiper === "undefined") return;

    // Inside Canvas editor, don't initialize Swiper because it moves DOM nodes,
    // which breaks the drag-and-drop capability.
    if (document.body.classList.contains("in-canvas")) return;

    var swiperEl = container.querySelector(".slider__swiper");
    if (!swiperEl) return;

    var wrapperEl = swiperEl.querySelector(".swiper-wrapper");
    if (!wrapperEl) return;

    // Detect empty state
    var emptyState = wrapperEl.querySelector(".slider__empty-state");

    // Wrap every direct element child that isn't already a .swiper-slide
    var children = Array.from(wrapperEl.children);
    var hasActualContent = false;
    children.forEach(function (child) {
      if (child.nodeType !== 1) return;
      if (child.classList.contains("slider__empty-state")) return;
      if (child.classList.contains("swiper-slide")) {
        hasActualContent = true;
        return;
      }

      var slide = document.createElement("div");
      slide.className = "swiper-slide";
      wrapperEl.insertBefore(slide, child);
      slide.appendChild(child);
      hasActualContent = true;
    });

    // If we have actual slides, remove the empty state if it exists
    if (hasActualContent && emptyState) {
      emptyState.remove();
    }

    // Don't init Swiper if there's nothing to slide
    if (!hasActualContent) return;

    var prevEl = container.querySelector("[data-slider-prev]");
    var nextEl = container.querySelector("[data-slider-next]");
    var paginationEl = container.querySelector("[data-slider-pagination]");

    var autoplay = container.getAttribute("data-slider-autoplay") === "1";
    var delay =
      parseInt(container.getAttribute("data-slider-delay"), 10) || 4000;
    var loop = container.getAttribute("data-slider-loop") === "1";
    var showArrows = container.getAttribute("data-slider-arrows") !== "0";
    var showPagination =
      container.getAttribute("data-slider-pagination") !== "0";

    var slideCount = wrapperEl.querySelectorAll(".swiper-slide").length;
    if (slideCount <= 1) loop = false;

    var config = {
      slidesPerView: "auto",
      spaceBetween: 24,
      loop: loop,
      grabCursor: true,
      keyboard: { enabled: true },
      a11y: {
        prevSlideMessage: "Previous slide",
        nextSlideMessage: "Next slide",
        paginationBulletMessage: "Go to slide {{index}}",
      },
      navigation:
        showArrows && prevEl && nextEl
          ? { nextEl: nextEl, prevEl: prevEl }
          : false,
      pagination:
        showPagination && paginationEl
          ? { el: paginationEl, clickable: true }
          : false,
      breakpoints: {
        768: { spaceBetween: 32 },
      },
    };

    if (autoplay && slideCount > 1) {
      config.autoplay = { delay: delay, disableOnInteraction: false };
    }

    new window.Swiper(swiperEl, config);
    container.setAttribute("data-slider-initialized", "true");
  }

  function init() {
    document.querySelectorAll(".slider[data-slider]").forEach(initSlider);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  if (typeof Drupal !== "undefined" && Drupal.behaviors) {
    Drupal.behaviors.sliderSdc = {
      attach: function (context) {
        var containers = context.querySelectorAll
          ? context.querySelectorAll(".slider[data-slider]")
          : [];
        containers.forEach(initSlider);
      },
    };
  }
})();
