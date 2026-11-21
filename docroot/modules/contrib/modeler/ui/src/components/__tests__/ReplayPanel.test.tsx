import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import ReplayPanel from '../ReplayPanel';
import { LISTEN_ITEM_INDEX } from '../../hooks/useReplayLoader';

const mockUseReplayPlayback = jest.fn(() => ({
  isPlaying: false,
  playbackSpeed: 1,
  setPlaybackSpeed: jest.fn(),
  handlePlay: jest.fn(),
  handleStop: jest.fn(),
  handlePrevious: jest.fn(),
  handleNext: jest.fn(),
  handleStepClick: jest.fn(),
}));

jest.mock('react-icons/fi', () => ({
  FiPlay: () => <span data-testid="fi-play" />,
  FiPause: () => <span data-testid="fi-pause" />,
  FiSquare: () => <span data-testid="fi-square" />,
  FiSkipBack: () => <span data-testid="fi-skip-back" />,
  FiSkipForward: () => <span data-testid="fi-skip-forward" />,
  FiActivity: () => <span data-testid="fi-activity" />,
  FiChevronLeft: () => <span data-testid="fi-chevron-left" />,
  FiChevronRight: () => <span data-testid="fi-chevron-right" />,
  FiCopy: () => <span data-testid="fi-copy" />,
  FiZap: () => <span data-testid="fi-zap" />,
  FiChevronDown: () => <span data-testid="fi-chevron-down" />,
  FiClock: () => <span data-testid="fi-clock" />,
  FiUser: () => <span data-testid="fi-user" />,
  FiGlobe: () => <span data-testid="fi-globe" />,
  FiLink: () => <span data-testid="fi-link" />,
  FiRefreshCw: () => <span data-testid="fi-refresh-cw" />,
  FiXCircle: () => <span data-testid="fi-x-circle" />,
}));

const mockToggleReplayPanelCollapse = jest.fn();

jest.mock('../../store/usePanelStore', () => ({
  usePanelStore: jest.fn((selector) => {
    const state = {
      replayPanelWidth: 300,
      replayPanelIsResizing: false,
      setReplayPanelWidth: jest.fn(),
      setReplayPanelResizing: jest.fn(),
      replayPanelCollapsed: false,
      toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../hooks/useReplayStepFilter', () => ({
  useReplayStepFilter: jest.fn(({ replayData }) => ({
    filteredReplayData: replayData || [],
    getFilteredIndex: jest.fn((idx: number) => idx),
    getOriginalIndex: jest.fn((idx: number) => idx),
  })),
}));

jest.mock('../../hooks/useReplayPlayback', () => ({
  useReplayPlayback: (...args: Parameters<typeof mockUseReplayPlayback>) => mockUseReplayPlayback(...args),
}));

jest.mock('../../hooks/usePanelResize', () => ({
  usePanelResize: jest.fn(() => ({
    startResize: jest.fn(),
  })),
}));

jest.mock('../ReplayDataRenderer', () => ({
  StepDataContainer: (_props: { stepData: Record<string, unknown>; predicted?: boolean }) => <div data-testid="step-data-container" />,
}));

jest.mock('../InfoPopup', () => {
  const MockInfoPopup = (props: any) => <div data-testid="info-popup">{props.items?.map((item: any, i: number) => item.show !== false ? <span key={i}>{item.value}</span> : null)}</div>;
  MockInfoPopup.displayName = 'MockInfoPopup';
  return MockInfoPopup;
});

jest.mock('../../utils/replayStepUtils', () => ({
  getStepIcon: () => <span data-testid="step-icon" />,
  getStepLabel: (step: any, index: number) => `Step ${index}`,
}));

describe('ReplayPanel', () => {
  const mockOnSelectStep = jest.fn();
  const mockOnToggleReplay = jest.fn();

  const mockReplayData = [
    { type: 'event', id: 'e1', data: null },
    { type: 'action', id: 'a1', data: { token: 'val' } },
  ];

  // A single data entry, selected by default, so the steps body renders. After
  // Rework H the step body shows only when a DATA entry (index >= 0) is selected
  // (the listen item is index -2, "no entry" is -1).
  const mockEntries = [
    { model_id: 'm1', component_id: 'e1', history: mockReplayData, timestamp: '2024-01-01T00:00:00Z', user: 'tester', ip: '127.0.0.1', url: '/' },
  ];

  const defaultProps = {
    replayData: mockReplayData as any,
    isReplayMode: true,
    onToggleReplay: mockOnToggleReplay,
    onSelectStep: mockOnSelectStep,
    isVisible: true,
    currentStep: -1,
    edges: [],
    nodes: [],
    replayEntries: mockEntries as any,
    selectedEntryIndex: 0,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockUseReplayPlayback.mockReturnValue({
      isPlaying: false,
      playbackSpeed: 1,
      setPlaybackSpeed: jest.fn(),
      handlePlay: jest.fn(),
      handleStop: jest.fn(),
      handlePrevious: jest.fn(),
      handleNext: jest.fn(),
      handleStepClick: jest.fn(),
    });
    // Mock clipboard API
    Object.assign(navigator, {
      clipboard: { writeText: jest.fn().mockResolvedValue(undefined) },
    });
  });

  describe('visibility', () => {
    it('should return null when not visible', () => {
      const { container } = render(<ReplayPanel {...defaultProps} isVisible={false} />);
      expect(container.firstChild).toBeNull();
    });
  });

  describe('empty data', () => {
    it('should show the empty message when replay data is empty', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} />);
      expect(screen.getByText('No execution data yet')).toBeTruthy();
    });

    it('should show the empty message when replay data is null', () => {
      render(<ReplayPanel {...defaultProps} replayData={null} />);
      expect(screen.getByText('No execution data yet')).toBeTruthy();
    });

    it('should show concise auto-capture guidance in the empty state', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} />);
      expect(screen.getByText('Trigger the event on your site and its execution will appear here automatically.')).toBeTruthy();
    });

    it('should NOT render the removed "Execution Replay" header bar', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} />);
      expect(screen.queryByText('Execution Replay')).toBeNull();
    });

    it('should NOT render the removed stale reload / Test guidance', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} hasReplayUrl hasTestUrl selectedStartNodeId="event-1" />);
      expect(screen.queryByText(/reload button in the property panel/)).toBeNull();
      expect(screen.queryByText('- or -')).toBeNull();
      expect(screen.queryByText(/click Test/i)).toBeNull();
    });
  });

  describe('with replay data', () => {
    it('should NOT render the removed "Execution Replay" header or step count', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.queryByText('Execution Replay')).toBeNull();
      expect(screen.queryByText('(2 steps)')).toBeNull();
    });

    it('should render playback controls', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByTitle('Previous Step')).toBeTruthy();
      expect(screen.getByTitle('Play')).toBeTruthy();
      expect(screen.getByTitle('Stop & Reset')).toBeTruthy();
      expect(screen.getByTitle('Next Step')).toBeTruthy();
    });

    it('should show Ready when no step selected', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByText('Ready')).toBeTruthy();
    });

    it('should show step progress', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} />);
      expect(screen.getByText('Step 1 of 2')).toBeTruthy();
    });

    it('should render step list', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByText('Step 0')).toBeTruthy();
      expect(screen.getByText('Step 1')).toBeTruthy();
    });

    it('should render speed control', () => {
      render(<ReplayPanel {...defaultProps} />);
      const select = screen.getByTitle('Playback Speed') as HTMLSelectElement;
      expect(select).toBeTruthy();
      expect(select.value).toBe('1');
    });

    it('should render progress bar with width 0 when no step selected', () => {
      render(<ReplayPanel {...defaultProps} currentStep={-1} />);
      const fill = document.querySelector('.progress-fill') as HTMLElement;
      expect(fill).toBeTruthy();
      expect(fill.style.width).toBe('0%');
    });

    it('should render progress bar with correct width when step selected', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} />);
      const fill = document.querySelector('.progress-fill') as HTMLElement;
      expect(fill.style.width).toBe('50%');
    });
  });

  describe('inline step data', () => {
    it('should render the selected step data inline inside the step list', () => {
      render(<ReplayPanel {...defaultProps} currentStep={1} stepData={{ token: 'val' }} />);
      const inline = document.querySelector('.replay-steps .replay-step-data');
      expect(inline).toBeTruthy();
      expect(inline!.querySelector('[data-testid="step-data-container"]')).toBeTruthy();
    });

    it('should render the inline block under the SELECTED step row only', () => {
      render(<ReplayPanel {...defaultProps} currentStep={1} stepData={{ token: 'val' }} />);
      const items = document.querySelectorAll('.replay-steps .replay-step-item');
      expect(items).toHaveLength(2);
      expect(items[0].querySelector('.replay-step-data')).toBeNull();
      expect(items[1].querySelector('.replay-step-data')).toBeTruthy();
    });

    it('should expose the inline block as a labeled region the selected row controls', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ token: 'val' }} />);
      const region = screen.getByRole('region', { name: 'Step data' });
      const row = screen.getByText('Step 0').closest('.replay-step')!;
      expect(row).toHaveAttribute('aria-expanded', 'true');
      expect(row).toHaveAttribute('aria-controls', region.id);
    });

    it('should not mark an unselected step row as expanded', () => {
      render(<ReplayPanel {...defaultProps} currentStep={1} stepData={{ token: 'val' }} />);
      const row = screen.getByText('Step 0').closest('.replay-step')!;
      expect(row).toHaveAttribute('aria-expanded', 'false');
      expect(row).not.toHaveAttribute('aria-controls');
    });

    it('should show no data message when step selected but stepData is empty', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{}} />);
      expect(screen.getByText('No token data available for this step')).toBeTruthy();
    });

    it('should show no data message when step selected but stepData is null', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={null} />);
      expect(screen.getByText('No token data available for this step')).toBeTruthy();
    });

    it('should render no inline block at all while no step is selected', () => {
      render(<ReplayPanel {...defaultProps} currentStep={-1} stepData={{ token: 'val' }} />);
      expect(document.querySelector('.replay-step-data')).toBeNull();
    });

    it('should NOT render a step count on a step row', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ token: 'val' }} />);
      const row = screen.getByText('Step 0').closest('.replay-step')!;
      expect(row.querySelector('.token-count')).toBeNull();
      expect(row.textContent).not.toMatch(/\(\d+\)/);
    });

    it('should NOT render the removed "Step Data" section header', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ token: 'val' }} />);
      expect(screen.queryByText('Step Data')).toBeNull();
      expect(document.querySelector('.step-data-section')).toBeNull();
    });

    it('should NOT render draggable section separators any more', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ token: 'val' }} />);
      expect(document.querySelector('.section-separator')).toBeNull();
      expect(document.querySelector('.resizable-sections')).toBeNull();
    });
  });

  describe('metadata info button (removed)', () => {
    it('should NOT render the metadata info button or popup, even with stepInfo', () => {
      render(<ReplayPanel {...defaultProps} stepInfo={{ type: 'action', id: 'a1', conditionId: 'c1', exception: { message: 'Err' } }} />);
      expect(screen.queryByTitle('Show metadata')).toBeNull();
      expect(screen.queryByTestId('info-popup')).toBeNull();
    });
  });

  describe('copy to clipboard', () => {
    it('should render copy button when stepData is provided', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ token: 'val' }} />);
      expect(screen.getByTitle('Copy all data')).toBeTruthy();
    });

    it('should render copy button when only stepInfo is provided for the selected step', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepInfo={{ type: 'action' }} />);
      expect(screen.getByTitle('Copy all data')).toBeTruthy();
    });

    it('should not render copy button when neither stepData nor stepInfo', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} />);
      expect(screen.queryByTitle('Copy all data')).toBeNull();
    });

    it('should call navigator.clipboard.writeText on copy', () => {
      jest.useFakeTimers();
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ token: 'val' }} stepInfo={{ type: 'action' }} />);
      fireEvent.click(screen.getByTitle('Copy all data'));
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
        JSON.stringify({ info: { type: 'action' }, data: { token: 'val' } }, null, 2)
      );
      jest.useRealTimers();
    });

    it('should show Copied! feedback briefly after copy', () => {
      jest.useFakeTimers();
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ a: 1 }} stepInfo={{ type: 'action' }} />);
      fireEvent.click(screen.getByTitle('Copy all data'));
      expect(screen.getByText('Copied!')).toBeTruthy();
      act(() => { jest.advanceTimersByTime(2000); });
      expect(screen.queryByText('Copied!')).toBeNull();
      jest.useRealTimers();
    });
  });

  describe('collapsed panel', () => {
    it('should render collapsed label when collapsed', () => {
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelWidth: 300,
          replayPanelIsResizing: false,
          setReplayPanelWidth: jest.fn(),
          setReplayPanelResizing: jest.fn(),
          replayPanelCollapsed: true,
          toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
        };
        return selector(state);
      });

      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByText('Replay')).toBeTruthy();
    });

    it('should expand when collapsed panel is clicked', () => {
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelWidth: 300,
          replayPanelIsResizing: false,
          setReplayPanelWidth: jest.fn(),
          setReplayPanelResizing: jest.fn(),
          replayPanelCollapsed: true,
          toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
        };
        return selector(state);
      });

      render(<ReplayPanel {...defaultProps} />);
      const panel = document.querySelector('.replay-panel')!;
      fireEvent.click(panel);
      expect(mockToggleReplayPanelCollapse).toHaveBeenCalled();
    });

    it('should not expand when non-collapsed panel is clicked', () => {
      // Reset store mock back to non-collapsed
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelWidth: 300,
          replayPanelIsResizing: false,
          setReplayPanelWidth: jest.fn(),
          setReplayPanelResizing: jest.fn(),
          replayPanelCollapsed: false,
          toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
        };
        return selector(state);
      });

      render(<ReplayPanel {...defaultProps} />);
      const panel = document.querySelector('.replay-panel')!;
      fireEvent.click(panel);
      expect(mockToggleReplayPanelCollapse).not.toHaveBeenCalled();
    });

    it('should toggle collapse when collapse widget is clicked', () => {
      // Ensure store mock is non-collapsed so button says "Collapse panel"
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelWidth: 300,
          replayPanelIsResizing: false,
          setReplayPanelWidth: jest.fn(),
          setReplayPanelResizing: jest.fn(),
          replayPanelCollapsed: false,
          toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
        };
        return selector(state);
      });

      render(<ReplayPanel {...defaultProps} />);
      const collapseBtn = screen.getByTitle('Collapse panel');
      fireEvent.click(collapseBtn);
      expect(mockToggleReplayPanelCollapse).toHaveBeenCalled();
    });
  });

  describe('replay entry selector', () => {
    const mockEntries = [
      {
        model_id: 'model1',
        component_id: 'ev1',
        history: [],
        timestamp: '2026-01-15T10:30:00Z',
        user: 'admin',
        ip: '127.0.0.1',
        url: '/node/1',
      },
      {
        model_id: 'model1',
        component_id: 'ev1',
        history: [],
        timestamp: 1737000000,
        user: { name: 'editor', uid: 2 },
        ip: '192.168.1.1',
        url: '/node/2',
      },
    ];

    const mockOnSelectReplayEntry = jest.fn();

    it('should ALWAYS render the entry selector in review, even with no data entries (Rework H)', () => {
      render(<ReplayPanel {...defaultProps} replayEntries={[]} selectedEntryIndex={-1} />);
      expect(document.querySelector('.replay-entry-selector')).toBeTruthy();
    });

    it('should render the entry selector even without an onSelectReplayEntry callback (listen item present)', () => {
      render(<ReplayPanel {...defaultProps} replayEntries={mockEntries} selectedEntryIndex={0} />);
      expect(document.querySelector('.replay-entry-selector')).toBeTruthy();
    });

    it('should render the persistent listen item at the TOP of the dropdown', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const listbox = screen.getByRole('listbox');
      const options = listbox.querySelectorAll('[role="option"]');
      // listen item + 2 data entries
      expect(options).toHaveLength(3);
      expect(options[0].className).toContain('replay-listen-item');
      expect(options[0].textContent).toContain('Listen to event to happen');
    });

    it('should call onSelectListenItem when the listen item is selected', () => {
      const onSelectListenItem = jest.fn();
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
          onSelectListenItem={onSelectListenItem}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const listbox = screen.getByRole('listbox');
      const listenOption = listbox.querySelector('.replay-listen-item') as HTMLElement;
      fireEvent.click(listenOption);
      expect(onSelectListenItem).toHaveBeenCalledTimes(1);
      expect(mockOnSelectReplayEntry).not.toHaveBeenCalled();
    });

    it('should render entry selector with entries and callback', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      expect(document.querySelector('.replay-entry-selector')).toBeTruthy();
    });

    it('should show "Select an execution..." when no entry is selected', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={-1}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      expect(screen.getByText('Select an execution...')).toBeTruthy();
    });

    it('should show selected entry timestamp and user', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // The toggle label should contain formatted timestamp and user
      const toggle = screen.getByLabelText('Select execution replay');
      expect(toggle).toBeTruthy();
      // "admin" is the user string
      expect(toggle.textContent).toContain('admin');
    });

    it('should open dropdown when toggle is clicked', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      const toggle = screen.getByLabelText('Select execution replay');
      fireEvent.click(toggle);
      expect(screen.getByRole('listbox')).toBeTruthy();
    });

    it('should render all entries in the dropdown', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const listbox = screen.getByRole('listbox');
      const options = listbox.querySelectorAll('[role="option"]');
      // listen item + 2 data entries
      expect(options).toHaveLength(3);
    });

    it('should mark the selected entry with aria-selected', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const options = screen.getAllByRole('option');
      // options[0] is the listen item (not selected); data entries follow.
      expect(options[0]).toHaveAttribute('aria-selected', 'false');
      expect(options[1]).toHaveAttribute('aria-selected', 'true');
      expect(options[2]).toHaveAttribute('aria-selected', 'false');
    });

    it('should call onSelectReplayEntry when a data entry is clicked', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const options = screen.getAllByRole('option');
      // options[2] is the SECOND data entry (index 1); options[0] is listen.
      fireEvent.click(options[2]);
      expect(mockOnSelectReplayEntry).toHaveBeenCalledWith(1);
    });

    it('should call onSelectReplayEntry on Enter key', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const options = screen.getAllByRole('option');
      fireEvent.keyDown(options[2], { key: 'Enter' });
      expect(mockOnSelectReplayEntry).toHaveBeenCalledWith(1);
    });

    it('should call onSelectReplayEntry on Space key', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const options = screen.getAllByRole('option');
      fireEvent.keyDown(options[2], { key: ' ' });
      expect(mockOnSelectReplayEntry).toHaveBeenCalledWith(1);
    });

    it('should close dropdown after selecting an entry', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      expect(screen.getByRole('listbox')).toBeTruthy();
      fireEvent.click(screen.getAllByRole('option')[1]);
      expect(screen.queryByRole('listbox')).toBeNull();
    });

    it('should close dropdown on outside click', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      expect(screen.getByRole('listbox')).toBeTruthy();
      fireEvent.mouseDown(document.body);
      expect(screen.queryByRole('listbox')).toBeNull();
    });

    it('should display user object with name and uid', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={1}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // The toggle should show user name + uid
      const toggle = screen.getByLabelText('Select execution replay');
      expect(toggle.textContent).toContain('editor');
    });

    it('should display IP and URL in dropdown items', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      expect(screen.getByText('127.0.0.1')).toBeTruthy();
      expect(screen.getByText('/node/1')).toBeTruthy();
    });

    it('should still render the entry selector with only one data entry (listen item is always present)', () => {
      const singleEntry = [mockEntries[0]];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={singleEntry}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      expect(document.querySelector('.replay-entry-selector')).toBeTruthy();
    });

    it('should handle user as object without uid', () => {
      const entries = [
        { ...mockEntries[0], user: { name: 'no uid' } as any },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      const toggle = screen.getByLabelText('Select execution replay');
      expect(toggle.textContent).toContain('no uid');
    });

    it('should handle null user gracefully', () => {
      const entries = [
        { ...mockEntries[0], user: null as any },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // Should not throw
      expect(screen.getByLabelText('Select execution replay')).toBeTruthy();
    });

    it('should handle numeric timestamp (unix seconds)', () => {
      const entries = [
        { ...mockEntries[0], timestamp: 1700000000 },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      const toggle = screen.getByLabelText('Select execution replay');
      // Should render a formatted date string (not the raw number)
      expect(toggle.textContent).not.toContain('1700000000');
    });

    it('should handle invalid timestamp string gracefully', () => {
      const entries = [
        { ...mockEntries[0], timestamp: 'not-a-date' },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // Should render the raw string as fallback
      const toggle = screen.getByLabelText('Select execution replay');
      expect(toggle.textContent).toContain('not-a-date');
    });

    it('should handle empty timestamp gracefully', () => {
      const entries = [
        { ...mockEntries[0], timestamp: '' as any },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // Should not throw
      expect(screen.getByLabelText('Select execution replay')).toBeTruthy();
    });

  });

  describe('step interactions', () => {
    it('should call handleStepClick when a step is clicked', () => {
      const handleStepClick = jest.fn();
      mockUseReplayPlayback.mockReturnValue({
        isPlaying: false,
        playbackSpeed: 1,
        setPlaybackSpeed: jest.fn(),
        handlePlay: jest.fn(),
        handleStop: jest.fn(),
        handlePrevious: jest.fn(),
        handleNext: jest.fn(),
        handleStepClick,
      });
      render(<ReplayPanel {...defaultProps} />);
      fireEvent.click(screen.getByText('Step 0'));
      expect(handleStepClick).toHaveBeenCalledWith(0);
    });

    it('should call handleStepClick on Enter key', () => {
      const handleStepClick = jest.fn();
      mockUseReplayPlayback.mockReturnValue({
        isPlaying: false,
        playbackSpeed: 1,
        setPlaybackSpeed: jest.fn(),
        handlePlay: jest.fn(),
        handleStop: jest.fn(),
        handlePrevious: jest.fn(),
        handleNext: jest.fn(),
        handleStepClick,
      });
      render(<ReplayPanel {...defaultProps} />);
      const step = screen.getByText('Step 0').closest('[role="button"]')!;
      fireEvent.keyDown(step, { key: 'Enter' });
      expect(handleStepClick).toHaveBeenCalledWith(0);
    });

    it('should call handleStepClick on Space key', () => {
      const handleStepClick = jest.fn();
      mockUseReplayPlayback.mockReturnValue({
        isPlaying: false,
        playbackSpeed: 1,
        setPlaybackSpeed: jest.fn(),
        handlePlay: jest.fn(),
        handleStop: jest.fn(),
        handlePrevious: jest.fn(),
        handleNext: jest.fn(),
        handleStepClick,
      });
      render(<ReplayPanel {...defaultProps} />);
      const step = screen.getByText('Step 0').closest('[role="button"]')!;
      fireEvent.keyDown(step, { key: ' ' });
      expect(handleStepClick).toHaveBeenCalledWith(0);
    });

    it('should apply current class to active step', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} />);
      const step = screen.getByText('Step 0').closest('.replay-step')!;
      expect(step.className).toContain('current');
    });

    it('should apply completed class to previous steps', () => {
      render(<ReplayPanel {...defaultProps} currentStep={1} />);
      const step = screen.getByText('Step 0').closest('.replay-step')!;
      expect(step.className).toContain('completed');
    });
  });

  describe('Test button (removed — listener auto-starts on review entry)', () => {
    it('should NOT render the Test button in the active replay view', () => {
      render(<ReplayPanel {...defaultProps} hasTestUrl selectedStartNodeId="event-1" onStartTest={jest.fn()} />);
      expect(screen.queryByTitle('Test this event')).toBeNull();
    });

    it('should NOT render the Test button in the empty state', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} hasTestUrl selectedStartNodeId="event-1" onStartTest={jest.fn()} />);
      expect(screen.queryByTitle('Test this event')).toBeNull();
    });
  });

  describe('test waiting state', () => {
    // After Rework H the waiting body is driven by the LISTEN item being
    // selected (selectedEntryIndex === LISTEN_ITEM_INDEX), not by isTestRunning.
    it('should show waiting state when the listen item is selected', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} replayEntries={[]} selectedEntryIndex={LISTEN_ITEM_INDEX} />);
      expect(screen.getByText('Waiting for test execution...')).toBeTruthy();
    });

    it('should show initiating heading when the listen item is selected and test is initiating', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} replayEntries={[]} selectedEntryIndex={LISTEN_ITEM_INDEX} isTestInitiating />);
      expect(screen.getByText('Starting test...')).toBeTruthy();
    });

    it('should show instructional text in the waiting body', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} replayEntries={[]} selectedEntryIndex={LISTEN_ITEM_INDEX} />);
      expect(screen.getByText('Trigger the selected event on your Drupal site so that the workflow gets executed and the results are captured.')).toBeTruthy();
    });

    it('should show the cancel button in the waiting body', () => {
      const onCancelTest = jest.fn();
      render(<ReplayPanel {...defaultProps} replayData={[]} replayEntries={[]} selectedEntryIndex={LISTEN_ITEM_INDEX} onCancelTest={onCancelTest} />);
      expect(screen.getByLabelText('Cancel test')).toBeTruthy();
    });

    it('should call onCancelTest when the cancel button is clicked', () => {
      const onCancelTest = jest.fn();
      render(<ReplayPanel {...defaultProps} replayData={[]} replayEntries={[]} selectedEntryIndex={LISTEN_ITEM_INDEX} onCancelTest={onCancelTest} />);
      fireEvent.click(screen.getByLabelText('Cancel test'));
      expect(onCancelTest).toHaveBeenCalled();
    });

    it('should NOT show the waiting body when a data entry is selected', () => {
      render(<ReplayPanel {...defaultProps} selectedEntryIndex={0} />);
      expect(screen.queryByText('Waiting for test execution...')).toBeNull();
    });

    it('should show the backend message in the empty body when not listening with no entries', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayData={[]}
          replayEntries={[]}
          selectedEntryIndex={-1}
          backendMessage="No replay data available for this event."
        />
      );
      expect(screen.getByText('No replay data available for this event.')).toBeTruthy();
      // The generic empty notice is replaced by the backend message.
      expect(screen.queryByText('No execution data yet')).toBeNull();
    });

    it('should hide playback controls when the listen item is selected (waiting body)', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} replayEntries={[]} selectedEntryIndex={LISTEN_ITEM_INDEX} />);
      expect(screen.queryByTitle('Previous Step')).toBeNull();
      expect(screen.queryByTitle('Play')).toBeNull();
    });
  });

  // The global and template token trees were removed from the review panel
  // (issue project/modeler#3589103); those tokens are reachable only through
  // the `[` token picker now.
  describe('token sections (removed)', () => {
    const settingsHolder = window as unknown as {
      drupalSettings?: {
        modeler_api?: {
          global_tokens?: Record<string, unknown>;
          template_tokens?: Record<string, unknown>;
        };
      };
    };

    beforeEach(() => {
      settingsHolder.drupalSettings = {
        modeler_api: {
          global_tokens: {
            '[site:name]': { name: 'Site name', 'raw token': '[site:name]', token: 'name', value: 'My Site' },
          },
          template_tokens: {
            '[template:author]': { name: 'Author', 'raw token': '[template:author]', token: 'author', value: 'Jane Doe' },
          },
        },
      };
    });

    afterEach(() => {
      delete settingsHolder.drupalSettings;
    });

    it('should render no token sections in the steps view even when tokens are configured', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ token: 'val' }} />);
      expect(screen.queryByText('Global Tokens')).toBeNull();
      expect(screen.queryByText('Template Tokens')).toBeNull();
    });

    it('should render no token sections in the empty state even when tokens are configured', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} />);
      expect(screen.queryByText('Global Tokens')).toBeNull();
      expect(screen.queryByText('Template Tokens')).toBeNull();
    });
  });
});
