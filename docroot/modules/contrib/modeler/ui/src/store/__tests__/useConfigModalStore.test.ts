/**
 * Tests for useConfigModalStore — open/close modal with associated data.
 */

import { useConfigModalStore } from '../useConfigModalStore';
import type { ConfigModalData } from '../../types/settings';

describe('useConfigModalStore', () => {
  beforeEach(() => {
    useConfigModalStore.setState({ configModalOpen: false, configModalData: null });
  });

  describe('initial state', () => {
    it('should be closed with no data', () => {
      const s = useConfigModalStore.getState();
      expect(s.configModalOpen).toBe(false);
      expect(s.configModalData).toBeNull();
    });
  });

  describe('openConfigModal', () => {
    it('should set open to true and store data', () => {
      const data = { nodeId: 'n1' } as ConfigModalData;
      useConfigModalStore.getState().openConfigModal(data);

      const s = useConfigModalStore.getState();
      expect(s.configModalOpen).toBe(true);
      expect(s.configModalData).toEqual(data);
    });
  });

  describe('closeConfigModal', () => {
    it('should set open to false and clear data', () => {
      useConfigModalStore.setState({
        configModalOpen: true,
        configModalData: { nodeId: 'n1' } as ConfigModalData,
      });

      useConfigModalStore.getState().closeConfigModal();

      const s = useConfigModalStore.getState();
      expect(s.configModalOpen).toBe(false);
      expect(s.configModalData).toBeNull();
    });
  });
});
