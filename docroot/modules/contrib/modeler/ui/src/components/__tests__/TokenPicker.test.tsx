import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import TokenPicker from '../TokenPicker';
import { TokenSourceContext } from '../TokenSourceContext';
import type { TokenSourceValue } from '../TokenSourceContext';

jest.mock('react-icons/fi', () => ({
  FiChevronRight: () => <span data-testid="fi-chevron-right" />,
  FiChevronLeft: () => <span data-testid="fi-chevron-left" />,
  FiSearch: () => <span data-testid="fi-search" />,
  FiActivity: () => <span data-testid="fi-activity" />,
  FiChevronDown: () => <span data-testid="fi-chevron-down" />,
  FiClock: () => <span data-testid="fi-clock" />,
  FiRefreshCw: () => <span data-testid="fi-refresh-cw" />,
  FiX: () => <span data-testid="fi-x" />,
}));

// The persistent "Listen…" dropdown item index (mirrors useReplayLoader's -2).
const LISTEN_ITEM_INDEX = -2;

// Sample Drupal-shaped global tokens (name + "raw token" → transformGlobalToken).
const sampleGlobalTokens = {
  '[site:name]': { name: 'Site name', 'raw token': '[site:name]', token: 'name', value: 'My Site' },
  '[current-user:name]': {
    name: 'Current user',
    'raw token': '[current-user]',
    token: 'current-user',
    children: {
      'account-name': { name: 'Account name', 'raw token': '[current-user:account-name]', token: 'account-name' },
      'display-name': { name: 'User name', 'raw token': '[current-user:display-name]', token: 'display-name' },
    },
  },
} as any;

const sampleTemplateTokens = {
  '[template:author]': { name: 'Author', 'raw token': '[template:author]', token: 'author' },
} as any;

const sampleStepData = {
  user: {
    label: 'User',
    data: {
      name: { label: 'User name', token: '[user:name]', value: 'admin' },
    },
  },
} as any;

function renderPicker(
  sources: Partial<TokenSourceValue>,
  props?: Partial<React.ComponentProps<typeof TokenPicker>>,
) {
  const onSelect = jest.fn();
  const onClose = jest.fn();
  const value: TokenSourceValue = { reviewAvailable: true, ...sources };
  const utils = render(
    <TokenSourceContext.Provider value={value}>
      <TokenPicker
        position={{ x: 0, y: 0 }}
        onSelect={onSelect}
        onClose={onClose}
        {...props}
      />
    </TokenSourceContext.Provider>,
  );
  return { onSelect, onClose, ...utils };
}

// Type into the picker's own search box (DECISION A: filtering lives in the
// picker, not the host field). Returns the input element for convenience.
function searchFor(text: string): HTMLInputElement {
  const input = screen.getByLabelText('Search tokens') as HTMLInputElement;
  fireEvent.change(input, { target: { value: text } });
  return input;
}

describe('TokenPicker', () => {
  describe('category list', () => {
    it('should show the "Select token category" heading', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      expect(screen.getByText('Select token category')).toBeTruthy();
    });

    it('should list Global tokens with a count', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      expect(screen.getByText('Global tokens')).toBeTruthy();
      expect(screen.getByText('(2)')).toBeTruthy();
    });

    it('should list Step data tokens when step data is present', () => {
      renderPicker({ stepData: sampleStepData, hasStepData: true });
      expect(screen.getByText('Step data tokens')).toBeTruthy();
    });

    it('should list Template tokens only when isTemplate is true', () => {
      const { rerender } = renderPicker({ templateTokens: sampleTemplateTokens, isTemplate: false });
      expect(screen.queryByText('Template tokens')).toBeNull();

      rerender(
        <TokenSourceContext.Provider value={{ templateTokens: sampleTemplateTokens, isTemplate: true, reviewAvailable: true }}>
          <TokenPicker position={{ x: 0, y: 0 }} onSelect={jest.fn()} onClose={jest.fn()} />
        </TokenSourceContext.Provider>,
      );
      expect(screen.getByText('Template tokens')).toBeTruthy();
    });
  });

  describe('drill-down', () => {
    it('should open a category and show its nodes', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      fireEvent.click(screen.getByText('Global tokens'));
      // Now showing the category contents with a Back affordance.
      expect(screen.getByLabelText('Back')).toBeTruthy();
      expect(screen.getByText('Site name')).toBeTruthy();
      expect(screen.getByText('Current user')).toBeTruthy();
    });

    it('should drill into a nested node and reveal leaf tokens', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      fireEvent.click(screen.getByText('Global tokens'));
      // "Current user" has children → drilling shows Account name / User name.
      fireEvent.click(screen.getByText('Current user'));
      expect(screen.getByText('Account name')).toBeTruthy();
      expect(screen.getByText('User name')).toBeTruthy();
    });

    it('should go back to the category list via Back', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      fireEvent.click(screen.getByText('Global tokens'));
      fireEvent.click(screen.getByLabelText('Back'));
      expect(screen.getByText('Select token category')).toBeTruthy();
    });

    it('breadcrumb truncates in the middle: rightmost crumb in the tail, full path in the title', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      fireEvent.click(screen.getByText('Global tokens'));
      fireEvent.click(screen.getByText('Current user')); // drill one level
      const label = document.querySelector('.token-picker-crumb-label')!;
      // (a) the trailing region holds the LAST (rightmost) crumb, fully visible.
      const tail = label.querySelector('.token-picker-crumb-tail');
      expect(tail).toBeTruthy();
      expect(tail!.textContent).toBe('Current user');
      // (b) the full ' / '-joined path is on the label's title (hover tooltip).
      expect(label.getAttribute('title')).toBe('Global tokens / Current user');
      // (c) the leading (shrinkable) region contains the category.
      const lead = label.querySelector('.token-picker-crumb-lead');
      expect(lead!.textContent).toBe('Global tokens');
    });
  });

  describe('Use → inserts token', () => {
    it('should call onSelect with the leaf label and token', () => {
      const { onSelect } = renderPicker({ globalTokens: sampleGlobalTokens });
      fireEvent.click(screen.getByText('Global tokens'));
      // "Site name" is a leaf option; clicking the whole row inserts the token.
      const siteRow = screen.getByText('Site name').closest('.token-picker-option')!;
      fireEvent.click(siteRow);
      expect(onSelect).toHaveBeenCalledWith('Site name', '[site:name]');
    });
  });

  describe('leaf value display (subtext shows the resolved value, not the token)', () => {
    it('renders the resolved VALUE under the label (with a title tooltip) and NOT the token string', () => {
      renderPicker({ stepData: sampleStepData, hasStepData: true });
      fireEvent.click(screen.getByText('Step data tokens'));
      fireEvent.click(screen.getByText('User'));
      const row = screen.getByText('User name').closest('.token-picker-option')!;
      const valueEl = row.querySelector('.token-picker-leaf-value');
      // The value is shown...
      expect(valueEl).toBeTruthy();
      expect(valueEl!.textContent).toBe('admin');
      // ...with the full value available via the title attribute...
      expect(valueEl!.getAttribute('title')).toBe('admin');
      // ...and the token string is NOT rendered as subtext.
      expect(row.querySelector('.token-picker-leaf-token')).toBeNull();
      expect(row.textContent).not.toContain('[user:name]');
    });

    it('renders NO value/subtext element when the leaf has no runtime value', () => {
      const noValueStep = {
        item: {
          label: 'Item',
          data: { id: { label: 'Item id', token: '[item:id]' } }, // no `value`
        },
      } as any;
      renderPicker({ stepData: noValueStep, hasStepData: true });
      fireEvent.click(screen.getByText('Step data tokens'));
      fireEvent.click(screen.getByText('Item'));
      const row = screen.getByText('Item id').closest('.token-picker-option')!;
      expect(row.querySelector('.token-picker-leaf-value')).toBeNull();
      // Label-only row: no token-string fallback either.
      expect(row.textContent).not.toContain('[item:id]');
    });

    it('stringifies an object value for display', () => {
      const objStep = {
        order: {
          label: 'Order',
          data: { meta: { label: 'Order meta', token: '[order:meta]', value: { qty: 2, sku: 'A1' } } },
        },
      } as any;
      renderPicker({ stepData: objStep, hasStepData: true });
      fireEvent.click(screen.getByText('Step data tokens'));
      fireEvent.click(screen.getByText('Order'));
      const row = screen.getByText('Order meta').closest('.token-picker-option')!;
      const valueEl = row.querySelector('.token-picker-leaf-value');
      expect(valueEl).toBeTruthy();
      expect(valueEl!.textContent).toBe(JSON.stringify({ qty: 2, sku: 'A1' }));
    });
  });

  describe('predicted step tokens (issue #3577207)', () => {
    // A flat step token (top-level leaf).
    const predictedStep = {
      user: { label: 'User name', token: '[user:name]', value: 'admin' },
    } as any;

    // Structured step data: the top-level entry is a BRANCH (has children), the
    // user must DRILL IN to reach the leaf token. Used to prove drilling never
    // stamps per-row badges.
    const nestedPredictedStep = {
      entity: {
        label: 'Entity',
        data: {
          title: { label: 'Title', token: '[entity:title]', value: 'Hello' },
        },
      },
    } as any;

    // The single header-level badge lives in the breadcrumb region. Assert its
    // text, tooltip, and accessible label.
    const assertHeaderBadge = (badge: Element | null) => {
      expect(badge).toBeTruthy();
      expect(badge!.textContent).toContain('Predicted');
      expect(badge!.getAttribute('title')).toBe(
        'Predicted from the previous step; not yet confirmed by a test run.',
      );
      expect(badge!.getAttribute('aria-label')).toBe('Predicted token');
    };

    it('renders EXACTLY ONE predicted badge, in the step-data header, when stepDataPredicted is true', () => {
      renderPicker({ stepData: predictedStep, hasStepData: true, stepDataPredicted: true });
      fireEvent.click(screen.getByText('Step data tokens'));
      // Exactly one badge in the whole document...
      const badges = document.querySelectorAll('.token-predicted-badge');
      expect(badges.length).toBe(1);
      // ...and it lives in the breadcrumb/header region, not on a row.
      const header = document.querySelector('.token-picker-breadcrumb')!;
      assertHeaderBadge(header.querySelector('.token-predicted-badge'));
      // The leaf row itself carries NO badge.
      const row = screen.getByText('User name').closest('.token-picker-option')!;
      expect(row.querySelector('.token-predicted-badge')).toBeNull();
    });

    it('drilling into a predicted branch does NOT add per-row badges (still one, in the header)', () => {
      renderPicker({ stepData: nestedPredictedStep, hasStepData: true, stepDataPredicted: true });
      fireEvent.click(screen.getByText('Step data tokens'));
      // Branch row carries no badge; header still holds the single badge.
      const branchRow = screen.getByText('Entity').closest('.token-picker-category')!;
      expect(branchRow.querySelector('.token-predicted-badge')).toBeNull();
      expect(document.querySelectorAll('.token-predicted-badge').length).toBe(1);

      // Drill in → the nested leaf carries no badge, and there is still exactly
      // one badge total (in the header).
      fireEvent.click(screen.getByText('Entity'));
      const leafRow = screen.getByText('Title').closest('.token-picker-option')!;
      expect(leafRow.querySelector('.token-predicted-badge')).toBeNull();
      expect(document.querySelectorAll('.token-predicted-badge').length).toBe(1);
      const header = document.querySelector('.token-picker-breadcrumb')!;
      assertHeaderBadge(header.querySelector('.token-predicted-badge'));
    });

    it('renders ZERO predicted badges for confirmed step tokens (stepDataPredicted omitted)', () => {
      renderPicker({ stepData: predictedStep, hasStepData: true });
      fireEvent.click(screen.getByText('Step data tokens'));
      expect(document.querySelectorAll('.token-predicted-badge').length).toBe(0);
    });

    it('renders ZERO predicted badges for confirmed branch/nested-leaf step data', () => {
      renderPicker({ stepData: nestedPredictedStep, hasStepData: true });
      fireEvent.click(screen.getByText('Step data tokens'));
      expect(document.querySelectorAll('.token-predicted-badge').length).toBe(0);
      fireEvent.click(screen.getByText('Entity'));
      expect(document.querySelectorAll('.token-predicted-badge').length).toBe(0);
    });

    it('does NOT render the predicted badge for the GLOBAL category header', () => {
      // Even though stepDataPredicted is true, opening the Global category must
      // not show the badge — it is gated on the step-data category header.
      renderPicker({
        globalTokens: sampleGlobalTokens,
        stepData: predictedStep,
        hasStepData: true,
        stepDataPredicted: true,
      });
      fireEvent.click(screen.getByText('Global tokens'));
      // We are in the Global drill-down; no badge in its header or anywhere.
      expect(document.querySelector('.token-picker-breadcrumb')).toBeTruthy();
      expect(document.querySelectorAll('.token-predicted-badge').length).toBe(0);
    });

    it('does NOT render the predicted badge for the TEMPLATE category header', () => {
      renderPicker({
        templateTokens: sampleTemplateTokens,
        isTemplate: true,
        stepDataPredicted: true,
      });
      fireEvent.click(screen.getByText('Template tokens'));
      expect(document.querySelector('.token-picker-breadcrumb')).toBeTruthy();
      expect(document.querySelectorAll('.token-predicted-badge').length).toBe(0);
    });
  });

  describe('search box (DECISION A)', () => {
    it('should render a search input that is auto-focused on open', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      const input = screen.getByLabelText('Search tokens') as HTMLInputElement;
      expect(input).toBeTruthy();
      expect(input.getAttribute('placeholder')).toBe('Search tokens…');
      // The input holds focus on open so the user can type to filter at once.
      expect(document.activeElement).toBe(input);
    });

    it('should show a flat list of matching usable tokens across categories', () => {
      renderPicker({ globalTokens: sampleGlobalTokens, stepData: sampleStepData, hasStepData: true });
      searchFor('name');
      // Matches: Site name [site:name], Account name, User name (global), User name (step).
      expect(screen.getByText('Site name')).toBeTruthy();
      expect(screen.getAllByText('User name').length).toBeGreaterThanOrEqual(1);
    });

    it('should filter to "Site name" when typing "name" in the search box', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      searchFor('name');
      expect(screen.getByText('Site name')).toBeTruthy();
    });

    it('should match on the raw token string', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      searchFor('site');
      expect(screen.getByText('Site name')).toBeTruthy();
      expect(screen.queryByText('Account name')).toBeNull();
    });

    it('should show an empty message when nothing matches', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      searchFor('xylophone');
      expect(screen.getByText('No tokens match "xylophone"')).toBeTruthy();
    });

    it('should return to the category list when the search box is cleared', () => {
      renderPicker({ globalTokens: sampleGlobalTokens });
      searchFor('site');
      expect(screen.queryByText('Select token category')).toBeNull();
      searchFor('');
      expect(screen.getByText('Select token category')).toBeTruthy();
    });

    it('should insert a filtered token via Use', () => {
      const { onSelect } = renderPicker({ globalTokens: sampleGlobalTokens });
      searchFor('site');
      const row = screen.getByText('Site name').closest('.token-picker-option')!;
      fireEvent.click(row);
      expect(onSelect).toHaveBeenCalledWith('Site name', '[site:name]');
    });
  });

  describe('empty step data hint', () => {
    it('should show the "Review the flow" hint when there is no step data', () => {
      const onReviewModel = jest.fn();
      renderPicker({ globalTokens: sampleGlobalTokens, hasStepData: false, reviewAvailable: true, onReviewModel });
      expect(screen.getByText('Review the flow to get richer tokens from captured step data.')).toBeTruthy();
      const reviewBtn = screen.getByText('Review the flow');
      fireEvent.click(reviewBtn);
      expect(onReviewModel).toHaveBeenCalled();
    });

    it('should NOT show the hint when step data is present', () => {
      renderPicker({ globalTokens: sampleGlobalTokens, stepData: sampleStepData, hasStepData: true });
      expect(screen.queryByText('Review the flow to get richer tokens from captured step data.')).toBeNull();
    });

    it('should NOT show the hint when review is unavailable', () => {
      renderPicker({ globalTokens: sampleGlobalTokens, hasStepData: false, reviewAvailable: false });
      expect(screen.queryByText('Review the flow')).toBeNull();
    });

    it('should never render the string "Test model"', () => {
      renderPicker({ globalTokens: sampleGlobalTokens, hasStepData: false, reviewAvailable: true, onReviewModel: jest.fn() });
      expect(screen.queryByText(/Test model/)).toBeNull();
    });
  });

  describe('keyboard', () => {
    it('should close on Escape', () => {
      const { onClose } = renderPicker({ globalTokens: sampleGlobalTokens });
      fireEvent.keyDown(document, { key: 'Escape' });
      expect(onClose).toHaveBeenCalled();
    });

    it('should open the active category on Enter, then use a leaf on Enter', () => {
      const { onSelect } = renderPicker({
        globalTokens: { '[site:name]': sampleGlobalTokens['[site:name]'] } as any,
      });
      // Nothing is highlighted on open (activeIndex -1) — first ArrowDown lands
      // on index 0, then Enter opens the only category.
      fireEvent.keyDown(document, { key: 'ArrowDown' });
      fireEvent.keyDown(document, { key: 'Enter' });
      expect(screen.getByLabelText('Back')).toBeTruthy();
      // Highlight the single leaf, then Enter inserts it.
      fireEvent.keyDown(document, { key: 'ArrowDown' });
      fireEvent.keyDown(document, { key: 'Enter' });
      expect(onSelect).toHaveBeenCalledWith('Site name', '[site:name]');
    });
  });

  // ── Caveat 2: no phantom highlight before keyboard navigation ─────────────
  describe('initial active row (no phantom highlight)', () => {
    it('highlights NO row on open and omits aria-activedescendant', () => {
      const { container } = renderPicker({ globalTokens: sampleGlobalTokens });
      expect(container.querySelector('.token-picker-option.active')).toBeNull();
      const listbox = container.querySelector('[role="listbox"]') as HTMLElement;
      expect(listbox.hasAttribute('aria-activedescendant')).toBe(false);
    });

    it('first ArrowDown highlights index 0 and sets aria-activedescendant', () => {
      const { container } = renderPicker({ globalTokens: sampleGlobalTokens });
      fireEvent.keyDown(document, { key: 'ArrowDown' });
      const active = container.querySelector('.token-picker-option.active');
      expect(active).toBeTruthy();
      expect(active?.id).toBe('token-picker-opt-0');
      const listbox = container.querySelector('[role="listbox"]') as HTMLElement;
      expect(listbox.getAttribute('aria-activedescendant')).toBe('token-picker-opt-0');
    });

    it('first ArrowUp (from none) highlights the LAST row', () => {
      // Two top-level categories: Global + (the single sample). Use a set with
      // a known count: globalTokens has 2 entries → 1 "Global tokens" category.
      // To get multiple rows, drill is needed; instead assert on the category
      // list which has at least 1 row, so last === 0 here. Use a 2-row view by
      // opening the multi-child category.
      const { container } = renderPicker({ globalTokens: sampleGlobalTokens });
      // Category list: 1 row (Global tokens). ArrowUp from none → last (index 0).
      fireEvent.keyDown(document, { key: 'ArrowUp' });
      const active = container.querySelector('.token-picker-option.active');
      expect(active?.id).toBe('token-picker-opt-0');
    });

    it('Enter with nothing highlighted is a no-op (does not act on index 0)', () => {
      const { onSelect, container } = renderPicker({
        globalTokens: { '[site:name]': sampleGlobalTokens['[site:name]'] } as any,
      });
      // No ArrowDown first → activeIndex is -1.
      fireEvent.keyDown(document, { key: 'Enter' });
      // The category was NOT opened (no breadcrumb/Back), nothing inserted.
      expect(screen.queryByLabelText('Back')).toBeNull();
      expect(onSelect).not.toHaveBeenCalled();
      expect(container.querySelector('.token-picker-option.active')).toBeNull();
    });
  });

  describe('modal dialog semantics', () => {
    it('exposes role="dialog" with aria-modal and an aria-label', () => {
      const { container } = renderPicker({ globalTokens: sampleGlobalTokens });
      const dialog = container.querySelector('.token-picker') as HTMLElement;
      expect(dialog.getAttribute('role')).toBe('dialog');
      expect(dialog.getAttribute('aria-modal')).toBe('true');
      expect(dialog.getAttribute('aria-label')).toBe('Insert a token');
    });

    it('renders a close (×) button that calls onClose and has an accessible label', () => {
      const { onClose, container } = renderPicker({ globalTokens: sampleGlobalTokens });
      const closeBtn = container.querySelector('.token-picker-close') as HTMLElement;
      expect(closeBtn).toBeTruthy();
      expect(closeBtn.getAttribute('aria-label')).toBe('Close');
      fireEvent.click(closeBtn);
      expect(onClose).toHaveBeenCalled();
    });

    it('does NOT install a document-level click-outside listener (dismissal is owned by the host backdrop)', () => {
      // The picker no longer self-dismisses on a document mousedown; the host's
      // modal backdrop handles click-outside. A mousedown on the body is a no-op.
      const { onClose } = renderPicker({ globalTokens: sampleGlobalTokens });
      fireEvent.mouseDown(document.body);
      expect(onClose).not.toHaveBeenCalled();
    });
  });

  describe('no token sources', () => {
    it('should show a "No tokens available" message with an empty context', () => {
      render(
        <TokenSourceContext.Provider value={{}}>
          <TokenPicker position={{ x: 0, y: 0 }} onSelect={jest.fn()} onClose={jest.fn()} />
        </TokenSourceContext.Provider>,
      );
      expect(screen.getByText('No tokens available.')).toBeTruthy();
    });
  });

  // ── Feature J: on-demand step-data in the [-token picker ───────────────────
  describe('Feature J: on-demand step data', () => {
    const sampleEntries = [
      { model_id: 'm', component_id: 'event_1', history: [], timestamp: '2024-02-01T10:00:00Z', user: 'alice', ip: '', url: '' },
      { model_id: 'm', component_id: 'event_1', history: [], timestamp: '2024-01-01T09:00:00Z', user: 'bob', ip: '', url: '' },
    ] as any;

    it('shows the Step data category for a non-template node with a resolvable owning event even with NO session', () => {
      renderPicker({ globalTokens: sampleGlobalTokens, owningEventId: 'event_1', isTemplate: false });
      expect(screen.getByText('Step data tokens')).toBeTruthy();
    });

    it('does NOT show the Step data category when there is no owning event', () => {
      renderPicker({ globalTokens: sampleGlobalTokens, owningEventId: null, isTemplate: false });
      expect(screen.queryByText('Step data tokens')).toBeNull();
    });

    it('does NOT show the Step data category for a template model', () => {
      renderPicker({ globalTokens: sampleGlobalTokens, owningEventId: 'event_1', isTemplate: true });
      expect(screen.queryByText('Step data tokens')).toBeNull();
    });

    it('triggers onLoadStepData with the owning event id when the empty step category is opened', () => {
      const onLoadStepData = jest.fn();
      renderPicker({ globalTokens: sampleGlobalTokens, owningEventId: 'event_1', onLoadStepData });
      fireEvent.click(screen.getByText('Step data tokens'));
      expect(onLoadStepData).toHaveBeenCalledWith('event_1');
    });

    it('does NOT trigger onLoadStepData when entering a step category that already has cached data', () => {
      const onLoadStepData = jest.fn();
      renderPicker({ owningEventId: 'event_1', stepData: sampleStepData, hasStepData: true, onLoadStepData });
      fireEvent.click(screen.getByText('Step data tokens'));
      expect(onLoadStepData).not.toHaveBeenCalled();
    });

    it('renders the loading ("Polling for data…") state while step data is being loaded', () => {
      renderPicker({ owningEventId: 'event_1', isLoadingStepData: true, onLoadStepData: jest.fn() });
      fireEvent.click(screen.getByText('Step data tokens'));
      expect(screen.getByText('Polling for data…')).toBeTruthy();
      expect(
        screen.queryByText(
          'Trigger the selected event on your Drupal site so that the workflow gets executed and the results are captured.',
        ),
      ).toBeNull();
    });

    it('renders the dataset dropdown newest-first with the most-recent selected by default', () => {
      renderPicker({
        owningEventId: 'event_1',
        replayEntries: sampleEntries,
        selectedEntryIndex: 0,
        stepData: sampleStepData,
        hasStepData: true,
      });
      fireEvent.click(screen.getByText('Step data tokens'));
      // Open the dataset dropdown.
      fireEvent.click(screen.getByLabelText('Select step data dataset'));
      const listbox = screen.getByRole('listbox', { name: 'Step data datasets' });
      const options = listbox.querySelectorAll('[role="option"]');
      // Listen item + 2 datasets.
      expect(options.length).toBe(3);
      // First data option (index 1) is the newest entry and is selected.
      expect(options[1].getAttribute('aria-selected')).toBe('true');
    });

    it('changing the dataset calls onSelectDataset with the chosen index', () => {
      const onSelectDataset = jest.fn();
      renderPicker({
        owningEventId: 'event_1',
        replayEntries: sampleEntries,
        selectedEntryIndex: 0,
        stepData: sampleStepData,
        hasStepData: true,
        onSelectDataset,
      });
      fireEvent.click(screen.getByText('Step data tokens'));
      fireEvent.click(screen.getByLabelText('Select step data dataset'));
      const listbox = screen.getByRole('listbox', { name: 'Step data datasets' });
      const options = listbox.querySelectorAll('[role="option"]');
      // Select the second data entry (index 1).
      fireEvent.click(options[2]);
      expect(onSelectDataset).toHaveBeenCalledWith(1);
    });

    it('selecting "Listen…" calls onStartListen and shows the inline waiting state without closing the picker', () => {
      const onStartListen = jest.fn();
      const onClose = jest.fn();
      const value: TokenSourceValue = {
        reviewAvailable: true,
        owningEventId: 'event_1',
        replayEntries: sampleEntries,
        selectedEntryIndex: 0,
        stepData: sampleStepData,
        hasStepData: true,
        onStartListen,
      };
      render(
        <TokenSourceContext.Provider value={value}>
          <TokenPicker position={{ x: 0, y: 0 }} onSelect={jest.fn()} onClose={onClose} />
        </TokenSourceContext.Provider>,
      );
      fireEvent.click(screen.getByText('Step data tokens'));
      fireEvent.click(screen.getByLabelText('Select step data dataset'));
      const listbox = screen.getByRole('listbox', { name: 'Step data datasets' });
      const listenOption = listbox.querySelector('.token-picker-listen-item')!;
      fireEvent.click(listenOption);
      expect(onStartListen).toHaveBeenCalled();
      // Picker stays open (no close), step category still showing.
      expect(onClose).not.toHaveBeenCalled();
      expect(screen.getByLabelText('Back')).toBeTruthy();
    });

    it('Back while listening STOPS listening (calls onStopListen) and returns to the category list', () => {
      const onStopListen = jest.fn();
      render(
        <TokenSourceContext.Provider
          value={{
            reviewAvailable: true,
            owningEventId: 'event_1',
            replayEntries: [],
            selectedEntryIndex: LISTEN_ITEM_INDEX,
            isListening: true,
            stepData: sampleStepData,
            hasStepData: true,
            onStopListen,
          }}
        >
          <TokenPicker position={{ x: 0, y: 0 }} onSelect={jest.fn()} onClose={jest.fn()} />
        </TokenSourceContext.Provider>,
      );
      fireEvent.click(screen.getByText('Step data tokens'));
      // On the listen item (waiting). Back must stop listening and go back.
      fireEvent.click(screen.getByLabelText('Back'));
      expect(onStopListen).toHaveBeenCalledTimes(1);
      expect(screen.getByText('Select token category')).toBeTruthy();
    });

    it('Back from a NON-listening step view does NOT call onStopListen', () => {
      const onStopListen = jest.fn();
      render(
        <TokenSourceContext.Provider
          value={{
            reviewAvailable: true,
            owningEventId: 'event_1',
            replayEntries: sampleEntries,
            selectedEntryIndex: 0, // a real dataset is selected, not listening
            isListening: false,
            stepData: sampleStepData,
            hasStepData: true,
            onStopListen,
          }}
        >
          <TokenPicker position={{ x: 0, y: 0 }} onSelect={jest.fn()} onClose={jest.fn()} />
        </TokenSourceContext.Provider>,
      );
      fireEvent.click(screen.getByText('Step data tokens'));
      fireEvent.click(screen.getByLabelText('Back'));
      expect(onStopListen).not.toHaveBeenCalled();
      expect(screen.getByText('Select token category')).toBeTruthy();
    });

    it('Back from a NON-step category does NOT call onStopListen even if listening flags are set elsewhere', () => {
      const onStopListen = jest.fn();
      render(
        <TokenSourceContext.Provider
          value={{
            reviewAvailable: true,
            owningEventId: 'event_1',
            globalTokens: sampleGlobalTokens,
            selectedEntryIndex: LISTEN_ITEM_INDEX,
            isListening: true,
            onStopListen,
          }}
        >
          <TokenPicker position={{ x: 0, y: 0 }} onSelect={jest.fn()} onClose={jest.fn()} />
        </TokenSourceContext.Provider>,
      );
      // Open the GLOBAL category (not step) and go Back.
      fireEvent.click(screen.getByText('Global tokens'));
      fireEvent.click(screen.getByLabelText('Back'));
      expect(onStopListen).not.toHaveBeenCalled();
    });

    it('shows the listening waiting state when the listen item is selected', () => {
      renderPicker({
        owningEventId: 'event_1',
        replayEntries: sampleEntries,
        selectedEntryIndex: -2, // LISTEN_ITEM_INDEX
        isListening: true,
      });
      fireEvent.click(screen.getByText('Step data tokens'));
      expect(screen.getByText('Listening for event…')).toBeTruthy();
      expect(
        screen.getByText(
          'Trigger the selected event on your Drupal site so that the workflow gets executed and the results are captured.',
        ),
      ).toBeTruthy();
    });

    it('renders the selected dataset step-data tokens below the dropdown', () => {
      renderPicker({
        owningEventId: 'event_1',
        replayEntries: sampleEntries,
        selectedEntryIndex: 0,
        stepData: sampleStepData,
        hasStepData: true,
      });
      fireEvent.click(screen.getByText('Step data tokens'));
      // The step-data token tree (User) is rendered.
      expect(screen.getByText('User')).toBeTruthy();
    });
  });

  // Picker-VIEW-only behavior: while parked on the listen item, the picker
  // auto-shows ONLY genuinely NEW live data (an entry-count increase beyond the
  // baseline captured when listening began — which includes any initially-loaded
  // history). Pre-existing history stays in the dropdown but does NOT auto-replace
  // the "Listening…" state. Routed once through Flow's onSelectDataset.
  describe('Feature J: auto-select newest dataset in the picker (view-only)', () => {
    const sampleEntries = [
      { model_id: 'm', component_id: 'event_1', history: [], timestamp: '2024-02-01T10:00:00Z', user: 'alice', ip: '', url: '' },
      { model_id: 'm', component_id: 'event_1', history: [], timestamp: '2024-01-01T09:00:00Z', user: 'bob', ip: '', url: '' },
    ] as any;

    // Render the picker, open the Step-data category, and return a setSources
    // helper so a test can simulate data arrival via a controlled rerender.
    function renderListening(initial: Partial<TokenSourceValue>) {
      const onSelectDataset = jest.fn();
      const onClose = jest.fn();
      const base: TokenSourceValue = {
        reviewAvailable: true,
        owningEventId: 'event_1',
        onSelectDataset,
        ...initial,
      };
      const utils = render(
        <TokenSourceContext.Provider value={base}>
          <TokenPicker position={{ x: 0, y: 0 }} onSelect={jest.fn()} onClose={onClose} />
        </TokenSourceContext.Provider>,
      );
      // Open the Step-data category so the auto-select effect can fire.
      fireEvent.click(screen.getByText('Step data tokens'));
      const setSources = (next: Partial<TokenSourceValue>) =>
        utils.rerender(
          <TokenSourceContext.Provider value={{ ...base, ...next }}>
            <TokenPicker position={{ x: 0, y: 0 }} onSelect={jest.fn()} onClose={onClose} />
          </TokenSourceContext.Provider>,
        );
      return { onSelectDataset, setSources, ...utils };
    }

    it('entering Listen with EXISTING entries does NOT auto-select (stays listening)', () => {
      // Regression #3576269: history loads alongside the listener; entering
      // Listen with N>0 existing entries must NOT jump to a dataset.
      const { onSelectDataset } = renderListening({
        replayEntries: sampleEntries, // N=2 already present
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: false,
      });
      expect(onSelectDataset).not.toHaveBeenCalled();
    });

    it('auto-selects index 0 ONCE when a NEW entry arrives after listening began', () => {
      // Baseline N=2 captured on entry → no auto-select yet.
      const { onSelectDataset, setSources } = renderListening({
        replayEntries: sampleEntries,
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: false,
      });
      expect(onSelectDataset).not.toHaveBeenCalled();

      // A NEW live event fires → fresh entry prepended (length 3 > baseline 2).
      const withNew = [sampleEntries[0], ...sampleEntries];
      setSources({
        replayEntries: withNew,
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: false,
      });
      expect(onSelectDataset).toHaveBeenCalledTimes(1);
      expect(onSelectDataset).toHaveBeenCalledWith(0);

      // A re-render with the same entries does NOT fire again (one-shot).
      setSources({
        replayEntries: withNew,
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: false,
      });
      expect(onSelectDataset).toHaveBeenCalledTimes(1);
    });

    it('initial on-demand load: history loading in (0→N) is the BASELINE (not auto-selected); a later new arrival auto-selects once', () => {
      // Open via on-demand load: listening armed, loading, no entries yet.
      const { onSelectDataset, setSources } = renderListening({
        replayEntries: [],
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: true,
      });
      expect(onSelectDataset).not.toHaveBeenCalled();

      // History settles (0 → 2) while still listening: baseline = 2, NO auto-select.
      setSources({
        replayEntries: sampleEntries,
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: false,
      });
      expect(onSelectDataset).not.toHaveBeenCalled();

      // Now a genuinely new live event arrives (2 → 3) → auto-select once.
      setSources({
        replayEntries: [sampleEntries[0], ...sampleEntries],
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: false,
      });
      expect(onSelectDataset).toHaveBeenCalledTimes(1);
      expect(onSelectDataset).toHaveBeenCalledWith(0);
    });

    it('re-arming Listen after viewing a dataset re-snapshots the baseline (no auto-select of existing entries)', () => {
      // Listening began with baseline 2, a new entry arrived → auto-selected.
      const { onSelectDataset, setSources } = renderListening({
        replayEntries: sampleEntries,
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: false,
      });
      const withNew = [sampleEntries[0], ...sampleEntries]; // length 3
      setSources({ replayEntries: withNew, selectedEntryIndex: LISTEN_ITEM_INDEX, isListening: true, isLoadingStepData: false });
      expect(onSelectDataset).toHaveBeenCalledTimes(1);

      // User now views a real dataset (selectedEntryIndex 0) → cycle resets.
      setSources({ replayEntries: withNew, selectedEntryIndex: 0, isListening: false, isLoadingStepData: false });

      // User re-arms Listen: baseline re-snapshots to current length (3), so the
      // EXISTING 3 entries do NOT auto-select.
      onSelectDataset.mockClear();
      setSources({ replayEntries: withNew, selectedEntryIndex: LISTEN_ITEM_INDEX, isListening: true, isLoadingStepData: false });
      expect(onSelectDataset).not.toHaveBeenCalled();

      // Only the NEXT new arrival (3 → 4) auto-selects.
      setSources({ replayEntries: [sampleEntries[0], ...withNew], selectedEntryIndex: LISTEN_ITEM_INDEX, isListening: true, isLoadingStepData: false });
      expect(onSelectDataset).toHaveBeenCalledTimes(1);
      expect(onSelectDataset).toHaveBeenCalledWith(0);
    });

    it('does NOT auto-select again once the user has chosen a real entry', () => {
      const { onSelectDataset, setSources } = renderListening({
        replayEntries: sampleEntries,
        selectedEntryIndex: 0, // user already picked the newest
        isListening: false,
        isLoadingStepData: false,
      });
      // Already on a real entry → must not auto-select.
      expect(onSelectDataset).not.toHaveBeenCalled();

      // More entries arrive but the user is still on a real selection.
      setSources({
        replayEntries: [...sampleEntries, sampleEntries[0]],
        selectedEntryIndex: 0,
        isListening: false,
        isLoadingStepData: false,
      });
      expect(onSelectDataset).not.toHaveBeenCalled();
    });

    it('does NOT auto-select while the step category is CLOSED', () => {
      const onSelectDataset = jest.fn();
      render(
        <TokenSourceContext.Provider
          value={{
            reviewAvailable: true,
            owningEventId: 'event_1',
            replayEntries: sampleEntries,
            selectedEntryIndex: LISTEN_ITEM_INDEX,
            isListening: true,
            isLoadingStepData: false,
            onSelectDataset,
          }}
        >
          <TokenPicker position={{ x: 0, y: 0 }} onSelect={jest.fn()} onClose={jest.fn()} />
        </TokenSourceContext.Provider>,
      );
      // Category list is showing; the Step-data category was never opened.
      expect(onSelectDataset).not.toHaveBeenCalled();
    });

    it('does NOT auto-select while step data is still loading (no entries yet)', () => {
      const { onSelectDataset, setSources } = renderListening({
        replayEntries: [],
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: true,
      });
      // Loading with no entries → wait.
      setSources({
        replayEntries: [],
        selectedEntryIndex: LISTEN_ITEM_INDEX,
        isListening: true,
        isLoadingStepData: true,
      });
      expect(onSelectDataset).not.toHaveBeenCalled();
    });
  });
});
