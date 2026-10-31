/**
 * QuickAddPopup - Shared popup component used by QuickAddButton,
 * QuickAddConditionButton, and QuickAddEventButton.
 *
 * Provides the common popup shell: search bar, favorite sorting,
 * click-outside detection, focus trapping, and component list rendering.
 * Optionally includes a collapsible type-filter panel for narrowing the
 * component list by type (e.g. action, condition, gateway).
 */

import React, { Profiler, useState, useCallback, useMemo, useRef, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { FiSearch, FiX, FiFilter } from 'react-icons/fi';
import { useComponentStore } from '../store/useComponentStore';
import { useContextStore } from '../store/useContextStore';
import type { StoreComponent as Component } from '../types/settings';
import { useContextFilter } from '../hooks/useContextFilter';
import { t } from '../utils/translation';
import { useFocusTrap } from '../hooks/useFocusTrap';
import { useClickOutside } from '../hooks/useClickOutside';
import { THRESHOLDS } from '../constants/dimensions';
import { isComponentFavorite, sortWithFavorites, filterComponentsBySearch } from '../utils/componentUtils';
import DocumentationButton from './DocumentationButton';
import { onRenderCallback } from '../utils/profiling';

/** Configuration for a section within the popup component list. */
export interface SectionConfig {
  /** Unique identifier for this section */
  id: string;
  /** Display label used as section header */
  label: string;
  /** Filter predicate: return true to include a component in this section.
   *  The second argument indicates whether the component is a favorite. */
  filter: (component: Component, isFavorite: boolean) => boolean;
}

/** A single option in the collapsible type-filter panel. */
export interface TypeFilterOption {
  /** Internal value used for matching (e.g. 'element', 'link', 'gateway', 'all') */
  value: string;
  /** Human-readable label shown in the UI */
  label: string;
  /** Component types to include when this filter is active.
   *  `null` means show all types (the "All" option). */
  types: string[] | null;
}

export interface QuickAddPopupConfig {
  /** Title shown in the popup header */
  title: string;
  /** Placeholder for the search input */
  searchPlaceholder: string;
  /** aria-label for the search input */
  searchAriaLabel: string;
  /** Text shown when the filtered list is empty */
  emptyMessage: string;
  /** CSS class applied to the popup container (in addition to 'quick-add-popup') */
  popupClassName?: string;
  /** Popup width for position calculations */
  popupWidth?: number;
  /** Popup height for position calculations */
  popupHeight?: number;
  /** Filter function: which components from the store to show */
  componentFilter: (components: Component[]) => Component[];
  /** Render a category icon for a given component */
  renderComponentIcon: (component: Component) => React.ReactNode;
  /** Calculate popup position from button rect */
  getPopupPosition?: (buttonRect: DOMRect) => { top: number; left: number };
  /** Whether to merge unfiltered gateway components when context is active (only for QuickAddButton) */
  mergeOthersCategory?: boolean;
  /** Optional type-filter options shown in a collapsible panel.
   *  When provided, a "Filter" toggle appears between the search bar
   *  and the component list, allowing users to narrow by component type. */
  typeFilters?: TypeFilterOption[];
}

interface QuickAddPopupProps {
  isOpen: boolean;
  onClose: () => void;
  onSelect: (component: Component) => void;
  config: QuickAddPopupConfig;
  buttonRef: React.RefObject<HTMLButtonElement | null>;
  /** Optional: section definitions for grouping the component list with headers */
  sections?: SectionConfig[];
}

const QuickAddPopup: React.FC<QuickAddPopupProps> = ({
  isOpen,
  onClose,
  onSelect,
  config,
  buttonRef,
  sections,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [activeTypeFilter, setActiveTypeFilter] = useState<string>('all');
  const [isFilterExpanded, setIsFilterExpanded] = useState(false);
  const popupRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Get components and favorites from store
  const allComponents = useComponentStore(state => state.components);
  const favoriteComponents = useComponentStore(state => state.favoriteComponents);
  const selectedContextId = useContextStore(state => state.selectedContextId);

  // Filter by active context
  const contextFilteredComponents = useContextFilter(allComponents);

  // Merge context-filtered components with unfiltered Others (only when configured)
  const components = useMemo(() => {
    if (!config.mergeOthersCategory || !selectedContextId) return contextFilteredComponents;
    if (!Array.isArray(contextFilteredComponents) || !Array.isArray(allComponents)) return contextFilteredComponents;

    const filteredPlugins = new Set(contextFilteredComponents.map(c => c.plugin));
    const othersComponents = allComponents.filter(comp => comp.type === 'gateway');
    const merged = [...contextFilteredComponents];
    for (const otherComp of othersComponents) {
      if (!filteredPlugins.has(otherComp.plugin)) {
        merged.push(otherComp);
      }
    }
    return merged;
  }, [allComponents, contextFilteredComponents, selectedContextId, config.mergeOthersCategory]);

  // Apply component filter (e.g., only conditions, only events, etc.)
  const baseComponents = useMemo(() => {
    if (!Array.isArray(components)) return [];
    return config.componentFilter(components);
  }, [components, config]);

  // Apply type filter (e.g. show only actions, only conditions, etc.)
  const typeFilteredComponents = useMemo(() => {
    if (!config.typeFilters || activeTypeFilter === 'all') return baseComponents;
    const activeOption = config.typeFilters.find(f => f.value === activeTypeFilter);
    if (!activeOption || !activeOption.types) return baseComponents;
    const allowedTypes = new Set(activeOption.types);
    return baseComponents.filter(comp => allowedTypes.has(comp.type || 'element'));
  }, [baseComponents, activeTypeFilter, config.typeFilters]);

  // Filter by search term and sort with favorites
  const filteredComponents = useMemo(() => {
    const searched = filterComponentsBySearch(typeFilteredComponents, searchTerm);
    return sortWithFavorites(searched, favoriteComponents, selectedContextId);
  }, [typeFilteredComponents, searchTerm, favoriteComponents, selectedContextId]);

  // Build sectioned groups when sections are configured
  const sectionedComponents = useMemo(() => {
    if (!sections || sections.length === 0) return null;

    return sections.map(section => {
      const items = filteredComponents.filter(comp => {
        const isFav = isComponentFavorite(comp, favoriteComponents, selectedContextId);
        return section.filter(comp, isFav);
      });
      return { ...section, items };
    }).filter(section => section.items.length > 0);
  }, [sections, filteredComponents, favoriteComponents, selectedContextId]);

  // Handle component selection
  const handleSelectComponent = useCallback((e: React.MouseEvent, component: Component) => {
    e.stopPropagation();
    e.preventDefault();
    onSelect(component);
  }, [onSelect]);

  // Toggle the filter panel
  const handleToggleFilter = useCallback((e: React.MouseEvent) => {
    e.stopPropagation();
    setIsFilterExpanded(prev => !prev);
  }, []);

  // Handle type filter change
  const handleTypeFilterChange = useCallback((value: string) => {
    setActiveTypeFilter(value);
  }, []);

  // Focus trap
  useFocusTrap({
    isActive: isOpen,
    onClose,
    containerRef: popupRef,
    autoFocus: false,
  });

  // Whether to show the search field (hide when few components)
  const showSearch = baseComponents.length >= THRESHOLDS.SEARCH_VISIBILITY_MIN_COMPONENTS;

  // Focus search input when popup opens
  useEffect(() => {
    if (isOpen && showSearch && searchInputRef.current) {
      const timer = setTimeout(() => searchInputRef.current?.focus(), 50);
      return () => clearTimeout(timer);
    }
  }, [isOpen, showSearch]);

  // Reset search and filter when popup opens/closes
  useEffect(() => {
    if (!isOpen) {
      setSearchTerm('');
      setActiveTypeFilter('all');
      setIsFilterExpanded(false);
    }
  }, [isOpen]);

  // Click outside detection
  const refs = useMemo(() => [popupRef, buttonRef], [buttonRef]);
  useClickOutside(isOpen, refs, onClose);

  // Calculate popup position
  const popupPosition = useMemo(() => {
    if (!buttonRef.current) return { top: 0, left: 0 };

    const buttonRect = buttonRef.current.getBoundingClientRect();

    if (config.getPopupPosition) {
      return config.getPopupPosition(buttonRect);
    }

    // Default positioning: to the right of the button
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const popupWidth = config.popupWidth || 320;
    const popupHeight = config.popupHeight || 400;

    let left = buttonRect.right + 8;
    let top = buttonRect.top;

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
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, config]);

  if (!isOpen) return null;

  return createPortal(
    <Profiler id="QuickAddPopup" onRender={onRenderCallback}>
    <div
      ref={popupRef}
      className={`quick-add-popup ${config.popupClassName || ''}`}
      style={popupPosition}
      role="dialog"
      aria-modal="true"
      aria-label={config.title}
    >
      <div className="quick-add-popup-header">
        <h2>{config.title}</h2>
        <button
          className="quick-add-popup-close"
          onClick={onClose}
          title={t('Close')}
          aria-label={t('Close')}
        >
          <FiX size={16} />
        </button>
      </div>

      {showSearch && (
        <div className="quick-add-popup-search">
          <FiSearch className="search-icon" />
          <input
            ref={searchInputRef}
            type="text"
            placeholder={config.searchPlaceholder}
            aria-label={config.searchAriaLabel}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>
      )}

      {config.typeFilters && config.typeFilters.length > 0 && (
        <div className="quick-add-filter-panel">
          <button
            className={`quick-add-filter-toggle ${isFilterExpanded ? 'expanded' : ''} ${activeTypeFilter !== 'all' ? 'has-active-filter' : ''}`}
            onClick={handleToggleFilter}
            aria-expanded={isFilterExpanded}
            aria-controls="quick-add-type-filters"
            title={t('Filter by type')}
          >
            <FiFilter size={12} />
            <span>{t('Filter')}</span>
            {activeTypeFilter !== 'all' && (
              <span className="quick-add-filter-badge" aria-label={t('Filter active')}>1</span>
            )}
          </button>
          {isFilterExpanded && (
            <div
              id="quick-add-type-filters"
              className="quick-add-filter-options"
              role="radiogroup"
              aria-label={t('Component type filter')}
            >
              {config.typeFilters.map(option => (
                <button
                  key={option.value}
                  className={`quick-add-filter-option ${activeTypeFilter === option.value ? 'active' : ''}`}
                  onClick={() => handleTypeFilterChange(option.value)}
                  role="radio"
                  aria-checked={activeTypeFilter === option.value}
                >
                  {option.label}
                </button>
              ))}
            </div>
          )}
        </div>
      )}

      <div className="quick-add-popup-components">
        {filteredComponents.length === 0 ? (
          <div className="quick-add-popup-empty">
            {config.emptyMessage}
          </div>
        ) : sectionedComponents ? (
          sectionedComponents.map(section => (
            <div key={section.id} className="quick-add-section">
              <div className="quick-add-section-header">{section.label}</div>
              {section.items.map((component, index) => (
                <button
                  key={`${component.plugin}-${index}`}
                  className="quick-add-component-item"
                  onClick={(e) => handleSelectComponent(e, component)}
                  title={component.description || component.label}
                >
                  <span className="component-category-indicator" data-type={component.type || 'element'}>
                    {config.renderComponentIcon(component)}
                  </span>
                  <span className="component-label">{component.label}</span>
                  {component.documentationUrl && (
                    <DocumentationButton
                      url={component.documentationUrl}
                      title={component.label || ''}
                      className="quick-add-documentation-btn"
                      size={12}
                    />
                  )}
                </button>
              ))}
            </div>
          ))
        ) : (
          filteredComponents.map((component, index) => (
            <button
              key={`${component.plugin}-${index}`}
              className="quick-add-component-item"
              onClick={(e) => handleSelectComponent(e, component)}
              title={component.description || component.label}
            >
              <span className="component-category-indicator" data-type={component.type || 'element'}>
                {config.renderComponentIcon(component)}
              </span>
              <span className="component-label">{component.label}</span>
              {component.documentationUrl && (
                <DocumentationButton
                  url={component.documentationUrl}
                  title={component.label || ''}
                  className="quick-add-documentation-btn"
                  size={12}
                />
              )}
            </button>
          ))
        )}
      </div>
    </div>
    </Profiler>,
    document.querySelector('.modeler') || document.body
  );
};

export default QuickAddPopup;
