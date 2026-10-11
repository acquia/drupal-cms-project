/**
 * Custom hook for model data loading and management
 * Handles parsing, loading, and initial setup of model data from Drupal
 */

import { useEffect, useMemo, useRef } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import { useComponentStore } from '../store/useComponentStore';
import { useContextStore } from '../store/useContextStore';
import { useModelStore } from '../store/useModelStore';
import { useFilterStore } from '../store/useFilterStore';
import { useLabelStore } from '../store/useLabelStore';
import { parseModelData, isConditionReuseEnabled } from '../utils/modelUtils';
import { validateModelDataShape } from '../utils/validation';
import { t } from '../utils/translation';
import { getComponentLabel, setActiveComponentLabels, setActiveComponentLabelsPlural, DEFAULT_TYPE_MAP } from '../utils/componentUtils';
import type { Settings, StoreComponent, StoreNode, StoreEdge, ModelData } from '../types/settings';
import type { ViewportActions } from './useViewportActions';

interface UseModelDataLoaderProps {
  settings: Settings;
  viewportActions: ViewportActions;
}

// ============ Extracted Helper Functions ============

function createGatewayComponent() {
  return {
    type: 'gateway',
    componentType: 6,
    provider: 'modeler',
    label: getComponentLabel('gateway'),
    plugin: 'gateway',
    description: t('Gateway for conditional branching and decision making'),
  };
}

/**
 * Resolve the string `type` for each component from its integer
 * `componentType` using the provided typeMap.  Components that already
 * carry a `type` (e.g. the synthetic gateway) are left untouched.
 */
function resolveComponentTypes(
  components: StoreComponent[],
  typeMap: Record<number, string>,
): StoreComponent[] {
  return components.map(comp => {
    if (comp.type) return comp;
    const resolved = typeMap[comp.componentType ?? 4] ?? 'element';
    return { ...comp, type: resolved };
  });
}

/** Ensure the gateway component exists in the components array.
 *  If the backend already provides gateway components (componentType 6 or
 *  type 'gateway'), the synthetic fallback is not injected.  This prevents
 *  model owners like MigratePlus — which supply their own gateway plugin —
 *  from ending up with a duplicate "gateway" entry. */
function ensureGatewayComponent(components: StoreComponent[]): StoreComponent[] {
  const hasGateway = components.some(
    comp => comp.type === 'gateway' || comp.componentType === 6,
  );
  if (hasGateway) return components;
  return [...components, createGatewayComponent()];
}

/** Build a set of all plugin IDs from the resolved components array */
function buildAvailablePluginIds(components: StoreComponent[]): Set<string> {
  const ids = new Set<string>();
  for (const comp of components) {
    if (comp.plugin) {
      ids.add(comp.plugin);
    }
  }
  return ids;
}

/**
 * Filter contexts to only include those that reference at least one plugin
 * that actually exists in the available components.  Contexts whose plugin
 * lists contain no existing plugin are omitted so they don't appear in the
 * dropdown.
 */
function filterContextsByAvailablePlugins(
  contexts: import('../types/settings').ModelerContext[],
  availablePluginIds: Set<string>,
): import('../types/settings').ModelerContext[] {
  return contexts.filter(ctx => {
    const components = ctx.components;
    if (!components) return false;
    for (const entry of Object.values(components)) {
      if (entry?.plugins && Array.isArray(entry.plugins)) {
        for (const pluginId of entry.plugins) {
          if (availablePluginIds.has(pluginId)) {
            return true;
          }
        }
      }
    }
    return false;
  });
}

/** Convert ownerComponents (keyed by type) to a flat component array */
function flattenOwnerComponents(ownerComponents: Record<string, StoreComponent[]>): StoreComponent[] {
  const allComponents: StoreComponent[] = [];
  Object.keys(ownerComponents).forEach(type => {
    const typeComponents = ownerComponents[type];
    if (Array.isArray(typeComponents)) {
      typeComponents.forEach(comp => {
        allComponents.push({ ...comp, type: comp.type || type });
      });
    }
  });
  return allComponents;
}

/** Parse raw model JSON, validate, and merge with settings metadata */
function parseAndMergeModelData(
  rawJson: ModelData | string,
  settings: Settings,
  source: string
): { modelData: ModelData; nodes: StoreNode[]; edges: StoreEdge[] } | null {
  try {
    const data = typeof rawJson === 'string' ? JSON.parse(rawJson) : rawJson;

    // Validate structural shape before parsing
    const warnings = validateModelDataShape(data);
    if (warnings.length > 0) {
      console.warn(`Model data validation warnings (${source}):`, warnings);
    }

    // Condition reuse (grouping on load) is owner-opt-in via model_constraints
    // (issue #3589093).  Read it DEFENSIVELY: absent/false ⇒ no grouping.
    const allowConditionReuse = isConditionReuseEnabled(
      settings?.modeler_api?.model_constraints,
    );
    const parsedData = parseModelData(data, { allowConditionReuse });

    // Merge model data with modelId from settings and metadata from API
    const mergedModelData = {
      ...parsedData.modelData,
      id: settings.modeler?.modelId || parsedData.modelData?.id || '',
      metadata: {
        ...parsedData.modelData?.metadata,
        ...settings.modeler_api?.metadata,
      },
    };

    // Ensure all nodes have proper data structure
    const nodesWithData = (parsedData.nodes || []).map(node => ({
      ...node,
      data: node.data || {}
    }));

    return {
      modelData: mergedModelData,
      nodes: nodesWithData,
      edges: parsedData.edges || [],
    };
  } catch (error) {
    console.error(`Failed to parse model data from ${source}:`, error);
    return null;
  }
}

/** Build a default empty model from settings metadata */
function buildDefaultModel(settings: Settings): ModelData {
  const apiMetadata = settings?.modeler_api?.metadata;
  return {
    id: settings?.modeler?.modelId || '',
    version: apiMetadata?.version || '1.0.0',
    metadata: {
      label: apiMetadata?.label || t('New Workflow'),
      documentation: apiMetadata?.documentation || '',
      storage: apiMetadata?.storage || '',
      executable: apiMetadata?.executable !== false,
      template: apiMetadata?.template || false,
      tags: apiMetadata?.tags || [],
      changelog: apiMetadata?.changelog || '',
    },
    nodes: [],
    edges: [],
  };
}

// ============ Hook ============

/** Viewport operation queued during model loading, applied once ReactFlow is ready. */
type PendingViewportOp =
  | { kind: 'fitToNodes' }
  | { kind: 'selectAndFocus'; node: StoreNode; targetKind: 'topAlignNode' | 'focusNode' };

export function useModelDataLoader({ settings, viewportActions }: UseModelDataLoaderProps) {
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);
  const setComponents = useComponentStore(state => state.setComponents);
  const setFavoriteComponents = useComponentStore(state => state.setFavoriteComponents);
  const setContexts = useContextStore(state => state.setContexts);
  const setDependencies = useContextStore(state => state.setDependencies);
  const setSelectedContextId = useContextStore(state => state.setSelectedContextId);
  const setContextConfig = useContextStore(state => state.setContextConfig);
  const setComponentLabels = useLabelStore(state => state.setComponentLabels);
  const setModelData = useModelStore(state => state.setModelData);
  const setVisibleStartNodeIds = useFilterStore(state => state.setVisibleStartNodeIds);

  // Queued viewport operation — set during model loading, applied once
  // useViewportActions signals readiness (deferred execution is handled
  // internally by the viewport actions hook).
  const pendingViewportRef = useRef<PendingViewportOp | null>(null);

  // Process replay data
  const replayData = useMemo(() => settings.modeler?.replayData || [], [settings.modeler?.replayData]);

  // Initialize data from Drupal on mount
  useEffect(() => {
    // Load favorite components from settings
    if (settings?.modeler_api?.favorite_components) {
      setFavoriteComponents(settings.modeler_api.favorite_components);
    }

    // Load context config (default key/value pairs for new component configurations)
    if (settings?.modeler?.setContextConfig && typeof settings.modeler.setContextConfig === 'object') {
      setContextConfig(settings.modeler.setContextConfig);
    }

    // Load dependency definitions from settings
    if (settings?.modeler_api?.dependencies && typeof settings.modeler_api.dependencies === 'object') {
      setDependencies(settings.modeler_api.dependencies);
    }

    // Load component labels from settings (model-owner-provided terminology)
    if (settings?.modeler_api?.component_labels && typeof settings.modeler_api.component_labels === 'object') {
      setComponentLabels(settings.modeler_api.component_labels);
      setActiveComponentLabels(settings.modeler_api.component_labels);
    }

    // Load plural component labels from settings
    if (settings?.modeler_api?.component_labels_plural && typeof settings.modeler_api.component_labels_plural === 'object') {
      setActiveComponentLabelsPlural(settings.modeler_api.component_labels_plural);
    }

    // Load components from settings, resolving `type` from `componentType`
    // using the backend-provided typeMap (falls back to a built-in default).
    let resolvedComponents: StoreComponent[];
    const typeMap = settings?.modeler?.typeMap ?? DEFAULT_TYPE_MAP;
    if (settings?.modeler?.components) {
      const resolved = resolveComponentTypes(settings.modeler.components, typeMap);
      resolvedComponents = ensureGatewayComponent(resolved);
    } else if (settings?.ownerComponents) {
      const flatComponents = flattenOwnerComponents(settings.ownerComponents);
      const resolved = resolveComponentTypes(flatComponents, typeMap);
      resolvedComponents = ensureGatewayComponent(resolved);
    } else {
      // Fallback: if no components from settings, at least add the gateway
      resolvedComponents = [createGatewayComponent()];
    }
    setComponents(resolvedComponents);

    // Now that components are resolved, filter contexts to only include those
    // that reference at least one existing plugin.  Contexts without any
    // matching plugin are hidden from the dropdown.
    const availablePluginIds = buildAvailablePluginIds(resolvedComponents);

    if (settings?.modeler_api?.contexts && Array.isArray(settings.modeler_api.contexts)) {
      const validContexts = filterContextsByAvailablePlugins(
        settings.modeler_api.contexts,
        availablePluginIds,
      );
      setContexts(validContexts);

      // Auto-select context if specified and available (in the filtered list)
      if (settings.modeler?.selectContextId) {
        const contextExists = validContexts.some(
          ctx => ctx.id === settings.modeler!.selectContextId
        );
        if (contextExists) {
          setSelectedContextId(settings.modeler.selectContextId);
        }
      }
    }

    // Load model data from settings.modeler.modelData
    if (settings?.modeler?.modelData) {
      const parsed = parseAndMergeModelData(settings.modeler.modelData, settings, 'settings');
      if (parsed) {
        setModelData(parsed.modelData);
        setNodes(parsed.nodes);
        setEdges(parsed.edges);

        // Auto-select element if specified
        if (settings.modeler?.selectComponentId && parsed.nodes.length > 0) {
          const selectComponentId = settings.modeler.selectComponentId;
          const nodeToSelect = parsed.nodes.find(node => node.id === selectComponentId);
          if (nodeToSelect) {
            // Mark the node as selected in the nodes array
            const updatedNodes = parsed.nodes.map(node => ({
              ...node,
              selected: node.id === selectComponentId
            }));
            setNodes(updatedNodes);

            // If the selected component is a start node and there are
            // multiple start nodes, filter the canvas to show only its flow.
            const isStartNode = nodeToSelect.type === 'start';
            const startNodeCount = parsed.nodes.filter(n => n.type === 'start').length;
            if (isStartNode && startNodeCount > 1) {
              setVisibleStartNodeIds([nodeToSelect.id]);
            }

            // Queue the viewport operation — useViewportActions handles
            // deferred execution internally until ReactFlow is ready.
            const viewportKind = isStartNode ? 'topAlignNode' : 'focusNode';
            pendingViewportRef.current = {
              kind: 'selectAndFocus',
              node: nodeToSelect,
              targetKind: viewportKind,
            };
          }
        } else if (parsed.nodes.length > 0) {
          // No specific selection requested - just fit the view
          pendingViewportRef.current = { kind: 'fitToNodes' };
        }
      }
    } else {
      // Fallback: try loading from hidden field
      const hiddenField = document.querySelector('[name="modeler_api_data"]') as HTMLInputElement;

      if (hiddenField && hiddenField.value) {
        const parsed = parseAndMergeModelData(hiddenField.value, settings, 'hidden field');
        if (parsed) {
          setModelData(parsed.modelData);
          setNodes(parsed.nodes);
          setEdges(parsed.edges);

          // Fit the workflow in view without auto-selecting any node
          if (parsed.nodes.length > 0) {
            pendingViewportRef.current = { kind: 'fitToNodes' };
          }
        }
      }

      // Initialize with default model if no data found anywhere
      if (!settings?.modeler?.modelData && (!hiddenField || !hiddenField.value)) {
        setModelData(buildDefaultModel(settings));
      }
    }
  }, [settings, setComponents, setFavoriteComponents, setContexts, setDependencies, setSelectedContextId, setContextConfig, setComponentLabels, setModelData, setNodes, setEdges, setVisibleStartNodeIds]);

  // Apply the pending viewport operation.  useViewportActions handles
  // deferred execution internally — if ReactFlow is not ready yet, the
  // operation is queued and applied once setReady() is called.
  // This effect runs once after the initialization effect above has set
  // pendingViewportRef.current.
  useEffect(() => {
    const pending = pendingViewportRef.current;
    if (!pending) return;

    // Consume the pending operation so it fires only once.
    pendingViewportRef.current = null;

    switch (pending.kind) {
      case 'fitToNodes':
        viewportActions.fitToNodes();
        break;
      case 'selectAndFocus':
        viewportActions.selectAndFocus(pending.node, pending.targetKind);
        break;
    }
  }, [viewportActions]);

  return {
    replayData
  };
}
