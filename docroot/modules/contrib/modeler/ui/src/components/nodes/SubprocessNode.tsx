import React, { Profiler, memo } from 'react';
import { Handle, Position, NodeProps } from 'reactflow';
import { NODE_TYPE_ICONS } from '../../utils/nodeIcons';
import { t } from '../../utils/translation';
import { getComponentLabel } from '../../utils/componentUtils';
import NodeWrapper from './NodeWrapper';
import { onRenderCallback } from '../../utils/profiling';
import type { BaseNodeData } from '../../types/settings';

interface SubprocessNodeData extends BaseNodeData {
  subflowCount?: number;
}

/** Shared canvas icon — the replay step list resolves the same one. */
const SubprocessIcon = NODE_TYPE_ICONS.subprocess;

const SubprocessNode = memo<NodeProps<SubprocessNodeData>>(({ data, selected }) => {
  return (
    <Profiler id="SubprocessNode" onRender={onRenderCallback}>
    <NodeWrapper data={data} selected={selected} nodeClass="subprocess-node">
      <Handle
        type="target"
        position={Position.Top}
        className="node-handle"
        id="input"
      />

      <div className="node-header">
        <SubprocessIcon className="node-icon" />
        <span className="node-type">{getComponentLabel('subprocess')}</span>
      </div>

      <div className="node-body">
        <div className="node-label">{data.label || getComponentLabel('subprocess')}</div>
        {data.subflowCount && data.subflowCount > 0 && (
          <div className="subflow-count">
            {t('@count nodes', { '@count': data.subflowCount })}
          </div>
        )}
      </div>

      <Handle
        type="source"
        position={Position.Bottom}
        className="node-handle"
        id="output"
      />
    </NodeWrapper>
    </Profiler>
  );
});

SubprocessNode.displayName = 'SubprocessNode';

export default SubprocessNode;
