import React, { Profiler, memo, useCallback } from 'react';
import { Handle, Position, NodeProps } from 'reactflow';
import { FiTrash2, FiFileText } from 'react-icons/fi';
import classNames from 'classnames';
import { NODE_TYPE_ICONS } from '../../utils/nodeIcons';
import { getComponentLabel } from '../../utils/componentUtils';
import { t } from '../../utils/translation';
import NodeWrapper from './NodeWrapper';
import { onRenderCallback } from '../../utils/profiling';
import { UI_DIMENSIONS } from '../../constants/dimensions';
import type { BaseNodeData } from '../../types/settings';

/**
 * Compact condition card — annotation + delete live in the header
 * (same pattern as GatewayNode) instead of a separate footer.
 *
 * Conditions are first-class React Flow nodes (node.type === 'condition',
 * componentType 5 = Link).  Promotion/demotion to the backend edge contract
 * is handled by the translation layer in `utils/modelUtils.ts`; this component
 * only renders the node and is draggable/selectable as a normal node.
 */
/** Shared canvas icon — the replay step list resolves the same one. */
const ConditionIcon = NODE_TYPE_ICONS.condition;

const ConditionNode = memo<NodeProps<BaseNodeData>>(({ data, selected }) => {
  const handleDelete = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    if (data.onDelete) {
      data.onDelete();
    }
  }, [data]);

  const isLocked = !!data.isLocked;
  const hasAnnotation = !!data.annotation;

  // When the source handle is disabled, show an explanatory native tooltip so
  // the user understands why they cannot start a connection (issue #3589093).
  // No title is set on an enabled handle to avoid a misleading tooltip.
  const sourceHandleTitle = data.sourceHandleDisabled
    ? data.sourceHandleDisabledReason === 'max-successors'
      ? t('Maximum number of connections reached.')
      : t('A condition can have only one outgoing connection.')
    : undefined;

  return (
    <Profiler id="ConditionNode" onRender={onRenderCallback}>
    <NodeWrapper data={data} selected={selected} nodeClass="condition-node" compact>
      <Handle
        type="target"
        position={Position.Top}
        className="node-handle"
        id="input"
      />

      <div className="node-header">
        <ConditionIcon className="node-icon" />
        <span className="node-type">{getComponentLabel('link')}</span>
        <div className="node-header-actions">
          {hasAnnotation && (
            <span
              className="node-footer-annotation"
              role="img"
              title={data.annotation}
              aria-label={t('Has annotation')}
            >
              <FiFileText size={UI_DIMENSIONS.ICON_SIZE_SMALL} />
            </span>
          )}
          {!isLocked && (
            <button
              className="node-footer-delete"
              onClick={handleDelete}
              title={t('Delete node')}
              aria-label={t('Delete node')}
            >
              <FiTrash2 size={UI_DIMENSIONS.ICON_SIZE_SMALL} />
            </button>
          )}
        </div>
      </div>

      <div className="node-body">
        <div className="node-label">{data.label || getComponentLabel('link')}</div>
      </div>

      <Handle
        type="source"
        position={Position.Bottom}
        className={classNames('node-handle', { 'node-handle--disabled': data.sourceHandleDisabled })}
        id="output"
        isConnectable={!data.sourceHandleDisabled}
        title={sourceHandleTitle}
      />
    </NodeWrapper>
    </Profiler>
  );
});

ConditionNode.displayName = 'ConditionNode';

export default ConditionNode;
