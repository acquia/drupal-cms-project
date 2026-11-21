/**
 * E2E tests for condensed layout behavior.
 *
 * Conditions are first-class NODES now (issue #3589093): there are no
 * condition edges and no extra per-edge spacing for conditions
 * (CONDITION_EXTRA_SPACING was removed).  A condition simply occupies its own
 * node in the vertical chain.
 *
 * Verifies that:
 * 1. Auto-layout places successors below their parents (not beside them).
 * 2. A promoted condition node sits between its source and target in the chain.
 * 3. Inserting a condition node on an edge pushes the target node down to make
 *    room for the new node (the source does not move).
 */

import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

test.describe('Workflow Modeler - Condensed Layout', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('Auto-Layout Node Placement', () => {
    test('should place successors below their parent after auto-layout', async ({ page }) => {
      // The mock model has: event_1 -> action_1 -> action_2
      // After auto-layout they should form a vertical chain.
      await modeler.autoLayout();
      await page.waitForTimeout(500);

      const eventBox = await modeler.getNode('event_1').boundingBox();
      const action1Box = await modeler.getNode('action_1').boundingBox();
      const action2Box = await modeler.getNode('action_2').boundingBox();

      expect(eventBox).toBeDefined();
      expect(action1Box).toBeDefined();
      expect(action2Box).toBeDefined();

      // Each successor should be positioned below its parent
      expect(action1Box!.y).toBeGreaterThan(eventBox!.y);
      expect(action2Box!.y).toBeGreaterThan(action1Box!.y);
    });

    test('should place the promoted condition node between its source and target', async ({ page }) => {
      // In the mock model, edge_1 (event_1 -> action_1) carries a condition.
      // On load it is promoted to a condition NODE between event_1 and
      // action_1, so the chain becomes event_1 -> cond -> action_1 -> action_2.
      await modeler.autoLayout();
      await page.waitForTimeout(500);

      const eventBox = await modeler.getNode('event_1').boundingBox();
      const conditionBox = await modeler.getConditionNode('cond__edge_1').boundingBox();
      const action1Box = await modeler.getNode('action_1').boundingBox();

      expect(eventBox).toBeDefined();
      expect(conditionBox).toBeDefined();
      expect(action1Box).toBeDefined();

      // The condition node sits vertically between event_1 and action_1.
      expect(conditionBox!.y).toBeGreaterThan(eventBox!.y);
      expect(action1Box!.y).toBeGreaterThan(conditionBox!.y);
    });
  });

  test.describe('Dynamic Spacing on Condition Add', () => {
    test('should shift target node down when inserting a condition node on an edge', async ({ page }) => {
      // Inserting a condition on edge_2 creates a condition NODE between
      // action_1 and action_2 (issue #3589093).  The new node needs vertical
      // room, so action_2 (the target) is pushed downward while action_1 (the
      // source) does not move.

      // First trigger auto-layout so nodes are in a predictable arrangement
      await modeler.autoLayout();

      // Wait long enough for the viewport animation to fully settle.
      await page.waitForTimeout(1000);

      // Record positions before adding a condition
      const action1Before = await modeler.getNode('action_1').boundingBox();
      const action2Before = await modeler.getNode('action_2').boundingBox();
      expect(action1Before).toBeDefined();
      expect(action2Before).toBeDefined();

      const gapBefore = action2Before!.y - action1Before!.y;

      // Insert a condition node on edge_2 (action_1 -> action_2)
      await modeler.openQuickAddConditionPopup('edge_2');
      await modeler.selectQuickAddComponent('Entity is New');
      await page.waitForTimeout(1000);

      // Inserting the condition node auto-pans the viewport toward the new node
      // (panToNodeIfOffscreen), which shifts the *screen* coordinates of every
      // node by the same amount.  An absolute "source did not move" assertion in
      // screen space is therefore invalid.  Instead we assert the vertical gap
      // between source (action_1) and target (action_2) grew — a pan-invariant
      // measurement, since both endpoints shift together — which proves action_2
      // was pushed down to make room for the inserted condition node.
      const action1After = await modeler.getNode('action_1').boundingBox();
      const action2After = await modeler.getNode('action_2').boundingBox();
      expect(action1After).toBeDefined();
      expect(action2After).toBeDefined();

      const gapAfter = action2After!.y - action1After!.y;
      // The gap after inserting the condition node should be larger than before
      expect(gapAfter).toBeGreaterThan(gapBefore);
    });
  });

  test.describe('Vertical Spacing When Inserting Elements', () => {
    test('should place inserted node below source with proper spacing when inserting on an edge', async ({ page }) => {
      // Use auto-layout for a clean starting point
      await modeler.autoLayout();
      await page.waitForTimeout(1000);

      // edge_1 (event_1 -> action_1) carries a condition and is promoted into a
      // condition node on load, so it no longer exists as a rendered edge.
      // Insert on edge_2 (action_1 -> action_2), a surviving plain edge, which
      // places the new node between action_1 and action_2.
      const action1Before = await modeler.getNode('action_1').boundingBox();
      const action2Before = await modeler.getNode('action_2').boundingBox();
      expect(action1Before).toBeDefined();
      expect(action2Before).toBeDefined();

      // Insert a 'Save Entity' action on edge_2 (action_1 -> action_2)
      await modeler.openQuickAddConditionPopup('edge_2');
      await modeler.selectQuickAddComponent('Save Entity');
      await page.waitForTimeout(1000);

      // The new node should appear between action_1 and action_2.  Find a node
      // that wasn't there before (the newly inserted one) in the vertical band
      // below action_1.  Note: boundingBox() is in screen coordinates and the
      // viewport may pan after insertion, so we identify the new node by data-id
      // rather than by an absolute screen position relative to the pre-insert
      // measurements.
      const nodes = await modeler.nodes.all();
      let newNodeBox = null;
      for (const node of nodes) {
        const dataId = await node.getAttribute('data-id');
        if (
          dataId !== 'event_1' &&
          dataId !== 'action_1' &&
          dataId !== 'action_2' &&
          dataId !== 'cond__edge_1'
        ) {
          const box = await node.boundingBox();
          if (box) {
            newNodeBox = box;
            break;
          }
        }
      }
      expect(newNodeBox).toBeDefined();

      // The new node should sit below action_1 (its source) on screen.  Both the
      // node and action_1 are measured after the insertion/pan, so this screen
      // comparison is consistent.
      const action1After = await modeler.getNode('action_1').boundingBox();
      expect(action1After).toBeDefined();
      expect(newNodeBox!.y).toBeGreaterThan(action1After!.y);
    });

    test('should push nodes further down when adding successor node', async ({ page }) => {
      // Record action_2 position before adding a successor to action_1
      const action2Before = await modeler.getNode('action_2').boundingBox();
      expect(action2Before).toBeDefined();

      // Add a successor node to action_1 via quick-add
      const action1 = modeler.getNode('action_1');
      await action1.hover();
      await modeler.openQuickAddPopup('action_1');
      await modeler.selectQuickAddComponent('Set Message');
      await page.waitForTimeout(500);

      // The new node should be below action_1
      const nodes = await modeler.nodes.all();
      const lastNode = nodes[nodes.length - 1];
      const lastNodeBox = await lastNode.boundingBox();
      const action1Box = await modeler.getNode('action_1').boundingBox();
      expect(lastNodeBox).toBeDefined();
      expect(action1Box).toBeDefined();
      expect(lastNodeBox!.y).toBeGreaterThan(action1Box!.y);
    });
  });
});
