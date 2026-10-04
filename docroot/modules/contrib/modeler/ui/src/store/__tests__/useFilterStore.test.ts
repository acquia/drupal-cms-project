/**
 * Tests for useFilterStore — visible start node IDs.
 */

import { useFilterStore } from '../useFilterStore';

describe('useFilterStore', () => {
  beforeEach(() => {
    useFilterStore.setState({
      visibleStartNodeIds: null,
    });
  });

  describe('initial state', () => {
    it('should have null visible IDs', () => {
      const s = useFilterStore.getState();
      expect(s.visibleStartNodeIds).toBeNull();
    });
  });

  describe('setVisibleStartNodeIds', () => {
    it('should set an array of IDs', () => {
      useFilterStore.getState().setVisibleStartNodeIds(['n1', 'n2']);
      expect(useFilterStore.getState().visibleStartNodeIds).toEqual(['n1', 'n2']);
    });

    it('should reset to null', () => {
      useFilterStore.setState({ visibleStartNodeIds: ['n1'] });
      useFilterStore.getState().setVisibleStartNodeIds(null);
      expect(useFilterStore.getState().visibleStartNodeIds).toBeNull();
    });
  });
});
