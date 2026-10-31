/**
 * PluginToolbarWidget - Renders external toolbar widgets registered by
 * other Drupal modules.
 *
 * Each widget gets an inline container within the toolbar.  The widget's
 * `render()` callback receives a plain DOM element and the public API —
 * it owns everything inside that element.
 *
 * Widgets are typically single buttons styled with the `toolbar-btn` CSS
 * class for visual consistency, but they can render any inline content.
 */

import React, { useEffect, useRef } from 'react';
import type { RegisteredWidget, ModelerPluginApi } from '../types/pluginApi';

interface PluginToolbarWidgetProps {
  widget: RegisteredWidget;
  api: ModelerPluginApi;
}

/**
 * Renders a single plugin toolbar widget.
 * Handles mount/unmount lifecycle.
 */
const PluginToolbarWidget: React.FC<PluginToolbarWidgetProps> = ({ widget, api }) => {
  const containerRef = useRef<HTMLDivElement>(null);
  const mountedRef = useRef(false);

  useEffect(() => {
    const el = containerRef.current;
    if (!el || mountedRef.current) return;

    mountedRef.current = true;
    try {
      widget.render(el, api);
    } catch (err) {
      console.error(`Plugin widget "${widget.id}" render() failed:`, err);
    }

    return () => {
      mountedRef.current = false;
      if (widget.destroy) {
        try {
          widget.destroy(el);
        } catch (err) {
          console.error(`Plugin widget "${widget.id}" destroy() failed:`, err);
        }
      }
    };
    // widget.id is the stable identity — we deliberately do not re-render
    // when the widget descriptor reference changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [widget.id]);

  return (
    <div
      ref={containerRef}
      className="plugin-toolbar-widget"
      data-plugin-widget-id={widget.id}
      role="group"
      aria-label={widget.label}
    />
  );
};

// ── Container that renders all widgets for a toolbar position ─────────

interface PluginToolbarWidgetSlotProps {
  widgets: RegisteredWidget[];
  api: ModelerPluginApi;
}

/**
 * Renders all registered plugin widgets for a given toolbar position.
 */
const PluginToolbarWidgetSlot: React.FC<PluginToolbarWidgetSlotProps> = ({ widgets: widgetList, api }) => {
  if (widgetList.length === 0) return null;

  return (
    <>
      {widgetList.length > 0 && <div className="toolbar-separator" />}
      {widgetList.map((widget) => (
        <PluginToolbarWidget key={widget.id} widget={widget} api={api} />
      ))}
    </>
  );
};

export default PluginToolbarWidgetSlot;
export { PluginToolbarWidget };
