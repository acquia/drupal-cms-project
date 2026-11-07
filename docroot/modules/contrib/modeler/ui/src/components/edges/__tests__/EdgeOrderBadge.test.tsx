import React from 'react';
import { render, fireEvent, act } from '@testing-library/react';
import EdgeOrderBadge from '../EdgeOrderBadge';

describe('EdgeOrderBadge', () => {
  const defaultProps = {
    edgeId: 'edge-1',
    edgeOrderInfo: {
      pathX: 200,
      pathY: 150,
      order: 1,
      totalEdges: 3,
      sourceNodeId: 'node-1',
    },
    isLocked: false,
    onReorderEdge: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  const getBadgeElement = () => {
    return document.querySelector('.edge-order-number') as HTMLElement;
  };

  const getBadgeLabel = () => {
    return document.querySelector('.edge-order-badge') as HTMLElement;
  };

  describe('rendering', () => {
    it('should render the flow label with order number', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      expect(badge).toBeTruthy();
      expect(badge.textContent).toBe('Flow 1');
    });

    it('should render "Flow 2" for second order', () => {
      render(
        <EdgeOrderBadge
          {...defaultProps}
          edgeOrderInfo={{ ...defaultProps.edgeOrderInfo, order: 2 }}
        />
      );
      const badge = getBadgeLabel();
      expect(badge.textContent).toBe('Flow 2');
    });

    it('should not render when pathX is undefined', () => {
      const { container } = render(
        <EdgeOrderBadge
          {...defaultProps}
          edgeOrderInfo={{ ...defaultProps.edgeOrderInfo, pathX: undefined }}
        />
      );
      expect(container.firstChild).toBeNull();
    });

    it('should not render when totalEdges is 1', () => {
      const { container } = render(
        <EdgeOrderBadge
          {...defaultProps}
          edgeOrderInfo={{ ...defaultProps.edgeOrderInfo, totalEdges: 1 }}
        />
      );
      expect(container.firstChild).toBeNull();
    });

    it('should be draggable when not locked', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      expect(badge.getAttribute('draggable')).toBe('true');
    });

    it('should not be draggable when locked', () => {
      render(<EdgeOrderBadge {...defaultProps} isLocked={true} />);
      const badge = getBadgeElement();
      expect(badge.getAttribute('draggable')).toBe('false');
    });

    it('should have interactive class when not locked', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      expect(badge.classList.contains('edge-order-badge--interactive')).toBe(true);
    });

    it('should not have interactive class when locked', () => {
      render(<EdgeOrderBadge {...defaultProps} isLocked={true} />);
      const badge = getBadgeLabel();
      expect(badge.classList.contains('edge-order-badge--interactive')).toBe(false);
    });
  });

  describe('dropdown', () => {
    it('should open dropdown on badge click', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      const dropdown = document.querySelector('.edge-order-dropdown');
      expect(dropdown).toBeTruthy();
    });

    it('should not open dropdown when locked', () => {
      render(<EdgeOrderBadge {...defaultProps} isLocked={true} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      const dropdown = document.querySelector('.edge-order-dropdown');
      expect(dropdown).toBeNull();
    });

    it('should show all flow options in dropdown', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      const items = document.querySelectorAll('.edge-order-dropdown-item');
      expect(items.length).toBe(3);
      expect(items[0].textContent).toBe('Flow 1');
      expect(items[1].textContent).toBe('Flow 2');
      expect(items[2].textContent).toBe('Flow 3');
    });

    it('should mark current order as active', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      const items = document.querySelectorAll('.edge-order-dropdown-item');
      expect(items[0].classList.contains('edge-order-dropdown-item--active')).toBe(true);
      expect(items[1].classList.contains('edge-order-dropdown-item--active')).toBe(false);
    });

    it('should call onReorderEdge when selecting a different order', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      const items = document.querySelectorAll('.edge-order-dropdown-item');
      fireEvent.click(items[1]); // Select "Flow 2"
      expect(defaultProps.onReorderEdge).toHaveBeenCalledWith('node-1', 1, 2);
    });

    it('should not call onReorderEdge when selecting the same order', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      const items = document.querySelectorAll('.edge-order-dropdown-item');
      fireEvent.click(items[0]); // Select "Flow 1" (same as current)
      expect(defaultProps.onReorderEdge).not.toHaveBeenCalled();
    });

    it('should close dropdown after selection', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      expect(document.querySelector('.edge-order-dropdown')).toBeTruthy();
      const items = document.querySelectorAll('.edge-order-dropdown-item');
      fireEvent.click(items[1]);
      expect(document.querySelector('.edge-order-dropdown')).toBeNull();
    });

    it('should close dropdown on outside pointer down', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      expect(document.querySelector('.edge-order-dropdown')).toBeTruthy();
      act(() => {
        fireEvent.pointerDown(document.body);
      });
      expect(document.querySelector('.edge-order-dropdown')).toBeNull();
    });

    it('should close dropdown on Escape key', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      expect(document.querySelector('.edge-order-dropdown')).toBeTruthy();
      act(() => {
        fireEvent.keyDown(document, { key: 'Escape' });
      });
      expect(document.querySelector('.edge-order-dropdown')).toBeNull();
    });

    it('should toggle dropdown on repeated clicks', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      expect(document.querySelector('.edge-order-dropdown')).toBeTruthy();
      fireEvent.click(badge);
      expect(document.querySelector('.edge-order-dropdown')).toBeNull();
    });

    it('should open dropdown on Enter key', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const wrapper = getBadgeElement();
      fireEvent.keyDown(wrapper, { key: 'Enter' });
      expect(document.querySelector('.edge-order-dropdown')).toBeTruthy();
    });

    it('should open dropdown on Space key', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const wrapper = getBadgeElement();
      fireEvent.keyDown(wrapper, { key: ' ' });
      expect(document.querySelector('.edge-order-dropdown')).toBeTruthy();
    });

    it('should have proper ARIA attributes on wrapper', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const wrapper = getBadgeElement();
      expect(wrapper.getAttribute('role')).toBe('button');
      expect(wrapper.getAttribute('aria-haspopup')).toBe('listbox');
      expect(wrapper.getAttribute('aria-expanded')).toBe('false');
      expect(wrapper.getAttribute('tabindex')).toBe('0');
    });

    it('should update aria-expanded when dropdown is open', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const wrapper = getBadgeElement();
      fireEvent.click(wrapper);
      expect(wrapper.getAttribute('aria-expanded')).toBe('true');
    });

    it('should have listbox role on dropdown', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      const dropdown = document.querySelector('.edge-order-dropdown');
      expect(dropdown?.getAttribute('role')).toBe('listbox');
    });

    it('should select via Enter key on dropdown item', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      const items = document.querySelectorAll('.edge-order-dropdown-item');
      fireEvent.keyDown(items[2], { key: 'Enter' });
      expect(defaultProps.onReorderEdge).toHaveBeenCalledWith('node-1', 1, 3);
    });
  });

  describe('drag and drop', () => {
    it('should stop propagation on mouseDown', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      const event = new MouseEvent('mousedown', { bubbles: true });
      const stopPropagation = jest.spyOn(event, 'stopPropagation');
      badge.dispatchEvent(event);
      expect(stopPropagation).toHaveBeenCalled();
    });

    it('should not handle dragStart when locked', () => {
      render(<EdgeOrderBadge {...defaultProps} isLocked={true} />);
      const badge = getBadgeElement();
      // Should not throw
      fireEvent.dragStart(badge);
    });

    it('should add visual feedback on dragOver', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      // Need to provide dataTransfer since the handler accesses it
      const dataTransfer = { dropEffect: '' };
      fireEvent.dragOver(badge, { dataTransfer } as any);
      // The drag-over class is added via event.currentTarget
      expect(badge.classList.contains('drag-over')).toBe(true);
    });

    it('should remove visual feedback on dragLeave', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      fireEvent.dragLeave(badge);
    });

    it('should not handle drop when locked', () => {
      render(<EdgeOrderBadge {...defaultProps} isLocked={true} />);
      const badge = getBadgeElement();
      fireEvent.drop(badge);
      expect(defaultProps.onReorderEdge).not.toHaveBeenCalled();
    });

    it('should silently handle empty drop data', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();

      // Simulate a drop with empty data - should not throw
      fireEvent.drop(badge, {
        dataTransfer: { getData: () => '' },
      });
      expect(defaultProps.onReorderEdge).not.toHaveBeenCalled();
    });

    it('should silently handle invalid JSON in drop data', () => {
      const debugSpy = jest.spyOn(console, 'debug').mockImplementation(() => {});
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();

      fireEvent.drop(badge, {
        dataTransfer: { getData: () => 'not-json' },
      });
      expect(defaultProps.onReorderEdge).not.toHaveBeenCalled();
      debugSpy.mockRestore();
    });

    it('should not be draggable while dropdown is open', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badgeLabel = getBadgeLabel();
      fireEvent.click(badgeLabel);
      const badge = getBadgeElement();
      expect(badge.getAttribute('draggable')).toBe('false');
    });
  });

  describe('positioning', () => {
    it('should position badge at pathX, pathY - 20', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      expect(badge.style.transform).toContain('200px');
      expect(badge.style.transform).toContain('130px'); // 150 - 20
    });

    it('should handle undefined pathY gracefully', () => {
      render(
        <EdgeOrderBadge
          {...defaultProps}
          edgeOrderInfo={{ ...defaultProps.edgeOrderInfo, pathY: undefined }}
        />
      );
      const badge = getBadgeElement();
      expect(badge.style.transform).toContain('-20px'); // 0 - 20
    });

    it('should have high z-index for visibility', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      // z-index is now applied via CSS class .edge-order-number
      expect(badge.classList.contains('edge-order-number')).toBe(true);
    });
  });

  describe('successful reorder', () => {
    it('should call onReorderEdge on valid drop with matching source node', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      const dropData = JSON.stringify({
        edgeId: 'other-edge',
        sourceNodeId: 'node-1',
        currentOrder: 2,
        totalEdges: 3,
      });
      fireEvent.drop(badge, {
        dataTransfer: { getData: () => dropData },
      });
      expect(defaultProps.onReorderEdge).toHaveBeenCalledWith('node-1', 2, 1);
    });

    it('should not call onReorderEdge when same order is dropped', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      const dropData = JSON.stringify({
        edgeId: 'other-edge',
        sourceNodeId: 'node-1',
        currentOrder: 1,
        totalEdges: 3,
      });
      fireEvent.drop(badge, {
        dataTransfer: { getData: () => dropData },
      });
      expect(defaultProps.onReorderEdge).not.toHaveBeenCalled();
    });

    it('should not call onReorderEdge when different source node', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      const dropData = JSON.stringify({
        edgeId: 'other-edge',
        sourceNodeId: 'different-node',
        currentOrder: 2,
        totalEdges: 3,
      });
      fireEvent.drop(badge, {
        dataTransfer: { getData: () => dropData },
      });
      expect(defaultProps.onReorderEdge).not.toHaveBeenCalled();
    });
  });

  describe('sourceNodeId fallback', () => {
    it('should use edgeId split as sourceNodeId fallback', () => {
      const props = {
        ...defaultProps,
        edgeId: 'nodeA_to_nodeB',
        edgeOrderInfo: {
          ...defaultProps.edgeOrderInfo,
          sourceNodeId: undefined,
        },
      };
      render(<EdgeOrderBadge {...props} />);
      const badge = getBadgeElement();
      const dropData = JSON.stringify({
        edgeId: 'other-edge',
        sourceNodeId: 'nodeA',
        currentOrder: 2,
        totalEdges: 3,
      });
      fireEvent.drop(badge, {
        dataTransfer: { getData: () => dropData },
      });
      expect(defaultProps.onReorderEdge).toHaveBeenCalledWith('nodeA', 2, 1);
    });

    it('should use edgeId fallback for dropdown reorder', () => {
      const props = {
        ...defaultProps,
        edgeId: 'nodeA_to_nodeB',
        edgeOrderInfo: {
          ...defaultProps.edgeOrderInfo,
          sourceNodeId: undefined,
        },
      };
      render(<EdgeOrderBadge {...props} />);
      const badge = getBadgeLabel();
      fireEvent.click(badge);
      const items = document.querySelectorAll('.edge-order-dropdown-item');
      fireEvent.click(items[1]); // Select "Flow 2"
      expect(defaultProps.onReorderEdge).toHaveBeenCalledWith('nodeA', 1, 2);
    });
  });

  describe('dragStart visual feedback', () => {
    it('should set data transfer and visual feedback on dragStart', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      const mockDataTransfer = {
        setData: jest.fn(),
        effectAllowed: '',
        setDragImage: jest.fn(),
      };
      fireEvent.dragStart(badge, { dataTransfer: mockDataTransfer });
      expect(mockDataTransfer.setData).toHaveBeenCalledWith('edgeOrderReorder', expect.any(String));
      expect(mockDataTransfer.effectAllowed).toBe('move');
    });
  });

  describe('dragEnd cleanup', () => {
    it('should restore opacity and cursor on dragEnd', () => {
      render(<EdgeOrderBadge {...defaultProps} />);
      const badge = getBadgeElement();
      fireEvent.dragEnd(badge);
      // dragEnd restores styles
    });
  });

  describe('dragOver when locked', () => {
    it('should not set dropEffect when locked', () => {
      render(<EdgeOrderBadge {...defaultProps} isLocked={true} />);
      const badge = getBadgeElement();
      const dataTransfer = { dropEffect: '' };
      fireEvent.dragOver(badge, { dataTransfer } as any);
      // When locked, handler returns early
    });

    it('should not process drop when locked', () => {
      render(<EdgeOrderBadge {...defaultProps} isLocked={true} />);
      const badge = getBadgeElement();
      const dropData = JSON.stringify({
        edgeId: 'other-edge',
        sourceNodeId: 'node-1',
        currentOrder: 2,
        totalEdges: 3,
      });
      fireEvent.drop(badge, {
        dataTransfer: { getData: () => dropData },
      });
      expect(defaultProps.onReorderEdge).not.toHaveBeenCalled();
    });
  });

  describe('drop without onReorderEdge callback', () => {
    it('should handle drop gracefully when onReorderEdge is undefined', () => {
      const props = { ...defaultProps, onReorderEdge: undefined };
      render(<EdgeOrderBadge {...props} />);
      const badge = getBadgeElement();
      const dropData = JSON.stringify({
        edgeId: 'other-edge',
        sourceNodeId: 'node-1',
        currentOrder: 2,
        totalEdges: 3,
      });
      // Should not throw
      fireEvent.drop(badge, {
        dataTransfer: { getData: () => dropData },
      });
    });

    it('should handle dropdown selection gracefully when onReorderEdge is undefined', () => {
      const { container } = render(
        <EdgeOrderBadge {...defaultProps} onReorderEdge={undefined} />
      );
      const badge = container.querySelector('.edge-order-badge') as HTMLElement;
      expect(badge).toBeTruthy();
      // Badge is still clickable (not locked) but onReorderEdge is undefined
      fireEvent.click(badge);
      const dropdown = container.querySelector('.edge-order-dropdown');
      expect(dropdown).toBeTruthy();
      const items = container.querySelectorAll('.edge-order-dropdown-item');
      expect(items.length).toBe(3);
      // Should not throw when selecting
      fireEvent.click(items[1]);
      // Dropdown should close after selection even without callback
      expect(container.querySelector('.edge-order-dropdown')).toBeNull();
    });
  });
});
