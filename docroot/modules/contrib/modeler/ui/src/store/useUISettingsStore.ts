import { create } from 'zustand';
import { STORAGE_KEYS } from '../constants/dimensions';

interface UISettingsState {
  darkMode: boolean;
  toggleDarkMode: () => void;
  /**
   * True while an edge-endpoint reconnect drag is in progress (issue #3585553).
   * Set on grip mousedown, cleared on mouseup (every code path). FlowCanvas
   * reads it to add a `reconnect-dragging` class on the canvas wrapper so CSS
   * can make ALL endpoint grips non-interactive during the drag — otherwise a
   * non-dragged grip (e.g. on the destination node's selected edge) sits on top
   * at the drop point and intercepts the `elementFromPoint` hit-test, blocking
   * the drop.
   */
  reconnectDragActive: boolean;
  setReconnectDragActive: (active: boolean) => void;
}

export const useUISettingsStore = create<UISettingsState>((set, get) => ({
  reconnectDragActive: false,
  setReconnectDragActive: (active) => set({ reconnectDragActive: active }),

  darkMode: (() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEYS.THEME);
      if (saved === 'dark') return true;
      if (saved === 'light') return false;
      return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
    } catch {
      return false;
    }
  })(),
  toggleDarkMode: () => {
    const newState = !get().darkMode;
    set({ darkMode: newState });
    localStorage.setItem(STORAGE_KEYS.THEME, newState ? 'dark' : 'light');
  },
}));
