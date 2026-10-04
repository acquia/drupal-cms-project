import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

/**
 * E2E tests for read-only mode.
 *
 * When `drupalSettings.modeler_api.readOnly` is set to `true`, no changes are
 * allowed. The modeler becomes a view-only interface where elements can be
 * selected and inspected but not modified.
 */

test.describe('Read-only mode', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page, { readOnly: true });
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('toolbar buttons', () => {
    test('should hide the Save button', async () => {
      await expect(modeler.saveButton).not.toBeVisible();
    });

    test('should hide the Copy button', async () => {
      await expect(modeler.copyButton).not.toBeVisible();
    });

    test('should hide the Paste button', async () => {
      await expect(modeler.pasteButton).not.toBeVisible();
    });

    test('should hide the Auto Layout button', async () => {
      await expect(modeler.autoLayoutButton).not.toBeVisible();
    });

    test('should still show Settings in the kebab menu', async () => {
      // Settings is inside the kebab menu — open it, then check the item
      await modeler.openKebabMenu();
      await expect(modeler.settingsButton).toBeVisible();
    });

    test('should still show the Search bar', async () => {
      await expect(modeler.searchButton).toBeVisible();
    });

    test('should hide the quick-add event button', async () => {
      const quickAddBtn = modeler.getQuickAddEventButton();
      await expect(quickAddBtn).not.toBeVisible();
    });
  });

  test.describe('context switch', () => {
    test('should hide context dropdown even when contexts exist', async ({ page }) => {
      await setupMocks(page, { readOnly: true, withContexts: true });
      modeler = new ModelerPage(page);
      await modeler.goto();

      const contextSelect = modeler.getContextSelect();
      await expect(contextSelect).not.toBeVisible();
    });
  });

  test.describe('canvas interaction', () => {
    test('should allow selecting a node', async () => {
      await modeler.selectNode('event_1');
      // Verify node appears selected by checking property panel shows content
      const componentType = modeler.propertyPanel.locator('.component-type');
      await expect(componentType).toBeVisible();
    });

    test('should not move a node when dragged (nodesDraggable is false)', async ({ page }) => {
      // In read-only mode, nodesDraggable is false.  Verify that the CSS
      // transform applied to the node by ReactFlow does not change after a
      // drag attempt.  We read the transform directly from the DOM element
      // instead of relying on bounding-box pixel comparisons (which are
      // affected by viewport panning).
      const actionNode = modeler.getNode('action_1');
      const getTransform = () =>
        actionNode.evaluate((el) => el.style.transform || el.getAttribute('transform') || '');

      const transformBefore = await getTransform();
      expect(transformBefore).toBeTruthy();

      // Attempt to drag the action node
      const box = await actionNode.boundingBox();
      expect(box).toBeTruthy();
      await page.mouse.move(box!.x + box!.width / 2, box!.y + box!.height / 2);
      await page.mouse.down();
      await page.mouse.move(
        box!.x + box!.width / 2 + 150,
        box!.y + box!.height / 2 + 150,
        { steps: 10 },
      );
      await page.mouse.up();
      await page.waitForTimeout(200);

      // The node's own transform should remain identical — it was not moved
      const transformAfter = await getTransform();
      expect(transformAfter).toBe(transformBefore);
    });

    test('should not delete a node when pressing Delete key', async () => {
      // Select the action node
      await modeler.selectNode('action_1');
      await modeler.page.waitForTimeout(200);

      // Count nodes before delete attempt
      const countBefore = await modeler.getNodeCount();

      // Press Delete key
      await modeler.page.keyboard.press('Delete');
      await modeler.page.waitForTimeout(200);

      // Count should remain the same
      const countAfter = await modeler.getNodeCount();
      expect(countAfter).toBe(countBefore);
    });

    test('should not show quick-add button on node hover', async ({ page }) => {
      const node = modeler.getNode('action_1');
      await node.hover();
      await page.waitForTimeout(300);

      const quickAddBtn = modeler.getQuickAddButton('action_1');
      await expect(quickAddBtn).not.toBeVisible();
    });

    test('should not show node delete button on hover', async ({ page }) => {
      const node = modeler.getNode('action_1');
      await node.hover();
      await page.waitForTimeout(300);

      const deleteBtn = node.locator('.node-footer-delete');
      await expect(deleteBtn).not.toBeVisible();
    });
  });

  test.describe('property panel', () => {
    test('should show property panel with disabled fields when node is selected', async () => {
      await modeler.selectNode('event_1');
      await modeler.page.waitForTimeout(300);

      // Label input should be disabled
      const labelInput = modeler.propertyPanel.locator('#modeler-component-label');
      await expect(labelInput).toBeDisabled();
    });

    test('should disable annotation field when node is selected', async () => {
      await modeler.selectNode('event_1');
      await modeler.page.waitForTimeout(300);

      // Annotation textarea should be disabled
      const annotation = modeler.propertyPanel.locator('#modeler-node-annotation');
      await expect(annotation).toBeDisabled();
    });

  });

  test.describe('metadata modal', () => {
    test('should make metadata fields read-only', async () => {
      await modeler.openSettings();

      expect(await modeler.isMetadataFieldEditable('label')).toBe(false);
      expect(await modeler.isMetadataFieldEditable('documentation')).toBe(false);

      // Version and storage live in the collapsed Advanced group
      await modeler.expandMetadataGroup('Advanced');
      expect(await modeler.isMetadataFieldEditable('version')).toBe(false);
      await expect(modeler.getMetadataField('storage')).toBeDisabled();
    });

    test('should hide Save button and show Close instead', async () => {
      await modeler.openSettings();

      // Save button should not exist
      const saveBtn = modeler.getMetadataSaveButton();
      await expect(saveBtn).toHaveCount(0);

      // The remaining button should say "Close" (not "Cancel")
      const closeBtn = modeler.getMetadataModal().locator('button.btn-secondary');
      await expect(closeBtn).toHaveText('Close');
    });
  });

  test.describe('keyboard shortcuts', () => {
    test('should not copy with Ctrl+C', async ({ page }) => {
      // Select a node first
      await modeler.selectNode('action_1');
      await page.waitForTimeout(200);

      // Copy with Ctrl+C
      await page.keyboard.press('Control+c');
      await page.waitForTimeout(200);

      // Paste with Ctrl+V should not create new nodes
      const countBefore = await modeler.getNodeCount();
      await page.keyboard.press('Control+v');
      await page.waitForTimeout(200);
      const countAfter = await modeler.getNodeCount();
      expect(countAfter).toBe(countBefore);
    });
  });
});

// ---------------------------------------------------------------------------
// Verify non-read-only mode still works normally (sanity check)
// ---------------------------------------------------------------------------
test.describe('Non-read-only mode (sanity check)', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test('should show the Save button', async () => {
    await expect(modeler.saveButton).toBeVisible();
  });

  test('should show the quick-add event button', async () => {
    const quickAddBtn = modeler.getQuickAddEventButton();
    await expect(quickAddBtn).toBeVisible();
  });

  test('should allow deleting a node', async ({ page }) => {
    // Select the action node
    await modeler.selectNode('action_1');
    await page.waitForTimeout(200);

    const countBefore = await modeler.getNodeCount();

    // Press Delete key
    await page.keyboard.press('Delete');
    await page.waitForTimeout(500);

    // Count should decrease
    const countAfter = await modeler.getNodeCount();
    expect(countAfter).toBeLessThan(countBefore);
  });
});
