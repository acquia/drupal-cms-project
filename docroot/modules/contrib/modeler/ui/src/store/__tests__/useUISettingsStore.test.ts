/**
 * Tests for useUISettingsStore — dark mode toggle with localStorage persistence.
 */

import { STORAGE_KEYS } from '../../constants/dimensions';

describe('useUISettingsStore', () => {
  beforeEach(() => {
    localStorage.clear();
    jest.resetModules();
  });

  function getStore() {
    return require('../useUISettingsStore').useUISettingsStore;
  }

  describe('initial state', () => {
    it('should default to light mode when localStorage is empty', () => {
      const useUISettingsStore = getStore();
      expect(useUISettingsStore.getState().darkMode).toBe(false);
    });

    it('should initialize to dark mode when localStorage has "dark"', () => {
      localStorage.setItem(STORAGE_KEYS.THEME, 'dark');
      const useUISettingsStore = getStore();
      expect(useUISettingsStore.getState().darkMode).toBe(true);
    });

    it('should initialize to light mode when localStorage has "light"', () => {
      localStorage.setItem(STORAGE_KEYS.THEME, 'light');
      const useUISettingsStore = getStore();
      expect(useUISettingsStore.getState().darkMode).toBe(false);
    });

    it('should fall back to matchMedia when localStorage has no theme', () => {
      // matchMedia is mocked to return matches: false by default in setupTests
      const useUISettingsStore = getStore();
      expect(useUISettingsStore.getState().darkMode).toBe(false);
    });

    it('should fall back to false when localStorage throws', () => {
      jest.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
        throw new Error('Storage error');
      });
      const useUISettingsStore = getStore();
      expect(useUISettingsStore.getState().darkMode).toBe(false);
      jest.restoreAllMocks();
    });
  });

  describe('toggleDarkMode', () => {
    it('should toggle from light to dark and persist', () => {
      const useUISettingsStore = getStore();
      useUISettingsStore.getState().toggleDarkMode();
      expect(useUISettingsStore.getState().darkMode).toBe(true);
      expect(localStorage.getItem(STORAGE_KEYS.THEME)).toBe('dark');
    });

    it('should toggle from dark to light and persist', () => {
      const useUISettingsStore = getStore();
      useUISettingsStore.setState({ darkMode: true });
      useUISettingsStore.getState().toggleDarkMode();
      expect(useUISettingsStore.getState().darkMode).toBe(false);
      expect(localStorage.getItem(STORAGE_KEYS.THEME)).toBe('light');
    });

    it('should toggle back and forth', () => {
      const useUISettingsStore = getStore();
      useUISettingsStore.getState().toggleDarkMode();
      useUISettingsStore.getState().toggleDarkMode();
      expect(useUISettingsStore.getState().darkMode).toBe(false);
    });
  });

  describe('reconnectDragActive (issue #3585553)', () => {
    it('should default to false', () => {
      const useUISettingsStore = getStore();
      expect(useUISettingsStore.getState().reconnectDragActive).toBe(false);
    });

    it('should set the flag true then false via setReconnectDragActive', () => {
      const useUISettingsStore = getStore();
      useUISettingsStore.getState().setReconnectDragActive(true);
      expect(useUISettingsStore.getState().reconnectDragActive).toBe(true);
      useUISettingsStore.getState().setReconnectDragActive(false);
      expect(useUISettingsStore.getState().reconnectDragActive).toBe(false);
    });
  });
});
