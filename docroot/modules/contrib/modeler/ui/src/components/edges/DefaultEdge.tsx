import React, { Profiler, useMemo } from 'react';
import { EdgeLabelRenderer, EdgeProps } from 'reactflow';
import { useEdgePath } from '../../hooks/useEdgePath';
import { useControlPointDrag } from '../../hooks/useControlPointDrag';
import { useEndpointDrag } from '../../hooks/useEndpointDrag';
import { buildPreviewPath } from '../../utils/edgePreviewPath';
import EdgeOrderBadge from './EdgeOrderBadge';
import QuickAddEdgeButton from '../QuickAddEdgeButton';
import EdgeDeleteButton from './EdgeDeleteButton';
import type { StoreComponent as Component } from '../../types/settings';
import { onRenderCallback } from '../../utils/profiling';
import { EDGE_STYLING } from '../../constants/dimensions';
import { t } from '../../utils/translation';
import type { BaseEdgeData } from '../../types/settings';

interface DefaultEdgeData extends BaseEdgeData {
  onAddCondition?: (edgeId: string, component: Component) => void;
  onAddActionOnEdge?: (edgeId: string, component: Component) => void;
  onDeleteEdge?: (edgeId: string) => void;
}

interface DefaultEdgeProps extends EdgeProps {
  data?: DefaultEdgeData;
}

const DefaultEdge: React.FC<DefaultEdgeProps> = ({
  id,
  source,
  target,
  sourceHandleId,
  targetHandleId,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  data,
  style = {},
  markerEnd,
  selected,
  interactionWidth,
}) => {
  // Get selected state from either prop or data
  const isEdgeSelected = selected || data?.isSelected || false;

  // Get control point offset from data (stored as absolute offset from center)
  const controlOffset = useMemo(() => data?.controlOffset || { x: 0, y: 0 }, [data?.controlOffset]);

  // Calculate edge center
  const edgeCenterX = (sourceX + targetX) / 2;
  const edgeCenterY = (sourceY + targetY) / 2;

  const controlPointX = edgeCenterX + controlOffset.x;
  const controlPointY = edgeCenterY + controlOffset.y;

  // Use extracted path calculation hook
  const [edgePath, labelX, labelY] = useEdgePath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    controlOffset,
  });

  const replayHighlight = data?.replayHighlight;

  // Use extracted control point drag hook
  const { isDragging, handleControlPointDrag } = useControlPointDrag({
    id,
    edgeCenterX,
    edgeCenterY,
    isLocked: false,
    onEdgeUpdate: data?.onEdgeUpdate,
  });

  // Endpoint reconnection grips (issue #3585553). Each end is independently
  // draggable IFF its grip is enabled (computed by FlowCanvas: this edge is
  // selected AND it is the only selected edge using that handle) and the
  // canvas is not globally locked. Dragging is handled by useEndpointDrag,
  // mirroring the control-point drag pattern.
  const endpointLocked = !!data?.globalLocked;
  const sourceGripEnabled = !!data?.sourceGripEnabled;
  const targetGripEnabled = !!data?.targetGripEnabled;

  // Visibility gate: the grip renders only when the edge is selected, its end
  // is eligible, and the canvas is unlocked.
  const showSourceGrip = isEdgeSelected && !endpointLocked && sourceGripEnabled;
  const showTargetGrip = isEdgeSelected && !endpointLocked && targetGripEnabled;

  // Defensive double-gate: pass `isLocked` to the drag hook that is true
  // whenever the grip is NOT shown, so even if the grip element somehow
  // existed it could not initiate a drag. This keeps "no grip on a shared
  // handle" deterministic — the handle neither renders a grip NOR functions.
  const {
    isDragging: isSourceDragging,
    previewPoint: sourcePreviewPoint,
    handleEndpointDrag: handleSourceGripDrag,
  } = useEndpointDrag({
    edgeId: id,
    endpoint: 'source',
    source,
    target,
    sourceHandle: sourceHandleId,
    targetHandle: targetHandleId,
    isLocked: !showSourceGrip,
    validateConnection: data?.validateReconnect,
    onReconnectEdge: data?.onReconnectEdge,
  });
  const {
    isDragging: isTargetDragging,
    previewPoint: targetPreviewPoint,
    handleEndpointDrag: handleTargetGripDrag,
  } = useEndpointDrag({
    edgeId: id,
    endpoint: 'target',
    source,
    target,
    sourceHandle: sourceHandleId,
    targetHandle: targetHandleId,
    isLocked: !showTargetGrip,
    validateConnection: data?.validateReconnect,
    onReconnectEdge: data?.onReconnectEdge,
  });

  // Live reconnect preview (issue #3585553). While a grip is dragged, draw a
  // smoothstep-style preview path from the FIXED endpoint to the cursor:
  //  - dragging the SOURCE grip → preview from the fixed TARGET end,
  //  - dragging the TARGET grip → preview from the fixed SOURCE end.
  // Mirrors React Flow's new-connection line so the prospective path is clear.
  const sourceDragPreview = isSourceDragging && sourcePreviewPoint
    ? buildPreviewPath(targetX, targetY, targetPosition, sourcePreviewPoint.x, sourcePreviewPoint.y)
    : null;
  const targetDragPreview = isTargetDragging && targetPreviewPoint
    ? buildPreviewPath(sourceX, sourceY, sourcePosition, targetPreviewPoint.x, targetPreviewPoint.y)
    : null;
  const reconnectPreviewPath = sourceDragPreview ?? targetDragPreview;

  // Apply replay highlight if present
  const edgeStyle = replayHighlight ? {
    ...style,
    stroke: replayHighlight,
    strokeWidth: 3
  } : style;

  // Generous edge-selection hit area (issue #3585553 follow-on UX). DefaultEdge
  // hand-rolls its <path> instead of using React Flow's <BaseEdge>, so it must
  // render the transparent interaction stroke itself — <BaseEdge> would
  // otherwise do this automatically from `interactionWidth`. The wide
  // transparent path makes the edge selectable when clicking NEAR the curve,
  // not only exactly on the 2px visible stroke. Falls back to the shared
  // EDGE_STYLING.INTERACTION_WIDTH (30px) when React Flow does not pass one.
  const interactionStrokeWidth = interactionWidth ?? EDGE_STYLING.INTERACTION_WIDTH;

  return (
    <Profiler id="DefaultEdge" onRender={onRenderCallback}>
    <>
      {/* Transparent wide interaction path (issue #3585553 follow-on UX).
          Rendered FIRST/underneath so the visible stroke and markers draw on
          top; it carries the click/hover hit area (stroke is transparent but
          pointer-events default to the stroke). Shared across replay and
          normal rendering so selection is equally easy in both states. */}
      <path
        className="react-flow__edge-interaction"
        d={edgePath}
        fill="none"
        stroke="transparent"
        strokeWidth={interactionStrokeWidth}
      />
      {replayHighlight ? (
        <g style={{ stroke: replayHighlight, strokeWidth: 3 }}>
          <path
            id={id}
            style={{ fill: 'none' }}
            className="react-flow__edge-path replay-highlighted"
            d={edgePath}
            markerEnd={markerEnd}
            stroke={replayHighlight}
            strokeWidth={3}
          />
        </g>
      ) : (
        <>
          <path
            id={id}
            style={edgeStyle}
            className={`react-flow__edge-path ${selected ? 'selected' : ''}`}
            d={edgePath}
            markerEnd={markerEnd}
          />
          {isEdgeSelected && (controlOffset.x !== 0 || controlOffset.y !== 0) && (
            <line
              className="edge-guide-line"
              x1={edgeCenterX}
              y1={edgeCenterY}
              x2={controlPointX}
              y2={controlPointY}
            />
          )}
        </>
      )}
      {/* Reconnect preview line (issue #3585553) — rendered in SVG (flow) space
          from the fixed endpoint to the cursor while a grip is dragged. The
          edge stays rendered as-is; this overlay shows the prospective path. */}
      {reconnectPreviewPath && (
        <path
          className="edge-reconnect-preview"
          d={reconnectPreviewPath}
          fill="none"
        />
      )}
      <EdgeLabelRenderer>
        {/* Edge action cluster — trash (left) + quick-add plus (right),
            centered on the edge midpoint.  The trash deletes the connection
            immediately (undo exists); the plus adds a condition or inserts a
            node.  The wrapper renders when either affordance is available so
            a locked-quick-add edge can still expose delete, and vice versa.
            The trash is revealed on hover (CSS) or when the edge is selected
            (the `selected` modifier class). */}
        {(data?.onAddCondition || data?.onDeleteEdge) && (
          <div
            className={`edge-quick-add-wrapper${isEdgeSelected ? ' selected' : ''}`}
            style={{
              transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
            }}
          >
            <div className="edge-action-cluster">
              {data?.onDeleteEdge && (
                <EdgeDeleteButton
                  edgeId={id}
                  onDelete={(edgeId) => data.onDeleteEdge!(edgeId)}
                  disabled={false}
                />
              )}
              {data?.onAddCondition && (
                <QuickAddEdgeButton
                  edgeId={id}
                  onAddCondition={(component) => data.onAddCondition!(id, component)}
                  onAddAction={(component) => data.onAddActionOnEdge?.(id, component)}
                  disabled={false}
                />
              )}
            </div>
          </div>
        )}

        {/* Control point when selected */}
        {isEdgeSelected && (
          <div
            className={`edge-control-point-wrapper ${isDragging ? 'dragging' : ''}`}
            onMouseDown={handleControlPointDrag}
            style={{
              transform: `translate(-50%, -50%) translate(${controlPointX}px,${controlPointY}px)`,
              cursor: isDragging ? 'grabbing' : 'grab',
            }}
          >
            <svg width="24" height="24">
              <circle className="control-point-outer" cx={12} cy={12} r={12} />
              <circle className="control-point-inner" cx={12} cy={12} r={6} />
            </svg>
          </div>
        )}

        {/* Endpoint reconnection grips (issue #3585553). Rendered only when the
            edge is selected AND this end's grip is enabled (the edge is the
            sole selected edge on that handle) AND the canvas is unlocked.
            Grabbed via onMouseDown → useEndpointDrag, mirroring the control
            point. On an invalid/empty drop the edge is never mutated, so the
            endpoint snaps back automatically. */}
        {showSourceGrip && (
          <div
            className={`edge-endpoint-grip edge-endpoint-grip--source ${isSourceDragging ? 'dragging' : ''}`}
            data-edge-id={id}
            onMouseDown={handleSourceGripDrag}
            title={t('Drag to reconnect the source')}
            style={{
              // While dragging, the grip tracks the cursor (previewPoint);
              // otherwise it sits on the edge's current source endpoint.
              transform: `translate(-50%, -50%) translate(${isSourceDragging && sourcePreviewPoint ? sourcePreviewPoint.x : sourceX}px,${isSourceDragging && sourcePreviewPoint ? sourcePreviewPoint.y : sourceY}px)`,
              cursor: isSourceDragging ? 'grabbing' : 'grab',
            }}
          >
            <svg width="20" height="20">
              <circle className="endpoint-grip-outer" cx={10} cy={10} r={9} />
              <circle className="endpoint-grip-inner" cx={10} cy={10} r={4} />
            </svg>
          </div>
        )}
        {showTargetGrip && (
          <div
            className={`edge-endpoint-grip edge-endpoint-grip--target ${isTargetDragging ? 'dragging' : ''}`}
            data-edge-id={id}
            onMouseDown={handleTargetGripDrag}
            title={t('Drag to reconnect the target')}
            style={{
              // While dragging, the grip tracks the cursor (previewPoint);
              // otherwise it sits on the edge's current target endpoint.
              transform: `translate(-50%, -50%) translate(${isTargetDragging && targetPreviewPoint ? targetPreviewPoint.x : targetX}px,${isTargetDragging && targetPreviewPoint ? targetPreviewPoint.y : targetY}px)`,
              cursor: isTargetDragging ? 'grabbing' : 'grab',
            }}
          >
            <svg width="20" height="20">
              <circle className="endpoint-grip-outer" cx={10} cy={10} r={9} />
              <circle className="endpoint-grip-inner" cx={10} cy={10} r={4} />
            </svg>
          </div>
        )}

        {/* Edge Order Number Badge — positioned northeast of the quick-add
            button.  Uses labelX/labelY (the actual rendered edge midpoint)
            instead of edgeOrderInfo.pathX/pathY (computed from node positions)
            so the badge stays anchored to the button even on initial load
            before node dimensions are fully measured. */}
        {data?.edgeOrdersVisible && data?.edgeOrderInfo && (
          <EdgeOrderBadge
            edgeId={id}
            edgeOrderInfo={{
              ...data.edgeOrderInfo,
              pathX: data.edgeOrderInfo.pathX != null
                ? labelX + EDGE_STYLING.BADGE_NE_OFFSET
                : undefined,
              pathY: labelY,
            }}
            isLocked={!!data?.globalLocked}
            onReorderEdge={data.onReorderEdge}
          />
        )}
      </EdgeLabelRenderer>
    </>
    </Profiler>
  );
};

export default DefaultEdge;
