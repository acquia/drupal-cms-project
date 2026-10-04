import React from 'react';
import { render, screen } from '@testing-library/react';
import PluginToolbarWidgetSlot, { PluginToolbarWidget } from '../PluginToolbarWidget';
import type { RegisteredWidget, ModelerPluginApi } from '../../types/pluginApi';

// ── Helpers ───────────────────────────────────────────────────────────

const mockRender = jest.fn();
const mockDestroy = jest.fn();

function createMockWidget(overrides: Partial<RegisteredWidget> = {}): RegisteredWidget {
  return {
    id: 'test-widget',
    label: 'Test Widget',
    render: mockRender,
    destroy: mockDestroy,
    position: 'right',
    weight: 0,
    ...overrides,
  };
}

const mockApi = {} as ModelerPluginApi;

// ── Tests ─────────────────────────────────────────────────────────────

describe('PluginToolbarWidget', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  // ── PluginToolbarWidget ───────────────────────────────────────────

  describe('PluginToolbarWidget', () => {
    describe('rendering', () => {
      it('renders a div with className "plugin-toolbar-widget"', () => {
        const widget = createMockWidget();
        const { container } = render(
          <PluginToolbarWidget widget={widget} api={mockApi} />,
        );

        expect(
          container.querySelector('.plugin-toolbar-widget'),
        ).toBeInTheDocument();
      });

      it('sets data-plugin-widget-id attribute from widget.id', () => {
        const widget = createMockWidget({ id: 'my-custom-widget' });
        const { container } = render(
          <PluginToolbarWidget widget={widget} api={mockApi} />,
        );

        expect(
          container.querySelector(
            '[data-plugin-widget-id="my-custom-widget"]',
          ),
        ).toBeInTheDocument();
      });

      it('sets role="group"', () => {
        const widget = createMockWidget({ label: 'Test Widget' });
        render(<PluginToolbarWidget widget={widget} api={mockApi} />);

        expect(
          screen.getByRole('group', { name: 'Test Widget' }),
        ).toBeInTheDocument();
      });

      it('sets aria-label from widget.label', () => {
        const widget = createMockWidget({ label: 'AI Toggle' });
        render(<PluginToolbarWidget widget={widget} api={mockApi} />);

        const group = screen.getByRole('group', { name: 'AI Toggle' });
        expect(group).toBeInTheDocument();
        expect(group.getAttribute('aria-label')).toBe('AI Toggle');
      });
    });

    describe('mount lifecycle', () => {
      it('calls render() on mount with container element and API', () => {
        const widget = createMockWidget();
        render(<PluginToolbarWidget widget={widget} api={mockApi} />);

        expect(mockRender).toHaveBeenCalledTimes(1);
        expect(mockRender).toHaveBeenCalledWith(
          expect.any(HTMLDivElement),
          mockApi,
        );
      });

      it('handles render() error gracefully', () => {
        const errorSpy = jest
          .spyOn(console, 'error')
          .mockImplementation(() => {});
        const failingRender = jest.fn(() => {
          throw new Error('Render boom');
        });
        const widget = createMockWidget({ render: failingRender });

        // Should not throw
        render(<PluginToolbarWidget widget={widget} api={mockApi} />);

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin widget "test-widget" render() failed:',
          expect.any(Error),
        );
        errorSpy.mockRestore();
      });
    });

    describe('unmount lifecycle', () => {
      it('calls destroy() on unmount', () => {
        const widget = createMockWidget();
        const { unmount } = render(
          <PluginToolbarWidget widget={widget} api={mockApi} />,
        );

        expect(mockDestroy).not.toHaveBeenCalled();
        unmount();
        expect(mockDestroy).toHaveBeenCalledTimes(1);
        expect(mockDestroy).toHaveBeenCalledWith(expect.any(HTMLDivElement));
      });

      it('handles destroy() error gracefully', () => {
        const errorSpy = jest
          .spyOn(console, 'error')
          .mockImplementation(() => {});
        const failingDestroy = jest.fn(() => {
          throw new Error('Destroy boom');
        });
        const widget = createMockWidget({ destroy: failingDestroy });
        const { unmount } = render(
          <PluginToolbarWidget widget={widget} api={mockApi} />,
        );

        unmount();

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin widget "test-widget" destroy() failed:',
          expect.any(Error),
        );
        errorSpy.mockRestore();
      });

      it('handles missing destroy function gracefully', () => {
        const widget = createMockWidget({ destroy: undefined });
        const { unmount } = render(
          <PluginToolbarWidget widget={widget} api={mockApi} />,
        );

        // Should not throw when destroy is undefined
        expect(() => unmount()).not.toThrow();
      });
    });
  });

  // ── PluginToolbarWidgetSlot ─────────────────────────────────────────

  describe('PluginToolbarWidgetSlot', () => {
    it('returns null when widgets array is empty', () => {
      const { container } = render(
        <PluginToolbarWidgetSlot widgets={[]} api={mockApi} />,
      );

      expect(container.innerHTML).toBe('');
    });

    it('renders a toolbar-separator when widgets are present', () => {
      const widgets = [createMockWidget({ id: 'w1', label: 'Widget 1' })];
      const { container } = render(
        <PluginToolbarWidgetSlot widgets={widgets} api={mockApi} />,
      );

      expect(
        container.querySelector('.toolbar-separator'),
      ).toBeInTheDocument();
    });

    it('renders a PluginToolbarWidget for each widget', () => {
      const widgets = [
        createMockWidget({ id: 'w1', label: 'Widget 1' }),
        createMockWidget({ id: 'w2', label: 'Widget 2' }),
      ];
      const { container } = render(
        <PluginToolbarWidgetSlot widgets={widgets} api={mockApi} />,
      );

      const widgetElements = container.querySelectorAll(
        '[data-plugin-widget-id]',
      );
      expect(widgetElements).toHaveLength(2);
      expect(
        container.querySelector('[data-plugin-widget-id="w1"]'),
      ).toBeInTheDocument();
      expect(
        container.querySelector('[data-plugin-widget-id="w2"]'),
      ).toBeInTheDocument();
    });

    it('renders multiple widgets with correct order', () => {
      const widgets = [
        createMockWidget({ id: 'first', label: 'First' }),
        createMockWidget({ id: 'second', label: 'Second' }),
        createMockWidget({ id: 'third', label: 'Third' }),
      ];
      const { container } = render(
        <PluginToolbarWidgetSlot widgets={widgets} api={mockApi} />,
      );

      const widgetElements = container.querySelectorAll(
        '.plugin-toolbar-widget',
      );
      expect(widgetElements).toHaveLength(3);
      expect(widgetElements[0].getAttribute('data-plugin-widget-id')).toBe(
        'first',
      );
      expect(widgetElements[1].getAttribute('data-plugin-widget-id')).toBe(
        'second',
      );
      expect(widgetElements[2].getAttribute('data-plugin-widget-id')).toBe(
        'third',
      );
    });

    it('renders exactly one separator regardless of widget count', () => {
      const widgets = [
        createMockWidget({ id: 'a', label: 'A' }),
        createMockWidget({ id: 'b', label: 'B' }),
        createMockWidget({ id: 'c', label: 'C' }),
      ];
      const { container } = render(
        <PluginToolbarWidgetSlot widgets={widgets} api={mockApi} />,
      );

      const separators = container.querySelectorAll('.toolbar-separator');
      expect(separators).toHaveLength(1);
    });

    it('renders single widget correctly', () => {
      const widgets = [
        createMockWidget({ id: 'solo', label: 'Solo Widget' }),
      ];
      const { container } = render(
        <PluginToolbarWidgetSlot widgets={widgets} api={mockApi} />,
      );

      expect(
        container.querySelector('[data-plugin-widget-id="solo"]'),
      ).toBeInTheDocument();
      expect(
        container.querySelector('.toolbar-separator'),
      ).toBeInTheDocument();
      expect(
        screen.getByRole('group', { name: 'Solo Widget' }),
      ).toBeInTheDocument();
    });
  });
});
