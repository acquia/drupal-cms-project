/**
 * QuickAddConditionButton - A button that appears on edge hover to quickly add conditions
 *
 * When clicked, shows a popup with available condition components that can be attached to the edge.
 */

import React, { useState, useCallback, useMemo, useRef } from 'react';
import { FiPlus, FiHelpCircle } from 'react-icons/fi';
import { useComponentStore } from '../store/useComponentStore';
import type { StoreComponent as Component } from '../types/settings';
import { useContextFilter } from '../hooks/useContextFilter';
import { t } from '../utils/translation';
import { getComponentLabel } from '../utils/componentUtils';
import QuickAddPopup from './QuickAddPopup';
import type { QuickAddPopupConfig, SectionConfig } from './QuickAddPopup';

interface QuickAddConditionButtonProps {
  edgeId: string;
  onAddCondition: (component: Component) => void;
  disabled?: boolean;
}

const QuickAddConditionButton: React.FC<QuickAddConditionButtonProps> = ({
  edgeId,
  onAddCondition,
  disabled = false
}) => {
  const [isPopupOpen, setIsPopupOpen] = useState(false);
  const buttonRef = useRef<HTMLButtonElement>(null);

  // Need to check if there are condition components to decide whether to render
  const allComponents = useComponentStore(state => state.components);
  const components = useContextFilter(allComponents);
  const hasConditions = useMemo(() => {
    if (!Array.isArray(components)) return false;
    return components.some(comp => comp.type === 'link');
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

  // Handle component selection
  const handleSelectComponent = useCallback((component: Component) => {
    setIsPopupOpen(false);
    onAddCondition(component);
  }, [onAddCondition]);

  // Filter: only link/condition components
  const componentFilter = useCallback((comps: Component[]) => {
    return comps.filter(comp => comp.type === 'link');
  }, []);

  const renderComponentIcon = useCallback((_component: Component) => {
    return <FiHelpCircle size={14} />;
  }, []);

  const getPopupPosition = useCallback((buttonRect: DOMRect) => {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const popupWidth = 280;
    const popupHeight = 350;

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
  const popupConfig: QuickAddPopupConfig = useMemo(() => ({
    title: t('Add @type', { '@type': linkLabel }),
    searchPlaceholder: t('Search @type...', { '@type': linkLabel.toLowerCase() }),
    searchAriaLabel: t('Search @type', { '@type': linkLabel.toLowerCase() }),
    emptyMessage: t('No @type found', { '@type': linkLabel.toLowerCase() }),
    popupClassName: 'quick-add-condition-popup',
    popupWidth: 280,
    popupHeight: 350,
    componentFilter,
    renderComponentIcon,
    getPopupPosition,
  }), [linkLabel, componentFilter, renderComponentIcon, getPopupPosition]);

  // Section definitions for grouping the component list
  const sections: SectionConfig[] = useMemo(() => [
    {
      id: 'recommended',
      label: t('Recommended'),
      filter: (_comp, isFavorite) => isFavorite,
    },
    {
      id: 'all-others',
      label: t('All others'),
      filter: (_comp, isFavorite) => !isFavorite,
    },
  ], []);

  if (disabled || !hasConditions) {
    return null;
  }

  return (
    <>
      <button
        ref={buttonRef}
        className="quick-add-condition-button nodrag nopan"
        onClick={handleButtonClick}
        title={t('Add @type', { '@type': linkLabel.toLowerCase() })}
        aria-label={t('Add @type', { '@type': linkLabel.toLowerCase() })}
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

export default QuickAddConditionButton;
