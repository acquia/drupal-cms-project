/**
 * E2E tests for condition-first authoring workflow.
 *
 * Conditions are first-class NODES now (issue #3589093).  Selecting a
 * condition from a node's quick-add popup creates a condition NODE followed by
 * a placeholder action node: `source -> conditionNode -> placeholder`.  The
 * condition node is auto-selected so its configuration panel opens
 * immediately, and the placeholder blocks saving until replaced.
 */

import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

test.describe('Condition-First Authoring', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('Type Filter Panel', () => {
    test('should show filter toggle in quick-add popup', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');

      const filterToggle = page.locator('.quick-add-filter-toggle');
      await expect(filterToggle).toBeVisible();
    });

    test('should expand filter options on toggle click', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');

      // Filter options should not be visible initially
      const filterOptions = page.locator('.quick-add-filter-options');
      await expect(filterOptions).not.toBeVisible();

      // Click the toggle to expand
      await page.locator('.quick-add-filter-toggle').click();
      await expect(filterOptions).toBeVisible();

      // Should have filter option buttons
      const options = page.locator('.quick-add-filter-option');
      const count = await options.count();
      expect(count).toBeGreaterThanOrEqual(4); // All, Actions, Conditions, Gateways
    });

    test('should filter component list by type when a filter is selected', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');

      // Expand filter panel
      await page.locator('.quick-add-filter-toggle').click();

      // Count components before filtering
      const allItems = page.locator('.quick-add-component-item');
      const totalCount = await allItems.count();
      expect(totalCount).toBeGreaterThan(0);

      // Click a specific filter (the conditions / "Links" filter)
      const conditionFilter = page.locator('.quick-add-filter-option').filter({ hasText: /Links|Conditions/ });
      if (await conditionFilter.count() > 0) {
        await conditionFilter.first().click();
        await page.waitForTimeout(200);

        // Should show fewer components (only conditions)
        const filteredCount = await allItems.count();
        expect(filteredCount).toBeLessThan(totalCount);
        expect(filteredCount).toBeGreaterThan(0);

        // All visible items should have the condition category indicator
        const conditionIndicators = page.locator('.quick-add-component-item .component-category-indicator[data-type="link"]');
        expect(await conditionIndicators.count()).toBe(filteredCount);
      }
    });
  });

  test.describe('Creating Condition + Placeholder Nodes', () => {
    test('should create a condition node and a placeholder node when selecting a condition', async ({ page }) => {
      const initialNodeCount = await modeler.getNodeCount();
      const initialEdgeCount = await modeler.getEdgeCount();
      const initialConditionCount = await modeler.getConditionNodeCount();

      // Open quick-add popup on event node
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');

      // Expand filter and select conditions
      await page.locator('.quick-add-filter-toggle').click();
      const conditionFilter = page.locator('.quick-add-filter-option').filter({ hasText: /Links|Conditions/ });
      await conditionFilter.first().click();
      await page.waitForTimeout(200);

      // Select the first condition
      const firstCondition = page.locator('.quick-add-component-item').first();
      await firstCondition.click();
      await page.waitForTimeout(500);

      // Condition-first authoring creates a condition NODE + a placeholder
      // action node, wired source -> condition -> placeholder.  So TWO new
      // nodes and TWO new edges are added (issue #3589093).
      const finalNodeCount = await modeler.getNodeCount();
      const finalEdgeCount = await modeler.getEdgeCount();
      expect(finalNodeCount).toBe(initialNodeCount + 2);
      expect(finalEdgeCount).toBe(initialEdgeCount + 2);

      // One of the new nodes is a condition node card.
      expect(await modeler.getConditionNodeCount()).toBe(initialConditionCount + 1);

      // The other new node is a placeholder awaiting an action.
      const placeholderNode = page.locator('.placeholder-node');
      await expect(placeholderNode).toBeVisible();
    });

    test('placeholder node should have distinct visual styling', async ({ page }) => {
      // Create a placeholder via condition-first
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');

      await page.locator('.quick-add-filter-toggle').click();
      const conditionFilter = page.locator('.quick-add-filter-option').filter({ hasText: /Links|Conditions/ });
      await conditionFilter.first().click();
      await page.waitForTimeout(200);
      await page.locator('.quick-add-component-item').first().click();
      await page.waitForTimeout(500);

      // Verify the placeholder node has dashed border styling
      const placeholderNode = page.locator('.placeholder-node');
      await expect(placeholderNode).toBeVisible();
      await expect(placeholderNode).toHaveCSS('border-style', 'dashed');
    });

    test('placeholder node should show "Select action..." button', async ({ page }) => {
      // Create a placeholder via condition-first
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');

      await page.locator('.quick-add-filter-toggle').click();
      const conditionFilter = page.locator('.quick-add-filter-option').filter({ hasText: /Links|Conditions/ });
      await conditionFilter.first().click();
      await page.waitForTimeout(200);
      await page.locator('.quick-add-component-item').first().click();
      await page.waitForTimeout(500);

      // Deselect the edge so we can see the placeholder node
      await modeler.canvas.click({ position: { x: 50, y: 50 } });
      await page.waitForTimeout(200);

      // The placeholder should have a "Select action..." button
      const selectButton = page.locator('.placeholder-select-button');
      await expect(selectButton).toBeVisible();
    });
  });

  test.describe('Replacing Placeholder Nodes', () => {
    test('should replace placeholder with action when component is selected', async ({ page }) => {
      // Create a placeholder via condition-first
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');

      await page.locator('.quick-add-filter-toggle').click();
      const conditionFilter = page.locator('.quick-add-filter-option').filter({ hasText: /Links|Conditions/ });
      await conditionFilter.first().click();
      await page.waitForTimeout(200);
      await page.locator('.quick-add-component-item').first().click();
      await page.waitForTimeout(500);

      // Deselect everything
      await modeler.canvas.click({ position: { x: 50, y: 50 } });
      await page.waitForTimeout(200);

      // Click the "Select action..." button on the placeholder
      const selectButton = page.locator('.placeholder-select-button');
      await selectButton.click();
      await page.waitForTimeout(300);

      // A popup should appear with actions (not conditions)
      const popup = page.locator('.quick-add-popup');
      await expect(popup).toBeVisible();

      // Select an action from the popup
      const actionItem = page.locator('.quick-add-component-item').first();
      await actionItem.click();
      await page.waitForTimeout(500);

      // The placeholder should be gone, replaced by a real node
      const placeholderNode = page.locator('.placeholder-node');
      await expect(placeholderNode).not.toBeVisible();
    });
  });

  test.describe('Save Validation', () => {
    test('should show error when trying to save with placeholder nodes', async ({ page }) => {
      // Create a placeholder via condition-first
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');

      await page.locator('.quick-add-filter-toggle').click();
      const conditionFilter = page.locator('.quick-add-filter-option').filter({ hasText: /Links|Conditions/ });
      await conditionFilter.first().click();
      await page.waitForTimeout(200);
      await page.locator('.quick-add-component-item').first().click();
      await page.waitForTimeout(500);

      // Deselect everything
      await modeler.canvas.click({ position: { x: 50, y: 50 } });
      await page.waitForTimeout(200);

      // Try to save — should be blocked
      const saveButton = page.locator('button[title="Save"]');
      if (await saveButton.isVisible()) {
        await saveButton.click();
        await page.waitForTimeout(500);

        // Should show an error message about placeholder nodes
        const errorMessage = page.locator('.messages--error, [role="alert"]');
        await expect(errorMessage.first()).toBeVisible({ timeout: 3000 });
      }
    });
  });
});
