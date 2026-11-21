/**
 * Custom hook for computing SVG path data for edges.
 * Handles default cubic bezier paths, manually positioned control point
 * paths, and loopback arcs for back-edges (cycles).
 */
import { useMemo } from 'react';
import { Position } from 'reactflow';
import { NODE_DIMENSIONS } from '../constants/dimensions';

interface ControlOffset {
  x: number;
  y: number;
}

interface UseEdgePathProps {
  sourceX: number;
  sourceY: number;
  targetX: number;
  targetY: number;
  sourcePosition: Position;
  targetPosition: Position;
  controlOffset: ControlOffset;
}

type EdgePathResult = [path: string, labelX: number, labelY: number];

/** Horizontal offset from the node center for the loopback arc. */
const LOOPBACK_ARC_OFFSET = NODE_DIMENSIONS.CARD_WIDTH / 2 + 40;

/** Vertical stub length leaving source / entering target before curving. */
const LOOPBACK_STUB = 30;

/**
 * Calculates base control points for a given source or target position.
 */
function getControlPoint(
  x: number,
  y: number,
  position: Position,
  distance: number,
  _isSource: boolean,
): { x: number; y: number } {
  switch (position) {
    case Position.Right:
      return { x: x + distance, y };
    case Position.Left:
      return { x: x - distance, y };
    case Position.Bottom:
      return { x, y: y + distance };
    case Position.Top:
      return { x, y: y - distance };
    default:
      return { x, y };
  }
}

/**
 * Detects whether the edge flows backward based on source/target positions.
 */
function isBackwardFlow(
  sourcePosition: Position,
  targetPosition: Position,
  sourceX: number,
  sourceY: number,
  targetX: number,
  targetY: number,
): boolean {
  return (
    (sourcePosition === Position.Bottom && targetPosition === Position.Top && targetY < sourceY) ||
    (sourcePosition === Position.Top && targetPosition === Position.Bottom && targetY > sourceY) ||
    (sourcePosition === Position.Right && targetPosition === Position.Left && targetX < sourceX) ||
    (sourcePosition === Position.Left && targetPosition === Position.Right && targetX > sourceX)
  );
}

/**
 * Detect a vertical back-edge: source exits Bottom, target enters Top,
 * and the target is above the source (loopback / cycle edge).
 */
function isVerticalBackEdge(
  sourcePosition: Position,
  targetPosition: Position,
  sourceY: number,
  targetY: number,
): boolean {
  return (
    sourcePosition === Position.Bottom &&
    targetPosition === Position.Top &&
    targetY < sourceY
  );
}

/**
 * Build a loopback arc path that curves to the right of the nodes.
 *
 * Shape:  source ↓ stub → curve right → travel up → curve left → stub ↑ target
 *
 * Uses cubic bezier segments so the path is smooth.  The arc bulges to
 * the right of the rightmost endpoint by LOOPBACK_ARC_OFFSET px.
 */
function buildLoopbackPath(
  sourceX: number,
  sourceY: number,
  targetX: number,
  targetY: number,
): { path: string; labelX: number; labelY: number } {
  // The arc sits to the right of whichever endpoint is further right.
  const rightX = Math.max(sourceX, targetX) + LOOPBACK_ARC_OFFSET;

  // Stub endpoints (short vertical segment leaving/entering the handle)
  const stubBottomY = sourceY + LOOPBACK_STUB;
  const stubTopY = targetY - LOOPBACK_STUB;

  // Build path:
  //   M  start at source
  //   L  short stub downward
  //   C  curve from stub-bottom → arc-right-bottom → arc-right-midY
  //   C  curve from arc-right-midY → arc-right-top → stub-top
  //   L  arrive at target
  const midY = (stubBottomY + stubTopY) / 2;

  const path = [
    `M ${sourceX},${sourceY}`,
    `L ${sourceX},${stubBottomY}`,
    `C ${sourceX},${stubBottomY + LOOPBACK_STUB} ${rightX},${stubBottomY} ${rightX},${midY}`,
    `C ${rightX},${stubTopY} ${targetX},${stubTopY - LOOPBACK_STUB} ${targetX},${stubTopY}`,
    `L ${targetX},${targetY}`,
  ].join(' ');

  // Label sits at the rightmost point of the arc, vertically centered.
  const labelX = rightX;
  const labelY = midY;

  return { path, labelX, labelY };
}

export function useEdgePath({
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  controlOffset,
}: UseEdgePathProps): EdgePathResult {
  const controlPointX = (sourceX + targetX) / 2 + controlOffset.x;
  const controlPointY = (sourceY + targetY) / 2 + controlOffset.y;

  return useMemo(() => {
    const distance = Math.sqrt(Math.pow(targetX - sourceX, 2) + Math.pow(targetY - sourceY, 2));
    const backward = isBackwardFlow(sourcePosition, targetPosition, sourceX, sourceY, targetX, targetY);
    const verticalBack = isVerticalBackEdge(sourcePosition, targetPosition, sourceY, targetY);

    // ── Loopback arc for vertical back-edges (cycles) ──
    // Only when the user hasn't manually repositioned the control point.
    if (verticalBack && controlOffset.x === 0 && controlOffset.y === 0) {
      const loop = buildLoopbackPath(sourceX, sourceY, targetX, targetY);
      return [loop.path, loop.labelX, loop.labelY];
    }

    if (controlOffset.x !== 0 || controlOffset.y !== 0) {
      // Manual control point path
      let baseControlPointDistance = Math.min(distance * 0.25, 50);
      if (backward) {
        baseControlPointDistance = Math.max(baseControlPointDistance, Math.abs(targetY - sourceY) * 0.4, 60);
      }

      const sourceControl = getControlPoint(sourceX, sourceY, sourcePosition, baseControlPointDistance, true);
      const targetControl = getControlPoint(targetX, targetY, targetPosition, baseControlPointDistance, false);

      const midPointX = (sourceX + controlPointX) / 2;
      const midPointY = (sourceY + controlPointY) / 2;

      const path = `M ${sourceX},${sourceY} C ${sourceControl.x},${sourceControl.y} ${midPointX},${midPointY} ${controlPointX},${controlPointY} S ${targetControl.x},${targetControl.y} ${targetX},${targetY}`;

      return [path, controlPointX, controlPointY];
    }

    // Default cubic bezier path
    let controlPointDistance = Math.min(distance * 0.25, 50);
    if (backward) {
      controlPointDistance = Math.max(controlPointDistance, Math.abs(targetY - sourceY) * 0.4, 60);
    }

    const sourceControl = getControlPoint(sourceX, sourceY, sourcePosition, controlPointDistance, true);
    const targetControl = getControlPoint(targetX, targetY, targetPosition, controlPointDistance, false);

    const path = `M ${sourceX},${sourceY} C ${sourceControl.x},${sourceControl.y} ${targetControl.x},${targetControl.y} ${targetX},${targetY}`;

    // Calculate label position at t=0.5 on cubic bezier curve
    const t = 0.5;
    const labelPosX = Math.pow(1 - t, 3) * sourceX + 3 * Math.pow(1 - t, 2) * t * sourceControl.x + 3 * (1 - t) * Math.pow(t, 2) * targetControl.x + Math.pow(t, 3) * targetX;
    const labelPosY = Math.pow(1 - t, 3) * sourceY + 3 * Math.pow(1 - t, 2) * t * sourceControl.y + 3 * (1 - t) * Math.pow(t, 2) * targetControl.y + Math.pow(t, 3) * targetY;

    return [path, labelPosX, labelPosY];
  }, [sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition, controlPointX, controlPointY, controlOffset]);
}
