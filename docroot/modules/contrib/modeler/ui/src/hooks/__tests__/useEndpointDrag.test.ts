import { renderHook, act } from '@testing-library/react';
import {
  useEndpointDrag,
  hitTestDropTarget,
  buildProposedConnection,
  clientToFlowPoint,
} from '../useEndpointDrag';

describe('useEndpointDrag', () => {
  const baseProps = {
    edgeId: 'edge-1',
    endpoint: 'target' as const,
    source: 'node-a',
    target: 'node-b',
    sourceHandle: 'output',
    targetHandle: 'input',
    isLocked: false,
    validateConnection: jest.fn(() => true),
    onReconnectEdge: jest.fn(),
  };

  // jsdom does not implement elementFromPoint; install a controllable stub.
  const setElementFromPoint = (el: Element | null) => {
    (document as unknown as { elementFromPoint: (x: number, y: number) => Element | null }).elementFromPoint =
      jest.fn(() => el);
  };
  const clearElementFromPoint = () => {
    delete (document as unknown as { elementFromPoint?: unknown }).elementFromPoint;
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  afterEach(() => {
    clearElementFromPoint();
  });

  describe('hitTestDropTarget', () => {
    it('returns the handle node + handle id when a handle is under the cursor', () => {
      const handle = document.createElement('div');
      handle.className = 'react-flow__handle';
      handle.setAttribute('data-nodeid', 'node-c');
      handle.setAttribute('data-handleid', 'input');
      setElementFromPoint(handle);

      expect(hitTestDropTarget(10, 10, 'target')).toEqual({ nodeId: 'node-c', handleId: 'input' });
    });

    it('infers the destination handle from a NODE-only hit for a dragged TARGET endpoint', () => {
      const node = document.createElement('div');
      node.className = 'react-flow__node';
      node.setAttribute('data-id', 'node-d');
      setElementFromPoint(node);

      // Dragged target endpoint → destination handle is the node's 'input'.
      expect(hitTestDropTarget(10, 10, 'target')).toEqual({ nodeId: 'node-d', handleId: 'input' });
    });

    it('infers the destination handle from a NODE-only hit for a dragged SOURCE endpoint', () => {
      const node = document.createElement('div');
      node.className = 'react-flow__node';
      node.setAttribute('data-id', 'node-d');
      setElementFromPoint(node);

      // Dragged source endpoint → destination handle is the node's 'output'.
      expect(hitTestDropTarget(10, 10, 'source')).toEqual({ nodeId: 'node-d', handleId: 'output' });
    });

    it('resolves the node beneath a grip overlay by re-querying with grips disabled (regression #3585553)', () => {
      // Reproduces the core blocker: the destination node's OWN selected-edge
      // grip sits on top at the drop point (pointer-events:all). The first
      // elementFromPoint returns the grip's circle; hitTestDropTarget must
      // temporarily disable grips and re-query to reach the node beneath.
      const node = document.createElement('div');
      node.className = 'react-flow__node';
      node.setAttribute('data-id', 'node-A');

      // A real grip element attached to the document so the hit-test can toggle
      // its pointer-events; its circle is what the first query returns.
      const grip = document.createElement('div');
      grip.className = 'edge-endpoint-grip edge-endpoint-grip--source';
      grip.setAttribute('data-edge-id', 'edge-A');
      grip.style.pointerEvents = 'all';
      const circle = document.createElement('div'); // stand-in for the SVG circle
      grip.appendChild(circle);
      document.body.appendChild(grip);

      // elementFromPoint returns the grip's circle while the grip is
      // interactive; once the grip is pointer-events:none, it returns the node.
      (document as unknown as { elementFromPoint: (x: number, y: number) => Element | null }).elementFromPoint =
        jest.fn(() => (grip.style.pointerEvents === 'none' ? node : circle));

      const result = hitTestDropTarget(10, 10, 'source');
      expect(result).toEqual({ nodeId: 'node-A', handleId: 'output' });
      // The grip's pointer-events must be RESTORED after the re-query.
      expect(grip.style.pointerEvents).toBe('all');

      document.body.removeChild(grip);
    });

    it('resolves a drop onto a node whose source handle is NON-CONNECTABLE (regression #3585553)', () => {
      // When node A's source handle is reserved (isConnectable=false), React
      // Flow gives it pointer-events:none, so the cursor lands on the node card
      // (not the handle). The drop must still resolve node A with the inferred
      // 'output' handle so a SOURCE-endpoint reconnect onto A can commit.
      const nodeCard = document.createElement('div');
      nodeCard.className = 'react-flow__node';
      nodeCard.setAttribute('data-id', 'node-a');
      const inner = document.createElement('div'); // node body, not a handle
      nodeCard.appendChild(inner);
      setElementFromPoint(inner);

      expect(hitTestDropTarget(10, 10, 'source')).toEqual({ nodeId: 'node-a', handleId: 'output' });
    });

    it('normalizes a handle hit with no data-handleid to the canonical destination handle', () => {
      const handle = document.createElement('div');
      handle.className = 'react-flow__handle';
      handle.setAttribute('data-nodeid', 'node-e');
      // No data-handleid attribute.
      setElementFromPoint(handle);

      expect(hitTestDropTarget(10, 10, 'source')).toEqual({ nodeId: 'node-e', handleId: 'output' });
    });

    it('returns null on empty canvas', () => {
      setElementFromPoint(null);
      expect(hitTestDropTarget(10, 10, 'target')).toBeNull();
    });

    it('returns null when elementFromPoint is unavailable', () => {
      clearElementFromPoint();
      expect(hitTestDropTarget(10, 10, 'target')).toBeNull();
    });
  });

  describe('buildProposedConnection', () => {
    const args = {
      source: 'node-a',
      target: 'node-b',
      sourceHandle: 'output',
      targetHandle: 'input',
    };

    it('replaces the target end when dragging the target grip', () => {
      const conn = buildProposedConnection(
        { ...args, endpoint: 'target' },
        { nodeId: 'node-x', handleId: 'input' },
      );
      expect(conn).toEqual({ source: 'node-a', sourceHandle: 'output', target: 'node-x', targetHandle: 'input' });
    });

    it('replaces the source end when dragging the source grip', () => {
      const conn = buildProposedConnection(
        { ...args, endpoint: 'source' },
        { nodeId: 'node-y', handleId: 'output' },
      );
      expect(conn).toEqual({ source: 'node-y', sourceHandle: 'output', target: 'node-b', targetHandle: 'input' });
    });
  });

  describe('return values', () => {
    it('returns isDragging, previewPoint and handleEndpointDrag', () => {
      const { result } = renderHook(() => useEndpointDrag(baseProps));
      expect(typeof result.current.isDragging).toBe('boolean');
      expect(typeof result.current.handleEndpointDrag).toBe('function');
      expect(result.current.isDragging).toBe(false);
      expect(result.current.previewPoint).toBeNull();
    });
  });

  describe('clientToFlowPoint', () => {
    // Install the renderer + viewport DOM the converter reads (mirrors the
    // control-point drag tests' mocking).
    const installViewport = (transform: string) => {
      const renderer = document.createElement('div');
      renderer.className = 'react-flow__renderer';
      renderer.getBoundingClientRect = jest.fn(() => ({
        left: 0, top: 0, right: 800, bottom: 600, width: 800, height: 600, x: 0, y: 0, toJSON: () => {},
      })) as unknown as () => DOMRect;
      const viewport = document.createElement('div');
      viewport.className = 'react-flow__viewport';
      viewport.style.transform = transform;
      document.body.appendChild(renderer);
      document.body.appendChild(viewport);
      return () => {
        document.body.removeChild(renderer);
        document.body.removeChild(viewport);
      };
    };

    it('converts client coords to flow coords using the viewport transform', () => {
      const cleanup = installViewport('translate(100px, 50px) scale(2)');
      // (clientX - translateX) / scale = (300 - 100) / 2 = 100
      // (clientY - translateY) / scale = (250 - 50) / 2 = 100
      expect(clientToFlowPoint(300, 250)).toEqual({ x: 100, y: 100 });
      cleanup();
    });

    it('treats a non-matching transform as identity', () => {
      const cleanup = installViewport('none');
      expect(clientToFlowPoint(40, 60)).toEqual({ x: 40, y: 60 });
      cleanup();
    });

    it('returns null when renderer/viewport are missing', () => {
      expect(clientToFlowPoint(10, 10)).toBeNull();
    });
  });

  describe('drag gating', () => {
    it('does not start drag when locked', () => {
      const { result } = renderHook(() => useEndpointDrag({ ...baseProps, isLocked: true }));
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      expect(evt.stopPropagation).not.toHaveBeenCalled();
      expect(result.current.isDragging).toBe(false);
    });

    it('does not start drag when onReconnectEdge is missing', () => {
      const { result } = renderHook(() =>
        useEndpointDrag({ ...baseProps, onReconnectEdge: undefined }),
      );
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      expect(result.current.isDragging).toBe(false);
    });

    it('starts drag and sets isDragging true', () => {
      const { result } = renderHook(() => useEndpointDrag(baseProps));
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      expect(evt.stopPropagation).toHaveBeenCalled();
      expect(evt.preventDefault).toHaveBeenCalled();
      expect(result.current.isDragging).toBe(true);
      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup'));
      });
    });
  });

  describe('live preview point', () => {
    const installViewport = () => {
      const renderer = document.createElement('div');
      renderer.className = 'react-flow__renderer';
      renderer.getBoundingClientRect = jest.fn(() => ({
        left: 0, top: 0, right: 800, bottom: 600, width: 800, height: 600, x: 0, y: 0, toJSON: () => {},
      })) as unknown as () => DOMRect;
      const viewport = document.createElement('div');
      viewport.className = 'react-flow__viewport';
      viewport.style.transform = 'translate(0px, 0px) scale(1)';
      document.body.appendChild(renderer);
      document.body.appendChild(viewport);
      return () => {
        document.body.removeChild(renderer);
        document.body.removeChild(viewport);
      };
    };

    it('updates previewPoint on mousemove and clears it on mouseup', () => {
      const cleanup = installViewport();
      const { result } = renderHook(() => useEndpointDrag(baseProps));
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;

      act(() => result.current.handleEndpointDrag(evt));
      expect(result.current.previewPoint).toBeNull();

      // With identity transform, flow coords equal client coords.
      act(() => {
        document.dispatchEvent(new MouseEvent('mousemove', { clientX: 123, clientY: 456 }));
      });
      expect(result.current.previewPoint).toEqual({ x: 123, y: 456 });

      // Drop on empty canvas → preview cleared, no commit.
      setElementFromPoint(null);
      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup', { clientX: 123, clientY: 456 }));
      });
      expect(result.current.previewPoint).toBeNull();
      expect(result.current.isDragging).toBe(false);

      clearElementFromPoint();
      cleanup();
    });

    it('does not set previewPoint when the viewport is unavailable', () => {
      const { result } = renderHook(() => useEndpointDrag(baseProps));
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      act(() => {
        document.dispatchEvent(new MouseEvent('mousemove', { clientX: 10, clientY: 10 }));
      });
      // No renderer/viewport → clientToFlowPoint returns null → preview stays null.
      expect(result.current.previewPoint).toBeNull();
      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup'));
      });
    });
  });

  describe('commit on valid drop', () => {
    it('commits the new target on a valid drop over a different node', () => {
      const node = document.createElement('div');
      node.className = 'react-flow__node';
      node.setAttribute('data-id', 'node-x');
      setElementFromPoint(node);

      const onReconnectEdge = jest.fn();
      const validateConnection = jest.fn(() => true);
      const { result } = renderHook(() =>
        useEndpointDrag({ ...baseProps, onReconnectEdge, validateConnection }),
      );
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup', { clientX: 50, clientY: 50 }));
      });

      // The destination handle is inferred from the dragged TARGET endpoint
      // (node's 'input'), independent of any handle element under the cursor.
      expect(validateConnection).toHaveBeenCalledWith({
        source: 'node-a',
        sourceHandle: 'output',
        target: 'node-x',
        targetHandle: 'input',
      });
      expect(onReconnectEdge).toHaveBeenCalledWith('edge-1', { target: 'node-x', targetHandle: 'input' });
      expect(result.current.isDragging).toBe(false);
    });

    it('commits the new source when dragging the source grip', () => {
      const node = document.createElement('div');
      node.className = 'react-flow__node';
      node.setAttribute('data-id', 'node-z');
      setElementFromPoint(node);

      const onReconnectEdge = jest.fn();
      const { result } = renderHook(() =>
        useEndpointDrag({ ...baseProps, endpoint: 'source', onReconnectEdge }),
      );
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup', { clientX: 50, clientY: 50 }));
      });
      // Destination handle inferred from the dragged SOURCE endpoint ('output').
      expect(onReconnectEdge).toHaveBeenCalledWith('edge-1', { source: 'node-z', sourceHandle: 'output' });
    });

    it('commits a SOURCE reconnect onto a node whose source handle is non-connectable (regression #3585553)', () => {
      // Reproduces the reported bug at the hook level: node A's source handle
      // is reserved (non-connectable → pointer-events:none), so the cursor
      // lands on the node body. The drop must still resolve A + 'output' and
      // commit, given validation passes.
      const nodeA = document.createElement('div');
      nodeA.className = 'react-flow__node';
      nodeA.setAttribute('data-id', 'node-A');
      const body = document.createElement('div');
      nodeA.appendChild(body);
      setElementFromPoint(body);

      const onReconnectEdge = jest.fn();
      const validateConnection = jest.fn(() => true);
      const { result } = renderHook(() =>
        useEndpointDrag({ ...baseProps, endpoint: 'source', onReconnectEdge, validateConnection }),
      );
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup', { clientX: 50, clientY: 50 }));
      });

      expect(validateConnection).toHaveBeenCalledWith({
        source: 'node-A',
        sourceHandle: 'output',
        target: 'node-b',
        targetHandle: 'input',
      });
      expect(onReconnectEdge).toHaveBeenCalledWith('edge-1', { source: 'node-A', sourceHandle: 'output' });
    });
  });

  describe('snap back (no mutation)', () => {
    it('does nothing on empty-canvas drop', () => {
      setElementFromPoint(null);
      const onReconnectEdge = jest.fn();
      const { result } = renderHook(() => useEndpointDrag({ ...baseProps, onReconnectEdge }));
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup', { clientX: 5, clientY: 5 }));
      });
      expect(onReconnectEdge).not.toHaveBeenCalled();
    });

    it('does nothing on an invalid drop', () => {
      const node = document.createElement('div');
      node.className = 'react-flow__node';
      node.setAttribute('data-id', 'node-x');
      setElementFromPoint(node);
      const onReconnectEdge = jest.fn();
      const validateConnection = jest.fn(() => false);
      const { result } = renderHook(() =>
        useEndpointDrag({ ...baseProps, onReconnectEdge, validateConnection }),
      );
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup', { clientX: 50, clientY: 50 }));
      });
      expect(onReconnectEdge).not.toHaveBeenCalled();
    });

    it('does nothing when dropped back on the same node/handle', () => {
      const node = document.createElement('div');
      node.className = 'react-flow__node';
      node.setAttribute('data-id', 'node-b'); // same as current target
      setElementFromPoint(node);
      const onReconnectEdge = jest.fn();
      const { result } = renderHook(() =>
        // baseProps.targetHandle is 'input'; the node-only drop infers 'input'
        // too, so the proposed (node-b, 'input') equals the current endpoint
        // and the no-op guard prevents a spurious commit.
        useEndpointDrag({ ...baseProps, onReconnectEdge }),
      );
      const evt = { stopPropagation: jest.fn(), preventDefault: jest.fn() } as unknown as React.MouseEvent;
      act(() => result.current.handleEndpointDrag(evt));
      act(() => {
        document.dispatchEvent(new MouseEvent('mouseup', { clientX: 50, clientY: 50 }));
      });
      expect(onReconnectEdge).not.toHaveBeenCalled();
    });
  });
});
