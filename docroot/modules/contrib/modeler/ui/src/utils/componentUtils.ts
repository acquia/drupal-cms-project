/**
 * Shared component utility functions used by QuickAdd popups,
 * PropertyPanel, and MultiSelectionPanel.
 */
import React from 'react';
import { FiActivity, FiBox, FiFilter, FiGitBranch, FiZap } from 'react-icons/fi';
import { t } from './translation';
import type { StoreComponent as Component } from '../types/settings';
import type { ComponentLabels, ComponentLabelsPlural } from '../types/settings';

/**
 * Generic default labels used when no model-owner-specific labels are
 * provided.  These are deliberately model-owner neutral.
 */
export const DEFAULT_COMPONENT_LABELS: Required<ComponentLabels> = {
  start: 'Start',
  element: 'Element',
  link: 'Link',
  gateway: 'Gateway',
  subprocess: 'Subprocess',
};

/**
 * Generic default plural labels used for grouping headings when no
 * model-owner-specific plural labels are provided.
 */
export const DEFAULT_COMPONENT_LABELS_PLURAL: Required<ComponentLabelsPlural> = {
  start: 'Starts',
  element: 'Elements',
  link: 'Links',
  gateway: 'Gateways',
  subprocess: 'Subprocesses',
};

/**
 * Default mapping from integer component-type constants to their string
 * node-type names.  Mirrors `Api::COMPONENT_TYPE_NAMES` from the backend.
 *
 * Used as a fallback when the backend-provided `typeMap` is not available
 * (e.g. standalone viewer mode, tests).
 */
export const DEFAULT_TYPE_MAP: Record<number, string> = {
  1: 'start',
  2: 'subprocess',
  3: 'swimlane',
  4: 'element',
  5: 'link',
  6: 'gateway',
  7: 'annotation',
};

/**
 * Resolve the string node type from an integer component type using the
 * given type map.  Falls back to `'element'` for unknown values.
 */
export function resolveNodeType(componentType: number | string | undefined, typeMap: Record<number, string> = DEFAULT_TYPE_MAP): string {
  if (componentType === undefined || componentType === null) return 'element';
  const key = typeof componentType === 'string' ? Number(componentType) : componentType;
  return typeMap[key] ?? 'element';
}

/**
 * Module-level reference to the current component labels.
 * Updated by calling `setActiveComponentLabels()` (typically from
 * `useModelDataLoader` after reading settings).
 *
 * Keeping this outside the store import avoids a circular-dependency
 * issue and allows tests to work without a fully initialized store.
 */
let _activeLabels: ComponentLabels = {};

/**
 * Module-level reference to the current plural component labels.
 * Updated by calling `setActiveComponentLabelsPlural()`.
 */
let _activeLabelsPlural: ComponentLabelsPlural = {};

/**
 * Update the active component labels.  Called once during initialization
 * (from `useModelDataLoader`) and whenever the labels change.
 */
export function setActiveComponentLabels(labels: ComponentLabels): void {
  _activeLabels = labels;
}

/**
 * Update the active plural component labels.
 */
export function setActiveComponentLabelsPlural(labels: ComponentLabelsPlural): void {
  _activeLabelsPlural = labels;
}

/**
 * Resolve the display label for a component type, checking the
 * active labels (set by the model owner) first, then falling back
 * to the generic defaults.
 */
export function getComponentLabel(internalType: keyof ComponentLabels): string {
  const raw = _activeLabels[internalType] ?? DEFAULT_COMPONENT_LABELS[internalType];
  return t(raw);
}

/**
 * Resolve the plural display label for a component type, checking the
 * active plural labels first, then falling back to the generic defaults.
 */
export function getComponentLabelPlural(internalType: keyof ComponentLabelsPlural): string {
  const raw = _activeLabelsPlural[internalType] ?? DEFAULT_COMPONENT_LABELS_PLURAL[internalType];
  return t(raw);
}

/**
 * Returns an icon element for a given node type.
 */
export const getComponentIcon = (nodeType: string): React.ReactElement => {
  switch (nodeType) {
    case 'start':
      return React.createElement(FiZap);
    case 'gateway':
      return React.createElement(FiGitBranch);
    case 'subprocess':
      return React.createElement(FiBox);
    case 'condition':
      return React.createElement(FiFilter);
    default:
      return React.createElement(FiActivity);
  }
};

/**
 * Returns a human-readable type name for a given node type.
 * Uses model-owner-provided labels when available, falling back to
 * generic defaults.
 */
export const getComponentTypeName = (nodeType: string): string => {
  switch (nodeType) {
    case 'start':
      return getComponentLabel('start');
    case 'gateway':
      return getComponentLabel('gateway');
    case 'subprocess':
      return getComponentLabel('subprocess');
    case 'condition':
      return getComponentLabel('link');
    default:
      return getComponentLabel('element');
  }
};

/**
 * Checks if a component is marked as a favorite for its type.
 * When a context is selected, favorites are ignored so the
 * context-filtered list is shown in plain alphabetical order.
 */
export function isComponentFavorite(
  component: Component,
  favoriteComponents: Record<number, string[]> | null | undefined,
  selectedContextId: string | null | undefined,
): boolean {
  if (selectedContextId) return false;
  if (!favoriteComponents || !component.componentType) return false;
  const favoritesForType = favoriteComponents[component.componentType];
  if (!favoritesForType || !Array.isArray(favoritesForType)) return false;
  return favoritesForType.includes(component.plugin);
}

/**
 * Sorts components with favorites first, then alphabetically by label.
 */
export function sortWithFavorites(
  components: Component[],
  favoriteComponents: Record<number, string[]> | null | undefined,
  selectedContextId: string | null | undefined,
): Component[] {
  return [...components].sort((a, b) => {
    const aIsFav = isComponentFavorite(a, favoriteComponents, selectedContextId);
    const bIsFav = isComponentFavorite(b, favoriteComponents, selectedContextId);

    // Favorites come first
    if (aIsFav && !bIsFav) return -1;
    if (!aIsFav && bIsFav) return 1;

    // Within same group, sort alphabetically by label
    const aLabel = (a.label || '').toLowerCase();
    const bLabel = (b.label || '').toLowerCase();
    return aLabel.localeCompare(bLabel);
  });
}

/**
 * Filters components by a text search term, matching against label, plugin, and description.
 */
export function filterComponentsBySearch(components: Component[], searchTerm: string): Component[] {
  if (!searchTerm.trim()) return components;
  const term = searchTerm.toLowerCase();
  return components.filter(comp =>
    comp.label?.toLowerCase().includes(term) ||
    comp.plugin?.toLowerCase().includes(term) ||
    comp.description?.toLowerCase().includes(term)
  );
}
