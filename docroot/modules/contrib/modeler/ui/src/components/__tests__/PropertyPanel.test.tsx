import React, { act } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import PropertyPanel from '../PropertyPanel';
import type { TokenSourceValue } from '../TokenSourceContext';

// Capture the value passed to TokenSourceContext.Provider (the @-picker's
// shared token sources) so Feature-J routing can be asserted without mounting
// the picker (which lives deep inside form fields).
let capturedTokenSources: TokenSourceValue = {};
jest.mock('../TokenSourceContext', () => {
  const actual = jest.requireActual('../TokenSourceContext');
  return {
    ...actual,
    TokenSourceContext: {
      ...actual.TokenSourceContext,
      Provider: ({ value, children }: { value: TokenSourceValue; children: React.ReactNode }) => {
        capturedTokenSources = value;
        return <>{children}</>;
      },
    },
  };
});

jest.mock('react-icons/fi', () => ({
  FiChevronDown: () => <span data-testid="fi-chevron-down" />,
  FiChevronRight: () => <span data-testid="fi-chevron-right" />,
  FiChevronLeft: () => <span data-testid="fi-chevron-left" />,
  FiActivity: () => <span data-testid="fi-activity" />,
  FiZap: () => <span data-testid="fi-zap" />,
  FiGitBranch: () => <span data-testid="fi-git-branch" />,
  FiBox: () => <span data-testid="fi-box" />,
  FiInfo: () => <span data-testid="fi-info" />,
  FiArrowLeft: () => <span data-testid="fi-arrow-left" />,
  FiRefreshCw: (props: any) => <span data-testid="fi-refresh" className={props.className} />,
}));

jest.mock('../DocumentationButton', () => () => <span data-testid="doc-btn" />);
jest.mock('../InfoPopup', () => {
  const MockInfoPopup = (props: any) => <div data-testid="info-popup">{props.items?.map((item: any, i: number) => <span key={i}>{item.label}: {item.value}</span>)}</div>;
  MockInfoPopup.displayName = 'MockInfoPopup';
  return MockInfoPopup;
});
jest.mock('../MultiSelectionPanel', () => (_props: any) => <div data-testid="multi-selection-panel" />);
jest.mock('../NodePropertiesPanel', () => (props: any) => <div data-testid="node-properties-panel" data-node-id={props.node?.id} />);
jest.mock('../EdgePropertiesPanel', () => (props: any) => <div data-testid="edge-properties-panel" data-edge-id={props.edge?.id} />);
jest.mock('../ReplayPanelContent', () => {
  const MockReplayPanelContent = (props: any) => (
    <div data-testid="replay-panel-content" data-step={props.currentStep} />
  );
  MockReplayPanelContent.displayName = 'MockReplayPanelContent';
  return MockReplayPanelContent;
});

const mockTogglePropertyPanelCollapse = jest.fn();
const mockSetPanelMode = jest.fn();
let mockPanelState: any = {
  panelWidth: 300,
  panelIsResizing: false,
  setPanelWidth: jest.fn(),
  setPanelResizing: jest.fn(),
  propertyPanelCollapsed: false,
  togglePropertyPanelCollapse: mockTogglePropertyPanelCollapse,
  panelMode: 'event',
  setPanelMode: mockSetPanelMode,
};
let mockComponentState: any = {
  components: [],
};

jest.mock('../../store/usePanelStore', () => ({
  usePanelStore: jest.fn((selector: any) => {
    if (typeof selector === 'function') return selector(mockPanelState);
    return mockPanelState;
  }),
}));

jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector: any) => {
    if (typeof selector === 'function') return selector(mockComponentState);
    return mockComponentState;
  }),
}));

jest.mock('../../hooks/useConfigurationLoader', () => ({
  useConfigurationLoader: jest.fn(() => ({
    configurationForm: null,
    loading: false,
  })),
}));

jest.mock('../../hooks/usePanelResize', () => ({
  usePanelResize: jest.fn(() => ({
    startResize: jest.fn(),
  })),
}));

const debouncedFieldCallbacks: Array<{ onDebouncedChange: (value: string) => void; disabled?: boolean }> = [];
jest.mock('../../hooks/useDebouncedField', () => ({
  useDebouncedField: jest.fn(({ initialValue, onDebouncedChange, disabled }) => {
    debouncedFieldCallbacks.push({ onDebouncedChange, disabled });
    return {
      value: initialValue || '',
      setValue: jest.fn(),
      onChange: jest.fn(),
      onBlur: jest.fn(),
      flush: jest.fn(),
    };
  }),
}));

describe('PropertyPanel', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    debouncedFieldCallbacks.length = 0;
    mockPanelState = {
      panelWidth: 300,
      panelIsResizing: false,
      setPanelWidth: jest.fn(),
      setPanelResizing: jest.fn(),
      propertyPanelCollapsed: false,
      togglePropertyPanelCollapse: mockTogglePropertyPanelCollapse,
      panelMode: 'event',
      setPanelMode: mockSetPanelMode,
    };
    mockComponentState = {
      components: [],
    };
  });

  describe('empty state', () => {
    it('should show empty message when no node or edge selected', () => {
      render(<PropertyPanel />);
      expect(screen.getByText('Select a component or connection to view its properties')).toBeTruthy();
    });
  });

  describe('single node selected', () => {
    it('should render NodePropertiesPanel', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
    });

    it('should show component type for element nodes', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByText('Element')).toBeTruthy();
    });

    it('should show Start type for start nodes', () => {
      const node = { id: 'node-1', type: 'start', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByText('Start')).toBeTruthy();
    });

    it('should show Gateway type for gateway nodes', () => {
      const node = { id: 'node-1', type: 'gateway', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByText('Gateway')).toBeTruthy();
    });
  });

  describe('single edge selected', () => {
    it('should render EdgePropertiesPanel', () => {
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { condition: 'test' } };
      render(<PropertyPanel edge={edge as any} />);
      expect(screen.getByTestId('edge-properties-panel')).toBeTruthy();
    });

    it('should show Link type', () => {
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { condition: 'test' } };
      render(<PropertyPanel edge={edge as any} />);
      expect(screen.getByText('Link')).toBeTruthy();
    });
  });

  describe('multi-selection', () => {
    it('should render MultiSelectionPanel when multiple nodes selected', () => {
      const nodes = [
        { id: 'n1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
        { id: 'n2', type: 'element', data: { label: 'B' }, position: { x: 0, y: 0 } },
      ];
      render(<PropertyPanel selectedNodes={nodes as any} />);
      expect(screen.getByTestId('multi-selection-panel')).toBeTruthy();
      expect(screen.getByText('Multiple Selection')).toBeTruthy();
    });
  });

  describe('subprocess type', () => {
    it('should show Subprocess type for subprocess nodes', () => {
      const node = { id: 'node-1', type: 'subprocess', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByText('Subprocess')).toBeTruthy();
    });
  });

  describe('loading indicator (in body, not header)', () => {
    it('should render the loading throbber in the panel BODY (.panel-loading), not the header', () => {
      const { useConfigurationLoader } = require('../../hooks/useConfigurationLoader');
      useConfigurationLoader.mockReturnValue({ configurationForm: null, loading: true });

      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      const { container } = render(<PropertyPanel node={node as any} />);

      // Throbber present, in the body region…
      const bodyLoading = container.querySelector('.panel-loading');
      expect(bodyLoading).toBeTruthy();
      expect(bodyLoading?.textContent).toContain('Loading...');
      // …and NOT inside the header.
      expect(container.querySelector('.panel-header .panel-loading')).toBeNull();
      // The legacy inline header indicator is gone.
      expect(container.querySelector('.loading-indicator')).toBeNull();
      // While loading, the node properties form is not rendered yet.
      expect(screen.queryByTestId('node-properties-panel')).toBeNull();
    });

    it('should NOT show the body loading throbber once loading completes', () => {
      const { useConfigurationLoader } = require('../../hooks/useConfigurationLoader');
      useConfigurationLoader.mockReturnValue({ configurationForm: null, loading: false });

      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      const { container } = render(<PropertyPanel node={node as any} />);
      expect(container.querySelector('.panel-loading')).toBeNull();
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
    });
  });

  describe('stable 3-zone header layout', () => {
    const savedReviewSettings = { modeler_api: { isNew: false, replay_url: '/api/replay', permissions: ['replay'] } };
    const eventNode = { id: 'event-1', type: 'start', data: { label: 'Event', plugin: 'evt' }, position: { x: 0, y: 0 } };
    const actionNode = { id: 'node-1', type: 'element', data: { label: 'Action', plugin: 'act' }, position: { x: 0, y: 0 } };

    it('should render all three header zones for a selected node', () => {
      const { container } = render(<PropertyPanel node={actionNode as any} />);
      expect(container.querySelector('.panel-header-label')).toBeTruthy();
      expect(container.querySelector('.panel-header-switch')).toBeTruthy();
      expect(container.querySelector('.panel-header-icons')).toBeTruthy();
    });

    it('should render NO Review-flow button when there is no replay/test capability, but KEEP the empty switch zone — stable footprint', () => {
      // No capability (e.g. permission denied) → the Review affordance must NOT
      // be rendered at all (security contract: hide replay/test affordance when
      // capability is absent). The empty middle switch cell is still rendered so
      // the header layout stays stable (no horizontal shift between states).
      const { container } = render(<PropertyPanel node={actionNode as any} hasAnyReplayCapability={false} />);
      const switchZone = container.querySelector('.panel-header-switch');
      expect(switchZone).toBeTruthy();
      const btn = switchZone?.querySelector('.header-review-btn');
      expect(btn).toBeNull();
    });

    it('should ALWAYS render the Review-flow button in the switch zone (disabled when not reviewable) WHEN capability exists — stable footprint', () => {
      // Capability present but non-event node → the button is present but
      // DISABLED, so the zone footprint is stable (no layout shift).
      const { container } = render(
        <PropertyPanel node={actionNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} />,
      );
      const switchZone = container.querySelector('.panel-header-switch');
      expect(switchZone).toBeTruthy();
      const btn = switchZone?.querySelector('.header-review-btn') as HTMLButtonElement;
      expect(btn).toBeTruthy();
      expect(btn.disabled).toBe(true);
    });

    it('should place the view-switch button in the middle zone in Properties view', () => {
      const { container } = render(
        <PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} />,
      );
      const btn = container.querySelector('.panel-header-switch .header-review-btn');
      expect(btn).toBeTruthy();
      expect(btn?.getAttribute('aria-label')).toBe('Review flow');
    });

    it('should keep the icons and switch zones present but EMPTY in Review view', () => {
      mockPanelState.panelMode = 'review';
      const { container } = render(
        <PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive />,
      );
      // Both trailing cells stay in the grid so the geometry does not shift…
      const iconsZone = container.querySelector('.panel-header-icons');
      const switchZone = container.querySelector('.panel-header-switch');
      expect(iconsZone).toBeTruthy();
      expect(switchZone).toBeTruthy();
      // …but they hold no controls: Review has only the back control (zone 1).
      expect(iconsZone?.children.length).toBe(0);
      expect(switchZone?.children.length).toBe(0);
    });

    it('should render the back control in the label zone in Review view', () => {
      mockPanelState.panelMode = 'review';
      const { container } = render(
        <PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive />,
      );
      const back = container.querySelector('.panel-header-label .panel-header-back');
      expect(back).toBeTruthy();
      expect(back?.getAttribute('aria-label')).toBe('Back to properties');
    });

    it('should render the icons only in zone 3 in Properties view', () => {
      const { container } = render(<PropertyPanel node={actionNode as any} />);
      const iconsZone = container.querySelector('.panel-header-icons');
      expect(iconsZone?.querySelector('.header-info-btn')).toBeTruthy();
    });

    it('should keep the switch button as the LAST child of .panel-header in Properties view, even when the icons zone has content — fixes the left/right jump bug', () => {
      const { container } = render(<PropertyPanel node={actionNode as any} />);
      const header = container.querySelector('.panel-header');
      const iconsZone = container.querySelector('.panel-header-icons');
      const switchZone = container.querySelector('.panel-header-switch');
      // The icons zone has content (the info button) in Properties view.
      expect(iconsZone?.querySelector('.header-info-btn')).toBeTruthy();
      // Yet the switch zone is still the last grid column / last DOM child.
      expect(header?.lastElementChild).toBe(switchZone);
    });

    it('should keep the switch button as the LAST child of .panel-header in Review view too — both views agree on right-most position', () => {
      mockPanelState.panelMode = 'review';
      const { container } = render(
        <PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive />,
      );
      const header = container.querySelector('.panel-header');
      const switchZone = container.querySelector('.panel-header-switch');
      expect(header?.lastElementChild).toBe(switchZone);
    });
  });

  describe('documentation button', () => {
    it('should show documentation button when component has documentationUrl', () => {
      mockComponentState.components = [{ plugin: 'test_plugin', documentationUrl: 'https://example.com/docs' }];
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test_plugin' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByTestId('doc-btn')).toBeTruthy();
    });

    it('should show documentation button from node data documentationUrl', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test_plugin', documentationUrl: 'https://example.com' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByTestId('doc-btn')).toBeTruthy();
    });

    it('should not show documentation button when no URL available', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'no_docs' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.queryByTestId('doc-btn')).toBeFalsy();
    });
  });

  describe('collapsed state', () => {
    it('should show collapsed label when collapsed', () => {
      mockPanelState.propertyPanelCollapsed = true;
      render(<PropertyPanel />);
      expect(screen.getByText('Properties')).toBeTruthy();
    });

    it('should expand panel when collapsed panel clicked', () => {
      mockPanelState.propertyPanelCollapsed = true;
      render(<PropertyPanel />);
      const panel = document.querySelector('.workflow-property-panel');
      fireEvent.click(panel!);
      expect(mockTogglePropertyPanelCollapse).toHaveBeenCalled();
    });

    it('should not toggle collapse when clicking expanded panel', () => {
      mockPanelState.propertyPanelCollapsed = false;
      render(<PropertyPanel />);
      const panel = document.querySelector('.workflow-property-panel');
      fireEvent.click(panel!);
      expect(mockTogglePropertyPanelCollapse).not.toHaveBeenCalled();
    });

    it('should toggle collapse when collapse widget clicked', () => {
      render(<PropertyPanel />);
      const collapseWidget = document.querySelector('.panel-collapse-widget');
      fireEvent.click(collapseWidget!);
      expect(mockTogglePropertyPanelCollapse).toHaveBeenCalled();
    });
  });

  describe('resizing state', () => {
    it('should apply resizing class when panel is resizing', () => {
      mockPanelState.panelIsResizing = true;
      render(<PropertyPanel />);
      const panel = document.querySelector('.workflow-property-panel');
      expect(panel?.className).toContain('resizing');
    });
  });

  describe('debounced field handlers', () => {
    it('should call onConfigurationChange when node label changes', () => {
      const onConfigurationChange = jest.fn();
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} onConfigurationChange={onConfigurationChange} />);

      // The first debounced field callback is for node label (order: nodeLabel, nodeAnnotation, edgeLabel, edgeAnnotation)
      const nodeLabelCallback = debouncedFieldCallbacks[0];
      nodeLabelCallback.onDebouncedChange('New Label');

      expect(onConfigurationChange).toHaveBeenCalledWith('node-1', { _componentLabel: 'New Label' });
    });

    it('should not call onConfigurationChange for node label when locked', () => {
      const onConfigurationChange = jest.fn();
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} onConfigurationChange={onConfigurationChange} isLocked={true} />);

      const nodeLabelCallback = debouncedFieldCallbacks[0];
      nodeLabelCallback.onDebouncedChange('New Label');

      expect(onConfigurationChange).not.toHaveBeenCalled();
    });

    it('should call onNodeUpdate when node annotation changes', () => {
      const onNodeUpdate = jest.fn();
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test', annotation: '' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} onNodeUpdate={onNodeUpdate} />);

      // The second debounced field callback is for node annotation
      const nodeAnnotationCallback = debouncedFieldCallbacks[1];
      nodeAnnotationCallback.onDebouncedChange('New annotation');

      expect(onNodeUpdate).toHaveBeenCalledWith('node-1', expect.objectContaining({ annotation: 'New annotation' }));
    });

    it('should not call onNodeUpdate for annotation when locked', () => {
      const onNodeUpdate = jest.fn();
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} onNodeUpdate={onNodeUpdate} isLocked={true} />);

      const nodeAnnotationCallback = debouncedFieldCallbacks[1];
      nodeAnnotationCallback.onDebouncedChange('New annotation');

      expect(onNodeUpdate).not.toHaveBeenCalled();
    });

    it('should call onEdgeUpdate when edge annotation changes', () => {
      const onEdgeUpdate = jest.fn();
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { annotation: '' } };
      render(<PropertyPanel edge={edge as any} onEdgeUpdate={onEdgeUpdate} />);

      // Third callback is for edge annotation (edge condition-label field removed in P5)
      const edgeAnnotationCallback = debouncedFieldCallbacks[2];
      edgeAnnotationCallback.onDebouncedChange('Edge note');

      expect(onEdgeUpdate).toHaveBeenCalledWith('edge-1', expect.objectContaining({ annotation: 'Edge note' }));
    });

    it('should not call onEdgeUpdate for annotation when locked', () => {
      const onEdgeUpdate = jest.fn();
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { annotation: '' } };
      render(<PropertyPanel edge={edge as any} onEdgeUpdate={onEdgeUpdate} isLocked={true} />);

      const edgeAnnotationCallback = debouncedFieldCallbacks[2];
      edgeAnnotationCallback.onDebouncedChange('Edge note');

      expect(onEdgeUpdate).not.toHaveBeenCalled();
    });
  });

  describe('info popup', () => {
    it('should toggle info popup when info button clicked', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);

      const infoBtn = screen.getByTitle('Show metadata');
      fireEvent.click(infoBtn);

      expect(screen.getByTestId('info-popup')).toBeTruthy();
    });

    it('should close info popup when toggled again', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);

      const infoBtn = screen.getByTitle('Show metadata');
      fireEvent.click(infoBtn);
      expect(screen.getByTestId('info-popup')).toBeTruthy();

      fireEvent.click(infoBtn);
      expect(screen.queryByTestId('info-popup')).toBeFalsy();
    });

    it('should show edge metadata in info popup', () => {
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { condition: 'test.condition' } };
      render(<PropertyPanel edge={edge as any} />);

      const infoBtn = screen.getByTitle('Show metadata');
      fireEvent.click(infoBtn);

      expect(screen.getByTestId('info-popup')).toBeTruthy();
    });
  });

  describe('multi-selection with edges', () => {
    it('should render MultiSelectionPanel when multiple edges selected', () => {
      const edges = [
        { id: 'e1', source: 'a', target: 'b', data: {} },
        { id: 'e2', source: 'b', target: 'c', data: {} },
      ];
      render(<PropertyPanel selectedEdges={edges as any} />);
      expect(screen.getByTestId('multi-selection-panel')).toBeTruthy();
    });

    it('should render MultiSelectionPanel when nodes and edges both selected', () => {
      const nodes = [
        { id: 'n1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
      ];
      const edges = [
        { id: 'e1', source: 'a', target: 'b', data: {} },
      ];
      render(<PropertyPanel selectedNodes={nodes as any} selectedEdges={edges as any} />);
      expect(screen.getByTestId('multi-selection-panel')).toBeTruthy();
    });
  });

  describe('Review flow button (Properties header)', () => {
    const eventNode = { id: 'event-1', type: 'start', data: { label: 'Event', plugin: 'evt' }, position: { x: 0, y: 0 } };
    const actionNode = { id: 'node-1', type: 'element', data: { label: 'Action', plugin: 'act' }, position: { x: 0, y: 0 } };
    const savedReviewSettings = { modeler_api: { isNew: false, replay_url: '/api/replay', permissions: ['replay'] } };

    it('should NOT render a segmented Event | Review flow tablist', () => {
      render(<PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} />);
      expect(screen.queryByRole('tab', { name: 'Event' })).toBeNull();
      expect(document.querySelector('.panel-mode-toggle')).toBeNull();
    });

    it('should NOT render the legacy "Test model" string anywhere', () => {
      render(<PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} />);
      expect(screen.queryByText(/Test model/)).toBeNull();
    });

    it('should NOT render the old "Review model" wording (renamed to "Review flow")', () => {
      render(<PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} />);
      expect(screen.queryByText(/Review model/)).toBeNull();
      expect(screen.queryByRole('button', { name: 'Review model' })).toBeNull();
      // The new label is present.
      expect(screen.getByRole('button', { name: 'Review flow' })).toBeTruthy();
    });

    // ── The button is ALWAYS rendered; enabled/disabled per reviewability ─────
    it('should render the Review flow button ENABLED for a selected event node (no session)', () => {
      render(<PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} />);
      const btn = screen.getByRole('button', { name: 'Review flow' }) as HTMLButtonElement;
      expect(btn).toBeTruthy();
      expect(btn.disabled).toBe(false);
      // The tooltip advertises the equivalent keyboard shortcut.
      expect(btn.getAttribute('title')).toBe('Review flow (Alt+Shift+R)');
    });

    it('should render the Review flow button DISABLED (with tooltip) for a non-event node (no session)', () => {
      render(<PropertyPanel node={actionNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} />);
      const btn = screen.getByRole('button', { name: 'Review flow' }) as HTMLButtonElement;
      expect(btn).toBeTruthy();
      expect(btn.disabled).toBe(true);
      expect(btn.getAttribute('aria-disabled')).toBe('true');
      expect(btn.getAttribute('title')).toBe(
        'Review is available once this step belongs to an executable event flow.',
      );
    });

    it('should render NO Review flow button when there is no replay/test capability', () => {
      // Security contract: with no capability the Review affordance is absent
      // from the DOM entirely (not merely disabled).
      const { container } = render(
        <PropertyPanel node={eventNode as any} hasAnyReplayCapability={false} settings={savedReviewSettings as any} />,
      );
      expect(screen.queryByRole('button', { name: 'Review flow' })).toBeNull();
      // The empty switch cell stays for layout stability.
      expect(container.querySelector('.panel-header-switch')).toBeTruthy();
    });

    it('should render NO Review flow button for a new (unsaved) model', () => {
      // reviewAvailable = hasAnyReplayCapability && !isNewModel — a new model has
      // no reviewable flow yet, so no Review affordance is rendered.
      const newSettings = { modeler_api: { isNew: true, replay_url: '/api/replay', permissions: ['replay'] } };
      render(<PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={newSettings as any} />);
      expect(screen.queryByRole('button', { name: 'Review flow' })).toBeNull();
    });

    // ── Active session: button enabled for ANY node (to return to replay) ────
    it('should render the Review flow button ENABLED for a NON-event node when a session is active', () => {
      render(<PropertyPanel node={actionNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive />);
      const btn = screen.getByRole('button', { name: 'Review flow' }) as HTMLButtonElement;
      expect(btn.disabled).toBe(false);
    });

    it('should NOT render the Review flow button when NO node is selected (even with an active session)', () => {
      // Visibility contract: the button shows ONLY for a single selected NODE.
      // With nothing selected there is no node to trace to an event, so the
      // button is hidden (the empty switch cell keeps the layout stable).
      const { container } = render(
        <PropertyPanel hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive />,
      );
      expect(screen.queryByRole('button', { name: 'Review flow' })).toBeNull();
      expect(container.querySelector('.panel-header-switch')).toBeTruthy();
    });

    it('should NOT render the Review flow button when MULTIPLE nodes are selected', () => {
      // Multi-select has no single node context → button hidden.
      const nodes = [
        { id: 'n1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
        { id: 'n2', type: 'element', data: { label: 'B' }, position: { x: 0, y: 0 } },
      ];
      const { container } = render(
        <PropertyPanel
          selectedNodes={nodes as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          replaySessionActive
        />,
      );
      expect(screen.queryByRole('button', { name: 'Review flow' })).toBeNull();
      expect(container.querySelector('.panel-header-switch')).toBeTruthy();
    });

    it('should NOT render the Review flow button when a single EDGE is selected', () => {
      // An edge selection sets `edge` but not `node` → button hidden.
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: {} };
      const { container } = render(
        <PropertyPanel
          edge={edge as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          replaySessionActive
        />,
      );
      expect(screen.queryByRole('button', { name: 'Review flow' })).toBeNull();
      expect(container.querySelector('.panel-header-switch')).toBeTruthy();
    });

    // ── Structural owning event: enabled WITHOUT a session ──────────────────
    it('should ENABLE the Review flow button for a NON-event node with a STRUCTURAL owning event but NO session', () => {
      // No `reviewableEventId` (no session) but Flow supplies a structural
      // owning event via `pickerOwningEventId` → the button is rendered AND
      // enabled so the user can start a review session for the owning event.
      render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId={null}
          pickerOwningEventId="event-1"
          onRequestReviewMode={jest.fn()}
        />,
      );
      const btn = screen.getByRole('button', { name: 'Review flow' }) as HTMLButtonElement;
      expect(btn).toBeTruthy();
      expect(btn.disabled).toBe(false);
      expect(btn.getAttribute('aria-disabled')).toBe('false');
      expect(btn.getAttribute('title')).toBe('Review flow (Alt+Shift+R)');
    });

    // ── BUG 2: non-event node OUTSIDE every reviewed flow → DISABLED ──────────
    it('should DISABLE the Review flow button for a non-event node whose owning event has NO session (reviewableEventId null)', () => {
      render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          replaySessionActive
          reviewableEventId={null}
          onRequestReviewMode={jest.fn()}
        />,
      );
      const btn = screen.getByRole('button', { name: 'Review flow' }) as HTMLButtonElement;
      expect(btn.disabled).toBe(true);
    });

    it('should ENABLE the Review flow button for a non-event node whose owning event HAS a session', () => {
      render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          replaySessionActive
          reviewableEventId="event-1"
          onRequestReviewMode={jest.fn()}
        />,
      );
      const btn = screen.getByRole('button', { name: 'Review flow' }) as HTMLButtonElement;
      expect(btn.disabled).toBe(false);
    });

    it('should NOT call onRequestReviewMode when the disabled button is clicked', () => {
      const onRequestReviewMode = jest.fn();
      render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId={null}
          onRequestReviewMode={onRequestReviewMode}
        />,
      );
      fireEvent.click(screen.getByRole('button', { name: 'Review flow' }));
      expect(onRequestReviewMode).not.toHaveBeenCalled();
    });

    // ── Click routing ────────────────────────────────────────────────────────
    it('should START a session via onRequestReviewMode when none is active (Phase 2 guard)', () => {
      const onRequestReviewMode = jest.fn();
      render(<PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} onRequestReviewMode={onRequestReviewMode} />);
      fireEvent.click(screen.getByRole('button', { name: 'Review flow' }));
      expect(onRequestReviewMode).toHaveBeenCalledTimes(1);
      // No direct switch — the guard owns starting the session.
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('review');
    });

    it('should delegate to onRequestReviewMode (Flow resolves resume/return) when a session is active', () => {
      // Per-event sessions: the click always delegates to Flow, which decides
      // whether to resume the selected event, return to the active event, or
      // start a new session — PropertyPanel no longer does a direct switch.
      const onRequestReviewMode = jest.fn();
      // A non-event node whose owning event HAS a session (reviewableEventId set)
      // → the button shows and delegates to Flow.
      render(<PropertyPanel node={actionNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive reviewableEventId="event-1" onRequestReviewMode={onRequestReviewMode} />);
      fireEvent.click(screen.getByRole('button', { name: 'Review flow' }));
      expect(onRequestReviewMode).toHaveBeenCalledTimes(1);
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('review');
    });

    it('should fall back to setPanelMode(review) when no guard handler is provided', () => {
      render(<PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} />);
      fireEvent.click(screen.getByRole('button', { name: 'Review flow' }));
      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
    });
  });

  describe('Replay view (header back control + body)', () => {
    const eventNode = { id: 'event-1', type: 'start', data: { label: 'Event', plugin: 'evt' }, position: { x: 0, y: 0 } };
    const actionNode = { id: 'node-1', type: 'element', data: { label: 'Action', plugin: 'act' }, position: { x: 0, y: 0 } };
    const savedReviewSettings = { modeler_api: { isNew: false, replay_url: '/api/replay', permissions: ['replay'] } };

    // Review view requires an active session (effectiveMode gates on it).
    it('should render exactly ONE interactive header control: the back control', () => {
      mockPanelState.panelMode = 'review';
      const { container } = render(
        <PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive />,
      );
      const header = container.querySelector('.panel-header') as HTMLElement;
      const controls = header.querySelectorAll('button, a, input, select, [role="button"]');
      expect(controls.length).toBe(1);
      const back = controls[0];
      expect(back.getAttribute('aria-label')).toBe('Back to properties');
      expect(back.tagName).toBe('BUTTON');
      expect(back.getAttribute('type')).toBe('button');
      expect(back.getAttribute('class')).toBe('panel-header-back');
      expect(back.getAttribute('title')).toBe('Back to properties (Alt+Shift+R)');
      expect(back.textContent).toBe('Back');
      // The old context label and Properties switch button are gone.
      expect(header.querySelector('.component-type')).toBeNull();
      expect(screen.queryByRole('button', { name: 'Show properties' })).toBeNull();
    });

    it('should return to the node properties when the back control is clicked', () => {
      mockPanelState.panelMode = 'review';
      const panel = () => (
        <PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive />
      );
      const { rerender } = render(panel());
      expect(screen.getByTestId('replay-panel-content')).toBeTruthy();

      fireEvent.click(screen.getByRole('button', { name: 'Back to properties' }));
      expect(mockSetPanelMode).toHaveBeenCalledWith('event');

      // The store is mocked (not reactive), so apply the mode the click asked
      // for and re-render to observe the resulting view. A fresh element is
      // required: React bails out of re-rendering an identical element.
      mockPanelState.panelMode = 'event';
      rerender(panel());
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
      expect(screen.queryByTestId('replay-panel-content')).toBeNull();
    });

    it('should stay in Replay view even when a non-event node is the current selection', () => {
      // Walking steps selects other nodes; the panel must remain in Replay.
      mockPanelState.panelMode = 'review';
      render(<PropertyPanel node={actionNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive />);
      expect(screen.getByTestId('replay-panel-content')).toBeTruthy();
      expect(screen.queryByTestId('node-properties-panel')).toBeNull();
    });

    it('should render ReplayPanelContent in the body while a session is active', () => {
      mockPanelState.panelMode = 'review';
      render(<PropertyPanel node={eventNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive currentReplayStep={2} />);
      expect(screen.getByTestId('replay-panel-content')).toBeTruthy();
    });

    it('should fall back to Properties when panelMode is review but NO session is active', () => {
      // Without an active session the panel must not show replay content.
      mockPanelState.panelMode = 'review';
      render(<PropertyPanel node={actionNode as any} hasAnyReplayCapability settings={savedReviewSettings as any} replaySessionActive={false} />);
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
      expect(screen.queryByTestId('replay-panel-content')).toBeNull();
    });

    it('should STAY on Properties for a picker-initiated session even when panelMode is review and a session is active', () => {
      // Bug #3576269: opening the @-picker arms a session (replaySessionActive
      // true) but, being picker-initiated, must NOT switch the panel to Review
      // despite a sticky panelMode='review'. The field stays visible.
      mockPanelState.panelMode = 'review';
      render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          replaySessionActive
          pickerInitiatedSession
        />,
      );
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
      expect(screen.queryByTestId('replay-panel-content')).toBeNull();
    });

    it('should show the Replay view when the session is active, panelMode is review, and it is NOT picker-initiated', () => {
      // The default (explicit Review) path is unchanged: pickerInitiatedSession
      // falsy → the Replay view shows as before.
      mockPanelState.panelMode = 'review';
      render(
        <PropertyPanel
          node={eventNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          replaySessionActive
          pickerInitiatedSession={false}
        />,
      );
      expect(screen.getByTestId('replay-panel-content')).toBeTruthy();
      expect(screen.queryByTestId('node-properties-panel')).toBeNull();
    });
  });

  // ── Feature J: tokenSources expose the @-picker step-data plumbing ─────────
  describe('Feature J: tokenSources for the [-token picker', () => {
    const savedReviewSettings = { modeler_api: { isNew: false, replay_url: '/api/replay', test_url: '/api/test', permissions: ['replay'] } };
    const eventNode = { id: 'event-1', type: 'start', data: { label: 'Event', plugin: 'evt' }, position: { x: 0, y: 0 } };
    const actionNode = { id: 'node-1', type: 'element', data: { label: 'Action', plugin: 'act' }, position: { x: 0, y: 0 } };

    beforeEach(() => { capturedTokenSources = {}; });

    it('publishes the global and template tokens to the picker context (their only consumer now)', () => {
      // The review panel no longer lists tokens (#3589103), so the "[" picker
      // is the sole consumer of these props - they must still arrive there.
      const globalTokens = { '[site:name]': { name: 'Site name', token: 'name', value: 'My Site' } };
      const templateTokens = { '[tpl:id]': { name: 'Template id', token: 'id', value: '7' } };
      render(
        <PropertyPanel
          node={actionNode as any}
          settings={savedReviewSettings as any}
          globalTokens={globalTokens as any}
          templateTokens={templateTokens as any}
          isTemplate
        />,
      );
      expect(capturedTokenSources.globalTokens).toBe(globalTokens);
      expect(capturedTokenSources.templateTokens).toBe(templateTokens);
      expect(capturedTokenSources.isTemplate).toBe(true);
    });

    it('uses Flow\'s structural pickerOwningEventId prop (works with NO session) when provided', () => {
      // Action node, no session, but Flow resolved the owning event structurally.
      render(
        <PropertyPanel
          node={actionNode as any}
          settings={savedReviewSettings as any}
          pickerOwningEventId="event-1"
        />,
      );
      expect(capturedTokenSources.owningEventId).toBe('event-1');
    });

    it('exposes a null owning event when the structural prop is null (node outside any flow)', () => {
      render(
        <PropertyPanel
          node={actionNode as any}
          settings={savedReviewSettings as any}
          pickerOwningEventId={null}
        />,
      );
      expect(capturedTokenSources.owningEventId).toBeNull();
    });

    it('exposes the selected EVENT as the owning event when review is available (legacy fallback, no prop)', () => {
      render(
        <PropertyPanel
          node={eventNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          selectedStartNodeId="event-1"
        />,
      );
      expect(capturedTokenSources.owningEventId).toBe('event-1');
    });

    it('exposes the resolved reviewableEventId as the owning event for a non-event node', () => {
      render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId="event-1"
        />,
      );
      expect(capturedTokenSources.owningEventId).toBe('event-1');
    });

    it('exposes a null owning event when neither an event nor a reviewable owner is present', () => {
      render(<PropertyPanel node={actionNode as any} settings={savedReviewSettings as any} />);
      expect(capturedTokenSources.owningEventId).toBeNull();
    });

    it('routes dataset selection / listen / load through the SAME Flow handlers (single source of truth)', () => {
      const onSelectReplayEntry = jest.fn();
      const onSelectListenItem = jest.fn();
      const onLoadStepData = jest.fn();
      render(
        <PropertyPanel
          node={eventNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          selectedStartNodeId="event-1"
          onSelectReplayEntry={onSelectReplayEntry}
          onSelectListenItem={onSelectListenItem}
          onLoadStepData={onLoadStepData}
          isReplayLoading
        />,
      );
      // onSelectDataset === onSelectReplayEntry (Review panel's entry handler).
      capturedTokenSources.onSelectDataset?.(2);
      expect(onSelectReplayEntry).toHaveBeenCalledWith(2);
      // onStartListen === onSelectListenItem (the single listener re-arm).
      capturedTokenSources.onStartListen?.();
      expect(onSelectListenItem).toHaveBeenCalled();
      // onLoadStepData passed straight through (Flow.loadStepDataForPicker).
      capturedTokenSources.onLoadStepData?.('event-1');
      expect(onLoadStepData).toHaveBeenCalledWith('event-1');
      // The loading flag is surfaced for the picker's "Polling for data" state.
      expect(capturedTokenSources.isLoadingStepData).toBe(true);
    });

    it('mirrors the active session selection (selectedEntryIndex) to the picker', () => {
      render(
        <PropertyPanel
          node={eventNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          selectedStartNodeId="event-1"
          selectedReplayEntryIndex={-2}
        />,
      );
      expect(capturedTokenSources.selectedEntryIndex).toBe(-2);
      expect(capturedTokenSources.isListening).toBe(true);
    });

    it('exposes a stable onPickerOpenChange callback for the freeze signal', () => {
      render(<PropertyPanel node={actionNode as any} settings={savedReviewSettings as any} />);
      expect(typeof capturedTokenSources.onPickerOpenChange).toBe('function');
    });
  });

  // Freeze the panel's review chrome while the [-token picker is open, so the
  // panel behind the modal does not repaint from picker-armed session changes.
  describe('Feature J: freeze panel chrome while the picker is open', () => {
    const savedReviewSettings = { modeler_api: { isNew: false, replay_url: '/api/replay', test_url: '/api/test', permissions: ['replay'] } };
    const actionNode = { id: 'node-1', type: 'element', data: { label: 'Action', plugin: 'act' }, position: { x: 0, y: 0 } };

    beforeEach(() => { capturedTokenSources = {}; });

    it('freezes the Review-button enabled state while the picker is open, then reconciles on close', () => {
      // Start: action node OUTSIDE any reviewable flow → button DISABLED.
      const { rerender } = render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId={null}
          onRequestReviewMode={jest.fn()}
        />,
      );
      const btn = () => screen.getByRole('button', { name: 'Review flow' }) as HTMLButtonElement;
      expect(btn().disabled).toBe(true);

      // Picker opens → freeze.
      act(() => { capturedTokenSources.onPickerOpenChange?.(true); });

      // A picker-armed session would normally make the owning event reviewable.
      rerender(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId="event-1"
          replaySessionActive
          onRequestReviewMode={jest.fn()}
        />,
      );
      // FROZEN: the button stays DISABLED (the chrome held its open-time value).
      expect(btn().disabled).toBe(true);

      // Picker closes → reconcile to live (now reviewable → ENABLED).
      act(() => { capturedTokenSources.onPickerOpenChange?.(false); });
      expect(btn().disabled).toBe(false);
    });

    it('freezes effectiveMode (no event→review flip) while the picker is open', () => {
      mockPanelState.panelMode = 'review';
      // No session yet → effectiveMode 'event' → Properties shown.
      const { rerender } = render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          onRequestReviewMode={jest.fn()}
        />,
      );
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();

      act(() => { capturedTokenSources.onPickerOpenChange?.(true); });

      // A session becomes active behind the picker (NOT picker-initiated here):
      // without the freeze, effectiveMode would flip to 'review'.
      rerender(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          replaySessionActive
          onRequestReviewMode={jest.fn()}
        />,
      );
      // FROZEN on Properties — no Replay flip behind the modal.
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
      expect(screen.queryByTestId('replay-panel-content')).toBeNull();
    });

    it('keeps the picker token-source DATA live while frozen (so data still flows + auto-select works)', () => {
      const { rerender } = render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId="event-1"
          selectedReplayEntryIndex={-2}
        />,
      );
      act(() => { capturedTokenSources.onPickerOpenChange?.(true); });

      // Data arrives behind the open picker: entries + selection update.
      const entries = [
        { model_id: 'm', component_id: 'event-1', history: [], timestamp: '2024-01-01T00:00:00Z', user: 'a', ip: '', url: '' },
      ];
      rerender(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId="event-1"
          replayEntries={entries as any}
          selectedReplayEntryIndex={0}
        />,
      );
      // The LIVE data is passed through to the picker (NOT frozen).
      expect(capturedTokenSources.replayEntries).toEqual(entries);
      expect(capturedTokenSources.selectedEntryIndex).toBe(0);
    });

    it('holds chrome constant across MULTIPLE input changes while open (ordering-proof), reconciles on close', () => {
      mockPanelState.panelMode = 'review';
      // Open-time: action node, no session → Properties + DISABLED button.
      const { rerender } = render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId={null}
          onRequestReviewMode={jest.fn()}
        />,
      );
      const btn = () => screen.getByRole('button', { name: 'Review flow' }) as HTMLButtonElement;
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
      expect(btn().disabled).toBe(true);

      // Picker opens → freeze the open-time chrome (Properties + disabled).
      act(() => { capturedTokenSources.onPickerOpenChange?.(true); });

      // Now hammer the session-derived inputs across several rerenders — the
      // exact ordering that produced the flash. Chrome must stay constant.
      // Every set is picker-initiated (the real Feature-J case), which also
      // means the post-close reconcile stays on Properties.
      const inputSets = [
        { replaySessionActive: true, reviewableEventId: 'event-1', pickerInitiatedSession: true },
        { replaySessionActive: true, reviewableEventId: null as string | null, pickerInitiatedSession: true },
        { replaySessionActive: true, reviewableEventId: 'event-1', pickerInitiatedSession: true },
      ];
      for (const inputs of inputSets) {
        rerender(
          <PropertyPanel
            node={actionNode as any}
            hasAnyReplayCapability
            settings={savedReviewSettings as any}
            onRequestReviewMode={jest.fn()}
            {...inputs}
          />,
        );
        // FROZEN every time: still Properties, still disabled, no Replay subtree.
        expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
        expect(screen.queryByTestId('replay-panel-content')).toBeNull();
        expect(btn().disabled).toBe(true);
      }

      // Close → reconcile to the LAST live inputs. picker-initiated keeps
      // Properties; the reviewable owner makes the button live-ENABLED.
      act(() => { capturedTokenSources.onPickerOpenChange?.(false); });
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
      expect(btn().disabled).toBe(false);
    });

    it('reconciles to ENABLED + stays Properties on close when the live state became reviewable', () => {
      // Open-time disabled; while open a reviewable session arms; on close the
      // button reconciles to enabled and (picker-initiated) stays on Properties.
      mockPanelState.panelMode = 'review';
      const { rerender } = render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId={null}
          onRequestReviewMode={jest.fn()}
        />,
      );
      const btn = () => screen.getByRole('button', { name: 'Review flow' }) as HTMLButtonElement;
      expect(btn().disabled).toBe(true);

      act(() => { capturedTokenSources.onPickerOpenChange?.(true); });
      rerender(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          reviewableEventId="event-1"
          replaySessionActive
          pickerInitiatedSession
          onRequestReviewMode={jest.fn()}
        />,
      );
      // Frozen disabled while open.
      expect(btn().disabled).toBe(true);

      act(() => { capturedTokenSources.onPickerOpenChange?.(false); });
      // Reconcile: live reviewable → ENABLED; picker-initiated → stays Properties.
      expect(btn().disabled).toBe(false);
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
      expect(screen.queryByTestId('replay-panel-content')).toBeNull();
    });

    it('FREEZES the config-loader isReplayMode input while the picker is open (no replay-reload → no field unmount), reconciles on close for a NON-picker-initiated session', () => {
      const { useConfigurationLoader } = require('../../hooks/useConfigurationLoader');
      useConfigurationLoader.mockReturnValue({ configurationForm: null, loading: false });
      const lastIsReplayMode = () => {
        const calls = useConfigurationLoader.mock.calls;
        return calls[calls.length - 1][0].isReplayMode as boolean;
      };

      // Open-time: not in replay mode → loader sees false.
      const { rerender } = render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          isReplayMode={false}
        />,
      );
      expect(lastIsReplayMode()).toBe(false);

      // Picker opens → freeze the loader input at its open-time value (false).
      act(() => { capturedTokenSources.onPickerOpenChange?.(true); });

      // Session arms (NOT picker-initiated) → Flow flips the LIVE isReplayMode
      // prop true. This models a real Review that armed behind the picker.
      rerender(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          isReplayMode
          replaySessionActive
        />,
      );
      // FROZEN: the loader still receives false → no "always reload in replay
      // mode" → no setLoading(true) → the field is never unmounted.
      expect(lastIsReplayMode()).toBe(false);
      // The field stays mounted (no loading swap).
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();

      // Close → reconcile: with pickerInitiatedSession false, the loader sees
      // the LIVE isReplayMode again (real-Review reload path intact).
      act(() => { capturedTokenSources.onPickerOpenChange?.(false); });
      expect(lastIsReplayMode()).toBe(true);
    });

    it('does NOT drive the config loader into replay-reload on close for a PICKER-INITIATED session (the repaint-on-insert fix)', () => {
      const { useConfigurationLoader } = require('../../hooks/useConfigurationLoader');
      useConfigurationLoader.mockReturnValue({ configurationForm: null, loading: false });
      const lastIsReplayMode = () => {
        const calls = useConfigurationLoader.mock.calls;
        return calls[calls.length - 1][0].isReplayMode as boolean;
      };

      // Open-time: not in replay mode → loader sees false.
      const { rerender } = render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          isReplayMode={false}
        />,
      );
      expect(lastIsReplayMode()).toBe(false);

      // Picker opens → freeze.
      act(() => { capturedTokenSources.onPickerOpenChange?.(true); });

      // The [-token picker armed a per-event review session purely to feed
      // itself: Flow flips the LIVE isReplayMode prop true AND marks the
      // session as picker-initiated.
      rerender(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          isReplayMode
          replaySessionActive
          pickerInitiatedSession
        />,
      );
      // While open the loader still receives false (freeze).
      expect(lastIsReplayMode()).toBe(false);

      // User inserts the FIRST token → the picker CLOSES → the freeze releases.
      // BUT the session is picker-initiated (the user is still editing), so the
      // loader must STILL receive false — it must NOT reconcile to the live
      // true and re-run the "always reload in replay mode" rule (which would
      // swap in the loading spinner = the ugly repaint).
      act(() => { capturedTokenSources.onPickerOpenChange?.(false); });
      expect(lastIsReplayMode()).toBe(false);
      // The field body stays mounted — no loading spinner swap on insert.
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
    });

    it('DOES drive the config loader into replay-reload on close for a real (non-picker-initiated) Review (reload path preserved)', () => {
      const { useConfigurationLoader } = require('../../hooks/useConfigurationLoader');
      useConfigurationLoader.mockReturnValue({ configurationForm: null, loading: false });
      const lastIsReplayMode = () => {
        const calls = useConfigurationLoader.mock.calls;
        return calls[calls.length - 1][0].isReplayMode as boolean;
      };

      // A genuine Review session is active and NOT picker-initiated (Flow's
      // enterReviewForNode/resumeReviewForNode clears pickerInitiatedSession).
      // The picker is closed. The loader MUST receive the live true so the
      // config form reloads in replay mode as before.
      render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
          isReplayMode
          replaySessionActive
          pickerInitiatedSession={false}
        />,
      );
      expect(lastIsReplayMode()).toBe(true);
    });

    it('does NOT swap to the loading spinner while the picker is open even if loading is true (field stays mounted)', () => {
      const { useConfigurationLoader } = require('../../hooks/useConfigurationLoader');
      useConfigurationLoader.mockReturnValue({ configurationForm: null, loading: true });

      const { container } = render(
        <PropertyPanel
          node={actionNode as any}
          hasAnyReplayCapability
          settings={savedReviewSettings as any}
        />,
      );
      // Picker not open yet → normal loading behavior (spinner shows).
      expect(container.querySelector('.panel-loading')).toBeTruthy();

      // Picker opens → the loading swap is suppressed; the field renders.
      act(() => { capturedTokenSources.onPickerOpenChange?.(true); });
      expect(container.querySelector('.panel-loading')).toBeNull();
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();

      // Close → loading behavior resumes (spinner again).
      act(() => { capturedTokenSources.onPickerOpenChange?.(false); });
      expect(container.querySelector('.panel-loading')).toBeTruthy();
    });
  });
});
