import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

test.describe('Workflow Modeler - Undo/Redo', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
  });

  test.describe('Keyboard Shortcuts', () => {
    test('should undo with Ctrl+Z', async ({ page }) => {
      await modeler.goto();

      const initialCount = await modeler.getNodeCount();

      // Add a node via quick-add on existing node
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Set Message');
      await page.waitForTimeout(300);

      // Verify node was added
      expect(await modeler.getNodeCount()).toBe(initialCount + 1);

      // Undo the action
      await page.keyboard.press('Control+z');
      await page.waitForTimeout(300);

      // Verify node was removed (back to initial count)
      expect(await modeler.getNodeCount()).toBe(initialCount);
    });

    test('should redo with Ctrl+Shift+Z', async ({ page }) => {
      await modeler.goto();

      const initialCount = await modeler.getNodeCount();

      // Add a node via quick-add
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Set Message');
      await page.waitForTimeout(300);

      // Verify node was added
      expect(await modeler.getNodeCount()).toBe(initialCount + 1);

      // Undo
      await page.keyboard.press('Control+z');
      await page.waitForTimeout(300);

      // Verify node was removed
      expect(await modeler.getNodeCount()).toBe(initialCount);

      // Redo
      await page.keyboard.press('Control+Shift+z');
      await page.waitForTimeout(300);

      // Verify node was restored
      expect(await modeler.getNodeCount()).toBe(initialCount + 1);
    });

    test('should redo with Ctrl+Y', async ({ page }) => {
      await modeler.goto();

      const initialCount = await modeler.getNodeCount();

      // Delete an existing node
      await modeler.selectNode('action_1');
      await modeler.deleteSelected();
      await page.waitForTimeout(300);

      // Verify node was deleted
      expect(await modeler.getNodeCount()).toBe(initialCount - 1);

      // Undo the deletion
      await page.keyboard.press('Control+z');
      await page.waitForTimeout(300);

      // Verify node was restored
      expect(await modeler.getNodeCount()).toBe(initialCount);

      // Redo with Ctrl+Y (re-apply deletion)
      await page.keyboard.press('Control+y');
      await page.waitForTimeout(300);

      // Verify node was deleted again
      expect(await modeler.getNodeCount()).toBe(initialCount - 1);
    });
  });

  test.describe('Toolbar Buttons', () => {
    test('should have undo and redo buttons in toolbar', async ({ page }) => {
      await modeler.goto();

      // Check undo button exists
      await expect(modeler.undoButton).toBeVisible();

      // Check redo button exists
      await expect(modeler.redoButton).toBeVisible();
    });

    test('should disable undo button when no history', async ({ page }) => {
      await modeler.goto();

      // Undo button should be disabled initially
      await expect(modeler.undoButton).toBeDisabled();
    });

    test('should disable redo button when no future history', async ({ page }) => {
      await modeler.goto();

      // Redo button should be disabled initially
      await expect(modeler.redoButton).toBeDisabled();
    });

    test('should enable undo button after making changes', async ({ page }) => {
      await modeler.goto();

      // Delete an existing node to create a history entry
      await modeler.selectNode('action_1');
      await modeler.deleteSelected();
      await page.waitForTimeout(300);

      // Undo button should now be enabled
      await expect(modeler.undoButton).toBeEnabled();
    });

    test('should undo via toolbar button', async ({ page }) => {
      await modeler.goto();

      const initialCount = await modeler.getNodeCount();

      // Delete an existing node
      await modeler.selectNode('action_1');
      await modeler.deleteSelected();
      await page.waitForTimeout(300);

      // Verify node was removed
      expect(await modeler.getNodeCount()).toBe(initialCount - 1);

      // Click undo button
      await modeler.undoButton.click();
      await page.waitForTimeout(300);

      // Verify node was restored
      expect(await modeler.getNodeCount()).toBe(initialCount);
    });

    test('should redo via toolbar button', async ({ page }) => {
      await modeler.goto();

      const initialCount = await modeler.getNodeCount();

      // Delete an existing node
      await modeler.selectNode('action_1');
      await modeler.deleteSelected();
      await page.waitForTimeout(300);

      // Undo
      await modeler.undoButton.click();
      await page.waitForTimeout(300);

      // Verify undo worked
      expect(await modeler.getNodeCount()).toBe(initialCount);

      // Redo button should now be enabled
      await expect(modeler.redoButton).toBeEnabled();

      // Click redo button
      await modeler.redoButton.click();
      await page.waitForTimeout(300);

      // Verify node was removed again
      expect(await modeler.getNodeCount()).toBe(initialCount - 1);
    });
  });

  test.describe('Multiple Undo/Redo', () => {
    test('should handle multiple undo operations', async ({ page }) => {
      await modeler.goto();

      const initialCount = await modeler.getNodeCount();
      const initialEdgeCount = await modeler.getEdgeCount();

      // Operation 1: Add a node via quick-add from event_1
      const eventNode = modeler.getNode('event_1');
      await eventNode.hover();
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Set Message');
      await page.waitForTimeout(500);
      expect(await modeler.getNodeCount()).toBe(initialCount + 1);

      // Undo the addition
      await modeler.undoButton.click();
      await page.waitForTimeout(500);
      expect(await modeler.getNodeCount()).toBe(initialCount);

      // Now add another node (clears redo, creates new history).
      // After undo, the ReactFlow viewport may have shifted, leaving the node
      // outside the visible area. Fit the view to bring all nodes back.
      await modeler.fitView();
      await eventNode.hover({ force: true });
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Save Entity');
      await page.waitForTimeout(500);
      expect(await modeler.getNodeCount()).toBe(initialCount + 1);

      // Undo this second addition
      await modeler.undoButton.click();
      await page.waitForTimeout(500);

      // Should be back to initial
      expect(await modeler.getNodeCount()).toBe(initialCount);
      expect(await modeler.getEdgeCount()).toBe(initialEdgeCount);
    });

    test('should handle multiple redo operations', async ({ page }) => {
      await modeler.goto();

      const initialCount = await modeler.getNodeCount();

      // Add a node via quick-add
      const eventNode = modeler.getNode('event_1');
      await eventNode.hover();
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Set Message');
      await page.waitForTimeout(500);

      // Verify node was added
      expect(await modeler.getNodeCount()).toBe(initialCount + 1);

      // Undo
      await modeler.undoButton.click();
      await page.waitForTimeout(500);
      expect(await modeler.getNodeCount()).toBe(initialCount);

      // Redo — node should come back
      await modeler.redoButton.click();
      await page.waitForTimeout(500);
      expect(await modeler.getNodeCount()).toBe(initialCount + 1);

      // Undo again
      await modeler.undoButton.click();
      await page.waitForTimeout(500);
      expect(await modeler.getNodeCount()).toBe(initialCount);

      // Redo again — node should come back again
      await modeler.redoButton.click();
      await page.waitForTimeout(500);
      expect(await modeler.getNodeCount()).toBe(initialCount + 1);
    });
  });

  test.describe('Undo/Redo with Connections', () => {
    // Skipped: ReactFlow's drag-to-connect via Playwright's dragTo() is
    // unreliable — the drag completes but ReactFlow does not always register
    // the connection. This is a known limitation of programmatic drag in
    // headless browsers with ReactFlow's handle detection.
    test.skip('should undo connection creation', async ({ page }) => {
      await modeler.goto();

      const initialEdgeCount = await modeler.getEdgeCount();

      // Connect action_1 to action_2 (these are NOT already connected)
      await modeler.connectNodes('action_1', 'action_2');
      await page.waitForTimeout(300);

      // Verify edge was added
      expect(await modeler.getEdgeCount()).toBe(initialEdgeCount + 1);

      // Undo
      await page.keyboard.press('Control+z');
      await page.waitForTimeout(300);

      // Verify edge was removed
      expect(await modeler.getEdgeCount()).toBe(initialEdgeCount);
    });
  });

  test.describe('Read-only Mode', () => {
    test('should hide undo/redo buttons in read-only mode', async ({ page }) => {
      // Set up mocks with read-only flag
      await setupMocks(page, { readOnly: true });
      modeler = new ModelerPage(page);

      await modeler.goto();

      // In read-only mode, undo/redo buttons should not be visible
      await expect(modeler.undoButton).not.toBeVisible();
      await expect(modeler.redoButton).not.toBeVisible();
    });
  });
});
