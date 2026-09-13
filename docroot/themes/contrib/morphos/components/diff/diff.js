/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/diff/diff.js
 * Copyright (c) 2026 Morpht Pty Ltd
 */

((Drupal, once) => {
  const isCanvasEditor = () => {
    try {
      return (
        !!window?.parent?.drupalSettings?.canvas &&
        !window.parent.document.body.querySelector(
          '[class^=_PagePreviewIframe]',
        )
      );
    } catch (e) {
      return false;
    }
  };

  Drupal.behaviors.diff_editor = {
    attach: (context) => {
      if (!isCanvasEditor()) return;

      once('diff-editor', '.js-diff', context).forEach((el) => {
        // Strip all DaisyUI diff classes so the component renders
        // as side-by-side slots that editors can easily work with.
        el.classList.remove('diff', 'aspect-16/9');
        el.style.display = 'flex';
        el.style.gap = '1rem';

        const item1 = el.querySelector('.diff-item-1');
        const item2 = el.querySelector('.diff-item-2');
        if (item1) {
          item1.classList.remove('diff-item-1');
          item1.style.flex = '1 1 0%';
        }
        if (item2) {
          item2.classList.remove('diff-item-2');
          item2.style.flex = '1 1 0%';
        }

        // Hide the resizer — not needed in editor.
        const resizer = el.querySelector('.diff-resizer');
        if (resizer) resizer.style.display = 'none';
      });
    },
  };
})(Drupal, once);
