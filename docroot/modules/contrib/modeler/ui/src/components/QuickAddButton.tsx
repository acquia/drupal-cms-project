/**
 * QuickAddButton - A button that appears on node hover to quickly add successor nodes
 *
 * When clicked, shows a popup with available components that can be added as successors.
 * Selecting an action/gateway component creates a new node and connects it to the source node.
 * Selecting a condition component creates a placeholder action node with the condition
 * pre-attached to the connecting edge, allowing condition-first authoring.
 */

import React, { useState, useCallback, useMemo, useRef } from 'react';
import { FiPlus, FiZap, FiActivity, FiGitBranch, FiHelpCircle } from 'react-icons/fi';
import type { StoreComponent as Component } from '../types/settings';
import { t } from '../utils/translation';
import { getComponentLabelPlural } from '../utils/componentUtils';
import QuickAddPopup from './QuickAddPopup';
import type { QuickAddPopupConfig, TypeFilterOption } from './QuickAddPopup';
import type { SectionConfig } from './QuickAddPopup';

interface QuickAddButtonProps {
  onAddNode: (component: Component) => void;
  disabled?: boolean;
}

const QuickAddButton: React.FC<QuickAddButtonProps> = ({
  onAddNode,
  disabled = false
}) => {
  const [isPopupOpen, setIsPopupOpen] = useState(false);
  const buttonRef = useRef<HTMLButtonElement>(null);

  // Handle button click - toggles popup open/closed
  const handleButtonClick = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    if (!disabled) {
      setIsPopupOpen(prev => !prev);
    }
  }, [disabled]);

  // Handle popup close
  const handleClosePopup = useCallback(() => {
    setIsPopupOpen(false);
  }, []);

  // Handle component selection
  const handleSelectComponent = useCallback((component: Component) => {
    setIsPopupOpen(false);
    onAddNode(component);
  }, [onAddNode]);

  // Filter: exclude start nodes (those are not successor nodes).
  // Conditions (type 'link') are now included so users can author condition-first.
  const componentFilter = useCallback((components: Component[]) => {
    return components.filter(comp =>
      comp.type !== 'start'
    );
  }, []);

  // Get icon for component type
  const getTypeIcon = useCallback((typeId: string) => {
    switch (typeId) {
      case 'element':
        return <FiActivity size={14} />;
      case 'gateway':
        return <FiGitBranch size={14} />;
      case 'link':
        return <FiHelpCircle size={14} />;
      default:
        return <FiZap size={14} />;
    }
  }, []);

  // Icon renderer for component items
  const renderComponentIcon = useCallback((component: Component) => {
    return getTypeIcon(component.type || 'element');
  }, [getTypeIcon]);

  const conditionLabelPlural = getComponentLabelPlural('link');

  // Type filter options for the collapsible filter panel
  const actionLabel = getComponentLabelPlural('element');
  const gatewayLabel = getComponentLabelPlural('gateway');
  const conditionLabel = conditionLabelPlural;

  const typeFilters: TypeFilterOption[] = useMemo(() => [
    { value: 'all', label: t('All'), types: null },
    { value: 'element', label: actionLabel, types: ['element'] },
    { value: 'link', label: conditionLabel, types: ['link'] },
    { value: 'gateway', label: gatewayLabel, types: ['gateway'] },
  ], [actionLabel, conditionLabel, gatewayLabel]);

  const popupConfig: QuickAddPopupConfig = useMemo(() => ({
    title: t('Add Successor'),
    searchPlaceholder: t('Search components...'),
    searchAriaLabel: t('Search components'),
    emptyMessage: t('No components found'),
    popupWidth: 320,
    popupHeight: 400,
    componentFilter,
    renderComponentIcon,
    mergeOthersCategory: true,
    typeFilters,
  }), [componentFilter, renderComponentIcon, typeFilters]);

  // Section definitions for grouping the component list.
  // Actions/gateways come first (recommended favorites, then special gateways,
  // then all others), followed by conditions in a clearly separated section.
  const sections: SectionConfig[] = useMemo(() => [
    {
      id: 'recommended',
      label: t('Recommended'),
      filter: (comp, isFavorite) => isFavorite && comp.type !== 'gateway' && comp.type !== 'link',
    },
    {
      id: 'special',
      label: t('Special'),
      filter: (comp) => comp.type === 'gateway',
    },
    {
      id: 'all-others',
      label: t('All others'),
      filter: (comp, isFavorite) => !isFavorite && comp.type !== 'gateway' && comp.type !== 'link',
    },
    {
      id: 'conditions',
      label: conditionLabelPlural,
      filter: (comp) => comp.type === 'link',
    },
  ], [conditionLabelPlural]);

  if (disabled) {
    return null;
  }

  return (
    <>
      <button
        ref={buttonRef}
        className="quick-add-button"
        onClick={handleButtonClick}
        title={t('Add successor node')}
        aria-label={t('Add successor node')}
      >
        <FiPlus size={14} />
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
  );
};

export default QuickAddButton;
