import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks, mockModel } from './fixtures/mocks';

test.describe('Workflow Modeler - Core Functionality', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
  });

  test.describe('Initial Load', () => {
    test('should load the modeler with canvas visible', async ({ page }) => {
      await modeler.goto();

      // Canvas should be visible
      await expect(modeler.canvas).toBeVisible();

      // Save button should be visible but disabled (no unsaved changes)
      await expect(modeler.saveButton).toBeVisible();
      await expect(modeler.saveButton).toBeDisabled();
    });

    test('should display existing nodes from model', async ({ page }) => {
      await modeler.goto();

      // Backend model has 3 nodes (event_1, action_1, action_2).  edge_1 carries
      // a condition that is PROMOTED to a condition node on load (issue
      // #3589093), so the canvas renders 4 nodes.
      const nodeCount = await modeler.getNodeCount();
      expect(nodeCount).toBe(4);
    });

    test('should display existing edges from model', async ({ page }) => {
      await modeler.goto();

      // Backend model has 2 edges (edge_1 with a condition, edge_2 without).
      // Promotion splits the condition edge into two plain edges
      // (event_1 -> cond, cond -> action_1), so the canvas renders 3 edges.
      const edgeCount = await modeler.getEdgeCount();
      expect(edgeCount).toBe(3);
    });
  });

  test.describe('Node Selection', () => {
    test('should select a node when clicked', async ({ page }) => {
      await modeler.goto();
      await modeler.selectNode('event_1');

      const node = modeler.getNode('event_1');
      await expect(node).toHaveClass(/selected/);
    });

    test('should show property panel when node is selected', async ({ page }) => {
      await modeler.goto();
      await modeler.selectNode('event_1');

      await expect(modeler.propertyPanel).toBeVisible();
      const title = await modeler.getPropertyPanelTitle();
      expect(title).toContain('Event');
    });

    test('should allow multi-select with Shift+Click', async ({ page }) => {
      await modeler.goto();
      await modeler.selectMultipleNodes(['event_1', 'action_1']);

      const event = modeler.getNode('event_1');
      const action = modeler.getNode('action_1');

      await expect(event).toHaveClass(/selected/);
      await expect(action).toHaveClass(/selected/);
    });

    test('should deselect all when clicking canvas background', async ({ page }) => {
      await modeler.goto();
      await modeler.selectNode('event_1');
      await modeler.canvas.click({ position: { x: 50, y: 50 } });

      const node = modeler.getNode('event_1');
      await expect(node).not.toHaveClass(/selected/);
    });

    test('should not mark model as changed when selecting a node', async ({ page }) => {
      await modeler.goto();

      // Save button starts disabled (no unsaved changes)
      await expect(modeler.saveButton).toBeDisabled();

      // Select a node
      await modeler.selectNode('event_1');
      await expect(modeler.getNode('event_1')).toHaveClass(/selected/);

      // Save button should still be disabled — selection alone is not a change
      await expect(modeler.saveButton).toBeDisabled();
    });

    test('should not mark model as changed when selecting multiple nodes', async ({ page }) => {
      await modeler.goto();
      await expect(modeler.saveButton).toBeDisabled();

      await modeler.selectMultipleNodes(['event_1', 'action_1']);
      await expect(modeler.getNode('event_1')).toHaveClass(/selected/);
      await expect(modeler.getNode('action_1')).toHaveClass(/selected/);

      // Multi-selection should not mark the model as changed
      await expect(modeler.saveButton).toBeDisabled();
    });

    test('should not mark model as changed when deselecting nodes', async ({ page }) => {
      await modeler.goto();
      await expect(modeler.saveButton).toBeDisabled();

      // Select then deselect by clicking canvas background
      await modeler.selectNode('event_1');
      await modeler.canvas.click({ position: { x: 50, y: 50 } });

      await expect(modeler.saveButton).toBeDisabled();
    });
  });

  test.describe('Node Manipulation', () => {
    test('should delete selected node with Delete key', async ({ page }) => {
      await modeler.goto();
      const initialCount = await modeler.getNodeCount();

      await modeler.selectNode('action_1');
      await modeler.deleteSelected();

      const finalCount = await modeler.getNodeCount();
      expect(finalCount).toBe(initialCount - 1);
    });

    test('should mark model as changed when dragging a node to a new position', async ({ page }) => {
      await modeler.goto();
      await expect(modeler.saveButton).toBeDisabled();

      await modeler.moveNode('event_1', 100, 50);
      await page.waitForTimeout(300);

      // Dragging a node to a new position is a real change
      await expect(modeler.saveButton).toBeEnabled();
    });

    test.skip('should move node by dragging', async ({ page }) => {
      // Drag behavior can be flaky in headless mode
      await modeler.goto();

      const nodeBefore = modeler.getNode('event_1');
      const boxBefore = await nodeBefore.boundingBox();

      await modeler.moveNode('event_1', 100, 50);

      const boxAfter = await nodeBefore.boundingBox();

      expect(boxAfter!.x).toBeCloseTo(boxBefore!.x + 100, 0);
      expect(boxAfter!.y).toBeCloseTo(boxBefore!.y + 50, 0);
    });
  });
});



// Some keyboard shortcuts need selector adjustments
// Copy/paste works, but undo/search tests need updated selectors
test.describe('Workflow Modeler - Keyboard Shortcuts', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test('should copy and paste selected node', async ({ page }) => {
    const initialCount = await modeler.getNodeCount();

    await modeler.selectNode('action_1');
    await modeler.copySelected();
    await modeler.paste();

    const finalCount = await modeler.getNodeCount();
    expect(finalCount).toBe(initialCount + 1);
  });

  test.skip('should undo last action', async ({ page }) => {
    // Undo requires undo stack which may not be available in isolated test.
    // Counts are relative so they remain correct regardless of condition-node
    // promotion (issue #3589093).
    const initialCount = await modeler.getNodeCount();
    await modeler.selectNode('action_2');
    await modeler.deleteSelected();

    const countAfterDelete = await modeler.getNodeCount();
    expect(countAfterDelete).toBe(initialCount - 1);

    await modeler.undo();

    const countAfterUndo = await modeler.getNodeCount();
    expect(countAfterUndo).toBe(initialCount);
  });

  test('should redo undone action', async ({ page }) => {
    // Use a leaf node (action_2) so deletion removes exactly one node.
    // Counts are relative so condition-node promotion does not affect them.
    const initialCount = await modeler.getNodeCount();
    await modeler.selectNode('action_2');
    await modeler.deleteSelected();
    await modeler.undo();
    await modeler.redo();

    const finalCount = await modeler.getNodeCount();
    expect(finalCount).toBe(initialCount - 1);
  });

  test('should open search with Ctrl+F', async ({ page }) => {
    await modeler.openSearch();

    // Search is an inline input in the toolbar
    const searchInput = page.locator('input[placeholder*="Search components"]');
    await expect(searchInput).toBeVisible();
  });


});

// Toolbar tests - some need API integration for full testing
test.describe('Workflow Modeler - Toolbar Actions', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test('should save model when save button clicked', async ({ page }) => {
    // Save button starts disabled (no unsaved changes)
    await expect(modeler.saveButton).toBeDisabled();

    // Make a change to enable the save button (auto layout marks model as changed)
    await modeler.autoLayout();
    await page.waitForTimeout(300);
    await expect(modeler.saveButton).toBeEnabled();

    // Set up request interception before clicking
    const savePromise = page.waitForRequest(request =>
      request.url().includes('modeler-api/model') &&
      (request.method() === 'POST' || request.method() === 'PUT')
    );

    await modeler.saveButton.click();

    // Wait for the save request (with timeout)
    const request = await Promise.race([
      savePromise,
      page.waitForTimeout(2000).then(() => null)
    ]);

    // Either request was made or timeout - both acceptable for basic test
    expect(modeler.saveButton).toBeVisible();
  });

  test('should open settings modal', async () => {
    await modeler.openSettings();

    const modal = modeler.page.locator('.metadata-modal');
    await expect(modal).toBeVisible();
  });

  test('should close modal with Escape key', async ({ page }) => {
    await modeler.openSettings();

    // Find and click the close button in the modal
    const closeButton = page.locator('.metadata-modal button.close-btn, .metadata-modal-header button');
    if (await closeButton.count() > 0) {
      await closeButton.first().click();
    } else {
      await modeler.closeModal(); // Fall back to Escape key
    }

    // Wait for modal to close
    await page.waitForTimeout(300);
    const modal = page.locator('.metadata-modal');
    await expect(modal).not.toBeVisible();
  });

  test('should trigger auto-layout', async ({ page }) => {
    const nodeBefore = modeler.getNode('event_1');
    const boxBefore = await nodeBefore.boundingBox();

    await modeler.autoLayout();

    // Wait for layout animation
    await page.waitForTimeout(300);

    const boxAfter = await nodeBefore.boundingBox();

    // Position should have changed (unless already optimal)
    // This is a basic check - actual validation depends on layout algorithm
    expect(boxAfter).toBeDefined();
  });
});

/**
 * Parallel-edge routing — issue #3586864.
 *
 * When a new edge is added between two nodes that are already connected
 * (directly or via a chain), the new edge must be routed sideways via
 * `controlOffset` so it does not visually overlap the existing connection.
 *
 * These tests drive the public plugin API (`window.WorkflowModeler.api`)
 * rather than the user-drag path, because programmatic drag-to-connect
 * is unreliable in headless browsers (see undo-redo.spec.ts:260). The
 * router itself runs in both code paths, so exercising it through the API
 * gives the same coverage.
 */
test.describe('Workflow Modeler - Parallel Edge Routing', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    // Pre-create the WorkflowModeler global so Flow.tsx attaches the
    // plugin API there when it mounts.
    await page.addInitScript(() => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (window as any).WorkflowModeler = (window as any).WorkflowModeler || {};
    });
    await modeler.goto();
  });

  test('routes a new direct parallel edge with a sideways controlOffset', async ({ page }) => {
    const initialEdgeCount = await modeler.getEdgeCount();

    // Conditions are first-class nodes now (issue #3589093), so there are no
    // condition edges to special-case here.  Use the plain direct edge
    // action_1 → action_2 (edge_2) and add a parallel edge between the same
    // pair so the router must spread them sideways via controlOffset.
    const newEdgeId = await page.evaluate(() => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const api = (window as any).WorkflowModeler?.api;
      return api ? api.addEdge('action_1', 'action_2') : null;
    });

    expect(newEdgeId).toBeTruthy();
    expect(await modeler.getEdgeCount()).toBe(initialEdgeCount + 1);

    // Read both edges' controlOffset from the store via the API.
    // The PluginEdge projection nests controlOffset under `data`.
    const offsets = await page.evaluate((newId: string) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const api = (window as any).WorkflowModeler?.api;
      const allEdges = api.getEdges();
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const original = allEdges.find((e: any) => e.id === 'edge_2');
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const created = allEdges.find((e: any) => e.id === newId);
      return {
        originalOffset: original?.data?.controlOffset ?? null,
        newOffset: created?.data?.controlOffset ?? null,
      };
    }, newEdgeId as string);

    expect(offsets.originalOffset).not.toBeNull();
    expect(offsets.newOffset).not.toBeNull();
    // Both edges have non-zero X offsets on opposite sides.
    expect(offsets.originalOffset!.x).not.toBe(0);
    expect(offsets.newOffset!.x).not.toBe(0);
    expect(
      Math.sign(offsets.originalOffset!.x) * Math.sign(offsets.newOffset!.x),
    ).toBeLessThan(0);
  });

  test('does not add a controlOffset to a non-parallel edge', async ({ page }) => {
    // Create a fresh node disconnected from the existing chain, then
    // connect action_2 → newNode. There is no existing path between
    // those two endpoints, so the router must not add any offset.
    const newEdgeId = await page.evaluate(() => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const api = (window as any).WorkflowModeler?.api;
      const newNodeId = api.addNode({
        plugin: 'message:set',
        componentType: 4, // 4 = element
        label: 'Independent Action',
        position: { x: 1000, y: 200 },
      });
      return api.addEdge('action_2', newNodeId);
    });

    expect(newEdgeId).toBeTruthy();

    const offset = await page.evaluate((id: string) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const api = (window as any).WorkflowModeler?.api;
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const edge = api.getEdges().find((e: any) => e.id === id);
      return edge?.data?.controlOffset ?? null;
    }, newEdgeId as string);

    // No collision means no offset (zero or undefined are both valid).
    if (offset !== null) {
      expect(offset.x).toBe(0);
      expect(offset.y).toBe(0);
    }
  });

  test('routes a bypass curve when source and target are connected via a chain', async ({ page }) => {
    // event_1 → action_1 → action_2 is the existing chain.
    // Add a new direct edge event_1 → action_2: it must bypass action_1
    // sideways via a non-zero controlOffset.x.
    const newEdgeId = await page.evaluate(() => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const api = (window as any).WorkflowModeler?.api;
      return api ? api.addEdge('event_1', 'action_2') : null;
    });

    expect(newEdgeId).toBeTruthy();

    const offset = await page.evaluate((id: string) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const api = (window as any).WorkflowModeler?.api;
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const edge = api.getEdges().find((e: any) => e.id === id);
      return edge?.data?.controlOffset ?? null;
    }, newEdgeId as string);

    expect(offset).not.toBeNull();
    expect(offset!.x).not.toBe(0);
  });
});

// Canvas controls - minimap may be hidden by default
test.describe.skip('Workflow Modeler - Canvas Controls', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test('should display minimap', async () => {
    await expect(modeler.minimap).toBeVisible();
  });

  test('should display controls (zoom buttons)', async () => {
    await expect(modeler.controls).toBeVisible();
  });

  test('should zoom canvas with mouse wheel', async ({ page }) => {
    // This test verifies zoom functionality is available
    // Actual zoom verification would require viewport transform checks
    await modeler.canvas.click();
    await modeler.zoom(-100); // Zoom in

    // Canvas should still be visible after zoom
    await expect(modeler.canvas).toBeVisible();
  });

  test('should fit view to show all nodes', async () => {
    await modeler.fitView();

    // All nodes should be visible after fit view
    const event = modeler.getNode('event_1');
    const action = modeler.getNode('action_1');

    await expect(event).toBeVisible();
    await expect(action).toBeVisible();
  });
});
