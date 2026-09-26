/**
 * E2E tests for conditions as first-class NODES (issue #3589093).
 *
 * Conditions used to be EDGE properties.  They are now first-class React Flow
 * nodes (type 'condition', data.__isConditionNode === true, componentType 5),
 * rendered by ConditionNode.tsx as a card with the class `.condition-node`.
 * The translation layer in utils/modelUtils.ts promotes backend condition
 * edges to nodes on load and demotes them back on save, so the backend
 * contract is unchanged.
 *
 * These specs cover the node-specific behaviors:
 *  - a condition is a draggable node,
 *  - a condition with its single outbound edge has a disabled source handle
 *    (with an explanatory tooltip),
 *  - multiple inbound edges are allowed,
 *  - inserting a condition adjacent to an existing condition auto-inserts a
 *    gateway (no two adjacent conditions),
 *  - condition-first authoring creates a condition node,
 *  - condition REUSE round-trips losslessly to the backend edge contract.
 *
 * NOTE: programmatic drag-to-connect is unreliable in headless browsers (see
 * modeler.spec.ts), so edge creation that needs to bypass that path uses the
 * public plugin API (`window.WorkflowModeler.api`), exactly like the
 * parallel-edge routing tests.
 */

import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks, mockReuseConstraints } from './fixtures/mocks';

test.describe('Condition Nodes', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    // Pre-create the WorkflowModeler global so Flow.tsx attaches the plugin
    // API there when it mounts (used by the multiple-inbound test).
    await page.addInitScript(() => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (window as any).WorkflowModeler = (window as any).WorkflowModeler || {};
    });
    await modeler.goto();
  });

  test.describe('Rendering', () => {
    test('should render the promoted condition as a node card', async () => {
      // The loaded model promotes edge_1 into a condition node (cond__edge_1).
      expect(await modeler.getConditionNodeCount()).toBe(1);
      await expect(modeler.getConditionNode('cond__edge_1')).toBeVisible();
      expect(await modeler.isConditionNode('cond__edge_1')).toBe(true);
    });
  });

  test.describe('Draggable Node', () => {
    test('should move the condition node when dragged', async ({ page }) => {
      const condition = modeler.getConditionNode('cond__edge_1');
      await expect(condition).toBeVisible();

      const before = await condition.boundingBox();
      expect(before).not.toBeNull();

      // Drag the condition node by a clear delta.
      await modeler.moveNode('cond__edge_1', 120, 80);
      await page.waitForTimeout(300);

      const after = await condition.boundingBox();
      expect(after).not.toBeNull();

      // The node should have moved (position changed in at least one axis).
      const moved =
        Math.abs(after!.x - before!.x) > 5 || Math.abs(after!.y - before!.y) > 5;
      expect(moved).toBe(true);
    });
  });

  test.describe('Single Outbound Edge (1-outbound rule)', () => {
    test('should disable the source handle once the condition has its outbound edge', async () => {
      // cond__edge_1 already has its single outbound edge (cond -> action_1),
      // so its source handle is rendered disabled.
      expect(await modeler.isNodeSourceHandleDisabled('cond__edge_1')).toBe(true);
    });

    test('should show the explanatory tooltip on the disabled source handle', async () => {
      const title = await modeler.getNodeSourceHandleTitle('cond__edge_1');
      expect(title).toBe('A condition can have only one outgoing connection.');
    });
  });

  test.describe('Multiple Inbound Edges (fan-in allowed)', () => {
    test('should allow connecting a second predecessor into a condition node', async ({ page }) => {
      const initialEdgeCount = await modeler.getEdgeCount();

      // Add a second inbound edge action_2 -> cond__edge_1.  Multiple inbound
      // edges to a condition are allowed (fan-in for a reused condition).
      const newEdgeId = await page.evaluate(() => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const api = (window as any).WorkflowModeler?.api;
        return api ? api.addEdge('action_2', 'cond__edge_1') : null;
      });

      expect(newEdgeId).toBeTruthy();
      // The edge was accepted (not rejected), so the edge count grew by one.
      expect(await modeler.getEdgeCount()).toBe(initialEdgeCount + 1);

      // The condition node now has two inbound edges in the store.
      const inboundCount = await page.evaluate(() => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const api = (window as any).WorkflowModeler?.api;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        return api.getEdges().filter((e: any) => e.target === 'cond__edge_1').length;
      });
      expect(inboundCount).toBe(2);
    });
  });

  test.describe('No Two Adjacent Conditions', () => {
    test('should auto-insert a gateway when inserting a condition adjacent to an existing condition', async ({ page }) => {
      const gatewaysBefore = await page.locator('.gateway-node').count();
      const conditionsBefore = await modeler.getConditionNodeCount();

      // The condition node's outbound edge is cond__edge_1 -> action_1, with id
      // `cond__edge_1__out`.  Inserting a condition on THAT edge would place a
      // new condition directly after the existing one — the invariant forces a
      // gateway between them (issue #3589093).
      await modeler.openQuickAddConditionPopup('cond__edge_1__out');
      await modeler.selectQuickAddComponent('User Has Role');
      await page.waitForTimeout(500);

      // A new condition node was added...
      expect(await modeler.getConditionNodeCount()).toBe(conditionsBefore + 1);
      // ...and a gateway was auto-inserted to keep the two conditions apart.
      const gatewaysAfter = await page.locator('.gateway-node').count();
      expect(gatewaysAfter).toBe(gatewaysBefore + 1);
    });
  });

  test.describe('Condition-First Authoring', () => {
    test('should create a condition node when selecting a condition from a node quick-add', async ({ page }) => {
      const conditionsBefore = await modeler.getConditionNodeCount();

      const node = modeler.getNode('event_1');
      await node.hover();
      await modeler.openQuickAddPopup('event_1');

      // Filter to conditions and pick one.
      await page.locator('.quick-add-filter-toggle').click();
      const conditionFilter = page
        .locator('.quick-add-filter-option')
        .filter({ hasText: /Links|Conditions/ });
      await conditionFilter.first().click();
      await page.waitForTimeout(200);
      await page.locator('.quick-add-component-item').first().click();
      await page.waitForTimeout(500);

      // A condition NODE was created (followed by a placeholder action node).
      expect(await modeler.getConditionNodeCount()).toBe(conditionsBefore + 1);
      await expect(page.locator('.placeholder-node')).toBeVisible();
    });
  });
});

test.describe('Condition Reuse Round-trip', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    // Serve the reuse model (two condition edges sharing the same conditionId
    // and target) and opt into reuse via model_constraints.
    await setupMocks(page, {
      reuseModel: true,
      modelConstraints: mockReuseConstraints,
    });
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test('should collapse reused condition edges into a single shared condition node on load', async () => {
    // event_1 -[cond]-> action_1 and event_2 -[cond]-> action_1 share the same
    // conditionId; with reuse enabled they collapse into ONE shared condition
    // node with two inbound edges and a single outbound edge.
    expect(await modeler.getConditionNodeCount()).toBe(1);

    const sharedId = 'cond__shared__eca_shared_condition_1__action_1';
    await expect(modeler.getConditionNode(sharedId)).toBeVisible();

    // The shared node has its single outbound edge, so the source handle is
    // disabled (1-outbound rule still applies to a reused condition).
    expect(await modeler.isNodeSourceHandleDisabled(sharedId)).toBe(true);

    // Canvas: 3 backend nodes (event_1, event_2, action_1) + 1 shared
    // condition node = 4 nodes; 2 inbound + 1 outbound = 3 edges.
    expect(await modeler.getNodeCount()).toBe(4);
    expect(await modeler.getEdgeCount()).toBe(3);
  });

  test('should demote the shared condition node back to the edge-property contract on save', async ({ page }) => {
    // Intercept the save POST (the mock Drupal.ajax in test-server.ts performs
    // a real fetch to the save endpoint).
    const saveRequestPromise = page.waitForRequest(
      (request) =>
        request.url().includes('modeler-api/model') && request.method() === 'POST',
    );

    // Make a change to enable save, then save.
    await modeler.autoLayout();
    await page.waitForTimeout(300);
    await expect(modeler.saveButton).toBeEnabled();
    await modeler.saveButton.click();

    const saveRequest = await saveRequestPromise;
    const payload = JSON.parse(saveRequest.postData() || '{}');

    // The shared condition node demotes to TWO backend condition edges, one
    // per original predecessor, BOTH targeting action_1 and BOTH sharing the
    // original conditionId — losslessly.
    const reusedEdges = (payload.edges ?? []).filter(
      (e: Record<string, unknown>) =>
        e.conditionId === 'eca_shared_condition_1' && e.target === 'action_1',
    );
    expect(reusedEdges).toHaveLength(2);

    const sources = reusedEdges.map((e: Record<string, string>) => e.source).sort();
    expect(sources).toEqual(['event_1', 'event_2']);

    // Condition plugin/label survive on every demoted edge.
    for (const edge of reusedEdges) {
      expect(edge.condition).toBe('entity:is_new');
      expect(edge.conditionLabel).toBe('Entity is New');
    }

    // No condition NODE may leak into the exported nodes[] (backend contract).
    const conditionNodes = (payload.nodes ?? []).filter(
      (n: Record<string, unknown>) => n.componentType === 5,
    );
    expect(conditionNodes).toHaveLength(0);
  });
});
