/**
 * Tests for useModelStore — modelData setter with direct value and
 * updater function.
 */

import { useModelStore } from '../useModelStore';
import type { ModelData } from '../../types/settings';

describe('useModelStore', () => {
  beforeEach(() => {
    useModelStore.setState({ modelData: null });
  });

  describe('initial state', () => {
    it('should start with null modelData', () => {
      expect(useModelStore.getState().modelData).toBeNull();
    });
  });

  describe('setModelData', () => {
    it('should set modelData directly', () => {
      const data: ModelData = { id: 'test', metadata: { label: 'Test' } };
      useModelStore.getState().setModelData(data);
      expect(useModelStore.getState().modelData).toEqual(data);
    });

    it('should set modelData to null', () => {
      useModelStore.setState({ modelData: { id: 'test' } });
      useModelStore.getState().setModelData(null);
      expect(useModelStore.getState().modelData).toBeNull();
    });

    it('should accept an updater function', () => {
      const initial: ModelData = { id: 'test', metadata: { label: 'Old' } };
      useModelStore.setState({ modelData: initial });

      useModelStore.getState().setModelData((prev) => {
        if (!prev) return prev;
        return { ...prev, metadata: { ...prev.metadata, label: 'New' } };
      });
      expect(useModelStore.getState().modelData?.metadata?.label).toBe('New');
    });

    it('should pass null to updater when modelData is null', () => {
      useModelStore.getState().setModelData((prev) => {
        expect(prev).toBeNull();
        return { id: 'created' };
      });
      expect(useModelStore.getState().modelData?.id).toBe('created');
    });
  });
});
