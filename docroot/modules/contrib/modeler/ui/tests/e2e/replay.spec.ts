import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks, mockReplayEntries, mockTestReplayData, mockOrphanConstraints } from './fixtures/mocks';

test.describe('Workflow Modeler - Replay System', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('Loading Replay Data', () => {
    test('should show replay load button when event node is selected', async () => {
      // Select the event node
      await modeler.selectNode('event_1');
      await modeler.page.waitForTimeout(300);

      // The replay load button should be visible in the property panel header
      const replayBtn = modeler.getReplayLoadButton();
      await expect(replayBtn).toBeVisible();
    });

    test('should show the Review button ENABLED for an action node with a structural owning event (no session yet)', async () => {
      // Unified panel (#3576269): with replay capability present, the Review
      // button is ALWAYS rendered for footprint stability. Its enablement was
      // intentionally broadened (see PropertyPanel.test.tsx) to
      //   !isStartNode && reviewAvailable && (reviewableEventId || pickerOwningEventIdProp)
      // i.e. the button is ENABLED whenever the node has EITHER an active review
      // session OR merely a STRUCTURAL path to an owning event — even before any
      // session exists. `action_1` is structurally owned by `event_1`
      // (event_1 -> action_1 -> action_2 in the default mock model), so it is
      // correctly ENABLED here. The button is only DISABLED for a node with NO
      // owning event at all — see the dedicated orphan-node test below.
      await modeler.selectNode('action_1');
      await modeler.page.waitForTimeout(300);

      const replayBtn = modeler.getReplayLoadButton();
      await expect(replayBtn).toBeVisible();
      await expect(replayBtn).toBeEnabled();
    });

    test('should show the Review button DISABLED for an orphan action node with NO owning event', async ({ page }) => {
      // This preserves coverage for the true "not an event flow" case that
      // `action_1` no longer represents: a genuinely DISCONNECTED action node
      // with no edges to/from any event has no structural owning event and no
      // review session, so `reviewableEventId` and `pickerOwningEventIdProp` are
      // both empty and the Review button is DISABLED.
      //
      // The default mock model has no orphaned node, and adding one to the
      // shared model would break the exact node/edge count assertions in
      // modeler.spec.ts ("should display existing nodes/edges from model"). So
      // this test swaps in an isolated single-test model (mockOrphanModel) via
      // the `orphanNode` setupMocks flag — mirroring how the condition-reuse
      // tests inject `mockReuseModel` — without touching the shared fixture.
      await setupMocks(page, { orphanNode: true, modelConstraints: mockOrphanConstraints });
      const orphanModeler = new ModelerPage(page);
      await orphanModeler.goto();

      await orphanModeler.selectNode('action_orphan');
      await orphanModeler.page.waitForTimeout(300);

      const replayBtn = orphanModeler.getReplayLoadButton();
      await expect(replayBtn).toBeVisible();
      await expect(replayBtn).toBeDisabled();
    });

    test('should load replay data and show replay panel when button is clicked', async ({ page }) => {
      // Select event node
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);

      // Click the load replay button
      await modeler.loadReplayData();

      // Wait for the replay panel to appear
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });
    });

    test('should show replay entries in the replay panel after loading', async ({ page }) => {
      // Select event node and load replay data
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await modeler.loadReplayData();

      // Wait for replay panel to appear
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });

      // The entry selector toggle should be visible
      const toggle = modeler.getReplayEntryToggle();
      await expect(toggle).toBeVisible();
    });

    test('should auto-select the first entry after loading', async ({ page }) => {
      // Select event node and load replay data
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await modeler.loadReplayData();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });

      // The toggle label should show the first entry's timestamp (not "Select an execution...")
      const toggleLabel = modeler.replayPanel.locator('.replay-entry-toggle-label');
      await expect(toggleLabel).not.toHaveText('Select an execution...');
    });
  });

  test.describe('Replay Entry Selector', () => {
    test.beforeEach(async ({ page }) => {
      // Load replay data for each test in this group
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await modeler.loadReplayData();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });
    });

    test('should open entry dropdown when toggle is clicked', async () => {
      await modeler.openReplayEntryDropdown();

      // The dropdown list should be visible
      const dropdownList = modeler.replayPanel.locator('.replay-entry-list');
      await expect(dropdownList).toBeVisible();
    });

    test('should show correct number of entries in dropdown', async () => {
      await modeler.openReplayEntryDropdown();

      const entries = modeler.getReplayEntryItems();
      await expect(entries).toHaveCount(mockReplayEntries.length);
    });

    test('should display entry details (timestamp, user, IP, URL) in dropdown items', async () => {
      await modeler.openReplayEntryDropdown();

      // Check first entry has expected content
      const firstEntry = modeler.getReplayEntryItems().first();
      // It should contain the IP address
      await expect(firstEntry.locator('.entry-value').nth(2)).toHaveText('192.168.1.100');
      // It should contain the URL
      await expect(firstEntry.locator('.entry-value.entry-url')).toHaveText('/node/42/edit');
    });

    test('should mark the selected entry in the dropdown', async () => {
      await modeler.openReplayEntryDropdown();

      // First entry should be selected (auto-selected on load)
      const firstEntry = modeler.getReplayEntryItems().first();
      await expect(firstEntry).toHaveClass(/selected/);
    });

    test('should switch replay data when a different entry is selected', async ({ page }) => {
      // First entry has 3 steps, second entry has 1 step
      // Verify we start with 3 steps from the first entry
      const steps = modeler.getReplaySteps();
      await expect(steps).toHaveCount(3);

      // Select the second entry (which has only 1 step)
      await modeler.selectReplayEntry(1);
      await page.waitForTimeout(300);

      // Now should show 1 step
      await expect(steps).toHaveCount(1);
    });

    test('should close dropdown when clicking outside', async ({ page }) => {
      await modeler.openReplayEntryDropdown();
      const dropdownList = modeler.replayPanel.locator('.replay-entry-list');
      await expect(dropdownList).toBeVisible();

      // Click on the unified panel header (outside the dropdown) to trigger the
      // outside-click handler. Unified panel (#3576269): the old standalone
      // `.replay-panel-header` was removed; the panel header is now `.panel-header`.
      const panelHeader = modeler.replayPanel.locator('.panel-header');
      await panelHeader.dispatchEvent('mousedown');
      await page.waitForTimeout(300);

      await expect(dropdownList).not.toBeVisible();
    });

    test('should update toggle label when entry changes', async ({ page }) => {
      const toggleLabel = modeler.replayPanel.locator('.replay-entry-toggle-label');

      // Get the initial label text (first entry)
      const firstLabelText = await toggleLabel.textContent();

      // Select the second entry
      await modeler.selectReplayEntry(1);
      await page.waitForTimeout(300);

      // Label should change to reflect the second entry
      const secondLabelText = await toggleLabel.textContent();
      expect(secondLabelText).not.toBe(firstLabelText);
    });
  });

  test.describe('Replay Panel UI', () => {
    test.beforeEach(async ({ page }) => {
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await modeler.loadReplayData();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });
    });

    test('should display the review-mode back control and the execution-replay entry selector', async () => {
      // Unified panel (#3576269): the standalone ".replay-panel-header h3"
      // ("Execution Replay") bar was removed. Since #3589103 the Review header
      // is emptied down to a SINGLE control - a left arrow plus "Back" - and
      // carries no component-type label; the execution-replay entry selector is
      // rendered in the body.
      const header = modeler.replayPanel.locator('.panel-header');
      await expect(header.locator('.component-type')).toHaveCount(0);
      await expect(header.locator('.panel-header-back')).toHaveCount(1);

      const back = modeler.getBackToPropertiesButton();
      await expect(back).toBeVisible();
      await expect(back).toContainText('Back');
      await expect(modeler.getReplayEntryToggle()).toBeVisible();
    });

    test('should show the loaded step count via the step list', async () => {
      // The old ".replay-count" header was removed; the step count is now
      // reflected by the rendered step list (and the "Step X of N" progress
      // label). The first entry has 3 steps.
      const steps = modeler.getReplaySteps();
      await expect(steps).toHaveCount(3);
    });

    test('should display replay steps', async () => {
      const steps = modeler.getReplaySteps();
      // First entry has 3 steps
      await expect(steps).toHaveCount(3);
    });

    test('should display playback controls', async () => {
      const playBtn = modeler.replayPanel.locator('button[aria-label="Play"]');
      const stopBtn = modeler.replayPanel.locator('button[aria-label="Stop & Reset"]');
      const nextBtn = modeler.replayPanel.locator('button[aria-label="Next Step"]');
      const prevBtn = modeler.replayPanel.locator('button[aria-label="Previous Step"]');

      await expect(playBtn).toBeVisible();
      await expect(stopBtn).toBeVisible();
      await expect(nextBtn).toBeVisible();
      await expect(prevBtn).toBeVisible();
    });

    test('should display speed control', async () => {
      const speedControl = modeler.getSpeedControl();
      await expect(speedControl).toBeVisible();
    });

    test('should display progress bar', async () => {
      const progressBar = modeler.replayPanel.locator('.progress-bar');
      await expect(progressBar).toBeVisible();
    });

    test('should show "Step 1 of 3" initially (event node auto-syncs to its replay step)', async () => {
      // Unified panel (#3576269): selecting the event node and entering Review
      // auto-syncs the canvas selection to its matching replay step (the
      // "started" event = step 0), so the progress label starts at "Step 1 of 3"
      // rather than the old standalone-panel "Ready" (step -1).
      const label = modeler.getProgressLabel();
      await expect(label).toHaveText('Step 1 of 3');
    });
  });

  test.describe('Replay Step Navigation', () => {
    test.beforeEach(async ({ page }) => {
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await modeler.loadReplayData();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });
      // Entry auto-syncs to step 0 ("Step 1 of 3"); wait for it to settle so
      // navigation starts from a deterministic point.
      await expect(modeler.getProgressLabel()).toHaveText('Step 1 of 3', { timeout: 5000 });
    });

    test('should navigate to next step', async ({ page }) => {
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);

      const label = modeler.getProgressLabel();
      await expect(label).toHaveText('Step 2 of 3');
    });

    test('should navigate through all steps with next button', async ({ page }) => {
      // From the auto-synced "Step 1 of 3", step forward twice.
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);
      await expect(modeler.getProgressLabel()).toHaveText('Step 2 of 3');

      await modeler.nextReplayStep();
      await page.waitForTimeout(200);
      await expect(modeler.getProgressLabel()).toHaveText('Step 3 of 3');
    });

    test('should navigate back with previous button', async ({ page }) => {
      // Go forward to step 3, then back to step 2.
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);
      await expect(modeler.getProgressLabel()).toHaveText('Step 3 of 3');

      await modeler.previousReplayStep();
      await page.waitForTimeout(200);
      await expect(modeler.getProgressLabel()).toHaveText('Step 2 of 3');
    });

    test('should highlight current step in step list', async ({ page }) => {
      // Already on step 1 (index 0) after auto-sync; advance to step 2 (index 1).
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);

      const secondStep = modeler.getReplaySteps().nth(1);
      await expect(secondStep).toHaveClass(/current/);
    });

    test('should mark earlier steps as completed', async ({ page }) => {
      // From step 1 (index 0), advance to step 3 (index 2).
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);

      // Earlier steps (index 0 and 1) should be marked completed.
      const firstStep = modeler.getReplaySteps().first();
      await expect(firstStep).toHaveClass(/completed/);
      const secondStep = modeler.getReplaySteps().nth(1);
      await expect(secondStep).toHaveClass(/completed/);

      // Third step (index 2) should be current.
      const thirdStep = modeler.getReplaySteps().nth(2);
      await expect(thirdStep).toHaveClass(/current/);
    });

    test('should jump to step when step is clicked', async ({ page }) => {
      // Wait for any prior sync to settle before clicking
      await page.waitForTimeout(500);

      // Click on the third step directly (action step)
      const thirdStep = modeler.getReplaySteps().nth(2);
      await thirdStep.click();

      await expect(modeler.getProgressLabel()).toHaveText('Step 3 of 3', { timeout: 5000 });
      await expect(thirdStep).toHaveClass(/current/);
    });

    test('should reset when stop button is clicked', async ({ page }) => {
      // Navigate forward
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);

      // Stop/Reset
      await modeler.stopReplay();
      await page.waitForTimeout(200);

      await expect(modeler.getProgressLabel()).toHaveText('Ready');
    });
  });

  test.describe('Replay Playback', () => {
    test.beforeEach(async ({ page }) => {
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await modeler.loadReplayData();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });
    });

    test('should start playback when play is clicked', async ({ page }) => {
      await modeler.startReplay();
      await page.waitForTimeout(200);

      // Play button should become a Pause button
      const pauseBtn = modeler.replayPanel.locator('button[aria-label="Pause"]');
      await expect(pauseBtn).toBeVisible();
    });

    test('should pause playback when pause is clicked', async ({ page }) => {
      await modeler.startReplay();
      await page.waitForTimeout(200);

      await modeler.pauseReplay();
      await page.waitForTimeout(200);

      // Should show Play button again
      const playBtn = modeler.replayPanel.locator('button[aria-label="Play"]');
      await expect(playBtn).toBeVisible();
    });

    test('should change playback speed', async ({ page }) => {
      const speedControl = modeler.getSpeedControl();

      // Default should be 1x
      await expect(speedControl).toHaveValue('1');

      // Change to 2x
      await speedControl.selectOption('2');
      await expect(speedControl).toHaveValue('2');
    });

    test('play button should have playing class while playing', async ({ page }) => {
      await modeler.startReplay();
      await page.waitForTimeout(200);

      const playBtn = modeler.replayPanel.locator('.play-btn');
      await expect(playBtn).toHaveClass(/playing/);
    });
  });

  test.describe('Inline Step Data', () => {
    test.beforeEach(async ({ page }) => {
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await modeler.loadReplayData();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });
    });

    test('should auto-select a step on entry so its data renders inline under that step row', async () => {
      // Unified panel (#3576269): entering Review on an event node auto-syncs
      // the canvas selection to its matching replay step (step 0 = "Step 1 of
      // 3"), so a step is always selected. Since #3589103 the selected step's
      // data is rendered INLINE underneath its own row in the single step list,
      // so the inline region exists as soon as review opens.
      await expect(modeler.getProgressLabel()).toHaveText('Step 1 of 3', { timeout: 5000 });

      const inlineData = modeler.replayPanel.locator('.replay-steps .replay-step-data');
      await expect(inlineData).toHaveCount(1);
      await expect(inlineData).toBeVisible();
    });

    test('should render the inline data as a labeled region inside the selected step row', async () => {
      const inlineData = modeler.replayPanel.locator('.replay-steps .replay-step-data[aria-label="Step data"]');
      await expect(inlineData).toBeVisible();

      // The inline block belongs to the CURRENT step row, and that row is the
      // one advertising it via aria-expanded.
      const currentItem = modeler.replayPanel.locator('.replay-step-item:has(> .replay-step.current)');
      await expect(currentItem.locator('.replay-step-data')).toHaveCount(1);
      await expect(currentItem.locator('.replay-step[aria-expanded="true"]')).toHaveCount(1);
    });

    test('should move the inline data along when a different step is selected', async ({ page }) => {
      // Navigate to the action step (action_1 carries entity data).
      await expect(modeler.getProgressLabel()).toHaveText('Step 1 of 3', { timeout: 5000 });
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);
      await modeler.nextReplayStep();
      await page.waitForTimeout(200);

      // Still exactly ONE inline block, now under the newly selected row.
      const inlineData = modeler.replayPanel.locator('.replay-steps .replay-step-data');
      await expect(inlineData).toHaveCount(1);
      await expect(modeler.getProgressLabel()).toHaveText('Step 3 of 3');
      const currentItem = modeler.replayPanel.locator('.replay-step-item:has(> .replay-step.current)');
      await expect(currentItem.locator('.replay-step-data')).toHaveCount(1);
    });
  });

  test.describe('Property Panel Info Popup', () => {
    // Unified panel (#3576269): the metadata "info" button and popup are a
    // PROPERTIES-view feature (PropertyPanel header zone 3, gated on
    // `!isReviewMode`). The old standalone replay-panel info button was removed.
    // These tests verify the metadata popup in its actual current home: the
    // Properties view of a selected node.
    test.beforeEach(async ({ page }) => {
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await expect(modeler.propertyPanel).toBeVisible({ timeout: 5000 });
    });

    test('should show the metadata info button in the Properties view of a selected node', async () => {
      const infoBtn = modeler.propertyPanel.locator('button[aria-label="Show metadata"]');
      await expect(infoBtn).toBeVisible();
    });

    test('should open the info popup when the metadata button is clicked', async () => {
      const infoBtn = modeler.propertyPanel.locator('button[aria-label="Show metadata"]');
      await infoBtn.click();

      // Info popup should appear.
      const infoPopup = modeler.propertyPanel.locator('.info-popup');
      await expect(infoPopup).toBeVisible();
    });
  });

  test.describe('Replay Condition Step Selection', () => {
    test.beforeEach(async ({ page }) => {
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await modeler.loadReplayData();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });
    });

    test('should select the condition node in the canvas when a condition step is clicked', async ({ page }) => {
      // Conditions are first-class NODES now (issue #3589093).  The second
      // step (index 1) is an "add successor" carrying conditionId
      // 'eca_entity_is_new_10j5tps', which resolves to the promoted condition
      // node (cond__edge_1) — it is selected/highlighted, not an edge.
      const conditionStep = modeler.getReplaySteps().nth(1);
      await conditionStep.click();
      await page.waitForTimeout(500);

      // The condition node is selected on the canvas.
      const conditionNode = modeler.getConditionNode('cond__edge_1');
      await expect(conditionNode).toHaveClass(/selected/, { timeout: 5000 });
    });

    test('should show the condition node label in the property panel when a condition step is clicked', async ({ page }) => {
      // Click the condition step — this selects the condition node on the canvas
      // while the unified panel stays in Review mode.
      const conditionStep = modeler.getReplaySteps().nth(1);
      await conditionStep.click();
      await page.waitForTimeout(500);

      // Unified panel (#3576269): node properties are edited in the Properties
      // view, so switch from Review to Properties for the now-selected node.
      await modeler.getBackToPropertiesButton().click();
      await page.waitForTimeout(300);

      // The condition is edited via the NODE properties panel now, so the
      // node label input is shown (no `#modeler-condition-label` edge field).
      const labelInput = modeler.getNodeLabelInput();
      await expect(labelInput).toBeVisible({ timeout: 5000 });
      await expect(labelInput).toHaveValue('Entity is New');
    });

    test('should select the action node when an action step is clicked after a condition step', async ({ page }) => {
      // First click the condition step (selects the condition node).
      const conditionStep = modeler.getReplaySteps().nth(1);
      await conditionStep.click();
      await page.waitForTimeout(500);

      // Then click the action step (index 2)
      const actionStep = modeler.getReplaySteps().nth(2);
      await actionStep.click();
      await page.waitForTimeout(500);

      // The action node should now be selected.
      const actionNode = modeler.getNode('action_1');
      await expect(actionNode).toHaveClass(/selected/);

      // The condition node should no longer be selected.
      const conditionNode = modeler.getConditionNode('cond__edge_1');
      await expect(conditionNode).not.toHaveClass(/selected/);
    });
  });
});

test.describe('Workflow Modeler - Test Feature', () => {
  let modeler: ModelerPage;

  test.describe('Test Affordance Visibility', () => {
    // Unified panel (#3576269): there is NO standalone Test button. The Test
    // affordance is the "listen" item reached from within Review mode, and the
    // single gate to it is the "Review flow" button.
    test('should expose the Test affordance when test_url is configured for an event node', async ({ page }) => {
      await setupMocks(page, { withTestUrl: true });
      modeler = new ModelerPage(page);
      await modeler.goto();

      // Unified panel is always present.
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });

      // Selecting the event node surfaces the Review gate, through which the
      // listen-to-test item is reachable.
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await expect(modeler.getTestAffordanceGate()).toBeVisible();

      await modeler.enterReviewMode();
      await modeler.openReplayEntryDropdown();
      await expect(modeler.getTestButton()).toBeVisible();
    });

    test('should still expose the Review affordance (replay) when test_url is not configured', async ({ page }) => {
      await setupMocks(page); // no withTestUrl, replay_url still present
      modeler = new ModelerPage(page);
      await modeler.goto();

      // Replay capability (replay_url) is present, so the Review affordance is
      // available for the event node — but without test_url, entering Review
      // does not auto-start a test listener (no waiting state).
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await expect(modeler.getReviewModeButton()).toBeVisible();
    });
  });

  test.describe('Test Execution', () => {
    test.beforeEach(async ({ page }) => {
      await setupMocks(page, { withTestUrl: true, testPollWaitCount: 1 });
      modeler = new ModelerPage(page);
      await modeler.goto();
      // Wait for replay panel (visible because test_url is configured)
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });
    });

    test('should show waiting state after starting a test (entering Review auto-starts the listener)', async () => {
      await modeler.startTest();

      // Should show the waiting/polling state
      const waitingState = modeler.getTestWaitingState();
      await expect(waitingState).toBeVisible({ timeout: 5000 });
    });

    test('should show cancel button during test polling', async () => {
      await modeler.startTest();

      const cancelBtn = modeler.getTestCancelButton();
      await expect(cancelBtn).toBeVisible({ timeout: 5000 });
    });

    test('should load replay data after test completes', async () => {
      await modeler.startTest();

      // Wait for test to complete (1 poll wait then data returns)
      // The replay steps should appear with the test replay data
      const steps = modeler.getReplaySteps();
      await expect(steps).toHaveCount(mockTestReplayData.length, { timeout: 10000 });
    });

    test('should cancel test when cancel button is clicked', async ({ page }) => {
      // Use a high wait count so the test stays in polling
      await setupMocks(page, { withTestUrl: true, testPollWaitCount: 100 });
      modeler = new ModelerPage(page);
      await modeler.goto();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });

      await modeler.startTest();

      // Wait for waiting state to appear
      const waitingState = modeler.getTestWaitingState();
      await expect(waitingState).toBeVisible({ timeout: 5000 });

      // Cancel the test
      await modeler.cancelTest();

      // Waiting state should disappear
      await expect(waitingState).not.toBeVisible({ timeout: 5000 });
    });
  });

  test.describe('Test Error Handling', () => {
    // Unified panel (#3576269): entering Review auto-starts the listener and the
    // waiting state shows immediately (listen item selected). On a test error
    // the error is surfaced as a Drupal message and the listener simply does NOT
    // produce replay data — the panel stays in the waiting/listening state with
    // a Cancel option (it does not silently fabricate steps). The non-weakened
    // contract here is: an error never yields bogus replay step data.
    test('should not produce replay data when test initiation fails', async ({ page }) => {
      await setupMocks(page, { withTestUrl: true, testInitError: 'Event not supported' });
      modeler = new ModelerPage(page);
      await modeler.goto();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });

      await modeler.startTest();

      // Wait for the error to be processed.
      await page.waitForTimeout(1000);

      // No replay steps should have been produced by the failed test.
      await expect(modeler.getReplaySteps()).toHaveCount(0);
    });

    test('should not produce replay data when polling fails', async ({ page }) => {
      await setupMocks(page, { withTestUrl: true, testPollError: 'Execution timed out' });
      modeler = new ModelerPage(page);
      await modeler.goto();
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });

      await modeler.startTest();

      // Wait for the poll to happen and return the error.
      await page.waitForTimeout(3000);

      // No replay steps should have been produced by the failed poll.
      await expect(modeler.getReplaySteps()).toHaveCount(0);
    });
  });
});
