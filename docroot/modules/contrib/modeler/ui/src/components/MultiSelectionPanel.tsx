/**
 * MultiSelectionPanel - Renders the property panel content when multiple
 * nodes and/or edges are selected. Shows a summary list with a bulk delete
 * action.
 */
import React from 'react';
import { FiGitBranch, FiTrash2 } from 'react-icons/fi';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { t } from '../utils/translation';
import { getComponentIcon, getComponentTypeName } from '../utils/componentUtils';

interface MultiSelectionPanelProps {
  selectedNodes: Node[];
  selectedEdges: Edge[];
  onDeleteSelected?: () => void;
  isLocked: boolean;
}

const MultiSelectionPanel: React.FC<MultiSelectionPanelProps> = React.memo(({
  selectedNodes,
  selectedEdges,
  onDeleteSelected,
  isLocked,
}) => {
  return (
    <div className="panel-content multi-selection">
      <div className="selection-summary">
        {selectedNodes.length > 0 && (
          <div className="summary-section">
            <h4>{t('Components (@count)', { '@count': selectedNodes.length })}</h4>
            <ul className="selection-list">
              {selectedNodes.map((selectedNode: Node) => (
                <li key={selectedNode.id} className="selection-item">
                  <div className="selection-item-icon">{getComponentIcon(selectedNode.type || 'element')}</div>
                  <div className="selection-item-info">
                    <div className="selection-item-label">{selectedNode.data?.label || t('Unnamed')}</div>
                    <div className="selection-item-type">{getComponentTypeName(selectedNode.type || 'element')}</div>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}

        {selectedEdges.length > 0 && (
          <div className="summary-section">
            <h4>{t('Connections (@count)', { '@count': selectedEdges.length })}</h4>
            <ul className="selection-list">
              {selectedEdges.map((selectedEdge: Edge) => (
                <li key={selectedEdge.id} className="selection-item">
                  <div className="selection-item-icon"><FiGitBranch /></div>
                  <div className="selection-item-info">
                    <div className="selection-item-label">
                      {selectedEdge.label || selectedEdge.data?.conditionLabel || t('No condition')}
                    </div>
                    <div className="selection-item-type">{t('Connection')}</div>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}

        <div className="summary-actions">
          <button
            className="bulk-action-btn bulk-delete-btn"
            onClick={() => {
              if (!isLocked && onDeleteSelected) {
                onDeleteSelected();
              }
            }}
            disabled={isLocked}
            title={t('Delete all selected items')}
          >
            <FiTrash2 /> {t('Delete All')}
          </button>
          <p className="summary-note">
            {t('Select a single component or connection to view its configuration.')}
          </p>
        </div>
      </div>
    </div>
  );
});

MultiSelectionPanel.displayName = 'MultiSelectionPanel';

export default MultiSelectionPanel;
