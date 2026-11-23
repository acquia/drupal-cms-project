import { create } from 'zustand';
import type { ModelerContext, ModelerDependencies } from '../types/settings';

interface ContextState {
  contexts: ModelerContext[];
  setContexts: (contexts: ModelerContext[]) => void;
  dependencies: ModelerDependencies;
  setDependencies: (dependencies: ModelerDependencies) => void;
  selectedContextId: string | null;
  setSelectedContextId: (id: string | null) => void;
  contextConfig: Record<string, string>;
  setContextConfig: (config: Record<string, string>) => void;
}

export const useContextStore = create<ContextState>((set) => ({
  contexts: [],
  setContexts: (contexts) => set({ contexts }),

  dependencies: {},
  setDependencies: (dependencies) => set({ dependencies }),

  selectedContextId: null,
  setSelectedContextId: (id) => set({ selectedContextId: id }),

  contextConfig: {},
  setContextConfig: (config) => set({ contextConfig: config }),
}));
