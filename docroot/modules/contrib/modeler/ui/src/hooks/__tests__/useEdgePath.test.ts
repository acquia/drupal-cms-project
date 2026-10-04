import { renderHook } from '@testing-library/react';
import { useEdgePath } from '../useEdgePath';

// Mock reactflow Position enum
jest.mock('reactflow', () => ({
  Position: {
    Top: 'top',
    Bottom: 'bottom',
    Left: 'left',
    Right: 'right',
  },
}));

const { Position } = require('reactflow');

describe('useEdgePath', () => {
  const defaultProps = {
    sourceX: 100,
    sourceY: 200,
    targetX: 400,
    targetY: 200,
    sourcePosition: Position.Right,
    targetPosition: Position.Left,
    controlOffset: { x: 0, y: 0 },
  };

  describe('return value', () => {
    it('should return a tuple of [path, labelX, labelY]', () => {
      const { result } = renderHook(() => useEdgePath(defaultProps));
      expect(result.current).toHaveLength(3);
      expect(typeof result.current[0]).toBe('string');
      expect(typeof result.current[1]).toBe('number');
      expect(typeof result.current[2]).toBe('number');
    });
  });

  describe('default path (no control offset)', () => {
    it('should generate an SVG path starting at source', () => {
      const { result } = renderHook(() => useEdgePath(defaultProps));
      const path = result.current[0];
      expect(path).toMatch(/^M 100,200/);
    });

    it('should generate a cubic bezier path ending at target', () => {
      const { result } = renderHook(() => useEdgePath(defaultProps));
      const path = result.current[0];
      expect(path).toMatch(/400,200$/);
    });

    it('should place label near the midpoint of the path', () => {
      const { result } = renderHook(() => useEdgePath(defaultProps));
      const labelX = result.current[1];
      const labelY = result.current[2];
      // Label should be roughly between source and target
      expect(labelX).toBeGreaterThan(100);
      expect(labelX).toBeLessThan(400);
      // Y should be close to the edge center for horizontal edge
      expect(Math.abs(labelY - 200)).toBeLessThan(50);
    });
  });

  describe('manual control point', () => {
    it('should generate a composite path with control offset', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          ...defaultProps,
          controlOffset: { x: 50, y: -100 },
        })
      );
      const path = result.current[0];
      expect(path).toMatch(/^M 100,200/);
      expect(path).toContain('S'); // Should use S command for smooth continuation
    });

    it('should position label at the control point', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          ...defaultProps,
          controlOffset: { x: 50, y: -100 },
        })
      );
      const labelX = result.current[1];
      const labelY = result.current[2];
      const edgeCenterX = (100 + 400) / 2;
      const edgeCenterY = (200 + 200) / 2;
      expect(labelX).toBe(edgeCenterX + 50);
      expect(labelY).toBe(edgeCenterY + (-100));
    });
  });

  describe('backward flow detection', () => {
    it('should handle right-to-left backward flow', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          sourceX: 400,
          sourceY: 200,
          targetX: 100,
          targetY: 200,
          sourcePosition: Position.Right,
          targetPosition: Position.Left,
          controlOffset: { x: 0, y: 0 },
        })
      );
      const path = result.current[0];
      expect(path).toBeDefined();
    });
  });

  describe('loopback arc for vertical back-edges', () => {
    it('should render a loopback arc when source is below target (cycle)', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          sourceX: 200,
          sourceY: 400,
          targetX: 200,
          targetY: 100,
          sourcePosition: Position.Bottom,
          targetPosition: Position.Top,
          controlOffset: { x: 0, y: 0 },
        })
      );
      const path = result.current[0];
      // Should start at source
      expect(path).toMatch(/^M 200,400/);
      // Should end at target
      expect(path).toMatch(/200,100$/);
      // Should contain L (stub segments) and C (curves) — not just a simple bezier
      expect(path).toContain('L');
      expect(path).toContain('C');
    });

    it('should place label to the right of nodes (at the arc apex)', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          sourceX: 200,
          sourceY: 400,
          targetX: 200,
          targetY: 100,
          sourcePosition: Position.Bottom,
          targetPosition: Position.Top,
          controlOffset: { x: 0, y: 0 },
        })
      );
      const labelX = result.current[1];
      const labelY = result.current[2];
      // Label should be to the right of both source and target
      expect(labelX).toBeGreaterThan(200);
      // Label should be vertically between source and target
      expect(labelY).toBeGreaterThan(100);
      expect(labelY).toBeLessThan(400);
    });

    it('should use regular bezier when user has set a manual control offset', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          sourceX: 200,
          sourceY: 400,
          targetX: 200,
          targetY: 100,
          sourcePosition: Position.Bottom,
          targetPosition: Position.Top,
          controlOffset: { x: 50, y: 0 },
        })
      );
      const path = result.current[0];
      // Should use S command (manual control point path), not L (loopback)
      expect(path).toContain('S');
    });

    it('should not use loopback for forward edges (target below source)', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          sourceX: 200,
          sourceY: 100,
          targetX: 200,
          targetY: 400,
          sourcePosition: Position.Bottom,
          targetPosition: Position.Top,
          controlOffset: { x: 0, y: 0 },
        })
      );
      const path = result.current[0];
      // Forward edge should be a simple cubic bezier (no L segments)
      expect(path).not.toContain('L');
    });
  });

  describe('different handle positions', () => {
    it('should handle top source position', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          ...defaultProps,
          sourcePosition: Position.Top,
        })
      );
      expect(result.current[0]).toBeDefined();
    });

    it('should handle bottom source and top target', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          ...defaultProps,
          sourcePosition: Position.Bottom,
          targetPosition: Position.Top,
        })
      );
      expect(result.current[0]).toBeDefined();
    });

    it('should handle left source position', () => {
      const { result } = renderHook(() =>
        useEdgePath({
          ...defaultProps,
          sourcePosition: Position.Left,
          targetPosition: Position.Right,
        })
      );
      expect(result.current[0]).toBeDefined();
    });
  });

  describe('memoization', () => {
    it('should return same result for same inputs', () => {
      const { result, rerender } = renderHook(() => useEdgePath(defaultProps));
      const firstResult = result.current;
      rerender();
      expect(result.current).toBe(firstResult);
    });

    it('should recalculate when control offset changes', () => {
      let offset = { x: 0, y: 0 };
      const { result, rerender } = renderHook(() =>
        useEdgePath({ ...defaultProps, controlOffset: offset })
      );
      expect(result.current).toHaveLength(3);
      offset = { x: 50, y: 50 };
      rerender();
      // Verify it still returns valid data after offset change
      expect(result.current).toHaveLength(3);
      expect(typeof result.current[0]).toBe('string');
    });
  });
});
