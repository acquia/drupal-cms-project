export default function currentlyInCanvasEditor() {
  try {
    // Check if we're in an iframe first (Canvas always uses iframes)
    if (window.self === window.top) {
      return false;
    }

    // Try to access parent's drupalSettings
    const parentHasCanvas = window?.parent?.drupalSettings?.canvas;
    const inPreviewIframe = window.parent.document.body.querySelector("[class^=_PagePreviewIframe]");

    return parentHasCanvas && !inPreviewIframe;
  } catch (e) {
    // If we can't access parent due to cross-origin, check for Canvas-specific classes in current window
    console.warn("Canvas detection failed, falling back to alternative detection:", e);

    // Fallback: Check if body has Canvas-specific attributes or if we're clearly in an editing context
    return (
      document.body.classList.contains("canvas-editing") ||
      document.querySelector("[data-canvas-editing]") !== null ||
      // Or check if we're in an iframe (likely Canvas)
      window.location !== window.parent.location
    );
  }
}
