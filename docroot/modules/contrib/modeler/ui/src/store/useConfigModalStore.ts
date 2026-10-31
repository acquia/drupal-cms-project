import { create } from 'zustand';
import type { ConfigModalData } from '../types/settings';

interface ConfigModalState {
  configModalOpen: boolean;
  configModalData: ConfigModalData | null;
  openConfigModal: (data: ConfigModalData) => void;
  closeConfigModal: () => void;
}

export const useConfigModalStore = create<ConfigModalState>((set) => ({
  configModalOpen: false,
  configModalData: null,
  openConfigModal: (data) => set({ configModalOpen: true, configModalData: data }),
  closeConfigModal: () => set({ configModalOpen: false, configModalData: null }),
}));
