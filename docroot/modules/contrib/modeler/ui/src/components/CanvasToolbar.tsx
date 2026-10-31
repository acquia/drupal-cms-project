/**
 * CanvasToolbar - Secondary toolbar rendered on top of the canvas
 *
 * Visually transparent bar at the top of the canvas area, same height
 * as the panel headers. Contains:
 *  - Left: View dropdown (fit view, auto layout)
 *  - Right: Copy, Paste, Undo, Redo, separator, Zoom out, Zoom in
 */

import React, { memo, useCallback, useMemo, useRef, useState } from 'react';
import {
  FiCopy,
  FiClipboard,
  FiRotateCcw,
  FiRotateCw,
  FiZoomIn,
  FiZoomOut,
  FiMaximize,
  FiLayout,
  FiChevronDown,
  FiEye,
} from 'react-icons/fi';
import { useReactFlow, useStore } from 'reactflow';
import type { ReactFlowState } from 'reactflow';
import { useClickOutside } from '../hooks/useClickOutside';
import { t } from '../utils/translation';
import { VIEWPORT } from '../constants/dimensions';
import StartFlowFilter from './StartFlowFilter';
import type { ModelerContext } from '../types/settings';

interface CanvasToolbarProps {
  /** Whether the canvas is locked (read-only mode) */
  isLocked: boolean;
  /** Whether the modeler is in read-only mode */
  isReadOnly?: boolean;
  /** Copy selected elements */
  onCopy: () => void;
  /** Paste elements */
  onPaste: () => void;
  /** Undo last action */
  onUndo?: () => void;
  /** Redo previously undone action */
  onRedo?: () => void;
  /** Whether there is a selection to copy */
  hasSelection?: boolean;
  /** Whether paste is available */
  canPaste?: boolean;
  /** Whether undo is available */
  canUndo?: boolean;
  /** Whether redo is available */
  canRedo?: boolean;
  /** Trigger auto layout */
  onAutoLayout: () => void;
  /** Fit the viewport to all visible nodes */
  onFitView: () => void;
  /** Available contexts from drupalSettings.modeler_api.contexts */
  contexts?: ModelerContext[];
  /** Currently selected context ID (null = none) */
  selectedContextId?: string | null;
  /** Callback when the user selects a context */
  onContextChange?: (contextId: string | null) => void;
}

/** Zoom limits matching the centralized VIEWPORT constants */
const ZOOM_MIN = VIEWPORT.MIN_ZOOM;
const ZOOM_MAX = VIEWPORT.MAX_ZOOM;
const ZOOM_TOLERANCE = 0.01;

/** Selector for reactive zoom level from ReactFlow store */
const zoomSelector = (state: ReactFlowState) => state.transform[2];

/** Stable empty array to avoid new references on every render. */
const EMPTY_CONTEXTS: ModelerContext[] = [];

const CanvasToolbar = memo<CanvasToolbarProps>(({
  isLocked,
  isReadOnly = false,
  onCopy,
  onPaste,
  onUndo,
  onRedo,
  hasSelection = false,
  canPaste = false,
  canUndo = false,
  canRedo = false,
  onAutoLayout,
  onFitView,
  contexts = EMPTY_CONTEXTS,
  selectedContextId = null,
  onContextChange,
}) => {
  // Reactive zoom level directly from ReactFlow internal store
  const zoomLevel = useStore(zoomSelector);
  const reactFlow = useReactFlow();
  const [viewMenuOpen, setViewMenuOpen] = useState(false);
  const viewMenuRef = useRef<HTMLDivElement>(null);
  const viewButtonRef = useRef<HTMLButtonElement>(null);

  useClickOutside(
    viewMenuOpen,
    [viewMenuRef],
    useCallback(() => setViewMenuOpen(false), []),
  );

  // Zoom handlers
  const handleZoomIn = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    reactFlow.zoomIn();
  }, [reactFlow]);

  const handleZoomOut = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    reactFlow.zoomOut();
  }, [reactFlow]);

  // View menu actions
  const handleFitView = useCallback(() => {
    onFitView();
    setViewMenuOpen(false);
  }, [onFitView]);

  const handleAutoLayout = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    onAutoLayout();
    setViewMenuOpen(false);
  }, [onAutoLayout]);

  const handleCopy = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    onCopy();
  }, [onCopy]);

  const handlePaste = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    onPaste();
  }, [onPaste]);

  const handleViewMenuKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      setViewMenuOpen(false);
      viewButtonRef.current?.focus();
    }
  }, []);

  const isAtMin = zoomLevel <= ZOOM_MIN + ZOOM_TOLERANCE;
  const isAtMax = zoomLevel >= ZOOM_MAX - ZOOM_TOLERANCE;

  const zoomPercent = useMemo(() => `${Math.round(zoomLevel * 100)}%`, [zoomLevel]);

  return (
    <div className="canvas-toolbar">
      {/* Left: Context selector, Flow filter, View dropdown */}
      <div className="canvas-toolbar-left">
        {contexts.length > 0 && (
          <select
            id="toolbar-context-select"
            name="toolbar-context-select"
            className="toolbar-context-select"
            value={selectedContextId ?? ''}
            onChange={(e) => onContextChange?.(e.target.value || null)}
            aria-label={t('Select Context')}
            title={t('Select Context')}
          >
            <option value="">{t('No Context')}</option>
            {contexts.map((ctx) => (
              <option key={ctx.id} value={ctx.id}>{ctx.topic}</option>
            ))}
          </select>
        )}
        <StartFlowFilter />
        <div className="canvas-toolbar-view-menu" ref={viewMenuRef}>
          <button
            ref={viewButtonRef}
            type="button"
            className="canvas-toolbar-btn canvas-toolbar-view-trigger"
            onClick={() => setViewMenuOpen(prev => !prev)}
            aria-haspopup="menu"
            aria-expanded={viewMenuOpen}
            aria-label={t('View options')}
            title={t('View options')}
          >
            <FiEye />
            <span className="canvas-toolbar-btn-label">{t('View')}</span>
            <FiChevronDown className={`canvas-toolbar-chevron${viewMenuOpen ? ' open' : ''}`} />
          </button>

          {viewMenuOpen && (
            <ul
              className="canvas-toolbar-dropdown"
              role="menu"
              aria-label={t('View options')}
              onKeyDown={handleViewMenuKeyDown}
            >
              <li
                role="menuitem"
                className="canvas-toolbar-dropdown-item"
                onClick={handleFitView}
                tabIndex={0}
              >
                <span className="canvas-toolbar-dropdown-icon"><FiMaximize /></span>
                <span className="canvas-toolbar-dropdown-label">{t('Fit View')}</span>
              </li>
              {!isReadOnly && (
                <li
                  role="menuitem"
                  className="canvas-toolbar-dropdown-item"
                  onClick={handleAutoLayout}
                  tabIndex={0}
                >
                  <span className="canvas-toolbar-dropdown-icon"><FiLayout /></span>
                  <span className="canvas-toolbar-dropdown-label">{t('Auto Layout')}</span>
                </li>
              )}
            </ul>
          )}
        </div>
      </div>

      {/* Right: Copy/Paste, Undo/Redo, Zoom controls */}
      <div className="canvas-toolbar-right">
        {!isReadOnly && (
          <>
            <button
              type="button"
              onClick={handleCopy}
              title={t('Copy Selected Elements (Ctrl+C)')}
              aria-label={t('Copy Selected Elements (Ctrl+C)')}
              className="canvas-toolbar-btn"
              disabled={isLocked || !hasSelection}
            >
              <FiCopy />
            </button>
            <button
              type="button"
              onClick={handlePaste}
              title={t('Paste Elements (Ctrl+V)')}
              aria-label={t('Paste Elements (Ctrl+V)')}
              className="canvas-toolbar-btn"
              disabled={isLocked || !canPaste}
            >
              <FiClipboard />
            </button>
            <button
              type="button"
              onClick={onUndo}
              title={t('Undo (Ctrl+Z)')}
              aria-label={t('Undo (Ctrl+Z)')}
              className="canvas-toolbar-btn"
              disabled={!canUndo}
            >
              <FiRotateCcw />
            </button>
            <button
              type="button"
              onClick={onRedo}
              title={t('Redo (Ctrl+Shift+Z)')}
              aria-label={t('Redo (Ctrl+Shift+Z)')}
              className="canvas-toolbar-btn"
              disabled={!canRedo}
            >
              <FiRotateCw />
            </button>
            <div className="canvas-toolbar-separator" />
          </>
        )}
        <button
          type="button"
          onClick={handleZoomOut}
          title={t('Zoom Out')}
          aria-label={t('Zoom Out')}
          className="canvas-toolbar-btn"
          disabled={isAtMin}
        >
          <FiZoomOut />
        </button>
        <span className="canvas-toolbar-zoom-level" aria-label={t('Current zoom level')}>
          {zoomPercent}
        </span>
        <button
          type="button"
          onClick={handleZoomIn}
          title={t('Zoom In')}
          aria-label={t('Zoom In')}
          className="canvas-toolbar-btn"
          disabled={isAtMax}
        >
          <FiZoomIn />
        </button>
      </div>
    </div>
  );
});

CanvasToolbar.displayName = 'CanvasToolbar';

export default CanvasToolbar;
