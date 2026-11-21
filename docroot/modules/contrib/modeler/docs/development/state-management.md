# State Management

The modeler uses [Zustand](https://github.com/pmndrs/zustand) for state
management. For the complete reference, see `ui/docs/state-management.md`.

## Core principles

### Single source of truth

Application state is split across domain-specific Zustand stores in `store/use*Store.ts`
(e.g. `useGraphStore`, `useSelectionStore`, `usePanelStore`). Import each store
directly from its own file. ReactFlow is the sole authority for node and edge
data -- `useGraphStore` holds the ReactFlow instance's state.

### Individual selectors

Always use individual selectors, never destructure the store:

```typescript
// Correct — use the specific domain store
const nodes = useGraphStore(state => state.nodes);
const selectedNode = useSelectionStore(state => state.selectedNode);

// Wrong -- causes unnecessary re-renders
const { nodes } = useGraphStore();
```

### Effect dependencies

Never put `nodes` or `edges` arrays in `useEffect` dependency arrays -- they
change on every render. Use specific values instead:

```typescript
// Correct
const nodeCount = useGraphStore(state => state.nodes.length);
useEffect(() => { /* ... */ }, [nodeCount]);

// Wrong -- fires on every render
const nodes = useGraphStore(state => state.nodes);
useEffect(() => { /* ... */ }, [nodes]);
```

## Store structure

The store manages:

- **Model data**: Nodes, edges, metadata, and dirty state.
- **Selection**: Currently selected node(s) and edge(s).
- **UI state**: Dark mode, search, replay mode, panel visibility.
- **Modal state**: Which dialogs are open and their parameters.
- **Context state**: `contexts`, `selectedContextId`, `contextConfig`, and `dependencies` for component filtering.
- **Flow filtering**: `visibleStartNodeIds` for showing/hiding individual event flows.
- **Component labels**: `componentLabels` for model-owner-specific terminology.
- **Error log**: `errorLog` for tracking runtime errors.

## Modal state

Modals use the `useModalState` hook with a `showConfirmationDialog` method
that accepts an options bag for customizing button labels and variants:

```typescript
const { showConfirmationDialog } = useModalState();

showConfirmationDialog(
  'Confirm deletion',
  'Are you sure you want to delete all selected items?',
  onConfirm,
  onCancel,
  onSecondary,
  { primaryLabel: 'Delete', primaryVariant: 'danger', secondaryLabel: false }
);
```

See `ui/docs/state-management.md` for advanced patterns and common pitfalls.
