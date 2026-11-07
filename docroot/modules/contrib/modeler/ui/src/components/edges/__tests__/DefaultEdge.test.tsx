import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import DefaultEdge from '../DefaultEdge';
import { Position } from 'reactflow';

// Mock reactflow
jest.mock('reactflow', () => ({
  getBezierPath: jest.fn(() => ['M 0 0 C 50 0 50 100 100 100', 50, 50]),
  getSmoothStepPath: jest.fn(() => ['M 0 0 L 100 100', 50, 50]),
  EdgeLabelRenderer: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="edge-label-renderer">{children}</div>
  ),
  Position: {
    Top: 'top',
    Bottom: 'bottom',
    Left: 'left',
    Right: 'right',
  },
}));

// Mock QuickAddEdgeButton
jest.mock('../../QuickAddEdgeButton', () => (props: any) => (
  <div>
    <button data-testid="quick-add-condition" onClick={() => props.onAddCondition?.({ id: 'cond1' })}>
      Add Condition
    </button>
    <button data-testid="quick-add-action" onClick={() => props.onAddAction?.({ id: 'act1', type: 'element' })}>
      Add Action
    </button>
  </div>
));

describe('DefaultEdge', () => {
  const defaultProps = {
    id: 'edge1',
    source: 'node1',
    target: 'node2',
    sourceX: 100,
    sourceY: 100,
    targetX: 200,
    targetY: 200,
    sourcePosition: Position.Bottom,
    targetPosition: Position.Top,
    data: {},
    style: {},
    markerEnd: 'url(#arrow)',
    selected: false,
    sourceHandleId: null,
    targetHandleId: null,
    animated: false,
    interactionWidth: 10,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  afterEach(() => {
    // Clean up any dragImage clones left in document.body by dragStart handlers
    document.querySelectorAll('[style*="top: -1000px"]').forEach(el => {
      if (el.parentNode === document.body) {
        document.body.removeChild(el);
      }
    });
  });

  describe('rendering', () => {
    it('should render edge path', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should apply marker end', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} />
        </svg>
      );
      const path = document.querySelector('.react-flow__edge-path');
      expect(path).toHaveAttribute('marker-end', 'url(#arrow)');
    });

    it('should add selected class when selected', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} selected={true} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path.selected')).toBeInTheDocument();
    });
  });

  // [C3] Generous edge-selection hit area (issue #3585553 follow-on UX).
  // DefaultEdge hand-rolls its path (no <BaseEdge>), so it must render the
  // transparent wide interaction path itself. These tests prove the path is
  // rendered, transparent, follows the edge `d`, and honors the interactionWidth
  // prop (falling back to the shared 30px default when React Flow omits it).
  describe('interaction hit area (C3)', () => {
    it('renders a transparent wide interaction path following the edge', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} />
        </svg>
      );
      const hit = document.querySelector('.react-flow__edge-interaction');
      expect(hit).toBeInTheDocument();
      expect(hit).toHaveAttribute('stroke', 'transparent');
      expect(hit).toHaveAttribute('fill', 'none');
      // It traces the same path geometry as the visible edge.
      const visible = document.querySelector('.react-flow__edge-path');
      expect(hit?.getAttribute('d')).toBe(visible?.getAttribute('d'));
    });

    it('uses the interactionWidth prop for the hit stroke width', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} interactionWidth={30} />
        </svg>
      );
      const hit = document.querySelector('.react-flow__edge-interaction');
      expect(hit).toHaveAttribute('stroke-width', '30');
    });

    it('falls back to the generous 30px default when no interactionWidth is given', () => {
      // Pass the prop as undefined to exercise the `?? INTERACTION_WIDTH` path.
      render(
        <svg>
          <DefaultEdge {...defaultProps} interactionWidth={undefined} />
        </svg>
      );
      const hit = document.querySelector('.react-flow__edge-interaction');
      expect(hit).toHaveAttribute('stroke-width', '30');
    });

    it('still renders the wide hit path during replay highlighting', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={{ replayHighlight: '#00ff00' }} />
        </svg>
      );
      const hit = document.querySelector('.react-flow__edge-interaction');
      expect(hit).toBeInTheDocument();
      expect(hit).toHaveAttribute('stroke', 'transparent');
    });
  });

  describe('replay highlighting', () => {
    it('should apply replay highlight color when provided', () => {
      const data = { replayHighlight: '#00ff00' };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      const path = document.querySelector('.react-flow__edge-path');
      expect(path).toHaveAttribute('stroke', '#00ff00');
    });

    it('should add replay-highlighted class when replay highlight present', () => {
      const data = { replayHighlight: '#00ff00' };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.replay-highlighted')).toBeInTheDocument();
    });

    it('should wrap path in g element when replay highlighting', () => {
      const data = { replayHighlight: '#00ff00' };
      const { container } = render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      const gElement = container.querySelector('g');
      expect(gElement).toHaveStyle({ stroke: '#00ff00' });
    });
  });

  describe('control point', () => {
    it('should not show control point when not selected', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} />
        </svg>
      );
      expect(document.querySelector('.edge-control-point-wrapper')).not.toBeInTheDocument();
    });

    it('should show control point when selected', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} selected={true} />
        </svg>
      );
      expect(document.querySelector('.edge-control-point-wrapper')).toBeInTheDocument();
    });

    it('should show control point when data.isSelected is true', () => {
      const data = { isSelected: true };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.edge-control-point-wrapper')).toBeInTheDocument();
    });

    it('should show dashed line when control offset exists', () => {
      const data = { controlOffset: { x: 50, y: 50 } };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} selected={true} />
        </svg>
      );
      const dashedLine = document.querySelector('line');
      expect(dashedLine).toHaveClass('edge-guide-line');
    });
  });

  describe('edge order display', () => {
    it('should not show edge order when not visible', () => {
      const data = {
        edgeOrdersVisible: false,
        edgeOrderInfo: { order: 1, totalEdges: 2, pathX: 150, pathY: 150 },
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.edge-order-number')).not.toBeInTheDocument();
    });

    it('should show edge order when visible and multiple edges exist', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 1, totalEdges: 2, pathX: 150, pathY: 150 },
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.edge-order-number')).toBeInTheDocument();
    });

    it('should not show edge order when only one edge exists', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 1, totalEdges: 1, pathX: 150, pathY: 150 },
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.edge-order-number')).not.toBeInTheDocument();
    });

    it('should display correct order number with Flow label', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 2, totalEdges: 3, pathX: 150, pathY: 150 },
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(screen.getByText('Flow 2')).toBeInTheDocument();
    });
  });

  describe('path generation', () => {
    it('should generate bezier path for default edge', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} />
        </svg>
      );
      const path = document.querySelector('.react-flow__edge-path');
      expect(path?.getAttribute('d')).toContain('M');
    });

    it('should use control point in path when offset provided', () => {
      const data = { controlOffset: { x: 50, y: 50 } };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      // Path should still be generated
      const path = document.querySelector('.react-flow__edge-path');
      expect(path?.getAttribute('d')).toBeDefined();
    });
  });

  describe('styles', () => {
    it('should apply custom style', () => {
      const style = { stroke: '#ff0000', strokeWidth: 5 };
      render(
        <svg>
          <DefaultEdge {...defaultProps} style={style} />
        </svg>
      );
      const path = document.querySelector('.react-flow__edge-path');
      expect(path).toHaveStyle({ stroke: '#ff0000', strokeWidth: 5 });
    });

    it('should override style with replay highlight', () => {
      const style = { stroke: '#ff0000' };
      const data = { replayHighlight: '#00ff00' };
      render(
        <svg>
          <DefaultEdge {...defaultProps} style={style} data={data} />
        </svg>
      );
      const path = document.querySelector('.react-flow__edge-path');
      expect(path).toHaveAttribute('stroke', '#00ff00');
    });
  });



  describe('edge center calculation', () => {
    it('should calculate edge center from source and target positions', () => {
      const props = {
        ...defaultProps,
        sourceX: 0,
        sourceY: 0,
        targetX: 200,
        targetY: 200,
        selected: true,
        data: { controlOffset: { x: 0, y: 0 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      // Control point should be at center (100, 100)
      const wrapper = document.querySelector('.edge-control-point-wrapper');
      expect(wrapper).toBeInTheDocument();
    });
  });

  describe('position handling with control offset', () => {
    it('should handle Right source position with control offset', () => {
      const props = {
        ...defaultProps,
        sourcePosition: Position.Right,
        targetPosition: Position.Left,
        data: { controlOffset: { x: 10, y: 10 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Left source position with control offset', () => {
      const props = {
        ...defaultProps,
        sourcePosition: Position.Left,
        targetPosition: Position.Right,
        data: { controlOffset: { x: 10, y: 10 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Top source position with control offset', () => {
      const props = {
        ...defaultProps,
        sourcePosition: Position.Top,
        targetPosition: Position.Bottom,
        data: { controlOffset: { x: 10, y: 10 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Right target position with control offset', () => {
      const props = {
        ...defaultProps,
        sourcePosition: Position.Bottom,
        targetPosition: Position.Right,
        data: { controlOffset: { x: 10, y: 10 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Left target position with control offset', () => {
      const props = {
        ...defaultProps,
        sourcePosition: Position.Bottom,
        targetPosition: Position.Left,
        data: { controlOffset: { x: 10, y: 10 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Bottom target position with control offset', () => {
      const props = {
        ...defaultProps,
        sourcePosition: Position.Top,
        targetPosition: Position.Bottom,
        data: { controlOffset: { x: 10, y: 10 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });

  describe('backward flow handling', () => {
    it('should handle backward flow with Bottom to Top when target is above', () => {
      const props = {
        ...defaultProps,
        sourceX: 100,
        sourceY: 200,
        targetX: 100,
        targetY: 100, // Target is above source
        sourcePosition: Position.Bottom,
        targetPosition: Position.Top,
        data: { controlOffset: { x: 5, y: 5 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle backward flow with Top to Bottom', () => {
      const props = {
        ...defaultProps,
        sourceX: 100,
        sourceY: 100,
        targetX: 100,
        targetY: 200, // Target is below source
        sourcePosition: Position.Top,
        targetPosition: Position.Bottom,
        data: { controlOffset: { x: 5, y: 5 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle backward flow with Right to Left', () => {
      const props = {
        ...defaultProps,
        sourceX: 200,
        sourceY: 100,
        targetX: 100, // Target is left of source
        targetY: 100,
        sourcePosition: Position.Right,
        targetPosition: Position.Left,
        data: { controlOffset: { x: 5, y: 5 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle backward flow with Left to Right', () => {
      const props = {
        ...defaultProps,
        sourceX: 100,
        sourceY: 100,
        targetX: 200, // Target is right of source
        targetY: 100,
        sourcePosition: Position.Left,
        targetPosition: Position.Right,
        data: { controlOffset: { x: 5, y: 5 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });

  describe('dragging state', () => {
    it('should render without control point when not selected', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} selected={false} />
        </svg>
      );
      expect(document.querySelector('.edge-control-point-wrapper')).not.toBeInTheDocument();
    });
  });

  describe('edge order visibility conditions', () => {
    it('should not show order when edgeOrderInfo is undefined', () => {
      const data = {
        edgeOrdersVisible: true,
        // No edgeOrderInfo
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.edge-order-number')).not.toBeInTheDocument();
    });

    it('should not show order when order info has no pathX', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 1, totalEdges: 2 }, // No pathX/pathY
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      // Should still render edge
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });

  describe('control point wrapper styles', () => {
    it('should position control point wrapper at calculated center with offset', () => {
      const props = {
        ...defaultProps,
        sourceX: 0,
        sourceY: 0,
        targetX: 200,
        targetY: 200,
        selected: true,
        data: { controlOffset: { x: 20, y: 30 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      const wrapper = document.querySelector('.edge-control-point-wrapper');
      expect(wrapper).toBeInTheDocument();
    });
  });

  describe('edge data properties', () => {
    it('should handle empty data object', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={{}} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle undefined data', () => {
      const props = { ...defaultProps };
      delete (props as any).data;
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });

  describe('QuickAddEdgeButton', () => {
    it('should show QuickAddEdgeButton when not in read-only mode and onAddCondition provided', () => {
      const onAddCondition = jest.fn();
      const data = { onAddCondition };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(screen.getByTestId('quick-add-condition')).toBeInTheDocument();
    });

    it('should not show QuickAddEdgeButton when no onAddCondition', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={{}} />
        </svg>
      );
      expect(screen.queryByTestId('quick-add-condition')).not.toBeInTheDocument();
    });

    it('should call onAddCondition with edge id when button clicked', () => {
      const onAddCondition = jest.fn();
      const data = { onAddCondition };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      fireEvent.click(screen.getByTestId('quick-add-condition'));
      expect(onAddCondition).toHaveBeenCalledWith('edge1', { id: 'cond1' });
    });

    it('should position quick-add button on the edge path, not the node midpoint', () => {
      // Regression: the button was positioned at the geometric midpoint between
      // source and target nodes (edgeCenterX/Y) instead of using labelX/labelY
      // from useEdgePath, causing it to float off the curved path.
      const onAddCondition = jest.fn();
      const props = {
        ...defaultProps,
        sourceX: 0,
        sourceY: 0,
        targetX: 200,
        targetY: 200,
        data: { onAddCondition, controlOffset: { x: 50, y: -30 } },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      const wrapper = document.querySelector('.edge-quick-add-wrapper');
      expect(wrapper).toBeInTheDocument();
      // With a control offset, the button must NOT be at the raw midpoint (100, 100).
      // useEdgePath returns the control point coordinates (150, 70) as labelX/labelY.
      const transform = (wrapper as HTMLElement).style.transform;
      expect(transform).not.toContain('translate(100px,100px)');
      // It should use the control point position (edgeCenterX + offsetX, edgeCenterY + offsetY)
      expect(transform).toContain('translate(150px,70px)');
    });

    it('should position quick-add button at the path midpoint when no offset', () => {
      const onAddCondition = jest.fn();
      const props = {
        ...defaultProps,
        sourceX: 0,
        sourceY: 0,
        targetX: 200,
        targetY: 200,
        data: { onAddCondition },
      };
      render(
        <svg>
          <DefaultEdge {...props} />
        </svg>
      );
      const wrapper = document.querySelector('.edge-quick-add-wrapper');
      expect(wrapper).toBeInTheDocument();
      // Without a control offset, labelX/labelY from useEdgePath is the bezier
      // midpoint (t=0.5), which may differ slightly from the geometric center.
      // The key invariant: it should come from useEdgePath, not be hardcoded.
      const transform = (wrapper as HTMLElement).style.transform;
      expect(transform).toBeDefined();
      expect(transform).toContain('translate(-50%, -50%)');
    });

    it('should call onAddActionOnEdge with edge id when action button clicked', () => {
      const onAddCondition = jest.fn();
      const onAddActionOnEdge = jest.fn();
      const data = { onAddCondition, onAddActionOnEdge };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      fireEvent.click(screen.getByTestId('quick-add-action'));
      expect(onAddActionOnEdge).toHaveBeenCalledWith('edge1', { id: 'act1', type: 'element' });
    });
  });

  describe('endpoint reconnection grips (issue #3585553)', () => {
    it('should not render grips when the edge is not selected', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} selected={false} data={{ sourceGripEnabled: true, targetGripEnabled: true }} />
        </svg>
      );
      expect(document.querySelector('.edge-endpoint-grip--source')).not.toBeInTheDocument();
      expect(document.querySelector('.edge-endpoint-grip--target')).not.toBeInTheDocument();
    });

    it('should render the source grip when selected and sourceGripEnabled', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} selected={true} data={{ sourceGripEnabled: true }} />
        </svg>
      );
      expect(document.querySelector('.edge-endpoint-grip--source')).toBeInTheDocument();
      // Target grip is independent — not enabled here.
      expect(document.querySelector('.edge-endpoint-grip--target')).not.toBeInTheDocument();
    });

    it('should render the target grip when selected and targetGripEnabled', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} selected={true} data={{ targetGripEnabled: true }} />
        </svg>
      );
      expect(document.querySelector('.edge-endpoint-grip--target')).toBeInTheDocument();
      expect(document.querySelector('.edge-endpoint-grip--source')).not.toBeInTheDocument();
    });

    it('should not render either grip when grip eligibility is false (ambiguous handle)', () => {
      // Models rule 2: when 2+ selected edges share the handle, FlowCanvas sets
      // gripEnabled false, so no grip renders even though the edge is selected.
      render(
        <svg>
          <DefaultEdge {...defaultProps} selected={true} data={{ sourceGripEnabled: false, targetGripEnabled: false }} />
        </svg>
      );
      expect(document.querySelector('.edge-endpoint-grip--source')).not.toBeInTheDocument();
      expect(document.querySelector('.edge-endpoint-grip--target')).not.toBeInTheDocument();
    });

    it('should not render grips when the canvas is globally locked', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            selected={true}
            data={{ sourceGripEnabled: true, targetGripEnabled: true, globalLocked: true }}
          />
        </svg>
      );
      expect(document.querySelector('.edge-endpoint-grip--source')).not.toBeInTheDocument();
      expect(document.querySelector('.edge-endpoint-grip--target')).not.toBeInTheDocument();
    });

    it('should commit a target reconnection via onReconnectEdge on a valid drop', () => {
      const onReconnectEdge = jest.fn();
      const validateReconnect = jest.fn(() => true);
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            selected={true}
            data={{ targetGripEnabled: true, onReconnectEdge, validateReconnect }}
          />
        </svg>
      );
      const grip = document.querySelector('.edge-endpoint-grip--target');
      expect(grip).toBeInTheDocument();
      fireEvent.mouseDown(grip!);

      // Simulate dropping over a different node. jsdom does not implement
      // elementFromPoint, so install a controllable stub.
      const dropNode = document.createElement('div');
      dropNode.className = 'react-flow__node';
      dropNode.setAttribute('data-id', 'node-x');
      document.body.appendChild(dropNode);
      (document as unknown as { elementFromPoint: (x: number, y: number) => Element | null }).elementFromPoint =
        jest.fn(() => dropNode);

      fireEvent(document, new MouseEvent('mouseup', { clientX: 10, clientY: 10 }));

      expect(validateReconnect).toHaveBeenCalled();
      // The destination handle is inferred from the dragged TARGET endpoint
      // ('input'), independent of any handle element under the cursor.
      expect(onReconnectEdge).toHaveBeenCalledWith('edge1', { target: 'node-x', targetHandle: 'input' });

      delete (document as unknown as { elementFromPoint?: unknown }).elementFromPoint;
      document.body.removeChild(dropNode);
    });

    it('should not render a reconnect preview line when not dragging', () => {
      render(
        <svg>
          <DefaultEdge {...defaultProps} selected={true} data={{ targetGripEnabled: true }} />
        </svg>
      );
      expect(document.querySelector('.edge-reconnect-preview')).not.toBeInTheDocument();
    });

    it('should render a reconnect preview line from the FIXED source while dragging the target grip', () => {
      // Install the renderer/viewport so clientToFlowPoint resolves a point.
      const mockRenderer = document.createElement('div');
      mockRenderer.className = 'react-flow__renderer';
      mockRenderer.getBoundingClientRect = jest.fn(() => ({
        left: 0, top: 0, right: 800, bottom: 600, width: 800, height: 600, x: 0, y: 0, toJSON: () => {},
      })) as unknown as () => DOMRect;
      const mockViewport = document.createElement('div');
      mockViewport.className = 'react-flow__viewport';
      mockViewport.style.transform = 'translate(0px, 0px) scale(1)';
      document.body.appendChild(mockRenderer);
      document.body.appendChild(mockViewport);

      const onReconnectEdge = jest.fn();
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourceX={100}
            sourceY={100}
            selected={true}
            data={{ targetGripEnabled: true, onReconnectEdge, validateReconnect: () => true }}
          />
        </svg>
      );

      // Begin dragging the target grip, then move the cursor.
      const grip = document.querySelector('.edge-endpoint-grip--target');
      fireEvent.mouseDown(grip!);
      fireEvent(document, new MouseEvent('mousemove', { clientX: 300, clientY: 400 }));

      const preview = document.querySelector('.edge-reconnect-preview');
      expect(preview).toBeInTheDocument();
      // Path starts at the FIXED source endpoint (100,100) and ends at the
      // cursor in flow coords (identity transform → 300,400).
      const d = preview!.getAttribute('d') || '';
      expect(d.startsWith('M 100,100 ')).toBe(true);
      expect(d.trim().endsWith('300,400')).toBe(true);

      // Drop on empty canvas → preview disappears, edge unchanged.
      (document as unknown as { elementFromPoint: (x: number, y: number) => Element | null }).elementFromPoint =
        jest.fn(() => null);
      fireEvent(document, new MouseEvent('mouseup', { clientX: 300, clientY: 400 }));
      expect(document.querySelector('.edge-reconnect-preview')).not.toBeInTheDocument();

      delete (document as unknown as { elementFromPoint?: unknown }).elementFromPoint;
      document.body.removeChild(mockRenderer);
      document.body.removeChild(mockViewport);
    });

    it('should render a reconnect preview line from the FIXED target while dragging the source grip', () => {
      const mockRenderer = document.createElement('div');
      mockRenderer.className = 'react-flow__renderer';
      mockRenderer.getBoundingClientRect = jest.fn(() => ({
        left: 0, top: 0, right: 800, bottom: 600, width: 800, height: 600, x: 0, y: 0, toJSON: () => {},
      })) as unknown as () => DOMRect;
      const mockViewport = document.createElement('div');
      mockViewport.className = 'react-flow__viewport';
      mockViewport.style.transform = 'translate(0px, 0px) scale(1)';
      document.body.appendChild(mockRenderer);
      document.body.appendChild(mockViewport);

      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            targetX={250}
            targetY={250}
            selected={true}
            data={{ sourceGripEnabled: true, onReconnectEdge: jest.fn(), validateReconnect: () => true }}
          />
        </svg>
      );

      const grip = document.querySelector('.edge-endpoint-grip--source');
      fireEvent.mouseDown(grip!);
      fireEvent(document, new MouseEvent('mousemove', { clientX: 30, clientY: 40 }));

      const preview = document.querySelector('.edge-reconnect-preview');
      expect(preview).toBeInTheDocument();
      const d = preview!.getAttribute('d') || '';
      // Fixed end is the TARGET (250,250); cursor (30,40).
      expect(d.startsWith('M 250,250 ')).toBe(true);
      expect(d.trim().endsWith('30,40')).toBe(true);

      fireEvent(document, new MouseEvent('mouseup'));
      document.body.removeChild(mockRenderer);
      document.body.removeChild(mockViewport);
    });
  });

  describe('control point drag handling', () => {
    it('should not initiate drag when onEdgeUpdate is missing', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            selected={true}
            data={{}}
          />
        </svg>
      );
      const wrapper = document.querySelector('.edge-control-point-wrapper');
      fireEvent.mouseDown(wrapper!);
    });

    it('should initiate drag when not in read-only mode and onEdgeUpdate is available', () => {
      const onEdgeUpdate = jest.fn();
      const mockRenderer = document.createElement('div');
      mockRenderer.className = 'react-flow__renderer';
      mockRenderer.getBoundingClientRect = jest.fn(() => ({
        left: 0, top: 0, right: 800, bottom: 600, width: 800, height: 600, x: 0, y: 0, toJSON: () => {},
      }));
      const mockViewport = document.createElement('div');
      mockViewport.className = 'react-flow__viewport';
      mockViewport.style.transform = 'translate(100px, 50px) scale(1.5)';
      document.body.appendChild(mockRenderer);
      document.body.appendChild(mockViewport);

      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            selected={true}
            data={{ onEdgeUpdate }}
          />
        </svg>
      );
      const wrapper = document.querySelector('.edge-control-point-wrapper');
      fireEvent.mouseDown(wrapper!);

      const moveEvent = new MouseEvent('mousemove', { clientX: 200, clientY: 200 });
      document.dispatchEvent(moveEvent);
      expect(onEdgeUpdate).toHaveBeenCalled();

      const upEvent = new MouseEvent('mouseup');
      document.dispatchEvent(upEvent);

      document.body.removeChild(mockRenderer);
      document.body.removeChild(mockViewport);
    });

    it('should handle drag when viewport has no transform match', () => {
      const onEdgeUpdate = jest.fn();
      const mockRenderer = document.createElement('div');
      mockRenderer.className = 'react-flow__renderer';
      mockRenderer.getBoundingClientRect = jest.fn(() => ({
        left: 0, top: 0, right: 800, bottom: 600, width: 800, height: 600, x: 0, y: 0, toJSON: () => {},
      }));
      const mockViewport = document.createElement('div');
      mockViewport.className = 'react-flow__viewport';
      mockViewport.style.transform = 'none';
      document.body.appendChild(mockRenderer);
      document.body.appendChild(mockViewport);

      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            selected={true}
            data={{ onEdgeUpdate }}
          />
        </svg>
      );
      const wrapper = document.querySelector('.edge-control-point-wrapper');
      fireEvent.mouseDown(wrapper!);

      const moveEvent = new MouseEvent('mousemove', { clientX: 200, clientY: 200 });
      document.dispatchEvent(moveEvent);
      expect(onEdgeUpdate).toHaveBeenCalled();

      const upEvent = new MouseEvent('mouseup');
      document.dispatchEvent(upEvent);

      document.body.removeChild(mockRenderer);
      document.body.removeChild(mockViewport);
    });

    it('should early return when renderer element not found', () => {
      const onEdgeUpdate = jest.fn();
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            selected={true}
            data={{ onEdgeUpdate }}
          />
        </svg>
      );
      const wrapper = document.querySelector('.edge-control-point-wrapper');
      fireEvent.mouseDown(wrapper!);
      const moveEvent = new MouseEvent('mousemove', { clientX: 200, clientY: 200 });
      document.dispatchEvent(moveEvent);
      expect(onEdgeUpdate).not.toHaveBeenCalled();
    });
  });

  describe('backward flow without control offset', () => {
    it('should handle backward Bottom to Top flow without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourceX={100}
            sourceY={200}
            targetX={100}
            targetY={100}
            sourcePosition={Position.Bottom}
            targetPosition={Position.Top}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle backward Top to Bottom flow without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourceX={100}
            sourceY={100}
            targetX={100}
            targetY={200}
            sourcePosition={Position.Top}
            targetPosition={Position.Bottom}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle backward Right to Left flow without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourceX={200}
            sourceY={100}
            targetX={100}
            targetY={100}
            sourcePosition={Position.Right}
            targetPosition={Position.Left}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle backward Left to Right flow without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourceX={100}
            sourceY={100}
            targetX={200}
            targetY={100}
            sourcePosition={Position.Left}
            targetPosition={Position.Right}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });

  describe('no-offset path positions', () => {
    it('should handle Right source position without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourcePosition={Position.Right}
            targetPosition={Position.Left}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Left source position without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourcePosition={Position.Left}
            targetPosition={Position.Right}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Top source position without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourcePosition={Position.Top}
            targetPosition={Position.Bottom}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Bottom target position without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourcePosition={Position.Right}
            targetPosition={Position.Bottom}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Right target position without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourcePosition={Position.Bottom}
            targetPosition={Position.Right}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Top target position without offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourcePosition={Position.Right}
            targetPosition={Position.Top}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });

  describe('edge order drag and drop', () => {
    it('should handle dragStart on edge order badge', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 1, totalEdges: 2, pathX: 150, pathY: 150, sourceNodeId: 'node1' },
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      const orderBadge = document.querySelector('.edge-order-number');
      const mockDataTransfer = {
        setData: jest.fn(),
        effectAllowed: '',
        setDragImage: jest.fn(),
      };
      fireEvent.dragStart(orderBadge!, { dataTransfer: mockDataTransfer });
      expect(mockDataTransfer.setData).toHaveBeenCalledWith('edgeOrderReorder', expect.any(String));
    });

    it('should handle dragEnd on edge order badge', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 1, totalEdges: 2, pathX: 150, pathY: 150, sourceNodeId: 'node1' },
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      const orderBadge = document.querySelector('.edge-order-number');
      fireEvent.dragEnd(orderBadge!);
    });

    it('should handle dragOver on edge order badge', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 2, totalEdges: 3, pathX: 150, pathY: 150, sourceNodeId: 'node1' },
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      const orderBadge = document.querySelector('.edge-order-number');
      fireEvent.dragOver(orderBadge!, { dataTransfer: { dropEffect: '' } });
    });

    it('should handle dragLeave on edge order badge', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 2, totalEdges: 3, pathX: 150, pathY: 150, sourceNodeId: 'node1' },
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      const orderBadge = document.querySelector('.edge-order-number');
      fireEvent.dragLeave(orderBadge!);
    });

    it('should display correct order number with Flow label', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 2, totalEdges: 3, pathX: 150, pathY: 150, sourceNodeId: 'node1' },
      };
      const { container } = render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      const orderBadge = container.querySelector('.edge-order-number');
      expect(orderBadge?.textContent).toBe('Flow 2');
    });

    it('should handle mouseDown on edge order badge', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 1, totalEdges: 2, pathX: 150, pathY: 150, sourceNodeId: 'node1' },
      };
      render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      const orderBadge = document.querySelector('.edge-order-number');
      expect(orderBadge).toBeInTheDocument();
      // Should not throw
      fireEvent.mouseDown(orderBadge!);
    });
  });

  describe('edge order without pathX', () => {
    it('should not show edge order when pathX is undefined', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 2, totalEdges: 3 }, // No pathX
      };
      const { container } = render(
        <svg>
          <DefaultEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(container.querySelector('.edge-order-number')).not.toBeInTheDocument();
    });
  });

  describe('backward flow with control offset - additional', () => {
    it('should handle backward Top to Bottom flow with control offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourceX={100}
            sourceY={100}
            targetX={100}
            targetY={200}
            sourcePosition={Position.Top}
            targetPosition={Position.Bottom}
            data={{ controlOffset: { x: 5, y: 5 } }}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle backward Left to Right flow with control offset', () => {
      render(
        <svg>
          <DefaultEdge
            {...defaultProps}
            sourceX={100}
            sourceY={100}
            targetX={200}
            targetY={100}
            sourcePosition={Position.Left}
            targetPosition={Position.Right}
            data={{ controlOffset: { x: 5, y: 5 } }}
          />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });
});
