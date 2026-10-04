import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

/**
 * E2E tests for the granular permissions system.
 *
 * The modeler API provides `drupalSettings.modeler_api.permissions` with
 * boolean flags that control feature availability.  All flags default to
 * `true` when not specified.
 *
 * Each permission is tested in both states: enabled (true) and disabled (false).
 */

// ---------------------------------------------------------------------------
// edit metadata
// ---------------------------------------------------------------------------
test.describe('Permission: edit metadata', () => {
  test.describe('enabled (default)', () => {
    let modeler: ModelerPage;

    test.beforeEach(async ({ page }) => {
      await setupMocks(page, { permissions: { 'edit metadata': true } });
      modeler = new ModelerPage(page);
      await modeler.goto();
    });

    test('should allow editing metadata fields', async () => {
      await modeler.openSettings();

      // Top-level fields
      await expect(modeler.getMetadataField('label')).toBeVisible();
      expect(await modeler.isMetadataFieldEditable('label')).toBe(true);
      expect(await modeler.isMetadataFieldEditable('documentation')).toBe(true);
      expect(await modeler.isMetadataFieldEditable('tags')).toBe(true);

      // Version, storage and changelog live in the collapsed Advanced group
      await modeler.expandMetadataGroup('Advanced');
      expect(await modeler.isMetadataFieldEditable('version')).toBe(true);
      expect(await modeler.isMetadataFieldEditable('changelog')).toBe(true);
      await expect(modeler.getMetadataField('storage')).toBeEnabled();

      // The recipe export fields sit in their own group
      await modeler.expandMetadataGroup('Recipe export');
      expect(await modeler.isMetadataFieldEditable('summary')).toBe(true);
      expect(await modeler.isMetadataFieldEditable('config_actions')).toBe(true);
    });

    test('should show Save button', async () => {
      await modeler.openSettings();
      const saveBtn = modeler.getMetadataSaveButton();
      await expect(saveBtn).toBeVisible();
    });
  });

  test.describe('disabled', () => {
    let modeler: ModelerPage;

    test.beforeEach(async ({ page }) => {
      await setupMocks(page, { permissions: { 'edit metadata': false } });
      modeler = new ModelerPage(page);
      await modeler.goto();
    });

    test('should make metadata fields read-only for existing models', async () => {
      await modeler.openSettings();

      expect(await modeler.isMetadataFieldEditable('label')).toBe(false);
      expect(await modeler.isMetadataFieldEditable('documentation')).toBe(false);
      expect(await modeler.isMetadataFieldEditable('tags')).toBe(false);

      await modeler.expandMetadataGroup('Advanced');
      expect(await modeler.isMetadataFieldEditable('version')).toBe(false);
      expect(await modeler.isMetadataFieldEditable('changelog')).toBe(false);
      await expect(modeler.getMetadataField('storage')).toBeDisabled();
    });

    test('should hide Save button and show Close instead of Cancel', async () => {
      await modeler.openSettings();

      // Save button should not exist
      const saveBtn = modeler.getMetadataSaveButton();
      await expect(saveBtn).toHaveCount(0);

      // The remaining button should say "Close" (not "Cancel")
      const closeBtn = modeler.getMetadataModal().locator('button.btn-secondary');
      await expect(closeBtn).toHaveText('Close');
    });

    test('should still allow editing for new models despite permission being false', async ({ page }) => {
      // New models should always be editable
      await setupMocks(page, {
        isNew: true,
        permissions: { 'edit metadata': false },
      });
      modeler = new ModelerPage(page);
      await modeler.goto('new-model');

      // Metadata modal opens automatically for new models
      await page.waitForSelector('.metadata-modal', { state: 'visible', timeout: 5000 });

      // Label field should be editable even though permission is false
      expect(await modeler.isMetadataFieldEditable('label')).toBe(true);

      // Save button should be present
      const saveBtn = modeler.getMetadataSaveButton();
      await expect(saveBtn).toBeVisible();
    });
  });
});

// ---------------------------------------------------------------------------
// changelog visibility (hidden for new models)
// ---------------------------------------------------------------------------
test.describe('MetadataModal: changelog visibility', () => {
  test('should hide changelog field when creating a new model', async ({ page }) => {
    await setupMocks(page, { isNew: true });
    const modeler = new ModelerPage(page);
    await modeler.goto('new-model');

    // Metadata modal opens automatically for new models
    await page.waitForSelector('.metadata-modal', { state: 'visible', timeout: 5000 });

    // Changelog field should NOT be present
    const changelogField = modeler.getMetadataField('changelog');
    await expect(changelogField).toHaveCount(0);
  });

  test('should show changelog field when editing an existing model', async ({ page }) => {
    await setupMocks(page);
    const modeler = new ModelerPage(page);
    await modeler.goto();

    await modeler.openSettings();

    // Changelog lives in the collapsed Advanced group
    await modeler.expandMetadataGroup('Advanced');
    await expect(modeler.getMetadataField('changelog')).toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// switch context
// ---------------------------------------------------------------------------
test.describe('Permission: switch context', () => {
  test.describe('enabled (default)', () => {
    test('should show context dropdown when contexts are available', async ({ page }) => {
      await setupMocks(page, {
        withContexts: true,
        permissions: { 'switch context': true },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      const contextSelect = modeler.getContextSelect();
      await expect(contextSelect).toBeVisible();
    });

    test('should list context options', async ({ page }) => {
      await setupMocks(page, {
        withContexts: true,
        permissions: { 'switch context': true },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      const contextSelect = modeler.getContextSelect();
      const options = contextSelect.locator('option');
      // "No Context" + 2 mock contexts = 3 options
      await expect(options).toHaveCount(3);
    });
  });

  test.describe('disabled', () => {
    test('should hide context dropdown even when contexts exist', async ({ page }) => {
      await setupMocks(page, {
        withContexts: true,
        permissions: { 'switch context': false },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      const contextSelect = modeler.getContextSelect();
      await expect(contextSelect).not.toBeVisible();
    });
  });
});

// ---------------------------------------------------------------------------
// create template
// ---------------------------------------------------------------------------
test.describe('Permission: create template', () => {
  test.describe('enabled (default)', () => {
    let modeler: ModelerPage;

    test.beforeEach(async ({ page }) => {
      await setupMocks(page, { permissions: { 'create template': true } });
      modeler = new ModelerPage(page);
      await modeler.goto();
    });

    test('should allow toggling template checkbox', async () => {
      await modeler.openSettings();

      const templateCheckbox = modeler.getTemplateCheckbox();
      await expect(templateCheckbox).toBeEnabled();
    });
  });

  test.describe('disabled', () => {
    let modeler: ModelerPage;

    test.beforeEach(async ({ page }) => {
      await setupMocks(page, { permissions: { 'create template': false } });
      modeler = new ModelerPage(page);
      await modeler.goto();
    });

    test('should disable template checkbox', async () => {
      await modeler.openSettings();

      const templateCheckbox = modeler.getTemplateCheckbox();
      await expect(templateCheckbox).toBeDisabled();
    });

    test('should keep other metadata fields editable', async () => {
      await modeler.openSettings();

      // The label field should still be editable
      expect(await modeler.isMetadataFieldEditable('label')).toBe(true);

      // The Enabled checkbox should still work
      const executableCheckbox = modeler.getMetadataModal()
        .locator('label.checkbox-wrapper', { hasText: 'Enabled' })
        .locator('input[type="checkbox"]');
      await expect(executableCheckbox).toBeEnabled();
    });
  });
});

// ---------------------------------------------------------------------------
// test (execution)
// ---------------------------------------------------------------------------
test.describe('Permission: test', () => {
  test.describe('enabled (default)', () => {
    test('should expose the Test affordance (listen-to-test) when test_url is configured', async ({ page }) => {
      // Unified panel (#3576269): there is NO standalone Test button. A test is
      // started from WITHIN Review mode by selecting the persistent "listen"
      // item in the execution-replay dropdown. The single gate that grants
      // access to it is the "Review flow" button, which appears for an event
      // node when replay/test capability is present.
      await setupMocks(page, {
        withTestUrl: true,
        permissions: { test: true },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      // Unified panel is always present.
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });

      // The Review affordance (gate to the listen-to-test item) is available
      // for the event node.
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await expect(modeler.getTestAffordanceGate()).toBeVisible();

      // Entering Review and opening the dropdown surfaces the listen-to-test item.
      await modeler.enterReviewMode();
      await modeler.openReplayEntryDropdown();
      await expect(modeler.getTestButton()).toBeVisible();
    });
  });

  test.describe('disabled', () => {
    test('should hide the Test affordance even when test_url is configured', async ({ page }) => {
      // With test denied (and replay also implicitly gated), the Review
      // affordance — the only path to the listen-to-test item — must be absent.
      await setupMocks(page, {
        withTestUrl: true,
        permissions: { test: false, replay: false },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      // No way to reach the listen-to-test item: the Review gate is absent.
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await expect(modeler.getTestAffordanceGate()).toHaveCount(0);
    });

    test('should still expose the Review affordance when replay is available (test is separate)', async ({ page }) => {
      // The `test` permission alone does not remove replay capability: with
      // replay still granted, the Review affordance remains available.
      await setupMocks(page, {
        permissions: { test: false },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      // Select event node — the Review (replay) affordance should be present.
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);

      const replayBtn = modeler.getReplayLoadButton();
      await expect(replayBtn).toBeVisible();
    });
  });
});

// ---------------------------------------------------------------------------
// replay
// ---------------------------------------------------------------------------
test.describe('Permission: replay', () => {
  test.describe('enabled (default)', () => {
    test('should show replay load button in property panel for event nodes', async ({ page }) => {
      await setupMocks(page, { permissions: { replay: true } });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);

      const replayBtn = modeler.getReplayLoadButton();
      await expect(replayBtn).toBeVisible();
    });

    test('should allow loading replay data', async ({ page }) => {
      await setupMocks(page, { permissions: { replay: true } });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      await modeler.loadReplayData();

      // Replay panel should show entries
      await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });
      const toggle = modeler.getReplayEntryToggle();
      await expect(toggle).toBeVisible();
    });
  });

  test.describe('disabled', () => {
    test('should hide the Review (replay) button in the property panel', async ({ page }) => {
      await setupMocks(page, { permissions: { replay: false } });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);

      // With no replay capability the Review affordance is not rendered at all.
      const replayBtn = modeler.getReplayLoadButton();
      await expect(replayBtn).toHaveCount(0);
    });

    test('should not show any Review affordance when replay is denied', async ({ page }) => {
      // Unified panel (#3576269): the property panel itself is ALWAYS present
      // (it hosts component properties); there is no separate replay column to
      // hide. The correct contract is that NO Review/replay AFFORDANCE is
      // offered when capability is absent. Without replay (and without test),
      // hasAnyReplayCapability is false, so the Review button is never rendered.
      await setupMocks(page, {
        permissions: { replay: false, test: false },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      // Even after selecting an event node, no Review affordance appears.
      await modeler.selectNode('event_1');
      await page.waitForTimeout(500);
      await expect(modeler.getReviewModeButton()).toHaveCount(0);
    });

    test('should hide the Test affordance even if test permission is granted (replay implies test)', async ({ page }) => {
      // Replay denied but test allowed — the Test affordance must still be
      // unreachable because the only path to it (the Review flow) requires
      // replay capability, which is absent.
      await setupMocks(page, {
        withTestUrl: true,
        permissions: { replay: false, test: true },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      // No Review affordance → no way to reach the listen-to-test item.
      await modeler.selectNode('event_1');
      await page.waitForTimeout(500);
      await expect(modeler.getReviewModeButton()).toHaveCount(0);
      await expect(modeler.getTestAffordanceGate()).toHaveCount(0);
    });
  });
});

// ---------------------------------------------------------------------------
// edit template
// ---------------------------------------------------------------------------
test.describe('Permission: edit template', () => {
  test.describe('enabled (default)', () => {
    test('should allow editing a template model', async ({ page }) => {
      // Model is an existing template, user has edit template permission
      await setupMocks(page, {
        withTemplate: true,
        permissions: { 'edit template': true },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      // Model should load — canvas should be visible
      await expect(modeler.canvas).toBeVisible();

      // Metadata modal should be openable and editable
      await modeler.openSettings();
      expect(await modeler.isMetadataFieldEditable('label')).toBe(true);
    });
  });

  test.describe('disabled', () => {
    test('should enter read-only mode for existing template models', async ({ page }) => {
      // Model is an existing template, user lacks edit template permission
      // — the entire modeler falls back to read-only mode.
      await setupMocks(page, {
        withTemplate: true,
        permissions: { 'edit template': false },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      // Canvas should still render
      await expect(modeler.canvas).toBeVisible();

      // Save button should be hidden (read-only)
      await expect(modeler.saveButton).not.toBeVisible();

      // Per-element locking has been removed
    });

    test('should make metadata fields read-only for template models without permission', async ({ page }) => {
      await setupMocks(page, {
        withTemplate: true,
        permissions: { 'edit template': false },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      await modeler.openSettings();

      // Label field should be read-only
      expect(await modeler.isMetadataFieldEditable('label')).toBe(false);

      // Save button should not exist in modal
      const saveBtn = modeler.getMetadataSaveButton();
      await expect(saveBtn).toHaveCount(0);
    });

    test('should disable property panel fields for template models without permission', async ({ page }) => {
      await setupMocks(page, {
        withTemplate: true,
        permissions: { 'edit template': false },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      // Select a node — property panel should show disabled fields
      await modeler.selectNode('event_1');
      await page.waitForTimeout(300);
      const labelInput = modeler.propertyPanel.locator('#modeler-component-label');
      await expect(labelInput).toBeDisabled();
    });

    test('should not affect non-template models but disable template checkbox', async ({ page }) => {
      // Model is NOT a template, edit template permission is false
      // — metadata stays editable but the template checkbox is disabled
      //   so the user cannot turn a non-template into a template.
      await setupMocks(page, {
        permissions: { 'edit template': false },
      });
      const modeler = new ModelerPage(page);
      await modeler.goto();

      await expect(modeler.canvas).toBeVisible();

      // Save button should still be visible (not read-only)
      await expect(modeler.saveButton).toBeVisible();

      // Metadata should remain editable
      await modeler.openSettings();
      expect(await modeler.isMetadataFieldEditable('label')).toBe(true);

      // Template checkbox should be disabled
      const templateCheckbox = modeler.getTemplateCheckbox();
      await expect(templateCheckbox).toBeDisabled();
    });
  });
});

// ---------------------------------------------------------------------------
// Combined permission scenarios
// ---------------------------------------------------------------------------
test.describe('Permissions: combined scenarios', () => {
  test('should enforce multiple denied permissions simultaneously', async ({ page }) => {
    await setupMocks(page, {
      withTestUrl: true,
      withContexts: true,
      permissions: {
        'edit metadata': false,
        'switch context': false,
        'create template': false,
        test: false,
        replay: false,
      },
    });
    const modeler = new ModelerPage(page);
    await modeler.goto();

    // Context dropdown should be hidden
    const contextSelect = modeler.getContextSelect();
    await expect(contextSelect).not.toBeVisible();

    // Select the event node first so the property-panel header is populated.
    await modeler.selectNode('event_1');
    await page.waitForTimeout(300);

    // With replay + test denied, no Review affordance is rendered, so the Test
    // affordance (reachable only via Review) is unreachable.
    const replayBtn = modeler.getReplayLoadButton();
    await expect(replayBtn).toHaveCount(0);
    await expect(modeler.getTestAffordanceGate()).toHaveCount(0);

    // Open metadata modal — fields should be read-only
    await modeler.openSettings();
    expect(await modeler.isMetadataFieldEditable('label')).toBe(false);

    // Template checkbox should be disabled
    const templateCheckbox = modeler.getTemplateCheckbox();
    await expect(templateCheckbox).toBeDisabled();

    // Save button should not exist
    const saveBtn = modeler.getMetadataSaveButton();
    await expect(saveBtn).toHaveCount(0);
  });

  test('should work with all permissions explicitly enabled', async ({ page }) => {
    await setupMocks(page, {
      withTestUrl: true,
      withContexts: true,
      permissions: {
        'edit metadata': true,
        'switch context': true,
        'create template': true,
        'edit template': true,
        test: true,
        replay: true,
      },
    });
    const modeler = new ModelerPage(page);
    await modeler.goto();

    // Context dropdown should be visible
    const contextSelect = modeler.getContextSelect();
    await expect(contextSelect).toBeVisible();

    // Unified panel is always present.
    await expect(modeler.replayPanel).toBeVisible({ timeout: 5000 });

    // Review (replay) affordance should be visible for event nodes, and the
    // listen-to-test item is reachable through it.
    await modeler.selectNode('event_1');
    await page.waitForTimeout(300);
    const replayBtn = modeler.getReplayLoadButton();
    await expect(replayBtn).toBeVisible();

    await modeler.enterReviewMode();
    await modeler.openReplayEntryDropdown();
    await expect(modeler.getTestButton()).toBeVisible();

    // Open metadata modal — fields should be editable
    await modeler.openSettings();
    expect(await modeler.isMetadataFieldEditable('label')).toBe(true);

    // Template checkbox should be enabled
    const templateCheckbox = modeler.getTemplateCheckbox();
    await expect(templateCheckbox).toBeEnabled();

    // Save button should be present
    const saveBtn = modeler.getMetadataSaveButton();
    await expect(saveBtn).toBeVisible();
  });
});
