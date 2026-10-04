/**
 * EdgePropertiesPanel - Renders the property panel content for a single
 * selected plain connection (edge). Edits a plain connection's annotation.
 *
 * Conditions are no longer edited here: they are authored as condition NODES
 * and edited through the generic node properties panel. The only editable
 * property for an edge is its annotation.
 */
import React from 'react';
import type { StoreEdge as Edge, EdgeData } from '../types/settings';
import { t } from '../utils/translation';
import { useDebouncedField } from '../hooks/useDebouncedField';

interface EdgePropertiesPanelProps {
  edge: Edge;
  onEdgeUpdate?: (edgeId: string, data: Partial<EdgeData>) => void;
  isLocked: boolean;
  edgeAnnotationField: ReturnType<typeof useDebouncedField>;
}

const EdgePropertiesPanel: React.FC<EdgePropertiesPanelProps> = ({
  edge: _edge,
  onEdgeUpdate: _onEdgeUpdate,
  isLocked,
  edgeAnnotationField,
}) => {
  return (
    <div className="panel-content">
      <div className="property-item modeler-native-field">
        <label htmlFor="modeler-edge-annotation">{t('Annotation')}</label>
        <div className="property-value editable">
          <textarea
            id="modeler-edge-annotation"
            name="modeler-edge-annotation"
            value={edgeAnnotationField.value}
            disabled={isLocked}
            placeholder={t('Add a note or annotation for this connection...')}
            rows={3}
            onChange={edgeAnnotationField.onChange}
            onBlur={edgeAnnotationField.onBlur}
          />
        </div>
      </div>
    </div>
  );
};

export default EdgePropertiesPanel;
