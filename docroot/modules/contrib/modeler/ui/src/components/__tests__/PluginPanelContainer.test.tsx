import React from 'react';
import { act, render, screen, fireEvent } from '@testing-library/react';
import fs from 'fs';
import path from 'path';
import PluginPanelSlot, { PluginPanel, resetFloatingPanelPositions } from '../PluginPanelContainer';
import type { RegisteredPanel, ModelerPluginApi } from '../../types/pluginApi';

// ── Mocks ─────────────────────────────────────────────────────────────

// Mock react-icons
jest.mock('react-icons/fi', () => ({
  FiMove: (props: any) => <span data-testid="fi-move" {...props} />,
}));

// Mock PanelErrorBoundary
jest.mock('../PanelErrorBoundary', () => {
  const MockPEB = ({ children, panelName, className }: any) => (
    <div data-testid={`error-boundary-${panelName}`} className={className}>
      {children}
    </div>
  );
  MockPEB.displayName = 'MockPanelErrorBoundary';
  return { __esModule: true, default: MockPEB };
});

// Mock usePanelResize
const mockStartResize = jest.fn();
let mockUsePanelResizeArgs: any = null;
jest.mock('../../hooks/usePanelResize', () => ({
  usePanelResize: jest.fn((args: any) => {
    mockUsePanelResizeArgs = args;
    return { startResize: mockStartResize };
  }),
}));

const mockStartHeightResize = jest.fn();
const mockResizeByKeyboard = jest.fn();
let mockUseHeightResizeArgs: any = null;
let mockMaximumHeight = 552;
jest.mock('../../hooks/useFloatingPanelHeightResize', () => ({
  getMaximumFloatingPanelHeight: jest.fn(() => mockMaximumHeight),
  clampFloatingPanelHeight: jest.fn((_el, height) => height),
  useFloatingPanelHeightResize: jest.fn((args: any) => {
    mockUseHeightResizeArgs = args;
    return {
      startResize: mockStartHeightResize,
      resizeByKeyboard: mockResizeByKeyboard,
    };
  }),
}));

// Mock translation
jest.mock('../../utils/translation', () => ({
  t: (str: string, args?: Record<string, string>) => {
    if (args) {
      let result = str;
      for (const [key, value] of Object.entries(args)) {
        result = result.replace(key, value);
      }
      return result;
    }
    return str;
  },
}));

// ── Helpers ───────────────────────────────────────────────────────────

const mockRender = jest.fn();
const mockDestroy = jest.fn();
const mockOnResize = jest.fn();

function createMockPanel(overrides: Partial<RegisteredPanel> = {}): RegisteredPanel {
  return {
    id: 'test-panel',
    label: 'Test Panel',
    render: mockRender,
    destroy: mockDestroy,
    onResize: mockOnResize,
    position: 'right',
    weight: 0,
    width: 320,
    floating: false,
    ...overrides,
  };
}

const mockApi = {} as ModelerPluginApi;

/**
 * Give every element a fixed offset box.  jsdom performs no layout, so the
 * clamping maths would otherwise run against a zero-sized panel.
 */
const originalOffsetDescriptors = {
  offsetWidth: Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'offsetWidth'),
  offsetHeight: Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'offsetHeight'),
};

function stubElementSize(width: number, height: number): void {
  Object.defineProperty(HTMLElement.prototype, 'offsetWidth', {
    configurable: true,
    value: width,
  });
  Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
    configurable: true,
    value: height,
  });
}

function restoreElementSize(): void {
  for (const [name, descriptor] of Object.entries(originalOffsetDescriptors)) {
    if (descriptor) {
      Object.defineProperty(HTMLElement.prototype, name, descriptor);
    } else {
      delete (HTMLElement.prototype as unknown as Record<string, unknown>)[name];
    }
  }
}

// ── Tests ─────────────────────────────────────────────────────────────

describe('PluginPanelContainer', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUsePanelResizeArgs = null;
    mockUseHeightResizeArgs = null;
    mockMaximumHeight = 552;
    resetFloatingPanelPositions();
  });

  // ── PluginPanel ───────────────────────────────────────────────────

  describe('PluginPanel', () => {
    describe('rendering', () => {
      it('renders panel content area and header', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel-content')).toBeInTheDocument();
        expect(container.querySelector('.plugin-panel-header')).toBeInTheDocument();
      });

      it('sets data-plugin-panel-id attribute', () => {
        const panel = createMockPanel({ id: 'my-custom-panel' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('[data-plugin-panel-id="my-custom-panel"]')).toBeInTheDocument();
      });

      it('applies position class', () => {
        const panel = createMockPanel({ position: 'right' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel--right')).toBeInTheDocument();
      });

      it('applies left position class', () => {
        const panel = createMockPanel({ position: 'left' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel--left')).toBeInTheDocument();
      });

      it('sets initial width from panel.width', () => {
        const panel = createMockPanel({ width: 400 });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const panelEl = container.querySelector('.plugin-panel') as HTMLElement;
        expect(panelEl.style.width).toBe('400px');
      });

      it('renders content region with aria-label', () => {
        const panel = createMockPanel({ label: 'Analytics' });
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(screen.getByRole('region', { name: 'Analytics' })).toBeInTheDocument();
      });
    });

    // ── Header (replaces the removed collapse tab) ───────────────────

    describe('header', () => {
      it('shows the panel label', () => {
        const panel = createMockPanel({ label: 'Analytics' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel-title')?.textContent).toBe('Analytics');
      });

      it('is non-interactive for a docked panel', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const header = container.querySelector('.plugin-panel-header') as HTMLElement;
        expect(header.tagName).toBe('DIV');
        expect(header.querySelector('button')).toBeNull();
        expect(header.getAttribute('role')).toBeNull();
        expect(header.getAttribute('tabindex')).toBeNull();
      });

      it('no longer renders a collapse control', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel-collapse-tab')).toBeNull();
        expect(container.querySelector('.plugin-panel-collapse-label')).toBeNull();
        expect(container.querySelector('.plugin-panel.collapsed')).toBeNull();
        expect(container.querySelector('[aria-expanded]')).toBeNull();
      });
    });

    describe('mount lifecycle', () => {
      it('calls render() on mount with container element and API', () => {
        const panel = createMockPanel();
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockRender).toHaveBeenCalledTimes(1);
        expect(mockRender).toHaveBeenCalledWith(
          expect.any(HTMLDivElement),
          mockApi
        );
      });

      it('handles render() error gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        const failingRender = jest.fn(() => {
          throw new Error('Render boom');
        });
        const panel = createMockPanel({ render: failingRender });

        // Should not throw
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin panel "test-panel" render() failed:',
          expect.any(Error)
        );
        errorSpy.mockRestore();
      });

    });

    describe('unmount lifecycle', () => {
      it('calls destroy() on unmount', () => {
        const panel = createMockPanel();
        const { unmount } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockDestroy).not.toHaveBeenCalled();
        unmount();
        expect(mockDestroy).toHaveBeenCalledTimes(1);
        expect(mockDestroy).toHaveBeenCalledWith(expect.any(HTMLDivElement));
      });

      it('handles destroy() error gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        const failingDestroy = jest.fn(() => {
          throw new Error('Destroy boom');
        });
        const panel = createMockPanel({ destroy: failingDestroy });
        const { unmount } = render(<PluginPanel panel={panel} api={mockApi} />);

        unmount();

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin panel "test-panel" destroy() failed:',
          expect.any(Error)
        );
        errorSpy.mockRestore();
      });

      it('handles missing destroy function gracefully', () => {
        const panel = createMockPanel({ destroy: undefined });
        const { unmount } = render(<PluginPanel panel={panel} api={mockApi} />);

        // Should not throw when destroy is undefined
        expect(() => unmount()).not.toThrow();
      });
    });

    describe('resize handle', () => {
      it('always shows the resize handle', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel-resize-handle')).toBeInTheDocument();
      });

      it('resize handle has correct position class for right panel', () => {
        const panel = createMockPanel({ position: 'right' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel-resize-handle--left')).toBeInTheDocument();
      });

      it('resize handle has correct position class for left panel', () => {
        const panel = createMockPanel({ position: 'left' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel-resize-handle--right')).toBeInTheDocument();
      });

      it('triggers startResize on mousedown', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const handle = container.querySelector('.plugin-panel-resize-handle') as HTMLElement;
        fireEvent.mouseDown(handle);

        expect(mockStartResize).toHaveBeenCalledTimes(1);
      });

      it('resize handle has correct a11y attributes', () => {
        const panel = createMockPanel({ label: 'My Panel' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const handle = container.querySelector('.plugin-panel-resize-handle') as HTMLElement;
        expect(handle.getAttribute('role')).toBe('separator');
        expect(handle.getAttribute('aria-orientation')).toBe('vertical');
        expect(handle.getAttribute('aria-label')).toBe('Resize My Panel panel');
        expect(handle.getAttribute('tabindex')).toBe('0');
      });
    });

    describe('usePanelResize integration', () => {
      it('passes correct direction for right panel', () => {
        const panel = createMockPanel({ position: 'right' });
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockUsePanelResizeArgs.direction).toBe('left');
      });

      it('passes correct direction for left panel', () => {
        const panel = createMockPanel({ position: 'left' });
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockUsePanelResizeArgs.direction).toBe('right');
      });

      it('passes panel width and dimension constants', () => {
        const panel = createMockPanel({ width: 350 });
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockUsePanelResizeArgs.panelWidth).toBe(350);
        expect(mockUsePanelResizeArgs.minWidth).toBe(200); // PLUGIN_PANEL.MIN_WIDTH
        expect(mockUsePanelResizeArgs.maxWidth).toBe(600); // PLUGIN_PANEL.MAX_WIDTH
      });
    });

    describe('onResize notification', () => {
      it('calls onResize when isResizing changes to false', () => {
        // The onResize effect fires when isResizing is false (initial render)
        // and containerRef.current exists. On mount, the effect fires after render.
        const panel = createMockPanel();
        render(<PluginPanel panel={panel} api={mockApi} />);

        // onResize is called on the initial effect run because isResizing starts as false
        expect(mockOnResize).toHaveBeenCalled();
      });

      it('calls onResize with element dimensions', () => {
        const panel = createMockPanel();
        render(<PluginPanel panel={panel} api={mockApi} />);

        // getBoundingClientRect returns 0s in jsdom, but the call should happen
        expect(mockOnResize).toHaveBeenCalledWith(
          expect.any(Number),
          expect.any(Number)
        );
      });

      it('handles onResize error gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        const failingOnResize = jest.fn(() => {
          throw new Error('Resize boom');
        });
        const panel = createMockPanel({ onResize: failingOnResize });

        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin panel "test-panel" onResize() failed:',
          expect.any(Error)
        );
        errorSpy.mockRestore();
      });

      it('does not call onResize when panel has no onResize callback', () => {
        const panel = createMockPanel({ onResize: undefined });
        // Should not throw
        expect(() => render(<PluginPanel panel={panel} api={mockApi} />)).not.toThrow();
      });
    });

    describe('CSS classes', () => {
      it('includes position class', () => {
        const panel = createMockPanel({ position: 'right' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const el = container.querySelector('.plugin-panel') as HTMLElement;
        expect(el.classList.contains('plugin-panel--right')).toBe(true);
      });

      it('does not have is-resizing class by default', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const el = container.querySelector('.plugin-panel') as HTMLElement;
        expect(el.classList.contains('is-resizing')).toBe(false);
      });

      it('does not have is-dragging class by default', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const el = container.querySelector('.plugin-panel') as HTMLElement;
        expect(el.classList.contains('is-dragging')).toBe(false);
      });
    });

    // ── Floating panels ──────────────────────────────────────────────

    describe('floating panels', () => {
      // Bounds fall back to the jsdom viewport (1024x768) because jsdom never
      // reports an offsetParent.  With a 300x200 panel and a 16px margin the
      // reachable range is x: 16..708, y: 16..552.
      const PANEL_W = 300;
      const PANEL_H = 200;
      const MAX_X = 1024 - PANEL_W - 16; // 708
      const MAX_Y = 768 - PANEL_H - 16;  // 552

      beforeEach(() => stubElementSize(PANEL_W, PANEL_H));
      afterEach(() => restoreElementSize());

      const floatingPanel = (overrides: Partial<RegisteredPanel> = {}) =>
        createMockPanel({ floating: true, ...overrides });

      function renderFloating(overrides: Partial<RegisteredPanel> = {}) {
        const result = render(<PluginPanel panel={floatingPanel(overrides)} api={mockApi} />);
        return {
          ...result,
          panelEl: result.container.querySelector('.plugin-panel') as HTMLElement,
          header: result.container.querySelector('.plugin-panel-header') as HTMLElement,
        };
      }

      describe('detached rendering', () => {
        it('adds the floating modifier class', () => {
          const { panelEl } = renderFloating();
          expect(panelEl.classList.contains('plugin-panel--floating')).toBe(true);
        });

        it('positions the panel with inline left/top', () => {
          const { panelEl } = renderFloating({ position: 'left' });
          expect(panelEl.style.left).toBe('16px');
          expect(panelEl.style.top).toBe('16px');
        });

        it('leaves a docked panel unpositioned and unmarked', () => {
          const { container } = render(
            <PluginPanel panel={createMockPanel({ floating: false })} api={mockApi} />,
          );

          const panelEl = container.querySelector('.plugin-panel') as HTMLElement;
          expect(panelEl.classList.contains('plugin-panel--floating')).toBe(false);
          expect(panelEl.style.left).toBe('');
          expect(panelEl.style.top).toBe('');
          expect(container.querySelector('.plugin-panel-drag-handle')).toBeNull();
        });

        it('treats an undefined floating flag as docked', () => {
          const panel = createMockPanel();
          delete (panel as Partial<RegisteredPanel>).floating;
          const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

          expect(container.querySelector('.plugin-panel--floating')).toBeNull();
        });

        it('still keeps the position modifier class', () => {
          const { panelEl } = renderFloating({ position: 'bottom' });
          expect(panelEl.classList.contains('plugin-panel--bottom')).toBe(true);
        });
      });

      describe('default position', () => {
        it('anchors a left panel to the top-left corner', () => {
          const { panelEl } = renderFloating({ position: 'left' });
          expect(panelEl.style.left).toBe('16px');
          expect(panelEl.style.top).toBe('16px');
        });

        it('anchors a right panel to the top-right corner', () => {
          const { panelEl } = renderFloating({ position: 'right' });
          expect(panelEl.style.left).toBe(`${MAX_X}px`);
          expect(panelEl.style.top).toBe('16px');
        });

        it('centers a bottom panel horizontally', () => {
          const { panelEl } = renderFloating({ position: 'bottom' });
          expect(panelEl.style.left).toBe(`${(1024 - PANEL_W) / 2}px`);
          expect(panelEl.style.top).toBe('16px');
        });

        /**
         * A floating panel is positioned against `.workflow-modeler`, which
         * also contains the 45px toolbar, and it outranks the toolbar on
         * z-index.  Anchoring at the bare top margin would therefore drop
         * every newly shown panel straight on top of the toolbar controls.
         * The slot is still in normal flow, so its offsetTop is the top of
         * the region the plugin actually asked for.
         */
        it('starts below the slot rather than on top of the toolbar', () => {
          const slot = document.createElement('div');
          Object.defineProperty(slot, 'offsetTop', { configurable: true, value: 45 });
          document.body.appendChild(slot);

          try {
            const { container } = render(
              <PluginPanel panel={floatingPanel({ position: 'left' })} api={mockApi} />,
              { container: slot },
            );
            const panelEl = container.querySelector('.plugin-panel') as HTMLElement;

            expect(panelEl.style.top).toBe('61px');
          } finally {
            slot.remove();
          }
        });

        it('anchors a bottom panel to the bottom of its slot', () => {
          // A bottom slot sits below the canvas; its offsetTop is near the
          // foot of the modeler, and clamping pulls the panel fully back in.
          const slot = document.createElement('div');
          Object.defineProperty(slot, 'offsetTop', { configurable: true, value: 700 });
          document.body.appendChild(slot);

          try {
            const { container } = render(
              <PluginPanel panel={floatingPanel({ position: 'bottom' })} api={mockApi} />,
              { container: slot },
            );
            const panelEl = container.querySelector('.plugin-panel') as HTMLElement;

            // 700 + 16 = 716, clamped to MAX_Y (552).
            expect(panelEl.style.top).toBe(`${MAX_Y}px`);
          } finally {
            slot.remove();
          }
        });
      });

      /**
       * The stylesheet can only cap a floating panel against the whole
       * containing block. A panel parked below the top margin would still be
       * allowed to grow past the bottom edge, putting the foot of its
       * scrollable content out of reach — so the cap has to follow the panel.
       */
      describe('height cap', () => {
        it('leaves room below the panel for the bottom margin', () => {
          const { panelEl } = renderFloating({ position: 'left' });

          // Placed at y=16, so it may occupy everything but 16px top + 16px bottom.
          expect(panelEl.style.maxHeight).toBe('calc(100% - 32px)');
        });

        it('tightens the cap as the panel moves down', () => {
          const { panelEl, header } = renderFloating({ position: 'left' });

          fireEvent.mouseDown(header, { clientX: 0, clientY: 0 });
          fireEvent.mouseMove(document, { clientX: 0, clientY: 200 });
          fireEvent.mouseUp(document);

          // y = 16 + 200 = 216, so 216 above + 16 below.
          expect(panelEl.style.top).toBe('216px');
          expect(panelEl.style.maxHeight).toBe('calc(100% - 232px)');
        });

        it('leaves a docked panel uncapped', () => {
          const { container } = render(
            <PluginPanel panel={createMockPanel({ floating: false })} api={mockApi} />,
          );
          const panelEl = container.querySelector('.plugin-panel') as HTMLElement;

          expect(panelEl.style.maxHeight).toBe('');
        });
      });

      describe('mouse dragging', () => {
        it('moves the panel by the pointer delta', () => {
          const { panelEl, header } = renderFloating({ position: 'left' });

          fireEvent.mouseDown(header, { clientX: 100, clientY: 100 });
          fireEvent.mouseMove(document, { clientX: 150, clientY: 180 });

          expect(panelEl.style.left).toBe('66px');
          expect(panelEl.style.top).toBe('96px');

          fireEvent.mouseUp(document);
        });

        it('flags the drag while the pointer is down', () => {
          const { panelEl, header } = renderFloating({ position: 'left' });

          fireEvent.mouseDown(header, { clientX: 100, clientY: 100 });
          expect(panelEl.classList.contains('is-dragging')).toBe(true);

          fireEvent.mouseUp(document);
          expect(panelEl.classList.contains('is-dragging')).toBe(false);
        });

        it('clamps the panel at the bottom-right edge of the viewport', () => {
          const { panelEl, header } = renderFloating({ position: 'left' });

          fireEvent.mouseDown(header, { clientX: 0, clientY: 0 });
          fireEvent.mouseMove(document, { clientX: 99999, clientY: 99999 });

          expect(panelEl.style.left).toBe(`${MAX_X}px`);
          expect(panelEl.style.top).toBe(`${MAX_Y}px`);

          fireEvent.mouseUp(document);
        });

        it('clamps the panel at the top-left edge of the viewport', () => {
          const { panelEl, header } = renderFloating({ position: 'right' });

          fireEvent.mouseDown(header, { clientX: 0, clientY: 0 });
          fireEvent.mouseMove(document, { clientX: -99999, clientY: -99999 });

          expect(panelEl.style.left).toBe('16px');
          expect(panelEl.style.top).toBe('16px');

          fireEvent.mouseUp(document);
        });

        it('does not start a drag from a docked panel header', () => {
          const { container } = render(
            <PluginPanel panel={createMockPanel({ floating: false })} api={mockApi} />,
          );
          const panelEl = container.querySelector('.plugin-panel') as HTMLElement;
          const header = container.querySelector('.plugin-panel-header') as HTMLElement;

          fireEvent.mouseDown(header, { clientX: 100, clientY: 100 });
          fireEvent.mouseMove(document, { clientX: 400, clientY: 400 });

          expect(panelEl.classList.contains('is-dragging')).toBe(false);
          expect(panelEl.style.left).toBe('');
          expect(panelEl.style.top).toBe('');
        });

        it('does not leak document listeners when unmounted mid-drag', () => {
          const removeSpy = jest.spyOn(document, 'removeEventListener');
          const { header, unmount } = renderFloating({ position: 'left' });

          fireEvent.mouseDown(header, { clientX: 100, clientY: 100 });
          removeSpy.mockClear();

          // No mouseup — the panel is unregistered while the pointer is down.
          unmount();

          expect(removeSpy).toHaveBeenCalledWith('mousemove', expect.any(Function));
          expect(removeSpy).toHaveBeenCalledWith('mouseup', expect.any(Function));
          removeSpy.mockRestore();
        });
      });

      describe('keyboard move handle', () => {
        it('renders a labelled button only for floating panels', () => {
          renderFloating({ label: 'Analytics' });

          const handle = screen.getByRole('button', { name: 'Move Analytics panel' });
          expect(handle).toBeInTheDocument();
          expect(handle.getAttribute('type')).toBe('button');
          expect(handle.getAttribute('title')).toBe('Drag to move, or press the arrow keys');
        });

        it('hides the move icon from assistive technology', () => {
          const { container } = renderFloating();
          expect(container.querySelector('[data-testid="fi-move"]')?.getAttribute('aria-hidden'))
            .toBe('true');
        });

        it('nudges the panel right on ArrowRight', () => {
          const { panelEl, container } = renderFloating({ position: 'left' });
          const handle = container.querySelector('.plugin-panel-drag-handle') as HTMLElement;

          fireEvent.keyDown(handle, { key: 'ArrowRight' });

          expect(panelEl.style.left).toBe('26px');
          expect(panelEl.style.top).toBe('16px');
        });

        it('nudges the panel down on ArrowDown', () => {
          const { panelEl, container } = renderFloating({ position: 'left' });
          const handle = container.querySelector('.plugin-panel-drag-handle') as HTMLElement;

          fireEvent.keyDown(handle, { key: 'ArrowDown' });

          expect(panelEl.style.left).toBe('16px');
          expect(panelEl.style.top).toBe('26px');
        });

        it('takes a bigger step when Shift is held', () => {
          const { panelEl, container } = renderFloating({ position: 'left' });
          const handle = container.querySelector('.plugin-panel-drag-handle') as HTMLElement;

          fireEvent.keyDown(handle, { key: 'ArrowRight', shiftKey: true });

          expect(panelEl.style.left).toBe('66px');
        });

        it('clamps keyboard moves too', () => {
          const { panelEl, container } = renderFloating({ position: 'left' });
          const handle = container.querySelector('.plugin-panel-drag-handle') as HTMLElement;

          // Already pinned to the left/top margin — these must not go negative.
          fireEvent.keyDown(handle, { key: 'ArrowLeft' });
          fireEvent.keyDown(handle, { key: 'ArrowUp' });

          expect(panelEl.style.left).toBe('16px');
          expect(panelEl.style.top).toBe('16px');
        });

        it('ignores keys other than the arrows', () => {
          const { panelEl, container } = renderFloating({ position: 'left' });
          const handle = container.querySelector('.plugin-panel-drag-handle') as HTMLElement;

          fireEvent.keyDown(handle, { key: 'Enter' });
          fireEvent.keyDown(handle, { key: 'a' });

          expect(panelEl.style.left).toBe('16px');
          expect(panelEl.style.top).toBe('16px');
        });
      });

      describe('resizing', () => {
        it('stays available, growing from the right edge', () => {
          const { container } = renderFloating({ position: 'right' });

          expect(container.querySelector('.plugin-panel-resize-handle--right')).toBeInTheDocument();
          expect(mockUsePanelResizeArgs.direction).toBe('right');
        });

        it('starts with automatic height and renders a bottom handle', () => {
          const { panelEl, container } = renderFloating({ label: 'Analytics' });

          expect(panelEl.style.height).toBe('');
          expect(panelEl).toHaveClass('is-auto-height');
          expect(panelEl).not.toHaveClass('has-manual-height');
          const handle = container.querySelector('.plugin-panel-height-resize-handle') as HTMLElement;
          expect(handle).toBeInTheDocument();
          expect(handle.getAttribute('role')).toBe('separator');
          expect(handle.getAttribute('aria-orientation')).toBe('horizontal');
          expect(handle.getAttribute('aria-label')).toBe('Resize Analytics panel height');
          expect(handle.getAttribute('aria-valuemin')).toBe('120');
          expect(handle.getAttribute('aria-valuenow')).toBe('200');
          expect(handle.getAttribute('aria-valuemax')).toBe('552');
          expect(handle.getAttribute('tabindex')).toBe('0');
        });

        it('keeps accessible height values coherent below the usable minimum', () => {
          mockMaximumHeight = 80;
          const { container } = renderFloating();
          const handle = container.querySelector('.plugin-panel-height-resize-handle') as HTMLElement;

          expect(handle.getAttribute('aria-valuemin')).toBe('80');
          expect(handle.getAttribute('aria-valuenow')).toBe('80');
          expect(handle.getAttribute('aria-valuemax')).toBe('80');
        });

        it('marks the panel as manually constrained after height interaction', () => {
          const { panelEl } = renderFloating();

          act(() => mockUseHeightResizeArgs.setPanelHeight(300));

          expect(panelEl).toHaveClass('has-manual-height');
          expect(panelEl).not.toHaveClass('is-auto-height');
          expect(panelEl.style.height).toBe('300px');
        });

        it('does not render the vertical resize handle for docked panels', () => {
          const { container } = render(
            <PluginPanel panel={createMockPanel({ floating: false })} api={mockApi} />,
          );
          expect(container.querySelector('.plugin-panel-height-resize-handle')).toBeNull();
          expect(mockUseHeightResizeArgs.enabled).toBe(false);
        });

        it('starts pointer resizing from the bottom handle', () => {
          const { container } = renderFloating();
          fireEvent.pointerDown(container.querySelector('.plugin-panel-height-resize-handle')!);
          expect(mockStartHeightResize).toHaveBeenCalledTimes(1);
        });

        it('resizes with Up and Down, using a larger Shift step', () => {
          const { container } = renderFloating();
          const handle = container.querySelector('.plugin-panel-height-resize-handle')!;

          fireEvent.keyDown(handle, { key: 'ArrowDown' });
          fireEvent.keyDown(handle, { key: 'ArrowUp', shiftKey: true });

          expect(mockResizeByKeyboard).toHaveBeenNthCalledWith(1, 10);
          expect(mockResizeByKeyboard).toHaveBeenNthCalledWith(2, -50);
        });

        it('notifies the plugin after vertical resizing completes', () => {
          renderFloating();
          mockOnResize.mockClear();

          act(() => mockUseHeightResizeArgs.onResizeEnd());

          expect(mockOnResize).toHaveBeenCalledWith(expect.any(Number), expect.any(Number));
        });
      });

      describe('position persistence', () => {
        it('restores the position after an unregister/re-register cycle', () => {
          const { header, unmount } = renderFloating({ position: 'left' });

          fireEvent.mouseDown(header, { clientX: 100, clientY: 100 });
          fireEvent.mouseMove(document, { clientX: 300, clientY: 250 });
          fireEvent.mouseUp(document);
          unmount();

          const { panelEl } = renderFloating({ position: 'left' });
          expect(panelEl.style.left).toBe('216px');
          expect(panelEl.style.top).toBe('166px');
        });

        it('keeps positions separate per panel id', () => {
          const { header, unmount } = renderFloating({ id: 'moved', position: 'left' });
          fireEvent.mouseDown(header, { clientX: 0, clientY: 0 });
          fireEvent.mouseMove(document, { clientX: 100, clientY: 100 });
          fireEvent.mouseUp(document);
          unmount();

          const { panelEl } = renderFloating({ id: 'untouched', position: 'left' });
          expect(panelEl.style.left).toBe('16px');
        });

        it('falls back to the default once the positions are reset', () => {
          const { header, unmount } = renderFloating({ position: 'left' });
          fireEvent.mouseDown(header, { clientX: 100, clientY: 100 });
          fireEvent.mouseMove(document, { clientX: 300, clientY: 250 });
          fireEvent.mouseUp(document);
          unmount();

          resetFloatingPanelPositions();

          const { panelEl } = renderFloating({ position: 'left' });
          expect(panelEl.style.left).toBe('16px');
          expect(panelEl.style.top).toBe('16px');
        });

        it('re-clamps a remembered position that no longer fits', () => {
          const { header, unmount } = renderFloating({ position: 'left' });
          fireEvent.mouseDown(header, { clientX: 0, clientY: 0 });
          fireEvent.mouseMove(document, { clientX: 99999, clientY: 99999 });
          fireEvent.mouseUp(document);
          unmount();

          // The panel comes back twice as wide as the viewport.
          stubElementSize(4000, 4000);
          const { panelEl } = renderFloating({ position: 'left' });
          expect(panelEl.style.left).toBe('16px');
          expect(panelEl.style.top).toBe('16px');
        });
      });

      describe('window resize', () => {
        it('pulls a stranded panel back into view', () => {
          const { panelEl, header } = renderFloating({ position: 'left' });

          fireEvent.mouseDown(header, { clientX: 0, clientY: 0 });
          fireEvent.mouseMove(document, { clientX: 99999, clientY: 99999 });
          fireEvent.mouseUp(document);
          expect(panelEl.style.left).toBe(`${MAX_X}px`);

          // Shrink the window, then tell the app about it.
          const originalWidth = window.innerWidth;
          const originalHeight = window.innerHeight;
          try {
            Object.defineProperty(window, 'innerWidth', { configurable: true, value: 500 });
            Object.defineProperty(window, 'innerHeight', { configurable: true, value: 400 });
            fireEvent(window, new Event('resize'));

            // 500 - 300 - 16 = 184 ; 400 - 200 - 16 = 184
            expect(panelEl.style.left).toBe('184px');
            expect(panelEl.style.top).toBe('184px');
          } finally {
            Object.defineProperty(window, 'innerWidth', { configurable: true, value: originalWidth });
            Object.defineProperty(window, 'innerHeight', { configurable: true, value: originalHeight });
          }
        });

        it('does not listen for resizes on a docked panel', () => {
          const addSpy = jest.spyOn(window, 'addEventListener');
          render(<PluginPanel panel={createMockPanel({ floating: false })} api={mockApi} />);

          expect(addSpy).not.toHaveBeenCalledWith('resize', expect.any(Function));
          addSpy.mockRestore();
        });
      });
    });
  });

  // ── PluginPanelSlot ─────────────────────────────────────────────────

  describe('PluginPanelSlot', () => {
    it('returns null when panels array is empty', () => {
      const { container } = render(
        <PluginPanelSlot panels={[]} api={mockApi} position="right" />
      );

      expect(container.innerHTML).toBe('');
    });

    it('renders panels wrapped in error boundaries', () => {
      const panels = [
        createMockPanel({ id: 'panel-a', label: 'Panel A' }),
        createMockPanel({ id: 'panel-b', label: 'Panel B' }),
      ];

      render(<PluginPanelSlot panels={panels} api={mockApi} position="right" />);

      expect(screen.getByTestId('error-boundary-Panel A')).toBeInTheDocument();
      expect(screen.getByTestId('error-boundary-Panel B')).toBeInTheDocument();
    });

    it('renders a panel for each item in the array', () => {
      const panels = [
        createMockPanel({ id: 'p1', label: 'P1' }),
        createMockPanel({ id: 'p2', label: 'P2' }),
        createMockPanel({ id: 'p3', label: 'P3' }),
      ];

      const { container } = render(
        <PluginPanelSlot panels={panels} api={mockApi} position="right" />
      );

      const panelElements = container.querySelectorAll('[data-plugin-panel-id]');
      expect(panelElements).toHaveLength(3);
    });

    it('applies position class to slot container', () => {
      const panels = [createMockPanel()];
      const { container } = render(
        <PluginPanelSlot panels={panels} api={mockApi} position="left" />
      );

      expect(container.querySelector('.plugin-panel-slot--left')).toBeInTheDocument();
    });

    it('applies plugin-panel-slot base class', () => {
      const panels = [createMockPanel()];
      const { container } = render(
        <PluginPanelSlot panels={panels} api={mockApi} position="right" />
      );

      expect(container.querySelector('.plugin-panel-slot')).toBeInTheDocument();
    });

    it('passes plugin-panel-error className to error boundary', () => {
      const panels = [createMockPanel({ label: 'Test Panel' })];
      render(<PluginPanelSlot panels={panels} api={mockApi} position="right" />);

      const boundary = screen.getByTestId('error-boundary-Test Panel');
      expect(boundary.className).toBe('plugin-panel-error');
    });

    it('renders single panel correctly', () => {
      const panels = [createMockPanel({ id: 'solo', label: 'Solo Panel' })];
      const { container } = render(
        <PluginPanelSlot panels={panels} api={mockApi} position="right" />
      );

      expect(container.querySelector('[data-plugin-panel-id="solo"]')).toBeInTheDocument();
      expect(screen.getByTestId('error-boundary-Solo Panel')).toBeInTheDocument();
    });

    it('mixes docked and floating panels in the same slot', () => {
      const panels = [
        createMockPanel({ id: 'docked', label: 'Docked' }),
        createMockPanel({ id: 'floater', label: 'Floater', floating: true }),
      ];

      const { container } = render(
        <PluginPanelSlot panels={panels} api={mockApi} position="right" />
      );

      expect(
        container.querySelector('[data-plugin-panel-id="docked"]')!
          .classList.contains('plugin-panel--floating'),
      ).toBe(false);
      expect(
        container.querySelector('[data-plugin-panel-id="floater"]')!
          .classList.contains('plugin-panel--floating'),
      ).toBe(true);
    });
  });

  // ── Stylesheet contract ─────────────────────────────────────────────

  /**
   * WHAT THESE TESTS PROVE: that the stylesheet still carries the specific
   * declarations the panel layout depends on — a bounded docked panel, a
   * scrollable content area, and a detached floating panel.
   *
   * WHAT THEY DO NOT PROVE: that anything actually scrolls or floats. jsdom
   * performs no layout, so no unit test can assert real overflow or
   * positioning. That must be checked in a browser.
   */
  describe('stylesheet contract', () => {
    const css = fs.readFileSync(
      path.resolve(__dirname, '../../styles/modeler.css'),
      'utf8',
    );

    /** Strip comments and split the stylesheet into { selector, body } rules. */
    const rules = css
      .replace(/\/\*[\s\S]*?\*\//g, '')
      .split('}')
      .map((chunk) => {
        const parts = chunk.split('{');
        if (parts.length < 2) return null;
        return { selector: parts[0].trim(), body: parts[1].trim() };
      })
      .filter((r): r is { selector: string; body: string } => r !== null);

    const rulesFor = (name: string) =>
      rules.filter((r) => r.selector.split(',').map((s) => s.trim()).includes(name));

    it('lets a docked side panel shrink so its content can scroll', () => {
      // A flex item only shrinks below its content size with min-height: 0,
      // and the base .plugin-panel rule sets flex-shrink: 0. Without this
      // override the panel keeps its full content height, the content area's
      // flex: 1 has nothing to constrain it, and the overflow is clipped by
      // .workflow-modeler-content { overflow: hidden } instead of scrolling.
      for (const selector of ['.plugin-panel--left', '.plugin-panel--right']) {
        const [rule] = rulesFor(selector);
        expect(rule).toBeDefined();
        expect(rule.body).toMatch(/min-height:\s*0/);
        expect(rule.body).toMatch(/flex:\s*0\s+1\s+auto/);
      }
    });

    it('lets the slot itself shrink to the content row', () => {
      const [rule] = rulesFor('.plugin-panel-slot--left');
      expect(rule.body).toMatch(/min-height:\s*0/);
    });

    it('makes the content area the scroll container', () => {
      const [rule] = rulesFor('.plugin-panel-content');
      expect(rule.body).toMatch(/overflow:\s*auto/);
      expect(rule.body).toMatch(/min-height:\s*0/);
    });

    it('bounds a bottom panel against short viewports', () => {
      const [rule] = rulesFor('.plugin-panel--bottom');
      expect(rule.body).toMatch(/max-height:\s*min\(400px,\s*50vh\)/);
    });

    it('takes a floating panel out of flow so the slot reserves no space', () => {
      const [rule] = rulesFor('.plugin-panel--floating');
      expect(rule).toBeDefined();
      expect(rule.body).toMatch(/position:\s*absolute/);
      // The base .plugin-panel rule sets flex-shrink: 0; a floating panel is
      // not a flex item at all, and must be able to shrink to its cap.
      expect(rule.body).toMatch(/min-height:\s*0/);
      // Elevation must come from a theme variable so dark mode still works.
      expect(rule.body).toMatch(/box-shadow:\s*var\(--modeler-shadow-lg\)/);
    });

    it('leaves the floating height cap to the component', () => {
      // It depends on the panel's current y offset, so a static rule here
      // would silently fight the inline style. See PluginPanelContainer.
      const [rule] = rulesFor('.plugin-panel--floating');
      expect(rule.body).not.toMatch(/max-height/);
    });

    it('styles the floating bottom resize handle and its interaction states', () => {
      const [rule] = rulesFor('.plugin-panel-height-resize-handle');
      expect(rule.body).toMatch(/bottom:\s*0/);
      expect(rule.body).toMatch(/cursor:\s*row-resize/);
      expect(rule.body).toMatch(/touch-action:\s*none/);
      expect(rulesFor('.plugin-panel-height-resize-handle:hover')).toHaveLength(1);
      expect(rulesFor('.plugin-panel-height-resize-handle:focus-visible')).toHaveLength(1);
      expect(rulesFor('.plugin-panel.is-height-resizing .plugin-panel-height-resize-handle'))
        .toHaveLength(1);
    });

    it('prevents automatic floating content from flex-growing to max-height', () => {
      const [rule] = rulesFor('.plugin-panel--floating.is-auto-height .plugin-panel-content');
      expect(rule).toBeDefined();
      expect(rule.body).toMatch(/flex:\s*0\s+1\s+auto/);
    });

    it('no longer styles the removed collapse controls', () => {
      expect(css).not.toMatch(/\.plugin-panel-collapse-tab/);
      expect(css).not.toMatch(/\.plugin-panel-collapse-label/);
      expect(css).not.toMatch(/\.plugin-panel\.collapsed/);
    });
  });
});
