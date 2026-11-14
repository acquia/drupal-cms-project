import { ComponentType, ComponentInstance } from "../../lib/component.js";
import currentlyInCanvasEditor from "../../lib/currentlyInCanvasEditor.js";

class Carousel extends ComponentInstance {
  init() {
    const inCanvas = currentlyInCanvasEditor();

    if (inCanvas || window.self !== window.top) {
      // In Canvas editor, disable Swiper and show all slides
      this.el.classList.add("carousel--canvas");
      return;
    }

    this.initSwiper();
  }

  initSwiper() {
    const Swiper = window.Swiper;

    // Get navigation and pagination elements
    const prevButton = this.el.querySelector(".carousel-button-prev");
    const nextButton = this.el.querySelector(".carousel-button-next");
    const pagination = this.el.querySelector(".carousel-pagination");

    // Initialize Swiper
    this.swiper = new Swiper(this.el, {
      // Display one slide at a time
      slidesPerView: 1,
      spaceBetween: 0,

      // No autoplay
      autoplay: false,

      // Loop if more than one slide
      loop: this.el.querySelectorAll(".swiper-slide").length > 1,

      // Keyboard navigation for accessibility
      keyboard: {
        enabled: true,
        onlyInViewport: true,
      },

      // Enable navigation if buttons exist
      navigation:
        prevButton && nextButton
          ? {
              nextEl: nextButton,
              prevEl: prevButton,
            }
          : false,

      // Enable pagination if element exists
      pagination: pagination
        ? {
            el: pagination,
            clickable: true,
            bulletElement: "button",
            bulletClass: "swiper-pagination-bullet",
            bulletActiveClass: "swiper-pagination-bullet-active",
            renderBullet: (index, className) => {
              return `<button type="button" class="${className}" aria-label="Go to slide ${index + 1}"></button>`;
            },
          }
        : false,

      // Accessibility
      a11y: {
        enabled: true,
        prevSlideMessage: "Previous slide",
        nextSlideMessage: "Next slide",
        firstSlideMessage: "This is the first slide",
        lastSlideMessage: "This is the last slide",
        paginationBulletMessage: "Go to slide {{index}}",
      },

      // Update ARIA labels on slide change
      on: {
        init: (swiper) => {
          this.updateAriaLabels(swiper);
        },
        slideChange: (swiper) => {
          this.updateAriaLabels(swiper);
        },
      },
    });
  }

  updateAriaLabels(swiper) {
    const slides = swiper.slides;
    const activeIndex = swiper.realIndex;
    const totalSlides = swiper.slides.length;

    slides.forEach((slide, index) => {
      const realIndex = swiper.slides[index].getAttribute("data-swiper-slide-index") || index;
      if (parseInt(realIndex) === activeIndex) {
        slide.setAttribute("aria-current", "true");
        slide.setAttribute("aria-label", `Slide ${activeIndex + 1} of ${totalSlides}`);
      } else {
        slide.removeAttribute("aria-current");
        slide.setAttribute("aria-label", `Slide ${parseInt(realIndex) + 1} of ${totalSlides}`);
      }
    });
  }

  remove() {
    // Clean up Swiper instance when component is removed
    if (this.swiper) {
      this.swiper.destroy(true, true);
    }
  }
}

new ComponentType(Carousel, "carousel", ".carousel");
