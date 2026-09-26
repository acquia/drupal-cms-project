# Replay System

Workflow execution visualization with bidirectional synchronization.

## Replay Architecture Overview

### Single Source of Truth Pattern
The replay system uses a **single-source-of-truth pattern** with an `isSyncing` flag to coordinate bidirectional synchronization between ReactFlow canvas and replay step list.

### Key Principles
1. **Manual Canvas Selection**: Clicking nodes/edges finds the first matching replay step
2. **Replay Step Selection**: Selecting steps highlights corresponding canvas elements  
3. **Sequential Auto-Replay**: Steps through replay list in exact order without searching
4. **Anti-Jumping Protection**: Prevents feedback loops during programmatic navigation
5. **Persistent Replay Mode**: Replay stays active indefinitely until explicitly stopped

## Replay Data Structure

### Step Interface
```typescript
interface ReplayStep {
  id: string;              // Node/element ID (source node for edge steps)
  type: string;            // Step type (started, execute, add successor, etc.)
  data?: any;              // Execution data and tokens
  successorId?: string;    // Target node for transitions
  conditionId?: string;    // Condition plugin ID for edge steps
}
```

### Replay Entry Structure
```typescript
interface ReplayEntry {
  model_id: string;      // Model ID
  event_id: string;      // Event component ID
  history: ReplayStep[];  // Array of replay steps
  timestamp: string;     // ISO-8601 execution timestamp
  user: string | { name: string };  // User who triggered execution
  ip: string;            // Client IP address
  url: string;           // Request URL
}
```

## Hook Architecture

### useSimpleReplaySync
```typescript
// Bidirectional sync between canvas and replay
const useSimpleReplaySync = ({
  replayData, nodes, edges,
  setNodes, setEdges, setSelectedNode, setSelectedEdge,
  currentStep, setCurrentStep,
}: UseSimpleReplaySyncProps) => {
  // General flag to prevent feedback loops during any sync direction
  const isSyncing = useRef(false);
  // Replay-to-canvas direction flag — guards onSelectionChange from stale
  // ReactFlow events during programmatic edge/node updates
  const isReplaySyncing = useRef(false);

  // Canvas → Replay: find matching step when user clicks a canvas element
  const selectReplayFromCanvas = useCallback((elementId, elementType) => {
    if (isSyncing.current) return;
    isSyncing.current = true;
    // ... find step matching elementId, call setCurrentStep
    setTimeout(() => { isSyncing.current = false; }, TIMING.REPLAY_SYNC_DELAY);
  }, [...]);

  // Replay → Canvas: highlight element when user clicks a replay step
  const selectCanvasFromReplay = useCallback((stepIndex) => {
    if (isSyncing.current) return;
    isSyncing.current = true;
    isReplaySyncing.current = true;  // blocks onSelectionChange
    requestAnimationFrame(() => {
      clearNodeHighlights(); clearEdgeHighlights();
      setSelectedNode(null); setSelectedEdge(null);
      // ... find and highlight element, call setSelectedNode/setSelectedEdge
      setTimeout(() => {
        isSyncing.current = false;
        isReplaySyncing.current = false;
      }, TIMING.CLEANUP_DELAY);
    });
  }, [...]);

  return {
    handleCanvasNodeClick, handleCanvasEdgeClick,
    handleReplayStepSelect,
    isSyncing: isSyncing.current,
    isSyncingRef: isSyncing,
    isReplaySyncingRef: isReplaySyncing,
  };
};
```

### useReplayStepFilter
```typescript
// Step filtering with index mapping
const useReplayStepFilter = (replayData: ReplayStep[], filterConfig: FilterConfig) => {
  const filteredSteps = useMemo(() => {
    return replayData.filter(step => {
      if (filterConfig.excludeConditions && step.type.includes('successor')) {
        return false;
      }
      if (filterConfig.showOnlyExecuted && step.type !== 'execute' && step.type !== 'started') {
        return false;
      }
      return true;
    });
  }, [replayData, filterConfig]);

  const mapFilteredToOriginal = useCallback((filteredIndex: number) => {
    return replayData.indexOf(filteredSteps[filteredIndex]);
  }, [replayData, filteredSteps]);

  const mapOriginalToFiltered = useCallback((originalIndex: number) => {
    return filteredSteps.indexOf(replayData[originalIndex]);
  }, [replayData, filteredSteps]);

  return {
    filteredSteps,
    mapFilteredToOriginal,
    mapOriginalToFiltered,
    hasFilter: filteredSteps.length < replayData.length
  };
};
```

### useReplayPlayback
```typescript
// Playback controls and state management
const useReplayPlayback = ({
  currentStep,
  onStepChange,
  totalSteps
}: PlaybackConfig) => {
  const [isPlaying, setIsPlaying] = useState(false);
  const [speed, setSpeed] = useState(1); // 0.5x, 1x, 2x
  const intervalRef = useRef<NodeJS.Timeout | null>(null);

  const stepForward = useCallback(() => {
    if (currentStep < totalSteps - 1) {
      onStepChange(currentStep + 1);
    }
  }, [currentStep, totalSteps, onStepChange]);

  const stepBackward = useCallback(() => {
    if (currentStep > 0) {
      onStepChange(currentStep - 1);
    }
  }, [currentStep, onStepChange]);

  const togglePlayback = useCallback(() => {
    setIsPlaying(prev => !prev);
  }, []);

  const stop = useCallback(() => {
    setIsPlaying(false);
    intervalRef.current && clearInterval(intervalRef.current);
  }, []);

  // Auto-play logic
  useEffect(() => {
    if (isPlaying && currentStep < totalSteps - 1) {
      intervalRef.current = setTimeout(() => {
        stepForward();
      }, 1000 / speed);
    } else {
      stop();
    }

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, [isPlaying, currentStep, totalSteps, speed, stepForward, stop]);

  return {
    isPlaying,
    speed,
    togglePlayback,
    setSpeed,
    stepForward,
    stepBackward,
    stop,
    goToStep: onStepChange
  };
};
```

## Critical Code Pattern

**Anti-Jumping Protection:**
```typescript
// In useSimpleReplaySync - prevents jumping back to first occurrence
if (currentStep >= 0 && currentStep < replayData.length) {
  const currentStepData = replayData[currentStep];
  if (elementType === 'node' && currentStepData.id === elementId &&
      (currentStepData.type === 'started' || currentStepData.type === 'execute' || currentStepData.type === 'access denied')) {
    return; // Already on the right step - don't search
  }
}
```

**Canvas Selection Protection:**
```typescript
// In useFlowEventHandlers onSelectionChange - prevents feedback during auto-replay AND preserves replay state
if (!isSyncing && replayData && replayData.length > 0 && 
    (!isReplayMode || currentReplayStep === -1)) {
  syncNodeToReplayDebounced(selectedNode);
  setIsReplayMode(true);
}
```

## Replay Visualization

### Canvas Highlighting
```typescript
// CSS-based replay highlighting (memory efficient)
const ReplayIndicators: React.FC = () => {
  const { replayData, currentStep } = useReplayState();
  const edges = useStore(state => state.edges);

  const getIndicatorPosition = useCallback((step: ReplayStep) => {
    if (step.type === 'add successor' || step.type === 'ignore successor') {
      const edge = edges.find(e => 
        e.source === step.id && 
        e.target === step.successorId
      );
      
      if (edge) {
        return getEdgeMidpoint(edge);
      }
    }
    return null;
  }, [edges]);

  return (
    <>
      {replayData.slice(0, currentStep + 1).map((step, index) => {
        const position = getIndicatorPosition(step);
        if (!position) return null;

        return (
          <div
            key={index}
            className={`replay-indicator replay-${step.type}`}
            style={{
              left: position.x,
              top: position.y
            }}
          >
            {step.type === 'add successor' ? '✓' : '✕'}
          </div>
        );
      })}
    </>
  );
};
```

### Step List Component
```typescript
const ReplayStepList: React.FC = () => {
  const { filteredSteps, mapFilteredToOriginal } = useReplayStepFilter(replayData, filterConfig);
  const { currentStep, goToStep } = useReplayPlayback();

  return (
    <div className="replay-steps">
      {filteredSteps.map((step, filteredIndex) => {
        const originalIndex = mapFilteredToOriginal(filteredIndex);
        const isActive = originalIndex === currentStep;

        return (
          <div
            key={filteredIndex}
            className={`replay-step ${isActive ? 'active' : ''}`}
            onClick={() => goToStep(originalIndex)}
          >
            <StepIcon type={step.type} />
            <div className="step-content">
              <div className="step-label">{getStepLabel(step)}</div>
              {step.conditionId && (
                <div className="step-condition">{step.conditionId}</div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
};
```

## Data Loading and Validation

### useReplayLoader
```typescript
// Load replay data with validation and user feedback
const { replayEntries, loading, error, loadReplayData, clearReplayEntries } = useReplayLoader({
  settings,                    // Contains modeler_api.replay_url, token_url, modeler.modelId
  onEntriesLoaded: (entries) => {
    // Called when entries are successfully loaded
  },
});

// Trigger load for a specific event component
await loadReplayData('event-component-id');
```

The hook provides user feedback through Drupal's native message system:
- **Empty replay data**: Shows a warning message — "No replay data available for this event."
- **Backend error** (response contains `{ error: "..." }`): Shows an error message with the backend's error text.
- Messages appear in the modeler's floating messages container and auto-fade after 5 seconds (see `docs/ui-components.md`, Messages System section).

## Test Runner

### useTestRunner Hook

The `useTestRunner` hook provides live workflow testing by sending a request to the backend, polling for results, and delivering replay data when the execution completes.

```typescript
const {
  isTestRunning,      // Whether polling is active
  isTestInitiating,   // Whether the initial POST is in flight
  testError,          // Error message if the test failed (null otherwise)
  startTest,          // (componentId: string) => void — begin a test
  cancelTest,         // () => void — abort the running test
  notifySaveComplete, // () => void — called after save, triggers pending test
} = useTestRunner({
  settings,                   // Contains modeler_api.test_url, token_url, modeler.modelId
  hasUnsavedChanges,          // Whether the model has unsaved edits
  showConfirmationDialog,     // Dialog helper for save-then-test flow
  saveButtonRef,              // Ref to the save button element
  onReplayDataReceived,       // (data: any[]) => void — called with replay steps on success
});
```

### Test Flow

```
User clicks Test
       │
       ▼
┌─ Unsaved changes? ─── YES ──► Show "Save and test" / "Cancel" dialog
│                                        │
│                                   User confirms
│                                        │
│                              Click save button via ref
│                                        │
│                              notifySaveComplete() called
│                                        │
└──── NO ◄───────────────────────────────┘
       │
       ▼
POST {modelId, componentId} → test_url
       │
       ▼
Response: { jobId, ?warning }
       │
       ▼
Poll every 1.5s: POST {jobId} → test_url
       │
       ├── { status: "waiting" } → continue polling
       ├── { error: "..." }      → show error, stop
       ├── { warning: "..." }    → show warning, continue
       └── ReplayStep[]          → pass to onReplayDataReceived, stop
```

When test results arrive, `handleTestReplayDataReceived` in Flow.tsx wraps the replay steps in a single `ReplayEntry` and passes it through `handleReplayEntriesLoaded([entry])`. Since this is a single entry, the entry selector dropdown is not shown — the user sees the replay steps directly without needing to choose between executions.

### Save-Then-Test Pattern

When the user clicks **Test** with unsaved changes:

1. `startTest` stores the `componentId` in a ref and shows a confirmation dialog with **"Save and test"** / **"Cancel"** buttons (secondary button hidden via `secondaryLabel: false`).
2. If the user confirms, the save button is programmatically clicked via `saveButtonRef.current.click()`.
3. Flow.tsx's save callback calls `notifySaveComplete()` after save succeeds.
4. `notifySaveComplete` reads the pending `componentId` from the ref and calls `proceedWithTest`.

This reuses the same save mechanism that "Save and Close" uses (clicking the actual save button) ensuring all CSRF, validation, and Drupal AJAX handling is executed.

### Error and Warning Handling

Responses from the test endpoint can contain:
- **`{ error: "..." }`** — Shown as a Drupal error message; stops the test.
- **`{ warning: "..." }`** — Shown as a Drupal warning message; does **not** block the test flow (polling continues).

Both initiation responses and poll responses support `error` and `warning` properties.

### Abort and Cleanup

- An `AbortController` is created for each test. Aborting it cancels both in-flight `fetch` calls and stops polling.
- `cancelTest()` clears the interval, aborts the controller, and resets all state.
- On component unmount, `cleanup()` is called via a `useEffect` return to prevent leaks.
- Poll callbacks check `abortControllerRef.current.signal.aborted` before processing each response.

## Memory Optimization

### CSS-Based Highlighting (Recommended)
```css
/* CSS handles all replay highlighting - no JavaScript needed */
.react-flow__edge.replay-add path {
  stroke: #10b981 !important;  /* Green for successful conditions */
  stroke-width: 3 !important;
}

.react-flow__edge.replay-ignore path {
  stroke: #ef4444 !important;  /* Red for failed conditions */
  stroke-width: 3 !important;
}
```

```typescript
// Apply replay classes instead of JavaScript state updates
const useReplayEdgeStyling = () => {
  const { replayData, currentStep } = useReplayState();
  const edges = useStore(state => state.edges);

  useEffect(() => {
    // Remove all replay classes
    edges.forEach(edge => {
      const element = document.querySelector(`[data-id="${edge.id}"]`);
      element?.classList.remove('replay-add', 'replay-ignore');
    });

    // Add replay classes based on current step
    for (let i = 0; i <= currentStep && i < replayData.length; i++) {
      const step = replayData[i];
      if (step.type === 'add successor' || step.type === 'ignore successor') {
        const edge = edges.find(e => 
          e.source === step.id && e.target === step.successorId
        );
        const element = document.querySelector(`[data-id="${edge?.id}"]`);
        element?.classList.add(`replay-${step.type.replace(' ', '-')}`);
      }
    }
  }, [currentStep, replayData, edges]);
};
```

## Testing Replay System

### Unit Tests
```typescript
describe('useReplayStepFilter', () => {
  test('filters out condition steps', () => {
    const replayData = [
      { type: 'started', id: 'node1' },
      { type: 'add successor', id: 'node1', successorId: 'node2' },
      { type: 'execute', id: 'node2' }
    ];

    const { filteredSteps } = useReplayStepFilter(replayData, {
      excludeConditions: true
    });

    expect(filteredSteps).toHaveLength(2); // Only started, execute
  });

  test('maps indices correctly', () => {
    const replayData = [
      { type: 'started', id: 'node1' },
      { type: 'add successor', id: 'node1', successorId: 'node2' }
    ];

    const { mapFilteredToOriginal, mapOriginalToFiltered } = useReplayStepFilter(
      replayData,
      { excludeConditions: true }
    );

    expect(mapFilteredToOriginal(0)).toBe(0); // started -> original 0
    expect(mapOriginalToFiltered(0)).toBe(0); // original 0 -> filtered 0
  });
});
```

### E2E Tests
```typescript
test('replay playback controls work', async ({ page }) => {
  const modeler = new ModelerPage(page);
  await modeler.goto();
  await modeler.loadReplayData();

  // Start playback
  await modeler.startReplay();
  await expect(modeler.isPlaying()).toBe(true);

  // Wait for first step
  await modeler.waitForStep(0);

  // Change speed
  await modeler.setPlaybackSpeed(2);

  // Stop playback
  await modeler.stopReplay();
  await expect(modeler.isPlaying()).toBe(false);
});
```

## Replay System Guidelines

### Implementation Checklist
- [ ] Use `isSyncing` ref to prevent feedback loops; use `isReplaySyncing` ref to guard `onSelectionChange` during replay-to-canvas sync
- [ ] Validate replay entries before using them
- [ ] Use CSS-based highlighting for memory efficiency
- [ ] Include proper error handling for API failures
- [ ] Implement playback controls with speed adjustment
- [ ] Provide step filtering options
- [ ] Show step metadata including conditions and tokens
- [ ] Sync canvas selection with replay steps
- [ ] Handle edge cases (empty data, single step, etc.)

### Performance Considerations
- [ ] Avoid continuous intervals for visual updates
- [ ] Use CSS classes instead of JavaScript styling
- [ ] Implement proper cleanup on component unmount
- [ ] Use virtualization for large step lists
- [ ] Debounce rapid user interactions
- [ ] Cache expensive calculations

### Testing Requirements
- [ ] Unit tests for all replay hooks
- [ ] E2E tests for playback controls
- [ ] Visual regression tests for step indicators
- [ ] Accessibility tests for keyboard navigation
- [ ] Performance tests for large replay datasets



**Critical Code Patterns:**
```typescript
// In useFlowEventHandlers onSelectionChange:
// 1. Skip stale events during replay-to-canvas sync (isReplaySyncingRef)
if (isReplaySyncingRef.current) return;
// 2. Guard auto-sync with general isSyncing flag
if (!isSyncing && hasReplayData && (!isReplayMode || currentReplayStep === -1)) {
  autoSyncToReplay(newSelectedNode);
}

// In useSimpleReplaySync - prevents jumping back to first occurrence
if (currentStep >= 0 && currentStep < replayData.length) {
  const currentStepData = replayData[currentStep];
  if (elementType === 'node' && currentStepData.id === elementId &&
      (currentStepData.type === 'started' || currentStepData.type === 'execute' || currentStepData.type === 'access denied')) {
    return; // Already on the right step - don't search
  }
}
```

**File Structure:**
- **Hook**: `src/hooks/useSimpleReplaySync.ts` - Bidirectional sync logic (~260 lines, TypeScript)
- **Integration**: `src/hooks/useFlowEventHandlers.ts` - Canvas selection handling with `isReplaySyncingRef` guard (via `onSelectionChange`)
- **Panel**: `src/components/ReplayPanel.tsx` - Standalone replay interface (~603 lines)

**Extracted Hooks (February 2026):**
- **Step Filter**: `src/hooks/useReplayStepFilter.ts` - Filters replay steps, provides index mapping (~130 lines)
- **Playback**: `src/hooks/useReplayPlayback.ts` - Play/pause, speed, navigation controls (~152 lines)
- **Replay Loader**: `src/hooks/useReplayLoader.ts` - Fetches replay execution entries from backend via POST with CSRF token (~90 lines)
- **Test Runner**: `src/hooks/useTestRunner.ts` - Live test initiation, polling, save-then-test coordination (~356 lines)

**Extracted Components:**
- **Data Renderer**: `src/components/ReplayDataRenderer.tsx` - Hierarchical token data display + global tokens (~373 lines)

**Shared Utilities:**
- **Step Utils**: `src/utils/replayStepUtils.tsx` - Step icon and label helpers (~95 lines)

**Synchronization Flow:**
1. **Auto-Replay Navigation**: ReplayPanel → hook sets `isSyncing=true` + `isReplaySyncing=true` → programmatic canvas selection → `onSelectionChange` skipped entirely (prevents stale node+edge race)
2. **Manual Canvas Selection**: User clicks element → `onSelectionChange` fires normally (only `isSyncing` is set, not `isReplaySyncing`) → hook searches for matching step → ReplayPanel updates
3. **Manual Step Selection**: User clicks step → hook highlights canvas element via `setNodes`/`setEdges` + calls `setSelectedNode`/`setSelectedEdge` directly → property panel updates via store

**Edge Case Handling:**
- **Duplicate Nodes**: Current step matching prevents searching when already positioned correctly
- **Condition Edges**: Matches by conditionId for precise edge-to-step relationships
- **Node Types**: Only matches execution steps (started/execute/access denied) not successor references
- **Race Conditions**: Two-flag system — `isSyncing` prevents general feedback loops; `isReplaySyncing` guards `onSelectionChange` from stale ReactFlow events during replay-to-canvas direction only
- **Final Step Persistence**: Auto-replay stops playing but stays on final step until explicitly stopped
- **Protected Replay State**: Canvas selection changes ignored while replay mode is active with valid step

## Replay Data Structure

**ReplayStep Interface:**
```typescript
interface ReplayStep {
  id: string;              // Node/element ID (source node for edge steps)
  type: string;            // Step type (started, execute, add successor, etc.)
  data?: any;              // Execution data and tokens
  successorId?: string;    // Target node for transitions
  conditionId?: string;    // Condition unique component ID (e.g., "eca_entity_is_new_10j5tps")
}
```

**IMPORTANT: Edge ID vs Condition ID:**
These are different concepts and must not be confused:
- **Edge ID**: The unique identifier for the ReactFlow edge (e.g., "edge_1", "edge_abc123")
- **Condition ID (`conditionId`)**: The condition plugin ID stored in `replayStep.conditionId` (e.g., "entity:entity_is_new")
- **Edge Condition (`edge.data.condition`)**: The condition plugin ID stored on the edge

To find an edge from a replay step (with fallback):
```typescript
// Primary: Match edge by its condition plugin ID
let edge = edges.find(e => e.data?.condition === step.conditionId);

// Fallback: Match by source/target relationship
if (!edge && step.successorId) {
  edge = edges.find(e =>
    e.source === step.id && e.target === step.successorId && e.data?.condition
  );
}
```

**Step Type Matching:**
- **Node Steps**: `started`, `execute`, `access denied` - Primary node execution
- **Edge Steps**: `add successor`, `ignore successor` - Transitions with conditions
- **Condition Steps**: Referenced by `conditionId` for edge highlighting

## Visual Indicators

**Replay Step Type Indicators:**
- **Floating Visual Elements**: Circular indicators positioned at edge midpoints during replay
- **Color Coded**: Green circle with ✓ for "add successor", red circle with ✕ for "ignore successor"
- **Non-Intrusive**: Positioned above ReactFlow canvas without interfering with edge selection
- **Viewport Responsive**: Indicators follow zoom and pan transformations
- **Professional Styling**: White borders, shadows, proper z-index management

**Requirements for Indicators to Display:**
1. `isReplayMode` must be `true` - the replay mode must be active
2. `currentReplayStep >= 0` - a valid step must be selected
3. Step must be `add successor` or `ignore successor` type with a `conditionId`
4. The edge must be found (see fallback strategy above)

**Note:** When clicking a step in the ReplayPanel, the `handleReplayStepSelect` wrapper in Flow.tsx automatically activates replay mode if not already active.

**Implementation:**
```typescript
// Positioned at calculated midpoint with viewport transformation
const midX = (sourceX + targetX) / 2;
const midY = (sourceY + targetY) / 2;
backgroundColor: currentStep.type === 'add successor' ? '#10b981' : '#ef4444',
```

**Benefits over Edge Coloring:**
- **No ReactFlow conflicts**: Edges retain standard selection colors (blue)
- **Clear visual distinction**: Separate elements avoid styling interference
- **Better maintainability**: No complex CSS overrides or color conflicts
- **Future proof**: Independent of ReactFlow styling changes

## Memory Optimization (February 2026)

### Problem: Continuous Edge Color Updates
The replay system was causing memory leaks through a continuously running 50ms interval that updated edge colors, even when idle.

### Solution: CSS-Based Highlighting
Replaced JavaScript state updates with CSS classes for replay edge highlighting:

**Before (Memory Leak):**
```typescript
// Running every 50ms continuously
useEffect(() => {
  const intervalId = setInterval(() => {
    setEdges(prevEdges => {
      // Constantly checking and updating edge colors
      return prevEdges.map(edge => {
        if (edge.data?._fromReplay) {
          return { ...edge, style: { stroke: highlightColor }};
        }
      });
    });
  }, 50);
}, [currentStep]);
```

**After (Optimized):**
```css
/* CSS handles all replay highlighting */
.react-flow__edge.replay-add path {
  stroke: #10b981 !important;  /* Green for successful conditions */
  stroke-width: 3 !important;
}

.react-flow__edge.replay-ignore path {
  stroke: #ef4444 !important;  /* Red for failed conditions */
  stroke-width: 3 !important;
}
```

### Benefits:
- **Zero runtime overhead**: No JavaScript execution required for maintaining colors
- **Stable memory usage**: No accumulating state updates or timer callbacks
- **Cleaner code**: Removed complex interval management and cleanup logic
- **Better performance**: CSS rendering is more efficient than React re-renders

### Implementation:
- Removed `useEffect` with setInterval from `useReplayController.ts`
- Added CSS classes `.replay-add` and `.replay-ignore` to edges during replay
- Eliminated all console.log statements that were accumulating memory
- Reduced unnecessary setTimeout chains for clearing replay flags

## Standalone Replay Panel

- **Dedicated interface**: Replay functionality now in separate panel between canvas and property panel
- **Smart panning**: Only pans when selected element is outside viewport
- **Preserved zoom**: Maintains user's zoom level during replay
- **Condition ID support**: Correctly highlights specific edges with multiple connections
- **Edge selection**: Uses conditionId to identify exact edge in multi-edge scenarios
- **Step filtering**: Shows relevant steps based on conditions and gateways
- **Step data display**: Shows conditionId and token data inline under the selected step row
- **Metadata info popup**: Step metadata (Type, Component ID, Successor ID, Condition ID, Error) shown in header info popup via "i" icon
- **Entry selector**: Dropdown to switch between multiple execution entries — only shown when there are 2+ entries (hidden for single test results)
- **Implementation**: `ReplayPanel.tsx` component with enhanced replay controls, data visualization, and `InfoPopup.tsx` for metadata

## Refactored Architecture (February 2026)

### Extracted Hooks

**useReplayStepFilter:**
```typescript
// Filters replay steps and provides index mapping
const {
  filteredSteps,           // Steps matching filter criteria
  mapFilteredToOriginal,   // Convert filtered index → original index
  mapOriginalToFiltered,   // Convert original index → filtered index
  hasFilter                // Whether filtering is active
} = useReplayStepFilter(replayData, filterConfig);
```

**useReplayPlayback:**
```typescript
// Manages playback state and controls
const {
  isPlaying,              // Auto-play state
  speed,                  // Playback speed (0.5x, 1x, 2x)
  togglePlayback,         // Play/pause toggle
  setSpeed,               // Change playback speed
  stepForward,            // Go to next step
  stepBackward,           // Go to previous step
  goToStep,               // Jump to specific step
  stop                    // Stop and reset playback
} = useReplayPlayback({ currentStep, onStepChange, totalSteps });
```

### Shared Components

**ReplayDataRenderer:**
- Renders hierarchical token data with collapsible sections
- Supports nested objects and arrays with proper indentation
- Handles special data types (dates, booleans, nulls)
- Memory-efficient with virtualization for large datasets
- Displays a grip dots icon (⋮) next to draggable token labels as a visual drag affordance
- Sets `isTokenDragging` in the Zustand store on drag start/end to coordinate drop zone indicators across the property panel

**StepDataContainer:**
- Wrapper for displaying step metadata and token data
- Collapsible sections for condition results and execution data
- Consistent styling across replay components
- Shows a help text hint ("Drag tokens into configuration fields to insert them.") above the token data when step data is available

**GlobalTokensContainer:**
- Transforms Drupal global token structure (`name`/`raw token`/`children`) into `ReplayDataRenderer` format (`label`/`token`/`data`)
- Renders site-wide tokens at the bottom of the replay panel (both empty and replay states)
- Tokens are draggable into configuration fields, using the `raw token` value as the drop payload
- Data sourced from `drupalSettings.modeler.global_tokens`

### Shared Utilities

**replayStepUtils.tsx:**
```typescript
// Get appropriate icon for step type
getStepIcon(stepType: string): ReactNode

// Get human-readable label for step type
getStepLabel(stepType: string): string
```

### Test Coverage

| Module | Tests | Description |
|--------|-------|-------------|
| useReplayStepFilter | 24 | Index mapping, filtering logic |
| useReplayPlayback | 24 | Playback controls, speed, navigation |
| useReplayLoader | 16 | Fetch, CSRF tokens, error/warning handling, loading states |
| useTestRunner | 25 | Test initiation, polling, save-then-test, cancel, error/warning handling |
| ReplayDataRenderer | 38 | Data rendering, collapsible sections, global tokens |
| replayStepUtils | 17 | Icon and label generation |
| **Unit Total** | **144** | Comprehensive replay system unit test coverage |
| **E2E Tests** | **43** | Playwright end-to-end replay + test feature tests |
| **Grand Total** | **174** | Combined unit + E2E replay test coverage |

## E2E Testing (Playwright)

The replay system has comprehensive E2E test coverage via Playwright, testing the full user flow from loading replay data to browsing execution entries and navigating steps.

### Test Categories (43 tests across 10 groups)

| Category | Tests | Coverage |
|----------|-------|----------|
| Loading Replay Data | 5 | Load button visibility, fetching entries, loading state, panel display, entry count |
| Replay Entry Selector | 7 | Dropdown toggle, entry display, entry switching, outside-click close, entry metadata |
| Replay Panel UI | 8 | Step list, progress label, playback controls, speed selector, stop button |
| Replay Step Navigation | 7 | Next/previous step, first/last boundaries, step highlighting, step selection |
| Replay Playback | 4 | Auto-play start/pause, speed changes, playback progression |
| Inline Step Data | 3 | Token data rendering, condition results, step metadata |
| Replay Panel Info Popup | 2 | Info popup display, metadata content |
| Test Button Visibility | 2 | Test button shown with test_url + event selected, hidden without test_url |
| Test Execution | 4 | Click test starts polling, waiting state with cancel, successful result display, cancel stops test |
| Test Error Handling | 2 | Init error shown as message, poll error shown as message |

### Mock Infrastructure

- **`mockReplayEntries`** in `e2e/fixtures/mocks.ts` - ReplayEntry[] format with 3 entries containing model_id, event_id, history (replay steps), timestamp, user, ip, url
- **Replay endpoint** in `e2e/test-server.ts` - POST `/modeler-api/replay` returns ReplayEntry[] data
- **CSRF token endpoint** - Returns plain text `'mock-csrf-token'` for useReplayLoader fetch
- **`Drupal.t()` interpolation** - Test server mock handles `@`-variable substitution (e.g., `'Step @current of @total'` → `'Step 1 of 2'`)

### Page Object Methods (`ModelerPage.ts`)

18+ new methods for replay and test interaction: `loadReplayData()`, `getReplayLoadButton()`, `getReplayEntryToggle()`, `openReplayEntryDropdown()`, `getReplayEntryItems()`, `selectReplayEntry()`, `getReplaySteps()`, `startReplay()`, `pauseReplay()`, `stopReplay()`, `nextReplayStep()`, `previousReplayStep()`, `getProgressLabel()`, `getSpeedControl()`, `getTestButton()`, `startTest()`, `getTestWaitingState()`, `getTestCancelButton()`, `cancelTest()`, `getReplayEmptyState()`, `getReplayPanelToggle()`

## Replay Mode Integration

- **Standalone panel**: Dedicated replay panel between canvas and property panel
- **Step visualization**: Highlight executed nodes and edges during replay
- **Condition filtering**: Only show steps with conditions or gateway outputs
- **State synchronization**: ReactFlow selection synced with replay step selection
- **Auto-activation**: Automatically switches to replay tab when data available

## Refactoring Benefits

**Before Refactoring:**
- 300+ lines of tangled replay logic in App.tsx
- Complex timing-based synchronization prone to race conditions
- Auto-replay jumping back to first occurrence of duplicate nodes
- Multiple "Maximum update depth exceeded" infinite loops
- ReplayPanel.tsx: 854 lines of monolithic code
- ReplayTab.tsx: 777 lines with duplicated logic (since removed)

**After Refactoring:**
- Clean separation: App.tsx (UI) + useSimpleReplaySync (logic)
- Single `isSyncing` flag eliminates all timing dependencies
- Sequential auto-replay guaranteed - never searches during navigation
- Persistent replay mode - stays active until explicitly stopped
- Protected replay state - prevents accidental resets during final step
- Visual indicators instead of edge coloring - no ReactFlow conflicts
- Enhanced stop button - properly exits replay mode completely
- TypeScript interfaces for better maintainability
- Comprehensive documentation for future modifications
- **ReplayPanel.tsx: 854 → 603 lines (with test feature additions)**
- **ReplayTab.tsx removed** (functionality consolidated into ReplayPanel)
- **Shared hooks eliminate code duplication**

This architecture provides a stable foundation for replay functionality that can be extended with additional features while maintaining reliability.
