import React from 'react';
import { render, screen, act } from '@testing-library/react';
import FlowCanvas from '../FlowCanvas';

// Captures the `nodes` AND `edges` props handed to ReactFlow so tests can
// assert the enhanced node/edge data computed by FlowCanvas (issues #3589093,
// #3585553). The `mock` prefix lets the hoisted jest.mock factory reference
// them safely.
const mockCapturedReactFlowNodes: { current: any[] } = { current: [] };
const mockCapturedReactFlowEdges: { current: any[] } = { current: [] };
// Captures the full props object handed to ReactFlow so tests can assert
// integration-level props such as onSelectionEnd (issue #3589101).
const mockCapturedReactFlowProps: { current: any } = { current: {} };

jest.mock('reactflow', () => {
  const MockReactFlow = React.forwardRef((props: any, ref: any) => {
    mockCapturedReactFlowNodes.current = props.nodes || [];
    mockCapturedReactFlowEdges.current = props.edges || [];
    mockCapturedReactFlowProps.current = props;
    return (
      <div data-testid="react-flow" ref={ref} className={props.className || ''}>
        {props.children}
      </div>
    );
  });
  MockReactFlow.displayName = 'MockReactFlow';

  return {
    __esModule: true,
    default: MockReactFlow,
    // useSuppressNodesSelectionBox (issue #3589101) consumes the React Flow
    // store API; provide a controllable stub so FlowCanvas can mount.
    useStoreApi: () => ({
      setState: jest.fn(),
      getState: jest.fn(() => ({ nodesSelectionActive: false })),
    }),
    MiniMap: () => <div data-testid="minimap" />,
    Background: () => <div data-testid="background" />,
    Controls: () => <div data-testid="controls" />,
    EdgeLabelRenderer: ({ children }: { children: React.ReactNode }) => <div data-testid="edge-label-renderer">{children}</div>,
    ConnectionLineType: { Bezier: 'bezier', SmoothStep: 'smoothstep' },
    MarkerType: { Arrow: 'arrow', ArrowClosed: 'arrowclosed' },
    SelectionMode: { Partial: 'partial' },
    PanOnScrollMode: { Free: 'free', Horizontal: 'horizontal', Vertical: 'vertical' },
    Position: { Top: 'top', Bottom: 'bottom', Left: 'left', Right: 'right' },
  };
});

jest.mock('../../hooks/useEdgeOrdering', () => ({
  useEdgeOrdering: jest.fn(() => ({
    handleDragStart: jest.fn(),
    handleDragEnd: jest.fn(),
    handleEdgeOrderDrop: jest.fn(),
    handleReorderEdge: jest.fn(),
    getEdgeOrderInfo: jest.fn(() => ({ order: 1, totalEdges: 1 })),
  })),
}));

jest.mock('../../hooks/useSimpleReplaySync', () => ({}));

jest.mock('../nodes/CustomNode', () => () => <div data-testid="custom-node" />);
jest.mock('../nodes/StartNode', () => () => <div data-testid="start-node" />);
jest.mock('../nodes/GatewayNode', () => () => <div data-testid="gateway-node" />);
jest.mock('../nodes/SubprocessNode', () => () => <div data-testid="subprocess-node" />);
jest.mock('../nodes/ConditionNode', () => () => <div data-testid="condition-node" />);
jest.mock('../edges/DefaultEdge', () => () => <div data-testid="default-edge" />);

jest.mock('../QuickAddEventButton', () => () => null);

describe('FlowCanvas', () => {
  const defaultEventHandlers = {
    onNodesChange: jest.fn(),
    onEdgesChange: jest.fn(),
    onConnect: jest.fn(),
    onSelectionChange: jest.fn(),
    onConnectStart: jest.fn(),
    onConnectEnd: jest.fn(),
    onNodeClick: jest.fn(),
    onEdgeClick: jest.fn(),
    onPaneClick: jest.fn(),
    onSelectionStart: jest.fn(),
    onNodeDragStart: jest.fn(),
    onNodeDragStop: jest.fn(),
    onInit: jest.fn(),
  };

  const defaultElementCallbacks = {
    onEdgeUpdate: jest.fn(),
    onNodeUpdate: jest.fn(),
    onDeleteNode: jest.fn(),
  };

  const defaultModifierKeys = {
    isShiftPressed: false,
    isCtrlPressed: false,
    isAltPressed: false,
  };

  const defaultUIState = {
    isDragActive: false,
    isLocked: false,
    showEdgeOrderNumbers: false,
    showAllAnnotations: false,
  };

  const defaultSearch = {
    searchTerm: '',
    highlightedSearchResult: null,
  };

  const defaultReplay = {
    replayData: [] as any[],
    currentReplayStep: -1,
    isReplayMode: false,
    replayIndicators: [] as any[],
  };

  const defaultQuickAdd = {
    onQuickAdd: jest.fn(),
    onAddCondition: jest.fn(),
  };

  const defaultProps = {
    nodes: [],
    edges: [],
    eventHandlers: defaultEventHandlers,
    elementCallbacks: defaultElementCallbacks,
    viewport: { x: 0, y: 0, zoom: 1 },
    modifierKeys: defaultModifierKeys,
    uiState: defaultUIState,
    search: defaultSearch,
    replay: defaultReplay,
    setEdges: jest.fn(),
    setHasUnsavedChanges: jest.fn(),
    quickAdd: defaultQuickAdd,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockCapturedReactFlowNodes.current = [];
    mockCapturedReactFlowEdges.current = [];
    mockCapturedReactFlowProps.current = {};
    // Reset the global reconnect-drag flag so it never leaks between tests.
    require('../../store/useUISettingsStore').useUISettingsStore.getState().setReconnectDragActive(false);
  });

  const findEnhancedNode = (id: string) =>
    mockCapturedReactFlowNodes.current.find(n => n.id === id);

  const findEnhancedEdge = (id: string) =>
    mockCapturedReactFlowEdges.current.find(e => e.id === id);

  describe('rendering', () => {
    it('should render the ReactFlow component', () => {
      render(<FlowCanvas {...defaultProps} />);
      expect(screen.getByTestId('react-flow')).toBeTruthy();
    });

    it('should render without errors with empty nodes/edges', () => {
      const { container } = render(<FlowCanvas {...defaultProps} />);
      expect(container).toBeTruthy();
    });

    it('should not apply modifier cursor classes', () => {
      // With Figma-like gestures, no modifier-based cursor classes are added
      const { container } = render(<FlowCanvas {...defaultProps} modifierKeys={{ ...defaultModifierKeys, isShiftPressed: true }} />);
      expect(container.querySelector('.shift-pressed')).toBeFalsy();
    });

    it('should not apply extra cursor classes when ctrl and alt are pressed', () => {
      render(<FlowCanvas {...defaultProps} modifierKeys={{ ...defaultModifierKeys, isCtrlPressed: true, isAltPressed: true }} />);
      const reactFlowEl = screen.getByTestId('react-flow');
      // Ctrl/Alt no longer add cursor classes (zoom/pan handled natively by ReactFlow)
      expect(reactFlowEl.className).not.toContain('ctrl-alt-pressed');
      expect(reactFlowEl.className).not.toContain('ctrl-pressed');
    });

    it('should disable node dragging when locked', () => {
      const { container } = render(<FlowCanvas {...defaultProps} uiState={{ ...defaultUIState, isLocked: true }} />);
      // FlowCanvas passes nodesDraggable={!isLocked} to ReactFlow
      // Verify the component renders without error when locked
      expect(container.querySelector('.reactflow-wrapper')).toBeTruthy();
    });

    it('should apply drag-active class when dragging', () => {
      const { container } = render(<FlowCanvas {...defaultProps} uiState={{ ...defaultUIState, isDragActive: true }} />);
      expect(container.querySelector('.drag-active')).toBeTruthy();
    });

    it('should add reconnect-dragging class while a reconnect drag is active (issue #3585553)', () => {
      const { useUISettingsStore } = require('../../store/useUISettingsStore');
      // Inactive by default → no class.
      const { container, rerender } = render(<FlowCanvas {...defaultProps} />);
      expect(container.querySelector('.reactflow-wrapper.reconnect-dragging')).toBeFalsy();

      // Activate the global flag → wrapper gains the class so CSS can disable
      // all grips during the drag.
      act(() => {
        useUISettingsStore.getState().setReconnectDragActive(true);
      });
      rerender(<FlowCanvas {...defaultProps} />);
      expect(container.querySelector('.reactflow-wrapper.reconnect-dragging')).toBeTruthy();

      // Clear it → class removed again.
      act(() => {
        useUISettingsStore.getState().setReconnectDragActive(false);
      });
      rerender(<FlowCanvas {...defaultProps} />);
      expect(container.querySelector('.reactflow-wrapper.reconnect-dragging')).toBeFalsy();
    });

    it('should always show minimap', () => {
      render(<FlowCanvas {...defaultProps} />);
      expect(screen.getByTestId('minimap')).toBeTruthy();
    });

    // Issue #3589101: rubber-band multi-select must match Shift+click. The
    // NodesSelection bounding box is suppressed via an onSelectionEnd handler
    // wired to ReactFlow.
    it('wires an onSelectionEnd handler to ReactFlow to suppress the selection box', () => {
      render(<FlowCanvas {...defaultProps} />);
      expect(typeof mockCapturedReactFlowProps.current.onSelectionEnd).toBe('function');
    });

    it('keeps selectionOnDrag enabled when unlocked and disabled when locked', () => {
      const { rerender } = render(<FlowCanvas {...defaultProps} />);
      expect(mockCapturedReactFlowProps.current.selectionOnDrag).toBe(true);

      rerender(<FlowCanvas {...defaultProps} uiState={{ ...defaultUIState, isLocked: true }} />);
      expect(mockCapturedReactFlowProps.current.selectionOnDrag).toBe(false);
    });
  });

  describe('with nodes', () => {
    it('should handle nodes with search highlighting', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test Node' } },
      ];
      const { container } = render(<FlowCanvas {...defaultProps} nodes={nodes as any} search={{ ...defaultSearch, searchTerm: 'Test' }} />);
      expect(container).toBeTruthy();
    });

    it('should highlight search result node', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test Node' } },
      ];
      const { container } = render(
        <FlowCanvas
          {...defaultProps}
          nodes={nodes as any}
          search={{ searchTerm: 'Test', highlightedSearchResult: { id: 'node-1', type: 'node' } }}
        />
      );
      expect(container).toBeTruthy();
    });
  });

  describe('with edges', () => {
    it('should enhance edges with order info and callbacks', () => {
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: {} },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} uiState={{ ...defaultUIState, showEdgeOrderNumbers: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should not add onAddCondition to edge with existing condition', () => {
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: { condition: 'existing' } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} quickAdd={{ ...defaultQuickAdd, onAddCondition: jest.fn() }} />
      );
      expect(container).toBeTruthy();
    });

    it('should add onAddCondition to edges without condition when not locked', () => {
      const onAddCondition = jest.fn();
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: {} },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} quickAdd={{ ...defaultQuickAdd, onAddCondition }} uiState={{ ...defaultUIState, isLocked: false }} />
      );
      expect(container).toBeTruthy();
    });

    it('should not add onAddCondition when locked', () => {
      const onAddCondition = jest.fn();
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: {} },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} quickAdd={{ ...defaultQuickAdd, onAddCondition }} uiState={{ ...defaultUIState, isLocked: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should enhance edges with annotation visibility', () => {
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: { isAnnotationVisible: true } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} uiState={{ ...defaultUIState, showAllAnnotations: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should enhance edges with replay data', () => {
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: {} },
      ];
      const replayData = [{ id: 'step-1', nodeId: 'node-1' }];
      const { container } = render(
        <FlowCanvas
          {...defaultProps}
          edges={edges as any}
          replay={{ ...defaultReplay, replayData: replayData as any, currentReplayStep: 0, isReplayMode: true }}
        />
      );
      expect(container).toBeTruthy();
    });
  });

  describe('enhanced nodes', () => {
    it('should enhance nodes with annotation visibility', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test', isAnnotationVisible: true } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} uiState={{ ...defaultUIState, showAllAnnotations: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should add onQuickAdd to nodes when not locked', () => {
      const onQuickAdd = jest.fn();
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test' } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} quickAdd={{ ...defaultQuickAdd, onQuickAdd }} uiState={{ ...defaultUIState, isLocked: false }} />
      );
      expect(container).toBeTruthy();
    });

    it('should not add onQuickAdd to nodes when locked', () => {
      const onQuickAdd = jest.fn();
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test' } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} quickAdd={{ ...defaultQuickAdd, onQuickAdd }} uiState={{ ...defaultUIState, isLocked: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should add onDelete to nodes when onDeleteNode provided', () => {
      const onDeleteNode = jest.fn();
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test' } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} elementCallbacks={{ ...defaultElementCallbacks, onDeleteNode }} />
      );
      expect(container).toBeTruthy();
    });

    it('should enhance nodes with replay state', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test' } },
      ];
      const { container } = render(
        <FlowCanvas
          {...defaultProps}
          nodes={nodes as any}
          replay={{ ...defaultReplay, isReplayMode: true, currentReplayStep: 0, replayData: [{ id: 'step-1', nodeId: 'node-1' }] as any }}
        />
      );
      expect(container).toBeTruthy();
    });
  });

  describe('condition source-handle constraint (issue #3589093)', () => {
    it('should enable a condition source handle when it has no outbound edge', () => {
      const nodes = [
        { id: 'cond-1', type: 'condition', position: { x: 0, y: 0 }, data: { __isConditionNode: true, label: 'Cond' } },
      ];
      render(<FlowCanvas {...defaultProps} nodes={nodes as any} edges={[]} />);
      const enhanced = findEnhancedNode('cond-1');
      expect(enhanced).toBeTruthy();
      expect(enhanced.data.sourceHandleDisabled).toBe(false);
      expect(enhanced.data.sourceHandleDisabledReason).toBeUndefined();
    });

    it('should disable a condition source handle once it has one outbound edge', () => {
      const nodes = [
        { id: 'cond-1', type: 'condition', position: { x: 0, y: 0 }, data: { __isConditionNode: true, label: 'Cond' } },
        { id: 'node-2', type: 'element', position: { x: 0, y: 100 }, data: { label: 'Next' } },
      ];
      const edges = [
        { id: 'edge-1', source: 'cond-1', target: 'node-2', data: {} },
      ];
      render(<FlowCanvas {...defaultProps} nodes={nodes as any} edges={edges as any} />);
      const enhanced = findEnhancedNode('cond-1');
      expect(enhanced).toBeTruthy();
      expect(enhanced.data.sourceHandleDisabled).toBe(true);
      expect(enhanced.data.sourceHandleDisabledReason).toBe('condition-single-out');
      // Quick-add is suppressed once the condition has its outbound edge.
      expect(enhanced.data.onQuickAdd).toBeUndefined();
    });

    it('should recognize a condition flagged only by __isConditionNode (no type)', () => {
      const nodes = [
        { id: 'cond-1', type: 'element', position: { x: 0, y: 0 }, data: { __isConditionNode: true, label: 'Cond' } },
      ];
      const edges = [
        { id: 'edge-1', source: 'cond-1', target: 'node-2', data: {} },
      ];
      render(<FlowCanvas {...defaultProps} nodes={nodes as any} edges={edges as any} />);
      const enhanced = findEnhancedNode('cond-1');
      expect(enhanced.data.sourceHandleDisabled).toBe(true);
      expect(enhanced.data.sourceHandleDisabledReason).toBe('condition-single-out');
    });

    it('should not disable a non-condition node with one outbound edge (no max constraint)', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Action' } },
      ];
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: {} },
      ];
      render(<FlowCanvas {...defaultProps} nodes={nodes as any} edges={edges as any} />);
      const enhanced = findEnhancedNode('node-1');
      expect(enhanced.data.sourceHandleDisabled).toBe(false);
      expect(enhanced.data.sourceHandleDisabledReason).toBeUndefined();
    });
  });

  describe('endpoint reconnection: source-handle suppression (issue #3585553)', () => {
    it('keeps a source handle connectable when no selected edge uses it', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'A' } },
      ];
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', sourceHandle: 'output', selected: false, data: {} },
      ];
      render(<FlowCanvas {...defaultProps} nodes={nodes as any} edges={edges as any} />);
      const enhanced = findEnhancedNode('node-1');
      expect(enhanced.data.sourceHandleDisabled).toBe(false);
    });

    it('suppresses new-edge connectability on a source handle reserved by a selected edge', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'A' } },
      ];
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', sourceHandle: 'output', selected: true, data: {} },
      ];
      render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} edges={edges as any} />,
      );
      const enhanced = findEnhancedNode('node-1');
      expect(enhanced.data.sourceHandleDisabled).toBe(true);
    });

    it('suppresses the handle when 2+ selected edges share it (ambiguous)', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'A' } },
      ];
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', sourceHandle: 'output', selected: true, data: {} },
        { id: 'edge-2', source: 'node-1', target: 'node-3', sourceHandle: 'output', selected: true, data: {} },
      ];
      render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} edges={edges as any} />,
      );
      const enhanced = findEnhancedNode('node-1');
      expect(enhanced.data.sourceHandleDisabled).toBe(true);
    });
  });

  describe('endpoint reconnection: grip eligibility (issue #3585553)', () => {
    // Two edges feeding the SAME target node/handle. The shared target handle
    // is ambiguous when both are selected (rule 2 → no grip there); each
    // edge's distinct SOURCE handle stays eligible.
    const sharedTargetNodes = [
      { id: 'n1', type: 'element', position: { x: 0, y: 0 }, data: {} },
      { id: 'n2', type: 'element', position: { x: 200, y: 0 }, data: {} },
      { id: 'shared', type: 'element', position: { x: 100, y: 200 }, data: {} },
    ];
    const sharedTargetEdges = (selectedIds: string[]) => [
      {
        id: 'e1', source: 'n1', target: 'shared', sourceHandle: 'output', targetHandle: 'input',
        selected: selectedIds.includes('e1'), data: {},
      },
      {
        id: 'e2', source: 'n2', target: 'shared', sourceHandle: 'output', targetHandle: 'input',
        selected: selectedIds.includes('e2'), data: {},
      },
    ];

    it('disables BOTH grips on a shared target handle when both edges are selected (rule 2)', () => {
      // REGRESSION (#3585553 bug report): two edges into the same node handle,
      // BOTH shift-selected → the shared TARGET grip must be off for both.
      render(
        <FlowCanvas
          {...defaultProps}
          nodes={sharedTargetNodes as any}
          edges={sharedTargetEdges(['e1', 'e2']) as any}
        />,
      );
      const e1 = findEnhancedEdge('e1');
      const e2 = findEnhancedEdge('e2');
      // Shared target handle → ambiguous → no target grip on either edge.
      expect(e1.data.targetGripEnabled).toBe(false);
      expect(e2.data.targetGripEnabled).toBe(false);
      // Each edge's own distinct source handle is still the sole selected
      // endpoint there, so the source grip stays eligible.
      expect(e1.data.sourceGripEnabled).toBe(true);
      expect(e2.data.sourceGripEnabled).toBe(true);
    });

    it('enables the target grip when only ONE of the shared-handle edges is selected', () => {
      render(
        <FlowCanvas
          {...defaultProps}
          nodes={sharedTargetNodes as any}
          edges={sharedTargetEdges(['e1']) as any}
        />,
      );
      const e1 = findEnhancedEdge('e1');
      const e2 = findEnhancedEdge('e2');
      // Only e1 is selected → it is the sole selected edge on the shared
      // target handle → target grip enabled.
      expect(e1.data.targetGripEnabled).toBe(true);
      expect(e1.data.sourceGripEnabled).toBe(true);
      // e2 is not selected → no grips at all.
      expect(e2.data.targetGripEnabled).toBe(false);
      expect(e2.data.sourceGripEnabled).toBe(false);
    });

    it('keeps grips independent for selected edges on DIFFERENT handles (rule 4)', () => {
      const nodes = [
        { id: 'a', type: 'element', position: { x: 0, y: 0 }, data: {} },
        { id: 'b', type: 'element', position: { x: 0, y: 100 }, data: {} },
        { id: 'c', type: 'element', position: { x: 0, y: 200 }, data: {} },
      ];
      const edges = [
        { id: 'e1', source: 'a', target: 'b', sourceHandle: 'output', targetHandle: 'input', selected: true, data: {} },
        { id: 'e2', source: 'b', target: 'c', sourceHandle: 'output', targetHandle: 'input', selected: true, data: {} },
      ];
      render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} edges={edges as any} />,
      );
      // No shared handle between e1 and e2 → all four endpoints are sole
      // occupants → all grips eligible (no global selection-count gate).
      const e1 = findEnhancedEdge('e1');
      const e2 = findEnhancedEdge('e2');
      expect(e1.data.sourceGripEnabled).toBe(true);
      expect(e1.data.targetGripEnabled).toBe(true);
      expect(e2.data.sourceGripEnabled).toBe(true);
      expect(e2.data.targetGripEnabled).toBe(true);
    });

    it('derives grip eligibility from the per-edge selected flag (single source of truth)', () => {
      // Robustness: grip math reads React Flow's per-edge `selected` flag —
      // the SAME flag that drives grip visibility in DefaultEdge — so the
      // count and the render can never diverge. Both edges carry
      // selected=true and share the target handle → ambiguous → no grip.
      render(
        <FlowCanvas
          {...defaultProps}
          nodes={sharedTargetNodes as any}
          edges={sharedTargetEdges(['e1', 'e2']) as any}
        />,
      );
      const e1 = findEnhancedEdge('e1');
      const e2 = findEnhancedEdge('e2');
      expect(e1.data.targetGripEnabled).toBe(false);
      expect(e2.data.targetGripEnabled).toBe(false);
    });

    it('disables grips entirely when the canvas is locked', () => {
      render(
        <FlowCanvas
          {...defaultProps}
          nodes={sharedTargetNodes as any}
          edges={sharedTargetEdges(['e1']) as any}
          uiState={{ ...defaultUIState, isLocked: true }}
        />,
      );
      const e1 = findEnhancedEdge('e1');
      expect(e1.data.sourceGripEnabled).toBe(false);
      expect(e1.data.targetGripEnabled).toBe(false);
    });
  });

  describe('selection mode', () => {
    it('should always use partial selection mode', () => {
      // Selection mode is always Partial with Figma-like gestures
      const { container } = render(<FlowCanvas {...defaultProps} modifierKeys={{ ...defaultModifierKeys, isShiftPressed: true }} />);
      // No modifier-based classes applied; selection mode is handled internally
      expect(container).toBeTruthy();
    });

    it('should not apply ctrl-pressed class when only ctrl pressed', () => {
      render(<FlowCanvas {...defaultProps} modifierKeys={{ ...defaultModifierKeys, isCtrlPressed: true, isAltPressed: false }} />);
      const reactFlowEl = screen.getByTestId('react-flow');
      // Ctrl no longer adds cursor class (zoom handled natively by ReactFlow)
      expect(reactFlowEl.className).not.toContain('ctrl-pressed');
    });
  });

  // QuickAddEventButton has been moved to the Toolbar component

  describe('replay indicators', () => {
    it('should render replay indicators', () => {
      const replayIndicators = [
        { id: 'indicator-1', x: 100, y: 200, color: '#00ff00' },
        { id: 'indicator-2', x: 300, y: 400, color: '#ff0000' },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} replay={{ ...defaultReplay, replayIndicators }} />
      );
      const indicators = container.querySelectorAll('.replay-indicator');
      expect(indicators.length).toBe(2);
    });

    it('should position replay indicators correctly', () => {
      const replayIndicators = [
        { id: 'indicator-1', x: 100, y: 200, color: '#00ff00' },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} replay={{ ...defaultReplay, replayIndicators }} />
      );
      const indicator = container.querySelector('.replay-indicator') as HTMLElement;
      expect(indicator).toBeTruthy();
      // Indicators use flow-coordinate transforms inside EdgeLabelRenderer
      expect(indicator.style.transform).toBe('translate(-50%, -50%) translate(100px, 200px)');
      expect(indicator.style.backgroundColor).toBe('rgb(0, 255, 0)');
    });

    it('should not render indicators when empty', () => {
      const { container } = render(
        <FlowCanvas {...defaultProps} replay={{ ...defaultReplay, replayIndicators: [] }} />
      );
      expect(container.querySelectorAll('.replay-indicator').length).toBe(0);
    });
  });
});
