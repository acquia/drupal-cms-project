/**
 * Tests for useContextStore — contexts, dependencies, selectedContextId,
 * and contextConfig.
 */

import { useContextStore } from '../useContextStore';
import type { ModelerDependencies } from '../../types/settings';

describe('useContextStore', () => {
  beforeEach(() => {
    useContextStore.setState({
      contexts: [],
      dependencies: {},
      selectedContextId: null,
      contextConfig: {},
    });
  });

  describe('initial state', () => {
    it('should start empty', () => {
      const s = useContextStore.getState();
      expect(s.contexts).toEqual([]);
      expect(s.dependencies).toEqual({});
      expect(s.selectedContextId).toBeNull();
      expect(s.contextConfig).toEqual({});
    });
  });

  describe('setContexts', () => {
    it('should replace the contexts list', () => {
      const ctx = [{ id: 'ctx1', label: 'Context 1' }];
      useContextStore.getState().setContexts(ctx as any);
      expect(useContextStore.getState().contexts).toEqual(ctx);
    });
  });

  describe('setDependencies', () => {
    it('should replace dependencies', () => {
      const deps = { events: { 'eca:content_save': [] } } as ModelerDependencies;
      useContextStore.getState().setDependencies(deps);
      expect(useContextStore.getState().dependencies).toEqual(deps);
    });
  });

  describe('setSelectedContextId', () => {
    it('should set and clear', () => {
      useContextStore.getState().setSelectedContextId('ctx1');
      expect(useContextStore.getState().selectedContextId).toBe('ctx1');

      useContextStore.getState().setSelectedContextId(null);
      expect(useContextStore.getState().selectedContextId).toBeNull();
    });
  });

  describe('setContextConfig', () => {
    it('should replace context config', () => {
      const config = { key: 'value' };
      useContextStore.getState().setContextConfig(config);
      expect(useContextStore.getState().contextConfig).toEqual(config);
    });
  });
});
