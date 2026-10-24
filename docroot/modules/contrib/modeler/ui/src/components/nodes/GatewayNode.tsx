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
 * Compact gateway card — annotation + delete live in the header
 * (same pattern as condition edge cards) instead of a separate footer.
 */
/** Shared canvas icon — the replay step list resolves the same one. */
const GatewayIcon = NODE_TYPE_ICONS.gateway;

const GatewayNode = memo<NodeProps<BaseNodeData>>(({ data, selected }) => {
  const handleDelete = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    if (data.onDelete) {
      data.onDelete();
    }
  }, [data]);

  const isLocked = !!data.isLocked;
  const hasAnnotation = !!data.annotation;

  // Explanatory tooltip when the source handle is disabled (issue #3589093).
  const sourceHandleTitle = data.sourceHandleDisabled
    ? t('Maximum number of connections reached.')
    : undefined;

  return (
    <Profiler id="GatewayNode" onRender={onRenderCallback}>
    <NodeWrapper data={data} selected={selected} nodeClass="gateway-node" compact>
      <Handle
        type="target"
        position={Position.Top}
        className="node-handle"
        id="input"
      />

      <div className="node-header">
        <GatewayIcon className="node-icon" />
        <span className="node-type">{getComponentLabel('gateway')}</span>
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
        <div className="node-label">{data.label || getComponentLabel('gateway')}</div>
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

GatewayNode.displayName = 'GatewayNode';

export default GatewayNode;
