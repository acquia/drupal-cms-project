import React, { Profiler, memo } from 'react';
import { Handle, Position, NodeProps } from 'reactflow';
import classNames from 'classnames';
import { NODE_TYPE_ICONS } from '../../utils/nodeIcons';
import { getComponentLabel } from '../../utils/componentUtils';
import { t } from '../../utils/translation';
import NodeWrapper from './NodeWrapper';
import { onRenderCallback } from '../../utils/profiling';
import type { BaseNodeData } from '../../types/settings';

/** Shared canvas icon — the replay step list resolves the same one. */
const ElementIcon = NODE_TYPE_ICONS.element;

const CustomNode = memo<NodeProps<BaseNodeData>>(({ data, selected }) => {
  // Explanatory tooltip when the source handle is disabled (issue #3589093).
  const sourceHandleTitle = data.sourceHandleDisabled
    ? t('Maximum number of connections reached.')
    : undefined;
  return (
    <Profiler id="CustomNode" onRender={onRenderCallback}>
    <NodeWrapper data={data} selected={selected} nodeClass="action-node">
      <Handle
        type="target"
        position={Position.Top}
        className="node-handle"
        id="input"
      />

      <div className="node-header">
        <ElementIcon className="node-icon" />
        <span className="node-type">{getComponentLabel('element')}</span>
      </div>

      <div className="node-body">
        <div className="node-label">{data.label}</div>
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

CustomNode.displayName = 'CustomNode';

export default CustomNode;
