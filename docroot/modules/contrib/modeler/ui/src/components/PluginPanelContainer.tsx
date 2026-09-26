/**
 * PluginPanelContainer - Renders external plugin panels registered by
 * other Drupal modules.
 *
 * Each registered panel gets a resizable container with a header showing the
 * panel label, wrapped in a PanelErrorBoundary so plugin crashes cannot tear
 * down the modeler.  The panel's `render()` callback receives a plain DOM
 * element and the public API — it owns everything inside that element.
 *
 * Panels have no collapse control.  Visibility is owned by the plugin, which
 * shows and hides its panel with `registerPanel()` / `unregisterPanel()` —
 * usually from a toolbar widget.
 *
 * Panels registered with `floating: true` are lifted out of their slot and
 * drawn over the canvas.  The user moves them by dragging the header, or by
 * focusing the move handle and pressing the arrow keys.  They are kept inside
 * the modeler, both by clamping every position they are given and by capping
 * their height to the room left below them.
 */

import React, { useEffect, useId, useLayoutEffect, useRef, useState, useCallback } from 'react';
import { FiMove } from 'react-icons/fi';
import PanelErrorBoundary from './PanelErrorBoundary';
import { usePanelResize } from '../hooks/usePanelResize';
import {
  useFloatingPanelDrag,
  clampFloatingPosition,
  getFloatingBounds,
} from '../hooks/useFloatingPanelDrag';
import type { FloatingPosition } from '../hooks/useFloatingPanelDrag';
import {
  clampFloatingPanelHeight,
  getMaximumFloatingPanelHeight,
  useFloatingPanelHeightResize,
} from '../hooks/useFloatingPanelHeightResize';
import { PANEL_DIMENSIONS } from '../constants/dimensions';
import { t } from '../utils/translation';
import type { RegisteredPanel, ModelerPluginApi, PanelPosition } from '../types/pluginApi';

const FLOATING_MARGIN = PANEL_DIMENSIONS.PLUGIN_PANEL.FLOATING_MARGIN;

/**
 * Last known position of each floating panel, keyed by panel ID.
 *
 * The documented way for a plugin to hide a panel is to unregister it, which
 * unmounts this component.  Without a store outside the component tree, every
 * hide/show cycle would throw the user's chosen position away.  This keeps it
 * for the lifetime of the page — it is deliberately not persisted, so a page
 * reload returns every panel to its default corner.
 */
const floatingPositions = new Map<string, FloatingPosition>();

/** Forget all remembered floating panel positions (also used by tests). */
export function resetFloatingPanelPositions(): void {
  floatingPositions.clear();
}

/**
 * Vertical origin for a floating panel's default placement.
 *
 * Floating panels are positioned against `.workflow-modeler`, which also
 * holds the toolbar — and they outrank it on z-index.  Anchoring at the bare
 * top margin would therefore drop every newly shown panel straight on top of
 * the toolbar controls.
 *
 * The panel's slot stays in normal flow even when all its panels float, and
 * it is measured against the same box, so its `offsetTop` is exactly the top
 * of the region the plugin asked for: below the toolbar for a `left`/`right`
 * panel, down at the foot of the modeler for a `bottom` one.  Clamping then
 * pulls a bottom panel fully back into view.
 *
 * This only seeds the default. Dragging is still clamped to the whole
 * modeler, so the user remains free to park a panel over the toolbar.
 */
function slotOffsetTop(el: HTMLElement | null): number {
  return el?.parentElement?.offsetTop ?? 0;
}

/**
 * Where a floating panel first appears.
 *
 * The horizontal anchor is derived from the registered `position`, so a
 * plugin still has a say in where its panel shows up.  Vertically the panel
 * is anchored to the top of its slot: a panel's height only becomes known
 * once the plugin's `render()` has filled it, which happens in a passive
 * effect *after* this placement runs, so any height-dependent maths here
 * would be measuring an empty panel.  Width, by contrast, is set by us and
 * is exact.
 */
function defaultFloatingPosition(
  el: HTMLElement | null,
  position: PanelPosition,
): FloatingPosition {
  const bounds = getFloatingBounds(el);
  const width = el?.offsetWidth ?? 0;

  let x: number;
  switch (position) {
    case 'left':
      x = FLOATING_MARGIN;
      break;
    case 'bottom':
      x = (bounds.width - width) / 2;
      break;
    case 'right':
    default:
      x = bounds.width - width - FLOATING_MARGIN;
      break;
  }

  const y = slotOffsetTop(el) + FLOATING_MARGIN;

  return clampFloatingPosition(el, { x, y }, FLOATING_MARGIN);
}

interface PluginPanelProps {
  panel: RegisteredPanel;
  api: ModelerPluginApi;
}

/**
 * Renders a single plugin panel.  Handles mount/unmount lifecycle, resize
 * and — for floating panels — moving.
 */
function PluginPanel({ panel, api }: PluginPanelProps) {
  const panelRef = useRef<HTMLDivElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const mountedRef = useRef(false);
  const contentId = useId();
  const [panelWidth, setPanelWidth] = useState(panel.width);
  const [panelHeight, setPanelHeight] = useState<number | null>(null);
  const [automaticPanelHeight, setAutomaticPanelHeight] = useState<number | null>(null);
  const [isResizing, setIsResizing] = useState(false);
  const [isHeightResizing, setIsHeightResizing] = useState(false);
  const [heightResizeCompletions, setHeightResizeCompletions] = useState(0);
  const [isDragging, setIsDragging] = useState(false);
  const [floatingPosition, setFloatingPosition] = useState<FloatingPosition>(
    () => floatingPositions.get(panel.id) ?? { x: FLOATING_MARGIN, y: FLOATING_MARGIN },
  );

  const isFloating = panel.floating === true;

  // A floating panel is anchored by its top-left corner, so the right edge is
  // always the one that grows it.  A docked panel grows away from its slot.
  const resizeEdge: 'left' | 'right' =
    isFloating || panel.position === 'left' ? 'right' : 'left';

  const { startResize } = usePanelResize({
    panelWidth,
    setPanelWidth,
    setPanelResizing: setIsResizing,
    direction: resizeEdge,
    minWidth: PANEL_DIMENSIONS.PLUGIN_PANEL.MIN_WIDTH,
    maxWidth: PANEL_DIMENSIONS.PLUGIN_PANEL.MAX_WIDTH,
  });

  /** Record the position so it survives an unregister/re-register cycle. */
  const commitFloatingPosition = useCallback((position: FloatingPosition) => {
    floatingPositions.set(panel.id, position);
    setFloatingPosition(position);
  }, [panel.id]);

  const { startDrag, nudge } = useFloatingPanelDrag({
    position: floatingPosition,
    setPosition: commitFloatingPosition,
    setDragging: setIsDragging,
    elementRef: panelRef,
    margin: FLOATING_MARGIN,
    enabled: isFloating,
  });

  const finishHeightResize = useCallback(() => {
    setHeightResizeCompletions((count) => count + 1);
  }, []);

  const {
    startResize: startHeightResize,
    resizeByKeyboard,
  } = useFloatingPanelHeightResize({
    elementRef: panelRef,
    panelY: floatingPosition.y,
    setPanelHeight,
    setPanelResizing: setIsHeightResizing,
    onResizeEnd: finishHeightResize,
    minHeight: PANEL_DIMENSIONS.PLUGIN_PANEL.MIN_HEIGHT,
    margin: FLOATING_MARGIN,
    enabled: isFloating,
  });

  // Mount: call render() once; Unmount: call destroy()
  useEffect(() => {
    const el = containerRef.current;
    if (!el || mountedRef.current) return;

    mountedRef.current = true;
    try {
      panel.render(el, api);
    } catch (err) {
      console.error(`Plugin panel "${panel.id}" render() failed:`, err);
    }

    return () => {
      mountedRef.current = false;
      if (panel.destroy) {
        try {
          panel.destroy(el);
        } catch (err) {
          console.error(`Plugin panel "${panel.id}" destroy() failed:`, err);
        }
      }
    };
    // panel.id is the stable identity — we deliberately do not re-render
    // when the panel descriptor reference changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [panel.id]);

  // Keep the separator's accessible current value aligned with the panel's
  // content-driven height until the first manual resize sets an explicit one.
  useEffect(() => {
    if (!isFloating || panelHeight !== null) return;
    const element = panelRef.current;
    if (!element) return;

    const measure = () => setAutomaticPanelHeight(element.offsetHeight);
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(element);
    return () => observer.disconnect();
  }, [isFloating, panelHeight]);

  // Place a floating panel once it has been measured.  Runs before paint, so
  // the provisional top-left position used for the first render is never
  // visible.  A remembered position is re-clamped rather than replaced.
  useLayoutEffect(() => {
    if (!isFloating) return;
    const remembered = floatingPositions.get(panel.id);
    commitFloatingPosition(
      remembered
        ? clampFloatingPosition(panelRef.current, remembered, FLOATING_MARGIN)
        : defaultFloatingPosition(panelRef.current, panel.position),
    );
  }, [isFloating, panel.id, panel.position, commitFloatingPosition]);

  // Shrinking the window must not strand a floating panel off-screen.
  useEffect(() => {
    if (!isFloating) return;
    const handleWindowResize = () => {
      setFloatingPosition((previous) => {
        const clamped = clampFloatingPosition(panelRef.current, previous, FLOATING_MARGIN);
        if (panelHeight !== null) {
          setPanelHeight(
            clampFloatingPanelHeight(
              panelRef.current,
              panelHeight,
              clamped.y,
              PANEL_DIMENSIONS.PLUGIN_PANEL.MIN_HEIGHT,
              FLOATING_MARGIN,
            ),
          );
        }
        floatingPositions.set(panel.id, clamped);
        return clamped;
      });
    };
    window.addEventListener('resize', handleWindowResize);
    return () => window.removeEventListener('resize', handleWindowResize);
  }, [isFloating, panel.id, panelHeight]);

  // Notify plugin of resize events
  useEffect(() => {
    if (!isResizing && panel.onResize && containerRef.current) {
      const rect = containerRef.current.getBoundingClientRect();
      try {
        panel.onResize(rect.width, rect.height);
      } catch (err) {
        console.error(`Plugin panel "${panel.id}" onResize() failed:`, err);
      }
    }
  }, [isResizing, panelWidth, panel]);

  // A completion counter makes keyboard resizing observable even though React
  // batches its start/end state changes into one render.
  useEffect(() => {
    if (heightResizeCompletions === 0 || !panel.onResize || !containerRef.current) return;
    const rect = containerRef.current.getBoundingClientRect();
    try {
      panel.onResize(rect.width, rect.height);
    } catch (err) {
      console.error(`Plugin panel "${panel.id}" onResize() failed:`, err);
    }
  }, [heightResizeCompletions, panel]);

  /** Keyboard alternative to dragging: arrow keys nudge, Shift moves faster. */
  const handleMoveKeyDown = useCallback((e: React.KeyboardEvent<HTMLButtonElement>) => {
    const step = e.shiftKey
      ? PANEL_DIMENSIONS.PLUGIN_PANEL.FLOATING_NUDGE_STEP_LARGE
      : PANEL_DIMENSIONS.PLUGIN_PANEL.FLOATING_NUDGE_STEP;

    let deltaX = 0;
    let deltaY = 0;
    switch (e.key) {
      case 'ArrowLeft': deltaX = -step; break;
      case 'ArrowRight': deltaX = step; break;
      case 'ArrowUp': deltaY = -step; break;
      case 'ArrowDown': deltaY = step; break;
      default: return;
    }

    e.preventDefault();
    nudge(deltaX, deltaY);
  }, [nudge]);

  const handleHeightResizeKeyDown = useCallback((e: React.KeyboardEvent<HTMLDivElement>) => {
    if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
    e.preventDefault();
    const step = e.shiftKey
      ? PANEL_DIMENSIONS.PLUGIN_PANEL.FLOATING_RESIZE_STEP_LARGE
      : PANEL_DIMENSIONS.PLUGIN_PANEL.FLOATING_RESIZE_STEP;
    resizeByKeyboard(e.key === 'ArrowDown' ? step : -step);
  }, [resizeByKeyboard]);

  const maximumPanelHeight = getMaximumFloatingPanelHeight(
    panelRef.current,
    floatingPosition.y,
    FLOATING_MARGIN,
  );
  const minimumPanelHeight = Math.min(
    PANEL_DIMENSIONS.PLUGIN_PANEL.MIN_HEIGHT,
    maximumPanelHeight,
  );
  const measuredPanelHeight = panelHeight
    ?? automaticPanelHeight
    ?? panelRef.current?.offsetHeight
    ?? minimumPanelHeight;
  const accessiblePanelHeight = Math.min(
    Math.max(measuredPanelHeight, minimumPanelHeight),
    maximumPanelHeight,
  );

  const className = [
    'plugin-panel',
    `plugin-panel--${panel.position}`,
    isFloating ? 'plugin-panel--floating' : '',
    isFloating && panelHeight === null ? 'is-auto-height' : '',
    isFloating && panelHeight !== null ? 'has-manual-height' : '',
    isResizing ? 'is-resizing' : '',
    isHeightResizing ? 'is-height-resizing' : '',
    isDragging ? 'is-dragging' : '',
  ].filter(Boolean).join(' ');

  const style: React.CSSProperties = isFloating
    ? {
        width: panelWidth,
        left: floatingPosition.x,
        top: floatingPosition.y,
        height: panelHeight ?? undefined,
        /*
         * Cap the height so the foot of the panel stays inside the modeler,
         * leaving the same margin below it as above.  This has to live here
         * rather than in the stylesheet: CSS can only cap against the whole
         * containing block, which would let a panel parked further down
         * overhang the bottom edge and put the end of its scrollable content
         * out of reach.
         */
        maxHeight: `calc(100% - ${floatingPosition.y + FLOATING_MARGIN}px)`,
      }
    : { width: panelWidth };

  return (
    <div
      ref={panelRef}
      className={className}
      style={style}
      data-plugin-panel-id={panel.id}
    >
      {/* Resize handle */}
      <div
        className={`plugin-panel-resize-handle plugin-panel-resize-handle--${resizeEdge}`}
        onMouseDown={startResize}
        role="separator"
        aria-orientation="vertical"
        aria-label={t('Resize @panel panel', { '@panel': panel.label })}
        tabIndex={0}
      />

      {isFloating && (
        <div
          className="plugin-panel-height-resize-handle"
          onPointerDown={startHeightResize}
          onKeyDown={handleHeightResizeKeyDown}
          role="separator"
          aria-orientation="horizontal"
          aria-label={t('Resize @panel panel height', { '@panel': panel.label })}
          aria-controls={contentId}
          aria-valuemin={minimumPanelHeight}
          aria-valuemax={maximumPanelHeight}
          aria-valuenow={accessiblePanelHeight}
          tabIndex={0}
        />
      )}

      {/*
        * Panel header.  Static text for a docked panel; for a floating panel
        * the whole strip is a drag surface, with the button below providing
        * the visible affordance and the keyboard route.
        */}
      <div
        className="plugin-panel-header"
        onMouseDown={isFloating ? startDrag : undefined}
      >
        <span className="plugin-panel-title">{panel.label}</span>
        {isFloating && (
          <button
            type="button"
            className="plugin-panel-drag-handle"
            onKeyDown={handleMoveKeyDown}
            aria-label={t('Move @panel panel', { '@panel': panel.label })}
            title={t('Drag to move, or press the arrow keys')}
          >
            <FiMove size={14} aria-hidden="true" />
          </button>
        )}
      </div>

      {/* Panel content — plugin owns this element */}
      <div
        ref={containerRef}
        id={contentId}
        className="plugin-panel-content"
        role="region"
        aria-label={panel.label}
      />
    </div>
  );
}

// ── Container that renders all panels for a position ──────────────────

interface PluginPanelSlotProps {
  panels: RegisteredPanel[];
  api: ModelerPluginApi;
  position: PanelPosition;
}

/**
 * Renders all registered plugin panels for a given position.
 * Each panel is wrapped in a PanelErrorBoundary for isolation.
 *
 * Floating panels are rendered here too, but they are taken out of flow by
 * CSS, so they contribute no width or height to the slot.
 */
const PluginPanelSlot: React.FC<PluginPanelSlotProps> = ({ panels, api, position }) => {
  if (panels.length === 0) return null;

  return (
    <div className={`plugin-panel-slot plugin-panel-slot--${position}`}>
      {panels.map((panel) => (
        <PanelErrorBoundary
          key={panel.id}
          panelName={panel.label}
          className="plugin-panel-error"
        >
          <PluginPanel panel={panel} api={api} />
        </PanelErrorBoundary>
      ))}
    </div>
  );
};

export default PluginPanelSlot;
export { PluginPanel };
