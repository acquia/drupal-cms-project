/**
 * Plugin Panel Registry
 *
 * Manages registration and lifecycle of external plugin panels.
 * Panels can be registered before or after the React app mounts —
 * early registrations are queued and rendered once the modeler is ready.
 *
 * This module is framework-agnostic: it stores plain descriptors and
 * notifies listeners when the registry changes.  The React side
 * subscribes to these changes via `usePluginPanels()`.
 */

import type {
  PluginPanelDescriptor,
  RegisteredPanel,
  PanelPosition,
  PluginToolbarWidgetDescriptor,
  RegisteredWidget,
  ToolbarWidgetPosition,
  ReadyCallback,
  Unsubscribe,
} from '../types/pluginApi';
import { PANEL_DIMENSIONS } from '../constants/dimensions';

// ── Internal state ────────────────────────────────────────────────────

/** All currently registered panels, keyed by panel ID. */
const panels = new Map<string, RegisteredPanel>();

/** All currently registered toolbar widgets, keyed by widget ID. */
const widgets = new Map<string, RegisteredWidget>();

/** Listeners notified whenever the panel set changes. */
const changeListeners = new Set<() => void>();

/** Listeners notified whenever the widget set changes. */
const widgetChangeListeners = new Set<() => void>();

/** Listeners waiting for the modeler to become ready. */
const readyListeners = new Set<ReadyCallback>();

/** Whether the React modeler has mounted and the API is available. */
let isReady = false;

// ── Default values ────────────────────────────────────────────────────

const DEFAULT_POSITION: PanelPosition = 'right';
const DEFAULT_WIDGET_POSITION: ToolbarWidgetPosition = 'right';
const DEFAULT_WEIGHT = 0;
const DEFAULT_WIDTH = PANEL_DIMENSIONS.PLUGIN_PANEL.DEFAULT_WIDTH;
const DEFAULT_FLOATING = false;

// ── Public API ────────────────────────────────────────────────────────

/**
 * Register a new plugin panel.
 *
 * @throws {Error} if a panel with the same ID is already registered.
 */
export function registerPanel(descriptor: PluginPanelDescriptor): void {
  if (!descriptor.id || typeof descriptor.id !== 'string') {
    throw new Error('Plugin panel descriptor must have a non-empty string "id".');
  }
  if (typeof descriptor.render !== 'function') {
    throw new Error(`Plugin panel "${descriptor.id}" must provide a "render" function.`);
  }
  if (panels.has(descriptor.id)) {
    throw new Error(`Plugin panel "${descriptor.id}" is already registered.`);
  }

  const registered: RegisteredPanel = {
    ...descriptor,
    position: descriptor.position ?? DEFAULT_POSITION,
    weight: descriptor.weight ?? DEFAULT_WEIGHT,
    width: descriptor.width ?? DEFAULT_WIDTH,
    floating: descriptor.floating ?? DEFAULT_FLOATING,
  };

  panels.set(registered.id, registered);
  notifyChange();
}

/**
 * Unregister a panel by ID.  The panel's `destroy` callback is NOT
 * called here — the React component handles teardown when it unmounts.
 */
export function unregisterPanel(panelId: string): void {
  if (panels.delete(panelId)) {
    notifyChange();
  }
}

/**
 * Return all registered panels sorted by weight then registration order.
 */
export function getRegisteredPanels(): RegisteredPanel[] {
  return Array.from(panels.values()).sort((a, b) => a.weight - b.weight);
}

/**
 * Return panels for a specific position, sorted by weight.
 */
export function getPanelsByPosition(position: PanelPosition): RegisteredPanel[] {
  return getRegisteredPanels().filter((p) => p.position === position);
}

// ── Widget registration ───────────────────────────────────────────────

/**
 * Register a new toolbar widget.
 *
 * @throws {Error} if a widget with the same ID is already registered.
 */
export function registerWidget(descriptor: PluginToolbarWidgetDescriptor): void {
  if (!descriptor.id || typeof descriptor.id !== 'string') {
    throw new Error('Plugin widget descriptor must have a non-empty string "id".');
  }
  if (typeof descriptor.render !== 'function') {
    throw new Error(`Plugin widget "${descriptor.id}" must provide a "render" function.`);
  }
  if (widgets.has(descriptor.id)) {
    throw new Error(`Plugin widget "${descriptor.id}" is already registered.`);
  }

  const registered: RegisteredWidget = {
    ...descriptor,
    position: descriptor.position ?? DEFAULT_WIDGET_POSITION,
    weight: descriptor.weight ?? DEFAULT_WEIGHT,
  };

  widgets.set(registered.id, registered);
  notifyWidgetChange();
}

/**
 * Unregister a toolbar widget by ID.  The widget's `destroy` callback
 * is NOT called here — the React component handles teardown on unmount.
 */
export function unregisterWidget(widgetId: string): void {
  if (widgets.delete(widgetId)) {
    notifyWidgetChange();
  }
}

/**
 * Return all registered widgets sorted by weight.
 */
export function getRegisteredWidgets(): RegisteredWidget[] {
  return Array.from(widgets.values()).sort((a, b) => a.weight - b.weight);
}

/**
 * Return widgets for a specific toolbar position, sorted by weight.
 */
export function getWidgetsByPosition(position: ToolbarWidgetPosition): RegisteredWidget[] {
  return getRegisteredWidgets().filter((w) => w.position === position);
}

// ── Change subscription ───────────────────────────────────────────────

/**
 * Subscribe to registry changes (panel added / removed).
 * Returns an unsubscribe function.
 */
export function onRegistryChange(listener: () => void): Unsubscribe {
  changeListeners.add(listener);
  return () => {
    changeListeners.delete(listener);
  };
}

function notifyChange(): void {
  changeListeners.forEach((fn) => {
    try {
      fn();
    } catch (err) {
      console.error('Plugin registry change listener error:', err);
    }
  });
}

/**
 * Subscribe to widget registry changes (widget added / removed).
 * Returns an unsubscribe function.
 */
export function onWidgetRegistryChange(listener: () => void): Unsubscribe {
  widgetChangeListeners.add(listener);
  return () => {
    widgetChangeListeners.delete(listener);
  };
}

function notifyWidgetChange(): void {
  widgetChangeListeners.forEach((fn) => {
    try {
      fn();
    } catch (err) {
      console.error('Plugin widget registry change listener error:', err);
    }
  });
}

// ── Ready lifecycle ───────────────────────────────────────────────────

/**
 * Subscribe to be notified when the modeler becomes ready.
 * If already ready, the callback fires synchronously.
 */
export function onReady(callback: ReadyCallback): Unsubscribe {
  if (isReady) {
    try {
      callback();
    } catch (err) {
      console.error('Plugin onReady callback error:', err);
    }
    // Still add the listener — the modeler might remount.
  }
  readyListeners.add(callback);
  return () => {
    readyListeners.delete(callback);
  };
}

/**
 * Called by the React app when the modeler has mounted and the public
 * API is available.
 *
 * After notifying internal listeners, a `workflow-modeler:ready` custom
 * event is dispatched on `document` so that external Drupal behaviors
 * can react to the modeler becoming available.
 */
export function markReady(): void {
  isReady = true;
  readyListeners.forEach((fn) => {
    try {
      fn();
    } catch (err) {
      console.error('Plugin onReady callback error:', err);
    }
  });

  // Dispatch a DOM event so Drupal behaviors and other non-bundled
  // scripts can reliably detect when the modeler is ready.
  // The event is dispatched asynchronously so that it fires after the
  // current call stack completes.  This is necessary because during
  // HTMX-based navigation the modeler may mount synchronously (via the
  // race-condition fix in index.js) *before* Drupal.attachBehaviors()
  // has run, meaning external behavior event listeners are not yet
  // registered.  Deferring with setTimeout(…, 0) guarantees the event
  // fires after all behaviors have attached.
  setTimeout(() => {
    document.dispatchEvent(new CustomEvent('workflow-modeler:ready'));
  }, 0);
}

/**
 * Called when the React app unmounts (e.g. HTMX navigation).
 * Resets the ready state but keeps registrations intact so panels
 * survive across re-mounts.
 */
export function markUnready(): void {
  isReady = false;
}

/**
 * Clear all registrations and listeners.  Intended for testing only.
 */
export function resetRegistry(): void {
  panels.clear();
  widgets.clear();
  changeListeners.clear();
  widgetChangeListeners.clear();
  readyListeners.clear();
  isReady = false;
}
