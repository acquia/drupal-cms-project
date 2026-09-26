/**
 * PlaceholderNode - A temporary node that stands in for an action/gateway
 * that has not yet been chosen.
 *
 * Created when a user adds a condition via the quick-add button on a node
 * before defining a successor action.  The placeholder is visually distinct
 * (dashed border, muted colors, pulsing prompt) and includes a button to
 * select the real action or gateway component that should replace it.
 */

import React, { Profiler, memo, useState, useCallback, useMemo, useRef } from 'react';
import { Handle, Position, NodeProps } from 'reactflow';
import { FiActivity, FiGitBranch, FiZap } from 'react-icons/fi';
import { getComponentLabel } from '../../utils/componentUtils';
import NodeWrapper from './NodeWrapper';
import QuickAddPopup from '../QuickAddPopup';
import type { QuickAddPopupConfig, SectionConfig } from '../QuickAddPopup';
import { onRenderCallback } from '../../utils/profiling';
import { t } from '../../utils/translation';
import type { NodeData, StoreComponent as Component } from '../../types/settings';

const PlaceholderNode = memo<NodeProps<NodeData>>(({ data, selected }) => {
  const [isPopupOpen, setIsPopupOpen] = useState(false);
  const buttonRef = useRef<HTMLButtonElement>(null);

  const handleButtonClick = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    setIsPopupOpen(prev => !prev);
  }, []);

  const handleClosePopup = useCallback(() => {
    setIsPopupOpen(false);
  }, []);

  const handleSelectComponent = useCallback((component: Component) => {
    setIsPopupOpen(false);
    if (data.onReplacePlaceholder) {
      data.onReplacePlaceholder(component);
    }
  }, [data]);

  // Filter: only actions and gateways (the types that can replace a placeholder)
  const componentFilter = useCallback((components: Component[]) => {
    return components.filter(comp =>
      comp.type !== 'start' && comp.type !== 'link'
    );
  }, []);

  // Get icon for component type
  const getTypeIcon = useCallback((typeId: string) => {
    switch (typeId) {
      case 'element':
        return <FiActivity size={14} />;
      case 'gateway':
        return <FiGitBranch size={14} />;
      default:
        return <FiZap size={14} />;
    }
  }, []);

  const renderComponentIcon = useCallback((component: Component) => {
    return getTypeIcon(component.type || 'element');
  }, [getTypeIcon]);

  const popupConfig: QuickAddPopupConfig = useMemo(() => ({
    title: t('Select Action'),
    searchPlaceholder: t('Search components...'),
    searchAriaLabel: t('Search components'),
    emptyMessage: t('No components found'),
    popupWidth: 320,
    popupHeight: 400,
    componentFilter,
    renderComponentIcon,
    mergeOthersCategory: true,
  }), [componentFilter, renderComponentIcon]);

  const sections: SectionConfig[] = useMemo(() => [
    {
      id: 'recommended',
      label: t('Recommended'),
      filter: (comp, isFavorite) => isFavorite && comp.type !== 'gateway',
    },
    {
      id: 'special',
      label: t('Special'),
      filter: (comp) => comp.type === 'gateway',
    },
    {
      id: 'all-others',
      label: t('All others'),
      filter: (comp, isFavorite) => !isFavorite && comp.type !== 'gateway',
    },
  ], []);

  const isLocked = !!data.isLocked;

  return (
    <Profiler id="PlaceholderNode" onRender={onRenderCallback}>
    <NodeWrapper data={data} selected={selected} nodeClass="placeholder-node">
      <Handle
        type="target"
        position={Position.Top}
        className="node-handle"
        id="input"
      />

      <div className="node-header">
        <FiActivity className="node-icon" />
        <span className="node-type">{getComponentLabel('element')}</span>
      </div>

      <div className="node-body">
        {!isLocked && data.onReplacePlaceholder ? (
          <>
            <button
              ref={buttonRef}
              className="placeholder-select-button"
              onClick={handleButtonClick}
              title={t('Select an action or gateway')}
              aria-label={t('Select an action or gateway')}
            >
              {t('Select action...')}
            </button>
            <QuickAddPopup
              isOpen={isPopupOpen}
              onClose={handleClosePopup}
              onSelect={handleSelectComponent}
              config={popupConfig}
              buttonRef={buttonRef}
              sections={sections}
            />
          </>
        ) : (
          <div className="node-label placeholder-label">{data.label}</div>
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

PlaceholderNode.displayName = 'PlaceholderNode';

export default PlaceholderNode;
