import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import './styles/modeler.css';
import 'reactflow/dist/style.css';
import { registerPanel, unregisterPanel, registerWidget, unregisterWidget, onReady } from './plugins/pluginRegistry';

// ── Expose the global Plugin API ──────────────────────────────────────
// The global `WorkflowModeler` object is the public entry point for
// other Drupal modules to register panels, toolbar widgets, and interact
// with the modeler.  It must be set up *before* the Drupal behavior runs
// so that modules whose scripts load before or after the modeler bundle
// can call `WorkflowModeler.registerPanel()` etc. at any time.
//
// The `api` property is populated later by the React app (Flow.tsx)
// once the modeler has mounted.  Until then it is `null`.
(function () {
  // Preserve any existing registrations from a previous mount cycle
  // (e.g. when HTMX navigates away and back).
  if (typeof window !== 'undefined' && !window.WorkflowModeler) {
    window.WorkflowModeler = {
      registerPanel,
      unregisterPanel,
      registerWidget,
      unregisterWidget,
      api: null,
      onReady,
    };
  }
})();

// Wait for Drupal to be ready
(function (Drupal, drupalSettings) {
  // Track the active React root so we can unmount on detach.
  let activeRoot = null;

  /**
   * Find the modeler container inside the given context element.
   *
   * @param {Element|Document} context
   * @returns {HTMLElement|null}
   */
  function findContainer(context) {
    return (
      context.querySelector('#workflow-modeler-react-root') ||
      context.querySelector('#workflow-modeler-wrapper')
    );
  }

  /**
   * Mount the React app into the given container element.
   *
   * @param {HTMLElement} container
   * @param {object} settings - The drupalSettings object at the time of mount.
   */
  function mountApp(container, settings) {
    // Guard: never mount twice in the same container.
    if (container.dataset.reactInitialized) {
      return;
    }
    container.dataset.reactInitialized = 'true';

    // Deep-clone settings to prevent reference issues with later merges.
    const clonedSettings = JSON.parse(JSON.stringify(settings));
    const appSettings = {
      ...clonedSettings,
      modeler: clonedSettings.modeler || {},
      modeler_api: clonedSettings.modeler_api || {},
    };

    // Clear the loading overlay.
    container.innerHTML = '';

    activeRoot = ReactDOM.createRoot(container);
    activeRoot.render(
      <React.StrictMode>
        <App settings={appSettings} drupal={Drupal} />
      </React.StrictMode>
    );
  }

  // Register the Drupal behavior.
  Drupal.behaviors.workflowModelerReact = {
    attach(context, settings) {
      const container = findContainer(context);
      if (container) {
        mountApp(container, settings);
      }
    },

    detach(context, settings, trigger) {
      // Only act on the 'unload' trigger (element removed from the DOM).
      if (trigger !== 'unload') {
        return;
      }
      const container = findContainer(context);
      if (container && container.dataset.reactInitialized && activeRoot) {
        activeRoot.unmount();
        activeRoot = null;
        delete container.dataset.reactInitialized;
      }
    },
  };

  // --- Race-condition fix for HTMX loading ---
  // When Drupal loads this script via HTMX + loadjs, the HTML container may
  // already be in the DOM *before* this script executes (because htmx:afterSettle
  // fires htmx:drupal:load and Drupal.attachBehaviors before the assets finish
  // loading). In that case, the attach pass missed both this behavior and any
  // behaviors provided by libraries that extend the modeler. Repeat the attach
  // pass after the modeler bundle and all of its dependencies have loaded.
  const existingContainer = findContainer(document);
  if (existingContainer && !existingContainer.dataset.reactInitialized) {
    Drupal.attachBehaviors(document, drupalSettings);
  }
})(Drupal, drupalSettings);
