import React, { useEffect } from 'react';
import type { Decorator } from '@storybook/react';
import { useGraphStore } from '../../src/store/useGraphStore';
import { useSelectionStore } from '../../src/store/useSelectionStore';
import { useFilterStore } from '../../src/store/useFilterStore';
import { useComponentStore } from '../../src/store/useComponentStore';
import type { StoreNode, StoreEdge, StoreComponent } from '../../src/types/settings';

// Default mock state for stories
interface MockState {
  nodes?: StoreNode[];
  edges?: StoreEdge[];
  selectedNode?: StoreNode | null;
  selectedEdge?: StoreEdge | null;
  visibleStartNodeIds?: string[] | null;
  components?: StoreComponent[];
}

interface StoreDecoratorOptions {
  initialState?: MockState;
}

/**
 * Decorator that provides mock Zustand store state for components.
 * Usage in stories:
 *
 * export const MyStory: Story = {
 *   decorators: [withStore({ initialState: { nodes: [...] } })],
 * };
 */
export const withStore = (options: StoreDecoratorOptions = {}): Decorator => {
  return (Story) => {
    // Reset stores to initial state before each story
    useEffect(() => {
      // Apply mock state to domain stores
      if (options.initialState?.nodes) {
        useGraphStore.getState().setNodes(options.initialState.nodes);
      }
      if (options.initialState?.edges) {
        useGraphStore.getState().setEdges(options.initialState.edges);
      }
      if (options.initialState?.selectedNode !== undefined) {
        useSelectionStore.getState().setSelectedNode(options.initialState.selectedNode);
      }
      if (options.initialState?.selectedEdge !== undefined) {
        useSelectionStore.getState().setSelectedEdge(options.initialState.selectedEdge);
      }
      if (options.initialState?.visibleStartNodeIds !== undefined) {
        useFilterStore.getState().setVisibleStartNodeIds(options.initialState.visibleStartNodeIds);
      }
      if (options.initialState?.components) {
        useComponentStore.getState().setComponents(options.initialState.components);
      }
    }, []);

    return <Story />;
  };
};

export default withStore;
