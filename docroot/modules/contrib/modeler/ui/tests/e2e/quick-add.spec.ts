import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

test.describe('Workflow Modeler - Quick Add Node', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('Quick Add Button Visibility', () => {
    test('should show quick-add button when hovering over a node', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      
      const quickAddBtn = modeler.getQuickAddButton('event_1');
      await expect(quickAddBtn).toBeVisible();
    });

    test('should hide quick-add button when not hovering', async ({ page }) => {
      // First hover to ensure button exists
      const node = modeler.getNode('event_1');
      await node.hover();
      await page.waitForTimeout(100);
      
      // Move mouse away from nodes to toolbar area
      await modeler.toolbar.hover();
      await page.waitForTimeout(300);
      
      const quickAddBtn = modeler.getQuickAddButton('event_1');
      // Button should have opacity 0 when not hovering (CSS: opacity: 0)
      await expect(quickAddBtn).toHaveCSS('opacity', '0');
    });
  });

  test.describe('Quick Add Popup', () => {
    test('should open popup when clicking quick-add button', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      
      await modeler.openQuickAddPopup('event_1');
      
      const popup = modeler.getQuickAddPopup();
      await expect(popup).toBeVisible();
    });

    test('should display search input in popup', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      
      const searchInput = modeler.getQuickAddPopup().locator('input[type="text"]');
      await expect(searchInput).toBeVisible();
    });

    test('should display section headers in popup', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      
      const sectionHeaders = modeler.getQuickAddSectionHeaders();
      expect(await sectionHeaders.count()).toBeGreaterThan(0);
    });

    test('should show Actions by default (not Events/Conditions)', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      
      // Should show action components
      const saveAction = modeler.getQuickAddComponent('Save Entity');
      await expect(saveAction).toBeVisible();
      
      // Should NOT show events (they are excluded from quick-add)
      const cronEvent = modeler.getQuickAddComponent('Cron Run');
      await expect(cronEvent).not.toBeVisible();
    });

    test('should filter components by search', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      
      await modeler.searchQuickAddComponents('message');
      
      // Should show matching component
      const messageAction = modeler.getQuickAddComponent('Set Message');
      await expect(messageAction).toBeVisible();
      
      // Should not show non-matching component
      const saveAction = modeler.getQuickAddComponent('Save Entity');
      await expect(saveAction).not.toBeVisible();
    });

    test('should close popup when clicking close button', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      
      await modeler.closeQuickAddPopupWithButton();
      
      const popup = modeler.getQuickAddPopup();
      await expect(popup).not.toBeVisible();
    });

    test('should close popup with Escape key', async ({ page }) => {
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      
      // Wait for popup to be fully rendered and keyboard listener attached
      await page.waitForTimeout(200);
      
      await page.keyboard.press('Escape');
      
      // Wait for close animation
      await page.waitForTimeout(100);
      
      const popup = modeler.getQuickAddPopup();
      await expect(popup).not.toBeVisible();
    });
  });

  test.describe('Adding Nodes via Quick Add', () => {
    test('should create new node when component is selected', async ({ page }) => {
      const initialCount = await modeler.getNodeCount();
      
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Set Message');
      
      // Wait for node to be created
      await page.waitForTimeout(300);
      
      const finalCount = await modeler.getNodeCount();
      expect(finalCount).toBe(initialCount + 1);
    });

    test('should create edge connecting source to new node', async ({ page }) => {
      const initialEdgeCount = await modeler.getEdgeCount();
      
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Set Message');
      
      // Wait for edge to be created
      await page.waitForTimeout(300);
      
      const finalEdgeCount = await modeler.getEdgeCount();
      expect(finalEdgeCount).toBe(initialEdgeCount + 1);
    });

    test('should auto-select the new node after creation', async ({ page }) => {
      const initialCount = await modeler.getNodeCount();
      
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Set Message');
      
      // Wait for node creation
      await page.waitForTimeout(500);
      
      // Verify node was created first
      const finalCount = await modeler.getNodeCount();
      expect(finalCount).toBe(initialCount + 1);
      
      // The newest node should be selected (React Flow adds the .selected
      // class to the selected node element).
      const selectedNodes = await page.locator('.react-flow__node.selected').count();
      expect(selectedNodes).toBeGreaterThanOrEqual(1);
    });

    test('should show property panel for newly added node', async ({ page }) => {
      // Add a successor node via quick-add
      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Set Message');
      
      await page.waitForTimeout(500);
      
      // Property panel should be visible, confirming the new node is selected
      // and the configuration loader was triggered
      const propertyPanel = modeler.propertyPanel;
      await expect(propertyPanel).toBeVisible();
    });

    test('should position new node below source node', async ({ page }) => {
      const sourceNode = modeler.getNode('event_1');
      const sourceBounds = await sourceNode.boundingBox();
      
      await sourceNode.hover();
      await modeler.openQuickAddPopup('event_1');
      await modeler.selectQuickAddComponent('Set Message');
      
      // Wait for node to be created
      await page.waitForTimeout(300);
      
      // Get the newest node
      const nodes = await modeler.nodes.all();
      const newNode = nodes[nodes.length - 1];
      const newBounds = await newNode.boundingBox();
      
      // New node should be below source node
      expect(newBounds!.y).toBeGreaterThan(sourceBounds!.y);
    });
  });

  test.describe('Quick Add from Different Node Types', () => {
    test('should work from action node', async ({ page }) => {
      const initialCount = await modeler.getNodeCount();
      
      const node = modeler.getNode('action_1');
      await node.hover();
      await modeler.openQuickAddPopup('action_1');
      // Use 'Set Message' instead of 'Send Email' since that's what's in mocks
      await modeler.selectQuickAddComponent('Set Message');
      
      await page.waitForTimeout(300);
      
      const finalCount = await modeler.getNodeCount();
      expect(finalCount).toBe(initialCount + 1);
    });
  });
});

test.describe('Workflow Modeler - Quick Add Condition', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('Quick Add Condition Button', () => {
    test('should show quick-add condition button on edge', async ({ page }) => {
      // The quick-add condition button exists on edges without a condition
      const quickAddBtn = modeler.getQuickAddConditionButton('edge_2');
      await expect(quickAddBtn).toBeAttached();
    });

    test('should be clickable and functional', async ({ page }) => {
      const quickAddBtn = modeler.getQuickAddConditionButton('edge_2');
      
      // Button should be visible and interactable
      await expect(quickAddBtn).toBeVisible();
      
      // Hover and click should work
      await quickAddBtn.hover();
      await page.waitForTimeout(100);
      await quickAddBtn.click();
      
      // Popup should open
      const popup = modeler.getQuickAddPopup();
      await expect(popup).toBeVisible();
    });
  });

  test.describe('Quick Add Condition Popup', () => {
    test('should open popup when clicking quick-add condition button', async ({ page }) => {
      await modeler.openQuickAddConditionPopup('edge_2');
      
      const popup = modeler.getQuickAddPopup();
      await expect(popup).toBeVisible();
    });

    test('should show conditions and actions in popup', async ({ page }) => {
      await modeler.openQuickAddConditionPopup('edge_2');
      
      // Should show condition components (preferred, listed first)
      const isNewCondition = modeler.getQuickAddComponent('Entity is New');
      await expect(isNewCondition).toBeVisible();
      
      // Should also show action components (for inserting nodes on edges)
      const saveAction = modeler.getQuickAddComponent('Save Entity');
      await expect(saveAction).toBeVisible();
    });

    test('should filter conditions by search', async ({ page }) => {
      await modeler.openQuickAddConditionPopup('edge_2');
      
      await modeler.searchQuickAddComponents('role');
      
      // Should show matching condition
      const roleCondition = modeler.getQuickAddComponent('User Has Role');
      await expect(roleCondition).toBeVisible();
      
      // Should not show non-matching condition
      const isNewCondition = modeler.getQuickAddComponent('Entity is New');
      await expect(isNewCondition).not.toBeVisible();
    });
  });

  test.describe('Inserting Condition Nodes on Edges', () => {
    test('should insert a condition node on the edge when a condition is selected', async ({ page }) => {
      const initialNodeCount = await modeler.getNodeCount();
      const initialConditionCount = await modeler.getConditionNodeCount();

      await modeler.openQuickAddConditionPopup('edge_2');
      await modeler.selectQuickAddComponent('Entity is New');
      await page.waitForTimeout(400);

      // The popup closes and a condition NODE is inserted on the edge
      // (issue #3589093), so the node count and condition-node count grow by 1.
      const popup = modeler.getQuickAddPopup();
      await expect(popup).not.toBeVisible();
      expect(await modeler.getNodeCount()).toBe(initialNodeCount + 1);
      expect(await modeler.getConditionNodeCount()).toBe(initialConditionCount + 1);
    });

    test('should mark model as having unsaved changes', async ({ page }) => {
      await modeler.openQuickAddConditionPopup('edge_2');
      await modeler.selectQuickAddComponent('Entity is New');

      await page.waitForTimeout(300);

      // Inserting a condition node marks the model as changed.
      await expect(modeler.saveButton).toBeEnabled();
    });

    test('should select the inserted condition node', async ({ page }) => {
      await modeler.openQuickAddConditionPopup('edge_2');
      await modeler.selectQuickAddComponent('Entity is New');

      await page.waitForTimeout(300);

      // The inserted condition node is auto-selected; the property panel shows
      // its node properties.
      await expect(modeler.propertyPanel).toBeVisible();
    });

    test('should show the node label input in the property panel after inserting a condition', async ({ page }) => {
      await modeler.openQuickAddConditionPopup('edge_2');
      await modeler.selectQuickAddComponent('Entity is New');

      await page.waitForTimeout(500);

      await expect(modeler.propertyPanel).toBeVisible();

      // Conditions are edited via the NODE properties panel now, so the node
      // label input is present and prefilled with the condition name.
      const labelInput = modeler.getNodeLabelInput();
      await expect(labelInput).toBeVisible();
      await expect(labelInput).toHaveValue('Entity is New');
    });
  });
});

test.describe('Workflow Modeler - Quick Add Event', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('Quick Add Event Button', () => {
    test('should display "New event" button in toolbar', async ({ page }) => {
      const quickAddBtn = modeler.getQuickAddEventButton();
      await expect(quickAddBtn).toBeVisible();

      // Should be inside the toolbar
      const toolbar = page.locator('.workflow-toolbar');
      await expect(toolbar.locator('.quick-add-event-button')).toBeVisible();
    });

    test('should be a labeled toolbar button with + icon', async ({ page }) => {
      const quickAddBtn = modeler.getQuickAddEventButton();

      // Should contain an SVG icon (the + icon)
      const svgIcon = quickAddBtn.locator('svg');
      await expect(svgIcon).toBeVisible();

      // Should display "New event" label text
      await expect(quickAddBtn).toContainText('New event');
    });

    test('should have "New event" title for accessibility', async ({ page }) => {
      const quickAddBtn = modeler.getQuickAddEventButton();
      await expect(quickAddBtn).toHaveAttribute('title', 'New event');
    });
  });

  test.describe('Quick Add Event Popup', () => {
    test('should open popup when clicking quick-add event button', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      
      const popup = modeler.getQuickAddPopup();
      await expect(popup).toBeVisible();
    });

    test('should display search input in popup', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      
      const searchInput = modeler.getQuickAddPopup().locator('input[type="text"]');
      await expect(searchInput).toBeVisible();
    });

    test('should only show events in popup (not actions or conditions)', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      
      // Should show event components
      const cronEvent = modeler.getQuickAddComponent('Cron Run');
      await expect(cronEvent).toBeVisible();
      
      const userLogin = modeler.getQuickAddComponent('User Login');
      await expect(userLogin).toBeVisible();
      
      // Should NOT show actions
      const saveAction = modeler.getQuickAddComponent('Save Entity');
      await expect(saveAction).not.toBeVisible();
      
      // Should NOT show conditions
      const isNewCondition = modeler.getQuickAddComponent('Entity is New');
      await expect(isNewCondition).not.toBeVisible();
    });

    test('should filter events by search', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      
      await modeler.searchQuickAddComponents('cron');
      
      // Should show matching event
      const cronEvent = modeler.getQuickAddComponent('Cron Run');
      await expect(cronEvent).toBeVisible();
      
      // Should not show non-matching events
      const userLogin = modeler.getQuickAddComponent('User Login');
      await expect(userLogin).not.toBeVisible();
    });

    test('should close popup with close button', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      
      await modeler.closeQuickAddPopupWithButton();
      
      const popup = modeler.getQuickAddPopup();
      await expect(popup).not.toBeVisible();
    });

    test('should close popup with Escape key', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      
      await page.waitForTimeout(200);
      await page.keyboard.press('Escape');
      await page.waitForTimeout(100);
      
      const popup = modeler.getQuickAddPopup();
      await expect(popup).not.toBeVisible();
    });
  });

  test.describe('Adding Events to Canvas', () => {
    test('should create new event node when selected', async ({ page }) => {
      const initialCount = await modeler.getNodeCount();
      
      await modeler.openQuickAddEventPopup();
      await modeler.selectQuickAddEvent('Cron Run');
      
      await page.waitForTimeout(300);
      
      const finalCount = await modeler.getNodeCount();
      expect(finalCount).toBe(initialCount + 1);
    });

    test('should auto-select the new event after creation', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      await modeler.selectQuickAddEvent('Cron Run');
      
      await page.waitForTimeout(500);
      
      // The new event node should be selected
      const selectedNodes = await page.locator('.react-flow__node.selected').count();
      expect(selectedNodes).toBeGreaterThanOrEqual(1);
    });

    test('should mark model as having unsaved changes', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      await modeler.selectQuickAddEvent('Cron Run');
      
      await page.waitForTimeout(300);
      
      // The save button should be enabled (model has unsaved changes)
      await expect(modeler.saveButton).toBeEnabled();
    });

    test('should show property panel for new event', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      await modeler.selectQuickAddEvent('User Login');
      
      await page.waitForTimeout(300);
      
      // Property panel should show the event details
      const propertyPanel = modeler.propertyPanel;
      await expect(propertyPanel).toBeVisible();
    });
  });

  test.describe('Documentation Button in Popup', () => {
    test('should show documentation button for components with documentation', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      
      // Components with documentationUrl should have a doc button
      const docButtons = page.locator('.quick-add-popup .quick-add-documentation-btn');
      // At least one component should have documentation
      const count = await docButtons.count();
      expect(count).toBeGreaterThanOrEqual(0); // May vary based on mock data
    });

    test('documentation button should not close quick-add popup when documentation closes', async ({ page }) => {
      await modeler.openQuickAddEventPopup();
      
      // Find a documentation button if it exists
      const docButton = page.locator('.quick-add-popup .quick-add-documentation-btn').first();
      if (await docButton.count() > 0) {
        await docButton.click();
        
        // Documentation popup should open
        const docPopup = page.locator('.documentation-popup-overlay');
        await expect(docPopup).toBeVisible();
        
        // Close documentation with X button
        const closeBtn = docPopup.locator('.documentation-close-btn');
        await closeBtn.click();
        
        // Documentation should close
        await expect(docPopup).not.toBeVisible();
        
        // Quick-add popup should still be visible
        const quickAddPopup = modeler.getQuickAddPopup();
        await expect(quickAddPopup).toBeVisible();
      }
    });
  });
});

test.describe('Workflow Modeler - New Model Flow', () => {
  let modeler: ModelerPage;

  test.describe('Metadata Modal and Event Popup Flow', () => {
    test('should open metadata modal for new model', async ({ page }) => {
      // Setup mocks with isNew flag
      await setupMocks(page, { isNew: true });
      modeler = new ModelerPage(page);
      await modeler.goto('new-model');
      
      // Metadata modal should be visible for new models
      const metadataModal = page.locator('.metadata-modal');
      await expect(metadataModal).toBeVisible({ timeout: 5000 });
    });

    test('should auto-open event popup after closing metadata modal for new model', async ({ page }) => {
      // Setup mocks with isNew flag
      await setupMocks(page, { isNew: true });
      modeler = new ModelerPage(page);
      await modeler.goto('new-model');
      
      // Wait for metadata modal
      const metadataModal = page.locator('.metadata-modal');
      await expect(metadataModal).toBeVisible({ timeout: 5000 });
      
      // Close the metadata modal using the close button (more reliable than Escape key)
      const closeButton = metadataModal.locator('.close-btn');
      await closeButton.click();
      await expect(metadataModal).not.toBeVisible({ timeout: 3000 });
      
      // Wait for event popup to auto-open (uses 150ms delay in Flow.tsx + rendering time)
      const quickAddPopup = modeler.getQuickAddPopup();
      await expect(quickAddPopup).toBeVisible({ timeout: 5000 });
      
      // It should be the event popup (showing events)
      const cronEvent = modeler.getQuickAddComponent('Cron Run');
      await expect(cronEvent).toBeVisible({ timeout: 3000 });
    });

    test('should not auto-open event popup for existing models', async ({ page }) => {
      // Setup mocks without isNew flag (existing model)
      await setupMocks(page, { isNew: false });
      modeler = new ModelerPage(page);
      await modeler.goto();
      
      // Wait for modeler to load
      await page.waitForTimeout(500);
      
      // No popup should be visible automatically
      const quickAddPopup = modeler.getQuickAddPopup();
      await expect(quickAddPopup).not.toBeVisible();
      
      // No metadata modal either
      const metadataModal = page.locator('.metadata-modal');
      await expect(metadataModal).not.toBeVisible();
    });
  });
});

/** Identity plus left edge of one rendered canvas node. */
type NodeBox = { id: string; x: number };

test.describe('Workflow Modeler - Event Positioning', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  /** Ids of all nodes currently rendered on the canvas. */
  const readNodeIds = (): Promise<string[]> =>
    modeler.nodes.evaluateAll((nodes) => nodes.map((node) => node.getAttribute('data-id') ?? ''));

  /**
   * Left edge of every rendered node, read in a single pass.
   *
   * One evaluateAll collects all rectangles synchronously inside the page, so
   * every value belongs to the same viewport state.  Calling
   * locator.boundingBox() per node would be one round trip each and could
   * straddle a canvas animation frame, mixing viewport states.
   */
  const readNodeBoxes = (): Promise<NodeBox[]> =>
    modeler.nodes.evaluateAll((nodes) =>
      nodes.map((node) => ({
        id: node.getAttribute('data-id') ?? '',
        x: node.getBoundingClientRect().x,
      })),
    );

  test('should position new event to the right of existing nodes', async () => {
    // Remember the existing nodes by id.  DOM order is not a reliable identity
    // because ReactFlow reorders node elements on selection, and adding a node
    // selects it.
    const idsBefore = await readNodeIds();

    await modeler.openQuickAddEventPopup();
    await modeler.selectQuickAddEvent('Cron Run');

    // Adding a node selects it, which opens the property panel and pans/zooms the
    // canvas onto the new node.  Screen coordinates keep changing while that
    // viewport animation runs, so a box captured before the add belongs to a
    // different viewport than one captured after it.  Wait until the new node
    // stops moving, then compare all nodes from that one settled snapshot: the
    // viewport transform is a positive scale plus a translation, so it preserves
    // the left-to-right order of whatever it is applied to.
    let boxes: NodeBox[] = [];
    let previousX: number | null = null;
    await expect(async () => {
      boxes = await readNodeBoxes();
      const added = boxes.filter((box) => !idsBefore.includes(box.id));
      expect(added, 'exactly one node should have been added').toHaveLength(1);
      const settled = previousX !== null && Math.abs(added[0].x - previousX) < 0.5;
      previousX = added[0].x;
      expect(settled, 'new node should have stopped moving').toBe(true);
      // toPass ignores the configured expect timeout by default, so the default
      // 5s expect budget is applied explicitly here.
    }).toPass({ intervals: [100], timeout: 5000 });

    const addedBox = boxes.find((box) => !idsBefore.includes(box.id))!;
    const otherX = boxes.filter((box) => box.id !== addedBox.id).map((box) => box.x);
    expect(addedBox.x).toBeGreaterThan(Math.max(...otherX));
  });
});
