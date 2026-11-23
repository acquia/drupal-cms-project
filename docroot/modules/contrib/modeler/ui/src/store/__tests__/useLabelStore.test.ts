/**
 * Tests for useLabelStore — component labels map.
 */

import { useLabelStore } from '../useLabelStore';

describe('useLabelStore', () => {
  beforeEach(() => {
    useLabelStore.setState({ componentLabels: {} });
  });

  describe('initial state', () => {
    it('should start with an empty labels map', () => {
      expect(useLabelStore.getState().componentLabels).toEqual({});
    });
  });

  describe('setComponentLabels', () => {
    it('should replace the labels map', () => {
      const labels = { start: 'Event', element: 'Action', link: 'Connection' };
      useLabelStore.getState().setComponentLabels(labels);
      expect(useLabelStore.getState().componentLabels).toEqual(labels);
    });

    it('should replace existing labels on subsequent calls', () => {
      useLabelStore.getState().setComponentLabels({ start: 'Trigger' });
      useLabelStore.getState().setComponentLabels({ element: 'Task' });
      expect(useLabelStore.getState().componentLabels).toEqual({ element: 'Task' });
    });
  });
});
