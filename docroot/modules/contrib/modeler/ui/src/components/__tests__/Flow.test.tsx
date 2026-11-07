import React from 'react';
import { render, screen, act } from '@testing-library/react';
import Flow from '../Flow';

// Mock ReactFlow
jest.mock('reactflow', () => ({
  useReactFlow: () => ({
    fitView: jest.fn(),
    setCenter: jest.fn(),
    setViewport: jest.fn(),
    getViewport: jest.fn(() => ({ x: 0, y: 0, zoom: 1 })),
  }),
  useStore: (selector: any) => {
    if (typeof selector === 'function') {
      // Provide a minimal ReactFlow state with transform
      return selector({ transform: [0, 0, 1] });
    }
    return 1;
  },
}));

// Mock all domain stores
jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: [],
      edges: [],
      setNodes: jest.fn(),
      setEdges: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: jest.fn((selector) => {
    const state = {
      selectedNode: null,
      setSelectedNode: jest.fn(),
      selectedEdge: null,
      setSelectedEdge: jest.fn(),
      selectedNodes: [],
      setSelectedNodes: jest.fn(),
      selectedEdges: [],
      setSelectedEdges: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useModelStore', () => ({
  useModelStore: jest.fn((selector) => {
    const state = {
      modelData: { metadata: { label: 'Test Model' } },
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

const mockSetPanelMode = jest.fn();
jest.mock('../../store/usePanelStore', () => ({
  usePanelStore: jest.fn((selector) => {
    const state = {
      replayPanelCollapsed: false,
      toggleReplayPanelCollapse: jest.fn(),
      setReplayPanelCollapsed: jest.fn(),
      setPropertyPanelCollapsed: jest.fn(),
      panelMode: 'event',
      setPanelMode: mockSetPanelMode,
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useFilterStore', () => ({
  useFilterStore: jest.fn((selector) => {
    const state = {
      visibleStartNodeIds: null,
      setVisibleStartNodeIds: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useContextStore', () => ({
  useContextStore: jest.fn((selector) => {
    const state = {
      contexts: [],
      selectedContextId: null,
      setSelectedContextId: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector) => {
    const state = {
      components: [],
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../hooks/useSimpleReplaySync', () => ({
  useSimpleReplaySync: jest.fn(() => ({
    isSyncing: false,
    handleCanvasNodeClick: jest.fn(),
    handleCanvasEdgeClick: jest.fn(),
    handleReplayStepSelect: jest.fn(),
  })),
}));

jest.mock('../../hooks/useKeyboardShortcuts', () => ({
  useKeyboardShortcuts: jest.fn(),
}));

jest.mock('../../hooks/useClipboard', () => ({
  useClipboard: jest.fn(() => ({
    handleCopy: jest.fn(),
    handlePaste: jest.fn(),
    canCopy: false,
    canPaste: false,
  })),
}));

jest.mock('../../hooks/useReplayCoordination', () => ({
  useReplayCoordination: jest.fn(() => ({
    autoSyncToReplay: jest.fn(),
    toggleReplayMode: jest.fn(),
    hasReplayData: false,
  })),
}));

jest.mock('../../hooks/useViewportActions', () => ({
  useViewportActions: jest.fn(() => ({
    panToNode: jest.fn(),
    panToNodeIfOffscreen: jest.fn(),
    fitToNodes: jest.fn(),
    topAlignNode: jest.fn(),
    focusNode: jest.fn(),
    fitToNodePair: jest.fn(),
    selectAndFocus: jest.fn(),
    setReady: jest.fn(),
  })),
}));

jest.mock('../../hooks/useFlowEventHandlers', () => ({
  useFlowEventHandlers: jest.fn(() => ({
    onNodesChange: jest.fn(),
    onEdgesChange: jest.fn(),
    onSelectionChange: jest.fn(),
    onNodeClick: jest.fn(),
    onEdgeClick: jest.fn(),
    onDeleteNode: jest.fn(),
    handleDeleteSelected: jest.fn(),
    onConnect: jest.fn(),
    // New-edge connection handlers now come from the hook (issue #3585553
    // follow-on UX): onConnectStart records the gesture origin, onConnectEnd
    // creates the edge when dropped on a node body.
    onConnectStart: jest.fn(),
    onConnectEnd: jest.fn(),
    onPaneClick: jest.fn(),
    // onSelectionStart clears the post-pane-click stale-selection guard so a
    // drag-select after a pane click is honored (issue #3589101 follow-up).
    onSelectionStart: jest.fn(),
  })),
}));

jest.mock('../../hooks/useReplayIndicators', () => ({
  useReplayIndicators: jest.fn(() => ({ replayIndicators: [] })),
}));

jest.mock('../../hooks/useModalState', () => ({
  useModalState: jest.fn(() => ({
    showMetadataModal: false,
    showConfirmDialog: false,
    confirmDialogTitle: '',
    confirmDialogMessage: '',
    confirmDialogType: 'info',
    confirmDialogLoading: false,
    onMetadataSubmit: jest.fn(),
    showConfirmationDialog: jest.fn(),
    handleConfirmDialog: jest.fn(),
    handleCancelDialog: jest.fn(),
    handleCloseWithoutSave: jest.fn(),
    openMetadataModal: jest.fn(),
    closeMetadataModal: jest.fn(),
  })),
}));

jest.mock('../../hooks/useSearch', () => ({
  useSearch: jest.fn(() => ({
    searchTerm: '',
    highlightedSearchResult: null,
    onSearchHighlight: jest.fn(),
    onSearchFocus: jest.fn(),
    clearSearch: jest.fn(),
  })),
}));

jest.mock('../../hooks/useModelDataLoader', () => ({
  useModelDataLoader: jest.fn(() => ({ replayData: [] })),
}));

jest.mock('../../hooks/useConfiguration', () => ({
  useConfiguration: jest.fn(() => ({
    onConfigurationChange: jest.fn(),
    onNodeUpdate: jest.fn(),
    onEdgeUpdate: jest.fn(),
    handleAutoLayout: jest.fn(),
  })),
}));

jest.mock('../../hooks/useCloseHandler', () => ({
  useCloseHandler: jest.fn(() => ({
    handleClose: jest.fn(),
    handleSaveComplete: jest.fn(),
    saveButtonRef: { current: null },
  })),
}));

const mockStartTest = jest.fn();
// Capture the onReplayDataReceived callback passed to useTestRunner so tests
// can simulate a live test result arriving (A40-A43).
let capturedOnReplayDataReceived: ((data: unknown[]) => void) | undefined;
jest.mock('../../hooks/useTestRunner', () => ({
  useTestRunner: jest.fn((props?: { onReplayDataReceived?: (data: unknown[]) => void }) => {
    if (props?.onReplayDataReceived) capturedOnReplayDataReceived = props.onReplayDataReceived;
    return {
      isTestRunning: false,
      isTestInitiating: false,
      testError: null,
      startTest: mockStartTest,
      cancelTest: jest.fn(),
      notifySaveComplete: jest.fn(),
    };
  }),
}));

const mockLoadReplayData = jest.fn();
jest.mock('../../hooks/useReplayLoader', () => ({
  // Preserve the real sentinel constant so Flow's listen-item logic works.
  LISTEN_ITEM_INDEX: -2,
  useReplayLoader: jest.fn(() => ({
    replayEntries: [],
    loading: false,
    error: null,
    emptyMessage: null,
    loadReplayData: mockLoadReplayData,
    clearReplayEntries: jest.fn(),
  })),
}));

jest.mock('../../hooks/useMessagesContainer', () => ({
  useMessagesContainer: jest.fn(() => ({
    messagesContainerRef: { current: null },
    messagesVisible: false,
    hasMessages: false,
    handleToggleMessages: jest.fn(),
    handleClearMessages: jest.fn(),
  })),
}));

jest.mock('../../hooks/useQuickAdd', () => ({
  useQuickAdd: jest.fn(() => ({
    addSuccessorNode: jest.fn(),
  })),
}));

jest.mock('../../hooks/useNodeEdgeActions', () => ({
  useNodeEdgeActions: jest.fn(() => ({
    handleAddCondition: jest.fn(),
    handleAddEvent: jest.fn(),
  })),
}));

jest.mock('../../hooks/useSelectionSync', () => ({
  useSelectionSync: jest.fn(),
}));

jest.mock('../../utils/modelUtils', () => ({
  exportModelData: jest.fn(() => ({})),
  getFitViewport: jest.fn(() => ({ x: 0, y: 0, zoom: 1 })),
}));

jest.mock('../../hooks/useStatusAnnouncer', () => ({
  useStatusAnnouncer: jest.fn(() => ({
    message: '',
    announce: jest.fn(),
  })),
}));

jest.mock('../../hooks/useExport', () => ({
  useExport: jest.fn(() => ({
    canExport: false,
    availableFormats: [],
    hasReplayData: false,
    executeExport: jest.fn(),
    getRequiredModules: jest.fn(() => []),
  })),
}));

jest.mock('../../hooks/useViewMode', () => ({
  useViewMode: jest.fn(() => ({
    viewMode: 'fullscreen',
    toggleViewMode: jest.fn(),
    startDrag: jest.fn(),
    startResize: jest.fn(),
    isDragging: false,
    isResizing: false,
  })),
}));

jest.mock('../../hooks/useHistory', () => ({
  useHistory: jest.fn(() => ({
    saveHistory: jest.fn(),
    undo: jest.fn(),
    redo: jest.fn(),
    canUndo: jest.fn(() => false),
    canRedo: jest.fn(() => false),
  })),
}));

jest.mock('../../hooks/usePluginPanels', () => ({
  usePluginPanels: jest.fn(() => []),
  usePluginWidgets: jest.fn(() => []),
}));

jest.mock('../../plugins/pluginApi', () => ({
  createPluginApi: jest.fn(() => ({})),
  setApiReadOnly: jest.fn(),
  setMutationHooks: jest.fn(),
  clearMutationHooks: jest.fn(),
  setViewportHooks: jest.fn(),
  clearViewportHooks: jest.fn(),
}));

jest.mock('../../plugins/pluginRegistry', () => ({
  markReady: jest.fn(),
  markUnready: jest.fn(),
}));

// Note: ../../utils/permissions is NOT mocked — the real hasPermission()
// reads from settings.modeler_api.permissions so template-edit-blocked
// tests work correctly.

// Capture props from mocked child components
let capturedToolbarProps: any = {};
let capturedModalsProps: any = {};
let capturedFlowCanvasProps: any = {};
let capturedPropertyPanelProps: any = {};
let capturedCanvasToolbarProps: any = {};

jest.mock('../FlowCanvas', () => (props: any) => { capturedFlowCanvasProps = props; return <div data-testid="flow-canvas" />; });
jest.mock('../PropertyPanel', () => (props: any) => { capturedPropertyPanelProps = props; return <div data-testid="property-panel" />; });
jest.mock('../Modals', () => (props: any) => { capturedModalsProps = props; return <div data-testid="modals" />; });
jest.mock('../Toolbar', () => (props: any) => { capturedToolbarProps = props; return <div data-testid="toolbar" data-model-name={props.modelName} />; });
jest.mock('../CanvasToolbar', () => (props: any) => { capturedCanvasToolbarProps = props; return <div data-testid="canvas-toolbar" />; });
jest.mock('../PanelErrorBoundary', () => ({ children }: any) => <>{children}</>);
jest.mock('../PluginPanelContainer', () => (_props: any) => null);

describe('Flow', () => {
  const defaultProps = {
    settings: {
      modeler: { modelId: 'test-1' },
      modeler_api: {},
    },
    drupal: {
      ajax: jest.fn(),
    },
  };

  beforeEach(() => {
    jest.clearAllMocks();
    capturedToolbarProps = {};
    capturedModalsProps = {};
    capturedFlowCanvasProps = {};
    capturedPropertyPanelProps = {};
    capturedCanvasToolbarProps = {};
    // clearAllMocks() does NOT reset implementations set via mockReturnValue, so
    // a replayData payload from a prior test would leak and (now) trip the
    // auto-enter-review-on-open effect. Restore the empty default each test.
    const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
    useModelDataLoader.mockReturnValue({ replayData: [] });
  });

  describe('rendering', () => {
    it('should render the workflow modeler container', () => {
      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-modeler')).toBeTruthy();
    });

    it('should render toolbar', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('toolbar')).toBeTruthy();
    });

    it('should pass model name to toolbar', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('toolbar').getAttribute('data-model-name')).toBe('Test Model');
    });

    it('should render canvas', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('flow-canvas')).toBeTruthy();
    });

    it('should render property panel', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('property-panel')).toBeTruthy();
    });

    it('should render modals', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('modals')).toBeTruthy();
    });

    it('should render content area', () => {
      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-modeler-content')).toBeTruthy();
    });

    it('should render messages container', () => {
      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-messages-container')).toBeTruthy();
    });
  });

  describe('toolbar callbacks', () => {
    it('should call exportModelData when onSave is invoked', () => {
      const { exportModelData } = require('../../utils/modelUtils');
      render(<Flow {...defaultProps} />);
      capturedToolbarProps.onSave();
      expect(exportModelData).toHaveBeenCalled();
    });

    it('should call handleSaveComplete when onSaveComplete is invoked', () => {
      const { useCloseHandler } = require('../../hooks/useCloseHandler');
      const mockHandleSaveComplete = jest.fn();
      useCloseHandler.mockReturnValue({
        handleClose: jest.fn(),
        handleSaveComplete: mockHandleSaveComplete,
        saveButtonRef: { current: null },
      });
      render(<Flow {...defaultProps} />);
      capturedToolbarProps.onSaveComplete();
      expect(mockHandleSaveComplete).toHaveBeenCalled();
    });

    it('should pass isLocked=false when not in read-only mode', () => {
      render(<Flow {...defaultProps} />);
      // isLocked is now derived from isReadOnly (no user toggle)
      expect(capturedToolbarProps.isLocked).toBe(false);
    });

    it('should show Untitled Workflow when modelData has no label', () => {
      const { useModelStore } = require('../../store/useModelStore');
      useModelStore.mockImplementation((selector: any) => {
        const state = {
          modelData: { metadata: {} },
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      render(<Flow {...defaultProps} />);
      expect(capturedToolbarProps.modelName).toBe('Untitled Workflow');
    });
  });

  describe('new model initial state', () => {
    it('should open metadata modal for new models', () => {
      jest.useFakeTimers();
      const { useModalState } = require('../../hooks/useModalState');
      const mockOpenMetadata = jest.fn();
      useModalState.mockReturnValue({
        showMetadataModal: false, showConfirmDialog: false,
        confirmDialogTitle: '', confirmDialogMessage: '',
        confirmDialogType: 'info', confirmDialogLoading: false,
        onMetadataSubmit: jest.fn(), showConfirmationDialog: jest.fn(),
        handleConfirmDialog: jest.fn(), handleCancelDialog: jest.fn(),
        handleCloseWithoutSave: jest.fn(),
        openMetadataModal: mockOpenMetadata, closeMetadataModal: jest.fn(),
      });

      render(<Flow settings={{ modeler: {}, modeler_api: { isNew: true } }} drupal={{ ajax: jest.fn() }} />);

      act(() => { jest.advanceTimersByTime(150); });
      expect(mockOpenMetadata).toHaveBeenCalled();
      jest.useRealTimers();
    });
  });

  describe('handleCloseMetadataModal', () => {
    it('should close metadata modal and open event popup for new models', () => {
      jest.useFakeTimers();
      const { useModalState } = require('../../hooks/useModalState');
      const mockCloseMetadata = jest.fn();
      useModalState.mockReturnValue({
        showMetadataModal: true, showConfirmDialog: false,
        confirmDialogTitle: '', confirmDialogMessage: '',
        confirmDialogType: 'info', confirmDialogLoading: false,
        onMetadataSubmit: jest.fn(), showConfirmationDialog: jest.fn(),
        handleConfirmDialog: jest.fn(), handleCancelDialog: jest.fn(),
        handleCloseWithoutSave: jest.fn(),
        openMetadataModal: jest.fn(), closeMetadataModal: mockCloseMetadata,
      });

      render(<Flow settings={{ modeler: {}, modeler_api: { isNew: true } }} drupal={{ ajax: jest.fn() }} />);
      
      act(() => { capturedModalsProps.onCloseMetadataModal(); });
      expect(mockCloseMetadata).toHaveBeenCalled();

      // Should open event popup after 150ms for new models
      act(() => { jest.advanceTimersByTime(200); });
      expect(capturedToolbarProps.isEventPopupOpen).toBe(true);
      jest.useRealTimers();
    });

    it('should close metadata modal without opening event popup for existing models', () => {
      jest.useFakeTimers();
      const { useModalState } = require('../../hooks/useModalState');
      const mockCloseMetadata = jest.fn();
      useModalState.mockReturnValue({
        showMetadataModal: true, showConfirmDialog: false,
        confirmDialogTitle: '', confirmDialogMessage: '',
        confirmDialogType: 'info', confirmDialogLoading: false,
        onMetadataSubmit: jest.fn(), showConfirmationDialog: jest.fn(),
        handleConfirmDialog: jest.fn(), handleCancelDialog: jest.fn(),
        handleCloseWithoutSave: jest.fn(),
        openMetadataModal: jest.fn(), closeMetadataModal: mockCloseMetadata,
      });

      render(<Flow settings={{ modeler: {}, modeler_api: { isNew: false } }} drupal={{ ajax: jest.fn() }} />);
      
      act(() => { capturedModalsProps.onCloseMetadataModal(); });
      expect(mockCloseMetadata).toHaveBeenCalled();

      act(() => { jest.advanceTimersByTime(200); });
      expect(capturedToolbarProps.isEventPopupOpen).toBe(false);
      jest.useRealTimers();
    });
  });

  describe('handleReplayStepSelect', () => {
    it('should activate replay mode when selecting a positive step', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: true,
      });
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: [{ type: 'action', id: 'n1' }] });

      render(<Flow {...defaultProps} />);

      // Replay content now lives inside the unified PropertyPanel. The panel
      // receives onSelectReplayStep which wraps handleReplayStepSelect.
      act(() => { capturedPropertyPanelProps.onSelectReplayStep(0); });
      // Should have set replay mode to true
      expect(capturedPropertyPanelProps.isReplayMode).toBe(true);
    });
  });

  describe('useViewportActions integration', () => {
    it('should call useViewportActions and receive viewport actions object', () => {
      const { useViewportActions } = require('../../hooks/useViewportActions');
      render(<Flow {...defaultProps} />);
      expect(useViewportActions).toHaveBeenCalled();
    });
  });

  describe('useKeyboardShortcuts callbacks', () => {
    it('should pass onToggleSearch callback that focuses search input', () => {
      const { useKeyboardShortcuts } = require('../../hooks/useKeyboardShortcuts');
      let capturedCallbacks: any;
      useKeyboardShortcuts.mockImplementation((opts: any) => {
        capturedCallbacks = opts.callbacks;
      });

      render(<Flow {...defaultProps} />);

      // Create a mock search input in the DOM
      const searchInput = document.createElement('input');
      searchInput.className = 'search-input';
      document.body.appendChild(searchInput);
      const focusSpy = jest.spyOn(searchInput, 'focus');

      capturedCallbacks.onToggleSearch();
      expect(focusSpy).toHaveBeenCalled();

      document.body.removeChild(searchInput);
    });

    it('should pass onEscape callback that clears search and blurs input', () => {
      const { useKeyboardShortcuts } = require('../../hooks/useKeyboardShortcuts');
      let capturedCallbacks: any;
      useKeyboardShortcuts.mockImplementation((opts: any) => {
        capturedCallbacks = opts.callbacks;
      });
      const { useSearch } = require('../../hooks/useSearch');
      const mockClearSearch = jest.fn();
      useSearch.mockReturnValue({
        searchTerm: 'test', highlightedSearchResult: null,
        onSearchHighlight: jest.fn(), onSearchFocus: jest.fn(),
        clearSearch: mockClearSearch,
      });

      render(<Flow {...defaultProps} />);

      // Create a mock search input and make it the active element
      const searchInput = document.createElement('input');
      searchInput.className = 'search-input';
      document.body.appendChild(searchInput);
      searchInput.focus();
      const blurSpy = jest.spyOn(searchInput, 'blur');

      capturedCallbacks.onEscape();
      expect(mockClearSearch).toHaveBeenCalled();
      expect(blurSpy).toHaveBeenCalled();

      document.body.removeChild(searchInput);
    });

    describe('review mode toggle (Alt+Shift+R)', () => {
      const mockSetPropertyPanelCollapsed = jest.fn();

      // The panel store mock is module-global, so every override must be undone
      // (back to the default 'event' mode) or it leaks into later tests.
      function mockPanelStore(panelMode: 'event' | 'review', propertyPanelCollapsed = false) {
        const { usePanelStore } = require('../../store/usePanelStore');
        usePanelStore.mockImplementation((selector: any) => {
          const state = {
            replayPanelCollapsed: false,
            toggleReplayPanelCollapse: jest.fn(),
            setReplayPanelCollapsed: jest.fn(),
            setPropertyPanelCollapsed: mockSetPropertyPanelCollapsed,
            propertyPanelCollapsed,
            panelMode,
            setPanelMode: mockSetPanelMode,
          };
          return typeof selector === 'function' ? selector(state) : state;
        });
      }

      function captureShortcutOptions() {
        const { useKeyboardShortcuts } = require('../../hooks/useKeyboardShortcuts');
        let captured: any;
        useKeyboardShortcuts.mockImplementation((opts: any) => { captured = opts; });
        return () => captured;
      }

      afterEach(() => {
        mockPanelStore('event');
        mockSetPropertyPanelCollapsed.mockClear();
        const { useGraphStore } = require('../../store/useGraphStore');
        useGraphStore.mockImplementation((selector: any) => {
          const state = { nodes: [], edges: [], setNodes: jest.fn(), setEdges: jest.fn() };
          return typeof selector === 'function' ? selector(state) : state;
        });
        const { useSelectionStore } = require('../../store/useSelectionStore');
        useSelectionStore.mockImplementation((selector: any) => {
          const state = {
            selectedNode: null,
            setSelectedNode: jest.fn(),
            selectedEdge: null, setSelectedEdge: jest.fn(),
            selectedNodes: [], selectedEdges: [],
            setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
          };
          return typeof selector === 'function' ? selector(state) : state;
        });
      });

      it('should LEAVE review mode (back to the event view) when already reviewing', () => {
        const options = captureShortcutOptions();
        mockPanelStore('review');
        render(<Flow {...defaultProps} />);
        mockSetPanelMode.mockClear();

        act(() => { options().callbacks.onToggleReviewMode(); });

        expect(mockSetPanelMode).toHaveBeenCalledWith('event');
      });

      // Select a start/event node so review entry is scoped to it. The store
      // mocks are module-global, so the afterEach above restores the defaults.
      function selectEventNode(id: string) {
        const node = { id, type: 'start', data: {}, position: { x: 0, y: 0 } };
        const { useGraphStore } = require('../../store/useGraphStore');
        useGraphStore.mockImplementation((selector: any) => {
          const state = { nodes: [node], edges: [], setNodes: jest.fn(), setEdges: jest.fn() };
          return typeof selector === 'function' ? selector(state) : state;
        });
        const { useSelectionStore } = require('../../store/useSelectionStore');
        useSelectionStore.mockImplementation((selector: any) => {
          const state = {
            selectedNode: node,
            setSelectedNode: jest.fn(),
            selectedEdge: null, setSelectedEdge: jest.fn(),
            selectedNodes: [], selectedEdges: [],
            setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
          };
          return typeof selector === 'function' ? selector(state) : state;
        });
      }

      it('should ENTER review mode through the guarded per-event path (requestReviewMode)', () => {
        const options = captureShortcutOptions();
        mockPanelStore('event');
        selectEventNode('event_1');
        render(
          <Flow
            {...defaultProps}
            settings={{
              modeler: { modelId: 'test-1' },
              modeler_api: {
                isNew: false,
                replay_url: '/api/replay',
                test_url: '/api/test',
                permissions: { replay: true, test: true },
              },
            }}
          />,
        );
        mockSetPanelMode.mockClear();

        act(() => { options().callbacks.onToggleReviewMode(); });

        // Entering goes through requestReviewMode → enterReviewForNode, so the
        // session is started for the selected event (listener + history load),
        // not merely flipped to the review view.
        expect(mockSetPanelMode).toHaveBeenCalledWith('review');
        expect(mockStartTest).toHaveBeenCalledWith('event_1');
        expect(mockLoadReplayData).toHaveBeenCalledWith('event_1');
      });

      it('should expand a collapsed property panel before toggling', () => {
        const options = captureShortcutOptions();
        mockPanelStore('review', true);
        render(<Flow {...defaultProps} />);

        act(() => { options().callbacks.onToggleReviewMode(); });

        expect(mockSetPropertyPanelCollapsed).toHaveBeenCalledWith(false);
        expect(mockSetPanelMode).toHaveBeenCalledWith('event');
      });

      it('should NOT offer the toggle for a new model with no replay capability', () => {
        const options = captureShortcutOptions();
        mockPanelStore('event');
        render(
          <Flow
            {...defaultProps}
            settings={{ modeler: { modelId: 'test-1' }, modeler_api: { isNew: true } }}
          />,
        );

        expect(options().capabilities.canToggleReviewMode).toBe(false);
      });

      it('should ALWAYS offer the toggle while in review mode, so the user can get out', () => {
        const options = captureShortcutOptions();
        mockPanelStore('review');
        render(
          <Flow
            {...defaultProps}
            settings={{ modeler: { modelId: 'test-1' }, modeler_api: { isNew: true } }}
          />,
        );

        expect(options().capabilities.canToggleReviewMode).toBe(true);
      });
    });
  });

  describe('replay panel rendering', () => {
    // Replay content was merged into the unified PropertyPanel (single panel).
    // There is no longer a standalone replay column; instead the replay state
    // is threaded into PropertyPanel and the "Review flow" capability flag
    // controls whether the review toggle is enabled.
    it('should render exactly one right-hand panel (no separate replay column)', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: true,
      });
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: [{ type: 'action', id: 'n1' }] });

      render(<Flow {...defaultProps} />);
      // The single unified panel is present...
      expect(screen.getByTestId('property-panel')).toBeTruthy();
      // ...and the old standalone replay column is gone.
      expect(screen.queryByTestId('replay-panel')).toBeNull();
      // Replay data is threaded into the unified panel.
      expect(capturedPropertyPanelProps.replayData).toEqual([{ type: 'action', id: 'n1' }]);
    });

    it('should always render the single PropertyPanel even when no replay data', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: false,
      });
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: [] });
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('property-panel')).toBeTruthy();
      expect(screen.queryByTestId('replay-panel')).toBeNull();
    });

    it('should pass step data when current step is valid', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: true,
      });
      const replaySteps = [
        { type: 'action', id: 'n1', data: { key: 'val' }, successorId: 'n2', object: 'Obj' },
      ];
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: replaySteps });

      // Make useSimpleReplaySync actually set the step when handleReplayStepSelect is called
      const { useSimpleReplaySync } = require('../../hooks/useSimpleReplaySync');
      let _mockSetCurrentStep: ((step: number) => void) | null = null;
      useSimpleReplaySync.mockImplementation((opts: any) => {
        _mockSetCurrentStep = opts.setCurrentStep;
        return {
          isSyncing: false,
          handleCanvasNodeClick: jest.fn(),
          handleCanvasEdgeClick: jest.fn(),
          handleReplayStepSelect: (step: number) => {
            opts.setCurrentStep(step);
          },
        };
      });

      render(<Flow {...defaultProps} />);

      // Select step 0
      act(() => { capturedPropertyPanelProps.onSelectReplayStep(0); });

      // Now step data and info should be passed to the unified PropertyPanel
      expect(capturedPropertyPanelProps.stepData).toEqual({ key: 'val' });
      expect(capturedPropertyPanelProps.stepInfo).toEqual(expect.objectContaining({ type: 'action', id: 'n1' }));
    });

    it('should pass null step data when no step selected', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: true,
      });
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: [{ type: 'action', id: 'n1' }] });

      render(<Flow {...defaultProps} />);
      // currentReplayStep defaults to -1
      expect(capturedPropertyPanelProps.stepData).toBeNull();
      expect(capturedPropertyPanelProps.stepInfo).toBeNull();
    });
  });

  describe('property panel props', () => {
    it('should pass selectedNodes mapped from store IDs', () => {
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = {
          nodes: [{ id: 'n1', data: {}, position: { x: 0, y: 0 } }, { id: 'n2', data: {}, position: { x: 1, y: 1 } }],
          edges: [], setNodes: jest.fn(), setEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useSelectionStore } = require('../../store/useSelectionStore');
      useSelectionStore.mockImplementation((selector: any) => {
        const state = {
          selectedNode: null, setSelectedNode: jest.fn(),
          selectedEdge: null, setSelectedEdge: jest.fn(),
          selectedNodes: ['n1', 'n2'], selectedEdges: [],
          setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });

      render(<Flow {...defaultProps} />);
      expect(capturedPropertyPanelProps.selectedNodes).toHaveLength(2);
      expect(capturedPropertyPanelProps.selectedNodes[0].id).toBe('n1');
    });

    it('should filter out selectedNodes with missing IDs', () => {
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = {
          nodes: [{ id: 'n1', data: {}, position: { x: 0, y: 0 } }],
          edges: [], setNodes: jest.fn(), setEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useSelectionStore } = require('../../store/useSelectionStore');
      useSelectionStore.mockImplementation((selector: any) => {
        const state = {
          selectedNode: null, setSelectedNode: jest.fn(),
          selectedEdge: null, setSelectedEdge: jest.fn(),
          selectedNodes: ['n1', 'n_nonexistent'], selectedEdges: ['e_missing'],
          setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });

      render(<Flow {...defaultProps} />);
      expect(capturedPropertyPanelProps.selectedNodes).toHaveLength(1);
      expect(capturedPropertyPanelProps.selectedEdges).toHaveLength(0);
    });
  });

  describe('messages container visibility', () => {
    it('should apply visible class when messages are visible', () => {
      const { useMessagesContainer } = require('../../hooks/useMessagesContainer');
      useMessagesContainer.mockReturnValue({
        messagesContainerRef: { current: null },
        messagesVisible: true,
        hasMessages: true,
        handleToggleMessages: jest.fn(),
        handleClearMessages: jest.fn(),
      });

      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-messages-container.visible')).toBeTruthy();
    });

    it('should apply hidden class when messages are not visible', () => {
      const { useMessagesContainer } = require('../../hooks/useMessagesContainer');
      useMessagesContainer.mockReturnValue({
        messagesContainerRef: { current: null },
        messagesVisible: false,
        hasMessages: false,
        handleToggleMessages: jest.fn(),
        handleClearMessages: jest.fn(),
      });
      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-messages-container.hidden')).toBeTruthy();
    });

    it('should have role="log" and aria-live attributes for screen readers', () => {
      const { container } = render(<Flow {...defaultProps} />);
      const messagesContainer = container.querySelector('.workflow-messages-container');
      expect(messagesContainer).toBeTruthy();
      expect(messagesContainer?.getAttribute('role')).toBe('log');
      expect(messagesContainer?.getAttribute('aria-label')).toBe('Workflow messages');
      expect(messagesContainer?.getAttribute('aria-live')).toBe('polite');
      expect(messagesContainer?.getAttribute('aria-relevant')).toBe('additions removals');
    });
  });

  describe('connection + init handlers', () => {
    it('should pass onConnectStart, onConnectEnd, and onInit to the canvas', () => {
      render(<Flow {...defaultProps} />);
      // onConnectStart/onConnectEnd now come from useFlowEventHandlers (issue
      // #3585553 follow-on UX — drop a NEW edge onto a node body); onInit is
      // still defined locally in Flow. Drag-to-create (onDrop/onDragOver/etc.)
      // was removed entirely (issue #3589093), so those handlers do not exist.
      const eh = capturedFlowCanvasProps.eventHandlers;
      expect(typeof eh.onConnectStart).toBe('function');
      expect(typeof eh.onConnectEnd).toBe('function');
      expect(typeof eh.onInit).toBe('function');
      // Calling them should not throw.
      eh.onInit();
    });
  });

  describe('read-only mode', () => {
    const readOnlySettings = {
      modeler: { modelId: 'test-1' },
      modeler_api: { readOnly: true },
    };
    const readOnlyProps = {
      settings: readOnlySettings,
      drupal: { ajax: jest.fn() },
    };

    it('should pass isLocked=true to toolbar when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedToolbarProps.isLocked).toBe(true);
    });

    it('should pass isReadOnly=true to toolbar when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedToolbarProps.isReadOnly).toBe(true);
    });

    it('should not pass isReadOnly to toolbar when readOnly is not set', () => {
      render(<Flow {...defaultProps} />);
      expect(capturedToolbarProps.isReadOnly).toBe(false);
    });

    it('should pass isLocked=true to canvas when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedFlowCanvasProps.uiState.isLocked).toBe(true);
    });

    it('should pass isLocked=true to property panel when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedPropertyPanelProps.isLocked).toBe(true);
    });

    it('should pass empty contexts to canvas toolbar when readOnly is set', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'test-1' },
            modeler_api: {
              readOnly: true,
              contexts: [{ id: 'ctx_1', topic: 'Content', model_owner: 'test_owner', components: {} }],
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedCanvasToolbarProps.contexts).toEqual([]);
    });

    it('should pass canEditMetadata=false to modals when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedModalsProps.canEditMetadata).toBe(false);
    });

    it('should pass canCreateTemplate=false to modals when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedModalsProps.canCreateTemplate).toBe(false);
    });

    it('should disable keyboard shortcuts for delete/copy/paste when readOnly is set', () => {
      const { useKeyboardShortcuts } = require('../../hooks/useKeyboardShortcuts');
      let capturedCapabilities: any;
      useKeyboardShortcuts.mockImplementation((opts: any) => {
        capturedCapabilities = opts.capabilities;
      });

      render(<Flow {...readOnlyProps} />);
      expect(capturedCapabilities.canDelete).toBe(false);
      expect(capturedCapabilities.canCopy).toBe(false);
      expect(capturedCapabilities.canPaste).toBe(false);
    });

    it('should always have isLocked=true in read-only mode (no user toggle)', () => {
      render(<Flow {...readOnlyProps} />);
      // isLocked is derived directly from isReadOnly — no user toggle exists
      expect(capturedToolbarProps.isLocked).toBe(true);
    });
  });

  describe('template edit blocked → read-only fallback', () => {
    // When the model is an existing template and the user lacks the
    // "edit template" permission, the modeler falls back to read-only mode.
    const blockedSettings = {
      modeler: { modelId: 'template-1' },
      modeler_api: {
        metadata: { template: true },
        permissions: { 'edit template': false },
      },
    };
    const blockedProps = {
      settings: blockedSettings,
      drupal: { ajax: jest.fn() },
    };

    it('should enter read-only mode for existing template when edit template is denied', () => {
      render(<Flow {...blockedProps} />);
      expect(capturedToolbarProps.isReadOnly).toBe(true);
      expect(capturedToolbarProps.isLocked).toBe(true);
    });

    it('should NOT enter read-only mode for new template models', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'new-template' },
            modeler_api: {
              isNew: true,
              metadata: { template: true },
              permissions: { 'edit template': false },
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedToolbarProps.isReadOnly).toBe(false);
    });

    it('should NOT enter read-only mode when edit template permission is granted', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'template-ok' },
            modeler_api: {
              metadata: { template: true },
              permissions: { 'edit template': true },
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedToolbarProps.isReadOnly).toBe(false);
    });

    it('should NOT enter read-only mode for non-template models', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'non-template' },
            modeler_api: {
              permissions: { 'edit template': false },
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedToolbarProps.isReadOnly).toBe(false);
    });

    it('should pass canEditMetadata=false to modals when template edit is blocked', () => {
      render(<Flow {...blockedProps} />);
      expect(capturedModalsProps.canEditMetadata).toBe(false);
    });

    it('should pass canCreateTemplate=false to modals when template edit is blocked', () => {
      render(<Flow {...blockedProps} />);
      expect(capturedModalsProps.canCreateTemplate).toBe(false);
    });

    it('should pass empty contexts when template edit is blocked', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'template-ctx' },
            modeler_api: {
              metadata: { template: true },
              permissions: { 'edit template': false },
              contexts: [{ id: 'ctx_1', topic: 'Content', model_owner: 'test_owner', components: {} }],
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedCanvasToolbarProps.contexts).toEqual([]);
    });
  });

  // ── requestReviewMode: enters review directly (NO unsaved-changes guard) ────
  // The unsaved-changes guard on review entry was removed (too much friction);
  // review/listening now proceed even with unsaved changes. Structural
  // validation and the brand-new-model gate still apply.
  describe('requestReviewMode (no unsaved-changes guard)', () => {
    // Settings for a SAVED, review-capable model.
    const reviewProps = {
      settings: {
        modeler: { modelId: 'rev-1' },
        modeler_api: {
          isNew: false,
          replay_url: '/api/replay',
          test_url: '/api/test',
          permissions: { replay: true, test: true },
        },
      },
      drupal: { ajax: jest.fn() },
    };

    function setupModalSpy() {
      const showConfirmationDialog = jest.fn();
      const { useModalState } = require('../../hooks/useModalState');
      useModalState.mockReturnValue({
        showMetadataModal: false,
        showConfirmDialog: false,
        confirmDialogTitle: '',
        confirmDialogMessage: '',
        confirmDialogType: 'info',
        confirmDialogLoading: false,
        onMetadataSubmit: jest.fn(),
        showConfirmationDialog,
        handleConfirmDialog: jest.fn(),
        handleCancelDialog: jest.fn(),
        handleCloseWithoutSave: jest.fn(),
        openMetadataModal: jest.fn(),
        closeMetadataModal: jest.fn(),
      });
      return showConfirmationDialog;
    }

    // `selectEventNode` (defined later in this describe) selects a start/event
    // node so requestReviewMode resolves to "start a new session for it".

    it('should enter review immediately when there are NO unsaved changes', () => {
      setupModalSpy();
      selectEventNode();
      render(<Flow {...reviewProps} />);

      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
    });

    it('should enter review DIRECTLY (no confirmation dialog) even with unsaved changes', () => {
      const showConfirmationDialog = setupModalSpy();
      selectEventNode();
      render(<Flow {...reviewProps} />);

      // Mark the model dirty via the canvas callback Flow passes down.
      act(() => { capturedFlowCanvasProps.setHasUnsavedChanges(true); });
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      // No unsaved-changes guard modal — review proceeds straight away.
      expect(showConfirmationDialog).not.toHaveBeenCalled();
      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
    });

    it('should abort (no modal, no switch) when validation fails', () => {
      const showConfirmationDialog = setupModalSpy();
      // A placeholder node makes validateBeforeSave return an error.
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = {
          nodes: [{ id: 'ph1', type: 'placeholder', data: {}, position: { x: 0, y: 0 } }],
          edges: [], setNodes: jest.fn(), setEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      render(<Flow {...reviewProps} />);

      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      expect(showConfirmationDialog).not.toHaveBeenCalled();
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('review');
    });

    // Select a start/event node so review is scoped to it.
    function selectEventNode(id = 'event_1') {
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = {
          nodes: [{ id, type: 'start', data: {}, position: { x: 0, y: 0 } }],
          edges: [], setNodes: jest.fn(), setEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useSelectionStore } = require('../../store/useSelectionStore');
      useSelectionStore.mockImplementation((selector: any) => {
        const state = {
          selectedNode: { id, type: 'start', data: {}, position: { x: 0, y: 0 } },
          setSelectedNode: jest.fn(),
          selectedEdge: null, setSelectedEdge: jest.fn(),
          selectedNodes: [], selectedEdges: [],
          setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
    }

    it('should auto-start the listener AND load history (in parallel) on review entry, scoped to the selected event', () => {
      setupModalSpy();
      selectEventNode('event_1');
      render(<Flow {...reviewProps} />);

      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      // Entered review…
      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
      // …and BOTH the live listener and the history load fired for the event id.
      expect(mockStartTest).toHaveBeenCalledWith('event_1');
      expect(mockLoadReplayData).toHaveBeenCalledWith('event_1');
    });
  });

  // ── Auto-enter Review when a model OPENS with saved replayData ──────────────
  describe('auto-enter Review on open with saved replayData', () => {
    const savedProps = {
      settings: {
        modeler: { modelId: 'auto-1' },
        modeler_api: {
          isNew: false,
          replay_url: '/api/replay',
          test_url: '/api/test',
          permissions: { replay: true, test: true },
        },
      },
      drupal: { ajax: jest.fn() },
    };

    // A single-event flow: start → action. The replay step references the action
    // node so the owning-event resolver lands on event_1.
    function mockSingleFlow() {
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = {
          nodes: [
            { id: 'event_1', type: 'start', data: {}, position: { x: 0, y: 0 } },
            { id: 'action_1', type: 'action', data: {}, position: { x: 0, y: 0 } },
          ],
          edges: [{ id: 'e1', source: 'event_1', target: 'action_1' }],
          setNodes: jest.fn(),
          setEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
    }

    function withReplayData(replayData: any[]) {
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData });
    }

    it('switches the panel to review mode when settings carry replayData', () => {
      mockSingleFlow();
      withReplayData([{ type: 'action', id: 'action_1', data: {} }]);

      act(() => { render(<Flow {...savedProps} />); });

      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
    });

    it('activates the review session so PropertyPanel shows the Replay view', () => {
      mockSingleFlow();
      withReplayData([{ type: 'action', id: 'action_1', data: {} }]);

      act(() => { render(<Flow {...savedProps} />); });

      // replaySessionActive requires an active reviewed event + data present.
      expect(capturedPropertyPanelProps.replaySessionActive).toBe(true);
    });

    it('surfaces the embedded data as a SELECTED entry so the steps render', () => {
      mockSingleFlow();
      const steps = [{ type: 'action', id: 'action_1', data: {} }];
      withReplayData(steps);

      act(() => { render(<Flow {...savedProps} />); });

      // The Replay body only shows steps when a data entry is selected. The
      // embedded data must therefore be wrapped in a selected entry (index 0),
      // not merely passed as replayData.
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(0);
      expect(capturedPropertyPanelProps.replayEntries).toHaveLength(1);
      expect(capturedPropertyPanelProps.replayEntries[0].history).toEqual(steps);
      expect(capturedPropertyPanelProps.replayData).toEqual(steps);
    });

    it('still auto-enters review when the graph loads AFTER mount (async store population)', () => {
      // Mirror the live app: settings carry replayData immediately, but the graph
      // store starts empty and is populated on a later render.
      const steps = [{ type: 'action', id: 'action_1', data: {} }];
      withReplayData(steps);
      const { useGraphStore } = require('../../store/useGraphStore');
      const emptyState = { nodes: [], edges: [], setNodes: jest.fn(), setEdges: jest.fn() };
      useGraphStore.mockImplementation((selector: any) =>
        typeof selector === 'function' ? selector(emptyState) : emptyState,
      );

      const { rerender } = render(<Flow {...savedProps} />);
      // No graph yet → must NOT have entered review.
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('review');

      // Graph arrives on a later render.
      mockSingleFlow();
      act(() => { rerender(<Flow {...savedProps} />); });

      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(0);
    });

    it('shows the embedded data only — does NOT start the live listener or reload history', () => {
      mockSingleFlow();
      withReplayData([{ type: 'action', id: 'action_1', data: {} }]);

      act(() => { render(<Flow {...savedProps} />); });

      expect(mockStartTest).not.toHaveBeenCalled();
      expect(mockLoadReplayData).not.toHaveBeenCalled();
    });

    // A multi-event model: two independent flows. selectedStartNodeId is null
    // (more than one start node, none selected), so resolution must come from the
    // replay step itself or the first-start-node fallback.
    function mockMultiFlow() {
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = {
          nodes: [
            { id: 'event_1', type: 'start', data: {}, position: { x: 0, y: 0 } },
            { id: 'action_1', type: 'action', data: {}, position: { x: 0, y: 0 } },
            { id: 'event_2', type: 'start', data: {}, position: { x: 0, y: 0 } },
            { id: 'action_2', type: 'action', data: {}, position: { x: 0, y: 0 } },
          ],
          edges: [
            { id: 'e1', source: 'event_1', target: 'action_1' },
            { id: 'e2', source: 'event_2', target: 'action_2' },
          ],
          setNodes: jest.fn(),
          setEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
    }

    it('enters review for a MULTI-event model using the owning event of the first step', () => {
      mockMultiFlow();
      // First step references action_2 → owned by event_2.
      withReplayData([{ type: 'action', id: 'action_2', data: {} }]);

      act(() => { render(<Flow {...savedProps} />); });

      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(0);
    });

    it('enters review for a MULTI-event model when the first step is the event "started" step', () => {
      mockMultiFlow();
      // A typical "started" step whose id IS the event id.
      withReplayData([{ type: 'started', id: 'event_2', data: {} }]);

      act(() => { render(<Flow {...savedProps} />); });

      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(0);
    });

    it('falls back to the FIRST start node for a multi-event model when nothing else resolves', () => {
      mockMultiFlow();
      // A step that matches neither a node nor an event id.
      withReplayData([{ type: 'action', id: 'ghost', data: {} }]);

      act(() => { render(<Flow {...savedProps} />); });

      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(0);
    });

    it('falls back to the single start node when the first step does not resolve to a node', () => {
      mockSingleFlow();
      // A step whose id matches no node — owning-event resolution fails, so the
      // single start node is used as the fallback target.
      withReplayData([{ type: 'action', id: 'ghost', data: {} }]);

      act(() => { render(<Flow {...savedProps} />); });

      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
    });

    it('does NOT auto-enter review when there is no saved replayData', () => {
      mockSingleFlow();
      withReplayData([]);

      act(() => { render(<Flow {...savedProps} />); });

      expect(mockSetPanelMode).not.toHaveBeenCalledWith('review');
    });
  });

  // ── Auto-exit Review on out-of-flow selection (Change 2) ────────────────────
  describe('Review auto-exit on out-of-flow selection', () => {
    const reviewProps = {
      settings: {
        modeler: { modelId: 'rev-2' },
        modeler_api: {
          isNew: false,
          replay_url: '/api/replay',
          test_url: '/api/test',
          permissions: { replay: true, test: true },
        },
      },
      drupal: { ajax: jest.fn() },
    };

    // Flow graph: event_1 → action_1 (in-flow). action_2 + event_2 are NOT
    // reachable from event_1 (out-of-flow).
    const flowNodes = [
      { id: 'event_1', type: 'start', data: {}, position: { x: 0, y: 0 } },
      { id: 'action_1', type: 'element', data: {}, position: { x: 0, y: 0 } },
      { id: 'action_2', type: 'element', data: {}, position: { x: 0, y: 0 } },
      { id: 'event_2', type: 'start', data: {}, position: { x: 0, y: 0 } },
    ];
    const flowEdges = [{ id: 'e1', source: 'event_1', target: 'action_1' }];

    function mockPanelReview() {
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelCollapsed: false,
          toggleReplayPanelCollapse: jest.fn(),
          setReplayPanelCollapsed: jest.fn(),
          setPropertyPanelCollapsed: jest.fn(),
          panelMode: 'review',
          setPanelMode: mockSetPanelMode,
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
    }

    function mockGraph() {
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = { nodes: flowNodes, edges: flowEdges, setNodes: jest.fn(), setEdges: jest.fn() };
        return typeof selector === 'function' ? selector(state) : state;
      });
    }

    function mockSelection(selectedNode: any) {
      const { useSelectionStore } = require('../../store/useSelectionStore');
      useSelectionStore.mockImplementation((selector: any) => {
        const state = {
          selectedNode,
          setSelectedNode: jest.fn(),
          selectedEdge: null, setSelectedEdge: jest.fn(),
          selectedNodes: [], selectedEdges: [],
          setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
    }

    // Active session for event_1: a running listener makes replaySessionActive
    // true once enterReviewForNode sets reviewedComponentId='event_1'.
    function mockActiveSession() {
      const { useTestRunner } = require('../../hooks/useTestRunner');
      useTestRunner.mockReturnValue({
        isTestRunning: true,
        isTestInitiating: false,
        testError: null,
        startTest: mockStartTest,
        cancelTest: jest.fn(),
        notifySaveComplete: jest.fn(),
      });
    }

    function mockReplaySync(isReplaySyncing = false) {
      const { useSimpleReplaySync } = require('../../hooks/useSimpleReplaySync');
      useSimpleReplaySync.mockReturnValue({
        isSyncing: false,
        isReplaySyncingRef: { current: isReplaySyncing },
        handleCanvasNodeClick: jest.fn(),
        handleCanvasEdgeClick: jest.fn(),
        handleReplayStepSelect: jest.fn(),
      });
    }

    /**
     * Start a session reviewing event_1, then re-render with `target` selected.
     * Returns after clearing setPanelMode calls made during session start, so
     * assertions reflect only the selection-driven auto-exit effect.
     */
    function reviewThenSelect(target: any, isReplaySyncing = false) {
      mockPanelReview();
      mockGraph();
      mockActiveSession();
      mockReplaySync(isReplaySyncing);
      // Start with the event selected so the session is scoped to event_1.
      mockSelection({ id: 'event_1', type: 'start', data: {}, position: { x: 0, y: 0 } });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      mockSetPanelMode.mockClear();
      // Now select the target node and re-render.
      mockSelection(target);
      rerender(<Flow {...reviewProps} />);
      return { rerender };
    }

    it('should EXIT to Properties when an OUT-OF-FLOW node is selected', () => {
      reviewThenSelect({ id: 'action_2', type: 'element', data: {}, position: { x: 0, y: 0 } });
      expect(mockSetPanelMode).toHaveBeenCalledWith('event');
    });

    it('should EXIT to Properties when a DIFFERENT event (separate flow) is selected', () => {
      reviewThenSelect({ id: 'event_2', type: 'start', data: {}, position: { x: 0, y: 0 } });
      expect(mockSetPanelMode).toHaveBeenCalledWith('event');
    });

    it('should STAY in Review when an IN-FLOW (reachable) node is selected', () => {
      reviewThenSelect({ id: 'action_1', type: 'element', data: {}, position: { x: 0, y: 0 } });
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('event');
    });

    it('should STAY in Review when the reviewed event itself stays selected', () => {
      reviewThenSelect({ id: 'event_1', type: 'start', data: {}, position: { x: 0, y: 0 } });
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('event');
    });

    it('should NOT exit when selection is replay-step-driven (isReplaySyncingRef)', () => {
      // Even an out-of-flow id is ignored while replay sync is in progress
      // (step-walking selects in-flow nodes anyway; the guard avoids churn).
      reviewThenSelect({ id: 'action_2', type: 'element', data: {}, position: { x: 0, y: 0 } }, true);
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('event');
    });

    it('should NOT exit when selection is cleared (background click)', () => {
      reviewThenSelect(null);
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('event');
    });
  });

  // ── Per-event review sessions (the event1→event2 bug) ───────────────────────
  describe('Per-event review sessions', () => {
    const reviewProps = {
      settings: {
        modeler: { modelId: 'rev-3' },
        modeler_api: {
          isNew: false,
          replay_url: '/api/replay',
          test_url: '/api/test',
          permissions: { replay: true, test: true },
        },
      },
      drupal: { ajax: jest.fn() },
    };

    // Two independent flows: event_1 → action_1, event_2 → action_2.
    const nodes = [
      { id: 'event_1', type: 'start', data: {}, position: { x: 0, y: 0 } },
      { id: 'action_1', type: 'element', data: {}, position: { x: 0, y: 0 } },
      { id: 'event_2', type: 'start', data: {}, position: { x: 0, y: 0 } },
      { id: 'action_2', type: 'element', data: {}, position: { x: 0, y: 0 } },
    ];
    const edges = [
      { id: 'e1', source: 'event_1', target: 'action_1' },
      { id: 'e2', source: 'event_2', target: 'action_2' },
    ];

    const mockCancelTest = jest.fn();

    function setup(panelMode: 'event' | 'review', selectedNode: any, opts: { running?: boolean } = {}) {
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelCollapsed: false,
          toggleReplayPanelCollapse: jest.fn(),
          setReplayPanelCollapsed: jest.fn(),
          setPropertyPanelCollapsed: jest.fn(),
          panelMode,
          setPanelMode: mockSetPanelMode,
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = { nodes, edges, setNodes: jest.fn(), setEdges: jest.fn() };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useSelectionStore } = require('../../store/useSelectionStore');
      useSelectionStore.mockImplementation((selector: any) => {
        const state = {
          selectedNode,
          setSelectedNode: jest.fn(),
          selectedEdge: null, setSelectedEdge: jest.fn(),
          selectedNodes: [], selectedEdges: [],
          setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useTestRunner } = require('../../hooks/useTestRunner');
      useTestRunner.mockReturnValue({
        isTestRunning: !!opts.running,
        isTestInitiating: false,
        testError: null,
        startTest: mockStartTest,
        cancelTest: mockCancelTest,
        notifySaveComplete: jest.fn(),
      });
      const { useSimpleReplaySync } = require('../../hooks/useSimpleReplaySync');
      useSimpleReplaySync.mockReturnValue({
        isSyncing: false,
        isReplaySyncingRef: { current: false },
        handleCanvasNodeClick: jest.fn(),
        handleCanvasEdgeClick: jest.fn(),
        handleReplayStepSelect: jest.fn(),
      });
    }

    const eventNode = (id: string) => ({ id, type: 'start', data: {}, position: { x: 0, y: 0 } });

    it('FIX: reviewing event_2 after event_1 enters event_2 session (no bounce, not event_1 data)', () => {
      // Review event_1 first.
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      // event_1 session started.
      expect(mockStartTest).toHaveBeenCalledWith('event_1');
      expect(mockLoadReplayData).toHaveBeenCalledWith('event_1');

      // Switch to Properties, select event_2.
      mockStartTest.mockClear();
      mockLoadReplayData.mockClear();
      mockSetPanelMode.mockClear();
      setup('event', eventNode('event_2'), { running: true });
      rerender(<Flow {...reviewProps} />);

      // Click "Review flow" on event_2 — event_2 has NO session → start it.
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      // Enters event_2's session (review mode), targeting event_2 — NOT event_1.
      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
      expect(mockStartTest).toHaveBeenCalledWith('event_2');
      expect(mockLoadReplayData).toHaveBeenCalledWith('event_2');
      expect(mockStartTest).not.toHaveBeenCalledWith('event_1');

      // Now reviewing event_2 with event_2 selected (in-flow) → must NOT bounce
      // back to Properties.
      mockSetPanelMode.mockClear();
      setup('review', eventNode('event_2'), { running: true });
      rerender(<Flow {...reviewProps} />);
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('event');
    });

    it('should RESUME an existing event session without re-listening', () => {
      // Start event_1, then start event_2 (event_1 now has a saved session).
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      setup('event', eventNode('event_2'), { running: true });
      rerender(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      // Return to event_1 (it now HAS a session) and click Review flow → resume.
      mockStartTest.mockClear();
      mockLoadReplayData.mockClear();
      mockSetPanelMode.mockClear();
      setup('event', eventNode('event_1'), { running: false });
      rerender(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      // Resumed: switched to review, but did NOT re-start the listener or reload.
      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
      expect(mockStartTest).not.toHaveBeenCalled();
      expect(mockLoadReplayData).not.toHaveBeenCalled();
    });

    it('should STOP event_1 listener when starting event_2 (one listener at a time)', () => {
      // Start event_1 (listener bound to event_1).
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      mockCancelTest.mockClear();

      // Start event_2 — must cancel event_1's listener first.
      setup('event', eventNode('event_2'), { running: true });
      rerender(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      expect(mockCancelTest).toHaveBeenCalled();
      expect(mockStartTest).toHaveBeenCalledWith('event_2');
    });
  });

  // ── Rework G: owning-event resolution for NON-event selections ──────────────
  // BUG 1: reviewing event_2, selecting an action UNDER event_1 then clicking
  //        "Review flow" must resume event_1's session (not no-op/bounce).
  // BUG 2: the "Review flow" button must HIDE for an action whose owning event
  //        has NO session (reviewableEventId === null).
  describe('Owning-event review resolution (Rework G)', () => {
    const reviewProps = {
      settings: {
        modeler: { modelId: 'rev-g' },
        modeler_api: {
          isNew: false,
          replay_url: '/api/replay',
          test_url: '/api/test',
          permissions: { replay: true, test: true },
        },
      },
      drupal: { ajax: jest.fn() },
    };

    // Three independent flows so there is NEVER a single auto-detected start
    // node (selectedStartNodeId is null for any non-event selection):
    //   event_1 → action_1 ; event_2 → action_2 ; event_3 → action_3
    const nodes = [
      { id: 'event_1', type: 'start', data: {}, position: { x: 0, y: 0 } },
      { id: 'action_1', type: 'element', data: {}, position: { x: 0, y: 0 } },
      { id: 'event_2', type: 'start', data: {}, position: { x: 0, y: 0 } },
      { id: 'action_2', type: 'element', data: {}, position: { x: 0, y: 0 } },
      { id: 'event_3', type: 'start', data: {}, position: { x: 0, y: 0 } },
      { id: 'action_3', type: 'element', data: {}, position: { x: 0, y: 0 } },
    ];
    const edges = [
      { id: 'e1', source: 'event_1', target: 'action_1' },
      { id: 'e2', source: 'event_2', target: 'action_2' },
      { id: 'e3', source: 'event_3', target: 'action_3' },
    ];

    const mockCancelTest = jest.fn();

    function setup(panelMode: 'event' | 'review', selectedNode: any, opts: { running?: boolean } = {}) {
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelCollapsed: false,
          toggleReplayPanelCollapse: jest.fn(),
          setReplayPanelCollapsed: jest.fn(),
          setPropertyPanelCollapsed: jest.fn(),
          panelMode,
          setPanelMode: mockSetPanelMode,
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = { nodes, edges, setNodes: jest.fn(), setEdges: jest.fn() };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useSelectionStore } = require('../../store/useSelectionStore');
      useSelectionStore.mockImplementation((selector: any) => {
        const state = {
          selectedNode,
          setSelectedNode: jest.fn(),
          selectedEdge: null, setSelectedEdge: jest.fn(),
          selectedNodes: [], selectedEdges: [],
          setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useTestRunner } = require('../../hooks/useTestRunner');
      useTestRunner.mockReturnValue({
        isTestRunning: !!opts.running,
        isTestInitiating: false,
        testError: null,
        startTest: mockStartTest,
        cancelTest: mockCancelTest,
        notifySaveComplete: jest.fn(),
      });
      const { useSimpleReplaySync } = require('../../hooks/useSimpleReplaySync');
      useSimpleReplaySync.mockReturnValue({
        isSyncing: false,
        isReplaySyncingRef: { current: false },
        handleCanvasNodeClick: jest.fn(),
        handleCanvasEdgeClick: jest.fn(),
        handleReplayStepSelect: jest.fn(),
      });
    }

    const eventNode = (id: string) => ({ id, type: 'start', data: {}, position: { x: 0, y: 0 } });
    const actionNode = (id: string) => ({ id, type: 'element', data: {}, position: { x: 0, y: 0 } });

    it('BUG1: reviewing event_2, select event_1\'s action, Review flow → resumes event_1 (no bounce)', () => {
      // Establish sessions for BOTH event_1 and event_2 (so event_1 has a saved
      // session that owns action_1). Start event_1, then event_2.
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      setup('event', eventNode('event_2'), { running: true });
      rerender(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      // event_2 is now the ACTIVE reviewed event.

      // Select action_1 (which lives UNDER event_1, NOT event_2's flow).
      mockStartTest.mockClear();
      mockLoadReplayData.mockClear();
      mockSetPanelMode.mockClear();
      setup('event', actionNode('action_1'), { running: true });
      rerender(<Flow {...reviewProps} />);

      // The owning event of action_1 is event_1 (it has a session).
      expect(capturedPropertyPanelProps.reviewableEventId).toBe('event_1');

      // Click "Review flow": must resume event_1's session (switch active to
      // event_1, enter review) WITHOUT starting a new listener/reload.
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
      expect(mockStartTest).not.toHaveBeenCalled();
      expect(mockLoadReplayData).not.toHaveBeenCalled();

      // Now reviewing event_1 with action_1 (in event_1's flow) selected → the
      // auto-exit effect must NOT bounce back to Properties.
      mockSetPanelMode.mockClear();
      setup('review', actionNode('action_1'), { running: true });
      rerender(<Flow {...reviewProps} />);
      expect(mockSetPanelMode).not.toHaveBeenCalledWith('event');
    });

    it('action whose owning event has NO session → reviewableEventId null, but STRUCTURAL owner starts a session on Review flow', () => {
      // Only event_1 gets a session. Select action_3 (belongs to event_3, which
      // has no session) while reviewing event_1.
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      setup('event', actionNode('action_3'), { running: true });
      rerender(<Flow {...reviewProps} />);

      // action_3 is in no session-flow → reviewableEventId is null (session-
      // gated), but it traces STRUCTURALLY to event_3.
      expect(capturedPropertyPanelProps.reviewableEventId).toBeNull();
      expect(capturedPropertyPanelProps.pickerOwningEventId).toBe('event_3');

      // Clicking Review flow now STARTS a review session for the structural
      // owning event (event_3) so the user can review action_3's flow.
      mockStartTest.mockClear();
      mockLoadReplayData.mockClear();
      mockSetPanelMode.mockClear();
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      expect(mockSetPanelMode).toHaveBeenCalledWith('review');
      expect(mockStartTest).toHaveBeenCalledWith('event_3');
      expect(mockLoadReplayData).toHaveBeenCalledWith('event_3');
    });

    it('orphan node (no owning event at all) → Review flow is a no-op (no start)', () => {
      // Only event_1 gets a session. Select an orphan node in no flow.
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      const orphan = { id: 'orphan', type: 'element', data: {}, position: { x: 0, y: 0 } };
      setup('event', orphan, { running: true });
      rerender(<Flow {...reviewProps} />);

      // No session and no structural owner → nothing to review.
      expect(capturedPropertyPanelProps.reviewableEventId).toBeNull();
      expect(capturedPropertyPanelProps.pickerOwningEventId).toBeNull();

      mockStartTest.mockClear();
      mockSetPanelMode.mockClear();
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      expect(mockStartTest).not.toHaveBeenCalled();
    });

    it('SHARED: a node reachable from the ACTIVE event keeps the active event (active-preferred)', () => {
      // Build a graph where `shared` is reachable from BOTH event_1 and event_2.
      const sharedNodes = [
        { id: 'event_1', type: 'start', data: {}, position: { x: 0, y: 0 } },
        { id: 'event_2', type: 'start', data: {}, position: { x: 0, y: 0 } },
        { id: 'shared', type: 'element', data: {}, position: { x: 0, y: 0 } },
      ];
      const sharedEdges = [
        { id: 's1', source: 'event_1', target: 'shared' },
        { id: 's2', source: 'event_2', target: 'shared' },
      ];
      function setupShared(panelMode: 'event' | 'review', selectedNode: any) {
        const { usePanelStore } = require('../../store/usePanelStore');
        usePanelStore.mockImplementation((selector: any) => {
          const state = {
            replayPanelCollapsed: false,
            toggleReplayPanelCollapse: jest.fn(),
            setReplayPanelCollapsed: jest.fn(),
            setPropertyPanelCollapsed: jest.fn(),
            panelMode,
            setPanelMode: mockSetPanelMode,
          };
          return typeof selector === 'function' ? selector(state) : state;
        });
        const { useGraphStore } = require('../../store/useGraphStore');
        useGraphStore.mockImplementation((selector: any) => {
          const state = { nodes: sharedNodes, edges: sharedEdges, setNodes: jest.fn(), setEdges: jest.fn() };
          return typeof selector === 'function' ? selector(state) : state;
        });
        const { useSelectionStore } = require('../../store/useSelectionStore');
        useSelectionStore.mockImplementation((selector: any) => {
          const state = {
            selectedNode,
            setSelectedNode: jest.fn(),
            selectedEdge: null, setSelectedEdge: jest.fn(),
            selectedNodes: [], selectedEdges: [],
            setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
          };
          return typeof selector === 'function' ? selector(state) : state;
        });
        const { useTestRunner } = require('../../hooks/useTestRunner');
        useTestRunner.mockReturnValue({
          isTestRunning: true,
          isTestInitiating: false,
          testError: null,
          startTest: mockStartTest,
          cancelTest: mockCancelTest,
          notifySaveComplete: jest.fn(),
        });
        const { useSimpleReplaySync } = require('../../hooks/useSimpleReplaySync');
        useSimpleReplaySync.mockReturnValue({
          isSyncing: false,
          isReplaySyncingRef: { current: false },
          handleCanvasNodeClick: jest.fn(),
          handleCanvasEdgeClick: jest.fn(),
          handleReplayStepSelect: jest.fn(),
        });
      }

      // Establish sessions for event_1 then event_2 (event_2 becomes active).
      setupShared('review', eventNode('event_1'));
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      setupShared('event', eventNode('event_2'));
      rerender(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      // Select the shared node — both events own it, active (event_2) preferred.
      setupShared('event', actionNode('shared'));
      rerender(<Flow {...reviewProps} />);
      expect(capturedPropertyPanelProps.reviewableEventId).toBe('event_2');
    });

    // ── Feature J caveat fix: structural (session-agnostic) picker owning event ─
    it('resolves pickerOwningEventId for an action node with NO session (multi-event model)', () => {
      // Fresh model, no session at all. action_1 lives under event_1; in this
      // multi-event model selectedStartNodeId is null, but the picker must still
      // resolve the owning event structurally.
      setup('event', actionNode('action_1'));
      render(<Flow {...reviewProps} />);
      expect(capturedPropertyPanelProps.pickerOwningEventId).toBe('event_1');
      // The Review-flow BUTTON gating is unchanged: still session-gated → null.
      expect(capturedPropertyPanelProps.reviewableEventId).toBeNull();
    });

    it('resolves pickerOwningEventId for the OTHER flow\'s action node (no session)', () => {
      setup('event', actionNode('action_2'));
      render(<Flow {...reviewProps} />);
      expect(capturedPropertyPanelProps.pickerOwningEventId).toBe('event_2');
    });

    it('pickerOwningEventId is null for a node outside every flow (no session)', () => {
      const orphan = { id: 'orphan', type: 'element', data: {}, position: { x: 0, y: 0 } };
      setup('event', orphan);
      render(<Flow {...reviewProps} />);
      expect(capturedPropertyPanelProps.pickerOwningEventId).toBeNull();
    });

    it('pickerOwningEventId resolves an EVENT node to itself (no session)', () => {
      setup('event', eventNode('event_3'));
      render(<Flow {...reviewProps} />);
      expect(capturedPropertyPanelProps.pickerOwningEventId).toBe('event_3');
    });
  });

  // ── Rework H: the persistent "listen" item state machine (A37-A48) ──────────
  describe('Listen item state machine (Rework H)', () => {
    const LISTEN = -2;
    const reviewProps = {
      settings: {
        modeler: { modelId: 'rev-h' },
        modeler_api: {
          isNew: false,
          replay_url: '/api/replay',
          test_url: '/api/test',
          permissions: { replay: true, test: true },
        },
      },
      drupal: { ajax: jest.fn() },
    };

    const nodes = [
      { id: 'event_1', type: 'start', data: {}, position: { x: 0, y: 0 } },
      { id: 'action_1', type: 'element', data: {}, position: { x: 0, y: 0 } },
      { id: 'event_2', type: 'start', data: {}, position: { x: 0, y: 0 } },
      { id: 'action_2', type: 'element', data: {}, position: { x: 0, y: 0 } },
    ];
    const edges = [
      { id: 'e1', source: 'event_1', target: 'action_1' },
      { id: 'e2', source: 'event_2', target: 'action_2' },
    ];
    const eventNode = (id: string) => ({ id, type: 'start', data: {}, position: { x: 0, y: 0 } });
    const mockCancelTest = jest.fn();

    function setup(
      panelMode: 'event' | 'review',
      selectedNode: any,
      opts: { running?: boolean; loading?: boolean } = {},
    ) {
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelCollapsed: false,
          toggleReplayPanelCollapse: jest.fn(),
          setReplayPanelCollapsed: jest.fn(),
          setPropertyPanelCollapsed: jest.fn(),
          panelMode,
          setPanelMode: mockSetPanelMode,
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = { nodes, edges, setNodes: jest.fn(), setEdges: jest.fn() };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useSelectionStore } = require('../../store/useSelectionStore');
      useSelectionStore.mockImplementation((selector: any) => {
        const state = {
          selectedNode,
          setSelectedNode: jest.fn(),
          selectedEdge: null, setSelectedEdge: jest.fn(),
          selectedNodes: [], selectedEdges: [],
          setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useTestRunner } = require('../../hooks/useTestRunner');
      useTestRunner.mockImplementation((props?: { onReplayDataReceived?: (d: unknown[]) => void }) => {
        if (props?.onReplayDataReceived) capturedOnReplayDataReceived = props.onReplayDataReceived;
        return {
          isTestRunning: !!opts.running,
          isTestInitiating: false,
          testError: null,
          startTest: mockStartTest,
          cancelTest: mockCancelTest,
          notifySaveComplete: jest.fn(),
        };
      });
      const { useReplayLoader } = require('../../hooks/useReplayLoader');
      useReplayLoader.mockImplementation(() => ({
        replayEntries: [],
        loading: !!opts.loading,
        error: null,
        emptyMessage: null,
        loadReplayData: mockLoadReplayData,
        clearReplayEntries: jest.fn(),
      }));
      const { useSimpleReplaySync } = require('../../hooks/useSimpleReplaySync');
      useSimpleReplaySync.mockReturnValue({
        isSyncing: false,
        isReplaySyncingRef: { current: false },
        handleCanvasNodeClick: jest.fn(),
        handleCanvasEdgeClick: jest.fn(),
        handleReplayStepSelect: jest.fn(),
      });
    }

    const dataEntry = (id: string, steps = 1) => ({
      model_id: 'rev-h',
      component_id: id,
      history: Array.from({ length: steps }, (_, i) => ({ type: 'action', id: `${id}_s${i}` })),
      timestamp: new Date().toISOString(),
      user: 'test',
      ip: '',
      url: '',
    });

    afterEach(() => {
      const { useReplayLoader } = require('../../hooks/useReplayLoader');
      // Restore the default (non-loading) loader mock for other suites.
      useReplayLoader.mockImplementation(() => ({
        replayEntries: [], loading: false, error: null, emptyMessage: null,
        loadReplayData: mockLoadReplayData, clearReplayEntries: jest.fn(),
      }));
    });

    it('A37: first review entry selects the LISTEN item and auto-starts the listener', () => {
      setup('review', eventNode('event_1'), { running: true });
      render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      expect(mockStartTest).toHaveBeenCalledWith('event_1');
      expect(mockLoadReplayData).toHaveBeenCalledWith('event_1');
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(LISTEN);
    });

    it('A39: parallel history arrival keeps the LISTEN item selected while listening', () => {
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      // History arrives while still listening.
      act(() => { capturedPropertyPanelProps.onReplayEntriesLoaded([dataEntry('event_1'), dataEntry('event_1')]); });
      rerender(<Flow {...reviewProps} />);
      // Selection stays on the listen item (does NOT jump to index 0).
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(LISTEN);
      expect(capturedPropertyPanelProps.replayEntries).toHaveLength(2);
    });

    it('A40-A43: a live test result selects the new entry; listen item remains in the dropdown', () => {
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      // The live listener finishes and delivers a result.
      act(() => { capturedOnReplayDataReceived?.([{ type: 'action', id: 'live_1' }]); });
      rerender(<Flow {...reviewProps} />);
      // The new entry is now selected (index 0), not the listen item.
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(0);
      expect(capturedPropertyPanelProps.replayEntries.length).toBeGreaterThanOrEqual(1);
    });

    it('A44: selecting the LISTEN item again re-arms the listener and shows waiting', () => {
      // Start a session, let a result arrive (now on a data entry).
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      act(() => { capturedOnReplayDataReceived?.([{ type: 'action', id: 'live_1' }]); });
      rerender(<Flow {...reviewProps} />);
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(0);

      // Now explicitly re-select the listen item.
      mockStartTest.mockClear();
      act(() => { capturedPropertyPanelProps.onSelectListenItem(); });
      rerender(<Flow {...reviewProps} />);
      expect(mockStartTest).toHaveBeenCalledWith('event_1');
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(LISTEN);
      // Existing entries are kept.
      expect(capturedPropertyPanelProps.replayEntries.length).toBeGreaterThanOrEqual(1);
    });

    it('A45: resuming an event whose stored selection is the listen item does NOT re-arm', () => {
      // Start event_1 (listen item selected), then switch to event_2 (snapshot
      // event_1 with listen selected), then resume event_1.
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      setup('event', eventNode('event_2'), { running: true });
      rerender(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });

      // Resume event_1 (it already has a session whose selection is the listen item).
      mockStartTest.mockClear();
      mockLoadReplayData.mockClear();
      setup('event', eventNode('event_1'), { running: false });
      rerender(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      // Resume must NOT auto-start the listener.
      expect(mockStartTest).not.toHaveBeenCalled();
      expect(mockLoadReplayData).not.toHaveBeenCalled();
    });

    it('A46: cancel with data selects the newest entry', () => {
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      // History present, listener still running, listen item selected.
      act(() => { capturedPropertyPanelProps.onReplayEntriesLoaded([dataEntry('event_1'), dataEntry('event_1')]); });
      rerender(<Flow {...reviewProps} />);
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(LISTEN);

      // Cancel (loading false) → select newest data entry (index 0).
      act(() => { capturedPropertyPanelProps.onCancelTest(); });
      rerender(<Flow {...reviewProps} />);
      expect(mockCancelTest).toHaveBeenCalled();
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(0);
    });

    it('A47: cancel with NO data shows the backend message (no entry selected)', () => {
      setup('review', eventNode('event_1'), { running: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      // A backend empty message arrives (no data) while listening.
      act(() => { capturedPropertyPanelProps.onReplayEntriesLoaded([]); });
      // Simulate the loader's empty message being stored on the active event.
      // (Flow stores it via onEmptyMessage; here we assert the cancel path.)
      act(() => { capturedPropertyPanelProps.onCancelTest(); });
      rerender(<Flow {...reviewProps} />);
      expect(mockCancelTest).toHaveBeenCalled();
      // No data → no entry selected (-1), not the listen item.
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(-1);
    });

    it('A48: cancel while history in-flight defers; newest selected once it resolves', () => {
      // loading=true so cancel defers the resolution.
      setup('review', eventNode('event_1'), { running: true, loading: true });
      const { rerender } = render(<Flow {...reviewProps} />);
      act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(LISTEN);

      // Cancel while still loading → stays on listen item (deferred).
      act(() => { capturedPropertyPanelProps.onCancelTest(); });
      rerender(<Flow {...reviewProps} />);
      expect(mockCancelTest).toHaveBeenCalled();
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(LISTEN);

      // History resolves with data → apply A46 (select newest).
      act(() => { capturedPropertyPanelProps.onReplayEntriesLoaded([dataEntry('event_1')]); });
      rerender(<Flow {...reviewProps} />);
      expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(0);
    });

    // ── Feature J: on-demand step-data in the [-token picker ─────────────────
    describe('Feature J: @-picker step-data routing', () => {
      it('threads onLoadStepData and isReplayLoading into PropertyPanel', () => {
        setup('event', eventNode('event_1'), { loading: true });
        render(<Flow {...reviewProps} />);
        expect(typeof capturedPropertyPanelProps.onLoadStepData).toBe('function');
        expect(capturedPropertyPanelProps.isReplayLoading).toBe(true);
      });

      it('onLoadStepData routes through Flow (starts the SINGLE listener + loads history) without the picker calling the test runner', () => {
        // Selected node is an action; its owning event is event_1 — but here we
        // drive the load handler directly with the owning event id, mirroring
        // how the picker calls the Flow-provided callback.
        setup('event', eventNode('event_1'), {});
        render(<Flow {...reviewProps} />);
        mockStartTest.mockClear();
        mockLoadReplayData.mockClear();
        act(() => { capturedPropertyPanelProps.onLoadStepData('event_1'); });
        // Routed through Flow: exactly ONE listener started for the owning event,
        // plus the history load. The picker never imported/called startTest.
        expect(mockStartTest).toHaveBeenCalledTimes(1);
        expect(mockStartTest).toHaveBeenCalledWith('event_1');
        expect(mockLoadReplayData).toHaveBeenCalledWith('event_1');
      });

      it('does NOT start a second listener when loadStepData targets the already-active event', () => {
        setup('review', eventNode('event_1'), { running: true });
        render(<Flow {...reviewProps} />);
        // Enter review for event_1 (one listener armed).
        act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
        expect(mockStartTest).toHaveBeenCalledTimes(1);
        // The picker requests step data for the SAME active event → no-op.
        mockStartTest.mockClear();
        act(() => { capturedPropertyPanelProps.onLoadStepData('event_1'); });
        expect(mockStartTest).not.toHaveBeenCalled();
      });

      it('switches the single listener to the owning event (cancels the previous) when loading a different event', () => {
        setup('review', eventNode('event_1'), { running: true });
        render(<Flow {...reviewProps} />);
        act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
        mockStartTest.mockClear();
        mockCancelTest.mockClear();
        // Load step data for a DIFFERENT event via the picker callback.
        act(() => { capturedPropertyPanelProps.onLoadStepData('event_2'); });
        // Previous listener cancelled, exactly one new listener started.
        expect(mockCancelTest).toHaveBeenCalled();
        expect(mockStartTest).toHaveBeenCalledTimes(1);
        expect(mockStartTest).toHaveBeenCalledWith('event_2');
      });

      // Bug #3576269: a picker-initiated session must keep the panel on
      // Properties (pickerInitiatedSession=true, no setPanelMode('review')); an
      // explicit Review action takes ownership (flag cleared, panel → review).
      it('loadStepDataForPicker marks the session picker-initiated and does NOT switch to review mode', () => {
        setup('event', eventNode('event_1'), {});
        const { rerender } = render(<Flow {...reviewProps} />);
        mockSetPanelMode.mockClear();
        act(() => { capturedPropertyPanelProps.onLoadStepData('event_1'); });
        rerender(<Flow {...reviewProps} />);
        // Panel stays on Properties: flagged picker-initiated, no review switch.
        expect(capturedPropertyPanelProps.pickerInitiatedSession).toBe(true);
        expect(mockSetPanelMode).not.toHaveBeenCalledWith('review');
      });

      it('an explicit Review action (enter) clears the picker-initiated flag and switches to review mode', () => {
        // Start a picker-initiated session for event_1.
        setup('event', eventNode('event_1'), {});
        const { rerender } = render(<Flow {...reviewProps} />);
        act(() => { capturedPropertyPanelProps.onLoadStepData('event_1'); });
        rerender(<Flow {...reviewProps} />);
        expect(capturedPropertyPanelProps.pickerInitiatedSession).toBe(true);

        // The user now selects a DIFFERENT event with no session and clicks
        // "Review flow" → enterReviewForNode claims a fresh session.
        mockSetPanelMode.mockClear();
        setup('event', eventNode('event_2'), {});
        rerender(<Flow {...reviewProps} />);
        act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
        rerender(<Flow {...reviewProps} />);
        expect(mockSetPanelMode).toHaveBeenCalledWith('review');
        expect(capturedPropertyPanelProps.pickerInitiatedSession).toBe(false);
      });

      it('an explicit Review action (resume) on a picker-initiated event clears the flag and switches to review mode', () => {
        // Picker arms a session for event_1 (panel stays on Properties).
        setup('event', eventNode('event_1'), { running: true });
        const { rerender } = render(<Flow {...reviewProps} />);
        act(() => { capturedPropertyPanelProps.onLoadStepData('event_1'); });
        rerender(<Flow {...reviewProps} />);
        expect(capturedPropertyPanelProps.pickerInitiatedSession).toBe(true);

        // The user clicks "Review flow" on the SAME event → resumeReviewForNode.
        mockSetPanelMode.mockClear();
        act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
        rerender(<Flow {...reviewProps} />);
        expect(mockSetPanelMode).toHaveBeenCalledWith('review');
        expect(capturedPropertyPanelProps.pickerInitiatedSession).toBe(false);
      });

      // ── Caveat 1: selecting a dataset while listening cancels the listener ──
      it('selecting a REAL dataset while listening CANCELS the listener and the chosen index sticks', () => {
        setup('review', eventNode('event_1'), { running: true });
        const { rerender } = render(<Flow {...reviewProps} />);
        // Enter review → listener armed for event_1, listen item selected.
        act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
        expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(LISTEN);

        // Three datasets arrive while listening (A39 keeps the listen item).
        act(() => {
          capturedPropertyPanelProps.onReplayEntriesLoaded([
            dataEntry('event_1'), dataEntry('event_1'), dataEntry('event_1'),
          ]);
        });
        rerender(<Flow {...reviewProps} />);
        expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(LISTEN);

        // User picks dataset #2 (from picker or Review dropdown — same handler).
        mockCancelTest.mockClear();
        act(() => { capturedPropertyPanelProps.onSelectReplayEntry(2); });
        rerender(<Flow {...reviewProps} />);

        // Listener cancelled, and the chosen index STICKS (no jump to 0).
        expect(mockCancelTest).toHaveBeenCalled();
        expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(2);

        // Even if a late history load resolves now, the user's pick is kept
        // (pendingCancelResolveRef was cleared by the explicit selection).
        act(() => {
          capturedPropertyPanelProps.onReplayEntriesLoaded([
            dataEntry('event_1'), dataEntry('event_1'), dataEntry('event_1'),
          ]);
        });
        rerender(<Flow {...reviewProps} />);
        expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(2);
      });

      it('selecting the LISTEN item does NOT cancel via the entry-select path (it re-arms)', () => {
        setup('review', eventNode('event_1'), { running: true });
        const { rerender } = render(<Flow {...reviewProps} />);
        act(() => { capturedPropertyPanelProps.onRequestReviewMode(); });
        act(() => {
          capturedPropertyPanelProps.onReplayEntriesLoaded([dataEntry('event_1')]);
        });
        rerender(<Flow {...reviewProps} />);

        mockCancelTest.mockClear();
        // Selecting the listen item routes to handleSelectListenItem, NOT a
        // cancel via handleSelectReplayEntry.
        act(() => { capturedPropertyPanelProps.onSelectReplayEntry(LISTEN); });
        rerender(<Flow {...reviewProps} />);
        expect(mockCancelTest).not.toHaveBeenCalled();
        expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(LISTEN);
      });

      it('selecting a dataset when NOT listening still works (no cancel)', () => {
        setup('review', eventNode('event_1'), {});
        const { rerender } = render(<Flow {...reviewProps} />);
        // Load data WITHOUT arming a listener (resume-style).
        act(() => {
          capturedPropertyPanelProps.onReplayEntriesLoaded([dataEntry('event_1'), dataEntry('event_1')]);
        });
        rerender(<Flow {...reviewProps} />);

        mockCancelTest.mockClear();
        act(() => { capturedPropertyPanelProps.onSelectReplayEntry(1); });
        rerender(<Flow {...reviewProps} />);
        expect(mockCancelTest).not.toHaveBeenCalled();
        expect(capturedPropertyPanelProps.selectedReplayEntryIndex).toBe(1);
      });
    });
  });
});
