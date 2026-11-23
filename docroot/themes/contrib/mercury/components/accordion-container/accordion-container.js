import { ComponentType, ComponentInstance } from "../../lib/component.js";
import currentlyInCanvasEditor from "../../lib/currentlyInCanvasEditor.js";

class AccordionContainer extends ComponentInstance {
  init() {
    if (currentlyInCanvasEditor()) {
      return;
    }

    // Listen for `accordionopen` events bubbling up from descendant
    // accordions.
    this.el.addEventListener("accordionopen", (e) => {
      // Close all descendant accordions except the one that just opened.
      const otherAccordionInstances = window.mercuryComponents.accordion.instances.filter(
        (accordion) => this.el.contains(accordion.el) && e.target !== accordion.el,
      );
      otherAccordionInstances.forEach((instance) => {
        instance.isOpen = false;
      });
    });
  }
}

new ComponentType(AccordionContainer, "accordionContainer", ".accordion-container");
