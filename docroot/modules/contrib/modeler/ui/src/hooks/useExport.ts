/**
 * useExport - Hook for exporting models in various formats
 *
 * Provides export functionality for:
 * - Recipe: leverages the Modeler API backend export_recipe_url
 * - Archive: leverages the Modeler API backend export_url
 * - JSON: client-side export with optional replay data and required modules
 * - SVG: client-side visual export of the current canvas
 */

import { useCallback, useMemo } from 'react';
import { t } from '../utils/translation';
import { exportModelData } from '../utils/modelUtils';
import { exportCanvasToSvg } from '../utils/svgExport';
import { fetchValidatedCsrfToken } from '../utils/validation';
import type { Settings } from '../types/settings';
import type { StoreNode as Node, StoreEdge as Edge, StoreComponent as Component } from '../types/settings';
import type { ReplayStep } from './useSimpleReplaySync';

/** Export format identifiers */
export type ExportFormat = 'recipe' | 'archive' | 'json' | 'svg';

interface UseExportProps {
  settings: Settings;
  nodes: Node[];
  edges: Edge[];
  components: Component[];
  modelData: { id?: string; version?: string; metadata?: Record<string, unknown> } | null;
  replayData: ReplayStep[];
  /** Screen reader announcement callback */
  announce?: (text: string) => void;
}

interface UseExportReturn {
  /** Whether the export button should be visible */
  canExport: boolean;
  /** Available export formats based on current settings */
  availableFormats: ExportFormat[];
  /** Whether replay data is available for JSON export */
  hasReplayData: boolean;
  /** Execute the actual export for a given format */
  executeExport: (format: ExportFormat, includeReplayData?: boolean) => Promise<void>;
  /** Derive required modules from the model nodes */
  getRequiredModules: () => string[];
}

/**
 * Trigger a browser download for a string payload.
 */
function downloadFile(content: string, filename: string, mimeType: string): void {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

export function useExport({
  settings,
  nodes,
  edges,
  components,
  modelData,
  replayData,
  announce,
}: UseExportProps): UseExportReturn {
  const hasExportUrl = !!settings.modeler_api?.export_url;
  const hasExportRecipeUrl = !!settings.modeler_api?.export_recipe_url;
  const hasReplay = replayData.length > 0;

  // Export is available as long as there are nodes to export
  const canExport = nodes.length > 0;

  // Determine available formats based on settings
  const availableFormats = useMemo((): ExportFormat[] => {
    const formats: ExportFormat[] = [];
    // Recipe and Archive require backend URLs (not available for new models)
    if (hasExportRecipeUrl) {
      formats.push('recipe');
    }
    if (hasExportUrl) {
      formats.push('archive');
    }
    // JSON and SVG are always available client-side
    formats.push('json');
    formats.push('svg');
    return formats;
  }, [hasExportUrl, hasExportRecipeUrl]);

  /**
   * Derive the list of required Drupal modules from node/edge providers.
   */
  const getRequiredModules = useCallback((): string[] => {
    const modules = new Set<string>();

    // Collect providers from all nodes by matching their plugin to the
    // components list
    nodes.forEach(node => {
      const plugin = node.data?.plugin;
      if (plugin) {
        const comp = components.find(c => c.plugin === plugin);
        if (comp?.provider) {
          modules.add(comp.provider);
        }
      }
    });

    // Also collect providers from edge conditions
    edges.forEach(edge => {
      const condition = edge.data?.condition;
      if (condition) {
        const comp = components.find(c => c.plugin === condition);
        if (comp?.provider) {
          modules.add(comp.provider);
        }
      }
    });

    return Array.from(modules).sort();
  }, [nodes, edges, components]);

  /**
   * Fetch configuration form schemas for all unique plugins used in the
   * model.  Returns a map from plugin ID to form field array.  Errors
   * for individual plugins are silently skipped — the resulting map will
   * simply omit their entries.
   */
  const fetchAllConfigForms = useCallback(async (): Promise<Record<string, Record<string, unknown>[]>> => {
    const configUrl = settings.modeler_api?.config_url;
    const tokenUrl = settings.modeler_api?.token_url;
    if (!configUrl || !tokenUrl) return {};

    // Collect unique plugin IDs for nodes and edge conditions
    const pluginRequests: { pluginId: string; componentType: string; componentId: string; configuration: Record<string, unknown> }[] = [];
    const seen = new Set<string>();

    nodes.forEach(node => {
      const plugin = node.data?.plugin;
      if (plugin && plugin !== 'gateway' && !seen.has(plugin)) {
        seen.add(plugin);
        pluginRequests.push({
          pluginId: plugin,
          componentType: String(node.data?.componentType || '4'),
          componentId: node.id,
          configuration: node.data?.configuration || {},
        });
      }
    });

    edges.forEach(edge => {
      const condition = edge.data?.condition;
      if (condition && !seen.has(condition)) {
        seen.add(condition);
        pluginRequests.push({
          pluginId: condition,
          componentType: '5',
          componentId: edge.id,
          configuration: edge.data?.conditionConfiguration || {},
        });
      }
    });

    if (pluginRequests.length === 0) return {};

    try {
      const token = await fetchValidatedCsrfToken(tokenUrl);
      const modelId = settings.modeler?.modelId || '';
      const isNew = settings.modeler_api?.isNew || false;

      const results = await Promise.allSettled(
        pluginRequests.map(async (req) => {
          const response = await fetch(configUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json;charset=UTF-8',
              'X-CSRF-Token': token.trim(),
            },
            body: JSON.stringify({
              component_type: req.componentType,
              component_id: req.componentId,
              model_id: modelId,
              is_new: isNew,
              plugin_id: req.pluginId,
              configuration: req.configuration,
            }),
          });
          if (!response.ok) return { pluginId: req.pluginId, form: null };
          const data = await response.json();
          const form = data?.form;
          return {
            pluginId: req.pluginId,
            form: Array.isArray(form) ? form : null,
          };
        }),
      );

      const configForms: Record<string, Record<string, unknown>[]> = {};
      results.forEach(result => {
        if (result.status === 'fulfilled' && result.value.form) {
          configForms[result.value.pluginId] = result.value.form;
        }
      });
      return configForms;
    } catch (_error) {
      // If token fetch or other global error, return empty
      return {};
    }
  }, [settings, nodes, edges]);

  /**
   * Build a minimal components list for the standalone viewer from the
   * plugins used in the model.
   */
  const getUsedComponents = useCallback((): Record<string, unknown>[] => {
    const seen = new Set<string>();
    const result: Record<string, unknown>[] = [];

    const addComponent = (pluginId: string) => {
      if (!pluginId || seen.has(pluginId)) return;
      seen.add(pluginId);
      const comp = components.find(c => c.plugin === pluginId);
      if (comp) {
        result.push({
          plugin: comp.plugin,
          label: comp.label,
          provider: comp.provider,
          componentType: comp.componentType,
          type: comp.type,
          description: comp.description || '',
          documentationUrl: comp.documentationUrl || null,
        });
      }
    };

    nodes.forEach(node => { if (node.data?.plugin) addComponent(node.data.plugin); });
    edges.forEach(edge => {
      if (edge.data?.condition) addComponent(edge.data.condition);
    });

    return result;
  }, [nodes, edges, components]);

  /**
   * Execute the export for a given format.
   */
  const executeExport = useCallback(async (format: ExportFormat, includeReplayData = false): Promise<void> => {
    try {
      switch (format) {
        case 'recipe': {
          // Open the recipe export form in a new window
          const recipeUrl = settings.modeler_api?.export_recipe_url;
          if (recipeUrl) {
            window.open(recipeUrl, '_blank', 'noopener,noreferrer');
            announce?.(t('Recipe export opened in new tab'));
          }
          break;
        }

        case 'archive': {
          // Download the archive via the backend
          const exportUrl = settings.modeler_api?.export_url;
          const tokenUrl = settings.modeler_api?.token_url;
          if (exportUrl && tokenUrl) {
            const token = await fetchValidatedCsrfToken(tokenUrl);
            // Trigger download via a hidden link with CSRF token
            const response = await fetch(exportUrl, {
              method: 'GET',
              headers: {
                'X-CSRF-Token': token,
              },
            });
            if (response.ok) {
              const blob = await response.blob();
              const contentDisposition = response.headers.get('Content-Disposition');
              let filename = 'export.tar.gz';
              if (contentDisposition) {
                const match = contentDisposition.match(/filename="?([^";\s]+)"?/);
                if (match) {
                  filename = match[1];
                }
              }
              const url = URL.createObjectURL(blob);
              const link = document.createElement('a');
              link.href = url;
              link.download = filename;
              document.body.appendChild(link);
              link.click();
              document.body.removeChild(link);
              URL.revokeObjectURL(url);
              announce?.(t('Archive exported successfully'));
            } else {
              throw new Error(t('Failed to download archive: @status', { '@status': response.statusText }));
            }
          }
          break;
        }

        case 'json': {
          const data = exportModelData(
            nodes,
            edges,
            { id: modelData?.id, version: modelData?.version, ...modelData?.metadata },
          );

          // Add required modules
          const requiredModules = getRequiredModules();
          const exportData: Record<string, unknown> = {
            ...data,
            requiredModules,
          };

          // Optionally include replay data
          if (includeReplayData && replayData.length > 0) {
            exportData.replayData = replayData;
          }

          // Fetch all configuration form schemas for the standalone viewer.
          // This requires backend access; skip silently if unavailable.
          announce?.(t('Fetching configuration forms...'));
          const configForms = await fetchAllConfigForms();
          if (Object.keys(configForms).length > 0) {
            exportData.configForms = configForms;
          }

          // Include a minimal component list so the standalone viewer can
          // derive component type, category, and documentation links.
          const usedComponents = getUsedComponents();
          if (usedComponents.length > 0) {
            exportData.components = usedComponents;
          }

          // The metadata form travels with the export so the standalone
          // viewer can render the model information dialog without a backend.
          const metadataForm = settings.modeler?.metadataForm;
          if (metadataForm) {
            exportData.metadataForm = metadataForm;
          }

          // Export compact JSON. The exported model — including any replay
          // data — only has technical consumers, so indentation is pure
          // overhead that can roughly double the file size. Humans who want
          // to inspect an export can prettify it with jq or any JSON tool.
          const json = JSON.stringify(exportData);
          const filename = `${data.id || 'model'}.json`;
          downloadFile(json, filename, 'application/json');
          announce?.(t('JSON exported successfully'));
          break;
        }

        case 'svg': {
          const svgContent = exportCanvasToSvg();
          const modelId = modelData?.id || modelData?.metadata?.label || 'model';
          const filename = `${modelId}.svg`;
          downloadFile(svgContent, filename, 'image/svg+xml');
          announce?.(t('SVG exported successfully'));
          break;
        }
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : t('Export failed');
      announce?.(message);
      throw error;
    }
  }, [settings, nodes, edges, modelData, replayData, getRequiredModules, fetchAllConfigForms, getUsedComponents, announce]);

  return {
    canExport,
    availableFormats,
    hasReplayData: hasReplay,
    executeExport,
    getRequiredModules,
  };
}
