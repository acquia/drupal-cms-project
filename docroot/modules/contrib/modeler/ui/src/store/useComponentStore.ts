import { create } from 'zustand';
import type { StoreComponent } from '../types/settings';

type FavoriteComponents = Record<number, string[]>;

interface ComponentState {
  components: StoreComponent[];
  setComponents: (components: StoreComponent[]) => void;
  favoriteComponents: FavoriteComponents;
  setFavoriteComponents: (favorites: FavoriteComponents) => void;
}

export const useComponentStore = create<ComponentState>((set) => ({
  components: [],
  setComponents: (components) => set({ components }),

  favoriteComponents: {},
  setFavoriteComponents: (favorites) => set({ favoriteComponents: favorites }),
}));
