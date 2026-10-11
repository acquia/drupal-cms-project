/**
 * Tests for useComponentStore — components list and favorites.
 */

import { useComponentStore } from '../useComponentStore';
import type { StoreComponent } from '../../types/settings';

const makeComponent = (id: string): StoreComponent =>
  ({
    id,
    label: `Component ${id}`,
    type: 'action',
    plugin: `plugin_${id}`,
  }) as StoreComponent;

describe('useComponentStore', () => {
  beforeEach(() => {
    useComponentStore.setState({ components: [], favoriteComponents: {} });
  });

  describe('initial state', () => {
    it('should start empty', () => {
      const s = useComponentStore.getState();
      expect(s.components).toEqual([]);
      expect(s.favoriteComponents).toEqual({});
    });
  });

  describe('setComponents', () => {
    it('should replace the components list', () => {
      const comps = [makeComponent('c1'), makeComponent('c2')];
      useComponentStore.getState().setComponents(comps);
      expect(useComponentStore.getState().components).toHaveLength(2);
    });
  });

  describe('setFavoriteComponents', () => {
    it('should store favorites indexed by group', () => {
      const favorites = { 0: ['c1', 'c2'], 1: ['c3'] };
      useComponentStore.getState().setFavoriteComponents(favorites);
      expect(useComponentStore.getState().favoriteComponents).toEqual(favorites);
    });
  });
});
