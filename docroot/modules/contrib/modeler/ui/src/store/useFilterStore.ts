import { create } from 'zustand';

interface FilterState {
  visibleStartNodeIds: string[] | null;
  setVisibleStartNodeIds: (ids: string[] | null) => void;
}

export const useFilterStore = create<FilterState>((set) => ({
  visibleStartNodeIds: null,
  setVisibleStartNodeIds: (ids) => set({ visibleStartNodeIds: ids }),
}));
