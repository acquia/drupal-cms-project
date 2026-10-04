/**
 * usePluginPanels / usePluginWidgets - React hooks for consuming
 * plugin panel and toolbar widget registrations.
 *
 * Bridges the framework-agnostic plugin registry with React by
 * subscribing to registry changes and returning the current set of
 * registered items, optionally filtered by position.
 */

import { useState, useEffect, useMemo } from 'react';
import {
  getRegisteredPanels,
  getPanelsByPosition,
  onRegistryChange,
  getRegisteredWidgets,
  getWidgetsByPosition,
  onWidgetRegistryChange,
} from '../plugins/pluginRegistry';
import type {
  RegisteredPanel,
  PanelPosition,
  RegisteredWidget,
  ToolbarWidgetPosition,
} from '../types/pluginApi';

/**
 * Return all registered plugin panels, re-rendering when panels are
 * added or removed.
 *
 * @param position - Optional position filter.  When provided, only panels
 *   matching the given position are returned.
 */
export function usePluginPanels(position?: PanelPosition): RegisteredPanel[] {
  // Seed state with the current registry snapshot.
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    const unsubscribe = onRegistryChange(() => {
      setRevision((r) => r + 1);
    });
    return unsubscribe;
  }, []);

  // Recompute whenever revision bumps (or position filter changes).
  const panels = useMemo(() => {
    // revision is intentionally in the dep array to trigger recomputation.
    void revision;
    return position != null
      ? getPanelsByPosition(position)
      : getRegisteredPanels();
  }, [revision, position]);

  return panels;
}

/**
 * Convenience hook: returns `true` when at least one plugin panel is
 * registered for the given position.
 */
export function useHasPluginPanels(position?: PanelPosition): boolean {
  const panels = usePluginPanels(position);
  return panels.length > 0;
}

/**
 * Return all registered toolbar widgets, re-rendering when widgets are
 * added or removed.
 *
 * @param position - Optional position filter.  When provided, only widgets
 *   matching the given position are returned.
 */
export function usePluginWidgets(position?: ToolbarWidgetPosition): RegisteredWidget[] {
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    const unsubscribe = onWidgetRegistryChange(() => {
      setRevision((r) => r + 1);
    });
    return unsubscribe;
  }, []);

  const widgetList = useMemo(() => {
    void revision;
    return position != null
      ? getWidgetsByPosition(position)
      : getRegisteredWidgets();
  }, [revision, position]);

  return widgetList;
}
