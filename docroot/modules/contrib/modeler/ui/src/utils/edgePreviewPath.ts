/**
 * Pure helper that builds the SVG path for the edge PREVIEW line — used by BOTH
 * the endpoint-reconnection preview (issue #3585553) and the NEW-edge
 * connection line (issue #3585553 follow-on UX).
 *
 * While the user drags an existing edge endpoint (DefaultEdge) OR drags a brand
 * new edge from a handle (the custom `ConnectionLine` component), a live
 * preview is drawn from the FIXED endpoint to the cursor. This builds a smooth
 * CUBIC-BEZIER curve (`M..C..`) that leaves the fixed handle along its
 * `Position` (the same default-case shape produced by `useEdgePath`), giving a
 * consistent "this is where the edge will go" read. Both previews share the
 * `.edge-reconnect-preview` CSS class so the dashed/accent/dark-mode treatment
 * is identical.
 *
 * This is a plain function (not a hook) so callers can invoke it conditionally
 * during a drag without violating the rules of hooks.
 */
import { Position } from 'reactflow';

/** Project a control point off a handle along its Position by `distance`. */
function controlPointFor(x: number, y: number, position: Position, distance: number): { x: number; y: number } {
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
 * Build a cubic-bezier preview path from a fixed endpoint to the cursor.
 *
 * @param fixedX     X of the endpoint NOT being dragged (flow coords).
 * @param fixedY     Y of the endpoint NOT being dragged (flow coords).
 * @param fixedPosition  Handle side of the fixed endpoint (so the curve leaves
 *                       it naturally, matching the rendered edge).
 * @param cursorX    X of the cursor (flow coords).
 * @param cursorY    Y of the cursor (flow coords).
 * @returns SVG path `d` string.
 */
export function buildPreviewPath(
  fixedX: number,
  fixedY: number,
  fixedPosition: Position,
  cursorX: number,
  cursorY: number,
): string {
  const distance = Math.sqrt((cursorX - fixedX) ** 2 + (cursorY - fixedY) ** 2);
  // Same control-point distance heuristic as useEdgePath's default case.
  const controlDistance = Math.min(distance * 0.25, 50);

  const fixedControl = controlPointFor(fixedX, fixedY, fixedPosition, controlDistance);

  // The cursor end has no handle Position; pull its control point straight
  // back toward the fixed end so the curve eases into the pointer smoothly.
  const cursorControlX = cursorX + (fixedX - cursorX) * 0.25;
  const cursorControlY = cursorY + (fixedY - cursorY) * 0.25;

  return `M ${fixedX},${fixedY} C ${fixedControl.x},${fixedControl.y} ${cursorControlX},${cursorControlY} ${cursorX},${cursorY}`;
}
