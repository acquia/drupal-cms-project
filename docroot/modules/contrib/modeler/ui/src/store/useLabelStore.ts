import { create } from 'zustand';
import type { ComponentLabels } from '../types/settings';

interface LabelState {
  componentLabels: ComponentLabels;
  setComponentLabels: (labels: ComponentLabels) => void;
}

export const useLabelStore = create<LabelState>((set) => ({
  componentLabels: {},
  setComponentLabels: (labels) => set({ componentLabels: labels }),
}));
