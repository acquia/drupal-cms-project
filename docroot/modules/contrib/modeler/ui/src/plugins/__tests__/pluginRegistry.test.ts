/**
 * Tests for pluginRegistry.ts — the plugin panel & widget registry.
 *
 * Covers: registerPanel, unregisterPanel, getRegisteredPanels,
 * getPanelsByPosition, registerWidget, unregisterWidget,
 * getRegisteredWidgets, getWidgetsByPosition, onRegistryChange,
 * onWidgetRegistryChange, onReady, markReady, markUnready, resetRegistry.
 */

import {
  registerPanel,
  unregisterPanel,
  getRegisteredPanels,
  getPanelsByPosition,
  registerWidget,
  unregisterWidget,
  getRegisteredWidgets,
  getWidgetsByPosition,
  onRegistryChange,
  onWidgetRegistryChange,
  onReady,
  markReady,
  markUnready,
  resetRegistry,
} from '../pluginRegistry';

import type {
  PluginPanelDescriptor,
  PluginToolbarWidgetDescriptor,
} from '../../types/pluginApi';

// ── Helpers ───────────────────────────────────────────────────────────

/** Minimal valid panel descriptor factory. */
function makePanel(overrides: Partial<PluginPanelDescriptor> = {}): PluginPanelDescriptor {
  return {
    id: 'test-panel',
    label: 'Test Panel',
    render: jest.fn(),
    ...overrides,
  };
}

/** Minimal valid widget descriptor factory. */
function makeWidget(
  overrides: Partial<PluginToolbarWidgetDescriptor> = {},
): PluginToolbarWidgetDescriptor {
  return {
    id: 'test-widget',
    label: 'Test Widget',
    render: jest.fn(),
    ...overrides,
  };
}

// ── Setup / teardown ──────────────────────────────────────────────────

beforeEach(() => {
  resetRegistry();
  jest.restoreAllMocks();
});

// =====================================================================
// registerPanel
// =====================================================================

describe('registerPanel', () => {
  it('registers a panel with defaults applied', () => {
    registerPanel(makePanel());

    const panels = getRegisteredPanels();
    expect(panels).toHaveLength(1);
    expect(panels[0]).toMatchObject({
      id: 'test-panel',
      label: 'Test Panel',
      position: 'right',
      weight: 0,
      width: 320,
      floating: false,
    });
    expect(typeof panels[0].render).toBe('function');
  });

  it('respects explicit position, weight, and width', () => {
    registerPanel(makePanel({ id: 'p1', position: 'left', weight: 5, width: 400 }));

    const panels = getRegisteredPanels();
    expect(panels[0]).toMatchObject({
      position: 'left',
      weight: 5,
      width: 400,
    });
  });

  it('opts a panel into floating mode', () => {
    registerPanel(makePanel({ id: 'p1', floating: true }));

    expect(getRegisteredPanels()[0].floating).toBe(true);
  });

  it('includes optional destroy and onResize callbacks', () => {
    const destroy = jest.fn();
    const onResize = jest.fn();
    registerPanel(makePanel({ destroy, onResize }));

    const panels = getRegisteredPanels();
    expect(panels[0].destroy).toBe(destroy);
    expect(panels[0].onResize).toBe(onResize);
  });

  it('throws when id is an empty string', () => {
    expect(() => registerPanel(makePanel({ id: '' }))).toThrow(
      'Plugin panel descriptor must have a non-empty string "id".',
    );
  });

  it('throws when id is not a string', () => {
    expect(() => registerPanel(makePanel({ id: 123 as unknown as string }))).toThrow(
      'Plugin panel descriptor must have a non-empty string "id".',
    );
  });

  it('throws when render is not a function', () => {
    expect(() =>
      registerPanel(makePanel({ render: 'not-a-fn' as unknown as PluginPanelDescriptor['render'] })),
    ).toThrow('Plugin panel "test-panel" must provide a "render" function.');
  });

  it('throws on duplicate panel id', () => {
    registerPanel(makePanel({ id: 'dup' }));
    expect(() => registerPanel(makePanel({ id: 'dup' }))).toThrow(
      'Plugin panel "dup" is already registered.',
    );
  });

  it('notifies change listeners', () => {
    const listener = jest.fn();
    onRegistryChange(listener);

    registerPanel(makePanel());
    expect(listener).toHaveBeenCalledTimes(1);
  });
});

// =====================================================================
// unregisterPanel
// =====================================================================

describe('unregisterPanel', () => {
  it('removes a previously registered panel', () => {
    registerPanel(makePanel({ id: 'removable' }));
    expect(getRegisteredPanels()).toHaveLength(1);

    unregisterPanel('removable');
    expect(getRegisteredPanels()).toHaveLength(0);
  });

  it('notifies change listeners when a panel is removed', () => {
    registerPanel(makePanel({ id: 'removable' }));
    const listener = jest.fn();
    onRegistryChange(listener);

    unregisterPanel('removable');
    expect(listener).toHaveBeenCalledTimes(1);
  });

  it('does NOT notify listeners when panel id does not exist', () => {
    const listener = jest.fn();
    onRegistryChange(listener);

    unregisterPanel('non-existent');
    expect(listener).not.toHaveBeenCalled();
  });
});

// =====================================================================
// getRegisteredPanels
// =====================================================================

describe('getRegisteredPanels', () => {
  it('returns an empty array when no panels registered', () => {
    expect(getRegisteredPanels()).toEqual([]);
  });

  it('returns panels sorted by weight (ascending)', () => {
    registerPanel(makePanel({ id: 'heavy', weight: 10 }));
    registerPanel(makePanel({ id: 'light', weight: -5 }));
    registerPanel(makePanel({ id: 'medium', weight: 0 }));

    const ids = getRegisteredPanels().map((p) => p.id);
    expect(ids).toEqual(['light', 'medium', 'heavy']);
  });
});

// =====================================================================
// getPanelsByPosition
// =====================================================================

describe('getPanelsByPosition', () => {
  it('filters panels by position', () => {
    registerPanel(makePanel({ id: 'left1', position: 'left' }));
    registerPanel(makePanel({ id: 'right1', position: 'right' }));
    registerPanel(makePanel({ id: 'left2', position: 'left', weight: 5 }));
    registerPanel(makePanel({ id: 'bottom1', position: 'bottom' }));

    const leftPanels = getPanelsByPosition('left');
    expect(leftPanels.map((p) => p.id)).toEqual(['left1', 'left2']);

    const rightPanels = getPanelsByPosition('right');
    expect(rightPanels.map((p) => p.id)).toEqual(['right1']);

    const bottomPanels = getPanelsByPosition('bottom');
    expect(bottomPanels.map((p) => p.id)).toEqual(['bottom1']);
  });

  it('returns empty array when no panels match position', () => {
    registerPanel(makePanel({ id: 'right1', position: 'right' }));
    expect(getPanelsByPosition('left')).toEqual([]);
  });
});

// =====================================================================
// registerWidget
// =====================================================================

describe('registerWidget', () => {
  it('registers a widget with defaults applied', () => {
    registerWidget(makeWidget());

    const widgets = getRegisteredWidgets();
    expect(widgets).toHaveLength(1);
    expect(widgets[0]).toMatchObject({
      id: 'test-widget',
      label: 'Test Widget',
      position: 'right',
      weight: 0,
    });
  });

  it('respects explicit position and weight', () => {
    registerWidget(makeWidget({ id: 'w1', position: 'left', weight: 3 }));

    const widgets = getRegisteredWidgets();
    expect(widgets[0]).toMatchObject({
      position: 'left',
      weight: 3,
    });
  });

  it('throws when id is an empty string', () => {
    expect(() => registerWidget(makeWidget({ id: '' }))).toThrow(
      'Plugin widget descriptor must have a non-empty string "id".',
    );
  });

  it('throws when id is not a string', () => {
    expect(() => registerWidget(makeWidget({ id: null as unknown as string }))).toThrow(
      'Plugin widget descriptor must have a non-empty string "id".',
    );
  });

  it('throws when render is not a function', () => {
    expect(() =>
      registerWidget(
        makeWidget({ render: 42 as unknown as PluginToolbarWidgetDescriptor['render'] }),
      ),
    ).toThrow('Plugin widget "test-widget" must provide a "render" function.');
  });

  it('throws on duplicate widget id', () => {
    registerWidget(makeWidget({ id: 'dup' }));
    expect(() => registerWidget(makeWidget({ id: 'dup' }))).toThrow(
      'Plugin widget "dup" is already registered.',
    );
  });

  it('notifies widget change listeners', () => {
    const listener = jest.fn();
    onWidgetRegistryChange(listener);

    registerWidget(makeWidget());
    expect(listener).toHaveBeenCalledTimes(1);
  });
});

// =====================================================================
// unregisterWidget
// =====================================================================

describe('unregisterWidget', () => {
  it('removes a previously registered widget', () => {
    registerWidget(makeWidget({ id: 'removable' }));
    expect(getRegisteredWidgets()).toHaveLength(1);

    unregisterWidget('removable');
    expect(getRegisteredWidgets()).toHaveLength(0);
  });

  it('notifies widget change listeners when a widget is removed', () => {
    registerWidget(makeWidget({ id: 'removable' }));
    const listener = jest.fn();
    onWidgetRegistryChange(listener);

    unregisterWidget('removable');
    expect(listener).toHaveBeenCalledTimes(1);
  });

  it('does NOT notify listeners when widget id does not exist', () => {
    const listener = jest.fn();
    onWidgetRegistryChange(listener);

    unregisterWidget('non-existent');
    expect(listener).not.toHaveBeenCalled();
  });
});

// =====================================================================
// getRegisteredWidgets
// =====================================================================

describe('getRegisteredWidgets', () => {
  it('returns an empty array when no widgets registered', () => {
    expect(getRegisteredWidgets()).toEqual([]);
  });

  it('returns widgets sorted by weight (ascending)', () => {
    registerWidget(makeWidget({ id: 'heavy', weight: 10 }));
    registerWidget(makeWidget({ id: 'light', weight: -2 }));
    registerWidget(makeWidget({ id: 'medium', weight: 0 }));

    const ids = getRegisteredWidgets().map((w) => w.id);
    expect(ids).toEqual(['light', 'medium', 'heavy']);
  });
});

// =====================================================================
// getWidgetsByPosition
// =====================================================================

describe('getWidgetsByPosition', () => {
  it('filters widgets by position', () => {
    registerWidget(makeWidget({ id: 'l1', position: 'left' }));
    registerWidget(makeWidget({ id: 'r1', position: 'right' }));
    registerWidget(makeWidget({ id: 'l2', position: 'left', weight: 5 }));

    const leftWidgets = getWidgetsByPosition('left');
    expect(leftWidgets.map((w) => w.id)).toEqual(['l1', 'l2']);

    const rightWidgets = getWidgetsByPosition('right');
    expect(rightWidgets.map((w) => w.id)).toEqual(['r1']);
  });

  it('returns empty array when no widgets match position', () => {
    registerWidget(makeWidget({ id: 'r1', position: 'right' }));
    expect(getWidgetsByPosition('left')).toEqual([]);
  });
});

// =====================================================================
// onRegistryChange
// =====================================================================

describe('onRegistryChange', () => {
  it('returns an unsubscribe function that removes the listener', () => {
    const listener = jest.fn();
    const unsubscribe = onRegistryChange(listener);

    registerPanel(makePanel({ id: 'p1' }));
    expect(listener).toHaveBeenCalledTimes(1);

    unsubscribe();

    registerPanel(makePanel({ id: 'p2' }));
    expect(listener).toHaveBeenCalledTimes(1); // not called again
  });

  it('supports multiple listeners', () => {
    const listener1 = jest.fn();
    const listener2 = jest.fn();
    onRegistryChange(listener1);
    onRegistryChange(listener2);

    registerPanel(makePanel());

    expect(listener1).toHaveBeenCalledTimes(1);
    expect(listener2).toHaveBeenCalledTimes(1);
  });
});

// =====================================================================
// notifyChange error handling
// =====================================================================

describe('notifyChange error handling', () => {
  it('catches listener errors and still calls remaining listeners', () => {
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    const error = new Error('listener boom');
    const throwingListener = jest.fn(() => {
      throw error;
    });
    const normalListener = jest.fn();

    onRegistryChange(throwingListener);
    onRegistryChange(normalListener);

    registerPanel(makePanel());

    expect(throwingListener).toHaveBeenCalledTimes(1);
    expect(normalListener).toHaveBeenCalledTimes(1);
    expect(consoleError).toHaveBeenCalledWith(
      'Plugin registry change listener error:',
      error,
    );
  });
});

// =====================================================================
// onWidgetRegistryChange
// =====================================================================

describe('onWidgetRegistryChange', () => {
  it('returns an unsubscribe function that removes the listener', () => {
    const listener = jest.fn();
    const unsubscribe = onWidgetRegistryChange(listener);

    registerWidget(makeWidget({ id: 'w1' }));
    expect(listener).toHaveBeenCalledTimes(1);

    unsubscribe();

    registerWidget(makeWidget({ id: 'w2' }));
    expect(listener).toHaveBeenCalledTimes(1); // not called again
  });
});

// =====================================================================
// notifyWidgetChange error handling
// =====================================================================

describe('notifyWidgetChange error handling', () => {
  it('catches listener errors and still calls remaining listeners', () => {
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    const error = new Error('widget listener boom');
    const throwingListener = jest.fn(() => {
      throw error;
    });
    const normalListener = jest.fn();

    onWidgetRegistryChange(throwingListener);
    onWidgetRegistryChange(normalListener);

    registerWidget(makeWidget());

    expect(throwingListener).toHaveBeenCalledTimes(1);
    expect(normalListener).toHaveBeenCalledTimes(1);
    expect(consoleError).toHaveBeenCalledWith(
      'Plugin widget registry change listener error:',
      error,
    );
  });
});

// =====================================================================
// onReady
// =====================================================================

describe('onReady', () => {
  it('adds callback and fires it when markReady is called', () => {
    const callback = jest.fn();
    onReady(callback);

    expect(callback).not.toHaveBeenCalled();

    markReady();
    expect(callback).toHaveBeenCalledTimes(1);
  });

  it('fires callback immediately if already ready', () => {
    markReady();

    const callback = jest.fn();
    onReady(callback);

    expect(callback).toHaveBeenCalledTimes(1);
  });

  it('still adds listener even when fired immediately (for remount)', () => {
    markReady();

    const callback = jest.fn();
    onReady(callback);
    expect(callback).toHaveBeenCalledTimes(1);

    // Reset and re-mark ready — should fire again
    markUnready();
    markReady();
    expect(callback).toHaveBeenCalledTimes(2);
  });

  it('catches errors from immediate callback when already ready', () => {
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    const error = new Error('onReady immediate boom');

    markReady();

    const throwingCallback = jest.fn(() => {
      throw error;
    });
    onReady(throwingCallback);

    expect(throwingCallback).toHaveBeenCalledTimes(1);
    expect(consoleError).toHaveBeenCalledWith('Plugin onReady callback error:', error);
  });

  it('returns an unsubscribe function', () => {
    const callback = jest.fn();
    const unsubscribe = onReady(callback);

    unsubscribe();

    markReady();
    expect(callback).not.toHaveBeenCalled();
  });

  it('unsubscribe works even after immediate fire', () => {
    markReady();

    const callback = jest.fn();
    const unsubscribe = onReady(callback);
    expect(callback).toHaveBeenCalledTimes(1);

    unsubscribe();

    markUnready();
    markReady();
    // Should not be called again since we unsubscribed
    expect(callback).toHaveBeenCalledTimes(1);
  });
});

// =====================================================================
// markReady
// =====================================================================

describe('markReady', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('fires all ready listeners', () => {
    const cb1 = jest.fn();
    const cb2 = jest.fn();
    onReady(cb1);
    onReady(cb2);

    markReady();

    expect(cb1).toHaveBeenCalledTimes(1);
    expect(cb2).toHaveBeenCalledTimes(1);
  });

  it('catches errors from ready listeners and still calls remaining ones', () => {
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    const error = new Error('ready listener boom');
    const throwingCb = jest.fn(() => {
      throw error;
    });
    const normalCb = jest.fn();

    onReady(throwingCb);
    onReady(normalCb);

    markReady();

    expect(throwingCb).toHaveBeenCalledTimes(1);
    expect(normalCb).toHaveBeenCalledTimes(1);
    expect(consoleError).toHaveBeenCalledWith('Plugin onReady callback error:', error);
  });

  it('dispatches a custom DOM event asynchronously via setTimeout', () => {
    const eventHandler = jest.fn();
    document.addEventListener('workflow-modeler:ready', eventHandler);

    markReady();

    // Event should NOT have fired yet (it's in a setTimeout)
    expect(eventHandler).not.toHaveBeenCalled();

    // Advance timers to fire the deferred event
    jest.runAllTimers();
    expect(eventHandler).toHaveBeenCalledTimes(1);

    const event = eventHandler.mock.calls[0][0];
    expect(event).toBeInstanceOf(CustomEvent);
    expect(event.type).toBe('workflow-modeler:ready');

    document.removeEventListener('workflow-modeler:ready', eventHandler);
  });
});

// =====================================================================
// markUnready
// =====================================================================

describe('markUnready', () => {
  it('resets the ready state so onReady callbacks are not fired immediately', () => {
    markReady();
    markUnready();

    const callback = jest.fn();
    onReady(callback);

    // Since we're no longer ready, callback should NOT fire immediately
    expect(callback).not.toHaveBeenCalled();

    // But it should fire when markReady is called again
    markReady();
    expect(callback).toHaveBeenCalledTimes(1);
  });
});

// =====================================================================
// resetRegistry
// =====================================================================

describe('resetRegistry', () => {
  it('clears all panels, widgets, listeners, and ready state', () => {
    // Set up state
    registerPanel(makePanel({ id: 'p1' }));
    registerWidget(makeWidget({ id: 'w1' }));
    const panelListener = jest.fn();
    const widgetListener = jest.fn();
    onRegistryChange(panelListener);
    onWidgetRegistryChange(widgetListener);
    markReady();

    // Reset
    resetRegistry();

    // Verify everything is cleared
    expect(getRegisteredPanels()).toEqual([]);
    expect(getRegisteredWidgets()).toEqual([]);

    // Listeners should have been cleared — new registrations should not notify old listeners
    registerPanel(makePanel({ id: 'p2' }));
    registerWidget(makeWidget({ id: 'w2' }));
    // panelListener was called once during initial registerPanel, but not after reset
    expect(panelListener).toHaveBeenCalledTimes(0); // was cleared before any notification via reset
    // widgetListener similarly
    expect(widgetListener).toHaveBeenCalledTimes(0);

    // Ready state should be reset — onReady should not fire immediately
    const readyCb = jest.fn();
    onReady(readyCb);
    expect(readyCb).not.toHaveBeenCalled();
  });
});

// =====================================================================
// Integration: full lifecycle
// =====================================================================

describe('integration: full lifecycle', () => {
  it('supports register → query → unregister → query cycle for panels', () => {
    registerPanel(makePanel({ id: 'a', position: 'left', weight: 2 }));
    registerPanel(makePanel({ id: 'b', position: 'right', weight: 1 }));
    registerPanel(makePanel({ id: 'c', position: 'left', weight: 1 }));

    expect(getRegisteredPanels().map((p) => p.id)).toEqual(['b', 'c', 'a']);
    expect(getPanelsByPosition('left').map((p) => p.id)).toEqual(['c', 'a']);

    unregisterPanel('c');
    expect(getPanelsByPosition('left').map((p) => p.id)).toEqual(['a']);
    expect(getRegisteredPanels()).toHaveLength(2);
  });

  it('supports register → query → unregister → query cycle for widgets', () => {
    registerWidget(makeWidget({ id: 'x', position: 'left', weight: 3 }));
    registerWidget(makeWidget({ id: 'y', position: 'right', weight: 1 }));
    registerWidget(makeWidget({ id: 'z', position: 'left', weight: 0 }));

    expect(getRegisteredWidgets().map((w) => w.id)).toEqual(['z', 'y', 'x']);
    expect(getWidgetsByPosition('left').map((w) => w.id)).toEqual(['z', 'x']);

    unregisterWidget('z');
    expect(getWidgetsByPosition('left').map((w) => w.id)).toEqual(['x']);
    expect(getRegisteredWidgets()).toHaveLength(2);
  });

  it('panel and widget registries are independent', () => {
    const panelListener = jest.fn();
    const widgetListener = jest.fn();
    onRegistryChange(panelListener);
    onWidgetRegistryChange(widgetListener);

    registerPanel(makePanel({ id: 'p1' }));
    expect(panelListener).toHaveBeenCalledTimes(1);
    expect(widgetListener).toHaveBeenCalledTimes(0);

    registerWidget(makeWidget({ id: 'w1' }));
    expect(panelListener).toHaveBeenCalledTimes(1);
    expect(widgetListener).toHaveBeenCalledTimes(1);
  });
});
