/**
 * Tests for useSuppressNodesSelectionBox (modeler issue #3589101).
 *
 * Mouse-drag (rubber-band) multi-select must behave identically to
 * Shift+click multi-select. React Flow v11 diverges in ONE place: on the
 * rubber-band mouse-up it sets `nodesSelectionActive = true`, which renders
 * the unwanted `<NodesSelection>` bounding box. Shift+click instead sets it
 * `false` (handleNodeClick). This hook resets `nodesSelectionActive` to
 * `false` when a rubber-band selection ends, so the two paths converge.
 */

import { renderHook, act } from '@testing-library/react';

// Controllable mock of React Flow's internal store API. React Flow's real
// useStoreApi returns a STABLE reference across renders, so the mock must too
// (otherwise the hook's useCallback dependency would change every render).
const mockSetState = jest.fn();
const mockGetState = jest.fn();
const mockStore = { setState: mockSetState, getState: mockGetState };

jest.mock('reactflow', () => ({
  useStoreApi: () => mockStore,
}));

import { useSuppressNodesSelectionBox } from '../useSuppressNodesSelectionBox';

describe('useSuppressNodesSelectionBox', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Default: the rubber band ended with at least one selected node, so
    // React Flow would have just set nodesSelectionActive = true.
    mockGetState.mockReturnValue({ nodesSelectionActive: true });
  });

  it('returns an onSelectionEnd handler', () => {
    const { result } = renderHook(() => useSuppressNodesSelectionBox());
    expect(typeof result.current.onSelectionEnd).toBe('function');
  });

  it('resets nodesSelectionActive to false when a drag-select ends', () => {
    const { result } = renderHook(() => useSuppressNodesSelectionBox());

    act(() => {
      result.current.onSelectionEnd();
    });

    // The bounding box must be suppressed — exactly the state Shift+click
    // leaves behind (handleNodeClick sets nodesSelectionActive: false).
    expect(mockSetState).toHaveBeenCalledWith({ nodesSelectionActive: false });
  });

  it('does not touch the store when the box is already inactive', () => {
    // If React Flow did not activate the box (e.g. zero nodes selected),
    // there is nothing to suppress — avoid a redundant store write that
    // would trigger extra renders.
    mockGetState.mockReturnValue({ nodesSelectionActive: false });
    const { result } = renderHook(() => useSuppressNodesSelectionBox());

    act(() => {
      result.current.onSelectionEnd();
    });

    expect(mockSetState).not.toHaveBeenCalled();
  });

  it('returns a stable handler reference across renders', () => {
    const { result, rerender } = renderHook(() => useSuppressNodesSelectionBox());
    const first = result.current.onSelectionEnd;
    rerender();
    expect(result.current.onSelectionEnd).toBe(first);
  });
});
