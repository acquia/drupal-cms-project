# Viewport Management

Comprehensive reference for all programmatic panning, zooming, and node
positioning in the modeler canvas.  This document is the single source of
truth; when behavior needs fine-tuning, update the code **and** this file
together.

---

## Design Principles

1. **Preserve the user's zoom level.**  Programmatic operations should pan
   without changing zoom unless visibility absolutely requires it.
2. **Minimize animation.**  Smooth transitions are nice, but aggressive
   zoom-in/out disorients users.  The `prefers-reduced-motion` media query
   disables all animation durations (instant transitions).
3. **Single codepath.**  All viewport operations go through the unified
   `useViewportActions` hook (`src/hooks/useViewportActions.ts`).  No
   calling ReactFlow's `setCenter()` or `fitView()` directly.
4. **Deferred execution.**  Operations requested before ReactFlow is
   initialized are queued and applied once `setReady()` is called.

---

## Architecture

```
┌──────────────────────────────────────────────────────┐
│ Callers                                              │
│  useNodeEdgeActions, useQuickAdd, useSearch,          │
│  useModelDataLoader, CanvasToolbar, pluginApi         │
└──────────────────────┬───────────────────────────────┘
                       │ call viewport action methods
                       ▼
┌──────────────────────────────────────────────────────┐
│ useViewportActions  (src/hooks/useViewportActions.ts) │
│                                                      │
│  - panToNode(nodeId)                                 │
│  - panToNodeIfOffscreen(nodeId)                      │
│  - fitToNodes(nodeIds?)                              │
│  - topAlignNode(nodeId)                              │
│  - focusNode(nodeId)                                 │
│  - fitToNodePair(id1, id2)                           │
│  - selectAndFocus(node, kind)                        │
│  - setReady()                                        │
│                                                      │
│  Internally uses:                                    │
│    ReactFlow setCenter(), fitView(), getZoom(),      │
│    getViewport()                                     │
│  Always passes current zoom explicitly to setCenter  │
│  to prevent ReactFlow's maxZoom default.             │
└──────────────────────────────────────────────────────┘
```

### Why not call ReactFlow directly?

ReactFlow's `setCenter(x, y, options)` defaults the zoom to **maxZoom**
(configured as 4x in this project) when no `zoom` option is provided.
The `useViewportActions` hook always reads `getZoom()` and passes it
explicitly to `setCenter()`, preventing unintended zoom changes.

### Plugin API access

The plugin API (`pluginApi.ts`) runs outside React hooks.  It accesses
viewport actions through registered callbacks:

```typescript
// Flow.tsx registers these on mount
setViewportHooks({
  focusNode: (nodeId) => viewportActions.focusNode(nodeId),
  fitView: () => viewportActions.fitToNodes(),
});
```

---

## API Reference

### `panToNode(nodeId: string)`

Pan the viewport to center the given node.  **Never changes zoom.**

- **Used by:** Not currently used directly (reserved for future use).
- **Behavior:** Reads current zoom via `getZoom()`, calculates the node's
  center, calls `setCenter()` with the current zoom.

### `panToNodeIfOffscreen(nodeId: string)`

Pan to center the node **only if it is currently outside the visible
viewport**.  If the node is already on-screen, this is a no-op.

- **Used by:** `useNodeEdgeActions` (all node insertions), `useQuickAdd`
  (successor node).
- **Behavior:** Gets the viewport bounds, checks if the node center is
  within bounds (with 40px margin).  If outside, calls `panToNode`.
- **Rationale:** When a user adds a node near their current view, the
  canvas should not jump.  It only pans when the new node would be
  invisible.

### `fitToNodes(nodeIds?: string[])`

Fit the viewport to show specific nodes (by ID), or all visible nodes if
no IDs are provided.  **This is the only operation that freely changes
zoom.**

- **Used by:** `CanvasToolbar` (Fit View button), `useModelDataLoader`
  (initial load without a selection).
- **Behavior:** Calls `fitView()` with `padding: 0.1` and
  `maxZoom: 1.5` (FIT_MAX_ZOOM) to prevent over-zooming when few nodes
  exist.  Filters out hidden nodes automatically.

### `topAlignNode(nodeId: string)`

Position a node near the **top** of the viewport (150px from the top
edge).  **Preserves the current zoom level.**

- **Used by:** `useModelDataLoader` (initial load when a start/event node
  is pre-selected).
- **Behavior:** Calculates an adjusted Y coordinate so the node appears
  near the top, then calls `setCenter()` with the current zoom.
- **Rationale:** Event/start nodes begin workflows.  Top-alignment lets
  the user see the flow downward from the start.

### `focusNode(nodeId: string)`

Pan to center a node.  Always pans (unlike `panToNodeIfOffscreen`), but
**never changes zoom**.

- **Used by:** `useSearch` (search result navigation), `pluginApi`
  (`focusNode()`).
- **Behavior:** Same as `panToNode` — reads current zoom and passes it
  to `setCenter()`.

### `fitToNodePair(nodeId1: string, nodeId2: string)`

Fit the viewport to show exactly two nodes.  Used when both nodes must
be visible together (e.g., a source and a newly created placeholder).

- **Used by:** `useQuickAdd` (condition-first quick-add with placeholder).
- **Behavior:** Calls `fitView()` scoped to the two nodes, with
  `maxZoom: 1.5` to prevent over-zooming when nodes are close.

### `selectAndFocus(node, kind)`

Combined operation for model load: selects a node in the store, waits
one tick for propagation, then applies `topAlignNode` or `focusNode`.

- **Used by:** `useModelDataLoader` (initial load with a pre-selected
  component).
- **Behavior:** Calls `setSelectedNode(node)`, then after `SYNC_DELAY`
  (100ms) calls the appropriate viewport method.

### `setReady()`

Signal that ReactFlow is fully initialized.  Triggers execution of any
queued (deferred) viewport operation.

- **Called by:** `Flow.tsx` in the `onInit` callback.

---

## When Each Operation Is Triggered

| Trigger | Method | Zoom behavior |
|---------|--------|---------------|
| Model load — no selection | `fitToNodes()` | Changes zoom (fit) |
| Model load — start node selected | `selectAndFocus(node, 'topAlignNode')` | Preserves zoom |
| Model load — other node selected | `selectAndFocus(node, 'focusNode')` | Preserves zoom |
| Fit View button (canvas toolbar) | `fitToNodes()` | Changes zoom (fit) |
| Zoom In / Zoom Out buttons | `reactFlow.zoomIn()` / `zoomOut()` | Changes zoom (step) |
| Add event node | `panToNodeIfOffscreen(id)` | Preserves zoom |
| Insert action on edge | `panToNodeIfOffscreen(id)` | Preserves zoom |
| Quick-add successor | `panToNodeIfOffscreen(id)` | Preserves zoom |
| Condition-first quick-add | `fitToNodePair(src, placeholder)` | Changes zoom (fit pair) |
| Search result selected | `focusNode(id)` | Preserves zoom |
| Plugin `focusNode()` | `focusNode(id)` | Preserves zoom |
| Plugin `fitView()` | `fitToNodes()` | Changes zoom (fit) |

---

## Node Positioning on Edge Insertion

When a node is inserted on an existing edge (splitting it into two
edges), the modeler does **not** run a full auto-layout.  Instead, a
targeted positioning algorithm is used:

### `positionInsertedNode` (in `useNodeEdgeActions.ts`)

1. **Position the new node** at the source node's X coordinate, one
   `NODE_SPACING_Y` (84px) below the source node's bottom edge.

2. **Check vertical space** between the source node's bottom and the
   target node's top.  The minimum needed is:
   `NODE_SPACING_Y + NODE_HEIGHT + NODE_SPACING_Y` = 84 + 120 + 84 = 288px.

3. **If insufficient space**, compute the shortfall and shift the target
   node **and all its graph descendants** downward by exactly that amount.
   All other nodes (upstream, sibling flows) remain untouched.

4. **If sufficient space**, no nodes are moved — the new node simply
   slots in.

### What does NOT trigger repositioning

- **Adding a condition node on an edge**: A condition node is inserted
  on the edge, and the standard node positioning system handles placement.
  The inserted node uses the same `panToNodeIfOffscreen()` behavior as
  other node insertions.
- **The explicit Auto Layout button**: This intentionally runs the full
  `autoLayout()` algorithm from `modelUtils.ts`.

### Descendant collection

The `collectDescendants(startId, edges)` helper performs a BFS traversal
of the directed graph to find all nodes reachable from the target.  This
ensures that when the target is shifted down, its entire subtree moves
with it, maintaining relative positions.

---

## Constants

All viewport-related constants live in `src/constants/dimensions.ts`:

### VIEWPORT

| Constant | Value | Purpose |
|----------|-------|---------|
| `DEFAULT_ZOOM` | 1 | Starting zoom level |
| `MIN_ZOOM` | 0.1 | Minimum allowed zoom |
| `MAX_ZOOM` | 4 | Maximum allowed zoom |
| `FIT_MAX_ZOOM` | 1.5 | Cap for `fitToNodes` / `fitView` (prevents over-zooming) |
| `FIT_VIEW_PADDING` | 0.1 | 10% padding around nodes when fitting |
| `AUTO_LAYOUT_PADDING` | 0.2 | 20% padding for auto-layout operations |
| `TOP_ALIGN_OFFSET` | 150 | Pixels from top for `topAlignNode` positioning |
| `PAN_ANIMATION_DURATION` | 800 | Animation duration in milliseconds |

### LAYOUT (positioning-related)

| Constant | Value | Purpose |
|----------|-------|---------|
| `NODE_SPACING_Y` | 84 | Vertical gap between rows |
| `NODE_SPACING_X` | 250 | Horizontal spacing between flows |
| `GRID_SIZE` | 20 | Snap-to-grid increment |

### NODE_DIMENSIONS

| Constant | Value | Purpose |
|----------|-------|---------|
| `CARD_WIDTH` | 180 | Uniform card width for all node types |
| `CARD_HEIGHT` | 120 | Uniform card height for all node types |

---

## Accessibility

### `prefers-reduced-motion`

The `useViewportActions` hook checks
`window.matchMedia('(prefers-reduced-motion: reduce)')` on every
operation.  When the user has reduced motion enabled:

- All animation durations are set to **0** (instant transitions).
- The viewport still moves to the correct position; it just does not
  animate.

This follows WCAG 2.1 SC 2.3.3 (Animation from Interactions).

---

## Deferred Execution

ReactFlow takes time to initialize.  If a viewport operation is
requested before `setReady()` is called, it is stored in a `pendingRef`
and applied once readiness is signaled.

**Flow:**

1. `useModelDataLoader` parses model data and calls `fitToNodes()` or
   `selectAndFocus()`.
2. `useViewportActions` detects `ready === false` and stores the
   operation in `pendingRef`.
3. ReactFlow fires its `onInit` callback → `Flow.tsx` calls
   `viewportActions.setReady()`.
4. The `useEffect` in `useViewportActions` fires, reads `pendingRef`,
   waits `SYNC_DELAY` (100ms) for React to flush state, then executes
   the operation.
5. `pendingRef` is cleared so it only fires once.

---

## Removed (historical)

The following were removed in the viewport unification refactoring
(April 2026, issue #3586972):

- **`useViewportStore`** — Zustand store for viewport targets.  Replaced
  by internal state in `useViewportActions`.
- **`useViewportEffects`** — Effects hook that processed viewport
  targets.  Logic folded into `useViewportActions`.
- **`ViewportTarget` type** — The `center | fit | top-align | none`
  discriminated union.  Replaced by explicit method calls.
- **`VIEWPORT.AUTO_CENTER_ZOOM`** (1.2) — No longer needed; zoom is
  preserved.
- **`VIEWPORT.FIT_VIEW_ZOOM`** (1.5) — Renamed to `FIT_MAX_ZOOM` with
  clearer intent.
- **`TIMING.VIEWPORT_PAN_DURATION`** (800) — Duplicate of
  `VIEWPORT.PAN_ANIMATION_DURATION`.
- **`TIMING.VIEWPORT_EFFECT_DELAY`** (0) — No longer needed.
- **`TIMING.VIEWPORT_READY_DELAY`** (200) — Folded into deferred
  execution.
- **Full `autoLayout()` on edge operations** — Replaced by targeted
  `positionInsertedNode()` that only shifts descendants downward.
