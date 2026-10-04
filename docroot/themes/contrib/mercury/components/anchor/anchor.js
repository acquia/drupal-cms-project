import { ComponentType, ComponentInstance } from "../../lib/component.js";
import currentlyInCanvasEditor from "../../lib/currentlyInCanvasEditor.js";

class Anchor extends ComponentInstance {
  init() {
    if (currentlyInCanvasEditor()) {
      this.el.classList.add("anchor--visible");
    }
  }
}

new ComponentType(Anchor, "anchor", ".anchor");
