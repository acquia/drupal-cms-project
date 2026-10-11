/**
 * Standalone entry point for the Workflow Modeler Viewer.
 *
 * This module exports an `init()` function that can be used to embed
 * a read-only workflow viewer (with optional replay) in any web page
 * without a Drupal backend.
 *
 * Usage:
 *   <link rel="stylesheet" href="modeler-viewer.bundle.css">
 *   <div id="workflow-viewer"></div>
 *   <script src="modeler-viewer.bundle.js"></script>
 *   <script>
 *     // From a URL:
 *     WorkflowModelerViewer.init('#workflow-viewer', { modelUrl: 'model.json' });
 *     // Or inline:
 *     WorkflowModelerViewer.init('#workflow-viewer', { model: { id: '...', ... } });
 *     // With panels collapsed initially (auto-expand on node selection):
 *     WorkflowModelerViewer.init('#workflow-viewer', {
 *       modelUrl: 'model.json',
 *       collapsePanels: true,
 *     });
 *   </script>
 */

import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import './styles/modeler.css';
import 'reactflow/dist/style.css';
import type { Settings, DrupalAjax, ModelData, ReplayDataEntry, StoreComponent } from './types/settings';
import type { FormField } from './types/forms';

/** Shape of the exported JSON model (matches useExport JSON output). */
interface ExportedModel {
  id?: string;
  version?: string;
  metadata?: ModelData['metadata'];
  nodes?: Record<string, unknown>[];
  edges?: Record<string, unknown>[];
  requiredModules?: string[];
  replayData?: ReplayDataEntry[];
  configForms?: Record<string, Record<string, unknown>[]>;
  metadataForm?: FormField[];
  components?: StoreComponent[];
}

/** Options accepted by `init()`. */
interface ViewerOptions {
  /** URL to fetch the model JSON from. */
  modelUrl?: string;
  /** Inline model data (takes precedence over modelUrl). */
  model?: ExportedModel;
  /**
   * When true, panels start collapsed and auto-expand/collapse based on
   * user interaction (e.g. selecting a node expands the property panel).
   * Default: false.
   */
  collapsePanels?: boolean;
}

/**
 * Build the `Settings` object that the modeler `<App>` expects from
 * the standalone-exported JSON.
 */
function buildSettings(data: ExportedModel, options: ViewerOptions): Settings {
  // Serialize the model data to a JSON string so useModelDataLoader parses
  // it through parseModelData(), which normalizes raw nodes/edges into
  // proper StoreNode/StoreEdge objects.
  const modelDataJson = JSON.stringify({
    id: data.id,
    version: data.version,
    metadata: data.metadata,
    nodes: data.nodes || [],
    edges: data.edges || [],
  });

  return {
    modeler: {
      standalone: true,
      collapsePanels: !!options.collapsePanels,
      modelId: data.id,
      modelData: modelDataJson,
      replayData: data.replayData || [],
      components: data.components || [],
      configForms: data.configForms || {},
      metadataForm: data.metadataForm,
    },
    modeler_api: {
      readOnly: true,
      isNew: false,
      metadata: data.metadata as Settings['modeler_api'] extends { metadata?: infer M } ? M : never,
      permissions: {
        'replay': true,
      },
    },
  };
}

/**
 * Minimal Drupal-compatible shim — the save function is a no-op in
 * standalone mode and `t()` returns the string as-is.
 */
const drupalShim: DrupalAjax = {
  ajax: () => ({ execute: () => {} }),
  t: (text: string) => text,
};

/**
 * Mount the standalone viewer into the given container.
 *
 * @param selector  CSS selector or DOM element for the container.
 * @param options   Must provide either `model` (inline) or `modelUrl`.
 * @returns A `destroy()` function to unmount the viewer.
 */
async function init(
  selector: string | HTMLElement,
  options: ViewerOptions,
): Promise<{ destroy: () => void }> {
  const container =
    typeof selector === 'string'
      ? document.querySelector<HTMLElement>(selector)
      : selector;

  if (!container) {
    throw new Error(`WorkflowModelerViewer: container not found: ${selector}`);
  }

  let data: ExportedModel;

  if (options.model) {
    data = options.model;
  } else if (options.modelUrl) {
    const response = await fetch(options.modelUrl);
    if (!response.ok) {
      throw new Error(
        `WorkflowModelerViewer: failed to fetch model from ${options.modelUrl}: ${response.statusText}`,
      );
    }
    data = await response.json();
  } else {
    throw new Error(
      'WorkflowModelerViewer: either `model` or `modelUrl` must be provided',
    );
  }

  const settings = buildSettings(data, options);

  container.innerHTML = '';
  const root = ReactDOM.createRoot(container);
  root.render(
    <React.StrictMode>
      <App settings={settings} drupal={drupalShim} />
    </React.StrictMode>,
  );

  return {
    destroy: () => {
      root.unmount();
    },
  };
}

// Expose the public API on the global namespace for IIFE builds.
(window as unknown as Record<string, unknown>).WorkflowModelerViewer = { init };

export { init };
export type { ViewerOptions, ExportedModel };
