import React, { Profiler, useCallback } from 'react';
import { FiChevronLeft, FiChevronRight } from 'react-icons/fi';
import { usePanelStore } from '../store/usePanelStore';
import { PANEL_DIMENSIONS } from '../constants/dimensions';
import { t } from '../utils/translation';
import { usePanelResize } from '../hooks/usePanelResize';
import { onRenderCallback } from '../utils/profiling';
import ReplayPanelContent from './ReplayPanelContent';
import type { ReplayPanelContentProps, StepInfo } from './ReplayPanelContent';

// Re-export the shared types and helpers so existing importers keep working.
export type { ReplayPanelContentProps, StepInfo };
export { formatTimestamp, formatUser, formatException } from './ReplayPanelContent';

interface ReplayPanelProps extends ReplayPanelContentProps {
  isVisible?: boolean;
  onClose?: () => void;
}

/**
 * Standalone replay panel wrapper.
 *
 * NOTE: As of the unified-panel refactor (issue project/modeler#3576269) the
 * replay content is rendered INSIDE `PropertyPanel` (in "Review flow" mode)
 * via {@link ReplayPanelContent}. This standalone wrapper is retained as a thin
 * shell — keeping its outer chrome (collapse widget, resize handle, fixed-width
 * column) — so existing imports, stories, and tests continue to work. It is no
 * longer rendered as a separate column inside `Flow.tsx`.
 */
const ReplayPanel: React.FC<ReplayPanelProps> = ({
  isVisible = false,
  onClose: _onClose,
  ...contentProps
}) => {
  // Get replay panel width and resizing state from store
  const replayPanelWidth = usePanelStore((state) => state.replayPanelWidth);
  const replayPanelIsResizing = usePanelStore((state) => state.replayPanelIsResizing);
  const setReplayPanelWidth = usePanelStore((state) => state.setReplayPanelWidth);
  const setReplayPanelResizing = usePanelStore((state) => state.setReplayPanelResizing);
  const replayPanelCollapsed = usePanelStore((state) => state.replayPanelCollapsed);
  const toggleReplayPanelCollapse = usePanelStore((state) => state.toggleReplayPanelCollapse);

  // Resize handler using the extracted hook
  const { startResize } = usePanelResize({
    panelWidth: replayPanelWidth,
    setPanelWidth: setReplayPanelWidth,
    setPanelResizing: setReplayPanelResizing,
    direction: 'left', // Middle panel: dragging left increases width
  });

  // Handle click on collapsed panel to expand
  const handlePanelClick = useCallback((e: React.MouseEvent) => {
    if (replayPanelCollapsed) {
      e.stopPropagation();
      toggleReplayPanelCollapse();
    }
  }, [replayPanelCollapsed, toggleReplayPanelCollapse]);

  if (!isVisible) {
    return null;
  }

  return (
    <Profiler id="ReplayPanel" onRender={onRenderCallback}>
    <div
      className={`replay-panel ${replayPanelIsResizing ? 'resizing' : ''} ${replayPanelCollapsed ? 'collapsed' : ''}`}
      style={{ width: replayPanelCollapsed ? `${PANEL_DIMENSIONS.REPLAY_PANEL.COLLAPSED_WIDTH}px` : `${replayPanelWidth}px` }}
      onClick={handlePanelClick}
      title={replayPanelCollapsed ? t('Click to expand') : undefined}
    >
      <div
        className="resize-handle"
        onMouseDown={startResize}
        title={t('Drag to resize')}
      />
      <button
        className="panel-collapse-widget"
        onClick={toggleReplayPanelCollapse}
        title={replayPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
        aria-label={replayPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
      >
        <span className="collapse-icon">
          {replayPanelCollapsed ? <FiChevronLeft /> : <FiChevronRight />}
        </span>
      </button>
      {replayPanelCollapsed && (
        <div className="panel-collapsed-label">
          <span>{t('Replay')}</span>
        </div>
      )}
      <div className="panel-content">
        <ReplayPanelContent {...contentProps} />
      </div>
    </div>
    </Profiler>
  );
};

export default ReplayPanel;
