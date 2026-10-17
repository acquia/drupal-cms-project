import {
  findFreePosition,
  findFlowAwarePosition,
} from '../positionUtils';
import { NODE_DIMENSIONS, LAYOUT } from '../../constants/dimensions';

/** Padding around nodes when checking for overlap (px). */
const COLLISION_PADDING = 20;

/**
 * Local overlap helper for test assertions.
 * Mirrors the internal isOverlapping logic in positionUtils.ts.
 */
function isOverlapping(
  candidateX: number,
  candidateY: number,
  candidateWidth: number,
  candidateHeight: number,
  existingNodes: { position: { x: number; y: number }; width?: number | null; height?: number | null }[],
  padding: number = COLLISION_PADDING,
): boolean {
  for (const node of existingNodes) {
    const nodeW = node.width || NODE_DIMENSIONS.DEFAULT_WIDTH;
    const nodeH = node.height || NODE_DIMENSIONS.DEFAULT_HEIGHT;

    const ex1 = node.position.x - padding;
    const ey1 = node.position.y - padding;
    const ex2 = node.position.x + nodeW + padding;
    const ey2 = node.position.y + nodeH + padding;

    const cx1 = candidateX;
    const cy1 = candidateY;
    const cx2 = candidateX + candidateWidth;
    const cy2 = candidateY + candidateHeight;

    if (cx1 < ex2 && cx2 > ex1 && cy1 < ey2 && cy2 > ey1) {
      return true;
    }
  }
  return false;
}

describe('positionUtils', () => {
  describe('findFreePosition', () => {
    it('should return the candidate position when no nodes exist', () => {
      const result = findFreePosition({ x: 100, y: 200 }, []);
      expect(result).toEqual({ x: 100, y: 200 });
    });

    it('should return the candidate position when it does not overlap', () => {
      const nodes = [{ position: { x: 0, y: 0 }, width: 200, height: 100 }];
      const result = findFreePosition({ x: 500, y: 500 }, nodes);
      expect(result).toEqual({ x: 500, y: 500 });
    });

    it('should offset to the right when candidate overlaps', () => {
      const nodes = [{ position: { x: 100, y: 100 }, width: 200, height: 100 }];
      const result = findFreePosition({ x: 100, y: 100 }, nodes);

      // Should have moved to the right
      expect(result.x).toBeGreaterThan(100);
      // Should stay at the same Y
      expect(result.y).toBe(100);
    });

    it('should find free position when multiple nodes block the right direction', () => {
      // Create a row of nodes blocking rightward movement
      const stepX = NODE_DIMENSIONS.DEFAULT_WIDTH + LAYOUT.NODE_SPACING_X; // 450
      const nodes = [];
      for (let i = 0; i < 20; i++) {
        nodes.push({
          position: { x: 100 + stepX * i, y: 100 },
          width: NODE_DIMENSIONS.DEFAULT_WIDTH,
          height: NODE_DIMENSIONS.DEFAULT_HEIGHT,
        });
      }

      const result = findFreePosition({ x: 100, y: 100 }, nodes);
      // Should have found a position (possibly moved down or diagonally)
      expect(result).toBeDefined();
      // Should not overlap any existing node
      expect(isOverlapping(
        result.x, result.y,
        NODE_DIMENSIONS.DEFAULT_WIDTH, NODE_DIMENSIONS.DEFAULT_HEIGHT,
        nodes,
      )).toBe(false);
    });

    it('should move down when right is fully blocked', () => {
      // Block all right positions within phase 1
      const stepX = NODE_DIMENSIONS.DEFAULT_WIDTH + LAYOUT.NODE_SPACING_X;
      const rightAttempts = Math.ceil(50 / 3); // 17
      const nodes = [];
      for (let i = 0; i <= rightAttempts; i++) {
        nodes.push({
          position: { x: 100 + stepX * i, y: 100 },
          width: NODE_DIMENSIONS.DEFAULT_WIDTH,
          height: NODE_DIMENSIONS.DEFAULT_HEIGHT,
        });
      }

      const result = findFreePosition({ x: 100, y: 100 }, nodes);
      // The first free position should be below (since right is blocked)
      expect(result.y).toBeGreaterThan(100);
    });

    it('should respect custom node dimensions', () => {
      const nodes = [{ position: { x: 100, y: 100 }, width: 400, height: 200 }];
      const result = findFreePosition({ x: 100, y: 100 }, nodes, 400, 200);

      expect(result.x !== 100 || result.y !== 100).toBe(true);
      // Ensure the found position is free
      expect(isOverlapping(result.x, result.y, 400, 200, nodes)).toBe(false);
    });

    it('should always return a position (fallback)', () => {
      // Even in pathological cases, a position must be returned
      const nodes = [{ position: { x: 100, y: 100 }, width: 200, height: 100 }];
      const result = findFreePosition({ x: 100, y: 100 }, nodes);
      expect(result).toBeDefined();
      expect(typeof result.x).toBe('number');
      expect(typeof result.y).toBe('number');
    });

    it('should not mutate the input candidate', () => {
      const candidate = { x: 100, y: 100 };
      const nodes = [{ position: { x: 100, y: 100 }, width: 200, height: 100 }];
      findFreePosition(candidate, nodes);
      expect(candidate).toEqual({ x: 100, y: 100 });
    });

    it('should handle a dense grid of nodes', () => {
      // Create a 5x5 grid of nodes
      const nodes = [];
      const stepX = NODE_DIMENSIONS.DEFAULT_WIDTH + LAYOUT.NODE_SPACING_X;
      const stepY = NODE_DIMENSIONS.DEFAULT_HEIGHT + LAYOUT.NODE_SPACING_Y;
      for (let row = 0; row < 5; row++) {
        for (let col = 0; col < 5; col++) {
          nodes.push({
            position: { x: 100 + col * stepX, y: 100 + row * stepY },
            width: NODE_DIMENSIONS.DEFAULT_WIDTH,
            height: NODE_DIMENSIONS.DEFAULT_HEIGHT,
          });
        }
      }

      const result = findFreePosition({ x: 100, y: 100 }, nodes);
      // Must not overlap any existing node
      expect(isOverlapping(
        result.x, result.y,
        NODE_DIMENSIONS.DEFAULT_WIDTH, NODE_DIMENSIONS.DEFAULT_HEIGHT,
        nodes,
      )).toBe(false);
    });

    it('should prefer positions closer to the candidate', () => {
      // Single blocking node at the candidate position
      const nodes = [{ position: { x: 100, y: 100 }, width: 200, height: 100 }];
      const result = findFreePosition({ x: 100, y: 100 }, nodes);

      const stepX = NODE_DIMENSIONS.DEFAULT_WIDTH + LAYOUT.NODE_SPACING_X;
      // The first free position should be one step to the right
      expect(result.x).toBe(100 + stepX);
      expect(result.y).toBe(100);
    });
  });

  // ============ Flow-Aware Positioning Tests ============

  describe('findFlowAwarePosition', () => {
    /**
     * Helper: build a simple two-flow scenario.
     *
     * Left flow:  event_L (100,100) -> action_L (100,350)
     * Right flow: event_R (600,100) -> action_R (600,350)
     */
    function twoFlowSetup() {
      const nodes: { id: string; position: { x: number; y: number }; width: number; height: number }[] = [
        { id: 'event_L',  position: { x: 100, y: 100 }, width: 200, height: 100 },
        { id: 'action_L', position: { x: 100, y: 350 }, width: 200, height: 100 },
        { id: 'event_R',  position: { x: 600, y: 100 }, width: 200, height: 100 },
        { id: 'action_R', position: { x: 600, y: 350 }, width: 200, height: 100 },
      ];
      const edges: { source: string; target: string }[] = [
        { source: 'event_L', target: 'action_L' },
        { source: 'event_R', target: 'action_R' },
      ];
      return { nodes, edges };
    }

    it('should return the candidate when it is already free', () => {
      const { nodes, edges } = twoFlowSetup();
      // Candidate below action_L with plenty of room
      const candidate = { x: 100, y: 600 };
      const result = findFlowAwarePosition(candidate, 'action_L', nodes, edges);
      expect(result.position).toEqual({ x: 100, y: 600 });
      expect(result.shiftAmount).toBe(0);
      expect(result.shiftNodeIds.size).toBe(0);
    });

    it('should return candidate when there are no nodes', () => {
      const result = findFlowAwarePosition({ x: 100, y: 100 }, 'a', [], []);
      expect(result.position).toEqual({ x: 100, y: 100 });
      expect(result.shiftAmount).toBe(0);
    });

    it('should shift right flow when candidate is blocked and no room to the right', () => {
      const { nodes, edges } = twoFlowSetup();
      const candidate = { x: 100, y: 350 };
      const result = findFlowAwarePosition(candidate, 'event_L', nodes, edges);

      // Position must not overlap any existing node (including after shift)
      const shiftedNodes = nodes.map(n => {
        if (n.id && result.shiftNodeIds.has(n.id)) {
          return { ...n, position: { x: n.position.x + result.shiftAmount, y: n.position.y } };
        }
        return n;
      });
      expect(isOverlapping(
        result.position.x, result.position.y,
        NODE_DIMENSIONS.DEFAULT_WIDTH, NODE_DIMENSIONS.DEFAULT_HEIGHT,
        shiftedNodes,
      )).toBe(false);

      // The new node must not end up in the right flow's original territory
      expect(result.position.x).toBeLessThan(600);
    });

    it('should NOT push the new node into a neighboring flow', () => {
      const { nodes, edges } = twoFlowSetup();
      // Candidate overlaps action_L at (100, 350)
      const candidate = { x: 100, y: 350 };
      const result = findFlowAwarePosition(candidate, 'event_L', nodes, edges);

      // After applying any shift, the new node must not overlap the right flow
      const rightFlowNodes = nodes
        .filter(n => n.id === 'event_R' || n.id === 'action_R')
        .map(n => {
          if (n.id && result.shiftNodeIds.has(n.id)) {
            return { ...n, position: { x: n.position.x + result.shiftAmount, y: n.position.y } };
          }
          return n;
        });
      expect(isOverlapping(
        result.position.x, result.position.y,
        NODE_DIMENSIONS.DEFAULT_WIDTH, NODE_DIMENSIONS.DEFAULT_HEIGHT,
        rightFlowNodes,
      )).toBe(false);
    });

    it('should shift neighboring flows when no room exists between flows', () => {
      // Two flows very close together — left flow at x=100, right flow at x=350
      const nodes: { id: string; position: { x: number; y: number }; width: number; height: number }[] = [
        { id: 'event_L',  position: { x: 100, y: 100 }, width: 200, height: 100 },
        { id: 'action_L', position: { x: 100, y: 350 }, width: 200, height: 100 },
        { id: 'event_R',  position: { x: 350, y: 100 }, width: 200, height: 100 },
        { id: 'action_R', position: { x: 350, y: 350 }, width: 200, height: 100 },
      ];
      const edges: { source: string; target: string }[] = [
        { source: 'event_L', target: 'action_L' },
        { source: 'event_R', target: 'action_R' },
      ];

      const candidate = { x: 100, y: 350 };
      const result = findFlowAwarePosition(candidate, 'event_L', nodes, edges);

      // Should indicate that right-flow nodes need shifting
      expect(result.shiftAmount).toBeGreaterThan(0);
      expect(result.shiftNodeIds.has('event_R')).toBe(true);
      expect(result.shiftNodeIds.has('action_R')).toBe(true);
      // Left-flow nodes should NOT be shifted
      expect(result.shiftNodeIds.has('event_L')).toBe(false);
      expect(result.shiftNodeIds.has('action_L')).toBe(false);

      // Simulate the shift that the hook would apply, then verify no overlap.
      const shiftedNodes = nodes.map(n => {
        if (n.id && result.shiftNodeIds.has(n.id)) {
          return { ...n, position: { x: n.position.x + result.shiftAmount, y: n.position.y } };
        }
        return n;
      });
      expect(isOverlapping(
        result.position.x, result.position.y,
        NODE_DIMENSIONS.DEFAULT_WIDTH, NODE_DIMENSIONS.DEFAULT_HEIGHT,
        shiftedNodes,
      )).toBe(false);
    });

    it('should not shift flows that are to the left of the source flow', () => {
      const nodes: { id: string; position: { x: number; y: number }; width: number; height: number }[] = [
        { id: 'left_event',   position: { x: 100, y: 100 }, width: 200, height: 100 },
        { id: 'source_event', position: { x: 500, y: 100 }, width: 200, height: 100 },
        { id: 'right_event',  position: { x: 900, y: 100 }, width: 200, height: 100 },
      ];
      const edges: { source: string; target: string }[] = [];

      const candidate = { x: 500, y: 100 };
      const result = findFlowAwarePosition(candidate, 'source_event', nodes, edges);
      expect(result.shiftNodeIds.has('left_event')).toBe(false);
    });

    it('should handle single-node flows (no edges)', () => {
      const nodes: { id: string; position: { x: number; y: number }; width: number; height: number }[] = [
        { id: 'a', position: { x: 100, y: 100 }, width: 200, height: 100 },
      ];
      const result = findFlowAwarePosition({ x: 100, y: 100 }, 'a', nodes, []);
      // Should find a free position (moved right — no neighbor to constrain)
      expect(
        result.position.x !== 100 || result.position.y !== 100
      ).toBe(true);
      expect(result.shiftAmount).toBe(0);
    });

    it('should not mutate the input candidate', () => {
      const candidate = { x: 100, y: 100 };
      const nodes: { id: string; position: { x: number; y: number }; width: number; height: number }[] = [
        { id: 'a', position: { x: 100, y: 100 }, width: 200, height: 100 },
      ];
      findFlowAwarePosition(candidate, 'a', nodes, []);
      expect(candidate).toEqual({ x: 100, y: 100 });
    });

    it('should prefer right over far-below when there is room within the flow zone', () => {
      const nodes: { id: string; position: { x: number; y: number }; width: number; height: number }[] = [
        { id: 'event',  position: { x: 100, y: 100 }, width: 200, height: 100 },
        { id: 'action', position: { x: 100, y: 350 }, width: 200, height: 100 },
      ];
      const edges: { source: string; target: string }[] = [{ source: 'event', target: 'action' }];

      // Candidate overlaps 'action'
      const candidate = { x: 100, y: 350 };
      const result = findFlowAwarePosition(candidate, 'event', nodes, edges);

      // Should move right (same row), not down
      expect(result.position.x).toBeGreaterThan(100);
      expect(result.position.y).toBe(350);
      expect(result.shiftAmount).toBe(0);
    });

    it('should shift right flow when right is blocked by a neighboring flow', () => {
      const nodes: { id: string; position: { x: number; y: number }; width: number; height: number }[] = [
        { id: 'event_L',  position: { x: 100, y: 100 }, width: 200, height: 100 },
        { id: 'action_L', position: { x: 100, y: 350 }, width: 200, height: 100 },
        { id: 'event_R',  position: { x: 350, y: 100 }, width: 200, height: 100 },
      ];
      const edges: { source: string; target: string }[] = [
        { source: 'event_L', target: 'action_L' },
      ];

      const candidate = { x: 100, y: 350 };
      const result = findFlowAwarePosition(candidate, 'event_L', nodes, edges);

      // Should stay on the same Y (not go down)
      expect(result.position.y).toBe(350);
      // Should have shifted the right flow to make room
      expect(result.shiftAmount).toBeGreaterThan(0);
      expect(result.shiftNodeIds.has('event_R')).toBe(true);
    });

    it('should work correctly with the two-flow side-by-side scenario from the issue', () => {
      const nodes: { id: string; position: { x: number; y: number }; width: number; height: number }[] = [
        // Left flow
        { id: 'L_event',   position: { x: 100, y: 100 },  width: 200, height: 50 },
        { id: 'L_action1', position: { x: 100, y: 300 },  width: 200, height: 100 },
        { id: 'L_action2', position: { x: 100, y: 550 },  width: 200, height: 100 },
        // Right flow
        { id: 'R_event',   position: { x: 600, y: 100 },  width: 200, height: 50 },
        { id: 'R_action1', position: { x: 600, y: 300 },  width: 200, height: 100 },
        { id: 'R_action2', position: { x: 600, y: 550 },  width: 200, height: 100 },
      ];
      const edges: { source: string; target: string }[] = [
        { source: 'L_event', target: 'L_action1' },
        { source: 'L_action1', target: 'L_action2' },
        { source: 'R_event', target: 'R_action1' },
        { source: 'R_action1', target: 'R_action2' },
      ];

      // Add successor to L_action2 — candidate is below L_action2
      const candidate = { x: 100, y: 550 + 100 + LAYOUT.NODE_SPACING_Y }; // below L_action2
      const result = findFlowAwarePosition(candidate, 'L_action2', nodes, edges);

      // The new node should be placed without overlapping any existing node
      expect(isOverlapping(
        result.position.x, result.position.y,
        NODE_DIMENSIONS.DEFAULT_WIDTH, NODE_DIMENSIONS.DEFAULT_HEIGHT,
        nodes,
      )).toBe(false);

      // The new node should NOT have crossed into the right flow's X territory
      expect(result.position.x).toBeLessThan(600);
    });
  });
});
