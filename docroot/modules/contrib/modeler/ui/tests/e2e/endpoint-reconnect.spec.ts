/**
 * E2E tests for per-handle, selection-gated edge-endpoint reconnection
 * (issue #3585553).
 *
 * The loaded mock model (see fixtures/mocks.ts) renders, after the condition
 * edge is promoted to a node:
 *   - nodes: event_1, cond__edge_1, action_1, action_2
 *   - edges: event_1 -> cond, cond -> action_1, edge_2 (action_1 -> action_2)
 *
 * `edge_2` is a plain edge (action_1 -> action_2), ideal for endpoint moves.
 *
 * We assert the reconnection by inspecting the SAVE payload (the same robust
 * pattern used by condition-roundtrip.spec.ts) rather than brittle pixel
 * positions: after moving an endpoint and saving, the edge's TOP-LEVEL
 * source/target reflect the new node.
 *
 * Dropping on empty canvas must SNAP BACK — the edge is never mutated and the
 * save payload still shows the original endpoints.
 */

import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

interface PayloadEdge {
  id?: string;
  source?: string;
  target?: string;
}

const findEdge = (payload: Record<string, unknown>, predicate: (e: PayloadEdge) => boolean) =>
  ((payload.edges as PayloadEdge[]) || []).find(predicate);

test.describe('Endpoint Reconnection (issue #3585553)', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
    // Sanity: the plain edge_2 (action_1 -> action_2) exists.
    expect(await modeler.getEdgeCount()).toBe(3);
  });

  test('moves a TARGET endpoint to a different node', async ({ page }) => {
    // Move edge_2's target from action_2 to event_1 (action_1 -> event_1).
    await modeler.reconnectEdgeEndpoint('edge_2', 'target', 'event_1');
    await page.waitForTimeout(300);

    const payload = await modeler.saveAndGetPayload();
    const moved = findEdge(payload, (e) => e.source === 'action_1' && e.target === 'event_1');
    expect(moved).toBeDefined();
    // The edge must NOT still point at action_2.
    const stale = findEdge(payload, (e) => e.source === 'action_1' && e.target === 'action_2');
    expect(stale).toBeUndefined();
  });

  test('shows a live preview line mid-drag and removes it on drop', async () => {
    // Begin dragging edge_2's target grip toward event_1 and pause (mouse down).
    await modeler.startEndpointDrag('edge_2', 'target', 'event_1');
    // The reconnect preview line must be visible while dragging.
    await expect(modeler.reconnectPreview).toBeVisible();
    // Releasing the mouse over event_1 commits and removes the preview.
    await modeler.endDrag();
    await expect(modeler.reconnectPreview).toHaveCount(0);
  });

  test('makes a destination grip non-interactive at the hit-test during a drag (regression #3585553)', async ({ page }) => {
    // Deterministic browser-level proof of the fix. Select both edges so
    // event_1 (edge_1__in) and action_1 (edge_2) each render a source grip.
    // Begin dragging edge_2's source grip and, mid-drag, probe
    // document.elementFromPoint at event_1's OWN grip center: the fix
    // (reconnect-dragging → pointer-events:none on all grips) must mean the
    // top element there is NOT an interactive endpoint grip, so the drop
    // hit-test can reach event_1. Pre-fix the grip kept pointer-events:all and
    // would be the top element, blocking the drop.
    await modeler.selectMultipleEdges(['edge_1__in', 'edge_2']);
    await page.waitForTimeout(100);

    const dragBox = await page
      .locator('.edge-endpoint-grip--source[data-edge-id="edge_2"]')
      .first()
      .boundingBox();
    const dropGrip = page.locator('.edge-endpoint-grip--source[data-edge-id="edge_1__in"]').first();
    const dropBox = await dropGrip.boundingBox();
    expect(dragBox && dropBox).toBeTruthy();

    const cx = dropBox!.x + dropBox!.width / 2;
    const cy = dropBox!.y + dropBox!.height / 2;

    // Start the drag and hold.
    await page.mouse.move(dragBox!.x + dragBox!.width / 2, dragBox!.y + dragBox!.height / 2);
    await page.mouse.down();
    await page.mouse.move(cx, cy, { steps: 10 });

    // Mid-drag: the destination grip at (cx,cy) must have pointer-events:none
    // so it does not intercept the hit-test. Verify the live computed style of
    // event_1's grip element.
    const gripPointerEvents = await dropGrip.evaluate((el) => getComputedStyle(el).pointerEvents);
    expect(gripPointerEvents).toBe('none');

    await page.mouse.up();
  });

  test('moves a SOURCE endpoint to a different node', async ({ page }) => {
    // Move edge_2's source from action_1 to event_1 (event_1 -> action_2).
    await modeler.reconnectEdgeEndpoint('edge_2', 'source', 'event_1');
    await page.waitForTimeout(300);

    const payload = await modeler.saveAndGetPayload();
    const moved = findEdge(payload, (e) => e.source === 'event_1' && e.target === 'action_2');
    expect(moved).toBeDefined();
    const stale = findEdge(payload, (e) => e.source === 'action_1' && e.target === 'action_2');
    expect(stale).toBeUndefined();
  });

  test('drops a SOURCE endpoint onto a node whose own outgoing edge is selected (issue #3585553)', async ({ page }) => {
    // User scenario: event_1 has a selected outgoing edge (edge_1__in) AND
    // action_1 has a selected outgoing edge (edge_2). With BOTH selected, both
    // nodes render a source reconnect grip. Dragging edge_2's source grip onto
    // event_1 must move edge_2 to start from event_1 — the presence of a grip
    // on the destination node (because its own edge is selected) must NOT block
    // the drop.
    //
    // End-to-end guard for the fix that makes all grips pointer-events:none
    // during a reconnect drag (plus the hit-test re-query hardening), so a
    // grip overlay on the destination can never intercept the drop. The
    // deterministic DOM-level proof lives in the useEndpointDrag unit test
    // ("resolves the node beneath a grip overlay …").
    await modeler.selectMultipleEdges(['edge_1__in', 'edge_2']);
    await page.waitForTimeout(100);

    // Drag edge_2's source grip onto event_1 (preserving the multi-selection).
    await modeler.dragSelectedEdgeEndpoint('edge_2', 'source', 'event_1');
    await page.waitForTimeout(300);

    const payload = await modeler.saveAndGetPayload();
    // edge_2 now starts from event_1 (event_1 -> action_2).
    const moved = findEdge(payload, (e) => e.source === 'event_1' && e.target === 'action_2');
    expect(moved).toBeDefined();
    // It must NOT still start from action_1.
    const stale = findEdge(payload, (e) => e.source === 'action_1' && e.target === 'action_2');
    expect(stale).toBeUndefined();
  });

  test('snaps back when an endpoint is dropped on empty canvas', async ({ page }) => {
    await modeler.dragEndpointToEmptyCanvas('edge_2', 'target');
    await page.waitForTimeout(300);

    // The edge is unchanged: still action_1 -> action_2 in the save payload.
    // We must make a (different) change to enable Save without altering edge_2;
    // moving a node keeps edge_2 intact while marking the model dirty.
    await modeler.moveNode('action_1', 40, 20);
    await page.waitForTimeout(200);

    const payload = await modeler.saveAndGetPayload();
    const unchanged = findEdge(payload, (e) => e.source === 'action_1' && e.target === 'action_2');
    expect(unchanged).toBeDefined();
  });
});
