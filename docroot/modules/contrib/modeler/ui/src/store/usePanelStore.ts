import { create } from 'zustand';
import { PANEL_DIMENSIONS, STORAGE_KEYS } from '../constants/dimensions';

/**
 * Mode of the single right-hand panel.
 * - `event`: shows the selected component's properties (default).
 * - `review`: shows the execution replay / "Review flow" content.
 */
export type PanelMode = 'event' | 'review';

interface PanelState {
  panelWidth: number;
  panelIsResizing: boolean;
  replayPanelWidth: number;
  replayPanelIsResizing: boolean;
  replayPanelCollapsed: boolean;
  propertyPanelCollapsed: boolean;
  panelMode: PanelMode;
  setPanelWidth: (width: number) => void;
  setPanelResizing: (isResizing: boolean) => void;
  setReplayPanelWidth: (width: number) => void;
  setReplayPanelResizing: (isResizing: boolean) => void;
  toggleReplayPanelCollapse: () => void;
  setReplayPanelCollapsed: (collapsed: boolean) => void;
  togglePropertyPanelCollapse: () => void;
  setPropertyPanelCollapsed: (collapsed: boolean) => void;
  setPanelMode: (mode: PanelMode) => void;
}

const loadPanelWidth = (key: string, min: number, max: number, defaultWidth: number): number => {
  try {
    const saved = localStorage.getItem(key);
    if (saved) {
      const width = parseInt(saved, 10);
      if (!isNaN(width) && width >= min && width <= max) {
        return width;
      }
    }
  } catch (error) {
    console.warn(`Failed to parse panel width from localStorage (${key}):`, error);
  }
  return defaultWidth;
};

const loadBoolean = (key: string, defaultValue: boolean): boolean => {
  try {
    const saved = localStorage.getItem(key);
    if (saved === 'true') return true;
    if (saved === 'false') return false;
    if (saved !== null) {
      const parsed = JSON.parse(saved);
      if (typeof parsed === 'boolean') return parsed;
    }
  } catch (error) {
    console.warn(`Failed to parse boolean from localStorage (${key}):`, error);
  }
  return defaultValue;
};

export const usePanelStore = create<PanelState>((set, get) => ({
  panelWidth: loadPanelWidth(
    STORAGE_KEYS.PROPERTY_PANEL_WIDTH,
    PANEL_DIMENSIONS.PROPERTY_PANEL.MIN_WIDTH,
    PANEL_DIMENSIONS.PROPERTY_PANEL.MAX_WIDTH,
    PANEL_DIMENSIONS.PROPERTY_PANEL.DEFAULT_WIDTH
  ),
  panelIsResizing: false,

  replayPanelWidth: loadPanelWidth(
    STORAGE_KEYS.REPLAY_PANEL_WIDTH,
    PANEL_DIMENSIONS.REPLAY_PANEL.MIN_WIDTH,
    PANEL_DIMENSIONS.REPLAY_PANEL.MAX_WIDTH,
    PANEL_DIMENSIONS.REPLAY_PANEL.DEFAULT_WIDTH
  ),
  replayPanelIsResizing: false,

  replayPanelCollapsed: loadBoolean(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED, false),
  propertyPanelCollapsed: loadBoolean(STORAGE_KEYS.PROPERTY_PANEL_COLLAPSED, false),

  // Replay-session VIEW state: intentionally NOT persisted. The Properties vs
  // Review view is per-canvas-session only and must reset to 'event' on every
  // model (re)load — replay sessions have no persistence whatsoever.
  panelMode: 'event',

  setPanelWidth: (width) => {
    const clampedWidth = Math.max(
      PANEL_DIMENSIONS.PROPERTY_PANEL.MIN_WIDTH,
      Math.min(PANEL_DIMENSIONS.PROPERTY_PANEL.MAX_WIDTH, width)
    );
    set({ panelWidth: clampedWidth });
    localStorage.setItem(STORAGE_KEYS.PROPERTY_PANEL_WIDTH, clampedWidth.toString());
  },

  setPanelResizing: (isResizing) => set({ panelIsResizing: isResizing }),

  setReplayPanelWidth: (width) => {
    const clampedWidth = Math.max(
      PANEL_DIMENSIONS.REPLAY_PANEL.MIN_WIDTH,
      Math.min(PANEL_DIMENSIONS.REPLAY_PANEL.MAX_WIDTH, width)
    );
    set({ replayPanelWidth: clampedWidth });
    localStorage.setItem(STORAGE_KEYS.REPLAY_PANEL_WIDTH, clampedWidth.toString());
  },

  setReplayPanelResizing: (isResizing) => set({ replayPanelIsResizing: isResizing }),

  toggleReplayPanelCollapse: () => {
    const newState = !get().replayPanelCollapsed;
    set({ replayPanelCollapsed: newState });
    localStorage.setItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED, JSON.stringify(newState));
  },

  setReplayPanelCollapsed: (collapsed: boolean) => {
    set({ replayPanelCollapsed: collapsed });
    localStorage.setItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED, JSON.stringify(collapsed));
  },

  togglePropertyPanelCollapse: () => {
    const newState = !get().propertyPanelCollapsed;
    set({ propertyPanelCollapsed: newState });
    localStorage.setItem(STORAGE_KEYS.PROPERTY_PANEL_COLLAPSED, JSON.stringify(newState));
  },

  setPropertyPanelCollapsed: (collapsed: boolean) => {
    set({ propertyPanelCollapsed: collapsed });
    localStorage.setItem(STORAGE_KEYS.PROPERTY_PANEL_COLLAPSED, JSON.stringify(collapsed));
  },

  setPanelMode: (mode: PanelMode) => {
    // In-memory only — see the panelMode initializer above (no persistence).
    set({ panelMode: mode });
  },
}));
