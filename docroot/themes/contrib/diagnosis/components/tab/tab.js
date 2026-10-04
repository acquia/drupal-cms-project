import { ComponentType, ComponentInstance } from "../../lib/component.js";
import currentlyInCanvasEditor from "../../lib/currentlyInCanvasEditor.js";

class Tab extends ComponentInstance {
  #savedAsActive;

  static activeClass = "tab--active";
  static animateClass = "tab--animate";

  // Whether to dispatch events to parent container
  shouldDispatchEvents = true;

  init() {
    const inCanvas = currentlyInCanvasEditor();

    if (inCanvas) {
      // In Canvas editor, add canvas class to show all content
      this.el.classList.add("tab--canvas");
      // Show content without JavaScript interaction
      const panel = this.el.querySelector(".tab--panel");
      if (panel) {
        panel.classList.remove("h-0", "py-0", "overflow-hidden");
        panel.classList.add("h-auto", "py-4", "overflow-visible");
      }
      // Hide mobile button icon in Canvas
      const icon = this.el.querySelector(".tab--icon");
      if (icon) {
        icon.style.display = "none";
      }
      return;
    }

    // Fallback: If we're in any iframe, assume Canvas editing mode
    // This catches cases where currentlyInCanvasEditor() fails
    if (window.self !== window.top) {
      console.log("Tab: Detected iframe context, enabling Canvas mode as fallback");
      this.el.classList.add("tab--canvas");
      const panel = this.el.querySelector(".tab--panel");
      if (panel) {
        panel.classList.remove("h-0", "py-0", "overflow-hidden");
        panel.classList.add("h-auto", "py-4", "overflow-visible");
      }
      return;
    }

    this.mobileButton = this.el.querySelector(".tab--mobile-button");
    this.panel = this.el.querySelector(".tab--panel");
    this.tabId = this.el.dataset.tabId;
    this.panelId = this.el.dataset.panelId;

    // Handle focusable descendants
    this.focusableDescendants = this.panel.querySelectorAll(":is(input, select, textarea, button, object):not(:disabled), a:is([href]), [tabindex]");

    this.focusableDescendants.forEach((el) => {
      el.tabIndex = el.tabIndex || 0;
      el.dataset.originalTabIndex = el.tabIndex;
    });

    // Set initial active state
    this.isActive = this.el.dataset.activeByDefault === "true";

    // Measure natural height for mobile accordion
    this.measureNaturalHeight();
    this.el.classList.remove(Tab.animateClass);

    // Handle window resize
    let timeout = 0;
    window.addEventListener("resize", () => {
      this.el.classList.add("tab--resizing");
      window.clearTimeout(timeout);
      timeout = window.setTimeout(() => {
        this.measureNaturalHeight();
        this.el.classList.remove("tab--resizing");
      }, 350);
    });

    // Mobile accordion button click
    this.mobileButton.addEventListener("click", () => {
      this.isActive = !this.isActive;
      if (this.isActive && this.shouldDispatchEvents) {
        this.el.dispatchEvent(new Event("tabactivate", { bubbles: true }));
      }
    });

    this.el.classList.add("tab--js");
    void this.el.offsetHeight;
    this.el.classList.add(Tab.animateClass);
  }

  set isActive(val) {
    if (val) {
      this.el.classList.add(Tab.activeClass);
      this.focusableDescendants.forEach((el) => {
        el.tabIndex = el.dataset.originalTabIndex;
      });
      this.mobileButton.setAttribute("aria-expanded", "true");
      this.panel.setAttribute("aria-hidden", "false");
      this.#savedAsActive = true;

      if (this.shouldDispatchEvents) {
        this.el.dispatchEvent(new Event("tabactivate", { bubbles: true }));
      }
    } else {
      this.el.classList.remove(Tab.activeClass);
      this.focusableDescendants.forEach((el) => {
        el.tabIndex = -1;
      });
      this.mobileButton.setAttribute("aria-expanded", "false");
      this.panel.setAttribute("aria-hidden", "true");
      this.#savedAsActive = false;
    }
  }

  get isActive() {
    return this.#savedAsActive;
  }

  measureNaturalHeight() {
    this.shouldDispatchEvents = false;
    const previousState = this.isActive;
    this.el.classList.remove(Tab.animateClass);
    this.isActive = true;
    const height = this.panel.getBoundingClientRect().height;
    this.el.style.setProperty("--natural-height", `${height}px`);
    this.isActive = previousState;
    this.el.classList.add(Tab.animateClass);
    this.shouldDispatchEvents = true;
  }
}

new ComponentType(Tab, "tab", ".tab");
