/**
 * QuickAddEdgeButton - A button that appears on edge hover to quickly add
 * conditions or insert action/gateway nodes between two connected nodes.
 *
 * Conditions are shown first (they are the more common use case for edges),
 * followed by actions and gateways.  Selecting a condition attaches it to the
 * edge; selecting an action/gateway inserts a new node and splits the edge.
 */

import React, { useState, useCallback, useMemo, useRef } from 'react';
import { FiPlus, FiActivity, FiGitBranch, FiHelpCircle } from 'react-icons/fi';
import { useComponentStore } from '../store/useComponentStore';
import type { StoreComponent as Component } from '../types/settings';
import { useContextFilter } from '../hooks/useContextFilter';
import { t } from '../utils/translation';
import { getComponentLabel, getComponentLabelPlural } from '../utils/componentUtils';
import QuickAddPopup from './QuickAddPopup';
import type { QuickAddPopupConfig, SectionConfig, TypeFilterOption } from './QuickAddPopup';

interface QuickAddEdgeButtonProps {
  edgeId: string;
  onAddCondition: (component: Component) => void;
  onAddAction: (component: Component) => void;
  disabled?: boolean;
}

const QuickAddEdgeButton: React.FC<QuickAddEdgeButtonProps> = ({
  edgeId,
  onAddCondition,
  onAddAction,
  disabled = false
}) => {
  const [isPopupOpen, setIsPopupOpen] = useState(false);
  const buttonRef = useRef<HTMLButtonElement>(null);

  // Need to check if there are any components to decide whether to render
  const allComponents = useComponentStore(state => state.components);
  const components = useContextFilter(allComponents);
  const hasComponents = useMemo(() => {
    if (!Array.isArray(components)) return false;
    return components.some(comp => comp.type === 'link' || comp.type === 'element' || comp.type === 'gateway');
  }, [components]);

  // Handle button click
  const handleButtonClick = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    e.preventDefault();
    if (!disabled) {
      setIsPopupOpen(prev => !prev);
    }
  }, [disabled]);

  // Handle popup close
  const handleClosePopup = useCallback(() => {
    setIsPopupOpen(false);
  }, []);

  // Handle component selection - route to the appropriate callback
  const handleSelectComponent = useCallback((component: Component) => {
    setIsPopupOpen(false);
    if (component.type === 'link') {
      onAddCondition(component);
    } else {
      onAddAction(component);
    }
  }, [onAddCondition, onAddAction]);

  // Filter: show conditions, actions, and gateways (exclude start nodes)
  const componentFilter = useCallback((comps: Component[]) => {
    return comps.filter(comp => comp.type === 'link' || comp.type === 'element' || comp.type === 'gateway');
  }, []);

  // Get icon for component type
  const renderComponentIcon = useCallback((component: Component) => {
    switch (component.type) {
      case 'link':
        return <FiHelpCircle size={14} />;
      case 'gateway':
        return <FiGitBranch size={14} />;
      default:
        return <FiActivity size={14} />;
    }
  }, []);

  const getPopupPosition = useCallback((buttonRect: DOMRect) => {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const popupWidth = 300;
    const popupHeight = 400;

    let left = buttonRect.right + 8;
    let top = buttonRect.top - 50;

    if (left + popupWidth > viewportWidth - 20) {
      left = buttonRect.left - popupWidth - 8;
    }
    if (top + popupHeight > viewportHeight - 20) {
      top = viewportHeight - popupHeight - 20;
    }
    if (top < 20) {
      top = 20;
    }

    return { top, left };
  }, []);

  const linkLabel = getComponentLabel('link');
  const linkLabelPlural = getComponentLabelPlural('link');
  const elementLabelPlural = getComponentLabelPlural('element');
  const gatewayLabelPlural = getComponentLabelPlural('gateway');

  // Type filter options for the collapsible filter panel
  const typeFilters: TypeFilterOption[] = useMemo(() => [
    { value: 'all', label: t('All'), types: null },
    { value: 'link', label: linkLabelPlural, types: ['link'] },
    { value: 'element', label: elementLabelPlural, types: ['element'] },
    { value: 'gateway', label: gatewayLabelPlural, types: ['gateway'] },
  ], [linkLabelPlural, elementLabelPlural, gatewayLabelPlural]);

  const popupConfig: QuickAddPopupConfig = useMemo(() => ({
    title: t('Insert on Edge'),
    searchPlaceholder: t('Search components...'),
    searchAriaLabel: t('Search components'),
    emptyMessage: t('No components found'),
    popupClassName: 'quick-add-condition-popup',
    popupWidth: 300,
    popupHeight: 400,
    componentFilter,
    renderComponentIcon,
    getPopupPosition,
    typeFilters,
  }), [componentFilter, renderComponentIcon, getPopupPosition, typeFilters]);

  // Section definitions for grouping the component list.
  // Conditions come first (preferred for edges), then actions/gateways.
  const sections: SectionConfig[] = useMemo(() => [
    {
      id: 'recommended-conditions',
      label: t('Recommended @type', { '@type': linkLabelPlural.toLowerCase() }),
      filter: (comp, isFavorite) => comp.type === 'link' && isFavorite,
    },
    {
      id: 'all-conditions',
      label: linkLabelPlural,
      filter: (comp, isFavorite) => comp.type === 'link' && !isFavorite,
    },
    {
      id: 'recommended-actions',
      label: t('Recommended @type', { '@type': elementLabelPlural.toLowerCase() }),
      filter: (comp, isFavorite) => isFavorite && comp.type !== 'link' && comp.type !== 'gateway',
    },
    {
      id: 'special',
      label: gatewayLabelPlural,
      filter: (comp) => comp.type === 'gateway',
    },
    {
      id: 'all-actions',
      label: elementLabelPlural,
      filter: (comp, isFavorite) => !isFavorite && comp.type !== 'link' && comp.type !== 'gateway',
    },
  ], [linkLabelPlural, elementLabelPlural, gatewayLabelPlural]);

  if (disabled || !hasComponents) {
    return null;
  }

  return (
    <>
      <button
        ref={buttonRef}
        className="quick-add-condition-button nodrag nopan"
        onClick={handleButtonClick}
        title={t('Add @type or insert node', { '@type': linkLabel.toLowerCase() })}
        aria-label={t('Add @type or insert node', { '@type': linkLabel.toLowerCase() })}
        data-edge-id={edgeId}
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

export default QuickAddEdgeButton;
