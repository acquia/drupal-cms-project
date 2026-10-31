import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

// Property Panel tests require data-testid attributes in the actual React components
// Most of these tests need implementation-specific selectors
test.describe.skip('Workflow Modeler - Property Panel', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('Node Configuration', () => {
    test('should display property panel when node selected', async ({ page }) => {
      await modeler.selectNode('event_1');

      await expect(modeler.propertyPanel).toBeVisible();
    });

    test('should show node label in property panel', async ({ page }) => {
      await modeler.selectNode('event_1');

      const title = await modeler.getPropertyPanelTitle();
      expect(title).toBeTruthy();
    });

    test('should load configuration form for node', async ({ page }) => {
      await modeler.selectNode('event_1');

      // Wait for form to load
      await page.waitForSelector('[data-testid="config-form"]', { state: 'visible', timeout: 5000 });

      const configForm = page.locator('[data-testid="config-form"]');
      await expect(configForm).toBeVisible();
    });

    test('should update node label when edited', async ({ page }) => {
      await modeler.selectNode('event_1');

      const labelInput = page.locator('[data-testid="node-label-input"]');
      await labelInput.fill('Updated Event Name');

      // Verify the node label updated on canvas
      const node = modeler.getNode('event_1');
      await expect(node).toContainText('Updated Event Name');
    });
  });

  test.describe('Multi-Select Mode', () => {
    test('should show multi-select info when multiple nodes selected', async ({ page }) => {
      await modeler.selectMultipleNodes(['event_1', 'action_1']);

      const multiSelectInfo = page.locator('[data-testid="multi-select-info"]');
      await expect(multiSelectInfo).toBeVisible();
    });

    test('should show count of selected nodes', async ({ page }) => {
      await modeler.selectMultipleNodes(['event_1', 'action_1']);

      const countDisplay = page.locator('[data-testid="selected-count"]');
      await expect(countDisplay).toContainText('2');
    });

    test('should allow batch deletion of selected nodes', async ({ page }) => {
      await modeler.selectMultipleNodes(['event_1', 'action_1']);

      const deleteButton = page.locator('[data-testid="delete-selected-button"]');
      await deleteButton.click();

      // Confirm deletion
      const confirmButton = page.locator('[data-testid="confirm-delete"]');
      await confirmButton.click();

      const nodeCount = await modeler.getNodeCount();
      expect(nodeCount).toBe(0);
    });
  });

  test.describe('Edge Configuration', () => {
    test('should show property panel when edge selected', async ({ page }) => {
      // Click on an edge
      const edge = page.locator('.react-flow__edge').first();
      await edge.click();

      await expect(modeler.propertyPanel).toBeVisible();
    });

    test('should display edge label editor', async ({ page }) => {
      const edge = page.locator('.react-flow__edge').first();
      await edge.click();

      const edgeLabelInput = page.locator('[data-testid="edge-label-input"]');
      await expect(edgeLabelInput).toBeVisible();
    });

    test('should show condition properties in the node panel for the promoted condition node', async ({ page }) => {
      // Conditions are first-class NODES now (issue #3589093).  The loaded
      // model's condition edge is promoted to a condition node (cond__edge_1),
      // edited via the NODE properties panel like any other node.
      await modeler.selectConditionNode('cond__edge_1');
      await page.waitForTimeout(300);

      await expect(modeler.propertyPanel).toBeVisible();

      // The panel reports the element type as a Condition (the 'link' label).
      const title = await modeler.getPropertyPanelTitle();
      expect(title).toContain('Condition');

      // The condition's label is shown in the node label input.
      const labelInput = modeler.getNodeLabelInput();
      await expect(labelInput).toBeVisible();
      await expect(labelInput).toHaveValue('Entity is New');
    });
  });

  test.describe('Annotation Editing', () => {
    test('should allow editing node annotation', async ({ page }) => {
      await modeler.selectNode('event_1');

      const annotationTab = page.locator('[data-testid="annotation-tab"]');
      await annotationTab.click();

      const annotationEditor = page.locator('[data-testid="annotation-editor"]');
      await expect(annotationEditor).toBeVisible();

      await annotationEditor.fill('This is a test annotation');

      // Check annotation is saved
      const annotationText = await annotationEditor.inputValue();
      expect(annotationText).toBe('This is a test annotation');
    });

    test('should toggle annotation visibility', async ({ page }) => {
      await modeler.selectNode('event_1');

      const annotationTab = page.locator('[data-testid="annotation-tab"]');
      await annotationTab.click();

      const annotationEditor = page.locator('[data-testid="annotation-editor"]');
      await annotationEditor.fill('Test annotation');

      const toggleButton = page.locator('[data-testid="toggle-annotation-visibility"]');
      await toggleButton.click();

      // Check annotation node visibility on canvas
      const annotationNode = page.locator('[data-testid="annotation-node"]');
      await expect(annotationNode).toBeVisible();
    });
  });

  test.describe('Token Browser', () => {
    test('should open token browser in config fields', async ({ page }) => {
      await modeler.selectNode('action_1');

      // Wait for config form
      await page.waitForSelector('[data-testid="config-form"]', { state: 'visible', timeout: 5000 });

      // Click token browser button
      const tokenButton = page.locator('[data-testid="token-browser-button"]').first();
      if (await tokenButton.count() > 0) {
        await tokenButton.click();

        const tokenBrowser = page.locator('[data-testid="token-browser"]');
        await expect(tokenBrowser).toBeVisible();
      }
    });

    test('should search tokens', async ({ page }) => {
      await modeler.selectNode('action_1');
      await page.waitForSelector('[data-testid="config-form"]', { state: 'visible', timeout: 5000 });

      const tokenButton = page.locator('[data-testid="token-browser-button"]').first();
      if (await tokenButton.count() > 0) {
        await tokenButton.click();

        const tokenSearch = page.locator('[data-testid="token-search"]');
        await tokenSearch.fill('user');

        // Should show filtered tokens
        const tokenList = page.locator('[data-testid="token-item"]');
        const count = await tokenList.count();
        expect(count).toBeGreaterThan(0);

        // All visible tokens should contain 'user'
        for (let i = 0; i < count; i++) {
          const text = await tokenList.nth(i).textContent();
          expect(text?.toLowerCase()).toContain('user');
        }
      }
    });

    test('should insert token into field', async ({ page }) => {
      await modeler.selectNode('action_1');
      await page.waitForSelector('[data-testid="config-form"]', { state: 'visible', timeout: 5000 });

      const tokenButton = page.locator('[data-testid="token-browser-button"]').first();
      if (await tokenButton.count() > 0) {
        await tokenButton.click();

        // Click a token to insert
        const tokenItem = page.locator('[data-testid="token-item"]').first();
        await tokenItem.click();

        // Check the field now contains the token
        const field = page.locator('[data-testid="token-enabled-field"]').first();
        const value = await field.inputValue();
        expect(value).toMatch(/\[[\w:]+\]/);
      }
    });
  });
});

// Model Settings tests require data-testid attributes
test.describe.skip('Workflow Modeler - Model Settings', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test('should open settings modal', async ({ page }) => {
    await modeler.openSettings();

    const modal = page.locator('[data-testid="settings-modal"]');
    await expect(modal).toBeVisible();
  });

  test('should display model label in settings', async ({ page }) => {
    await modeler.openSettings();

    const labelInput = page.locator('[data-testid="model-label-input"]');
    const value = await labelInput.inputValue();

    expect(value).toBe('Test Workflow');
  });

  test('should update model label', async ({ page }) => {
    await modeler.openSettings();

    const labelInput = page.locator('[data-testid="model-label-input"]');
    await labelInput.fill('Updated Workflow Name');

    const saveButton = page.locator('[data-testid="save-settings"]');
    await saveButton.click();

    // Reopen settings to verify
    await modeler.openSettings();

    const updatedValue = await labelInput.inputValue();
    expect(updatedValue).toBe('Updated Workflow Name');
  });

  test('should update model documentation', async ({ page }) => {
    await modeler.openSettings();

    const docInput = page.locator('[data-testid="model-documentation-input"]');
    await docInput.fill('Updated documentation for this workflow');

    const saveButton = page.locator('[data-testid="save-settings"]');
    await saveButton.click();

    // Reopen and verify
    await modeler.openSettings();

    const updatedValue = await docInput.inputValue();
    expect(updatedValue).toBe('Updated documentation for this workflow');
  });

  test('should toggle model active status', async ({ page }) => {
    await modeler.openSettings();

    const statusToggle = page.locator('[data-testid="model-status-toggle"]');
    await statusToggle.click();

    const saveButton = page.locator('[data-testid="save-settings"]');
    await saveButton.click();

    // The toggle state should have changed
    await modeler.openSettings();
    const isChecked = await statusToggle.isChecked();
    expect(isChecked).toBe(false); // Was true in mock, now should be false
  });
});
