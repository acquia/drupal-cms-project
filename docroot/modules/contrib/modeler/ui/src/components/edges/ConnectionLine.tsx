import React from 'react';
import { ConnectionLineComponentProps } from 'reactflow';
import { buildPreviewPath } from '../../utils/edgePreviewPath';

/**
 * Custom connection line shown while the user drags a NEW edge from a handle
 * (issue #3585553 follow-on UX).
 *
 * React Flow's built-in connection line uses the `connectionLineType` shape
 * (here historically `SmoothStep`, which renders cornered/stepped). The user
 * disliked that the new-edge preview looked different from the (nicer) endpoint
 * RECONNECT preview. This component makes the two visually identical: it builds
 * the path with the SAME {@link buildPreviewPath} helper (a smooth cubic
 * bezier leaving the source handle along its `Position`) and renders it with
 * the SAME `.edge-reconnect-preview` CSS class — so dashed style, accent color,
 * and dark-mode adaptation all match the reconnect preview exactly.
 *
 * Anti-criterion [A1]: this affects ONLY the live drag preview. Committed edges
 * are still rendered by their normal edge component (DefaultEdge) and keep
 * their usual path; nothing here changes a saved edge's shape.
 *
 * The `from*`/`to*` coordinates React Flow passes are already in flow (canvas)
 * space and rendered inside the viewport transform, so no coordinate
 * conversion is needed — we pass them straight to `buildPreviewPath`.
 */
const ConnectionLine: React.FC<ConnectionLineComponentProps> = ({
  fromX,
  fromY,
  fromPosition,
  toX,
  toY,
}) => {
  const path = buildPreviewPath(fromX, fromY, fromPosition, toX, toY);

  return (
    <path
      className="edge-reconnect-preview"
      d={path}
      fill="none"
    />
  );
};

export default ConnectionLine;
