/**
 * Tests for usePluginPanels hook
 */

import { renderHook } from '@testing-library/react';
import { usePluginPanels, useHasPluginPanels, usePluginWidgets } from '../usePluginPanels';
import {
  registerPanel,
  registerWidget,
  resetRegistry,
} from '../../plugins/pluginRegistry';

describe('usePluginPanels', () => {
  beforeEach(() => {
    resetRegistry();
  });

  afterEach(() => {
    resetRegistry();
  });

  describe('usePluginPanels', () => {
    it('should return empty array when no panels registered', () => {
      const { result } = renderHook(() => usePluginPanels());
      expect(result.current).toEqual([]);
    });

    it('should return registered panels', () => {
      registerPanel({
        id: 'test-panel',
        label: 'Test Panel',
        render: () => {},
      });

      const { result } = renderHook(() => usePluginPanels());
      expect(result.current).toHaveLength(1);
      expect(result.current[0].id).toBe('test-panel');
    });

    it('should return panels filtered by position', () => {
      registerPanel({
        id: 'left-panel',
        label: 'Left Panel',
        position: 'left',
        render: () => {},
      });
      registerPanel({
        id: 'right-panel',
        label: 'Right Panel',
        position: 'right',
        render: () => {},
      });

      const { result: leftResult } = renderHook(() => usePluginPanels('left'));
      const { result: rightResult } = renderHook(() => usePluginPanels('right'));

      expect(leftResult.current).toHaveLength(1);
      expect(leftResult.current[0].id).toBe('left-panel');
      expect(rightResult.current).toHaveLength(1);
      expect(rightResult.current[0].id).toBe('right-panel');
    });

    it('should sort panels by weight', () => {
      registerPanel({
        id: 'heavy-panel',
        label: 'Heavy Panel',
        weight: 100,
        render: () => {},
      });
      registerPanel({
        id: 'light-panel',
        label: 'Light Panel',
        weight: 0,
        render: () => {},
      });

      const { result } = renderHook(() => usePluginPanels());
      expect(result.current[0].id).toBe('light-panel');
      expect(result.current[1].id).toBe('heavy-panel');
    });
  });

  describe('useHasPluginPanels', () => {
    it('should return false when no panels registered', () => {
      const { result } = renderHook(() => useHasPluginPanels());
      expect(result.current).toBe(false);
    });

    it('should return true when panels are registered', () => {
      registerPanel({
        id: 'test-panel',
        label: 'Test Panel',
        render: () => {},
      });

      const { result } = renderHook(() => useHasPluginPanels());
      expect(result.current).toBe(true);
    });

    it('should respect position filter', () => {
      registerPanel({
        id: 'left-panel',
        label: 'Left Panel',
        position: 'left',
        render: () => {},
      });

      const { result: leftResult } = renderHook(() => useHasPluginPanels('left'));
      const { result: rightResult } = renderHook(() => useHasPluginPanels('right'));

      expect(leftResult.current).toBe(true);
      expect(rightResult.current).toBe(false);
    });
  });
});

describe('usePluginWidgets', () => {
  beforeEach(() => {
    resetRegistry();
  });

  afterEach(() => {
    resetRegistry();
  });

  it('should return empty array when no widgets registered', () => {
    const { result } = renderHook(() => usePluginWidgets());
    expect(result.current).toEqual([]);
  });

  it('should return registered widgets', () => {
    registerWidget({
      id: 'test-widget',
      label: 'Test Widget',
      render: () => {},
    });

    const { result } = renderHook(() => usePluginWidgets());
    expect(result.current).toHaveLength(1);
    expect(result.current[0].id).toBe('test-widget');
  });

  it('should return widgets filtered by position', () => {
    registerWidget({
      id: 'left-widget',
      label: 'Left Widget',
      position: 'left',
      render: () => {},
    });
    registerWidget({
      id: 'right-widget',
      label: 'Right Widget',
      position: 'right',
      render: () => {},
    });

    const { result: leftResult } = renderHook(() => usePluginWidgets('left'));
    const { result: rightResult } = renderHook(() => usePluginWidgets('right'));

    expect(leftResult.current).toHaveLength(1);
    expect(leftResult.current[0].id).toBe('left-widget');
    expect(rightResult.current).toHaveLength(1);
    expect(rightResult.current[0].id).toBe('right-widget');
  });

  it('should sort widgets by weight', () => {
    registerWidget({
      id: 'heavy-widget',
      label: 'Heavy Widget',
      weight: 100,
      render: () => {},
    });
    registerWidget({
      id: 'light-widget',
      label: 'Light Widget',
      weight: 0,
      render: () => {},
    });

    const { result } = renderHook(() => usePluginWidgets());
    expect(result.current[0].id).toBe('light-widget');
    expect(result.current[1].id).toBe('heavy-widget');
  });
});
