/**
 * StartFlowFilter - Dropdown multi-select for filtering visible flows by start node.
 *
 * Renders a button that opens a dropdown listing all current start (event)
 * nodes on the canvas.  The user can select "All" or pick one-or-more
 * individual start nodes.  Unselected flows are hidden from the canvas.
 *
 * The list updates dynamically as start nodes are added or removed.
 *
 * Reads nodes directly from the Zustand store so the parent component
 * (Toolbar) does not need to re-render when nodes change.
 */

import React, { memo, useCallback, useMemo, useRef, useState } from 'react';
import { FiChevronDown, FiCheck, FiFilter } from 'react-icons/fi';
import { useGraphStore } from '../store/useGraphStore';
import { useFilterStore } from '../store/useFilterStore';
import type { StoreNode as Node } from '../types/settings';
import { useClickOutside } from '../hooks/useClickOutside';
import { t } from '../utils/translation';

/** Returns a display label for a start node. */
const getNodeLabel = (node: Node): string =>
  node.data?.label || node.data?.plugin || node.id;

const StartFlowFilter: React.FC = memo(() => {
  const [isOpen, setIsOpen] = useState(false);

  // Read nodes directly from the store — avoids prop-drilling through Toolbar
  const nodes = useGraphStore(state => state.nodes);
  const visibleStartNodeIds = useFilterStore(state => state.visibleStartNodeIds);
  const setVisibleStartNodeIds = useFilterStore(state => state.setVisibleStartNodeIds);

  const containerRef = useRef<HTMLDivElement>(null);

  // Close dropdown on outside click
  useClickOutside(isOpen, [containerRef], useCallback(() => setIsOpen(false), []));

  // Derive the list of start nodes currently on the canvas
  const startNodes = useMemo(
    () => nodes.filter((n) => n.type === 'start'),
    [nodes],
  );

  const isAllSelected = visibleStartNodeIds === null;

  /** Compose a summary label for the button. */
  const buttonLabel = useMemo(() => {
    if (isAllSelected) return t('All Flows');
    const count = visibleStartNodeIds!.length;
    if (count === 1) {
      const node = startNodes.find((n) => n.id === visibleStartNodeIds![0]);
      return node ? getNodeLabel(node) : t('1 Flow');
    }
    return t('@count Flows', { '@count': String(count) });
  }, [isAllSelected, visibleStartNodeIds, startNodes]);

  // Don't render the filter when there are fewer than 2 start nodes
  if (startNodes.length < 2) {
    return null;
  }

  /** Toggle the dropdown open/closed. */
  const handleToggle = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    e.stopPropagation();
    setIsOpen((prev) => !prev);
  };

  /** Select "All" — clears per-node filtering. */
  const handleSelectAll = () => {
    setVisibleStartNodeIds(null);
  };

  /** Toggle a single start node's visibility. */
  const handleToggleNode = (nodeId: string) => {
    if (isAllSelected) {
      // Switching from "All" → select only this one node
      setVisibleStartNodeIds([nodeId]);
      return;
    }

    const current = visibleStartNodeIds!;
    const isCurrentlySelected = current.includes(nodeId);

    if (isCurrentlySelected) {
      const next = current.filter((id) => id !== nodeId);
      // If nothing would remain, revert to "All"
      if (next.length === 0) {
        setVisibleStartNodeIds(null);
      } else {
        setVisibleStartNodeIds(next);
      }
    } else {
      const next = [...current, nodeId];
      // If every start node is now selected, revert to "All"
      if (next.length === startNodes.length) {
        setVisibleStartNodeIds(null);
      } else {
        setVisibleStartNodeIds(next);
      }
    }
  };

  return (
    <div className="start-flow-filter" ref={containerRef}>
      <button
        type="button"
        className={`start-flow-filter-toggle ${!isAllSelected ? 'active' : ''}`}
        onClick={handleToggle}
        aria-haspopup="listbox"
        aria-expanded={isOpen}
        aria-label={t('Filter visible flows')}
        title={t('Filter visible flows')}
      >
        <FiFilter className="start-flow-filter-icon" />
        <span className="start-flow-filter-label">{buttonLabel}</span>
        <FiChevronDown className={`start-flow-filter-chevron ${isOpen ? 'open' : ''}`} />
      </button>

      {isOpen && (
        <ul
          className="start-flow-filter-dropdown"
          role="listbox"
          aria-label={t('Start node filter options')}
          aria-multiselectable="true"
        >
          {/* "All" option */}
          <li
            role="option"
            aria-selected={isAllSelected}
            className={`start-flow-filter-option ${isAllSelected ? 'selected' : ''}`}
            onClick={handleSelectAll}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleSelectAll(); } }}
            tabIndex={0}
          >
            <span className="start-flow-filter-check">
              {isAllSelected && <FiCheck />}
            </span>
            <span>{t('All')}</span>
          </li>

          <li className="start-flow-filter-divider" role="separator" />

          {/* Individual start nodes */}
          {startNodes.map((node) => {
            const isSelected = isAllSelected || visibleStartNodeIds!.includes(node.id);
            return (
              <li
                key={node.id}
                role="option"
                aria-selected={isSelected}
                className={`start-flow-filter-option ${isSelected ? 'selected' : ''}`}
                onClick={() => handleToggleNode(node.id)}
                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleToggleNode(node.id); } }}
                tabIndex={0}
              >
                <span className="start-flow-filter-check">
                  {isSelected && <FiCheck />}
                </span>
                <span className="start-flow-filter-option-label">{getNodeLabel(node)}</span>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
});

StartFlowFilter.displayName = 'StartFlowFilter';

export default StartFlowFilter;
