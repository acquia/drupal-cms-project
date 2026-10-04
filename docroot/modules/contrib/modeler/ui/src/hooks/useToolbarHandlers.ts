/**
 * useToolbarHandlers - Hook for toolbar button click handlers
 *
 * Provides wrapped event handlers that prevent default behavior
 * and stop propagation for toolbar button clicks.
 */

import { useCallback } from 'react';

interface UseToolbarHandlersProps {
  onToggleLock?: () => void;
  onAutoLayout?: () => void;
  onCopy?: () => void;
  onPaste?: () => void;
  onToggleSearch?: () => void;
  onClose?: () => void;
}

interface UseToolbarHandlersReturn {
  handleToggleLock: (e: React.MouseEvent) => void;
  handleAutoLayout: (e: React.MouseEvent) => void;
  handleCopy: (e: React.MouseEvent) => void;
  handlePaste: (e: React.MouseEvent) => void;
  handleToggleSearch: (e: React.MouseEvent) => void;
  handleClose: (e: React.MouseEvent) => void;
}

/**
 * Creates a toolbar click handler that prevents default behavior,
 * stops propagation, and then calls the provided callback.
 */
function useToolbarHandler(callback?: () => void) {
  return useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    callback?.();
  }, [callback]);
}

export function useToolbarHandlers({
  onToggleLock,
  onAutoLayout,
  onCopy,
  onPaste,
  onToggleSearch,
  onClose,
}: UseToolbarHandlersProps): UseToolbarHandlersReturn {
  const handleToggleLock = useToolbarHandler(onToggleLock);
  const handleAutoLayout = useToolbarHandler(onAutoLayout);
  const handleCopy = useToolbarHandler(onCopy);
  const handlePaste = useToolbarHandler(onPaste);
  const handleToggleSearch = useToolbarHandler(onToggleSearch);
  const handleClose = useToolbarHandler(onClose);

  return {
    handleToggleLock,
    handleAutoLayout,
    handleCopy,
    handlePaste,
    handleToggleSearch,
    handleClose,
  };
}

// Zoom limits (matching ReactFlow's minZoom and maxZoom)
const ZOOM_LIMITS = {
  MIN: 0.1,
  MAX: 4,
  TOLERANCE: 0.01,
};

/**
 * Check if zoom level is at minimum
 */
export function isAtMinZoom(zoomLevel: number): boolean {
  return zoomLevel <= ZOOM_LIMITS.MIN + ZOOM_LIMITS.TOLERANCE;
}

/**
 * Check if zoom level is at maximum
 */
export function isAtMaxZoom(zoomLevel: number): boolean {
  return zoomLevel >= ZOOM_LIMITS.MAX - ZOOM_LIMITS.TOLERANCE;
}
