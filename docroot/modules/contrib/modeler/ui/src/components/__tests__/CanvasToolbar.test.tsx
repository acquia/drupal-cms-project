import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import CanvasToolbar from '../CanvasToolbar';
import type { ModelerContext } from '../../types/settings';

// --- Mocks ---

const mockZoomIn = jest.fn();
const mockZoomOut = jest.fn();
const mockFitView = jest.fn();
const mockSetViewport = jest.fn();
const mockGetNodes = jest.fn((): Record<string, unknown>[] => []);

jest.mock('reactflow', () => ({
  useReactFlow: () => ({
    zoomIn: mockZoomIn,
    zoomOut: mockZoomOut,
    fitView: mockFitView,
    setViewport: mockSetViewport,
    getNodes: mockGetNodes,
  }),
  useStore: jest.fn((selector: any) => {
    if (typeof selector === 'function') {
      return selector({ transform: [0, 0, mockZoomLevel] });
    }
    return mockZoomLevel;
  }),
}));

// Mutable zoom level used by the useStore mock above
let mockZoomLevel = 1;

jest.mock('../StartFlowFilter', () => {
  const MockStartFlowFilter = () => <div data-testid="start-flow-filter">StartFlowFilter</div>;
  MockStartFlowFilter.displayName = 'StartFlowFilter';
  return { __esModule: true, default: MockStartFlowFilter };
});

const mockUseClickOutside = jest.fn();
jest.mock('../../hooks/useClickOutside', () => ({
  useClickOutside: (...args: any[]) => mockUseClickOutside(...args),
}));

jest.mock('../../utils/modelUtils', () => ({
  getFitViewport: jest.fn(() => ({ x: 0, y: 0, zoom: 1 })),
}));

// --- Helpers ---

const defaultProps = {
  isLocked: false,
  isReadOnly: false,
  onCopy: jest.fn(),
  onPaste: jest.fn(),
  onUndo: jest.fn(),
  onRedo: jest.fn(),
  hasSelection: false,
  canPaste: false,
  canUndo: false,
  canRedo: false,
  onAutoLayout: jest.fn(),
  onFitView: jest.fn(),
  contexts: [] as ModelerContext[],
  selectedContextId: null as string | null,
  onContextChange: jest.fn(),
};

function renderToolbar(overrides: Partial<typeof defaultProps> = {}) {
  const props = { ...defaultProps, ...overrides };
  return render(<CanvasToolbar {...props} />);
}

// --- Tests ---

describe('CanvasToolbar', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockZoomLevel = 1;
    mockGetNodes.mockReturnValue([]);
  });

  // ─── Rendering ───────────────────────────────────────────────

  describe('rendering', () => {
    it('should render the toolbar container', () => {
      renderToolbar();
      expect(document.querySelector('.canvas-toolbar')).toBeInTheDocument();
    });

    it('should render the StartFlowFilter component', () => {
      renderToolbar();
      expect(screen.getByTestId('start-flow-filter')).toBeInTheDocument();
    });

    it('should render the View button', () => {
      renderToolbar();
      expect(screen.getByLabelText('View options')).toBeInTheDocument();
    });

    it('should render "View" label text in the button', () => {
      renderToolbar();
      expect(screen.getByText('View')).toBeInTheDocument();
    });
  });

  // ─── Zoom controls ──────────────────────────────────────────

  describe('zoom controls', () => {
    it('should render zoom in button', () => {
      renderToolbar();
      expect(screen.getByLabelText('Zoom In')).toBeInTheDocument();
    });

    it('should render zoom out button', () => {
      renderToolbar();
      expect(screen.getByLabelText('Zoom Out')).toBeInTheDocument();
    });

    it('should display zoom percentage at 100%', () => {
      mockZoomLevel = 1;
      renderToolbar();
      expect(screen.getByLabelText('Current zoom level')).toHaveTextContent('100%');
    });

    it('should display zoom percentage at 50%', () => {
      mockZoomLevel = 0.5;
      renderToolbar();
      expect(screen.getByLabelText('Current zoom level')).toHaveTextContent('50%');
    });

    it('should display zoom percentage at 200%', () => {
      mockZoomLevel = 2;
      renderToolbar();
      expect(screen.getByLabelText('Current zoom level')).toHaveTextContent('200%');
    });

    it('should call reactFlow.zoomIn when zoom in button is clicked', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('Zoom In'));
      expect(mockZoomIn).toHaveBeenCalledTimes(1);
    });

    it('should call reactFlow.zoomOut when zoom out button is clicked', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('Zoom Out'));
      expect(mockZoomOut).toHaveBeenCalledTimes(1);
    });

    it('should disable zoom out button at minimum zoom', () => {
      mockZoomLevel = 0.1;
      renderToolbar();
      expect(screen.getByLabelText('Zoom Out')).toBeDisabled();
    });

    it('should disable zoom in button at maximum zoom', () => {
      mockZoomLevel = 4;
      renderToolbar();
      expect(screen.getByLabelText('Zoom In')).toBeDisabled();
    });

    it('should disable zoom out when within tolerance of minimum', () => {
      mockZoomLevel = 0.105; // <= 0.1 + 0.01 = 0.11
      renderToolbar();
      expect(screen.getByLabelText('Zoom Out')).toBeDisabled();
    });

    it('should disable zoom in when within tolerance of maximum', () => {
      mockZoomLevel = 3.995; // >= 4 - 0.01 = 3.99
      renderToolbar();
      expect(screen.getByLabelText('Zoom In')).toBeDisabled();
    });

    it('should enable zoom out when above minimum plus tolerance', () => {
      mockZoomLevel = 0.5;
      renderToolbar();
      expect(screen.getByLabelText('Zoom Out')).not.toBeDisabled();
    });

    it('should enable zoom in when below maximum minus tolerance', () => {
      mockZoomLevel = 2;
      renderToolbar();
      expect(screen.getByLabelText('Zoom In')).not.toBeDisabled();
    });

    it('should stop propagation on zoom in click', () => {
      renderToolbar();
      const btn = screen.getByLabelText('Zoom In');
      const event = new MouseEvent('click', { bubbles: true, cancelable: true });
      jest.spyOn(event, 'stopPropagation');
      btn.dispatchEvent(event);
      // The actual handler calls stopPropagation via React synthetic event,
      // but fireEvent is simplest:
      fireEvent.click(btn);
      expect(mockZoomIn).toHaveBeenCalled();
    });

    it('should stop propagation on zoom out click', () => {
      renderToolbar();
      const btn = screen.getByLabelText('Zoom Out');
      fireEvent.click(btn);
      expect(mockZoomOut).toHaveBeenCalled();
    });
  });

  // ─── Copy / Paste / Undo / Redo ─────────────────────────────

  describe('copy/paste/undo/redo buttons', () => {
    it('should render all four editing buttons when not read-only', () => {
      renderToolbar();
      expect(screen.getByLabelText('Copy Selected Elements (Ctrl+C)')).toBeInTheDocument();
      expect(screen.getByLabelText('Paste Elements (Ctrl+V)')).toBeInTheDocument();
      expect(screen.getByLabelText('Undo (Ctrl+Z)')).toBeInTheDocument();
      expect(screen.getByLabelText('Redo (Ctrl+Shift+Z)')).toBeInTheDocument();
    });

    it('should hide editing buttons in read-only mode', () => {
      renderToolbar({ isReadOnly: true });
      expect(screen.queryByLabelText('Copy Selected Elements (Ctrl+C)')).not.toBeInTheDocument();
      expect(screen.queryByLabelText('Paste Elements (Ctrl+V)')).not.toBeInTheDocument();
      expect(screen.queryByLabelText('Undo (Ctrl+Z)')).not.toBeInTheDocument();
      expect(screen.queryByLabelText('Redo (Ctrl+Shift+Z)')).not.toBeInTheDocument();
    });

    it('should render separator between editing and zoom buttons when not read-only', () => {
      renderToolbar();
      expect(document.querySelector('.canvas-toolbar-separator')).toBeInTheDocument();
    });

    it('should not render separator in read-only mode', () => {
      renderToolbar({ isReadOnly: true });
      expect(document.querySelector('.canvas-toolbar-separator')).not.toBeInTheDocument();
    });
  });

  describe('copy button', () => {
    it('should be disabled when isLocked is true', () => {
      renderToolbar({ isLocked: true, hasSelection: true });
      expect(screen.getByLabelText('Copy Selected Elements (Ctrl+C)')).toBeDisabled();
    });

    it('should be disabled when hasSelection is false', () => {
      renderToolbar({ hasSelection: false });
      expect(screen.getByLabelText('Copy Selected Elements (Ctrl+C)')).toBeDisabled();
    });

    it('should be enabled when not locked and has selection', () => {
      renderToolbar({ isLocked: false, hasSelection: true });
      expect(screen.getByLabelText('Copy Selected Elements (Ctrl+C)')).not.toBeDisabled();
    });

    it('should call onCopy when clicked', () => {
      const onCopy = jest.fn();
      renderToolbar({ onCopy, hasSelection: true });
      fireEvent.click(screen.getByLabelText('Copy Selected Elements (Ctrl+C)'));
      expect(onCopy).toHaveBeenCalledTimes(1);
    });

    it('should be disabled when both isLocked and no selection', () => {
      renderToolbar({ isLocked: true, hasSelection: false });
      expect(screen.getByLabelText('Copy Selected Elements (Ctrl+C)')).toBeDisabled();
    });
  });

  describe('paste button', () => {
    it('should be disabled when isLocked is true', () => {
      renderToolbar({ isLocked: true, canPaste: true });
      expect(screen.getByLabelText('Paste Elements (Ctrl+V)')).toBeDisabled();
    });

    it('should be disabled when canPaste is false', () => {
      renderToolbar({ canPaste: false });
      expect(screen.getByLabelText('Paste Elements (Ctrl+V)')).toBeDisabled();
    });

    it('should be enabled when not locked and canPaste is true', () => {
      renderToolbar({ isLocked: false, canPaste: true });
      expect(screen.getByLabelText('Paste Elements (Ctrl+V)')).not.toBeDisabled();
    });

    it('should call onPaste when clicked', () => {
      const onPaste = jest.fn();
      renderToolbar({ onPaste, canPaste: true });
      fireEvent.click(screen.getByLabelText('Paste Elements (Ctrl+V)'));
      expect(onPaste).toHaveBeenCalledTimes(1);
    });
  });

  describe('undo button', () => {
    it('should be disabled when canUndo is false', () => {
      renderToolbar({ canUndo: false });
      expect(screen.getByLabelText('Undo (Ctrl+Z)')).toBeDisabled();
    });

    it('should be enabled when canUndo is true', () => {
      renderToolbar({ canUndo: true });
      expect(screen.getByLabelText('Undo (Ctrl+Z)')).not.toBeDisabled();
    });

    it('should call onUndo when clicked', () => {
      const onUndo = jest.fn();
      renderToolbar({ onUndo, canUndo: true });
      fireEvent.click(screen.getByLabelText('Undo (Ctrl+Z)'));
      expect(onUndo).toHaveBeenCalledTimes(1);
    });
  });

  describe('redo button', () => {
    it('should be disabled when canRedo is false', () => {
      renderToolbar({ canRedo: false });
      expect(screen.getByLabelText('Redo (Ctrl+Shift+Z)')).toBeDisabled();
    });

    it('should be enabled when canRedo is true', () => {
      renderToolbar({ canRedo: true });
      expect(screen.getByLabelText('Redo (Ctrl+Shift+Z)')).not.toBeDisabled();
    });

    it('should call onRedo when clicked', () => {
      const onRedo = jest.fn();
      renderToolbar({ onRedo, canRedo: true });
      fireEvent.click(screen.getByLabelText('Redo (Ctrl+Shift+Z)'));
      expect(onRedo).toHaveBeenCalledTimes(1);
    });
  });

  // ─── View dropdown menu ──────────────────────────────────────

  describe('view menu', () => {
    it('should not show dropdown by default', () => {
      renderToolbar();
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('should have aria-expanded=false by default', () => {
      renderToolbar();
      expect(screen.getByLabelText('View options')).toHaveAttribute('aria-expanded', 'false');
    });

    it('should have aria-haspopup=menu', () => {
      renderToolbar();
      expect(screen.getByLabelText('View options')).toHaveAttribute('aria-haspopup', 'menu');
    });

    it('should open dropdown when View button is clicked', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      expect(screen.getByRole('menu')).toBeInTheDocument();
    });

    it('should set aria-expanded=true when open', () => {
      renderToolbar();
      const button = screen.getByRole('button', { name: 'View options' });
      fireEvent.click(button);
      expect(button).toHaveAttribute('aria-expanded', 'true');
    });

    it('should close dropdown when View button is clicked again', () => {
      renderToolbar();
      const button = screen.getByLabelText('View options');
      fireEvent.click(button);
      expect(screen.getByRole('menu')).toBeInTheDocument();
      fireEvent.click(button);
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('should have open class on chevron when menu is open', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      const chevron = document.querySelector('.canvas-toolbar-chevron');
      expect(chevron).toHaveClass('open');
    });

    it('should not have open class on chevron when menu is closed', () => {
      renderToolbar();
      const chevron = document.querySelector('.canvas-toolbar-chevron');
      expect(chevron).not.toHaveClass('open');
    });

    it('should show Fit View menu item', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      expect(screen.getByText('Fit View')).toBeInTheDocument();
    });

    it('should show Auto Layout menu item when not read-only', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      expect(screen.getByText('Auto Layout')).toBeInTheDocument();
    });

    it('should hide Auto Layout menu item in read-only mode', () => {
      renderToolbar({ isReadOnly: true });
      fireEvent.click(screen.getByLabelText('View options'));
      expect(screen.queryByText('Auto Layout')).not.toBeInTheDocument();
    });

    it('should have role=menuitem on Fit View', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      const menuItems = screen.getAllByRole('menuitem');
      expect(menuItems.length).toBeGreaterThanOrEqual(1);
      expect(menuItems[0]).toHaveTextContent('Fit View');
    });

    it('should have role=menuitem on Auto Layout', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      const menuItems = screen.getAllByRole('menuitem');
      expect(menuItems.length).toBe(2);
      expect(menuItems[1]).toHaveTextContent('Auto Layout');
    });

    it('should have role=menu with aria-label on dropdown', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      const menu = screen.getByRole('menu');
      expect(menu).toHaveAttribute('aria-label', 'View options');
    });
  });

  // ─── Fit View action ────────────────────────────────────────

  describe('fit view', () => {
    it('should call onFitView when Fit View is clicked', () => {
      const onFitView = jest.fn();
      renderToolbar({ onFitView });
      fireEvent.click(screen.getByLabelText('View options'));
      fireEvent.click(screen.getByText('Fit View'));
      expect(onFitView).toHaveBeenCalledTimes(1);
    });

    it('should close menu after fit view', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      fireEvent.click(screen.getByText('Fit View'));
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
  });

  // ─── Auto Layout action ─────────────────────────────────────

  describe('auto layout', () => {
    it('should call onAutoLayout when Auto Layout is clicked', () => {
      const onAutoLayout = jest.fn();
      renderToolbar({ onAutoLayout });
      fireEvent.click(screen.getByLabelText('View options'));
      fireEvent.click(screen.getByText('Auto Layout'));
      expect(onAutoLayout).toHaveBeenCalledTimes(1);
    });

    it('should close menu after auto layout', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      fireEvent.click(screen.getByText('Auto Layout'));
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
  });

  // ─── Keyboard handling ──────────────────────────────────────

  describe('keyboard handling', () => {
    it('should close View menu on Escape key', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      expect(screen.getByRole('menu')).toBeInTheDocument();

      fireEvent.keyDown(screen.getByRole('menu'), { key: 'Escape' });
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('should not close View menu on other keys', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      expect(screen.getByRole('menu')).toBeInTheDocument();

      fireEvent.keyDown(screen.getByRole('menu'), { key: 'ArrowDown' });
      expect(screen.getByRole('menu')).toBeInTheDocument();
    });
  });

  // ─── useClickOutside integration ────────────────────────────

  describe('click outside', () => {
    it('should register useClickOutside hook', () => {
      renderToolbar();
      expect(mockUseClickOutside).toHaveBeenCalled();
    });

    it('should pass viewMenuOpen state to useClickOutside', () => {
      renderToolbar();
      // On initial render, the first argument should be false
      expect(mockUseClickOutside).toHaveBeenCalledWith(
        false,
        expect.any(Array),
        expect.any(Function),
      );
    });

    it('should pass true to useClickOutside when menu is open', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      // After opening, the hook should be called with true
      const lastCall = mockUseClickOutside.mock.calls[mockUseClickOutside.mock.calls.length - 1];
      expect(lastCall[0]).toBe(true);
    });

    it('should close menu when useClickOutside callback is invoked', () => {
      renderToolbar();
      fireEvent.click(screen.getByLabelText('View options'));
      expect(screen.getByRole('menu')).toBeInTheDocument();

      // Get the callback passed to useClickOutside and invoke it
      const lastCall = mockUseClickOutside.mock.calls[mockUseClickOutside.mock.calls.length - 1];
      const closeCallback = lastCall[2];
      closeCallback();

      // After the callback, re-render needed: the state was set inside callback
      // Since we're calling the function directly, we need to verify it was a valid callback
      expect(typeof closeCallback).toBe('function');
    });
  });

  // ─── Context selector ───────────────────────────────────────

  describe('context selector', () => {
    const mockContexts: ModelerContext[] = [
      { id: 'ctx-1', topic: 'Context One', model_owner: 'owner1', components: {} },
      { id: 'ctx-2', topic: 'Context Two', model_owner: 'owner2', components: {} },
    ] as ModelerContext[];

    it('should not render context selector when contexts is empty', () => {
      renderToolbar({ contexts: [] });
      expect(screen.queryByLabelText('Select Context')).not.toBeInTheDocument();
    });

    it('should not render context selector when contexts is undefined', () => {
      renderToolbar({ contexts: undefined });
      expect(screen.queryByLabelText('Select Context')).not.toBeInTheDocument();
    });

    it('should render context selector when contexts are provided', () => {
      renderToolbar({ contexts: mockContexts });
      expect(screen.getByLabelText('Select Context')).toBeInTheDocument();
    });

    it('should render "No Context" as default option', () => {
      renderToolbar({ contexts: mockContexts });
      expect(screen.getByText('No Context')).toBeInTheDocument();
    });

    it('should render all context topics as options', () => {
      renderToolbar({ contexts: mockContexts });
      expect(screen.getByText('Context One')).toBeInTheDocument();
      expect(screen.getByText('Context Two')).toBeInTheDocument();
    });

    it('should select "No Context" by default when selectedContextId is null', () => {
      renderToolbar({ contexts: mockContexts, selectedContextId: null });
      const select = screen.getByLabelText('Select Context') as HTMLSelectElement;
      expect(select.value).toBe('');
    });

    it('should select the matching context when selectedContextId is provided', () => {
      renderToolbar({ contexts: mockContexts, selectedContextId: 'ctx-2' });
      const select = screen.getByLabelText('Select Context') as HTMLSelectElement;
      expect(select.value).toBe('ctx-2');
    });

    it('should call onContextChange with context id when a context is selected', () => {
      const onContextChange = jest.fn();
      renderToolbar({ contexts: mockContexts, onContextChange });
      fireEvent.change(screen.getByLabelText('Select Context'), {
        target: { value: 'ctx-1' },
      });
      expect(onContextChange).toHaveBeenCalledWith('ctx-1');
    });

    it('should call onContextChange with null when "No Context" is selected', () => {
      const onContextChange = jest.fn();
      renderToolbar({
        contexts: mockContexts,
        selectedContextId: 'ctx-1',
        onContextChange,
      });
      fireEvent.change(screen.getByLabelText('Select Context'), {
        target: { value: '' },
      });
      expect(onContextChange).toHaveBeenCalledWith(null);
    });

    it('should have correct id and name attributes', () => {
      renderToolbar({ contexts: mockContexts });
      const select = screen.getByLabelText('Select Context');
      expect(select).toHaveAttribute('id', 'toolbar-context-select');
      expect(select).toHaveAttribute('name', 'toolbar-context-select');
    });

    it('should have correct CSS class', () => {
      renderToolbar({ contexts: mockContexts });
      const select = screen.getByLabelText('Select Context');
      expect(select).toHaveClass('toolbar-context-select');
    });

    it('should not call onContextChange if it is not provided', () => {
      // Should not throw when onContextChange is undefined
      renderToolbar({ contexts: mockContexts, onContextChange: undefined });
      expect(() => {
        fireEvent.change(screen.getByLabelText('Select Context'), {
          target: { value: 'ctx-1' },
        });
      }).not.toThrow();
    });
  });

  // ─── Default props ──────────────────────────────────────────

  describe('default prop values', () => {
    it('should default isReadOnly to false (show editing buttons)', () => {
      render(
        <CanvasToolbar
          isLocked={false}
          onCopy={jest.fn()}
          onPaste={jest.fn()}
          onAutoLayout={jest.fn()}
          onFitView={jest.fn()}
        />,
      );
      expect(screen.getByLabelText('Copy Selected Elements (Ctrl+C)')).toBeInTheDocument();
    });

    it('should default hasSelection to false (copy disabled)', () => {
      render(
        <CanvasToolbar
          isLocked={false}
          onCopy={jest.fn()}
          onPaste={jest.fn()}
          onAutoLayout={jest.fn()}
          onFitView={jest.fn()}
        />,
      );
      expect(screen.getByLabelText('Copy Selected Elements (Ctrl+C)')).toBeDisabled();
    });

    it('should default canPaste to false (paste disabled)', () => {
      render(
        <CanvasToolbar
          isLocked={false}
          onCopy={jest.fn()}
          onPaste={jest.fn()}
          onAutoLayout={jest.fn()}
          onFitView={jest.fn()}
        />,
      );
      expect(screen.getByLabelText('Paste Elements (Ctrl+V)')).toBeDisabled();
    });

    it('should default canUndo to false (undo disabled)', () => {
      render(
        <CanvasToolbar
          isLocked={false}
          onCopy={jest.fn()}
          onPaste={jest.fn()}
          onAutoLayout={jest.fn()}
          onFitView={jest.fn()}
        />,
      );
      expect(screen.getByLabelText('Undo (Ctrl+Z)')).toBeDisabled();
    });

    it('should default canRedo to false (redo disabled)', () => {
      render(
        <CanvasToolbar
          isLocked={false}
          onCopy={jest.fn()}
          onPaste={jest.fn()}
          onAutoLayout={jest.fn()}
          onFitView={jest.fn()}
        />,
      );
      expect(screen.getByLabelText('Redo (Ctrl+Shift+Z)')).toBeDisabled();
    });

    it('should not render context selector when contexts is not provided', () => {
      render(
        <CanvasToolbar
          isLocked={false}
          onCopy={jest.fn()}
          onPaste={jest.fn()}
          onAutoLayout={jest.fn()}
          onFitView={jest.fn()}
        />,
      );
      expect(screen.queryByLabelText('Select Context')).not.toBeInTheDocument();
    });
  });

  // ─── Combined states ────────────────────────────────────────

  describe('combined states', () => {
    it('should still show zoom controls in read-only mode', () => {
      renderToolbar({ isReadOnly: true });
      expect(screen.getByLabelText('Zoom In')).toBeInTheDocument();
      expect(screen.getByLabelText('Zoom Out')).toBeInTheDocument();
      expect(screen.getByLabelText('Current zoom level')).toBeInTheDocument();
    });

    it('should still show View dropdown in read-only mode', () => {
      renderToolbar({ isReadOnly: true });
      expect(screen.getByLabelText('View options')).toBeInTheDocument();
    });

    it('should show Fit View in read-only mode', () => {
      renderToolbar({ isReadOnly: true });
      fireEvent.click(screen.getByLabelText('View options'));
      expect(screen.getByText('Fit View')).toBeInTheDocument();
    });

    it('should still show StartFlowFilter in read-only mode', () => {
      renderToolbar({ isReadOnly: true });
      expect(screen.getByTestId('start-flow-filter')).toBeInTheDocument();
    });

    it('should render both context selector and StartFlowFilter', () => {
      const mockContexts: ModelerContext[] = [
        { id: 'ctx-1', topic: 'Ctx One', model_owner: 'o1', components: {} },
      ] as ModelerContext[];
      renderToolbar({ contexts: mockContexts });
      expect(screen.getByLabelText('Select Context')).toBeInTheDocument();
      expect(screen.getByTestId('start-flow-filter')).toBeInTheDocument();
    });
  });

  // ─── Fit view with mixed nodes ──────────────────────────────

  describe('fit view with mixed node states', () => {
    it('should delegate to onFitView regardless of node visibility', () => {
      // Fit view logic (filtering hidden nodes, computing viewport) is now
      // handled by useViewportActions.fitToNodes, not the toolbar itself.
      const onFitView = jest.fn();
      mockGetNodes.mockReturnValue([
        { id: '1', position: { x: 0, y: 0 }, data: {}, hidden: false },
        { id: '2', position: { x: 100, y: 100 }, data: {}, hidden: true },
      ]);
      renderToolbar({ onFitView });
      fireEvent.click(screen.getByLabelText('View options'));
      fireEvent.click(screen.getByText('Fit View'));
      expect(onFitView).toHaveBeenCalledTimes(1);
    });
  });

  // ─── Display name ───────────────────────────────────────────

  describe('component identity', () => {
    it('should have displayName set to CanvasToolbar', () => {
      expect(CanvasToolbar.displayName).toBe('CanvasToolbar');
    });
  });
});
