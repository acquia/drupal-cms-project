# UI Components

Reference for the modeler's UI architecture.

## Panel System Overview

### Three Main Panels
```
┌───────────────────────────────────────────────┐
│ Canvas          │ Replay Panel │ Property Panel │
│ (ReactFlow)     │  (execution) │  (forms)       │
└───────────────────────────────────────────────┘
```

### Panel Characteristics
- **Canvas** (center): ReactFlow workflow visualization with quick-add popups for adding components
- **Replay Panel** (center-right): Execution playback controls, standalone
- **Property Panel** (far right): Element configuration, auto-revealing

## Canvas Component (ReactFlow)

### Key Implementation
```typescript
// Location: src/components/FlowCanvas.tsx
import ReactFlow from 'reactflow';

const FlowCanvas: React.FC<FlowCanvasProps> = ({
  nodes,
  edges,
  onNodesChange,
  onEdgesChange,
  onConnect,
  // ... other props
}) => {
  return (
    <ReactFlow
      nodes={nodes}
      edges={edges}
      onNodesChange={onNodesChange}
      onEdgesChange={onEdgesChange}
      onConnect={onConnect}
      nodeTypes={customNodeTypes}
      edgeTypes={customEdgeTypes}
      fitView
      snapToGrid
      snapGrid={[20, 20]}
    >
      {/* Background and overlays */}
    </ReactFlow>
  );
};
```

### Canvas Interactions
- **Node Selection**: Click to select, Shift+click for multi-select
- **Quick-Add Popups**: Add components via the quick-add buttons on nodes/edges
- **Connection Creation**: Click output port, drag to input port
- **Panning**: Mouse wheel/trackpad scroll to pan, middle-click/right-click drag, or Space+drag
- **Zooming**: Ctrl+wheel (Cmd+wheel on Mac), pinch-to-zoom, or toolbar controls
- **Keyboard**: Delete, Ctrl+C/V, Ctrl+F shortcuts

### Custom Node Types
```typescript
// Custom nodes in components/nodes/
const nodeTypes = {
  start: StartNode,      // Event/trigger nodes
  element: CustomNode,   // Action/activity nodes
  gateway: GatewayNode,  // Decision/diamond nodes
  subprocess: SubprocessNode, // Nested workflows
  placeholder: PlaceholderNode, // Temporary nodes awaiting action/gateway selection
};
```

### Custom Edge Types
```typescript
// Custom edges in components/edges/
const edgeTypes = {
  default: DefaultEdge,      // Basic connections
};
```

## Property Panel Architecture

### Auto-Reveal Pattern
```typescript
// PropertyPanel.tsx - auto-reveals when element selected
const PropertyPanel: React.FC = () => {
  const selectedNode = useSelectionStore(state => state.selectedNode);
  const selectedEdge = useSelectionStore(state => state.selectedEdge);
  
  // Hide when nothing selected
  if (!selectedNode && !selectedEdge) {
    return null;
  }

  return (
    <div data-testid="property-panel">
      {selectedNode && <NodePropertiesPanel node={selectedNode} />}
      {selectedEdge && <EdgePropertiesPanel edge={selectedEdge} />}
    </div>
  );
};
```

### Sub-Panel System
```typescript
// Single element property panel
const NodePropertiesPanel: React.FC<{ node: Node }> = ({ node }) => (
  <div>
    <LabelField label={node.data.label} onChange={handleLabelChange} />
    <AnnotationField annotation={node.data.annotation} />
    <ConfigurationForm componentId={node.data.plugin} />
    <InfoPopup metadata={getNodeMetadata(node)} />
  </div>
);

// Multi-selection panel
const MultiSelectionPanel: React.FC<MultiSelectionPanelProps> = ({
  selectedNodes, selectedEdges, onConfigurationChange, onEdgeConfigurationChange,
  onDeleteSelected, isReadOnly
}) => (
  <div>
    <SelectionSummary nodes={selectedNodes} edges={selectedEdges} />
    <DeleteAllButton onClick={onDeleteSelected} disabled={isReadOnly} />
    {/* Danger-styled button triggers confirmation dialog via Flow.tsx */}
  </div>
);
```

#### Delete All with Confirmation
The "Delete All" button in `MultiSelectionPanel` is a danger-styled action (`bulk-delete-btn` class) that deletes all selected nodes and edges. The button is disabled in read-only mode. Instead of deleting immediately, `Flow.tsx` wires `onDeleteSelected` to show a customized `ConfirmDialog` (2-button danger variant: "Delete" + "Cancel", secondary button hidden) via `showConfirmationDialog` with an options bag. The actual deletion is performed by `handleDeleteSelected` only after the user confirms.

### Configuration Form Integration
```typescript
// Configuration form loading via useConfigurationLoader hook
// The hook posts component_type, plugin_id, model_id, is_new,
// and configuration to the config_url endpoint.
// The backend returns { form: [...], error?: "..." } as JSON.
const { configurationForm, loading } = useConfigurationLoader({
  node,      // Selected node (or null)
  edge,      // Selected edge (or null)
  settings,  // drupalSettings with modeler_api.config_url, etc.
  isReplayMode,
});

// configurationForm is an array of field objects from the backend,
// or null when no form is available. Errors are surfaced to the
// user via Drupal.Message (showDrupalMessage).
```

## Replay Panel Implementation

### Always-Visible Panel

The ReplayPanel is always rendered (not hidden when empty) as long as either `replay_url` or `test_url` is available and the model is not new. This ensures users can discover and use the Test feature even before any replay data is loaded.

**Visibility rules:**
- If neither `replay_url` nor `test_url` is configured → panel is hidden entirely
- If `drupalSettings.modeler.isNew === true` → panel is hidden (new models can't be tested/replayed)
- Otherwise → panel is always visible (auto-collapses when no data, auto-expands when data loads)

### Auto-Collapse/Expand Behavior

The panel automatically manages its collapsed state:
- **Auto-collapse**: When no replay data exists AND no test is running, the panel collapses automatically using a ref-based pattern (reads collapsed state via `useRef` to avoid re-triggering the effect)
- **Auto-expand**: When replay data loads (from either the replay loader or test runner) and the panel is collapsed, it automatically expands

**`collapsePanels` override**: When `settings.modeler.collapsePanels` is `true` (used by the standalone viewer), the auto-expand effect in `Flow.tsx` is guarded with `if (collapsePanels) return;`. This prevents the replay panel from auto-expanding on mount, keeping all panels collapsed until the user interacts. The property panel still auto-expands/collapses based on node/edge selection.

### Test Button

A **Test** button (with play icon) appears in the panel header when:
1. An event node is selected (or auto-detected when only one event exists)
2. `test_url` is configured in `drupalSettings.modeler_api`
3. No test is currently running or initiating

```typescript
const showTestButton = selectedEventNodeId && hasTestUrl && !isTestRunning && !isTestInitiating;
```

Clicking **Test** either starts the test directly (if no unsaved changes) or shows a "Save and test" / "Cancel" confirmation dialog.

### Test Waiting State

During test polling, the panel shows a waiting state with:
- A spinning refresh icon (`.spinning` CSS animation)
- Phase-aware heading: "Starting test..." during initiation, "Waiting for test execution..." during polling
- Instructional text: "Trigger the selected event on your Drupal site so that the workflow gets executed and the results are captured."
- A **Cancel** button (only shown during the polling phase, not during initiation)

### Context-Aware Empty State Messages

When the panel has no replay data and no test is running, it shows context-specific guidance:

| Configuration | Message |
|--------------|---------|
| `replay_url` available | "Select an event and use the reload button in the property panel to load past execution data." |
| Both `replay_url` and `test_url` | Shows replay message + "- or -" separator + test message |
| `test_url` + event selected | "Click Test to execute the workflow and capture the results." |
| `test_url` + no event selected | "Select an event and click Test to execute the workflow and capture the results." |
| Neither URL available | "Run your workflow to generate execution data" |

### Review Mode Header

In review mode the panel header is emptied: its label zone holds a single control, the back button (`.header-review-btn.panel-header-back`) rendered as `FiArrowLeft` plus the label "Back". Clicking it returns to property mode; the replay session, selected entry and current step are preserved. `Alt+Shift+R` (`Option+Shift+R` on Mac) toggles the two modes from the keyboard - see [keyboard-shortcuts.md](keyboard-shortcuts.md).

### Inline Step Data

When replay data is loaded, the review body is a **single list** of steps (`.replay-steps`). Selecting a step expands its step data inline, directly underneath that step row.

```tsx
<div ref={el => { stepRefs.current[index] = el; }} className="replay-step-item">
  <div
    className={`replay-step ${isSelected ? 'current' : ''} ...`}
    role="button"
    tabIndex={0}
    aria-expanded={isSelected}
    aria-controls={isSelected ? stepDataId : undefined}
  >
    ...
  </div>
  {isSelected && (
    <div id={stepDataId} className="replay-step-data" role="region" aria-label={t('Step data')} tabIndex={0}>
      <StepDataContainer stepData={stepData} predicted={stepDataPredicted} />
    </div>
  )}
</div>
```

- **Wrapper**: `.replay-step-item` holds the row plus its inline data and is what `stepRefs` points at, so playback auto-scroll keeps both in view
- **Step row**: `role="button"`, `tabIndex={0}`, `aria-expanded`, and `aria-controls` set only while the region exists (a dangling id would be an invalid ARIA value)
- **Rendering**: `StepDataContainer` (in `ReplayDataRenderer.tsx`) renders the token tree, unchanged
- **Empty data**: a selected step without token data renders the "No token data available for this step" hint instead of the tree
- **No sub-panels**: there is no separate step data, global tokens or template tokens section below the list, and the body is not vertically resizable
- **Global and template tokens**: reachable only through the `[` token picker, whose `global` and `template` categories are built in `utils/tokenPickerData.ts` from the exported `transformGlobalToken` helper
- **Accessibility**: scrollable regions carry `tabIndex={0}`, `role="region"` and an `aria-label` (required by the axe `scrollable-region-focusable` rule)

### Standalone Interface
```typescript
// ReplayPanel.tsx - independent replay functionality
const ReplayPanel: React.FC = () => {
  const { replayData, currentReplayStep } = useReplayState();
  
  return (
    <div data-testid="replay-panel">
      <ReplayControls />
      <ReplayStepList />
    </div>
  );
};
```

### Playback Controls
```typescript
const ReplayControls: React.FC = () => {
  const { isPlaying, speed, togglePlayback, setSpeed, stop } = useReplayPlayback();
  
  return (
    <div className="replay-controls">
      <button onClick={togglePlayback}>
        {isPlaying ? 'Pause' : 'Play'}
      </button>
      <select value={speed} onChange={(e) => setSpeed(Number(e.target.value))}>
        <option value={0.5}>0.5x</option>
        <option value={1}>1x</option>
        <option value={2}>2x</option>
      </select>
      <button onClick={stop}>Stop</button>
    </div>
  );
};
```

### Step Navigation
```typescript
const ReplayStepList: React.FC = () => {
  const { currentReplayStep, goToStep } = useReplayPlayback();
  const filteredSteps = useReplayStepFilter();
  
  return (
    <div className="replay-steps">
      {filteredSteps.map((step, index) => (
        <div key={index} className="replay-step-item">
          <div
            className={`step ${index === currentReplayStep ? 'active' : ''}`}
            onClick={() => goToStep(index)}
          >
            <StepIcon type={step.type} />
            <StepLabel step={step} />
          </div>
          {index === currentReplayStep && <StepDataContainer stepData={step.data} />}
        </div>
      ))}
    </div>
  );
};
```

## Toolbar System

The modeler uses a two-toolbar architecture:

### Main Toolbar (Toolbar.tsx, ~275 lines)
Rendered at the top of the modeler. Three sections:
- **Left**: QuickAddEventButton, inline SearchBar, plugin widgets (left)
- **Center**: Model title (drag handle in restored mode), messages indicator
- **Right**: Plugin widgets (right), Save button, ToolbarMenu (kebab menu with settings/export/dark-mode/docs), view mode toggle, close

```typescript
// Toolbar.tsx - top action bar (simplified)
const Toolbar: React.FC<ToolbarProps> = ({
  onSave, onOpenMetadata, modelName, hasUnsavedChanges,
  onAddEvent, onExport, viewMode, onToggleViewMode, ...
}) => {
  return (
    <div className="workflow-toolbar">
      <div className="toolbar-left">
        <QuickAddEventButton onAddEvent={onAddEvent} />
        <SearchBar ref={searchBarRef} onHighlight={...} />
        <PluginToolbarWidgetSlot widgets={pluginWidgetsLeft} api={pluginApi} />
      </div>
      <div className="toolbar-center">
        <h1 className="model-title">{modelName}</h1>
        {/* Messages toggle and clear buttons */}
      </div>
      <div className="toolbar-right">
        <PluginToolbarWidgetSlot widgets={pluginWidgetsRight} api={pluginApi} />
        <button onClick={handleSave} className="toolbar-btn primary">Save</button>
        <ToolbarMenu onOpenMetadata={...} onExport={...} canExport={...} />
        {/* View mode, close buttons */}
      </div>
    </div>
  );
};
```

### Canvas Toolbar (CanvasToolbar.tsx, ~309 lines)
A secondary semi-transparent toolbar rendered on top of the canvas area. Contains controls previously in the main toolbar:
- **Left**: Context selector, StartFlowFilter, View menu (Fit View, Auto Layout)
- **Right**: Copy, Paste, Undo, Redo, separator, Zoom out, zoom %, Zoom in

```typescript
// CanvasToolbar.tsx - canvas-level controls (simplified)
const CanvasToolbar: React.FC<CanvasToolbarProps> = ({
  isLocked, isReadOnly, onCopy, onPaste, onUndo, onRedo,
  hasSelection, canPaste, canUndo, canRedo, onAutoLayout,
  contexts, selectedContextId, onContextChange,
}) => {
  return (
    <div className="canvas-toolbar">
      <div className="canvas-toolbar-left">
        {/* Context selector, StartFlowFilter, View dropdown */}
      </div>
      <div className="canvas-toolbar-right">
        {/* Copy, Paste, Undo, Redo, Zoom controls */}
      </div>
    </div>
  );
};
```

### ToolbarMenu (ToolbarMenu.tsx)
Kebab (three-dot) dropdown menu in the main toolbar's right section. Contains:
- Model settings (opens metadata modal)
- Export (opens export dialog)
- Dark mode toggle

## Modal System

### Modal Coordination
```typescript
// Modals.tsx - coordinates all modal dialogs
const Modals: React.FC<ModalsProps> = ({
  showMetadataModal, showConfirmDialog,
  confirmDialogPrimaryLabel, confirmDialogSecondaryLabel,
  confirmDialogCancelLabel, confirmDialogPrimaryVariant,
  onConfirmDialog, onCancelDialog, onCloseWithoutSave, ...rest
}) => (
  <>
    {showMetadataModal && <MetadataModal ... />}
    {showConfirmDialog && (
      <ConfirmDialog
        primaryButtonLabel={confirmDialogPrimaryLabel}
        secondaryButtonLabel={confirmDialogSecondaryLabel}
        cancelButtonLabel={confirmDialogCancelLabel}
        primaryButtonVariant={confirmDialogPrimaryVariant}
        onCloseWithoutSave={onCloseWithoutSave}
        ...
      />
    )}
  </>
);
```

### MetadataModal (MetadataModal.tsx)
The model information dialog owns no field list of its own. The backend converts the model owner's Drupal metadata form (`ModelerBase::defaultModelConfigForm()`) to JSON and ships it as `drupalSettings.modeler.metadataForm`; `Flow.tsx` threads it through `Modals.tsx` into the dialog, which renders it with the same `ConfigurationForm` as any plugin configuration form. A field the owner hides with `#access => FALSE` is therefore neither rendered nor submitted, and a field it adds needs no frontend change.

The dialog contributes exactly three things of its own:
- **Chrome**: overlay, title, close button, focus trap, and the Cancel/Save footer. Save is hidden when the user may not edit metadata (`fieldsReadOnly`), which also disables every field.
- **Permissions and lifecycle**: the `template` field is disabled without the "create template" permission, and `changelog` is dropped for a model that does not exist yet.
- **Value translation**: `utils/metadataCodec.ts` converts between the stored metadata (arrays, a list of config actions) and the strings the form speaks (comma separated tags, one entry per line, a YAML document). An unparsable config actions document keeps the value the dialog opened with.

The `model_id` field is Drupal's `machine_name` element: it mirrors the label until the user types an ID of their own, and is disabled for an existing model. Only a new model submits an `id`.

### ConfirmDialog Customization
`ConfirmDialog` supports several optional props for reuse across different confirmation scenarios:
- **`primaryButtonLabel`**: Custom label for the primary action (default: "Save and Close")
- **`secondaryButtonLabel`**: Custom label or `false` to hide the secondary button entirely (default: "Close Without Saving")
- **`cancelButtonLabel`**: Custom label for the cancel button (default: "Cancel")
- **`primaryButtonVariant`**: `'primary'` or `'danger'` to style the primary button (default: `'primary'`)

These props are passed through from `Modals.tsx` which receives them from the `useModalState` hook. The hook's `showConfirmationDialog` method accepts an optional 6th `options` bag for these values (see State Management docs).

### Focus Management
```typescript
const ModalWrapper: React.FC<{ children: React.ReactNode; onClose: () => void }> = ({ 
  children, 
  onClose 
}) => {
  const modalRef = useRef<HTMLDivElement>(null);
  
  useFocusTrap(modalRef, onClose);

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div 
        ref={modalRef}
        className="modal-content"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
      >
        {children}
      </div>
    </div>
  );
};
```

## Panel Resize System

### Resize Implementation
```typescript
// Panel resize with drag handles
const usePanelResize = (panelId: string, initialWidth: number) => {
  const [width, setWidth] = useState(initialWidth);
  const [isResizing, setIsResizing] = useState(false);
  
  const handleMouseDown = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    setIsResizing(true);
    
    const startX = e.clientX;
    const startWidth = width;
    
    const handleMouseMove = (moveEvent: MouseEvent) => {
      const newWidth = startWidth + (moveEvent.clientX - startX);
      setWidth(Math.max(200, Math.min(800, newWidth))); // Min/max constraints
    };
    
    const handleMouseUp = () => {
      setIsResizing(false);
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
    };
    
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  }, [width]);

  return { width, isResizing, handleMouseDown };
};
```

## Search Integration

### Global Search System
```typescript
// SearchBar.tsx - integrated search functionality
const SearchBar: React.FC = () => {
  const [searchTerm, setSearchTerm] = useState('');
  const [isSearchMode, setIsSearchMode] = useState(false);
  
  const handleSearch = useCallback((term: string) => {
    setSearchTerm(term);
    performSearch(term); // Debounced search function
  }, []);

  return (
    <div className={`search-bar ${isSearchMode ? 'active' : ''}`}>
      <input
        type="text"
        value={searchTerm}
        onChange={(e) => handleSearch(e.target.value)}
        placeholder="Search nodes and edges..."
        aria-label="Search workflow elements"
      />
      <button onClick={() => setIsSearchMode(!isSearchMode)}>
        {isSearchMode ? 'Close' : 'Search'}
      </button>
    </div>
  );
};
```

## UI Patterns

### Component States
```typescript
// Loading states
const LoadingState: React.FC = () => (
  <div className="loading">
    <div className="loading-spinner" />
    <span>Loading...</span>
  </div>
);

// Error states
const ErrorState: React.FC<{ error: string; onRetry?: () => void }> = ({ error, onRetry }) => (
  <div className="error-state">
    <div className="error-icon">⚠️</div>
    <div className="error-message">{error}</div>
    {onRetry && <button onClick={onRetry}>Retry</button>}
  </div>
);

// Empty states
const EmptyState: React.FC<{ message: string; action?: React.ReactNode }> = ({ 
  message, 
  action 
}) => (
  <div className="empty-state">
    <div className="empty-icon">📋</div>
    <div className="empty-message">{message}</div>
    {action && <div className="empty-action">{action}</div>}
  </div>
);
```

## Accessibility Patterns

### ARIA Implementation
```typescript
// Accessible button with icon
const IconButton: React.FC<{ icon: React.ReactNode; label: string; onClick: () => void }> = ({ 
  icon, 
  label, 
  onClick 
}) => (
  <button 
    onClick={onClick}
    aria-label={label}
    className="icon-button"
  >
    {icon}
    <span className="sr-only">{label}</span>
  </button>
);

// Accessible search combobox
const SearchCombobox: React.FC = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  
  return (
    <div 
      role="combobox"
      aria-expanded={isOpen}
      aria-haspopup="listbox"
      aria-activedescendant={`result-${highlightedIndex}`}
    >
      <input
        type="text"
        aria-autocomplete="list"
        aria-controls="search-results"
        role="searchbox"
      />
      {isOpen && (
        <ul id="search-results" role="listbox">
          {results.map((result, index) => (
            <li
              key={result.id}
              id={`result-${index}`}
              role="option"
              aria-selected={index === highlightedIndex}
            >
              {result.label}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};
```

## UI Development Guidelines

### Component Creation Checklist
- [ ] Follow established file structure (Component.tsx, Component.stories.tsx, __tests__/Component.test.tsx)
- [ ] Use TypeScript interfaces for all props
- [ ] Include Storybook stories for all variants
- [ ] Write comprehensive unit tests
- [ ] Use CSS custom properties for styling
- [ ] Implement proper accessibility attributes
- [ ] Add data-testid attributes for E2E testing
- [ ] Include error and loading states
- [ ] Use proper event handling patterns

### Panel Integration Patterns
- [ ] Use store selectors for state dependencies
- [ ] Implement resize handles for resizable panels
- [ ] Add collapse/expand functionality
- [ ] Include focus trapping for popups
- [ ] Handle keyboard navigation
- [ ] Use proper ARIA roles and labels

## Component File Structure

```
components/
├── ReplayPanel.tsx          # Standalone replay interface (~603 lines, with test feature)
├── ReplayDataRenderer.tsx   # Hierarchical token data display + transformGlobalToken helper
├── PropertyPanel.tsx        # Properties with annotations, info popup, and resizable width
├── NodePropertiesPanel.tsx  # Single node: label, annotation, config (~91 lines)
├── EdgePropertiesPanel.tsx  # Single edge: label, annotation, config (~110 lines)
├── MultiSelectionPanel.tsx  # Multi-select bulk operations (delete all with confirmation)
├── InfoPopup.tsx            # Metadata info popup from panel header "i" icon (~73 lines)
├── Toolbar.tsx              # Main toolbar (~275 lines, restructured)
├── CanvasToolbar.tsx        # Canvas-level toolbar (zoom, copy/paste, undo/redo, ~309 lines)
├── ToolbarMenu.tsx          # Kebab menu (settings, export, dark mode)
├── PluginToolbarWidget.tsx  # Plugin widget slot renderer
├── PluginPanelContainer.tsx # Plugin panel container with resize/collapse
├── StartFlowFilter.tsx      # Multi-select dropdown to filter visible flows by start node
├── SearchBar.tsx            # Search functionality
├── MetadataModal.tsx        # Model information dialog (renders the backend metadata form)
├── ConfigurationForm.tsx    # Dynamic form rendering (types in src/types/forms.ts)
├── ContentEditableField.tsx # Rich text input with token drag-and-drop & inline editing (~712 lines, extracted)
├── ConfirmDialog.tsx        # Customizable confirmation dialogs (button labels, danger variant, hide secondary)
├── DocumentationPopup.tsx   # External documentation viewer popup
├── DocumentationButton.tsx  # Reusable documentation link button
├── QuickAddButton.tsx       # Node hover button to add successor nodes (context-filtered, with type filter)
├── QuickAddConditionButton.tsx # Edge hover button to add conditions (context-filtered)
├── QuickAddEdgeButton.tsx     # Edge hover button to add conditions or insert action/gateway nodes (context-filtered)
├── QuickAddEventButton.tsx  # Canvas button to add event/start nodes (context-filtered)
├── QuickAddPopup.tsx        # Shared popup with search, sections, and collapsible type-filter panel
├── PanelErrorBoundary.tsx   # Granular error boundary with auto-retry for panels
├── nodes/                   # Custom node components
│   ├── CustomNode.tsx       # Standard workflow nodes
│   ├── StartNode.tsx        # Event/trigger nodes
│   ├── GatewayNode.tsx      # Decision diamond nodes
│   ├── SubprocessNode.tsx   # Subprocess nodes
│   ├── PlaceholderNode.tsx  # Temporary nodes for condition-first authoring
│   └── ConditionNode.tsx    # Condition nodes (first-class nodes for conditions)
└── edges/                   # Custom edge components
    ├── DefaultEdge.tsx      # Basic edges with control points and annotations
    └── EdgeOrderBadge.tsx   # Draggable edge order number badge
```

## Toolbar Features Summary

### Main Toolbar (`Toolbar.tsx`)
- **Quick-add event button**: Blue "+ Event" button to add start nodes
- **Integrated search**: Search field always visible inline with keyboard shortcut override (Ctrl+F)
- **Plugin widgets**: Left and right slots for plugin-registered toolbar widgets
- **Dynamic title**: Shows model name with unsaved changes indicator (●)
- **Messages indicator**: Lightning bolt button next to model title (when messages present) with toggle and clear controls (see Messages System below)
- **Save button**: Primary action, disabled when no unsaved changes
- **Kebab menu** (`ToolbarMenu.tsx`): Model settings, Export, Dark mode toggle, Documentation link
- **View mode / Close**: Window management buttons

### Canvas Toolbar (`CanvasToolbar.tsx`)
- **Context selector**: When `drupalSettings.modeler.contexts` is non-empty, a `<select>` dropdown appears allowing users to filter available components
- **Start flow filter**: Multi-select dropdown that lets users filter which flows are visible on the canvas by selecting specific start (event) nodes. Appears automatically when two or more start nodes exist. Uses BFS to trace reachable nodes from selected start nodes and hides the rest via ReactFlow's `hidden` property. Newly added start nodes are auto-included in the active selection. When `selectComponentId` targets a start node on model open, the filter is pre-set to show only that flow.
- **View menu**: Dropdown with Fit View and Auto Layout actions
- **Copy/Paste buttons**: With proper enable/disable states based on selection and clipboard
- **Undo/Redo buttons**: Wired to `useHistoryStore` with up to 50 snapshots
- **Zoom controls**: Zoom in/out buttons with percentage display and disabled state at limits

Implementation: `src/components/Toolbar.tsx`, `src/components/CanvasToolbar.tsx`, `src/components/ToolbarMenu.tsx`, `src/components/StartFlowFilter.tsx`

## Messages System

The modeler intercepts Drupal's native message system and displays messages in a floating container positioned below the toolbar. Messages are managed by the `useMessagesContainer` hook (`src/hooks/useMessagesContainer.ts`).

### How It Works

1. **DOM interception**: On mount, the hook finds the existing `.messages-list` element in the page and moves it into the modeler's floating container. On unmount, it restores the element to its original DOM position.
2. **MutationObserver**: A `MutationObserver` watches the `.messages-list` for changes (new messages added, messages removed). When new content appears, the container becomes visible.
3. **Auto-fade**: When new messages arrive, they are shown for 5 seconds then automatically fade out via CSS opacity/visibility transitions.
4. **Drupal.Message integration**: The modeler uses `new Drupal.Message().add(text, { type })` to create messages (e.g., replay warnings/errors) and `new Drupal.Message().clear()` to remove all messages.

### Toolbar Controls

When messages are present, two buttons appear in the toolbar center (next to the model title):

- **Toggle button** (lightning bolt icon, `FiZap`):
  - When messages are **hidden** (after fade-out): button appears **active** (colored). Clicking shows messages and **pins** them — they stay visible indefinitely until dismissed.
  - When messages are **visible** (pinned): button appears **inactive** (greyed out). Clicking hides messages immediately.
- **Clear button** (trash icon, `FiTrash2`): Permanently deletes all messages by calling `new Drupal.Message().clear()`, clearing the DOM, and resetting all state. Both buttons disappear since there are no more messages.

### Pinning Behavior

Messages can be in one of three visibility modes:
- **Auto-fade** (default): New messages appear, then fade out after 5 seconds
- **Pinned**: User clicked the toggle to bring back messages — they stay visible until explicitly dismissed
- **Hidden**: User clicked toggle while messages were visible, or messages faded out naturally

New messages always interrupt and show regardless of the current mode, restarting the auto-fade timer (unless pinned).

### Key Files
- **Hook**: `src/hooks/useMessagesContainer.ts` — DOM management, visibility state, pinning logic
- **Toolbar integration**: `src/components/Toolbar.tsx` — Toggle and clear buttons
- **Orchestration**: `src/components/Flow.tsx` — Wires hook to toolbar and renders the container
- **Styles**: `src/styles/modeler.css` — `.workflow-messages-container`, `.messages-toggle-btn`, `.messages-clear-btn`
- **Drupal types**: `src/types/drupal.d.ts` — `DrupalMessageMessenger` interface with `add()` and `clear()` methods

## Quick-Add Component Behavior

### Context and Dependency Filtering
All four quick-add components (`QuickAddButton`, `QuickAddConditionButton`, `QuickAddEdgeButton`, `QuickAddEventButton`) use the `useContextFilter` hook to filter their component lists.

### Type Filter Panel
`QuickAddButton` includes a collapsible **type filter** panel (powered by `QuickAddPopup`'s `typeFilters` config) that lets users narrow the component list by type (All / Actions / Conditions / Gateways). The filter is defined via `TypeFilterOption[]` and applied between the `componentFilter` and search/sort steps.

### Condition-First Authoring
When a user selects a **condition** from the `QuickAddButton` popup, the `handleQuickAdd` callback in `Flow.tsx` routes to `useQuickAdd.addConditionWithPlaceholder()`, which creates a placeholder node + condition edge. The condition edge is auto-selected so the Property Panel opens on the condition for immediate configuration.

### Edge Insertion
When a user clicks the "+" button on a **default edge** (no condition), the `QuickAddEdgeButton` popup offers both conditions and actions/gateways. Selecting a condition attaches it to the edge via `handleAddCondition`. Selecting an action/gateway inserts a new node between the two connected nodes via `handleAddActionOnEdge`, splitting the edge into two.

### Condition Edge Insertion
**Condition edges** show two "+" buttons: one before and one after the condition card. Selecting an **action/gateway** inserts a new node, preserving the original condition on the appropriate side. Selecting a **condition** inserts a gateway node in a linear chain — the original condition stays on one side and the new condition goes on the other:

- **"+" before condition**: `source --[new cond]--> gateway --[orig cond]--> target`
- **"+" after condition**: `source --[orig cond]--> gateway --[new cond]--> target`

### Context and Dependency Filtering
- When a context is selected, only plugins defined in the active context's component entries are shown
- Plugins with entries in the global dependency definitions (from `drupalSettings.modeler_api.dependencies`) are further filtered based on whether the current workflow satisfies those dependencies
- Both the context filter and the dependency filter must pass for a component to be included
- When no context is selected, all components pass the context check but dependencies are still enforced

### Favorite Suppression
When a context is active, the `isComponentFavorite` callback returns `false` for all components — no star indicators, no favorites-first sorting, and no divider lines. Components appear in plain alphabetical order.

### Popup Close Behavior
Quick-add popups can be closed four ways:
1. **Close button**: The X button in the popup header
2. **Escape key**: Via focus trap's capture-phase keydown handler
3. **Toggle click**: Clicking the quick-add button again
4. **Click outside**: Capture-phase `pointerdown` listener on `document` (registered via `setTimeout(0)` to avoid the opening click from immediately closing)

### Search Visibility
The three quick-add popups conditionally hide the search field when the number of available components is below a threshold:
- **Threshold**: `THRESHOLDS.SEARCH_VISIBILITY_MIN_COMPONENTS` (default 15) in `src/constants/dimensions.ts`
- **Checked against**: The pre-filtered component list for each popup (before any user search input), i.e. `successorComponents`, `eventComponents`, or `conditionComponents` respectively
- **Scope**: Only the quick-add popups
- **Focus behavior**: Auto-focus on the search input when the popup opens is skipped when the search field is hidden

## Node Positioning System

When new nodes are added to the canvas (via quick-add, event-add, or drag-and-drop), the modeler uses collision detection and flow-aware positioning to place them without overlapping existing nodes or crossing into neighboring flows.

### Position Utilities

All positioning logic lives in `src/utils/positionUtils.ts`. Two main entry points:

- **`findFreePosition()`** — Simple collision-avoiding placement. Searches right, then down, then diagonal. Used by `useNodeEdgeActions` for event node placement.
- **`findFlowAwarePosition()`** — Flow-boundary-respecting placement. Identifies connected components (flows) and ensures new nodes stay within their flow's territory. Used by `useQuickAdd` for successor node placement.

### Flow-Aware Positioning Algorithm

When adding a successor node via quick-add, the algorithm:

1. **Compute candidate position**: Directly below the source node (centered horizontally, offset by `NODE_SPACING_Y` vertically).
2. **Fast path**: If the candidate position is free, use it immediately.
3. **Identify flows**: Use BFS on the edge graph (treated as undirected) to find the source node's connected component and all neighboring flows.
4. **Compute safe zone**: The new node's right edge must stay at least `COLLISION_PADDING` (20px) away from the nearest right-neighboring flow's left edge (`maxAllowedX`).
5. **Phase 1 — Search within safe zone**: Try positions to the right of the candidate in tight increments (`nodeWidth + 2 * COLLISION_PADDING = 240px`), stopping if the position would cross into the neighboring flow.
6. **Phase 2 — Shift neighboring flows**: If no room exists within the safe zone, place the node just right of the blocking same-flow node and shift all flows to the right by the amount needed to maintain `MIN_FLOW_GAP` (250px) clearance. The shift is applied atomically in the same `setNodes` update.

### Key Interfaces

```typescript
// Position utilities use lightweight interfaces for flexibility
interface NodeLike {
  id?: string;
  position: { x: number; y: number };
  width?: number | null;
  height?: number | null;
}

interface EdgeLike {
  source: string;
  target: string;
}

// Flow-aware result includes shift information
interface FlowAwarePositionResult {
  position: { x: number; y: number };
  shiftNodeIds: Set<string>;  // IDs of nodes to shift right
  shiftAmount: number;         // Pixels to shift (0 = no shift needed)
}
```

### Constants

| Constant | Value | Purpose |
|----------|-------|---------|
| `COLLISION_PADDING` | 20px | Padding around nodes for overlap detection |
| `MAX_ATTEMPTS` | 50 | Safety limit on search iterations |
| `MIN_FLOW_GAP` | `LAYOUT.NODE_SPACING_X` (250px) | Minimum gap between flows when shifting |

### Helper Functions

- **`isOverlapping()`** — AABB collision detection with configurable padding. Both candidate and existing nodes are axis-aligned bounding boxes in ReactFlow's top-left coordinate system.
- **`getConnectedComponent()`** — BFS to find all nodes in the same flow (edges treated as undirected).
- **`getAllFlows()`** — Partitions all nodes into connected components.
- **`getFlowExtent()`** — Computes horizontal bounding extent (minX to maxX) of a flow.

### Consumer Hooks

| Hook | Position Function | Use Case |
|------|-------------------|----------|
| `useQuickAdd` | `findFlowAwarePosition()` | Successor node and placeholder node placement (respects flow boundaries) |
| `useNodeEdgeActions` | `findFreePosition()` | Event node placement (positions to the right of all content) |

### How Flow Shifting Works

When `findFlowAwarePosition` returns a non-zero `shiftAmount`, `useQuickAdd` applies the shift in the same `setNodes` call that adds the new node:

```typescript
setNodes(prev => {
  const updated = prev.map(n => {
    let node = n.selected ? { ...n, selected: false } : n;
    if (shiftAmount > 0 && n.id && shiftNodeIds.has(n.id)) {
      node = node === n ? { ...n } : node;
      node.position = { x: node.position.x + shiftAmount, y: node.position.y };
    }
    return node;
  });
  return [...updated, newNode];
});
```

This atomic update ensures the canvas never shows an intermediate state where the new node overlaps a neighboring flow.

### Key Files
- **Position Utils**: `src/utils/positionUtils.ts` — All positioning algorithms
- **Quick Add Hook**: `src/hooks/useQuickAdd.ts` — Flow-aware successor and placeholder placement (`addSuccessorNode`, `addConditionWithPlaceholder`)
- **Node/Edge Actions Hook**: `src/hooks/useNodeEdgeActions.ts` — Simple event placement
- **Tests**: `src/utils/__tests__/positionUtils.test.ts` — 45 tests covering all positioning functions
- **Tests**: `src/hooks/__tests__/useQuickAdd.test.ts` — 14 tests including flow-shift integration

## Search & Find Functionality
- **Toggle Search**: Search button in toolbar activates inline search mode
- **Live Search**: Real-time search of nodes and edges by label, plugin, type, or ID
- **Dropdown Results**: Multiple results shown in searchable dropdown with keyboard navigation
- **Visual Highlighting**: Selected results highlighted with orange glow and pulsing animation
- **Keyboard Shortcut**: Ctrl+F (Cmd+F on Mac) toggles search mode, overriding browser find
- Implementation: `src/components/SearchBar.tsx`

## Viewport Management (April 2026)

All programmatic viewport operations are managed by `useViewportActions`
(`src/hooks/useViewportActions.ts`).  See `docs/viewport-management.md`
for the comprehensive reference.

### Key Behaviors
- **Zoom preservation**: All pan operations preserve the current zoom
  level.  Only `fitToNodes()` and `fitToNodePair()` change zoom.
- **Off-screen check**: Node insertions use `panToNodeIfOffscreen()` —
  the canvas only pans if the new node would be invisible.
- **Accessibility**: Respects `prefers-reduced-motion` (instant
  transitions when enabled).

### Smart Auto-Selection Positioning
When a model is loaded with an element to be auto-selected:

- **Event Nodes** (start nodes): Top-aligned via `topAlignNode()` —
  positioned 150px from the top of the viewport so the user can see the
  flow downward.
- **Other Nodes**: Centered via `focusNode()` — panned to center
  without changing zoom.

### Implementation
```typescript
// In useModelDataLoader — queues for deferred execution
const viewportKind = isStartNode ? 'topAlignNode' : 'focusNode';
viewportActions.selectAndFocus(nodeToSelect, viewportKind);
```

- Implementation: `src/hooks/useViewportActions.ts`

## FlowCanvas Prop Grouping (February 2026)

FlowCanvas was refactored from 34 flat props to 13 top-level props using 7 logical interface groups:

| Interface Group | Props Grouped | Purpose |
|----------------|---------------|---------|
| `FlowCanvasEventHandlers` | 14 | ReactFlow event handlers (onNodesChange, onConnect, etc.) |
| `FlowCanvasElementCallbacks` | 3 | Element update callbacks (onEdgeUpdate, onNodeUpdate, onDeleteNode) |
| `FlowCanvasModifierKeys` | 3 | Keyboard modifier state (isShiftPressed, isCtrlPressed, isAltPressed) |
| `FlowCanvasUIState` | 5 | Toggle flags (isDragActive, isLocked, showMinimap, etc.) |
| `FlowCanvasSearchState` | 2 | Search state (searchTerm, highlightedSearchResult) |
| `FlowCanvasReplayState` | 4 | Replay visualization (replayData, currentReplayStep, etc.) |
| `FlowCanvasQuickAddProps` | varies | Quick-add callbacks and popup control |

All interfaces are exported from `FlowCanvas.tsx` for reuse. Flow.tsx constructs the grouped prop objects and passes them to FlowCanvas.

## Recent Component Refactoring (February 2026)

### ConfigurationForm & ContentEditableField

The `ConfigurationForm.tsx` was refactored from 694 to 239 lines (65.6% reduction) by extracting:

**ContentEditableField Component:**
- Rich text input with token drag-and-drop support
- Handles `[token:path]` syntax for dynamic values
- Supports paste handling with HTML sanitization
- Auto-resizes based on content
- Accepts `acceptsTokens` prop to control whether token drops are allowed per-field
- Accepts `isTokenDragging` prop for visual indicators (`.token-drop-target` / `.token-drop-rejected` classes)
- Tokens within a field are draggable — users can drag a token pill to reposition it within the same field (move semantics; the source token is removed and re-inserted at the drop position)
- Token selection highlighting — when the browser selection intersects a token span, a `.selected` class is applied to the entire pill (background + border change) so it appears selected as a unit rather than just highlighting the inner text

**Token Drop Zone System:**
The configuration form implements field-level token drop control:
- Each `FormField` from the backend may include `token_support: true` to mark it as token-eligible
- A `replace_tokens` checkbox field (if present and checked) overrides per-field settings and enables all fields
- During a token drag (`isTokenDragging` from the Zustand store), eligible fields get `.token-drop-enabled` while non-eligible fields get `.token-drop-disabled` (dimmed at 0.6 opacity)
- The modeler's native Label and Annotation fields are always disabled as drop zones during token drag
- Draggable tokens in the replay panel display a grip icon (⋮) and a help text hint explains the drag functionality

**Inline Token Editing:**
Users can edit existing token pills in-place without deleting and re-creating them. The feature consists of an edit icon overlay and an edit popup:

- **Edit icon**: A small pencil icon (`FiEdit2`) appears above a token when the user hovers over it or when the browser selection intersects a token. Mouse tracking is attached to the wrapper div (not the contenteditable container) so the icon stays visible as the mouse moves from the token to the icon itself.
- **Edit popup**: Clicking the icon (or pressing **Ctrl+E** / **Cmd+E** with the cursor adjacent to or selecting a token) opens a floating popup with a text input pre-filled with the token value (without brackets).

Popup positioning:
- **Vertical**: Above the token by default; flips below if there is less than 80px of space above the token within the wrapper.
- **Horizontal**: Centered on the token, then clamped so the popup (200px `min-width`) stays within the wrapper's horizontal bounds (`halfPopup = 100px` on each side).
- **CSS class**: `.token-edit-popup` for above placement; `.token-edit-popup-below` added when positioned below.

Save / cancel:
- **Save** (Enter key or Save button): Updates `data-token`, `title`, and `textContent` on the DOM element. Calls `onChange` **immediately** (not debounced) so the parent receives the update even while the field is still focused.
- **Cancel** (Escape key or Cancel button): Closes the popup without changes.
- **Empty input**: Treated as cancel — closes without saving.

Keyboard access:
- **Ctrl+E / Cmd+E**: When a token has the `.selected` class (via selection highlighting), or when the cursor is immediately adjacent to a token, this shortcut opens the edit popup for that token.

CSS classes:
- `.token-edit-icon` — Positioned absolutely above the hovered/selected token
- `.token-edit-popup` / `.token-edit-popup-below` — The floating edit form
- `.token-edit-input` — The text input within the popup
- `.token-edit-actions` — Button container (Save / Cancel)
- `.token-edit-save` / `.token-edit-cancel` — Action buttons

**Token Utilities (`tokenUtils.ts`):**
- `tokensToHtml()` - Converts token strings to styled HTML spans
- `htmlToTokens()` - Extracts token strings from HTML content
- DOMPurify integration for XSS prevention

### Toolbar Refactoring

The `Toolbar.tsx` was restructured from 485 lines to ~275 lines by extracting canvas-level controls to `CanvasToolbar.tsx` (~309 lines) and creating `ToolbarMenu.tsx` for the kebab menu. Additional extractions include:

**useSaveModel Hook:**
- Handles complete Drupal AJAX save flow
- CSRF token retrieval and handling
- Error handling with user feedback
- Marks model as saved on success

**useToolbarHandlers Hook:**
- Layout button handlers (auto-layout, clear canvas)
- Zoom control handlers (zoom in/out, fit to screen)
- Export/import handlers
- Model metadata handlers

### ReplayPanel Refactoring

The ReplayPanel uses extracted hooks and shared components:

**Extracted Hooks:**
- `useReplayStepFilter` - Step filtering with index mapping
- `useReplayPlayback` - Playback controls (play/pause/speed/navigation)

**Shared Components:**
- `ReplayDataRenderer` - Hierarchical token data display
- `StepDataContainer` - Renders the step data tree shown inline under the selected step
- `transformGlobalToken` - Normalizes Drupal token entries; consumed by `utils/tokenPickerData.ts` for the picker's `global` and `template` categories

**Results:**
- ReplayPanel: 854 → 603 lines (with test feature additions)
- ReplayTab removed (functionality consolidated into ReplayPanel)
