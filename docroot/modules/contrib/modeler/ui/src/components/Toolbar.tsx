/**
 * Toolbar - Main toolbar component for the workflow modeler
 *
 * Restructured layout:
 *  - Left: Add event button (light blue), inline search bar
 *  - Center: Model title (drag handle in restored mode)
 *  - Right: Docs link, Save button, kebab menu (settings/export/dark mode), Close
 *
 * Items previously here (zoom, copy/paste, undo/redo, lock, annotations,
 * edge orders, minimap) have been moved to the CanvasToolbar or removed.
 */

import React, { memo, Profiler, useRef } from 'react';
import { FiSave, FiX, FiMaximize2, FiMinimize2, FiZap, FiTrash2 } from 'react-icons/fi';
import type { ViewMode } from '../hooks/useViewMode';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import SearchBar, { SearchBarRef } from './SearchBar';

import QuickAddEventButton from './QuickAddEventButton';
import ToolbarMenu from './ToolbarMenu';
import PluginToolbarWidgetSlot from './PluginToolbarWidget';
import { t } from '../utils/translation';
import { useSaveModel } from '../hooks/useSaveModel';
import { useToolbarHandlers } from '../hooks/useToolbarHandlers';
import type { Settings, DrupalAjax, StoreComponent as Component } from '../types/settings';
import type { RegisteredWidget, ModelerPluginApi } from '../types/pluginApi';
import { onRenderCallback } from '../utils/profiling';

interface SearchResult {
  id: string;
  type: 'node' | 'edge';
  label: string;
  subtitle: string;
  data: Node | Edge;
}

interface ToolbarProps {
  onSave?: () => any;
  onSaveComplete?: () => void;
  onOpenMetadata: () => void;
  onToggleMessages: () => void;
  onClearMessages: () => void;
  isLocked: boolean;
  /** When true, all editing is disabled */
  isReadOnly?: boolean;
  hasMessages?: boolean;
  messagesVisible?: boolean;
  onSearchHighlight?: (result: SearchResult | null) => void;
  onSearchFocus?: (data: Node | Edge) => void;
  modelName?: string;
  hasUnsavedChanges?: boolean;
  onClose?: () => void;
  settings?: Settings;
  drupal?: DrupalAjax;
  saveButtonRef?: React.RefObject<HTMLButtonElement | null>;
  /** Screen reader announcement callback */
  announce?: (text: string) => void;
  /** Pre-save validation callback (return error message or null) */
  validateBeforeSave?: () => string | null;

  /** Callback when user adds a new event via quick-add */
  onAddEvent?: (component: Component) => void;
  /** Whether the event popup is open (controlled) */
  isEventPopupOpen?: boolean;
  /** Callback when event popup open state changes */
  onEventPopupOpenChange?: (isOpen: boolean) => void;
  /** Callback when user clicks the export button */
  onExport?: () => void;
  /** Whether the export button should be visible */
  canExport?: boolean;
  /** Current view mode: fullscreen or restored */
  viewMode?: ViewMode;
  /** Toggle between fullscreen and restored */
  onToggleViewMode?: () => void;
  /** Start dragging the window (for restored Drupal mode) */
  onStartDrag?: (e: React.MouseEvent) => void;
  /** Plugin widgets registered for the left toolbar position */
  pluginWidgetsLeft?: RegisteredWidget[];
  /** Plugin widgets registered for the right toolbar position */
  pluginWidgetsRight?: RegisteredWidget[];
  /** Public API passed to plugin widgets */
  pluginApi?: ModelerPluginApi | null;
}

/** Stable empty arrays to avoid new references on every render. */
const EMPTY_WIDGETS: RegisteredWidget[] = [];

const Toolbar = memo<ToolbarProps>(({
  onSave,
  onSaveComplete,
  onOpenMetadata,
  onToggleMessages,
  onClearMessages,
  isLocked,
  isReadOnly = false,
  hasMessages = false,
  messagesVisible = false,
  onSearchHighlight,
  onSearchFocus,
  modelName = t('Untitled Model'),
  hasUnsavedChanges = false,
  onClose,
  settings = {},
  drupal,
  saveButtonRef,
  announce,
  validateBeforeSave,

  onAddEvent,
  isEventPopupOpen,
  onEventPopupOpenChange,
  onExport,
  canExport = false,
  viewMode = 'fullscreen',
  onToggleViewMode,
  onStartDrag,
  pluginWidgetsLeft = EMPTY_WIDGETS,
  pluginWidgetsRight = EMPTY_WIDGETS,
  pluginApi = null,
}) => {
  const searchBarRef = useRef<SearchBarRef>(null);

  // Use extracted save model hook
  const { handleSave } = useSaveModel({
    onSave,
    onSaveComplete,
    settings,
    drupal,
    announce,
    validateBeforeSave,
  });

  // Use extracted toolbar handlers hook
  const {
    handleClose,
  } = useToolbarHandlers({
    onClose,
  });

  const isStandalone = !!settings.modeler?.standalone;

  return (
    <Profiler id="Toolbar" onRender={onRenderCallback}>
    <div className="workflow-toolbar">
      <div className="toolbar-left">
        {onAddEvent && !isLocked && !isReadOnly && (
          <QuickAddEventButton
            onAddEvent={onAddEvent}
            disabled={isLocked}
            isOpen={isEventPopupOpen}
            onOpenChange={onEventPopupOpenChange}
          />
        )}

        {/* Inline search bar - always visible next to actions */}
        <div className="toolbar-search-inline">
          <SearchBar
            ref={searchBarRef}
            onHighlight={onSearchHighlight}
            onFocus={onSearchFocus}
          />
        </div>
        {pluginApi && pluginWidgetsLeft.length > 0 && (
          <PluginToolbarWidgetSlot widgets={pluginWidgetsLeft} api={pluginApi} />
        )}
      </div>
      <div
        className="toolbar-center"
        onMouseDown={onStartDrag}
        onDoubleClick={onToggleViewMode}
      >
        <h1 className="model-title">
          {hasUnsavedChanges && <span className="unsaved-indicator">{'● '}</span>}
          {modelName}
        </h1>
        {hasMessages && (
          <div className="messages-actions">
            <button
              type="button"
              onClick={onToggleMessages}
              title={messagesVisible ? t('Hide messages') : t('Show messages')}
              aria-label={messagesVisible ? t('Hide messages') : t('Show messages')}
              className={`messages-toggle-btn ${messagesVisible ? 'inactive' : 'active'}`}
            >
              <FiZap />
            </button>
            <button
              type="button"
              onClick={onClearMessages}
              title={t('Clear messages')}
              aria-label={t('Clear messages')}
              className="messages-clear-btn"
            >
              <FiTrash2 />
            </button>
          </div>
        )}
      </div>

      <div className="toolbar-right">
        {pluginApi && pluginWidgetsRight.length > 0 && (
          <PluginToolbarWidgetSlot widgets={pluginWidgetsRight} api={pluginApi} />
        )}
        {!isReadOnly && (
          <button
            type="button"
            ref={saveButtonRef}
            onClick={handleSave}
            title={t('Save Model')}
            className="toolbar-btn primary"
            disabled={!hasUnsavedChanges}
          >
            <FiSave /> {t('Save')}
          </button>
        )}
        <ToolbarMenu
          onOpenMetadata={onOpenMetadata}
          onExport={onExport}
          canExport={canExport}
        />
        {onToggleViewMode && (
          <button
            type="button"
            onClick={onToggleViewMode}
            title={viewMode === 'fullscreen' ? t('Restore Window') : t('Fullscreen')}
            aria-label={viewMode === 'fullscreen' ? t('Restore Window') : t('Fullscreen')}
            className="toolbar-btn"
          >
            {viewMode === 'fullscreen' ? <FiMinimize2 /> : <FiMaximize2 />}
          </button>
        )}
        {!isStandalone && (
          <button
            type="button"
            onClick={handleClose}
            title={t('Close Modeler')}
            aria-label={t('Close Modeler')}
            className="toolbar-btn"
          >
            <FiX />
          </button>
        )}
      </div>
    </div>
    </Profiler>
  );
});

// eslint-disable-next-line i18n/no-untranslated-strings
Toolbar.displayName = 'Toolbar';

export default Toolbar;
