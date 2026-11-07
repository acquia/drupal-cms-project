import { create } from 'zustand';
import type { ModelData } from '../types/settings';

interface ModelState {
  modelData: ModelData | null;
  setModelData: (data: ModelData | null | ((prev: ModelData | null) => ModelData | null)) => void;
}

export const useModelStore = create<ModelState>((set) => ({
  modelData: null,
  setModelData: (data) =>
    set((state) => ({
      modelData: typeof data === 'function' ? data(state.modelData) : data,
    })),
}));
