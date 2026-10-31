/**
 * Regression tests for issue #3589115 - a pending edit must be committed to
 * the component it was typed into, never to the one selected after it.
 *
 * PropertyPanel's identity effects (PropertyPanel.tsx:518-526 for the node,
 * :528-535 for the edge) call flush() AFTER `node` / `edge` has already become
 * the newly selected one. Before the fix, flush() invoked whichever
 * onDebouncedChange was current at that moment, so the text typed into the
 * previous component was written onto the new one and the edited component
 * received nothing.
 *
 * These tests deliberately do NOT blur the field before switching, because
 * blurring is exactly what hides the defect: a native mouse click fires blur
 * during mousedown, i.e. before the click handler that changes the selection,
 * so onBlur clears the timer and flush() finds nothing to do. The reachable
 * trigger is a selection change with no pointer event - the public plugin API
 * exposes one at pluginApi.ts:306, `selectNode(nodeId)`.
 *
 * Everything peripheral is mocked. The parts under test are real:
 * PropertyPanel, useDebouncedField, NodePropertiesPanel and
 * EdgePropertiesPanel (which own the actual inputs). In particular this file
 * does NOT mock useDebouncedField - PropertyPanel.test.tsx replaces the whole
 * hook with `flush: jest.fn()`, which is why no existing test could catch it.
 */

import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import PropertyPanel from '../PropertyPanel';

jest.mock('react-icons/fi', () => new Proxy({}, {
  get: (_target, prop) => {
    if (prop === '__esModule') return true;
    const Icon = () => null;
    (Icon as unknown as { displayName: string }).displayName = String(prop);
    return Icon;
  },
}));

jest.mock('../DocumentationButton', () => () => <span data-testid="doc-btn" />);
jest.mock('../InfoPopup', () => {
  const MockInfoPopup = () => <div data-testid="info-popup" />;
  MockInfoPopup.displayName = 'MockInfoPopup';
  return MockInfoPopup;
});
jest.mock('../MultiSelectionPanel', () => () => <div data-testid="multi-selection-panel" />);
jest.mock('../ReplayPanelContent', () => {
  const MockReplayPanelContent = () => <div data-testid="replay-panel-content" />;
  MockReplayPanelContent.displayName = 'MockReplayPanelContent';
  return MockReplayPanelContent;
});

const mockPanelState = {
  panelWidth: 300,
  panelIsResizing: false,
  setPanelWidth: jest.fn(),
  setPanelResizing: jest.fn(),
  propertyPanelCollapsed: false,
  togglePropertyPanelCollapse: jest.fn(),
  panelMode: 'event',
  setPanelMode: jest.fn(),
};
const mockComponentState = { components: [] };

jest.mock('../../store/usePanelStore', () => ({
  usePanelStore: jest.fn((selector: any) =>
    (typeof selector === 'function' ? selector(mockPanelState) : mockPanelState)),
}));
jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector: any) =>
    (typeof selector === 'function' ? selector(mockComponentState) : mockComponentState)),
}));
jest.mock('../../hooks/useConfigurationLoader', () => ({
  useConfigurationLoader: jest.fn(() => ({ configurationForm: null, loading: false })),
}));
jest.mock('../../hooks/usePanelResize', () => ({
  usePanelResize: jest.fn(() => ({ startResize: jest.fn() })),
}));

const nodeA: any = {
  id: 'node-a',
  type: 'element',
  position: { x: 0, y: 0 },
  data: { label: 'Alpha', annotation: 'Alpha note' },
};
const nodeB: any = {
  id: 'node-b',
  type: 'element',
  position: { x: 0, y: 0 },
  data: { label: 'Beta', annotation: 'Beta note' },
};
const edgeA: any = { id: 'edge-a', source: 'node-a', target: 'node-b', data: { annotation: 'Edge A note' } };
const edgeB: any = { id: 'edge-b', source: 'node-b', target: 'node-a', data: { annotation: 'Edge B note' } };

describe('PropertyPanel - pending edit on a selection switch (issue #3589115)', () => {
  let configCalls: Array<[string, Record<string, any>]>;
  let nodeUpdateCalls: Array<[string, any]>;
  let edgeUpdateCalls: Array<[string, any]>;

  const handlers = {
    onConfigurationChange: (id: string, cfg: Record<string, any>) => { configCalls.push([id, cfg]); },
    onNodeUpdate: (id: string, data: any) => { nodeUpdateCalls.push([id, data]); },
    onEdgeUpdate: (id: string, data: any) => { edgeUpdateCalls.push([id, data]); },
  };

  const panel = (node: any, edge: any) => (
    <PropertyPanel node={node} edge={edge} settings={{}} {...handlers} />
  );

  beforeEach(() => {
    jest.useFakeTimers();
    configCalls = [];
    nodeUpdateCalls = [];
    edgeUpdateCalls = [];
  });

  afterEach(() => {
    act(() => { jest.runOnlyPendingTimers(); });
    jest.useRealTimers();
  });

  it('commits a pending node label to the edited node, not the newly selected one', () => {
    const { rerender } = render(panel(nodeA, null));

    const input = screen.getByRole('textbox', { name: 'Label' }) as HTMLInputElement;
    expect(input.value).toBe('Alpha');

    act(() => {
      fireEvent.change(input, { target: { value: 'Alpha EDITED' } });
    });
    expect(configCalls).toEqual([]);

    // Selection switches with the edit still pending, and with NO blur.
    act(() => {
      rerender(panel(nodeB, null));
    });

    expect(configCalls).toEqual([
      ['node-a', { _componentLabel: 'Alpha EDITED' }],
    ]);
  });

  it('commits a pending node annotation to the edited node', () => {
    const { rerender } = render(panel(nodeA, null));

    const textarea = screen.getByRole('textbox', { name: 'Annotation' }) as HTMLTextAreaElement;

    act(() => {
      fireEvent.change(textarea, { target: { value: 'Alpha note EDITED' } });
    });

    act(() => {
      rerender(panel(nodeB, null));
    });

    expect(nodeUpdateCalls).toEqual([
      ['node-a', { label: 'Alpha', annotation: 'Alpha note EDITED' }],
    ]);
  });

  it('commits a pending edge annotation to the edited edge', () => {
    const { rerender } = render(panel(null, edgeA));

    const textarea = screen.getByRole('textbox', { name: 'Annotation' }) as HTMLTextAreaElement;

    act(() => {
      fireEvent.change(textarea, { target: { value: 'Edge A note EDITED' } });
    });

    act(() => {
      rerender(panel(null, edgeB));
    });

    expect(edgeUpdateCalls).toEqual([
      ['edge-a', { annotation: 'Edge A note EDITED' }],
    ]);
  });

  it('commits a pending edit to the edited node when the panel unmounts', () => {
    // The unmount cleanup shares the fault with flush(): it too used to reach
    // for the current handler rather than the one the edit belongs to.
    const { unmount } = render(panel(nodeA, null));

    const input = screen.getByRole('textbox', { name: 'Label' }) as HTMLInputElement;
    act(() => {
      fireEvent.change(input, { target: { value: 'Alpha EDITED' } });
    });

    unmount();

    expect(configCalls).toEqual([
      ['node-a', { _componentLabel: 'Alpha EDITED' }],
    ]);
  });

  it('still commits to the edited node when a blur precedes the switch', () => {
    // The ordinary interactive path, kept as a control: a real click blurs the
    // input first, which is why this defect never showed up in manual use.
    const { rerender } = render(panel(nodeA, null));

    const input = screen.getByRole('textbox', { name: 'Label' }) as HTMLInputElement;
    act(() => {
      fireEvent.change(input, { target: { value: 'Alpha EDITED' } });
    });
    act(() => {
      fireEvent.blur(input, { target: { value: 'Alpha EDITED' } });
    });

    act(() => {
      rerender(panel(nodeB, null));
    });

    expect(configCalls).toEqual([
      ['node-a', { _componentLabel: 'Alpha EDITED' }],
    ]);
  });
});
