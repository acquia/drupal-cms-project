/**
 * E2E tests for creating a NEW edge by dropping it onto a node's BODY
 * (issue #3585553 follow-on UX, [C2]).
 *
 * Previously a new edge could only be created by landing the drag precisely on
 * the destination's target HANDLE (React Flow's `onConnect` fires only then).
 * The follow-on UX makes the drop succeed when released anywhere on the
 * destination NODE — mirroring the endpoint-reconnect drop-onto-node behavior.
 *
 * The loaded mock model (fixtures/mocks.ts), after the condition edge is
 * promoted to a node, renders:
 *   - nodes: event_1, cond__edge_1, action_1, action_2
 *   - edges: event_1 -> cond, cond -> action_1, edge_2 (action_1 -> action_2)
 *
 * We assert via the SAVE payload (robust, position-independent): after dropping
 * a new edge on a node body and saving, the payload contains the new edge.
 * Negative cases (empty canvas, self-loop) must create nothing.
 */

import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

interface PayloadEdge {
  id?: string;
  source?: string;
  target?: string;
}

const edges = (payload: Record<string, unknown>): PayloadEdge[] =>
  (payload.edges as PayloadEdge[]) || [];

const findEdge = (payload: Record<string, unknown>, predicate: (e: PayloadEdge) => boolean) =>
  edges(payload).find(predicate);

test.describe('New-edge drop on node body (issue #3585553 [C2])', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
    // Sanity: the three default edges exist after load/promotion.
    expect(await modeler.getEdgeCount()).toBe(3);
  });

  test('creates an edge when a new edge is dropped on the BODY of a node', async ({ page }) => {
    // Drag a NEW edge from action_2's output handle and drop on event_1's body
    // (NOT its target handle). action_2 -> event_1 is a fresh, valid edge:
    // not a duplicate, not a self-loop, not condition->condition.
    await modeler.dragNewEdgeToNodeBody('action_2', 'event_1');
    await page.waitForTimeout(300);

    const payload = await modeler.saveAndGetPayload();
    const created = findEdge(payload, (e) => e.source === 'action_2' && e.target === 'event_1');
    expect(created).toBeDefined();
    // The original edges are still present (no edges were lost).
    expect(findEdge(payload, (e) => e.source === 'action_1' && e.target === 'action_2')).toBeDefined();
  });

  test('creates nothing when a new edge is dropped on empty canvas', async ({ page }) => {
    await modeler.dragNewEdgeToEmptyCanvas('action_2');
    await page.waitForTimeout(300);

    // Make an unrelated change so Save is enabled without creating an edge,
    // then assert no action_2-sourced edge exists in the payload.
    await modeler.moveNode('action_1', 40, 20);
    await page.waitForTimeout(200);

    const payload = await modeler.saveAndGetPayload();
    const strayFromAction2 = findEdge(payload, (e) => e.source === 'action_2');
    expect(strayFromAction2).toBeUndefined();
  });

  test('creates nothing when a new edge is dropped back on its own source node', async ({ page }) => {
    // Drop action_2's new edge back onto action_2 — no self-loop allowed.
    await modeler.dragNewEdgeToNodeBody('action_2', 'action_2');
    await page.waitForTimeout(300);

    await modeler.moveNode('action_1', 40, 20);
    await page.waitForTimeout(200);

    const payload = await modeler.saveAndGetPayload();
    const selfLoop = findEdge(payload, (e) => e.source === 'action_2' && e.target === 'action_2');
    expect(selfLoop).toBeUndefined();
  });
});
