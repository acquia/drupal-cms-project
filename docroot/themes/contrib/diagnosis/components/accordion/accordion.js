import { ComponentType, ComponentInstance } from "../../lib/component.js";
import currentlyInCanvasEditor from "../../lib/currentlyInCanvasEditor.js";

class Accordion extends ComponentInstance {
  // An internal private property to keep up with the current state of the
  // accordion. The hash makes it so you can't get or set this property outside
  // of this file.
  #savedAsOpen;

  static openClass = "accordion--open";
  static animateClass = "accordion--animate";

  // Whether ancestor accordion containers should close other accordions when
  // this one is opened.
  shouldDispatchEvents = true;

  init() {
    if (currentlyInCanvasEditor()) {
      // In Canvas editor, show content by removing collapsed state classes
      const content = this.el.querySelector(".accordion--content");
      content.classList.remove("h-0", "py-0", "overflow-hidden");
      content.classList.add("h-auto", "py-4", "overflow-visible");
      return;
    }

    // Save our togglable classes for easy reference.
    this.button = this.el.querySelector(".accordion--title");
    this.contentContainer = this.el.querySelector(".accordion--content");
    this.focusableDescendants = this.contentContainer.querySelectorAll(
      ":is(input, select, textarea, button, object):not(:disabled), a:is([href]), [tabindex]",
    );

    // Keep track of the starting tabindex for all focusable descendants, so we
    // can restore them after nuking them when the accordion is closed.
    this.focusableDescendants.forEach((el) => {
      el.tabIndex = el.tabIndex || 0;
      el.dataset.originalTabIndex = el.tabIndex;
    });

    // With the `set isOpen()` below, merely setting this property does all the
    // stuff necessary to open or close the accordion.
    this.isOpen = this.el.dataset.openByDefault === "true";

    // Figure out what height the content will be when open so we can smoothly
    // animate to it with CSS.
    this.measureNaturalHeight();

    // The previous line enables animations, but we're not ready for them yet.
    this.el.classList.remove(Accordion.animateClass);

    // Remeasure the height on every (debounced) resize event.
    let timeout = 0;

    window.addEventListener("resize", (e) => {
      this.el.classList.add("accordion--resizing");
      window.clearTimeout(timeout);
      timeout = window.setTimeout(() => {
        this.measureNaturalHeight();
        this.el.classList.remove("accordion--resizing");
      }, 350);
    });

    // Make the button work.
    this.button.addEventListener("click", () => {
      // Toggle the accordion.
      this.isOpen = !this.isOpen;
    });

    this.el.classList.add("accordion--js");
    void this.el.offsetHeight;
    this.el.classList.add(Accordion.animateClass);
  }

  // This setter makes it so the accordion can be opened and closed just by
  // doing `this.isOpen = true` or `this.isOpen = false` rather than calling a
  // method. The advantage is that (for example) if you have a boolean variable
  // `shouldOpen`, you can just do `this.isOpen = shouldOpen` rather than all
  // this:
  //
  // ```js
  // if(shouldOpen) {
  //   this.open();
  // } else {
  //   this.close();
  // }
  // ```Even
  set isOpen(val) {
    if (val) {
      // First do all the DOM manipulation needed to actually open the
      // accordion.
      this.el.classList.add(Accordion.openClass);
      this.focusableDescendants.forEach((el) => {
        el.tabIndex = el.dataset.originalTabIndex;
      });
      this.button.setAttribute("aria-expanded", "true");

      // Then stash the current state in a simple private property with no
      // getters or setters involved.
      this.#savedAsOpen = true;

      // Dispatch an event that any accordion container ancestors can use to
      // close other accordions.
      if (this.shouldDispatchEvents) {
        this.el.dispatchEvent(new Event("accordionopen", { bubbles: true }));
      }
    } else {
      // DOM manipulation.
      this.el.classList.remove(Accordion.openClass);
      this.focusableDescendants.forEach((el) => {
        el.tabIndex = -1;
      });
      this.button.setAttribute("aria-expanded", "false");
      // Stash current state.
      this.#savedAsOpen = false;
    }
  }

  // Get the simple boolean we saved in the setter.
  get isOpen() {
    return this.#savedAsOpen;
  }

  // Measure how tall the content should be when open so we can smoothly animate
  // to it using CSS.
  measureNaturalHeight() {
    // What we do here should not be seen by ancestor accordions.
    this.shouldDispatchEvents = false;
    // Remember what state the accordion started in.
    const previousState = this.isOpen;
    // Turn off animations.
    this.el.classList.remove(Accordion.animateClass);
    // Open the accordion if it's not already open.
    this.isOpen = true;
    // Measure the natural height and make it available to CSS as a custom
    // property.
    const height = this.contentContainer.getBoundingClientRect().height;
    this.el.style.setProperty("--natural-height", `${height}px`);
    // Restore the accordion to the state it started in.
    this.isOpen = previousState;
    // Re-enable animations.
    this.el.classList.add(Accordion.animateClass);
    // Become visible to ancestor accordions again.
    this.shouldDispatchEvents = true;
  }
}

new ComponentType(Accordion, "accordion", ".accordion");
