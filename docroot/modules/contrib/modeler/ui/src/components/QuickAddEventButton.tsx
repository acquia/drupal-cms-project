/**
 * QuickAddEventButton - Toolbar button to quickly add event/start nodes
 *
 * When clicked, shows a popup with available event components that can be added to the canvas.
 * Events are starting points for workflows and don't connect to existing nodes.
 */

import React, { memo, useState, useCallback, useMemo, useRef } from 'react';
import { FiPlus, FiZap } from 'react-icons/fi';
import { useComponentStore } from '../store/useComponentStore';
import type { StoreComponent as Component } from '../types/settings';
import { useContextFilter } from '../hooks/useContextFilter';
import { t } from '../utils/translation';
import { getComponentLabel } from '../utils/componentUtils';
import QuickAddPopup from './QuickAddPopup';
import type { QuickAddPopupConfig, SectionConfig } from './QuickAddPopup';

interface QuickAddEventButtonProps {
  onAddEvent: (component: Component) => void;
  disabled?: boolean;
  isOpen?: boolean;
  onOpenChange?: (isOpen: boolean) => void;
}

const QuickAddEventButton = memo<QuickAddEventButtonProps>(({
  onAddEvent,
  disabled = false,
  isOpen: controlledIsOpen,
  onOpenChange
}) => {
  const [internalIsOpen, setInternalIsOpen] = useState(false);

  // Support both controlled and uncontrolled modes
  const isPopupOpen = controlledIsOpen !== undefined ? controlledIsOpen : internalIsOpen;
  const setIsPopupOpen = useCallback((open: boolean) => {
    setInternalIsOpen(open);
    onOpenChange?.(open);
  }, [onOpenChange]);

  const buttonRef = useRef<HTMLButtonElement>(null);

  // Need to check if there are event components to decide whether to render
  const allComponents = useComponentStore(state => state.components);
  const components = useContextFilter(allComponents);
  const hasEvents = useMemo(() => {
    if (!Array.isArray(components)) return false;
    return components.some(comp => comp.type === 'start');
  }, [components]);

  // Handle button click
  const handleButtonClick = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    if (!disabled) {
      setIsPopupOpen(!isPopupOpen);
    }
  }, [disabled, isPopupOpen, setIsPopupOpen]);

  // Handle popup close
  const handleClosePopup = useCallback(() => {
    setIsPopupOpen(false);
  }, [setIsPopupOpen]);

  // Handle component selection
  const handleSelectComponent = useCallback((component: Component) => {
    setIsPopupOpen(false);
    onAddEvent(component);
  }, [onAddEvent, setIsPopupOpen]);

  // Filter: only start/event components
  const componentFilter = useCallback((comps: Component[]) => {
    return comps.filter(comp => comp.type === 'start');
  }, []);

  const renderComponentIcon = useCallback((_component: Component) => {
    return <FiZap size={14} />;
  }, []);

  const getPopupPosition = useCallback((buttonRect: DOMRect) => {
    const viewportHeight = window.innerHeight;
    const popupHeight = 400;

    const left = buttonRect.left;
    let top = buttonRect.bottom + 8;

    if (top + popupHeight > viewportHeight - 20) {
      top = buttonRect.top - popupHeight - 8;
    }
    if (top < 20) {
      top = 20;
    }

    return { top, left };
  }, []);

  const startLabel = getComponentLabel('start');
  const popupConfig: QuickAddPopupConfig = useMemo(() => ({
    title: t('Add @type', { '@type': startLabel }),
    searchPlaceholder: t('Search @type...', { '@type': startLabel.toLowerCase() }),
    searchAriaLabel: t('Search @type', { '@type': startLabel.toLowerCase() }),
    emptyMessage: t('No @type found', { '@type': startLabel.toLowerCase() }),
    popupClassName: 'quick-add-event-popup',
    popupWidth: 320,
    popupHeight: 400,
    componentFilter,
    renderComponentIcon,
    getPopupPosition,
  }), [startLabel, componentFilter, renderComponentIcon, getPopupPosition]);

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

  if (disabled || !hasEvents) {
    return null;
  }

  return (
    <>
      <button
        ref={buttonRef}
        className="toolbar-btn quick-add-event-button"
        onClick={handleButtonClick}
        title={t('New @type', { '@type': startLabel.toLowerCase() })}
        aria-label={t('New @type', { '@type': startLabel.toLowerCase() })}
      >
        <FiPlus size={14} /> {t('New @type', { '@type': startLabel.toLowerCase() })}
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
});

QuickAddEventButton.displayName = 'QuickAddEventButton';

export default QuickAddEventButton;
