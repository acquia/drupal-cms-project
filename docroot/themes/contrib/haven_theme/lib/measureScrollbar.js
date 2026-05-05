/**
 * Sets a custom property for the width of the scrollbar on an element.
 *
 * @param {HTMLElement} el The element whose scrollbar was measured.
 * @param {Number} width The width of the scrollbar in pixels.
 */

function setScrollbarCustomProperty(el, width) {
  el.style.setProperty("--element-scrollbar-width", `${width}px`);

  if (el === document.body) {
    document.documentElement.style.setProperty("--window-scrollbar-width", `${width}px`);
  }

  return width;
}

export default function measureScrollbar(el = document.body, callback = setScrollbarCustomProperty) {
  let width = el.offsetWidth - el.clientWidth;

  if (el === document.body) {
    width = window.innerWidth - el.clientWidth;
  }

  if (callback) {
    return callback(el, width);
  }

  return width;
}

export function measureScrollbarAndObserve(el = document.body, callback = setScrollbarCustomProperty) {
  if (typeof callback !== "function") {
    throw new Error("Callback must be provided for measureScrollbarAndObserve() to do anything.");
  }

  const resizeObserver = new ResizeObserver((entries) => {
    for (const entry of entries) {
      measureScrollbar(entry.target, callback);
    }
  });

  resizeObserver.observe(el);

  return measureScrollbar(el, callback);
}
