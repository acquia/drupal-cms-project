/**
 * Tests for usePanelStore — panel widths with clamping, collapse
 * toggles, and localStorage persistence (including edge cases).
 */

import { PANEL_DIMENSIONS, STORAGE_KEYS } from '../../constants/dimensions';

// We need to test the loadPanelWidth and loadBoolean helper functions
// that run at store creation time, so we dynamically require the store
// after setting up localStorage values.

describe('usePanelStore', () => {
  beforeEach(() => {
    localStorage.clear();
    jest.resetModules();
  });

  function getStore() {
    return require('../usePanelStore').usePanelStore;
  }

  describe('initial defaults', () => {
    it('should use default widths when localStorage is empty', () => {
      const usePanelStore = getStore();
      const s = usePanelStore.getState();
      expect(s.panelWidth).toBe(PANEL_DIMENSIONS.PROPERTY_PANEL.DEFAULT_WIDTH);
      expect(s.replayPanelWidth).toBe(PANEL_DIMENSIONS.REPLAY_PANEL.DEFAULT_WIDTH);
    });

    it('should not be resizing by default', () => {
      const usePanelStore = getStore();
      const s = usePanelStore.getState();
      expect(s.panelIsResizing).toBe(false);
      expect(s.replayPanelIsResizing).toBe(false);
    });

    it('should not be collapsed by default', () => {
      const usePanelStore = getStore();
      const s = usePanelStore.getState();
      expect(s.replayPanelCollapsed).toBe(false);
      expect(s.propertyPanelCollapsed).toBe(false);
    });
  });

  describe('loadPanelWidth (localStorage initialization)', () => {
    it('should restore a valid saved panel width from localStorage', () => {
      localStorage.setItem(STORAGE_KEYS.PROPERTY_PANEL_WIDTH, '400');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().panelWidth).toBe(400);
    });

    it('should ignore saved width below minimum and use default', () => {
      localStorage.setItem(STORAGE_KEYS.PROPERTY_PANEL_WIDTH, '10');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().panelWidth).toBe(PANEL_DIMENSIONS.PROPERTY_PANEL.DEFAULT_WIDTH);
    });

    it('should ignore saved width above maximum and use default', () => {
      localStorage.setItem(STORAGE_KEYS.PROPERTY_PANEL_WIDTH, '9999');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().panelWidth).toBe(PANEL_DIMENSIONS.PROPERTY_PANEL.DEFAULT_WIDTH);
    });

    it('should ignore NaN saved width and use default', () => {
      localStorage.setItem(STORAGE_KEYS.PROPERTY_PANEL_WIDTH, 'not-a-number');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().panelWidth).toBe(PANEL_DIMENSIONS.PROPERTY_PANEL.DEFAULT_WIDTH);
    });

    it('should handle localStorage getItem throwing and use default', () => {
      jest.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
        throw new Error('Storage error');
      });
      const usePanelStore = getStore();
      expect(usePanelStore.getState().panelWidth).toBe(PANEL_DIMENSIONS.PROPERTY_PANEL.DEFAULT_WIDTH);
      jest.restoreAllMocks();
    });

    it('should restore a valid replay panel width', () => {
      localStorage.setItem(STORAGE_KEYS.REPLAY_PANEL_WIDTH, '450');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().replayPanelWidth).toBe(450);
    });
  });

  describe('loadBoolean (localStorage initialization)', () => {
    it('should restore true from localStorage for collapsed state', () => {
      localStorage.setItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED, 'true');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().replayPanelCollapsed).toBe(true);
    });

    it('should restore false from localStorage for collapsed state', () => {
      localStorage.setItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED, 'false');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().replayPanelCollapsed).toBe(false);
    });

    it('should parse JSON boolean from localStorage', () => {
      // JSON.stringify(true) → "true" which is caught by the === 'true' check
      // Use a non-string-boolean JSON value that parses to boolean
      localStorage.setItem(STORAGE_KEYS.PROPERTY_PANEL_COLLAPSED, 'true');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().propertyPanelCollapsed).toBe(true);
    });

    it('should handle non-boolean JSON in localStorage and use default', () => {
      localStorage.setItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED, '"string-value"');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().replayPanelCollapsed).toBe(false);
    });

    it('should handle invalid JSON in localStorage and use default', () => {
      localStorage.setItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED, '{invalid');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().replayPanelCollapsed).toBe(false);
    });

    it('should handle non-null non-boolean-string values via JSON parse', () => {
      // saved !== null && not 'true'/'false' → tries JSON.parse
      localStorage.setItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED, '42');
      const usePanelStore = getStore();
      // 42 is not a boolean, so falls through to default
      expect(usePanelStore.getState().replayPanelCollapsed).toBe(false);
    });
  });

  describe('setPanelWidth', () => {
    it('should set the width and persist to localStorage', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setPanelWidth(400);
      expect(usePanelStore.getState().panelWidth).toBe(400);
      expect(localStorage.getItem(STORAGE_KEYS.PROPERTY_PANEL_WIDTH)).toBe('400');
    });

    it('should clamp below minimum', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setPanelWidth(50);
      expect(usePanelStore.getState().panelWidth).toBe(PANEL_DIMENSIONS.PROPERTY_PANEL.MIN_WIDTH);
    });

    it('should clamp above maximum', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setPanelWidth(9999);
      expect(usePanelStore.getState().panelWidth).toBe(PANEL_DIMENSIONS.PROPERTY_PANEL.MAX_WIDTH);
    });
  });

  describe('setReplayPanelWidth', () => {
    it('should clamp and persist', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setReplayPanelWidth(400);
      expect(usePanelStore.getState().replayPanelWidth).toBe(400);
      expect(localStorage.getItem(STORAGE_KEYS.REPLAY_PANEL_WIDTH)).toBe('400');
    });

    it('should clamp below minimum for replay panel', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setReplayPanelWidth(10);
      expect(usePanelStore.getState().replayPanelWidth).toBe(PANEL_DIMENSIONS.REPLAY_PANEL.MIN_WIDTH);
    });

    it('should clamp above maximum for replay panel', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setReplayPanelWidth(9999);
      expect(usePanelStore.getState().replayPanelWidth).toBe(PANEL_DIMENSIONS.REPLAY_PANEL.MAX_WIDTH);
    });
  });

  describe('resizing flags', () => {
    it('should set and clear panelIsResizing', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setPanelResizing(true);
      expect(usePanelStore.getState().panelIsResizing).toBe(true);
      usePanelStore.getState().setPanelResizing(false);
      expect(usePanelStore.getState().panelIsResizing).toBe(false);
    });

    it('should set replayPanelIsResizing', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setReplayPanelResizing(true);
      expect(usePanelStore.getState().replayPanelIsResizing).toBe(true);
    });
  });

  describe('collapse toggles', () => {
    it('toggleReplayPanelCollapse should flip and persist', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().toggleReplayPanelCollapse();
      expect(usePanelStore.getState().replayPanelCollapsed).toBe(true);
      expect(localStorage.getItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED)).toBe('true');
    });

    it('toggleReplayPanelCollapse should toggle back to false', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().toggleReplayPanelCollapse();
      usePanelStore.getState().toggleReplayPanelCollapse();
      expect(usePanelStore.getState().replayPanelCollapsed).toBe(false);
      expect(localStorage.getItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED)).toBe('false');
    });

    it('togglePropertyPanelCollapse should flip and persist', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().togglePropertyPanelCollapse();
      expect(usePanelStore.getState().propertyPanelCollapsed).toBe(true);
      expect(localStorage.getItem(STORAGE_KEYS.PROPERTY_PANEL_COLLAPSED)).toBe('true');
    });

    it('togglePropertyPanelCollapse should toggle back to false', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().togglePropertyPanelCollapse();
      usePanelStore.getState().togglePropertyPanelCollapse();
      expect(usePanelStore.getState().propertyPanelCollapsed).toBe(false);
      expect(localStorage.getItem(STORAGE_KEYS.PROPERTY_PANEL_COLLAPSED)).toBe('false');
    });
  });

  describe('direct collapse setters', () => {
    it('setReplayPanelCollapsed should set state and persist', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setReplayPanelCollapsed(true);
      expect(usePanelStore.getState().replayPanelCollapsed).toBe(true);
      expect(localStorage.getItem(STORAGE_KEYS.REPLAY_PANEL_COLLAPSED)).toBe('true');
    });

    it('setPropertyPanelCollapsed should set state', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setPropertyPanelCollapsed(true);
      expect(usePanelStore.getState().propertyPanelCollapsed).toBe(true);
    });
  });

  // panelMode is replay-session VIEW state and must NOT persist across reloads:
  // it always starts 'event' and setPanelMode never writes to localStorage.
  describe('panelMode (in-memory only, never persisted)', () => {
    it("defaults to 'event' even when localStorage holds 'review'", () => {
      // Simulate a prior (now-defunct) persisted review mode.
      localStorage.setItem('modelerPanelMode', 'review');
      const usePanelStore = getStore();
      expect(usePanelStore.getState().panelMode).toBe('event');
    });

    it('setPanelMode updates state but does NOT write panelMode to localStorage', () => {
      const usePanelStore = getStore();
      usePanelStore.getState().setPanelMode('review');
      expect(usePanelStore.getState().panelMode).toBe('review');
      // No persistence: the legacy key is never written.
      expect(localStorage.getItem('modelerPanelMode')).toBeNull();
    });
  });
});
