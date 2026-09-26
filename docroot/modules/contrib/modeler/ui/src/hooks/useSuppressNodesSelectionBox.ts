/**
 * Suppress React Flow's NodesSelection bounding box for rubber-band
 * (mouse-drag) multi-selection so it behaves identically to Shift+click
 * multi-selection (modeler issue #3589101).
 *
 * Background — why this hook exists:
 *
 * In React Flow v11 the two multi-select gestures end in DIFFERENT internal
 * states:
 *
 *  - Shift+click goes through `handleNodeClick`, which explicitly sets
 *    `nodesSelectionActive: false` — so NO `<NodesSelection>` box renders.
 *  - The rubber-band (selectionOnDrag) path ends in the Pane's `onMouseUp`,
 *    which sets `nodesSelectionActive: prevSelectedNodesCount.current > 0`.
 *    With one or more nodes captured, that flag becomes `true` and React Flow
 *    renders the `<NodesSelection>` bounding box around the selection.
 *
 * The selection itself (per-node `selected` flags + the `onSelectionChange`
 * event that feeds the selection store, the property panel, and the Copy
 * button) is identical for both gestures. The ONLY divergence is this
 * `nodesSelectionActive` flag and its bounding-box overlay.
 *
 * React Flow fires `onSelectionEnd` right AFTER it sets `nodesSelectionActive`
 * in `onMouseUp`, so resetting the flag here lands the rubber-band gesture in
 * exactly the same state Shift+click produces — no bounding box, same store
 * selection, same Copy enablement.
 */

import { useCallback } from 'react';
import { useStoreApi } from 'reactflow';

interface UseSuppressNodesSelectionBoxResult {
  /**
   * Pass to `<ReactFlow onSelectionEnd={...} />`. Fires when a rubber-band
   * selection gesture completes; clears the NodesSelection bounding box.
   */
  onSelectionEnd: () => void;
}

export function useSuppressNodesSelectionBox(): UseSuppressNodesSelectionBoxResult {
  const store = useStoreApi();

  const onSelectionEnd = useCallback(() => {
    // Only write when the box was actually activated, to avoid redundant
    // store updates (and the extra renders they would cause) on gestures
    // that selected nothing.
    if (store.getState().nodesSelectionActive) {
      store.setState({ nodesSelectionActive: false });
    }
  }, [store]);

  return { onSelectionEnd };
}
