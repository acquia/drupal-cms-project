/**
 * EdgeOrderBadge - Renders the draggable edge order badge with "Flow N" label
 * displayed near edges when edge ordering is visible. Supports both a dropdown
 * menu for selecting flow order and drag-and-drop reordering of edges from the
 * same source node.
 */
import React, { useState, useRef, useEffect, useCallback } from 'react';
import type { EdgeOrderInfo } from '../../types/settings';
import { t } from '../../utils/translation';

interface EdgeOrderBadgeProps {
  edgeId: string;
  edgeOrderInfo: EdgeOrderInfo;
  isLocked: boolean;
  onReorderEdge?: (sourceNodeId: string, fromOrder: number, toOrder: number) => void;
}

const EdgeOrderBadge: React.FC<EdgeOrderBadgeProps> = ({
  edgeId,
  edgeOrderInfo,
  isLocked,
  onReorderEdge,
}) => {
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);

  const closeDropdown = useCallback(() => {
    setDropdownOpen(false);
  }, []);

  // Close dropdown when clicking outside or pressing Escape.
  // Uses capture phase + pointerdown so the listener fires even when
  // ReactFlow or other handlers call stopPropagation on mousedown.
  useEffect(() => {
    if (!dropdownOpen) return;
    const handlePointerOutside = (event: PointerEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target as HTMLElement)) {
        closeDropdown();
      }
    };
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeDropdown();
      }
    };
    document.addEventListener('pointerdown', handlePointerOutside, true);
    document.addEventListener('keydown', handleKeyDown, true);
    return () => {
      document.removeEventListener('pointerdown', handlePointerOutside, true);
      document.removeEventListener('keydown', handleKeyDown, true);
    };
  }, [dropdownOpen, closeDropdown]);

  if (edgeOrderInfo.pathX === undefined || edgeOrderInfo.totalEdges <= 1) {
    return null;
  }

  const handleBadgeClick = (e: React.MouseEvent) => {
    if (isLocked) return;
    e.stopPropagation();
    e.preventDefault();
    setDropdownOpen(prev => !prev);
  };

  const handleOrderSelect = (newOrder: number) => {
    if (newOrder !== edgeOrderInfo.order && onReorderEdge) {
      const sourceNodeId = edgeOrderInfo.sourceNodeId || edgeId.split('_to_')[0];
      onReorderEdge(sourceNodeId, edgeOrderInfo.order, newOrder);
    }
    closeDropdown();
  };

  const orderOptions = Array.from({ length: edgeOrderInfo.totalEdges }, (_, i) => i + 1);

  return (
    <div
      className="edge-order-number nodrag nopan"
      style={{
        transform: `translate(-50%, -50%) translate(${edgeOrderInfo.pathX}px, ${(edgeOrderInfo.pathY ?? 0) - 20}px)`,
      }}
      ref={wrapperRef}
      role={isLocked ? undefined : 'button'}
      tabIndex={isLocked ? undefined : 0}
      aria-label={isLocked ? undefined : t('Flow @order of @total, click to change', { '@order': String(edgeOrderInfo.order), '@total': String(edgeOrderInfo.totalEdges) })}
      aria-expanded={isLocked ? undefined : dropdownOpen}
      aria-haspopup={isLocked ? undefined : 'listbox'}
      draggable={!isLocked && !dropdownOpen}
      onClick={handleBadgeClick}
      onKeyDown={(e) => {
        if (isLocked) return;
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          e.stopPropagation();
          setDropdownOpen(prev => !prev);
        }
      }}
      onMouseDown={(e) => {
        e.stopPropagation();
      }}
      onDragStart={(e) => {
        if (isLocked || dropdownOpen) return;
        e.stopPropagation();
        e.dataTransfer.setData('edgeOrderReorder', JSON.stringify({
          edgeId: edgeId,
          sourceNodeId: edgeOrderInfo.sourceNodeId || edgeId.split('_to_')[0],
          currentOrder: edgeOrderInfo.order,
          totalEdges: edgeOrderInfo.totalEdges
        }));
        e.dataTransfer.effectAllowed = 'move';

        const dragImage = (e.target as HTMLElement).cloneNode(true) as HTMLElement;
        dragImage.style.position = 'absolute';
        dragImage.style.top = '-1000px';
        document.body.appendChild(dragImage);
        e.dataTransfer.setDragImage(dragImage, 8, 8);
        setTimeout(() => document.body.removeChild(dragImage), 0);

        (e.target as HTMLElement).classList.add('dragging');
      }}
      onDragEnd={(e) => {
        (e.target as HTMLElement).classList.remove('dragging');
      }}
      onDragOver={(e) => {
        if (isLocked) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        (e.currentTarget as HTMLElement).classList.add('drag-over');
      }}
      onDragLeave={(e) => {
        (e.currentTarget as HTMLElement).classList.remove('drag-over');
      }}
      onDrop={(e) => {
        if (isLocked) return;
        e.preventDefault();
        e.stopPropagation();

        (e.currentTarget as HTMLElement).classList.remove('drag-over');

        try {
          const rawData = e.dataTransfer.getData('edgeOrderReorder');
          if (!rawData || rawData.trim() === '') {
            return;
          }

          const dragData = JSON.parse(rawData);
          const targetOrder = edgeOrderInfo.order;
          const sourceNodeId = dragData.sourceNodeId;

          if (dragData.currentOrder !== targetOrder && dragData.sourceNodeId === (edgeOrderInfo.sourceNodeId || edgeId.split('_to_')[0])) {
            if (onReorderEdge) {
              onReorderEdge(sourceNodeId, dragData.currentOrder, targetOrder);
            }
          }
        } catch (error) {
          console.debug('Drop ignored, not edge order data:', error);
        }
      }}
    >
      <div
        className={`edge-order-badge${!isLocked ? ' edge-order-badge--interactive' : ''}`}
      >
        {t('Flow @order', { '@order': String(edgeOrderInfo.order) })}
      </div>
      {dropdownOpen && !isLocked && (
        <div className="edge-order-dropdown" role="listbox" aria-label={t('Select flow order')}>
          {orderOptions.map((orderNum) => (
            <div
              key={orderNum}
              className={`edge-order-dropdown-item${orderNum === edgeOrderInfo.order ? ' edge-order-dropdown-item--active' : ''}`}
              role="option"
              aria-selected={orderNum === edgeOrderInfo.order}
              onClick={(e) => {
                e.stopPropagation();
                handleOrderSelect(orderNum);
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  e.stopPropagation();
                  handleOrderSelect(orderNum);
                }
              }}
              tabIndex={0}
            >
              {t('Flow @order', { '@order': String(orderNum) })}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default EdgeOrderBadge;
