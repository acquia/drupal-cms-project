/**
 * @file
 * Agent form behaviors for tool configuration modals.
 */

/* global MicroModal */

(function (Drupal, once) {
  Drupal.behaviors.openToolsModal = {
    attach(context) {
      MicroModal.init();
      once("open-tools-modal", ".dynamic-tool-modal", context).forEach((el) => {
        el.addEventListener("click", (e) => {
          e.preventDefault();
          const { tool } = e.currentTarget.dataset;
          const id = `tool-${tool}`;
          MicroModal.show(id);
        });
      });
    },
  };

  // Trigger the tools library update_widget after the selection modal closes.
  // The ai module's tool library uses a jQuery UI dialog whose close event is
  // jQuery-only, so we watch the DOM for dialog removal instead.
  Drupal.behaviors.aiAgentsToolUsageRefresh = {
    attach(context) {
      if (context !== document) {
        return;
      }
      once("ai-agents-tool-refresh", "body", context).forEach(() => {
        let dialogWasOpen = false;
        const observer = new MutationObserver(() => {
          const dialogExists = !!document.querySelector(".ui-dialog");
          if (dialogWasOpen && !dialogExists) {
            const updateWidget = document.querySelector(
              "[data-ai-tools-library-form-element-update]",
            );
            if (updateWidget) {
              updateWidget.dispatchEvent(new Event("mousedown"));
            }
          }
          dialogWasOpen = dialogExists;
        });
        observer.observe(document.body, { childList: true, subtree: true });
      });
    },
  };
})(Drupal, once);
