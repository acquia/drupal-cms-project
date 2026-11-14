# Architecture Patterns

Enterprise-grade architectural patterns for maintainable codebases.

## Component Architecture

### Orchestrator Pattern
```typescript
// Main orchestrator coordinates specialized components
const Flow: React.FC<FlowProps> = ({ settings, drupal }) => {
  // Central state from domain-specific stores - individual selectors
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const selectedNode = useSelectionStore(state => state.selectedNode);
  const selectedEdge = useSelectionStore(state => state.selectedEdge);
  
  // Initialize all specialized hooks
  const { handleCopy, handlePaste } = useClipboard({
    nodes,
    edges,
    selectedNode,
    selectedEdge
  });
  
  // Unified viewport actions — see docs/viewport-management.md
  const viewportActions = useViewportActions();

  return (
    <div className="workflow-modeler">
      <Toolbar 
        onCopy={handleCopy}
        onPaste={handlePaste}
        onZoomIn={zoomIn}
        onZoomOut={zoomOut}
        selectedElement={selectedNode || selectedEdge}
      />
      <PanelErrorBoundary panelName="Canvas">
        <FlowCanvas
          nodes={nodes}
          edges={edges}
          onNodesChange={useGraphStore(state => state.applyNodeChanges)}
          onEdgesChange={useGraphStore(state => state.applyEdgeChanges)}
          selectedNode={selectedNode}
          selectedEdge={selectedEdge}
        />
      </PanelErrorBoundary>
      <PanelErrorBoundary panelName="Properties">
        <PropertyPanel
          node={selectedNode}
          edge={selectedEdge}
        />
      </PanelErrorBoundary>
      <PanelErrorBoundary panelName="Replay">
        <ReplayPanel />
      </PanelErrorBoundary>
    </div>
  );
};
```

### Single Responsibility Principle
```typescript
// Each hook has one clear purpose
const useClipboard = ({ nodes, edges, selectedNode, selectedEdge }: ClipboardConfig) => {
  // Only handles copy/paste functionality
  const copyToClipboard = useCallback(() => {
    const selectedElements = getSelectedElements(nodes, edges, selectedNode, selectedEdge);
    const clipboardData = serializeElements(selectedElements);
    setClipboardData(clipboardData);
    
    // Screen reader announcement
    announceToScreenReader(`Copied ${selectedElements.length} elements`);
  }, [nodes, edges, selectedNode, selectedEdge]);

  const pasteFromClipboard = useCallback(async () => {
    const clipboardData = await getClipboardData();
    if (!clipboardData) return;
    
    const { nodes: pastedNodes, edges: pastedEdges } = deserializeElements(clipboardData);
    const { positionedNodes, positionedEdges } = calculatePastePositions(
      pastedNodes, 
      pastedEdges, 
      getCurrentViewport()
    );
    
    addNodes(positionedNodes);
    addEdges(positionedEdges);
    
    announceToScreenReader(`Pasted ${pastedNodes.length} elements`);
  }, []);

  return {
    copyToClipboard,
    pasteFromClipboard,
    canCopy: hasSelectedElements(selectedNode, selectedEdge),
    canPaste: await hasClipboardData()
  };
};
```

### Dependency Inversion
```typescript
// Abstract external dependencies for testability
interface ClipboardService {
  copy(data: ClipboardData): Promise<void>;
  paste(): Promise<ClipboardData | null>;
  hasData(): Promise<boolean>;
}

interface AnnouncementService {
  announce(message: string): void;
}

// Default implementation for production
class DefaultClipboardService implements ClipboardService {
  async copy(data: ClipboardData): Promise<void> {
    const encrypted = await encryptClipboardData(data);
    localStorage.setItem('workflow-clipboard', encrypted);
  }
  
  async paste(): Promise<ClipboardData | null> {
    const encrypted = localStorage.getItem('workflow-clipboard');
    if (!encrypted) return null;
    
    return await decryptClipboardData(encrypted);
  }
  
  async hasData(): Promise<boolean> {
    return !!localStorage.getItem('workflow-clipboard');
  }
}

// Hook uses injected dependencies
const useClipboard = (
  config: ClipboardConfig,
  dependencies: {
    clipboardService: ClipboardService;
    announcementService: AnnouncementService;
  }
) => {
  const { clipboardService, announcementService } = dependencies;
  
  const copyToClipboard = useCallback(async () => {
    await clipboardService.copy(clipboardData);
    announcementService.announce(`Copied ${elements.length} elements`);
  }, [clipboardService, announcementService]);

  return { copyToClipboard, /* ... */ };
};
```

## State Management Patterns

### Command Pattern for State Updates
```typescript
// Encapsulate complex state changes
class ModelCommands {
  static addNode(store: StoreState, node: Node): void {
    store.setNodes(prevNodes => [...prevNodes, node]);
    store.setSelectedNode(node);
  }
  
  static deleteNode(store: StoreState, nodeId: string): void {
    const nodeEdges = store.edges.filter(e => 
      e.source === nodeId || e.target === nodeId
    );
    
    store.setEdges(prevEdges => 
      prevEdges.filter(e => !nodeEdges.includes(e))
    );
    
    store.setNodes(prevNodes => 
      prevNodes.filter(n => n.id !== nodeId)
    );
    
    if (store.selectedNode?.id === nodeId) {
      store.setSelectedNode(null);
    }
  }
  
  static updateNode(store: StoreState, nodeId: string, updates: Partial<Node>): void {
    store.setNodes(prevNodes => 
      prevNodes.map(n => n.id === nodeId ? { ...n, ...updates } : n)
    );
    
    if (store.selectedNode?.id === nodeId) {
      store.setSelectedNode(prev => prev ? { ...prev, ...updates } : null);
    }
  }
}
```

### Event Sourcing Pattern
```typescript
// Track all state changes for undo/redo
interface StateEvent {
  id: string;
  type: 'nodes_added' | 'nodes_removed' | 'nodes_updated' | 'edges_added' | 'edges_removed' | 'edges_updated';
  timestamp: number;
  data: unknown;
  inverse?: StateEvent; // For undo operations
}

const useEventSourcing = () => {
  const [events, setEvents] = useState<StateEvent[]>([]);
  const [currentStateIndex, setCurrentStateIndex] = useState(-1);
  
  const addEvent = useCallback((event: StateEvent) => {
    setEvents(prev => [...prev.slice(0, currentStateIndex + 1), {
      ...event,
      id: generateId(),
      timestamp: Date.now()
    }]);
    setCurrentStateIndex(prev => prev + 1);
  }, [currentStateIndex]);

  const undo = useCallback(() => {
    if (currentStateIndex <= 0) return;
    
    setCurrentStateIndex(prev => prev - 1);
    // Apply events up to new index
    applyEvents(events.slice(0, currentStateIndex));
  }, [events, currentStateIndex]);

  const redo = useCallback(() => {
    if (currentStateIndex >= events.length - 1) return;
    
    setCurrentStateIndex(prev => prev + 1);
    // Apply events up to new index
    applyEvents(events.slice(0, currentStateIndex + 2));
  }, [events, currentStateIndex]);

  return { addEvent, undo, redo, canUndo: currentStateIndex > 0, canRedo: currentStateIndex < events.length - 1 };
};
```

## Performance Patterns

### Memoization Strategies
```typescript
// Memoize expensive calculations
const useWorkflowMetrics = (nodes: Node[], edges: Edge[]) => {
  const metrics = useMemo(() => {
    const nodeCount = nodes.length;
    const edgeCount = edges.length;
    
    // Calculate workflow complexity
    const sourceNodes = new Set(edges.map(e => e.source));
    const targetNodes = new Set(edges.map(e => e.target));
    const connectedNodes = new Set([...sourceNodes, ...targetNodes]);
    
    const isolatedNodes = nodes.filter(n => !connectedNodes.has(n.id));
    
    // Calculate cycles
    const hasCycles = detectCycles(nodes, edges);
    
    return {
      nodeCount,
      edgeCount,
      isolatedNodeCount: isolatedNodes.length,
      connectedNodeCount: connectedNodes.size,
      hasCycles,
      complexity: calculateComplexity(nodes, edges)
    };
  }, [nodes, edges]);

  return metrics;
};

// Memoize rendering of large lists
const VirtualizedNodeList: React.FC<{ nodes: Node[] }> = ({ nodes }) => {
  const [visibleRange, setVisibleRange] = useState({ start: 0, end: 50 });

  const visibleNodes = useMemo(() => {
    return nodes.slice(visibleRange.start, visibleRange.end);
  }, [nodes, visibleRange]);

  return (
    <div className="virtualized-list">
      {visibleNodes.map(node => (
        <NodeItem key={node.id} node={node} />
      ))}
    </div>
  );
};
```

### Lazy Loading Pattern
```typescript
// Load data only when needed
const useLazyConfiguration = (componentId: string) => {
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [loaded, setLoaded] = useState(false);

  const loadConfig = useCallback(async () => {
    if (loaded || loading) return;
    
    setLoading(true);
    setError(null);
    
    try {
      const configData = await fetchConfiguration(componentId);
      setConfig(configData);
      setLoaded(true);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [componentId, loaded, loading]);

  return { config, loading, error, loaded, loadConfig };
};
```

## Flow-Aware Positioning Pattern

### Problem
A modeler canvas can contain multiple disconnected flows (connected components) laid out side by side. When adding a new node as a successor, naive collision avoidance searches rightward and may place the new node inside or beyond a neighboring flow, creating visual edge crossings and layout confusion.

### Solution: Two-Phase Bounded Search with Flow Shifting

The positioning system separates node placement into two concerns:

1. **Graph partitioning** — BFS-based connected component detection to identify which nodes belong to which flow.
2. **Bounded spatial search** — Constrained rightward search within the gap between the source flow and the nearest right-neighboring flow, with a fallback that shifts neighboring flows to create room.

```typescript
// Phase 1: Identify flow boundaries
const sourceFlow = getConnectedComponent(sourceNodeId, allNodeIds, edges);
const sourceExtent = getFlowExtent(sourceFlow, allNodes);

// Compute safe zone: don't cross into neighboring flows
const maxAllowedX = rightBoundary - nodeWidth - COLLISION_PADDING;

// Phase 2: Search within safe zone, then shift if needed
for (let i = 1; i <= MAX_ATTEMPTS; i++) {
  const x = candidate.x + stepX * i;
  if (x > maxAllowedX) break;  // Would cross flow boundary
  if (!isOverlapping(x, candidate.y, nodeWidth, nodeHeight, allNodes)) {
    return { position: { x, y: candidate.y }, shiftNodeIds: new Set(), shiftAmount: 0 };
  }
}

// No room: place after blocking node and shift right-neighboring flows
return { position: newPosition, shiftNodeIds, shiftAmount };
```

### Why This Pattern
- **Preserves layout intent**: Flows remain visually separated without manual repositioning.
- **Atomic updates**: Node addition and flow shifting happen in a single `setNodes` call, avoiding intermediate broken states.
- **Lightweight types**: Uses `NodeLike`/`EdgeLike` interfaces instead of full ReactFlow types, keeping the utility pure and testable.
- **Two-tier API**: `findFreePosition()` for simple cases (event placement), `findFlowAwarePosition()` for flow-boundary-respecting cases (successor placement).

### Key Files
- `src/utils/positionUtils.ts` — Pure utility functions (no React dependencies)
- `src/hooks/useQuickAdd.ts` — Consumer that applies flow-aware results (both `addSuccessorNode` and `addConditionWithPlaceholder` use `findFlowAwarePosition()`)
- `src/utils/__tests__/positionUtils.test.ts` — 45 unit tests

## Error Handling Patterns

### Error Boundary Hierarchy
```typescript
// Granular error boundaries with recovery
const PanelErrorBoundary: React.FC<{
  children: React.ReactNode;
  panelName: string;
  fallback?: React.ComponentType<{ error: Error; errorInfo: ErrorInfo; retry: () => void }>;
}> = ({ children, panelName, fallback: FallbackComponent = DefaultFallback }) => {
  const [error, setError] = useState<Error | null>(null);
  const [errorInfo, setErrorInfo] = useState<ErrorInfo | null>(null);
  const [retryCount, setRetryCount] = useState(0);
  const [isRetrying, setIsRetrying] = useState(false);

  const handleError = useCallback((error: Error, errorInfo: ErrorInfo) => {
    setError(error);
    setErrorInfo(errorInfo);
    reportError(error, errorInfo, { panelName, retryCount });
  }, [panelName, retryCount]);

  const retry = useCallback(async () => {
    if (retryCount >= ERROR_RECOVERY.MAX_MANUAL_RETRIES) return;
    
    setIsRetrying(true);
    setRetryCount(prev => prev + 1);
    
    try {
      await attemptRecovery(error, errorInfo);
      setError(null);
      setErrorInfo(null);
      markRecoveryAttempted(errorInfo.componentStack, true);
    } catch (recoveryError) {
      handleError(recoveryError, { componentStack: errorInfo.componentStack });
    } finally {
      setIsRetrying(false);
    }
  }, [error, errorInfo, retryCount]);

  if (error) {
    return (
      <fallback
        error={error}
        errorInfo={errorInfo}
        retry={retry}
        retryCount={retryCount}
        isRetrying={isRetrying}
      />
    );
  }

  return children;
};
```

### Graceful Degradation
```typescript
// Fallbacks for missing features
const FeatureWrapper: React.FC<{
  children: (features: FeatureFlags) => React.ReactNode;
  fallback?: React.ReactNode;
}> = ({ children, fallback = null }) => {
  const [features, setFeatures] = useState<FeatureFlags>({});

  useEffect(() => {
    // Detect available features
    const detectFeatures = async () => {
      const detected = {
        supportsWebWorkers: await checkWebWorkerSupport(),
        supportsOffscreenCanvas: await checkOffscreenCanvas(),
        supportsFileAPI: 'showOpenFilePicker' in window,
        supportsNotifications: 'Notification' in window
      };
      
      setFeatures(detected);
    };

    detectFeatures();
  }, []);

  return <>{children(features)}</>;
};

// Usage with feature detection
<Modeler>
  {(features) => (
    <>
      <ReactFlowProvider>
        {features.supportsWebWorkers && <WebWorkerProcessor />}
        <MainCanvas />
      </ReactFlowProvider>
      
      {!features.supportsNotifications && <FallbackNotifications />}
    </>
  )}
</Modeler>
```

## Testing Patterns

### Test Data Builders
```typescript
// Build test data programmatically
class NodeBuilder {
  private node: Partial<Node> = {
    id: '',
    type: 'element',
    position: { x: 0, y: 0 },
    data: { label: '', plugin: '' }
  };

  static create(): NodeBuilder {
    return new NodeBuilder();
  }

  withId(id: string): NodeBuilder {
    this.node.id = id;
    return this;
  }

  withType(type: string): NodeBuilder {
    this.node.type = type;
    return this;
  }

  withPosition(x: number, y: number): NodeBuilder {
    this.node.position = { x, y };
    return this;
  }

  withLabel(label: string): NodeBuilder {
    this.node.data = { ...this.node.data, label };
    return this;
  }

  withPlugin(plugin: string): NodeBuilder {
    this.node.data = { ...this.node.data, plugin };
    return this;
  }

  build(): Node {
    if (!this.node.id) {
      throw new Error('Node ID is required');
    }
    return this.node as Node;
  }
}

// Usage in tests
const testNode = NodeBuilder
  .create()
  .withId('test-node-1')
  .withType('element')
  .withPosition(100, 200)
  .withLabel('Test Node')
  .withPlugin('test_plugin')
  .build();
```

### Test Utilities
```typescript
// Reusable test utilities
const renderWithStore = (
  component: React.ReactElement,
  initialState?: Partial<StoreState>
) => {
  const mockStore = createMockStore(initialState);
  
  return render(
    <StoreProvider store={mockStore}>
      {component}
    </StoreProvider>
  );
};

const waitForStoreUpdate = (predicate: (state: StoreState) => boolean) => {
  return new Promise((resolve) => {
    const unsubscribe = useStore.subscribe((state) => {
      if (predicate(state)) {
        unsubscribe();
        resolve(state);
      }
    });
  });
};

// Usage in tests
test('store updates correctly', async () => {
  const { getByTestId } = renderWithStore(<MyComponent />);
  
  // Act
  fireEvent.click(getByTestId('add-node-button'));
  
  // Wait for specific store update
  const updatedState = await waitForStoreUpdate(state => 
    state.nodes.length === 1
  );
  
  expect(updatedState.nodes[0].id).toBe('new-node');
});
```

## Security Patterns

### Input Validation Pipeline
```typescript
// Multi-layer input validation
const validateNodeData = (data: unknown): Node => {
  // Structural validation
  if (!isValidNodeStructure(data)) {
    throw new ValidationError('Invalid node structure');
  }
  
  // Type validation
  const typedData = data as NodeData;
  
  // Business rule validation
  if (!isValidNodeType(typedData.type)) {
    throw new ValidationError(`Invalid node type: ${typedData.type}`);
  }
  
  if (!isValidPluginId(typedData.plugin)) {
    throw new ValidationError(`Invalid plugin ID: ${typedData.plugin}`);
  }
  
  // Sanitize dangerous fields
  return {
    ...typedData,
    label: sanitizeHtml(typedData.label),
    annotation: sanitizeHtml(typedData.annotation)
  };
};

// Validation decorators
interface ValidationRule {
  validate(value: unknown): boolean | string;
  message?: string;
}

const createValidator = (rules: ValidationRule[]) => {
  return (value: unknown) => {
    for (const rule of rules) {
      const result = rule.validate(value);
      if (result === true) continue;
      
      return typeof result === 'string' ? result : rule.message || 'Validation failed';
    }
    
    return null; // All rules passed
  };
};
```

### Content Security Policy
```typescript
// CSP-compliant content generation
const secureContentRenderer = {
  // Allow only safe HTML elements
  allowedTags: ['div', 'span', 'p', 'strong', 'em'],
  
  // Allow only safe attributes
  allowedAttributes: ['class', 'data-*', 'aria-*'],
  
  // Sanitize content
  render: (content: string) => {
    return DOMPurify.sanitize(content, {
      ALLOWED_TAGS: this.allowedTags,
      ALLOWED_ATTR: this.allowedAttributes,
      ALLOW_DATA_ATTR: false
    });
  },
  
  // Create safe elements
  createElement: (tag: string, props: Record<string, unknown>, children: string = '') => {
    const sanitizedProps = Object.keys(props).reduce((acc, key) => {
      if (this.allowedAttributes.includes(key) || key.startsWith('data-') || key.startsWith('aria-')) {
        acc[key] = props[key];
      }
      return acc;
    }, {});
    
    return {
      tag,
      props: sanitizedProps,
      children: this.render(children)
    };
  }
};
```

## Migration Patterns

### Feature Flag Migration
```typescript
// Gradual migration with feature flags
const useMigratingComponent = (oldImplementation: any, newImplementation: any) => {
  const [useNewVersion] = useFeatureFlag('enable-new-component');
  
  if (useNewVersion) {
    return <newImplementation />;
  }
  
  return <oldImplementation />;
};

// Data migration utilities
const migrateWorkflowData = (oldData: any): ModelData => {
  try {
    // Apply migration rules
    const migratedData = {
      ...oldData,
      version: '2.0.0',
      nodes: oldData.nodes?.map(migrateNode) || [],
      edges: oldData.edges?.map(migrateEdge) || [],
      metadata: {
        ...oldData.metadata,
        migratedAt: new Date().toISOString()
      }
    };
    
    return migratedData;
  } catch (error) {
    console.error('Migration failed:', error);
    return oldData; // Fallback to original data
  }
};
```

### Backward Compatibility
```typescript
// Maintain compatibility with older data formats
const parseModelData = (data: string): ModelData => {
  try {
    const parsed = JSON.parse(data);
    
    // Handle different version formats
    if (parsed.version === '1.0') {
      return migrateFrom1_0(parsed);
    } else if (parsed.version === '1.5') {
      return migrateFrom1_5(parsed);
    } else {
      // Current version
      return parsed;
    }
  } catch (error) {
    // Try legacy format
    return parseLegacyFormat(data);
  }
};
```

## Documentation Patterns

### Self-Documenting Code
```typescript
// Clear interfaces with documentation
interface WorkflowMetrics {
  /** Number of nodes in the workflow */
  nodeCount: number;
  
  /** Number of edges in the workflow */
  edgeCount: number;
  
  /** Number of nodes not connected to any edge */
  isolatedNodeCount: number;
  
  /** Whether the workflow contains cycles */
  hasCycles: boolean;
  
  /** Complexity score (0-100) */
  complexity: number;
}

/**
 * Hook for calculating workflow metrics
 * @returns WorkflowMetrics object with workflow statistics
 */
const useWorkflowMetrics = (nodes: Node[], edges: Edge[]): WorkflowMetrics => {
  // Implementation...
};
```

### Architecture Decision Records (ADR)
```markdown
# ADR-001: Use Zustand for State Management

## Status
Accepted

## Context
We need a state management solution for our React application that:
- Handles complex workflow data
- Supports time-travel debugging
- Is performant with large datasets
- Works well with TypeScript

## Decision
Use Zustand as our state management solution.

## Consequences
- **Positive**: Simple API, great TypeScript support, performant
- **Negative**: Less ecosystem support than Redux, learning curve for team

## Alternatives Considered
- Redux: More complex, larger bundle size
- Context API: Not suitable for complex state
- Local state: Doesn't scale with complexity
```

