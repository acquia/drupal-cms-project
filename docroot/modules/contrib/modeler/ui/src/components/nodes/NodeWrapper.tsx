/**
 * NodeWrapper - Shared wrapper component for all node types.
 *
 * Provides the common chrome shared by CustomNode, StartNode, GatewayNode,
 * and SubprocessNode: className construction, node footer with annotation
 * indicator and delete button, and QuickAddButton rendering.
 *
 * Layout (standard):
 *   ┌──────────────────────┐
 *   │  header (icon + type)│
 *   ├──────────────────────┤
 *   │  body (label)        │
 *   ├──────────────────────┤
 *   │  footer (ann | trash)│
 *   └──────────────────────┘
 *        [+] (hover)
 *
 * Layout (compact — used by GatewayNode):
 *   ┌──────────────────────────────┐
 *   │  header (icon + type + actions)│
 *   ├──────────────────────────────┤
 *   │  body (label)                │
 *   └──────────────────────────────┘
 *        [+] (hover)
 *
 * In compact mode the footer is suppressed; callers embed actions
 * (annotation indicator + delete) directly in the header.
 */
import React from 'react';
import { FiTrash2, FiFileText } from 'react-icons/fi';
import classNames from 'classnames';
import { t } from '../../utils/translation';
import QuickAddButton from '../QuickAddButton';
import type { BaseNodeData } from '../../types/settings';
import { UI_DIMENSIONS } from '../../constants/dimensions';

interface NodeWrapperProps {
  data: BaseNodeData;
  selected: boolean;
  /** The node-specific CSS class (e.g., 'action-node', 'start-node') */
  nodeClass: string;
  /** When true, the footer is suppressed (compact card layout). */
  compact?: boolean;
  children: React.ReactNode;
}

const NodeWrapper: React.FC<NodeWrapperProps> = ({
  data,
  selected,
  nodeClass,
  compact = false,
  children,
}) => {
  const handleDelete = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (data.onDelete) {
      data.onDelete();
    }
  };

  const isLocked = !!data.isLocked;
  const hasAnnotation = !!data.annotation;
  const showFooter = !compact && (hasAnnotation || !isLocked);

  return (
    <div className={classNames('custom-node', nodeClass, {
      selected: selected,
      'node-has-annotation': data.annotation,
    })}>
      {children}

      {showFooter && (
        <div className="node-footer">
          <div className="node-footer-left">
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
          </div>
          <div className="node-footer-right">
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
      )}

      {data.onQuickAdd && !isLocked && (
        <QuickAddButton onAddNode={data.onQuickAdd} />
      )}
    </div>
  );
};

export default NodeWrapper;
