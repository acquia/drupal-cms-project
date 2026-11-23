import React, { useState, useRef, useCallback, useEffect } from 'react';
import { FiPlay, FiPause, FiSquare, FiSkipBack, FiSkipForward, FiActivity, FiCopy, FiZap, FiChevronDown, FiClock, FiUser, FiGlobe, FiLink, FiRefreshCw, FiXCircle } from 'react-icons/fi';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { TIMING } from '../constants/dimensions';
import { t } from '../utils/translation';
import { useReplayStepFilter } from '../hooks/useReplayStepFilter';
import { useReplayPlayback } from '../hooks/useReplayPlayback';
import { StepDataContainer } from './ReplayDataRenderer';
import { getStepIcon, getStepLabel, ReplayStep } from '../utils/replayStepUtils';
import type { ReplayEntry } from '../hooks/useReplayLoader';
import { LISTEN_ITEM_INDEX } from '../hooks/useReplayLoader';

/** Format a timestamp string into a localized date/time */
export function formatTimestamp(ts: string | number | undefined): string {
  if (!ts) return '';
  try {
    // Handle Unix timestamps (seconds)
    const date = typeof ts === 'number' ? new Date(ts * 1000) : new Date(ts);
    if (isNaN(date.getTime())) return String(ts);
    return date.toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch {
    return String(ts);
  }
}

/** Extract user display string from entry.user */
export function formatUser(user: ReplayEntry['user']): string {
  if (typeof user === 'string') return user;
  if (user && typeof user === 'object') {
    const name = user.name || '';
    const uid = user.uid !== undefined ? ` (#${String(user.uid)})` : '';
    return `${name}${uid}` || String(user);
  }
  return String(user ?? '');
}

/** Extract a display string from a replay step exception. */
export function formatException(exc: StepInfo['exception']): string {
  if (!exc) return '';
  if (typeof exc === 'object') {
    const parts: string[] = [];
    if ('class' in exc && typeof exc.class === 'string') parts.push(exc.class);
    if ('message' in exc && typeof exc.message === 'string') parts.push(exc.message);
    if ('file' in exc && typeof exc.file === 'string') parts.push(`at ${exc.file}`);
    if (parts.length > 0) return parts.join(': ');
  }
  return String(exc);
}

export interface StepInfo {
  type: string;
  id?: string;
  successorId?: string;
  successorType?: number;
  conditionId?: string;
  object?: unknown;
  exception?: {
    class?: string;
    code?: number;
    message?: string;
    file?: string;
    trace?: string;
  } | Record<string, unknown>;
}

export interface ReplayPanelContentProps {
  replayData?: ReplayStep[] | null;
  isReplayMode: boolean;
  onToggleReplay: () => void;
  onSelectStep: (step: number) => void;
  currentStep?: number;
  stepData?: Record<string, any> | null;
  /**
   * Whether {@link stepData} was PREDICTED from a replay-covered predecessor of
   * the selected node (issue #3577207). When `true`, the Step-data container
   * renders a subtle "predicted" badge + tooltip on its tokens. Defaults to
   * `false` (confirmed step data, unchanged rendering).
   */
  stepDataPredicted?: boolean;
  stepInfo?: StepInfo | null;
  edges?: Edge[];
  nodes?: Node[];
  /** Loaded replay entries from backend (multiple executions) */
  replayEntries?: ReplayEntry[];
  /**
   * Index of the currently selected dropdown item: LISTEN_ITEM_INDEX (-2) = the
   * persistent "listen" item, -1 = no entry, 0..n-1 = a data entry.
   */
  selectedEntryIndex?: number;
  /** Callback when user selects a different (data) replay entry */
  onSelectReplayEntry?: (index: number) => void;
  /** Callback when the user selects the persistent "listen" item (re-arm) */
  onSelectListenItem?: () => void;
  /** Per-event backend empty/warning message for the empty body (A47) */
  backendMessage?: string | null;
  /** ID of the currently selected (or auto-detected) event node */
  selectedStartNodeId?: string | null;
  /** Whether a replay_url endpoint is available */
  hasReplayUrl?: boolean;
  /** Whether a test_url endpoint is available */
  hasTestUrl?: boolean;
  /** Whether a test is currently running (polling for results) */
  isTestRunning?: boolean;
  /** Whether the initial test request is in flight */
  isTestInitiating?: boolean;
  /** Error message if the test failed */
  testError?: string | null;
  /** Start a test for the given event component ID */
  onStartTest?: (componentId: string) => void;
  /** Cancel the running test */
  onCancelTest?: () => void;
}

/**
 * Presentational body of the review / replay panel.
 *
 * This component renders ONLY the inner content of the review view: the entry
 * selector, the waiting state and a single step list whose selected row shows
 * its step data inline. It deliberately omits the outer panel chrome (resize
 * handle, collapse widget, fixed-width wrapper) so it can be embedded inside
 * the unified right-hand panel (`PropertyPanel`) when in "Review flow" mode.
 *
 * All replay/test state is lifted to `Flow.tsx` and threaded down as props.
 */
const ReplayPanelContent: React.FC<ReplayPanelContentProps> = ({
  replayData,
  isReplayMode: _isReplayMode,
  onToggleReplay,
  onSelectStep,
  currentStep = -1,
  stepData,
  stepDataPredicted = false,
  stepInfo,
  edges = [],
  nodes = [],
  replayEntries = [],
  selectedEntryIndex = -1,
  onSelectReplayEntry,
  onSelectListenItem,
  backendMessage = null,
  // selectedStartNodeId / hasReplayUrl / hasTestUrl / onStartTest are retained
  // on the interface for caller compatibility but are no longer consumed here:
  // the "Execution Replay" header bar (with its Test button) was removed, and
  // the live listener auto-starts on review entry (handled in Flow.tsx).
  selectedStartNodeId: _selectedStartNodeId,
  hasReplayUrl: _hasReplayUrl,
  hasTestUrl: _hasTestUrl,
  // isTestRunning is retained on the interface for caller compatibility but the
  // body's waiting state is now driven by the listen-item selection (A38), not
  // by the running flag directly.
  isTestRunning: _isTestRunning = false,
  isTestInitiating = false,
  testError: _testError,
  onStartTest: _onStartTest,
  onCancelTest,
}) => {
  const [copiedPath, setCopiedPath] = useState<string | null>(null);
  const [entryDropdownOpen, setEntryDropdownOpen] = useState(false);
  const entryDropdownRef = useRef<HTMLDivElement>(null);
  const stepsContainerRef = useRef<HTMLDivElement>(null);
  const stepRefs = useRef<Record<number, HTMLDivElement | null>>({});

  // Close the entry dropdown on outside click
  useEffect(() => {
    if (!entryDropdownOpen) return;
    const handleClickOutside = (e: MouseEvent) => {
      if (entryDropdownRef.current && !entryDropdownRef.current.contains(e.target as HTMLElement)) {
        setEntryDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [entryDropdownOpen]);

  const handleEntrySelect = useCallback((index: number) => {
    onSelectReplayEntry?.(index);
    setEntryDropdownOpen(false);
  }, [onSelectReplayEntry]);

  const handleListenSelect = useCallback(() => {
    onSelectListenItem?.();
    setEntryDropdownOpen(false);
  }, [onSelectListenItem]);

  // Whether the persistent "listen" item is the current selection.
  const listenItemSelected = selectedEntryIndex === LISTEN_ITEM_INDEX;
  // Whether a real data entry is selected.
  const dataEntrySelected = selectedEntryIndex >= 0 && selectedEntryIndex < replayEntries.length;

  // Use the extracted step filter hook
  const { filteredReplayData, getFilteredIndex, getOriginalIndex } = useReplayStepFilter({
    replayData,
    nodes,
    edges,
  });

  const filteredCurrentStep = getFilteredIndex(currentStep);

  // Use the extracted playback control hook
  const {
    isPlaying,
    playbackSpeed,
    setPlaybackSpeed,
    handlePlay,
    handleStop,
    handlePrevious,
    handleNext,
    handleStepClick,
  } = useReplayPlayback({
    totalSteps: filteredReplayData.length,
    filteredCurrentStep,
    onSelectStep,
    onToggleReplay,
    getOriginalIndex,
    stepRefs,
    stepsContainerRef,
  });

  const copyToClipboard = (text: unknown, path: string) => {
    navigator.clipboard.writeText(JSON.stringify(text, null, 2));
    setCopiedPath(path);
    setTimeout(() => setCopiedPath(null), TIMING.COPY_FEEDBACK_DURATION);
  };

  const hasReplaySteps = filteredReplayData && filteredReplayData.length > 0;

  // ── Review body state machine (A38/A47/A48) ──────────────────────────────
  // The body reflects the SELECTED dropdown item:
  //   • listen item selected → waiting/spinner body (covers initiating, running,
  //     and the brief post-cancel window while history is still loading — A48).
  //   • a data entry selected → the step list with the selected step's data
  //     rendered inline underneath its row.
  //   • neither (no entry) → the backend empty/warning message if one is stored
  //     (A47), else the generic "no execution data yet" notice.
  const showWaiting = listenItemSelected;
  const showSteps = dataEntrySelected && hasReplaySteps;
  const showEmptyMessage = !listenItemSelected && !dataEntrySelected && !!backendMessage;

  // The toggle label reflects the selected item: a listening label when the
  // listen item is selected, otherwise the selected entry's timestamp + user.
  const listenLabel = t('Listen to event to happen');
  const entryDropdown = (
    <div className="replay-entry-selector" ref={entryDropdownRef}>
      <button
        className="replay-entry-toggle"
        onClick={() => setEntryDropdownOpen(prev => !prev)}
        aria-expanded={entryDropdownOpen}
        aria-haspopup="listbox"
        aria-label={t('Select execution replay')}
      >
        <span className="replay-entry-toggle-label">
          {listenItemSelected ? (
            <>
              <FiActivity className="toggle-icon" />
              {t('Listening for the event…')}
            </>
          ) : dataEntrySelected ? (
            <>
              <FiClock className="toggle-icon" />
              {formatTimestamp(replayEntries[selectedEntryIndex].timestamp)}
              {' — '}
              {formatUser(replayEntries[selectedEntryIndex].user)}
            </>
          ) : (
            t('Select an execution...')
          )}
        </span>
        <FiChevronDown className={`toggle-chevron ${entryDropdownOpen ? 'open' : ''}`} />
      </button>
      {entryDropdownOpen && (
        <div className="replay-entry-list" role="listbox" aria-label={t('Execution replays')}>
          {/* Persistent TOP item: listen for the event to happen (A34/A35). */}
          <div
            className={`replay-entry-item replay-listen-item ${listenItemSelected ? 'selected' : ''}`}
            role="option"
            aria-selected={listenItemSelected}
            onClick={handleListenSelect}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleListenSelect(); } }}
            tabIndex={0}
          >
            <div className="replay-entry-row">
              <FiActivity className="entry-icon" />
              <span className="entry-value">{listenLabel}</span>
            </div>
          </div>
          {replayEntries.map((entry, index) => (
            <div
              key={index}
              className={`replay-entry-item ${index === selectedEntryIndex ? 'selected' : ''}`}
              role="option"
              aria-selected={index === selectedEntryIndex}
              onClick={() => handleEntrySelect(index)}
              onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleEntrySelect(index); } }}
              tabIndex={0}
            >
              <div className="replay-entry-row">
                <FiClock className="entry-icon" />
                <span className="entry-value">{formatTimestamp(entry.timestamp)}</span>
              </div>
              <div className="replay-entry-row">
                <FiUser className="entry-icon" />
                <span className="entry-value">{formatUser(entry.user)}</span>
              </div>
              <div className="replay-entry-row">
                <FiGlobe className="entry-icon" />
                <span className="entry-value">{typeof entry.ip === 'string' ? entry.ip : String(entry.ip ?? '')}</span>
              </div>
              <div className="replay-entry-row">
                <FiLink className="entry-icon" />
                <span className="entry-value entry-url">{typeof entry.url === 'string' ? entry.url : String(entry.url ?? '')}</span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );

  // The waiting/spinner body (A38): shown whenever the listen item is selected.
  const waitingBody = (
    <div className="replay-test-waiting">
      <FiRefreshCw className="spinning test-spinner" />
      <h4>{isTestInitiating ? t('Starting test...') : t('Waiting for test execution...')}</h4>
      <p>{t('Trigger the selected event on your Drupal site so that the workflow gets executed and the results are captured.')}</p>
      {onCancelTest && (
        <button
          className="btn btn-secondary test-cancel-btn"
          onClick={onCancelTest}
          aria-label={t('Cancel test')}
        >
          <FiXCircle /> {t('Cancel')}
        </button>
      )}
    </div>
  );

  // Empty body (A47): the per-event backend message when present, else generic.
  const emptyBody = (
    <div className="replay-panel-empty">
      <FiActivity className="empty-icon" />
      {showEmptyMessage ? (
        <p>{backendMessage}</p>
      ) : (
        <>
          <p>{t('No execution data yet')}</p>
          <small>{t('Trigger the event on your site and its execution will appear here automatically.')}</small>
        </>
      )}
    </div>
  );

  // Empty/non-step state: render the dropdown + the appropriate body (waiting
  // when the listen item is selected, otherwise the empty/backend-message body).
  if (!showSteps) {
    return (
      <>
        {entryDropdown}
        {showWaiting ? waitingBody : emptyBody}
      </>
    );
  }

  return (
    <>
      {/* Persistent entry dropdown (listen item + data entries) - always shown
          in review (A34/A35/A36). */}
      {entryDropdown}

      {/* The single review body: playback controls, progress bar and the step
          list. The selected step's data renders inline under its own row, so
          there is no separate step-data sub-panel any more (#3589103). */}
      <div className="replay-control-section">
        <div className="replay-controls">
          <div className="playback-controls">
            <button
              className="control-btn"
              onClick={handlePrevious}
              disabled={filteredCurrentStep <= -1}
              title={t('Previous Step')}
              aria-label={t('Previous Step')}
            >
              <FiSkipBack />
            </button>
            <button
              className={`control-btn play-btn ${isPlaying ? 'playing' : ''}`}
              onClick={handlePlay}
              title={isPlaying ? t('Pause') : t('Play')}
              aria-label={isPlaying ? t('Pause') : t('Play')}
            >
              {isPlaying ? <FiPause /> : <FiPlay />}
            </button>
            <button
              className="control-btn"
              onClick={handleStop}
              title={t('Stop & Reset')}
              aria-label={t('Stop & Reset')}
            >
              <FiSquare />
            </button>
            <button
              className="control-btn"
              onClick={handleNext}
              disabled={filteredCurrentStep >= filteredReplayData.length - 1}
              title={t('Next Step')}
              aria-label={t('Next Step')}
            >
              <FiSkipForward />
            </button>
          </div>

          <div className="speed-control">
            <FiZap className="speed-icon" title={t('Playback Speed')} />
            <select
              value={playbackSpeed}
              onChange={(e) => setPlaybackSpeed(parseFloat(e.target.value))}
              title={t('Playback Speed')}
              aria-label={t('Playback Speed')}
            >
              <option value={0.5}>0.5x</option>
              <option value={1}>1x</option>
              <option value={2}>2x</option>
              <option value={4}>4x</option>
            </select>
          </div>
        </div>

        <div className="replay-progress">
          <div className="progress-bar">
            <div
              className="progress-fill"
              style={{
                width: `${filteredCurrentStep >= 0 ? ((filteredCurrentStep + 1) / filteredReplayData.length) * 100 : 0}%`
              }}
            />
          </div>
          <div className="progress-label">
            {filteredCurrentStep >= 0 ? t('Step @current of @total', { '@current': filteredCurrentStep + 1, '@total': filteredReplayData.length }) : t('Ready')}
          </div>
        </div>

        <div className="replay-steps" ref={stepsContainerRef}>
          {filteredReplayData.map((step, index) => {
            const isSelected = index === filteredCurrentStep;
            const stepDataId = `replay-step-data-${index}`;
            return (
              // The per-step wrapper is what `stepRefs` points at, so the
              // playback auto-scroll keeps the row AND its inline data in view.
              <div
                key={index}
                ref={el => { stepRefs.current[index] = el; }}
                className="replay-step-item"
              >
                <div
                  className={`replay-step ${isSelected ? 'current' : ''} ${index < filteredCurrentStep ? 'completed' : ''}`}
                  onClick={() => handleStepClick(index)}
                  role="button"
                  tabIndex={0}
                  aria-expanded={isSelected}
                  // Only reference the inline region while it exists: a dangling
                  // aria-controls id would be an invalid ARIA attribute value.
                  aria-controls={isSelected ? stepDataId : undefined}
                  onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleStepClick(index); } }}
                >
                  <div className="step-indicator">
                    {getStepIcon(step, nodes)}
                  </div>
                  <div className="step-label">{getStepLabel(step, index, nodes, edges)}</div>
                </div>
                {isSelected && (
                  <div
                    id={stepDataId}
                    className="replay-step-data"
                    role="region"
                    aria-label={t('Step data')}
                    tabIndex={0}
                  >
                    {(stepData || stepInfo) && (
                      <button
                        className="copy-btn"
                        onClick={() => copyToClipboard({ info: stepInfo, data: stepData }, 'all')}
                        title={t('Copy all data')}
                        aria-label={t('Copy all data')}
                      >
                        <FiCopy />
                        {copiedPath === 'all' && <span className="copied">{t('Copied!')}</span>}
                      </button>
                    )}
                    {stepData && Object.keys(stepData).length > 0 ? (
                      <StepDataContainer stepData={stepData} predicted={stepDataPredicted} />
                    ) : (
                      <p className="no-data">{t('No token data available for this step')}</p>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </>
  );
};

export default ReplayPanelContent;
