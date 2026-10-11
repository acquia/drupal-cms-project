/**
 * useContextFilter - Filters components based on the currently selected context
 * and global dependency definitions.
 *
 * When a context is selected, only plugins listed in that context's component
 * entries are shown. When no context is selected, all components pass through
 * the context check.
 *
 * Dependency definitions (from drupalSettings.modeler_api.dependencies) are
 * applied independently: if a plugin has dependency constraints, at least one
 * must be satisfied by the current workflow (checked against nodes and edges
 * in the store). Both the context filter and the dependency filter must pass
 * for a component to be included.
 */

import { useMemo } from 'react';
import { useContextStore } from '../store/useContextStore';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreComponent as Component } from '../types/settings';
import type { ContextDependency, ModelerDependencies } from '../types/settings';

/**
 * Build a flat lookup of all dependency arrays keyed by plugin ID, collected
 * from every component-type entry in the global dependency definitions.
 */
function buildDependencyMap(
  dependencies: ModelerDependencies
): Map<string, ContextDependency[]> {
  const map = new Map<string, ContextDependency[]>();
  for (const entry of Object.values(dependencies)) {
    if (entry && typeof entry === 'object') {
      for (const [pluginId, deps] of Object.entries(entry)) {
        if (Array.isArray(deps) && deps.length > 0) {
          map.set(pluginId, deps);
        }
      }
    }
  }
  return map;
}

/**
 * Returns the input components filtered by the active context and global
 * dependency definitions.
 *
 * - Context filtering: if a context is selected, only plugins listed in
 *   that context pass. If no context is selected, all components pass.
 * - Dependency filtering: if a plugin has entries in the global dependency
 *   map, at least one dependency must be satisfied by the current workflow.
 *   Plugins without dependencies always pass this check.
 */
export function useContextFilter(components: Component[]): Component[] {
  const selectedContextId = useContextStore(state => state.selectedContextId);
  const contexts = useContextStore(state => state.contexts);
  const dependencies = useContextStore(state => state.dependencies);
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);

  return useMemo(() => {
    // --- Context filtering setup ---
    let allowedPlugins: Set<string> | null = null;

    if (selectedContextId && contexts && contexts.length > 0) {
      const activeContext = contexts.find(ctx => ctx.id === selectedContextId);
      if (activeContext) {
        allowedPlugins = new Set<string>();
        const contextComponents = activeContext.components;
        if (contextComponents) {
          for (const entry of Object.values(contextComponents)) {
            if (entry?.plugins && Array.isArray(entry.plugins)) {
              for (const pluginId of entry.plugins) {
                allowedPlugins.add(pluginId);
              }
            }
          }
        }
      }
    }

    // --- Dependency filtering setup ---
    const dependencyMap: Map<string, ContextDependency[]> = dependencies
      ? buildDependencyMap(dependencies)
      : new Map();

    // If there's no context filter and no dependencies, return everything.
    if (allowedPlugins === null && dependencyMap.size === 0) {
      return components;
    }

    // Build sets of plugin IDs currently present in the workflow (only when
    // there are dependencies to check).
    let workflowNodePlugins: Set<string> | null = null;
    let workflowEdgePlugins: Set<string> | null = null;

    if (dependencyMap.size > 0) {
      workflowNodePlugins = new Set<string>();
      workflowEdgePlugins = new Set<string>();

      for (const node of nodes) {
        const plugin = node.data?.plugin;
        if (plugin) {
          workflowNodePlugins.add(plugin);
        }
      }
      for (const edge of edges) {
        const condition = edge.data?.condition;
        if (condition) {
          workflowEdgePlugins.add(condition);
        }
      }
    }

    return components.filter(comp => {
      // Context check: if a context is active, the plugin must be allowed.
      if (allowedPlugins !== null && !allowedPlugins.has(comp.plugin)) {
        return false;
      }

      // Dependency check: if the plugin has dependencies, at least one must
      // be satisfied.
      const deps = dependencyMap.get(comp.plugin);
      if (deps && deps.length > 0) {
        return deps.some(dep => {
          if (dep.type === 'link') {
            return workflowEdgePlugins!.has(dep.id);
          }
          return workflowNodePlugins!.has(dep.id);
        });
      }

      return true;
    });
  }, [components, selectedContextId, contexts, dependencies, nodes, edges]);
}
