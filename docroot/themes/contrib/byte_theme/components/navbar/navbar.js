import { ComponentType, ComponentInstance } from "../../lib/component.js";
import { measureScrollbarAndObserve } from "../../lib/measureScrollbar.js";

class Navbar extends ComponentInstance {
  #savedAsOpen = false;

  init() {
    this.closeButton = this.el.querySelector(".navbar--hide-menu");
    this.menuButton = this.el.querySelector(".navbar--hamburger");
    this.menu = this.el.querySelector(".navbar--menu");

    measureScrollbarAndObserve(this.el.querySelector(".navbar--dropdown-menu"));

    this.menuButton.addEventListener("click", () => {
      this.menu.querySelectorAll(".dropdown-menu__expand-button--has-been-opened").forEach((button) => {
        button.classList.remove("dropdown-menu__expand-button--has-been-opened");
      });
      this.isOpen = true;
    });

    this.closeButton.addEventListener("click", () => {
      this.isOpen = false;
    });

    // Keep up with scroll amount for mobile menu positioning.
    const scrollHandler = this.measureScrollTop.bind(this);
    const desktopMQ = window.matchMedia("(min-width: 48rem)");

    // Attach on page load only if less than desktop width AND navbar is visible.
    if (!desktopMQ.matches && this.el.getBoundingClientRect().bottom > 0) {
      scrollHandler();
      window.addEventListener("scroll", scrollHandler);
    }

    // Respond to window width changes, also checking scroll position.
    desktopMQ.addEventListener("change", (e) => {
      if (!e.matches && this.el.getBoundingClientRect().bottom > 0) {
        scrollHandler();
        window.addEventListener("scroll", scrollHandler);
      } else {
        window.removeEventListener("scroll", scrollHandler);
      }
    });

    // Respond to scroll position changes, also checking window width.
    const intersectionObserver = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting && !desktopMQ.matches) {
          scrollHandler();
          window.addEventListener("scroll", scrollHandler);
        } else {
          window.removeEventListener("scroll", scrollHandler);
        }
      }
    });

    intersectionObserver.observe(this.el);
  }

  set isOpen(value) {
    if (value) {
      this.menu.classList.add("navbar--menu--open");
      this.menu.querySelector("a, button").focus();
      document.documentElement.classList.add("navbar-modal-open");
    } else {
      this.menu.classList.remove("navbar--menu--open");
      document.documentElement.classList.remove("navbar-modal-open");
    }

    this.#savedAsOpen = !!value;
  }

  get isOpen() {
    return this.#savedAsOpen;
  }

  measureScrollTop() {
    document.documentElement.style.setProperty("--navbar-scroll-top", `${window.scrollY}px`);
  }
}

window.navbar = new ComponentType(Navbar, "navbar", ".navbar");
