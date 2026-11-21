# State Management

Zustand store patterns for managing application state.

## Core Principles

### Single Source of Truth
- **Store Only**: Never use React Flow's internal state (`useNodesState`, `useEdgesState`)
- **Domain-Specific Stores**: Application state is split across domain-specific Zustand stores in `store/use*Store.ts`
- **No Dual State**: Eliminate synchronization between multiple state sources

### Selector Pattern
```typescript
// ✅ CORRECT: Individual selectors from domain-specific stores
const nodes = useGraphStore(state => state.nodes);
const selectedNode = useSelectionStore(state => state.selectedNode);
const setNodes = useGraphStore(state => state.setNodes);

// ❌ WRONG: Store destructuring
const { nodes, selectedNode, setNodes } = useGraphStore();
```

## Store Architecture

The monolithic store has been split into domain-specific stores. Import each store directly from its own file:

| Store | File | Responsibility |
|-------|------|----------------|
| `useGraphStore` | `store/useGraphStore.ts` | Nodes, edges, and graph mutations |
| `useSelectionStore` | `store/useSelectionStore.ts` | Selected node/edge (single + multi) |
| `useHistoryStore` | `store/useHistoryStore.ts` | Undo/redo with snapshot stack (max 50) |
| `usePanelStore` | `store/usePanelStore.ts` | Panel collapse state and persistence |
| `useUISettingsStore` | `store/useUISettingsStore.ts` | Dark mode, token dragging, UI flags |
| `useComponentStore` | `store/useComponentStore.ts` | Components and favorites |
| `useContextStore` | `store/useContextStore.ts` | Contexts, selectedContextId, dependencies |
| `useFilterStore` | `store/useFilterStore.ts` | visibleStartNodeIds for flow filtering |
| `useLabelStore` | `store/useLabelStore.ts` | Component labels |
| `useModelStore` | `store/useModelStore.ts` | Model data and metadata |
| `useErrorStore` | `store/useErrorStore.ts` | Error log |
| ~~useViewportStore~~ | *(removed)* | Replaced by `useViewportActions` hook — see `docs/viewport-management.md` |
| `useConfigModalStore` | `store/useConfigModalStore.ts` | Modal state |

### Key Store Interfaces

```typescript
// useGraphStore
interface GraphState {
  nodes: Node[];
  edges: Edge[];
  setNodes: (nodes: Node[] | ((nodes: Node[]) => Node[])) => void;
  setEdges: (edges: Edge[] | ((edges: Edge[]) => Edge[])) => void;
  updateNode: (nodeId: string, updates: Partial<Node>) => void;
}

// useSelectionStore
interface SelectionState {
  selectedNode: Node | null;
  selectedEdge: Edge | null;
  selectedNodes: string[];
  selectedEdges: string[];
  setSelectedNode: (node: Node | null) => void;
  setSelectedEdge: (edge: Edge | null) => void;
  setSelectedNodes: (ids: string[]) => void;
  setSelectedEdges: (ids: string[]) => void;
}

// useHistoryStore
interface HistoryState {
  canUndo: boolean;
  canRedo: boolean;
  pushHistory: (snapshot: HistorySnapshot) => void;
  undo: () => HistorySnapshot | undefined;
  redo: () => HistorySnapshot | undefined;
  clearHistory: () => void;
}
```

### Selection Architecture

ReactFlow is the **sole selection authority**. The selection flow:

```
User action (click/quick-add/drag-drop)
  → Hook sets `selected: true/false` on node/edge objects via setNodes/setEdges
  → ReactFlow detects `.selected` changes on elements
  → ReactFlow fires `onSelectionChange({ nodes, edges })`
  → useFlowEventHandlers handler writes to store:
      setSelectedNode / setSelectedEdge / setSelectedNodes / setSelectedEdges
  → PropertyPanel receives selection from store
  → useConfigurationLoader fetches config form for the selected element
```

**Key rules:**
- Hooks that add elements (useQuickAdd — both `addSuccessorNode` and `addConditionWithPlaceholder`, useNodeEdgeActions, useDragAndDrop) must
  set `selected: true` on the new element and `selected: false` on all others
  via `setNodes`/`setEdges` updater functions. They must NOT call `setSelectedNode`
  or `setSelectedEdge` directly -- this avoids race conditions with ReactFlow's
  `onSelectionChange` handler.
- `useFlowEventHandlers.ts` defines the canonical `onSelectionChange` handler
  that bridges ReactFlow's internal selection to the Zustand store. It uses two
  guards:
  - `paneClickedRef`: prevents a stale-selection race when the user clicks the
    empty canvas — `onPaneClick` fires first and clears the store, but ReactFlow
    may then fire `onSelectionChange` with the previously selected node still
    present. The guard ignores non-empty events until the confirming empty event.
  - `isReplaySyncingRef`: during replay-to-canvas sync (clicking a step in the
    replay panel), programmatic `setNodes`/`setEdges` updates cause ReactFlow to
    fire `onSelectionChange` with stale data (e.g., previously selected node AND
    newly selected edge). The guard skips all `onSelectionChange` events while
    replay-to-canvas sync is in progress. The selection store is populated
    directly by `useSimpleReplaySync` in this case.
- **Exception — replay sync**: `useSimpleReplaySync.selectCanvasFromReplay` calls
  `setSelectedNode`/`setSelectedEdge` directly (bypassing the normal ReactFlow
  → `onSelectionChange` flow) because `isReplaySyncingRef` blocks the canonical
  handler during this operation. This is the only place where direct selection
  store writes are permitted outside `onSelectionChange`.
- `useSelectionSync.ts` keeps `selectedNode`/`selectedEdge` object references
  fresh when `nodes`/`edges` arrays update (e.g., after label edits).
- `useConfigurationLoader.ts` uses stable primitive identifiers
  (`node.id + node.data.plugin`) in its effect dependencies to avoid
  spurious re-triggers from object reference changes.

## Modal State Management

### useModalState Hook
The `useModalState` hook (`src/hooks/useModalState.ts`) manages metadata modal and confirmation dialog state. Its `showConfirmationDialog` method supports an optional 6th `options` bag for button customization:

```typescript
// Original 5 positional args + optional options bag
showConfirmationDialog(
  title: string,
  message: string,
  type: 'danger' | 'warning' | 'info',
  onSaveAndCloseCallback?: () => void,
  onCloseWithoutSaveCallback?: () => void,
  options?: {
    primaryLabel?: string;           // Custom primary button text
    secondaryLabel?: string | false; // Custom text or `false` to hide
    cancelLabel?: string;            // Custom cancel button text
    primaryVariant?: 'primary' | 'danger'; // Button styling variant
  }
);

// Example: 2-button danger dialog for bulk delete
showConfirmationDialog(
  'Delete Selected?',
  'This will permanently delete the selected elements.',
  'danger',
  handleDeleteSelected,
  undefined,
  { primaryLabel: 'Delete', secondaryLabel: false, cancelLabel: 'Cancel', primaryVariant: 'danger' }
);
```

The hook exposes the customization state as `confirmDialogPrimaryLabel`, `confirmDialogSecondaryLabel`, `confirmDialogCancelLabel`, and `confirmDialogPrimaryVariant`, which `Flow.tsx` passes through `Modals` to `ConfirmDialog`.

**Key design choice**: `secondaryLabel: false` (not `undefined`) is the sentinel to hide the secondary button, because `undefined` would be replaced by the ConfirmDialog component's destructuring default.

## Common Agent Tasks

### Reading State
```typescript
// Get current state from domain-specific stores
const currentNodes = useGraphStore(state => state.nodes);
const isDarkMode = useUISettingsStore(state => state.darkMode);

// Derived state
const selectedNodeIds = useGraphStore(state => 
  state.nodes.filter(n => n.selected).map(n => n.id)
);
```

### Updating State
```typescript
// Simple updates
const setNodes = useGraphStore(state => state.setNodes);
setNodes(prevNodes => prevNodes.filter(n => n.id !== nodeId));

// Complex updates
const updateNode = useGraphStore(state => state.updateNode);
updateNode(nodeId, { label: 'New Label' });
```

### Batch Operations
```typescript
// Atomic updates — use selectors from the appropriate stores
const setNodes = useGraphStore(s => s.setNodes);
const setEdges = useGraphStore(s => s.setEdges);
const setSelectedNode = useSelectionStore(s => s.setSelectedNode);

// Use together for atomic changes
setNodes(newNodes);
setEdges(newEdges);
setSelectedNode(null);
```

## Performance Patterns

### Avoid Re-renders
```typescript
// ✅ CORRECT: Specific selector from domain store
const nodeCount = useGraphStore(state => state.nodes.length);

// ❌ WRONG: Causes re-renders
const { nodes } = useGraphStore();
const nodeCount = nodes.length;
```

### Effect Dependencies
```typescript
// ✅ CORRECT: Minimal dependencies
useEffect(() => {
  // Side effect using current values
  console.log('Current count:', nodes.length);
}, [selectedNodeId]); // Only depends on selection

// ❌ WRONG: Expensive re-renders
useEffect(() => {
  console.log('Current count:', nodes.length);
}, [nodes]); // Fires on every node change
```

## File Locations

### Core Store Files
- **Store Definitions**: `src/store/use*Store.ts` (one file per domain store)
- **Shared Types**: `src/types/settings.ts`
- **Constants**: `src/constants/dimensions.ts`

### Hook Integration
- **State Hooks**: `src/hooks/use*.ts` (35 specialized hooks)
- **Component Usage**: Individual selectors in all components

## Testing State Management

### Unit Tests
```typescript
// Test store operations
test('should add node', () => {
  const initialState = useGraphStore.getState();
  
  useGraphStore.getState().setNodes(prev => [...prev, mockNode]);
  
  const newState = useGraphStore.getState();
  expect(newState.nodes).toHaveLength(initialState.nodes.length + 1);
});
```

### Integration Tests
```typescript
// Test component-store integration
test('component updates store correctly', () => {
  const { rerender } = render(<MyComponent />);
  
  act(() => {
    fireEvent.click(screen.getByTestId('add-node'));
  });
  
  const storeState = useGraphStore.getState();
  expect(storeState.nodes).toHaveLength(1);
});
```

## Panel Collapse Setters

There are two ways to collapse/expand the property panel:

- **`togglePropertyPanelCollapse()`** -- Toggles and persists to `localStorage`. Used by the UI collapse widget so the user's preference survives page reloads.
- **`setPropertyPanelCollapsed(collapsed: boolean)`** -- Direct setter with no `localStorage` side effect. Used by the `collapsePanels` feature in `Flow.tsx` for programmatic control (initial collapse on mount, auto-expand/collapse on selection changes).

The replay panel has a similar `set*Collapsed` / `toggle*Collapse` pair. Both panels follow the same convention.

## Common Issues to Avoid

### Store Destructuring
Never destructure the store - this creates subscriptions to all properties.

### Direct Mutation
Never mutate state directly - always use store methods.

### Circular Dependencies
Avoid hooks that depend on each other in a circular manner.

### Race Conditions
Use proper cleanup for async operations that update store.

## Advanced Patterns

### Store Computed Values
```typescript
// Computed selector from domain store
const selectedNodeIds = useGraphStore(state => 
  state.nodes.filter(n => n.selected).map(n => n.id)
);
```

### Store Actions
```typescript
// Complex actions using domain stores
const deleteNodeWithEdges = (nodeId: string) => {
  useGraphStore.setState(state => ({
    nodes: state.nodes.filter(n => n.id !== nodeId),
    edges: state.edges.filter(e => e.source !== nodeId && e.target !== nodeId)
  }));
};
```

