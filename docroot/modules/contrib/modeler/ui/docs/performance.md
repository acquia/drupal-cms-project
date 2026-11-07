# Performance Optimization

Performant code patterns that scale to large workflows without memory leaks or excessive re-renders.

## Core Performance Patterns

### Store Selectors
```typescript
// ✅ CORRECT: Individual selectors prevent re-renders
const selectedNode = useSelectionStore(state => state.selectedNode);
const edges = useGraphStore(state => state.edges);

// ❌ WRONG: Destructuring causes cascade re-renders
const { selectedNode } = useSelectionStore();
```

### Effect Dependencies
```typescript
// ✅ CORRECT: Minimal dependencies, uses closure values
const handleCopy = useCallback(() => {
  const currentSelected = nodes.filter(n => n.selected);
  copyElements(currentSelected);
}, []); // No dependencies - uses current values

// ❌ WRONG: Recreates on every change
const handleCopy = useCallback(() => {
  copyElements(selectedNodes);
}, [selectedNodes, nodes, edges]);
```

### Direct Functions
```typescript
// ✅ CORRECT: Direct function uses latest values
const getModelData = () => {
  return exportModelData(nodes, edges, metadata);
};

// ❌ WRONG: Unnecessary memoization
const getModelData = useCallback(() => {
  return exportModelData(nodes, edges, metadata);
}, [nodes, edges, metadata]);
```

## Memory Management

### Timer Cleanup
```typescript
useEffect(() => {
  const intervalId = setInterval(() => {
    // Some periodic operation
  }, 1000);

  return () => clearInterval(intervalId); // Always cleanup
}, []);
```

### AbortController Pattern
```typescript
const fetchConfiguration = async (componentId: string) => {
  const controller = new AbortController();
  
  try {
    const response = await fetch(`/config/${componentId}`, {
      signal: controller.signal
    });
    return await response.json();
  } catch (error) {
    if (error.name !== 'AbortError') {
      console.error('Fetch failed:', error);
    }
  }
};

// Cleanup on unmount or new request
useEffect(() => {
  return () => controller.abort();
}, [componentId]);
```

### CSS-Based Optimization
```typescript
// ✅ CORRECT: CSS classes for state-based styling
<div className={`edge ${isHighlighted ? 'highlighted' : ''}`} />

// ❌ WRONG: JavaScript style updates
<div style={{ 
  borderColor: isHighlighted ? '#10b981' : '#8b8b8b' 
}} />
```

## Viewport Management

### Unified Viewport Actions

All programmatic pan/zoom operations go through `useViewportActions`
(see `docs/viewport-management.md` for the full reference).

```typescript
// ✅ CORRECT: Use useViewportActions methods
const viewportActions = useViewportActions();
viewportActions.focusNode(nodeId);       // pan, preserve zoom
viewportActions.panToNodeIfOffscreen(id); // pan only if off-screen
viewportActions.fitToNodes();             // fit all visible nodes

// ❌ WRONG: Direct ReactFlow calls bypass zoom preservation
reactFlowInstance.setCenter(x, y);  // defaults to maxZoom (4x)!
reactFlowInstance.fitView();         // no maxZoom cap
```

## Input Debouncing

### Search Input Optimization
```typescript
// ✅ CORRECT: Debounced search
const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

const handleSearchChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
  setSearchTerm(e.target.value); // Immediate visual feedback

  if (debounceRef.current) {
    clearTimeout(debounceRef.current);
  }

  debounceRef.current = setTimeout(() => {
    performSearch(e.target.value); // Debounced operation
  }, TIMING.SEARCH_DEBOUNCE);
}, [performSearch]);

// ❌ WRONG: Immediate search on every keystroke
const handleSearchChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
  setSearchTerm(e.target.value);
  performSearch(e.target.value); // Fires immediately
}, [performSearch]);
```

## Component Optimization

### React.memo for Components
```typescript
// ✅ CORRECT: Memoize expensive components
const ExpensiveComponent = React.memo(({ data, onUpdate }) => {
  const processedData = useMemo(() => 
    expensiveProcessing(data), [data]
  );
  
  return <div>{processedData}</div>;
});

// ❌ WRONG: Unnecessary re-renders
const ExpensiveComponent = ({ data, onUpdate }) => {
  const processedData = expensiveProcessing(data); // Recalculates every render
  return <div>{processedData}</div>;
};
```

### useCallback for Event Handlers
```typescript
// ✅ CORRECT: Stable function reference
const handleClick = useCallback((id: string) => {
  onItemSelect(id);
}, [onItemSelect]);

// ❌ WRONG: New function on every render
const handleClick = (id: string) => {
  onItemSelect(id);
};
```

## Data Structure Optimization

### Efficient Lookups
```typescript
// ✅ CORRECT: Use Maps for frequent lookups
const nodeMap = useMemo(() => 
  new Map(nodes.map(n => [n.id, n])), [nodes]
);

const getNode = (id: string) => nodeMap.get(id);

// ❌ WRONG: Linear search
const getNode = (id: string) => nodes.find(n => n.id === id);
```

### Virtualization for Large Lists
```typescript
// ✅ CORRECT: Virtualize long lists
const VirtualizedList = ({ items }) => {
  return (
    <FixedSizeList
      height={400}
      itemCount={items.length}
      itemSize={35}
      itemData={items}
    >
      {({ index, style, data }) => (
        <div style={style}>{data[index]}</div>
      )}
    </FixedSizeList>
  );
};
```

## Performance Monitoring

### Built-in React Profiler Integration

The modeler includes built-in `<Profiler>` wrappers around all performance-critical
components.  A shared `onRenderCallback` in `utils/profiling.ts` logs a console
warning whenever a component render exceeds the slow-render threshold (default:
16 ms — one frame at 60 fps).

React strips Profiler callbacks from production builds, so there is **zero
runtime cost** in production.

#### Profiled Components

| Tier | Component | ID |
|------|-----------|-----|
| 1 — Canvas | FlowCanvas | `FlowCanvas` |
| 1 — Nodes | CustomNode, StartNode, GatewayNode, SubprocessNode, ConditionNode | `CustomNode`, `StartNode`, `GatewayNode`, `SubprocessNode`, `ConditionNode` |
| 1 — Edges | DefaultEdge | `DefaultEdge` |
| 2 — Panels | ReplayPanel, PropertyPanel | `ReplayPanel`, `PropertyPanel` |
| 2 — Overlays | SearchBar, QuickAddPopup | `SearchBar`, `QuickAddPopup` |
| 3 — Chrome | Toolbar, Flow (orchestrator) | `Toolbar`, `Flow` |

#### Reading the Output

Open the browser console and interact with the modeler.  Slow renders appear as
warnings:

```
[Profiler] Slow render: "FlowCanvas" (update) took 24.3ms (base 18.1ms, commit 12345–start 12320)
```

- **id** — the component name from the table above
- **phase** — `mount` (first render) or `update` (re-render)
- **actualDuration** — wall-clock time for the render
- **baseDuration** — estimated time without memoization (useful for gauging
  `React.memo` / `useMemo` effectiveness)

#### Adjusting the Threshold

Edit `PROFILER_SLOW_THRESHOLD_MS` in `utils/profiling.ts` to change when
warnings fire.  A lower value surfaces more renders; a higher value reduces
noise.

#### Using with React DevTools

The Profiler wrappers are fully compatible with the React DevTools Profiler
tab.  Record a profiling session in DevTools and the component IDs from the
table above will appear in the flame chart, making it easy to correlate
console warnings with the visual profiling data.

### Memory Usage Tracking
```typescript
// Monitor memory in development
if (process.env.NODE_ENV === 'development') {
  setInterval(() => {
    const memory = performance.memory;
    console.log('Memory:', {
      used: Math.round(memory.usedJSHeapSize / 1024 / 1024) + ' MB',
      total: Math.round(memory.totalJSHeapSize / 1024 / 1024) + ' MB'
    });
  }, 10000);
}
```

## Node Positioning Performance

### Collision Detection
The `isOverlapping()` function uses AABB (axis-aligned bounding box) collision testing — O(n) per call, where n is the number of existing nodes. This is efficient for typical workflow sizes (< 200 nodes).

### Flow Detection
`getConnectedComponent()` uses BFS with an adjacency list built from edges — O(V + E) per call. `getAllFlows()` partitions all nodes into components in a single pass.

### Atomic State Updates
Flow-aware positioning computes all shift amounts before modifying state. The shift is applied in a single `setNodes` call alongside the new node addition, avoiding multiple React re-renders:

```typescript
// ✅ CORRECT: Single atomic update for node add + flow shift
setNodes(prev => {
  const updated = prev.map(n => {
    // Shift neighboring flow nodes in the same pass
    if (shiftAmount > 0 && shiftNodeIds.has(n.id)) {
      return { ...n, position: { x: n.position.x + shiftAmount, y: n.position.y } };
    }
    return n;
  });
  return [...updated, newNode];
});

// ❌ WRONG: Separate updates cause two re-renders
setNodes(prev => [...prev, newNode]);          // Re-render 1
setNodes(prev => prev.map(n => /* shift */));  // Re-render 2
```

### Safety Bounds
All search loops are bounded by `MAX_ATTEMPTS = 50` to prevent runaway iteration on pathological layouts.

## Common Performance Issues

### Continuous Intervals
```typescript
// ❌ WRONG: Causes memory leak
useEffect(() => {
  const interval = setInterval(() => {
    setEdges(prevEdges => updateEdgeColors(prevEdges));
  }, 50); // Runs forever

  return () => clearInterval(interval);
}, [currentStep]); // Restarted on every step change
```

### Excessive Re-renders
```typescript
// ❌ WRONG: Causes cascade updates
const MyComponent = () => {
  const store = useGraphStore(); // Subscribes to ALL changes
  
  // Any store change re-renders this component
  return <div>{store.nodes.length} nodes</div>;
};
```

### Large Object Comparisons
```typescript
// ❌ WRONG: Expensive deep comparison
useEffect(() => {
  if (JSON.stringify(prevNodes) !== JSON.stringify(nodes)) {
    // Process nodes
  }
}, [nodes]);

// ✅ CORRECT: Use specific tracked values
useEffect(() => {
  if (prevNodes.length !== nodes.length) {
    // Process nodes
  }
}, [nodes.length]);
```

## Performance Tools

### Build Commands
```bash
# Development with performance monitoring
npm run dev

# Production build (optimized)
npm run build:production

# Test coverage
npm run test:coverage
```

### Browser DevTools
- **React DevTools**: Profile component renders
- **Performance Tab**: Record runtime performance
- **Memory Tab**: Monitor heap usage and leaks

## Performance Guidelines

1. **Always use individual store selectors**
2. **Minimize effect dependencies**
3. **Clean up timers, listeners, and AbortControllers**
4. **Prefer CSS classes over JavaScript styling**
5. **Use useMemo for expensive calculations**
6. **Implement proper debouncing for user input**
7. **Monitor memory usage in development**
8. **Profile components before optimization**
9. **Test with realistic data sizes**
10. **Use virtualization for large lists**

Following these patterns ensures the modeler performs well even with workflows containing hundreds of nodes and edges.