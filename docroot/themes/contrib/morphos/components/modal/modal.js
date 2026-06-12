/*!
 * Project Name: Morphos (morpht/morphos)
 * File: components/modal/modal.js
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

  Drupal.behaviors.modal = {
    attach: (context) => {
      if (isCanvasEditor()) return;

      once('modal', '.modal-component', context).forEach((wrapper) => {
        const placeholder = wrapper.querySelector('.modal-component__dialog');
        const action = wrapper.querySelector('.modal-component__action');
        if (!placeholder || !action) return;

        const placement = wrapper.dataset.modalPlacement;

        // Create a native <dialog> and move content into it.
        const dialog = document.createElement('dialog');
        dialog.classList.add('modal', placement);
        while (placeholder.firstChild) {
          dialog.appendChild(placeholder.firstChild);
        }

        // Add backdrop for click-outside close.
        const backdrop = document.createElement('form');
        backdrop.method = 'dialog';
        backdrop.className = 'modal-backdrop';
        backdrop.innerHTML = '<button>Close</button>';
        dialog.appendChild(backdrop);

        // Replace the placeholder div with the real dialog.
        placeholder.replaceWith(dialog);
        wrapper._modalDialog = dialog;

        // Wire the action trigger.
        const trigger =
          action.querySelector('button') ||
          action.querySelector('a') ||
          action.querySelector('[role="button"]');
        if (!trigger) return;

        trigger.addEventListener('click', (e) => {
          e.preventDefault();
          dialog.showModal();
        });

        // Close button handler.
        const closeBtn = dialog.querySelector('.modal-component__close');
        if (closeBtn) {
          closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            dialog.close();
          });
        }

        // Close on backdrop click.
        dialog.addEventListener('click', (e) => {
          if (e.target === dialog) {
            dialog.close();
          }
        });
      });
    },

    detach: (context) => {
      once.remove('modal', '.modal-component', context).forEach((wrapper) => {
        if (wrapper._modalDialog) {
          wrapper._modalDialog.close();
          delete wrapper._modalDialog;
        }
      });
    },
  };
})(Drupal, once);
