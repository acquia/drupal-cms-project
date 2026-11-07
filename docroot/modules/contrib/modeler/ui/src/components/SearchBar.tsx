import React, { Profiler, useState, useCallback, useRef, useEffect, forwardRef, useImperativeHandle, memo } from 'react';
import { FiSearch, FiX, FiChevronDown } from 'react-icons/fi';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { TIMING } from '../constants/dimensions';
import { t } from '../utils/translation';
import { getComponentLabel } from '../utils/componentUtils';
import { onRenderCallback } from '../utils/profiling';

interface SearchResult {
  id: string;
  type: 'node' | 'edge';
  label: string;
  subtitle: string;
  data: Node | Edge;
}

interface SearchBarProps {
  onHighlight?: (result: SearchResult | null) => void;
  onFocus?: (data: Node | Edge) => void;
}

export interface SearchBarRef {
  focus: () => void;
}

const SearchBar = memo(forwardRef<SearchBarRef, SearchBarProps>(({ onHighlight, onFocus }, ref) => {
  // Read nodes/edges directly from the store so the parent (Toolbar)
  // doesn't need to pass them as props and re-render when they change.
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);

  const [searchTerm, setSearchTerm] = useState('');
  const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);
  const [highlightedItem, setHighlightedItem] = useState<SearchResult | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Expose focus method to parent component
  useImperativeHandle(ref, () => ({
    focus: () => {
      inputRef.current?.focus();
    }
  }), []);

  // Handle result selection
  const handleResultSelect = useCallback((result: SearchResult) => {
    setHighlightedItem(result);
    setIsDropdownOpen(false);
    setSelectedIndex(-1);

    if (onHighlight) {
      onHighlight(result);
    }

    if (onFocus) {
      onFocus(result.data);
    }
  }, [onHighlight, onFocus]);

  // Search through nodes and edges
  const performSearch = useCallback((term: string) => {
    if (!term.trim()) {
      setSearchResults([]);
      setIsDropdownOpen(false);
      setHighlightedItem(null);
      if (onHighlight) {
        onHighlight(null);
      }
      return;
    }

    const results: SearchResult[] = [];
    const searchLower = term.toLowerCase();

    // Search nodes
    nodes.forEach(node => {
      const label = node.data?.label || '';
      const plugin = node.data?.plugin || '';
      const type = node.type || '';
      
      if (
        label.toLowerCase().includes(searchLower) ||
        plugin.toLowerCase().includes(searchLower) ||
        type.toLowerCase().includes(searchLower) ||
        node.id.toLowerCase().includes(searchLower)
      ) {
        results.push({
          id: node.id,
          type: 'node',
          label: label || plugin || type || node.id,
          subtitle: `${type} • ${plugin || t('No plugin')}`,
          data: node
        });
      }
    });

    // Search edges — only include edges that have a condition attached
    edges.forEach(edge => {
      const conditionLabel = edge.data?.conditionLabel || '';
      const condition = edge.data?.condition || '';

      // Skip edges without a condition
      if (!condition && !conditionLabel) return;

      if (
        conditionLabel.toLowerCase().includes(searchLower) ||
        condition.toLowerCase().includes(searchLower)
      ) {
        results.push({
          id: edge.id,
          type: 'edge',
          label: conditionLabel || condition,
          subtitle: `${getComponentLabel('link')} • ${condition}`,
          data: edge
        });
      }
    });

    setSearchResults(results);
    setIsDropdownOpen(results.length > 0);
    setSelectedIndex(results.length > 0 ? 0 : -1);

    // If only one result, auto-highlight it
    if (results.length === 1) {
      handleResultSelect(results[0]);
    } else if (results.length === 0) {
      setHighlightedItem(null);
      if (onHighlight) {
        onHighlight(null);
      }
    }
  }, [nodes, edges, onHighlight, handleResultSelect]);

  // Handle search input change with debouncing
  const handleSearchChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearchTerm(value);

    // Clear any pending debounced search
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    // Debounce the search operation
    debounceRef.current = setTimeout(() => {
      performSearch(value);
    }, TIMING.SEARCH_DEBOUNCE);
  }, [performSearch]);

  // Handle keyboard navigation
  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (!isDropdownOpen || searchResults.length === 0) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedIndex(prev => 
          prev < searchResults.length - 1 ? prev + 1 : 0
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedIndex(prev => 
          prev > 0 ? prev - 1 : searchResults.length - 1
        );
        break;
      case 'Enter':
        e.preventDefault();
        if (selectedIndex >= 0 && selectedIndex < searchResults.length) {
          handleResultSelect(searchResults[selectedIndex]);
        }
        break;
      case 'Escape':
        e.preventDefault();
        setIsDropdownOpen(false);
        setSelectedIndex(-1);
        inputRef.current?.blur();
        break;
    }
  }, [isDropdownOpen, searchResults, selectedIndex, handleResultSelect]);

  // Clear search
  const handleClear = useCallback(() => {
    // Clear any pending debounced search
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
      debounceRef.current = null;
    }
    setSearchTerm('');
    setSearchResults([]);
    setIsDropdownOpen(false);
    setSelectedIndex(-1);
    setHighlightedItem(null);
    if (onHighlight) {
      onHighlight(null);
    }
    inputRef.current?.focus();
  }, [onHighlight]);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && event.target instanceof Element && !dropdownRef.current.contains(event.target)) {
        setIsDropdownOpen(false);
        setSelectedIndex(-1);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Cleanup debounce timeout on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, []);

  // Generate stable IDs for aria-activedescendant
  const listboxId = 'search-results-listbox';
  const getOptionId = (index: number) => `search-result-${index}`;

  return (
    <Profiler id="SearchBar" onRender={onRenderCallback}>
    <div className="search-bar" ref={dropdownRef}>
      <div className="search-input-container" role="combobox" aria-expanded={isDropdownOpen} aria-haspopup="listbox" aria-owns={listboxId}>
        <FiSearch className="search-icon" aria-hidden="true" />
        <input
          ref={inputRef}
          type="text"
          placeholder={t('Search components and conditions...')}
          aria-label={t('Search components and conditions')}
          aria-autocomplete="list"
          aria-controls={isDropdownOpen ? listboxId : undefined}
          aria-activedescendant={isDropdownOpen && selectedIndex >= 0 ? getOptionId(selectedIndex) : undefined}
          value={searchTerm}
          onChange={handleSearchChange}
          onKeyDown={handleKeyDown}
          className="search-input"
        />
        {searchTerm && (
          <button
            type="button"
            onClick={handleClear}
            className="search-clear-btn"
            title={t('Clear search')}
            aria-label={t('Clear search')}
          >
            <FiX />
          </button>
        )}
        {searchResults.length > 1 && (
          <button
            type="button"
            onClick={() => setIsDropdownOpen(!isDropdownOpen)}
            className="search-dropdown-toggle"
            title={t('@count results', { '@count': searchResults.length })}
            aria-label={t('@count results', { '@count': searchResults.length })}
          >
            <span className="result-count">{searchResults.length}</span>
            <FiChevronDown className={`dropdown-icon ${isDropdownOpen ? 'open' : ''}`} />
          </button>
        )}
      </div>

      {/* Screen reader announcement for search result count */}
      <div role="status" aria-live="polite" aria-atomic="true" className="sr-only">
        {searchTerm && searchResults.length > 0
          ? t('@count results found', { '@count': searchResults.length })
          : searchTerm ? t('No results found') : ''}
      </div>

      {isDropdownOpen && searchResults.length > 0 && (
        <div className="search-dropdown">
          <div className="search-results" id={listboxId} role="listbox" aria-label={t('Search results')}>
            {searchResults.map((result, index) => (
              <div
                key={result.id}
                id={getOptionId(index)}
                className={`search-result-item ${index === selectedIndex ? 'highlighted' : ''} ${highlightedItem?.id === result.id ? 'selected' : ''}`}
                onClick={() => handleResultSelect(result)}
                onMouseEnter={() => setSelectedIndex(index)}
                role="option"
                aria-selected={index === selectedIndex}
              >
                <div className="result-main">
                  <span className="result-label">{result.label}</span>
                  <span className={`result-type ${result.type}`}>
                    {result.type === 'node' ? t('Component') : getComponentLabel('link')}
                  </span>
                </div>
                <div className="result-subtitle">{result.subtitle}</div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
    </Profiler>
  );
}));

SearchBar.displayName = 'SearchBar';

export default SearchBar;
