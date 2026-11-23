import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

/**
 * E2E tests for label editing functionality.
 * These tests verify that labels can be edited in the property panel
 * and that changes are reflected on the canvas.
 */
test.describe('Workflow Modeler - Label Editing', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('Node Label Editing', () => {
    test('should display node label in property panel when node is selected', async ({ page }) => {
      // Select the event node
      await modeler.selectNode('event_1');
      
      // Wait for property panel to show the node
      await expect(modeler.propertyPanel).toBeVisible();
      
      // Check that the label input exists and has the correct value
      const labelInput = page.locator('#modeler-component-label');
      await expect(labelInput).toBeVisible();
      await expect(labelInput).toHaveValue('On Entity Insert');
    });

    test('should update node label on canvas when edited in property panel', async ({ page }) => {
      // Select the event node
      await modeler.selectNode('event_1');
      
      // Find the label input
      const labelInput = page.locator('#modeler-component-label');
      await expect(labelInput).toBeVisible();
      
      // Clear and type new label
      await labelInput.clear();
      await labelInput.fill('My Updated Event');
      
      // Blur to trigger save
      await labelInput.blur();
      
      // Wait for debounce and update
      await page.waitForTimeout(400);
      
      // Verify the node label updated on canvas
      const node = modeler.getNode('event_1');
      await expect(node).toContainText('My Updated Event');
    });

    test('should persist node label after selecting different node and returning', async ({ page }) => {
      // Select the event node
      await modeler.selectNode('event_1');
      
      // Edit the label
      const labelInput = page.locator('#modeler-component-label');
      await labelInput.clear();
      await labelInput.fill('Renamed Event');
      await labelInput.blur();
      await page.waitForTimeout(400);
      
      // Select a different node
      await modeler.selectNode('action_1');
      await page.waitForTimeout(200);
      
      // Select the original node again
      await modeler.selectNode('event_1');
      await page.waitForTimeout(200);
      
      // Verify the label persisted
      await expect(labelInput).toHaveValue('Renamed Event');
      
      // Verify it's still on the canvas
      const node = modeler.getNode('event_1');
      await expect(node).toContainText('Renamed Event');
    });

    test('should mark model as having unsaved changes when label is edited', async ({ page }) => {
      // Select the event node
      await modeler.selectNode('event_1');
      
      // Edit the label
      const labelInput = page.locator('#modeler-component-label');
      await labelInput.clear();
      await labelInput.fill('Changed Label');
      await labelInput.blur();
      await page.waitForTimeout(400);
      
      // Check for unsaved changes indicator (the save button or title change)
      // The toolbar should show unsaved state
      const saveButton = page.locator('button[title="Save Model"]');
      // The button should be enabled when there are unsaved changes
      await expect(saveButton).toBeEnabled();
    });
  });

  test.describe('Condition Label Editing', () => {
    // Conditions are first-class NODES now (issue #3589093).  Inserting a
    // condition on an edge creates a condition NODE (the inserted node is
    // auto-selected), and the condition's label is edited through the NODE
    // properties panel (`#modeler-component-label`) — exactly like any other
    // node.  There is no `#modeler-condition-label` edge field anymore.

    /** Insert a condition node on edge_2 and return its selected label input. */
    async function insertConditionOnEdge2(page: import('@playwright/test').Page) {
      await modeler.openQuickAddConditionPopup('edge_2');
      await modeler.selectQuickAddComponent('Entity is New');
      await page.waitForTimeout(400);
    }

    test('should display the node label input when the inserted condition node is selected', async ({ page }) => {
      await insertConditionOnEdge2(page);

      // The newly inserted condition node is auto-selected, so the node
      // properties panel shows its label input prefilled with the condition.
      const labelInput = modeler.getNodeLabelInput();
      await expect(labelInput).toBeVisible();
      await expect(labelInput).toHaveValue('Entity is New');

      // Two condition node cards are now rendered: the promoted condition from
      // edge_1 (loaded on startup) plus the one we just inserted on edge_2.
      expect(await modeler.getConditionNodeCount()).toBe(2);
    });

    test('should update condition node label on canvas when edited', async ({ page }) => {
      await insertConditionOnEdge2(page);

      const labelInput = modeler.getNodeLabelInput();
      await expect(labelInput).toBeVisible();

      // Edit the condition node's label via the node panel.
      await labelInput.clear();
      await labelInput.fill('Is New Entity?');
      await labelInput.blur();
      await page.waitForTimeout(400);

      // The condition node card on the canvas reflects the new label.  Target
      // the selected (newly inserted) condition node specifically, since the
      // promoted condition from edge_1 shares the same "Entity is New" label.
      const conditionCard = modeler.getSelectedConditionNode();
      await expect(conditionCard).toContainText('Is New Entity?');
    });

    test('should persist condition node label after deselecting and reselecting the node', async ({ page }) => {
      await insertConditionOnEdge2(page);

      const labelInput = modeler.getNodeLabelInput();
      await expect(labelInput).toBeVisible();

      await labelInput.clear();
      await labelInput.fill('Custom Condition Label');
      await labelInput.blur();
      await page.waitForTimeout(400);

      // Deselect by clicking empty canvas (top-right, away from the minimap).
      await modeler.canvas.click({ position: { x: 600, y: 50 } });
      await page.waitForTimeout(300);

      // Reselect the condition node card and verify the label persisted.
      // Target the renamed card by its text — the promoted condition from
      // edge_1 still shows "Entity is New", so we must pick the one we edited.
      await modeler.getConditionNodes().filter({ hasText: 'Custom Condition Label' }).first().click();
      await page.waitForTimeout(300);
      const labelInputAfter = modeler.getNodeLabelInput();
      await expect(labelInputAfter).toHaveValue('Custom Condition Label');
    });
  });

  test.describe('Action Node Label Editing', () => {
    test('should update action node label', async ({ page }) => {
      // Select the action node
      await modeler.selectNode('action_1');
      
      // Find the label input
      const labelInput = page.locator('#modeler-component-label');
      await expect(labelInput).toBeVisible();
      await expect(labelInput).toHaveValue('Save Entity');
      
      // Edit the label
      await labelInput.clear();
      await labelInput.fill('Custom Save Action');
      await labelInput.blur();
      await page.waitForTimeout(400);
      
      // Verify the node label updated on canvas
      const node = modeler.getNode('action_1');
      await expect(node).toContainText('Custom Save Action');
    });
  });

  test.describe('Label Input Behavior', () => {
    test('should update label on blur without waiting for debounce', async ({ page }) => {
      // Select a node
      await modeler.selectNode('event_1');
      
      const labelInput = page.locator('#modeler-component-label');
      
      // Type a new label
      await labelInput.clear();
      await labelInput.fill('Immediate Update Test');
      
      // Immediately blur (don't wait for debounce)
      await labelInput.blur();
      
      // Small wait for the blur handler
      await page.waitForTimeout(100);
      
      // The label should already be updated
      const node = modeler.getNode('event_1');
      await expect(node).toContainText('Immediate Update Test');
    });

    test('should handle rapid typing with debounce', async ({ page }) => {
      // Select a node
      await modeler.selectNode('event_1');
      
      const labelInput = page.locator('#modeler-component-label');
      
      // Type character by character rapidly
      await labelInput.clear();
      await labelInput.pressSequentially('Rapid', { delay: 50 });
      
      // Wait for debounce to complete
      await page.waitForTimeout(400);
      
      // The final value should be on the canvas
      const node = modeler.getNode('event_1');
      await expect(node).toContainText('Rapid');
    });

  });
});
