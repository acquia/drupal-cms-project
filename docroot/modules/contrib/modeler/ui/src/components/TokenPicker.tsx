/**
 * TokenPicker - The "[" token picker popup shown inside token-supporting
 * property fields.
 *
 * Behavior (mirrors the "Apply tokens" comps):
 * 1. Top level: a "Select token category" list — Step data, Global (n),
 *    Template (n).
 * 2. Drill into a category to browse its (possibly nested) tokens.
 * 3. Each usable (leaf) token is itself the clickable option — clicking (or
 *    pressing Enter on) the whole row inserts the token; there is no separate
 *    "Use" pill.
 * 4. A search box at the top filters the visible tokens (label/token
 *    substring, case-insensitive); while filtering, the picker shows a flat
 *    list of matching usable tokens across all categories. The search box owns
 *    its own state — it is NOT driven by what the user types in the host field.
 * 5. When there is no cached step data, a hint nudges the user to "Review the
 *    model" to get richer step-data tokens (Global/Template stay available).
 *
 * Positioning: anchored to a caret rect supplied by the host field. Keyboard
 * navigable (Arrow keys, Enter to use/drill, Escape to close, Backspace handled
 * by the host). All strings via t(). Colors via --modeler-* only.
 */

import React, { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { FiChevronRight, FiChevronLeft, FiSearch, FiActivity, FiChevronDown, FiClock, FiRefreshCw, FiX } from 'react-icons/fi';
import { t } from '../utils/translation';
import { useTokenSources } from './TokenSourceContext';
import { LISTEN_ITEM_INDEX } from '../hooks/useReplayLoader';
import { formatTimestamp, formatUser } from './ReplayPanelContent';
import {
  buildBreadcrumb,
  buildTokenCategories,
  computePickerPlacement,
  flattenUsableTokens,
  formatTokenValue,
  pickerMinHeight,
  tokenMatchesQuery,
  type TokenCategory,
  type TokenNode,
} from '../utils/tokenPickerData';

/** Viewport edge margin and height bounds for the picker popup (px). */
const PICKER_MARGIN = 8;
// Ceiling raised to comfortably fit the ~10-row list floor
// (10*32 + ~120 chrome ≈ 440) on a normal viewport. computePickerPlacement
// still clamps to the available viewport space, so the popup never overflows.
const PICKER_MAX_HEIGHT = 520;
const PICKER_MIN_HEIGHT = 120;

export interface TokenPickerProps {
  /** Caret-anchored position (relative to the field wrapper), in px. */
  position: { x: number; y: number };
  /** Insert the chosen token; host removes the triggering `[` and inserts the pill. */
  onSelect: (label: string, token: string) => void;
  /** Close the picker without inserting. */
  onClose: () => void;
}

/**
 * The subtle "predicted" pill shown once in the step-data category header when
 * the currently-shown step data was propagated from a replay-covered
 * predecessor (issue #3577207). A NON-interactive `<span>` so it never
 * introduces a nested-interactive a11y violation. Rendered a single time in the
 * breadcrumb header (never per-token) so the indicator reads as a header-level
 * status rather than clutter on every row.
 */
const PredictedBadge: React.FC = () => (
  <span
    className="token-predicted-badge"
    title={t('Predicted from the previous step; not yet confirmed by a test run.')}
    aria-label={t('Predicted token')}
  >
    {t('Predicted')}
  </span>
);

/**
 * A single usable (leaf) token row. The ENTIRE row is the actionable
 * `role="option"` (click / Enter inserts the token); there is no separate
 * "Use" pill. Keeping the whole row as the sole clickable target avoids
 * nesting an interactive control inside an option (WAI-ARIA / axe
 * `nested-interactive`). Focus stays in the host field; activation is driven by
 * click here and by Enter in the picker's keyboard handler.
 */
const TokenLeafRow: React.FC<{
  node: TokenNode;
  active: boolean;
  onUse: (node: TokenNode) => void;
  id: string;
}> = ({ node, active, onUse, id }) => {
  // Display the resolved runtime VALUE under the label (not the token string).
  // Empty/absent values (typical for global/template tokens) render no subtext.
  const value = formatTokenValue(node.value);
  return (
    <div
      id={id}
      role="option"
      aria-selected={active}
      aria-label={t('Use token @token', { '@token': node.token || node.label })}
      className={`token-picker-option token-picker-leaf ${active ? 'active' : ''}`}
      onClick={() => onUse(node)}
      // Prevent the field from losing focus / closing before the click lands.
      onMouseDown={(e) => e.preventDefault()}
    >
      <span className="token-picker-leaf-label" title={node.token}>
        {node.label}
        {value && (
          <span className="token-picker-leaf-value" title={value}>
            {value}
          </span>
        )}
      </span>
    </div>
  );
};

const TokenPicker: React.FC<TokenPickerProps> = React.memo(({ position, onSelect, onClose }) => {
  const {
    globalTokens,
    templateTokens,
    isTemplate,
    stepData,
    stepDataPredicted = false,
    hasStepData,
    onReviewModel,
    reviewAvailable,
    // Feature J: thin view over Flow's per-event session machinery.
    owningEventId,
    replayEntries = [],
    selectedEntryIndex = -1,
    isLoadingStepData = false,
    isListening = false,
    onLoadStepData,
    onSelectDataset,
    onStartListen,
    onStopListen,
  } = useTokenSources();

  const popupRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);
  // The picker owns its OWN search state (DECISION A): typing in the search box
  // below filters the tokens. This is independent of the host field's text — the
  // user types the trigger `[` in the field to OPEN the picker, then searches
  // here.
  const [search, setSearch] = useState('');
  // Drill state: which category is open (by id, so the live category object is
  // re-resolved as `categories` rebuilds — important for the step category whose
  // contents change when step data loads on demand), and the nested node path.
  const [openCategoryId, setOpenCategoryId] = useState<TokenCategory['id'] | null>(null);
  const [path, setPath] = useState<TokenNode[]>([]);
  // Keyboard active-descendant index within the currently visible option list.
  // -1 = NO active row (nothing highlighted until the user presses an arrow key)
  // so the picker does not show a phantom selection on open / view change.
  const [activeIndex, setActiveIndex] = useState(-1);
  // Computed viewport-aware placement: horizontal shift to stay on-screen, the
  // resolved top (below the caret, or flipped above when there's more room), and
  // a dynamic max-height capped to the available space (internal scroll handles
  // the rest). Initialized to the natural below-caret anchor.
  const [layout, setLayout] = useState<{ dx: number; top: number; maxHeight: number; placement: 'below' | 'above' }>(
    { dx: 0, top: position.y, maxHeight: PICKER_MAX_HEIGHT, placement: 'below' },
  );
  // Whether the in-step dataset dropdown is expanded.
  const [datasetDropdownOpen, setDatasetDropdownOpen] = useState(false);

  // Feature J: the Step-data category is offered (load-on-demand) when the
  // selected node has a resolvable owning event AND the model is not a template.
  const canResolveStepData = !isTemplate && !!owningEventId;

  const categories = useMemo(
    () =>
      buildTokenCategories({
        stepData,
        globalTokens,
        templateTokens,
        isTemplate,
        canResolveStepData,
        stepPredicted: stepDataPredicted,
        labels: {
          step: t('Step data tokens'),
          global: t('Global tokens'),
          template: t('Template tokens'),
        },
      }),
    [stepData, globalTokens, templateTokens, isTemplate, canResolveStepData, stepDataPredicted],
  );

  // Resolve the live open category from its id (re-resolved each render so the
  // step category reflects freshly-loaded data). Falls back to null when the
  // category is no longer present (e.g. step category disappears).
  const openCategory = useMemo(
    () => (openCategoryId ? categories.find((c) => c.id === openCategoryId) ?? null : null),
    [openCategoryId, categories],
  );

  // Whether the step-data category is currently open (Feature J view).
  const isStepCategoryOpen = openCategory?.id === 'step';

  const isFiltering = search.trim().length > 0;

  // When filtering, show a flat list of matching usable tokens across every
  // category. Otherwise show the current drill level (categories or nodes).
  const filteredTokens = useMemo(() => {
    if (!isFiltering) return [];
    const all = categories.flatMap((c) => flattenUsableTokens(c.nodes));
    return all.filter((n) => tokenMatchesQuery(n, search));
  }, [isFiltering, categories, search]);

  // Nodes visible at the current drill level (non-filtering mode).
  const currentNodes: TokenNode[] = useMemo(() => {
    if (!openCategory) return [];
    if (path.length === 0) return openCategory.nodes;
    const last = path[path.length - 1];
    return last.data ? Object.values(last.data) : [];
  }, [openCategory, path]);

  // Feature J: whether a real data entry is selected for the owning event.
  const dataEntrySelected =
    selectedEntryIndex >= 0 && selectedEntryIndex < replayEntries.length;
  // The inline waiting state shows while the listen item is selected (listener
  // armed) or the owning event's history is still loading on demand.
  const stepShowsWaiting =
    isStepCategoryOpen && (isListening || selectedEntryIndex === LISTEN_ITEM_INDEX || isLoadingStepData);

  // Load-on-demand: when the (non-filtering) step category opens and there is no
  // cached step data yet — and we are not already listening/loading — ask Flow
  // to enter the owning event's review session (which starts the SINGLE listener
  // and loads history). Routed entirely through Flow; the picker never calls the
  // test runner / loader directly. Guarded so it fires once per open.
  const stepLoadRequestedRef = useRef(false);
  useEffect(() => {
    if (!isStepCategoryOpen) {
      stepLoadRequestedRef.current = false;
      return;
    }
    if (stepLoadRequestedRef.current) return;
    const alreadyHasContext =
      hasStepData ||
      replayEntries.length > 0 ||
      selectedEntryIndex === LISTEN_ITEM_INDEX ||
      isLoadingStepData ||
      isListening;
    if (!alreadyHasContext && owningEventId && onLoadStepData) {
      stepLoadRequestedRef.current = true;
      onLoadStepData(owningEventId);
    }
  }, [
    isStepCategoryOpen,
    hasStepData,
    replayEntries.length,
    selectedEntryIndex,
    isLoadingStepData,
    isListening,
    owningEventId,
    onLoadStepData,
  ]);

  // Feature J (picker-VIEW only): while the picker is parked on the "Listen…"
  // item, auto-show ONLY genuinely NEW live data — never pre-existing history.
  //
  // Decision: selecting "Listen" stays in the waiting state; existing datasets
  // remain in the dropdown but do NOT auto-replace it. Only when a NEW event
  // fires (live capture prepends a fresh entry at index 0) does the picker
  // auto-switch to that fresh dataset — once. This is driven by an entry-COUNT
  // increase beyond a baseline captured when listening began (which INCLUDES
  // any history loaded as part of arming the listener). Routed through the same
  // Flow `onSelectDataset` the dropdown uses; Review's A39 behavior is untouched.
  //
  // Baseline sentinel: -1 = not yet captured for the current listen cycle.
  const listenBaselineCountRef = useRef(-1);
  const autoSelectedRef = useRef(false);
  useEffect(() => {
    const onListenItem = selectedEntryIndex === LISTEN_ITEM_INDEX;

    // Leaving the step category, no entries, or no longer parked on the listen
    // item → reset the cycle so the next listen re-snapshots its baseline and a
    // future new arrival can auto-show again.
    if (!isStepCategoryOpen || replayEntries.length === 0 || !onListenItem) {
      listenBaselineCountRef.current = -1;
      autoSelectedRef.current = false;
      return;
    }

    // Parked on the listen item. Wait for any in-flight history load to settle
    // so the baseline INCLUDES the initially-loaded history (entering Listen via
    // the on-demand load must NOT auto-select that history).
    if (isLoadingStepData) return;

    // Capture the baseline ONCE per listen cycle (sentinel -1). Subsequent
    // re-renders while still listening do NOT move the baseline up.
    if (listenBaselineCountRef.current < 0) {
      listenBaselineCountRef.current = replayEntries.length;
      autoSelectedRef.current = false;
      return;
    }

    // A NEW entry arrived since listening began (live capture prepends index 0)
    // → auto-show it ONCE.
    if (
      !autoSelectedRef.current &&
      replayEntries.length > listenBaselineCountRef.current &&
      onSelectDataset
    ) {
      autoSelectedRef.current = true;
      onSelectDataset(0);
    }
  }, [
    isStepCategoryOpen,
    replayEntries.length,
    selectedEntryIndex,
    isLoadingStepData,
    onSelectDataset,
  ]);

  // The list of selectable rows for keyboard navigation at the current view.
  const navItemCount = isFiltering
    ? filteredTokens.length
    : openCategory
      ? currentNodes.length
      : categories.length;

  // Whether the current view actually renders a `.token-picker-list` (vs a
  // compact empty/waiting/hint-only state). Mirrors the render branches below
  // exactly: filtering with matches; the root category list; or a drill level
  // with nodes (the step-data top level only shows its list when not waiting).
  const stepTopLevel = isStepCategoryOpen && path.length === 0;
  const showingTokenList = isFiltering
    ? filteredTokens.length > 0
    : !openCategory
      ? categories.length > 0
      : stepTopLevel
        ? !stepShowsWaiting && currentNodes.length > 0
        : currentNodes.length > 0;
  // The listen/waiting step view (spinner + open-able dataset selector): give it
  // a generous popup floor so the dropdown has room (Caveat 1).
  const showingWaiting = stepTopLevel && stepShowsWaiting;

  // Reset to NO active row (-1) whenever the visible list changes, so a fresh
  // view never shows a phantom highlight before the user navigates.
  useEffect(() => {
    setActiveIndex(-1);
  }, [openCategory, path, search, isFiltering]);

  // aria-activedescendant only points at a real option when one is active;
  // when nothing is highlighted (-1) it is omitted so screen readers don't
  // announce a phantom active option.
  const activeDescendantId = activeIndex >= 0 ? `token-picker-opt-${activeIndex}` : undefined;

  // Viewport-aware placement + sizing: keep the popup on-screen horizontally,
  // flip it ABOVE the caret when there's more room above, and cap its height to
  // the available space (the body scrolls internally for the rest). The popup
  // is `position: fixed`, so `position.{x,y}` and all measurements are
  // viewport-relative. Runs in a layout effect (pre-paint) so there is no flash
  // of a mis-sized/mis-placed popup, and re-runs on content/anchor changes and
  // on window resize.
  useLayoutEffect(() => {
    const el = popupRef.current;
    if (!el || typeof window === 'undefined') return;

    const computeLayout = () => {
      const node = popupRef.current;
      if (!node) return;
      const rect = node.getBoundingClientRect();
      if (rect.width === 0) return;

      // Horizontal clamp (unchanged): shift left if overflowing the right edge,
      // but never past the left margin.
      let dx = 0;
      const overflowRight = rect.right - (window.innerWidth - PICKER_MARGIN);
      if (overflowRight > 0) dx = -overflowRight;
      if (rect.left + dx < PICKER_MARGIN) dx = PICKER_MARGIN - rect.left;

      // The caret anchor in viewport coords: `position.y` is the caret BOTTOM
      // (where we anchor below). Approximate the caret TOP from the popup's own
      // top when below, else from `position.y`. Use the natural content height
      // (scrollHeight) as the desired height, capped to PICKER_MAX_HEIGHT.
      const anchorBottom = position.y;
      const anchorTop = position.y;
      const desired = Math.min(PICKER_MAX_HEIGHT, node.scrollHeight);
      // Raise the popup floor by view: a token list reserves chrome + ~5 rows;
      // the listen/waiting view reserves room for the open dataset selector;
      // empty/hint views keep the smaller compact floor. The placement logic
      // clamps this target to the available viewport space (no overflow).
      const min = pickerMinHeight({ showingTokenList, showingWaiting }, PICKER_MIN_HEIGHT);
      const placement = computePickerPlacement({
        anchorTop,
        anchorBottom,
        viewportHeight: window.innerHeight,
        margin: PICKER_MARGIN,
        desired,
        min,
      });

      setLayout((prev) =>
        prev.dx === dx &&
        prev.top === placement.top &&
        prev.maxHeight === placement.maxHeight &&
        prev.placement === placement.placement
          ? prev
          : { dx, top: placement.top, maxHeight: placement.maxHeight, placement: placement.placement },
      );
    };

    computeLayout();
    window.addEventListener('resize', computeLayout);
    return () => window.removeEventListener('resize', computeLayout);
    // Recompute when content (and thus size) or anchor changes.
  }, [position.x, position.y, openCategory, path, search, isFiltering, categories, showingTokenList, showingWaiting]);

  const handleUse = useCallback(
    (node: TokenNode) => {
      if (node.token) {
        onSelect(node.label, node.token);
      }
    },
    [onSelect],
  );

  const enterNode = useCallback((node: TokenNode) => {
    // Drill into a node that has children; leaf nodes are inserted via "Use".
    if (node.data && Object.keys(node.data).length > 0) {
      setPath((prev) => [...prev, node]);
    }
  }, []);

  const goBack = useCallback(() => {
    if (path.length > 0) {
      setPath((prev) => prev.slice(0, -1));
    } else {
      // Leaving the step-data category top level: if the live listener is armed,
      // STOP it so listen state does not linger on the category view. Only stop
      // when actually listening — a normal Back must not cancel anything.
      const listening = isListening || selectedEntryIndex === LISTEN_ITEM_INDEX;
      if (isStepCategoryOpen && listening) {
        onStopListen?.();
      }
      setOpenCategoryId(null);
      setDatasetDropdownOpen(false);
    }
  }, [path.length, isStepCategoryOpen, isListening, selectedEntryIndex, onStopListen]);

  // Feature J dataset-dropdown handlers (route through Flow — single listener).
  const handleDatasetSelect = useCallback(
    (index: number) => {
      onSelectDataset?.(index);
      setDatasetDropdownOpen(false);
    },
    [onSelectDataset],
  );

  const handleListenSelect = useCallback(() => {
    onStartListen?.();
    setDatasetDropdownOpen(false);
  }, [onStartListen]);

  // Modal: move focus INTO the picker's search input on open so the user can
  // type to filter immediately, and so keyboard nav + Escape work even after the
  // host contenteditable field blurs (the picker is portaled out of the field
  // and rendered as a modal dialog). Click-outside dismissal is owned by the
  // backdrop the host renders behind this dialog — not a document listener
  // here — so background controls are blocked while it is open. Falls back to
  // the popup itself if the input is unavailable.
  useEffect(() => {
    (searchInputRef.current ?? popupRef.current)?.focus();
  }, []);

  // Keyboard navigation. Listens at the document level so it works regardless of
  // exactly which element inside the modal currently holds focus.
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        // From "no active row" (-1), the first ArrowDown lands on index 0.
        setActiveIndex((i) => (navItemCount === 0 ? -1 : (i + 1 + navItemCount) % navItemCount));
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        // From "no active row" (-1), ArrowUp lands on the LAST item; otherwise
        // wrap as usual.
        setActiveIndex((i) =>
          navItemCount === 0 ? -1 : i < 0 ? navItemCount - 1 : (i - 1 + navItemCount) % navItemCount,
        );
        return;
      }
      if (e.key === 'ArrowLeft' && !isFiltering && openCategory) {
        e.preventDefault();
        goBack();
        return;
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        // Nothing highlighted yet → Enter is a no-op (don't act on index 0).
        if (activeIndex < 0) {
          return;
        }
        if (isFiltering) {
          const node = filteredTokens[activeIndex];
          if (node) handleUse(node);
          return;
        }
        if (!openCategory) {
          const cat = categories[activeIndex];
          if (cat) {
            setOpenCategoryId(cat.id);
            setPath([]);
          }
          return;
        }
        // Inside the step category, Enter navigation targets its token tree
        // (the dataset dropdown is mouse/space-driven); fall through to the
        // shared node handling below.
        const node = currentNodes[activeIndex];
        if (node) {
          if (node.data && Object.keys(node.data).length > 0) {
            enterNode(node);
          } else {
            handleUse(node);
          }
        }
      }
    };
    document.addEventListener('keydown', handleKeyDown, true);
    return () => document.removeEventListener('keydown', handleKeyDown, true);
  }, [
    navItemCount,
    isFiltering,
    openCategory,
    categories,
    currentNodes,
    filteredTokens,
    activeIndex,
    goBack,
    enterNode,
    handleUse,
    onClose,
  ]);

  const listboxId = 'token-picker-listbox';

  // ---- Render helpers ----

  const renderEmptyStepHint = (): React.ReactNode => {
    if (hasStepData || !reviewAvailable || !onReviewModel) return null;
    return (
      <div className="token-picker-hint">
        <FiActivity className="token-picker-hint-icon" aria-hidden="true" />
        <p>{t('Review the flow to get richer tokens from captured step data.')}</p>
        <button
          type="button"
          className="token-picker-review-btn"
          onClick={onReviewModel}
          onMouseDown={(e) => e.preventDefault()}
        >
          {t('Review the flow')}
        </button>
      </div>
    );
  };

  // Feature J: the dataset dropdown shown at the top of the step-data category
  // view. Mirrors ReplayPanelContent's selector semantics: a persistent
  // "Listen…" item at the top, then data entries newest-first; the most-recent
  // entry is selected by default (Flow selects index 0 on load). Selecting a
  // dataset / Listen routes through Flow (single source of truth, one listener).
  const renderDatasetDropdown = (): React.ReactNode => {
    const listenSelected = selectedEntryIndex === LISTEN_ITEM_INDEX;
    const toggleLabel = listenSelected ? (
      <>
        <FiActivity className="token-picker-dataset-icon" aria-hidden="true" />
        {t('Listening for the event…')}
      </>
    ) : dataEntrySelected ? (
      <>
        <FiClock className="token-picker-dataset-icon" aria-hidden="true" />
        {formatTimestamp(replayEntries[selectedEntryIndex].timestamp)}
        {' — '}
        {formatUser(replayEntries[selectedEntryIndex].user)}
      </>
    ) : (
      t('Select a dataset…')
    );
    return (
      <div className="token-picker-dataset-selector">
        <button
          type="button"
          className="token-picker-dataset-toggle"
          onClick={() => setDatasetDropdownOpen((prev) => !prev)}
          onMouseDown={(e) => e.preventDefault()}
          aria-expanded={datasetDropdownOpen}
          aria-haspopup="listbox"
          aria-label={t('Select step data dataset')}
        >
          <span className="token-picker-dataset-toggle-label">{toggleLabel}</span>
          <FiChevronDown
            className={`token-picker-dataset-chevron ${datasetDropdownOpen ? 'open' : ''}`}
            aria-hidden="true"
          />
        </button>
        {datasetDropdownOpen && (
          <div className="token-picker-dataset-list" role="listbox" aria-label={t('Step data datasets')}>
            {/* Persistent TOP item: Listen for the event to happen. */}
            <div
              className={`token-picker-dataset-item token-picker-listen-item ${listenSelected ? 'selected' : ''}`}
              role="option"
              aria-selected={listenSelected}
              tabIndex={0}
              onClick={handleListenSelect}
              onMouseDown={(e) => e.preventDefault()}
              onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleListenSelect(); } }}
            >
              <FiActivity className="token-picker-dataset-icon" aria-hidden="true" />
              <span className="token-picker-dataset-item-label">{t('Listen to event to happen')}</span>
            </div>
            {replayEntries.map((entry, index) => (
              <div
                key={index}
                className={`token-picker-dataset-item ${index === selectedEntryIndex ? 'selected' : ''}`}
                role="option"
                aria-selected={index === selectedEntryIndex}
                tabIndex={0}
                onClick={() => handleDatasetSelect(index)}
                onMouseDown={(e) => e.preventDefault()}
                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleDatasetSelect(index); } }}
              >
                <FiClock className="token-picker-dataset-icon" aria-hidden="true" />
                <span className="token-picker-dataset-item-label">
                  {formatTimestamp(entry.timestamp)}
                  {' — '}
                  {formatUser(entry.user)}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    );
  };

  // Feature J: the body shown below the dataset dropdown inside the step view.
  //   • waiting → listener armed (listen item) or history still loading.
  //   • tokens  → the selected dataset's step-data token tree (same nodes the
  //     Review panel renders, derived from the active session's step data).
  //   • empty   → no dataset selected / no step data captured.
  const renderStepBody = (): React.ReactNode => {
    if (stepShowsWaiting) {
      const polling = isLoadingStepData && !isListening;
      return (
        <div className="token-picker-listening">
          <FiRefreshCw className="spinning token-picker-listening-icon" aria-hidden="true" />
          <p>{polling ? t('Polling for data…') : t('Listening for event…')}</p>
          {!polling && (
            <p className="token-picker-listening-hint">
              {t('Trigger the selected event on your Drupal site so that the workflow gets executed and the results are captured.')}
            </p>
          )}
        </div>
      );
    }
    if (currentNodes.length === 0) {
      return (
        <div className="token-picker-empty">
          <p>{t('No step data captured yet.')}</p>
        </div>
      );
    }
    return (
      <div
        className="token-picker-list"
        role="listbox"
        tabIndex={0}
        id={listboxId}
        aria-label={openCategory?.label}
        aria-activedescendant={activeDescendantId}
      >
        {currentNodes.map((node, idx) => {
          const hasChildren = !!node.data && Object.keys(node.data).length > 0;
          if (hasChildren) {
            return (
              <button
                key={`${node.label}-${idx}`}
                type="button"
                id={`token-picker-opt-${idx}`}
                role="option"
                aria-selected={idx === activeIndex}
                className={`token-picker-option token-picker-category ${idx === activeIndex ? 'active' : ''}`}
                onClick={() => enterNode(node)}
                onMouseDown={(e) => e.preventDefault()}
              >
                <span className="token-picker-category-label">
                  {node.label}
                  <span className="token-picker-count">({Object.keys(node.data!).length})</span>
                </span>
                <FiChevronRight aria-hidden="true" />
              </button>
            );
          }
          return (
            <TokenLeafRow
              key={`${node.label}-${idx}`}
              id={`token-picker-opt-${idx}`}
              node={node}
              active={idx === activeIndex}
              onUse={handleUse}
            />
          );
        })}
      </div>
    );
  };

  let body: React.ReactNode;

  if (isFiltering) {
    body =
      filteredTokens.length > 0 ? (
        <div
          className="token-picker-list"
          role="listbox"
        tabIndex={0}
          id={listboxId}
          aria-label={t('Matching tokens')}
          aria-activedescendant={activeDescendantId}
        >
          {filteredTokens.map((node, idx) => (
            <TokenLeafRow
              key={`${node.token}-${idx}`}
              id={`token-picker-opt-${idx}`}
              node={node}
              active={idx === activeIndex}
              onUse={handleUse}
            />
          ))}
        </div>
      ) : (
        <div className="token-picker-empty">
          <p>{t('No tokens match "@query"', { '@query': search })}</p>
        </div>
      );
  } else if (!openCategory) {
    body = (
      <>
        <div className="token-picker-heading">{t('Select token category')}</div>
        {categories.length > 0 ? (
          <div
            className="token-picker-list"
            role="listbox"
        tabIndex={0}
            id={listboxId}
            aria-label={t('Token categories')}
            aria-activedescendant={activeDescendantId}
          >
            {categories.map((cat, idx) => (
              <button
                key={cat.id}
                type="button"
                id={`token-picker-opt-${idx}`}
                role="option"
                aria-selected={idx === activeIndex}
                className={`token-picker-option token-picker-category ${idx === activeIndex ? 'active' : ''}`}
                onClick={() => {
                  setOpenCategoryId(cat.id);
                  setPath([]);
                }}
                onMouseDown={(e) => e.preventDefault()}
              >
                <span className="token-picker-category-label">
                  {cat.label}
                  <span className="token-picker-count">({cat.count})</span>
                </span>
                <FiChevronRight aria-hidden="true" />
              </button>
            ))}
          </div>
        ) : (
          <div className="token-picker-empty">
            <p>{t('No tokens available.')}</p>
          </div>
        )}
        {renderEmptyStepHint()}
      </>
    );
  } else {
    // Drill-down view within a category.
    body = (
      <>
        <div className="token-picker-breadcrumb">
          <button
            type="button"
            className="token-picker-back"
            onClick={goBack}
            onMouseDown={(e) => e.preventDefault()}
            aria-label={t('Back')}
          >
            <FiChevronLeft aria-hidden="true" /> {t('Back')}
          </button>
          {(() => {
            const crumb = buildBreadcrumb(openCategory.label, path.map((p) => p.label));
            return (
              <span className="token-picker-crumb-label" title={crumb.title}>
                <span className="token-picker-crumb-lead">{crumb.lead}</span>
                {crumb.hasTrailing && <span className="token-picker-crumb-sep"> / </span>}
                {crumb.hasTrailing && <span className="token-picker-crumb-tail">{crumb.tail}</span>}
              </span>
            );
          })()}
          {/* Show the "Predicted" indicator ONCE, as a header-level status, and
              only for the step-data category when its data is predicted (never
              for the global/template categories). */}
          {isStepCategoryOpen && stepDataPredicted && <PredictedBadge />}
        </div>
        {/* Feature J: at the top level of the step-data category, show the
            dataset dropdown and the load-on-demand waiting/empty/token body. */}
        {isStepCategoryOpen && path.length === 0 ? (
          <>
            {renderDatasetDropdown()}
            {renderStepBody()}
          </>
        ) : (
        <div
          className="token-picker-list"
          role="listbox"
        tabIndex={0}
          id={listboxId}
          aria-label={openCategory.label}
          aria-activedescendant={activeDescendantId}
        >
          {currentNodes.map((node, idx) => {
            const hasChildren = !!node.data && Object.keys(node.data).length > 0;
            if (hasChildren) {
              return (
                <button
                  key={`${node.label}-${idx}`}
                  type="button"
                  id={`token-picker-opt-${idx}`}
                  role="option"
                  aria-selected={idx === activeIndex}
                  className={`token-picker-option token-picker-category ${idx === activeIndex ? 'active' : ''}`}
                  onClick={() => enterNode(node)}
                  onMouseDown={(e) => e.preventDefault()}
                >
                  <span className="token-picker-category-label">
                    {node.label}
                    <span className="token-picker-count">({Object.keys(node.data!).length})</span>
                  </span>
                  <FiChevronRight aria-hidden="true" />
                </button>
              );
            }
            return (
              <TokenLeafRow
                key={`${node.label}-${idx}`}
                id={`token-picker-opt-${idx}`}
                node={node}
                active={idx === activeIndex}
                onUse={handleUse}
              />
            );
          })}
        </div>
        )}
      </>
    );
  }

  return (
    <div
      ref={popupRef}
      className={`token-picker token-picker--${layout.placement}`}
      data-placement={layout.placement}
      style={{
        left: `${position.x + layout.dx}px`,
        top: `${layout.top}px`,
        maxHeight: `${layout.maxHeight}px`,
      }}
      role="dialog"
      aria-modal="true"
      aria-label={t('Insert a token')}
      tabIndex={-1}
      onMouseDown={(e) => e.stopPropagation()}
    >
      {/* Persistent modal header: title + close (×). The × is always available
          regardless of the current drill state. */}
      <div className="token-picker-header">
        <span className="token-picker-title">{t('Insert a token')}</span>
        <button
          type="button"
          className="token-picker-close"
          aria-label={t('Close')}
          onClick={onClose}
          onMouseDown={(e) => e.preventDefault()}
        >
          <FiX aria-hidden="true" />
        </button>
      </div>
      {/* Search box (DECISION A): owns its own filter state, auto-focused on
          open. Typing here filters the token list; it is independent of the
          host field's text. */}
      <div className="token-picker-search">
        <FiSearch className="token-picker-search-icon" aria-hidden="true" />
        <input
          ref={searchInputRef}
          type="text"
          className="token-picker-search-input"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder={t('Search tokens…')}
          aria-label={t('Search tokens')}
          autoComplete="off"
          spellCheck={false}
        />
      </div>
      {body}
    </div>
  );
});

TokenPicker.displayName = 'TokenPicker';

export default TokenPicker;
