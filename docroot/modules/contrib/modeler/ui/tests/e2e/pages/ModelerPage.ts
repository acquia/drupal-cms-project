import { Page, Locator, expect } from '@playwright/test';

/**
 * Page Object for the Workflow Modeler.
 * Provides a clean API for interacting with modeler elements in tests.
 */
export class ModelerPage {
  readonly page: Page;

  // Main layout elements
  readonly canvas: Locator;
  readonly propertyPanel: Locator;
  readonly toolbar: Locator;
  readonly replayPanel: Locator;

  // Toolbar buttons (main toolbar)
  readonly saveButton: Locator;
  readonly settingsButton: Locator;
  readonly searchButton: Locator;
  readonly exportButton: Locator;
  readonly kebabMenuTrigger: Locator;

  // Canvas toolbar buttons (secondary toolbar on the canvas)
  readonly undoButton: Locator;
  readonly redoButton: Locator;
  readonly autoLayoutButton: Locator;
  readonly copyButton: Locator;
  readonly pasteButton: Locator;
  // Canvas elements
  readonly nodes: Locator;
  readonly edges: Locator;
  readonly minimap: Locator;
  readonly controls: Locator;

  constructor(page: Page) {
    this.page = page;

    // Main layout - use actual CSS classes from the app.
    // NOTE (unified panel, issue project/modeler#3576269): the standalone
    // replay column was merged into the single right-hand panel. Replay /
    // "Review flow" content now renders INSIDE `.workflow-property-panel`
    // when the panel is in Review mode, so `replayPanel` points at the same
    // unified panel as `propertyPanel`.
    this.canvas = page.locator('.react-flow');
    this.propertyPanel = page.locator('.workflow-property-panel');
    this.toolbar = page.locator('.workflow-toolbar');
    this.replayPanel = page.locator('.workflow-property-panel');

    // Main toolbar buttons
    this.saveButton = page.locator('button[title="Save Model"]');
    // Settings and Export are now inside the kebab menu (ToolbarMenu)
    this.kebabMenuTrigger = page.locator('.toolbar-menu-trigger');
    this.settingsButton = page.locator('.toolbar-menu-item:has(.toolbar-menu-item-label:text("Model Settings"))');
    this.searchButton = page.locator('.toolbar-search-inline');
    this.exportButton = page.locator('.toolbar-menu-item:has(.toolbar-menu-item-label:text("Export Model"))');

    // Canvas toolbar buttons (secondary toolbar above the canvas)
    this.undoButton = page.locator('button[title*="Undo"]');
    this.redoButton = page.locator('button[title*="Redo"]');
    this.autoLayoutButton = page.locator('.canvas-toolbar-dropdown-item:has(.canvas-toolbar-dropdown-label:text("Auto Layout"))');
    this.copyButton = page.locator('button[title*="Copy Selected"]');
    this.pasteButton = page.locator('button[title*="Paste Elements"]');

    // Canvas
    this.nodes = page.locator('.react-flow__node');
    this.edges = page.locator('.react-flow__edge');
    this.minimap = page.locator('.react-flow__minimap');
    this.controls = page.locator('.react-flow__controls');
  }

  /**
   * Navigate to the modeler page.
   */
  async goto(modelId = 'test-model-1') {
    await this.page.goto(`/modeler/${modelId}`);
    await this.waitForLoad();
  }

  /**
   * Wait for the modeler to fully load.
   */
  async waitForLoad() {
    await this.canvas.waitFor({ state: 'visible', timeout: 10000 });
    // Wait for React Flow to initialize
    await this.page.waitForSelector('.react-flow__viewport', { timeout: 10000 });
  }

  /**
   * Get a specific node by its ID.
   */
  getNode(nodeId: string): Locator {
    return this.page.locator(`[data-id="${nodeId}"]`);
  }

  /**
   * Get a specific edge by its ID.
   * React Flow creates edge path elements with the edge ID.
   * We look for the edge path or its parent group.
   */
  getEdge(edgeId: string): Locator {
    // Try to find edge by path ID first (React Flow puts edge ID on the path element)
    return this.page.locator(`.react-flow__edge path#${edgeId}, .react-flow__edge[data-id="${edgeId}"]`);
  }

  /**
   * Select a node on the canvas.
   */
  async selectNode(nodeId: string) {
    const node = this.getNode(nodeId);
    await node.click();
  }

  /**
   * Select an edge on the canvas.
   *
   * Conditions are first-class nodes now (issue #3589093), so every rendered
   * edge is a plain default edge — there is no condition card overlay to click
   * through.  This still dispatches a click directly on the SVG edge wrapper so
   * the event reliably reaches ReactFlow's handler regardless of the small
   * quick-add affordance rendered at the edge midpoint.
   */
  async selectEdge(edgeId: string) {
    const edgeGroup = this.page.locator(
      `.react-flow__edge[data-testid="rf__edge-${edgeId}"], .react-flow__edge[data-id="${edgeId}"]`,
    ).first();
    await edgeGroup.dispatchEvent('click');
  }

  /**
   * Select multiple nodes (Shift+Click).
   */
  async selectMultipleNodes(nodeIds: string[]) {
    for (let i = 0; i < nodeIds.length; i++) {
      const node = this.getNode(nodeIds[i]);
      if (i === 0) {
        await node.click();
      } else {
        await node.click({ modifiers: ['Shift'] });
      }
    }
  }

  /**
   * Delete selected elements using keyboard shortcut.
   */
  async deleteSelected() {
    await this.page.keyboard.press('Delete');
  }

  /**
   * Copy selected elements using keyboard shortcut.
   */
  async copySelected() {
    await this.page.keyboard.press('Control+c');
  }

  /**
   * Paste elements using keyboard shortcut.
   */
  async paste() {
    await this.page.keyboard.press('Control+v');
  }

  /**
   * Undo last action using keyboard shortcut.
   */
  async undo() {
    await this.page.keyboard.press('Control+z');
  }

  /**
   * Redo last undone action using keyboard shortcut.
   */
  async redo() {
    await this.page.keyboard.press('Control+Shift+z');
  }

  /**
   * Open search using keyboard shortcut.
   */
  async openSearch() {
    await this.page.keyboard.press('Control+f');
  }

  /**
   * Save the model using keyboard shortcut.
   */
  async saveModel() {
    await this.page.keyboard.press('Control+s');
  }

  /**
   * Click a toolbar button, falling back to the overflow menu when the
   * button has been collapsed by the toolbar overflow system.
   */
  private async clickToolbarButton(inlineButton: Locator, overflowLabel: string) {
    if (await inlineButton.isVisible().catch(() => false)) {
      await inlineButton.click();
      return;
    }
    // Button is overflowed — open the "..." menu and click the item there.
    const overflowTrigger = this.page.locator('.toolbar-overflow-trigger');
    await overflowTrigger.click();
    const menuItem = this.page.locator(`.toolbar-overflow-item:has(.toolbar-overflow-item-label:text("${overflowLabel}"))`);
    await menuItem.click();
  }

  /**
   * Click the auto-layout button via the View dropdown in the canvas toolbar.
   */
  async autoLayout() {
    // Open the View dropdown in the canvas toolbar
    const viewTrigger = this.page.locator('.canvas-toolbar-view-trigger');
    await viewTrigger.click();
    // Click "Auto Layout" in the dropdown
    await this.autoLayoutButton.waitFor({ state: 'visible', timeout: 2000 });
    await this.autoLayoutButton.click();
  }

  /**
   * Open the settings modal via the kebab menu.
   */
  async openSettings() {
    await this.kebabMenuTrigger.click();
    await this.settingsButton.waitFor({ state: 'visible', timeout: 2000 });
    await this.settingsButton.click();
    await this.page.waitForSelector('.metadata-modal', { state: 'visible' });
  }

  /**
   * Close any open modal.
   */
  async closeModal() {
    await this.page.keyboard.press('Escape');
  }

  /**
   * Get the node count on the canvas.
   */
  async getNodeCount(): Promise<number> {
    return await this.nodes.count();
  }

  /**
   * Get the edge count on the canvas.
   */
  async getEdgeCount(): Promise<number> {
    return await this.edges.count();
  }

  /**
   * Connect two nodes by creating an edge.
   */
  async connectNodes(sourceNodeId: string, targetNodeId: string) {
    const sourceNode = this.getNode(sourceNodeId);
    const targetNode = this.getNode(targetNodeId);

    // Find the source handle (output)
    const sourceHandle = sourceNode.locator('.react-flow__handle-right, .react-flow__handle-bottom');
    // Find the target handle (input)
    const targetHandle = targetNode.locator('.react-flow__handle-left, .react-flow__handle-top');

    await sourceHandle.dragTo(targetHandle);
  }

  /**
   * Start a NEW edge from a source node's output handle and DROP it on the
   * BODY (center) of the destination node — NOT on its target handle (issue
   * #3585553 follow-on UX). React Flow's `onConnect` fires only on a handle
   * drop; this exercises the `onConnectEnd` node-body fallback. Performs a real
   * mouse drag (down on the source handle, move to the destination node center,
   * up) so the live connection-line preview and `onConnectEnd` both fire.
   */
  async dragNewEdgeToNodeBody(sourceNodeId: string, destinationNodeId: string) {
    const sourceNode = this.getNode(sourceNodeId);
    // Output handle is rendered at the bottom of each node (Position.Bottom).
    const sourceHandle = sourceNode.locator('.react-flow__handle-bottom').first();
    await sourceHandle.waitFor({ state: 'visible', timeout: 5000 });
    const handleBox = await sourceHandle.boundingBox();
    if (!handleBox) throw new Error(`Source handle for node ${sourceNodeId} not found`);

    const dest = this.getNode(destinationNodeId);
    const destBox = await dest.boundingBox();
    if (!destBox) throw new Error(`Destination node ${destinationNodeId} not found`);

    await this.page.mouse.move(handleBox.x + handleBox.width / 2, handleBox.y + handleBox.height / 2);
    await this.page.mouse.down();
    // Move to the node CENTER (its body), away from the target handle, in
    // steps so React Flow registers an active connection drag.
    await this.page.mouse.move(destBox.x + destBox.width / 2, destBox.y + destBox.height / 2, { steps: 10 });
    await this.page.mouse.up();
  }

  /**
   * Start a NEW edge from a source node's output handle and DROP it on empty
   * canvas (should create nothing). Used as the [C2] negative test.
   */
  async dragNewEdgeToEmptyCanvas(sourceNodeId: string) {
    const sourceNode = this.getNode(sourceNodeId);
    const sourceHandle = sourceNode.locator('.react-flow__handle-bottom').first();
    await sourceHandle.waitFor({ state: 'visible', timeout: 5000 });
    const handleBox = await sourceHandle.boundingBox();
    if (!handleBox) throw new Error(`Source handle for node ${sourceNodeId} not found`);

    const canvasBox = await this.canvas.boundingBox();
    if (!canvasBox) throw new Error('Canvas not found');

    await this.page.mouse.move(handleBox.x + handleBox.width / 2, handleBox.y + handleBox.height / 2);
    await this.page.mouse.down();
    // Drop in the bottom-right corner — clear of every node.
    await this.page.mouse.move(canvasBox.x + canvasBox.width - 10, canvasBox.y + canvasBox.height - 10, { steps: 10 });
    await this.page.mouse.up();
  }

  /**
   * Drag a node to a new position.
   */
  async moveNode(nodeId: string, deltaX: number, deltaY: number) {
    const node = this.getNode(nodeId);
    const box = await node.boundingBox();
    if (!box) throw new Error(`Node ${nodeId} not found`);

    await this.page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await this.page.mouse.down();
    await this.page.mouse.move(box.x + box.width / 2 + deltaX, box.y + box.height / 2 + deltaY);
    await this.page.mouse.up();
  }

  /**
   * Drag a selected edge's SOURCE or TARGET endpoint grip onto another node
   * (per-handle endpoint reconnection — issue #3585553).
   *
   * The grip (`.edge-endpoint-grip--source` / `--target`) is only rendered
   * after the edge is selected and the endpoint is eligible, so this selects
   * the edge first, then performs a real mouse drag from the grip center to
   * the center of the destination node. Dropping over a node triggers the
   * `elementFromPoint` hit-test in useEndpointDrag.
   */
  async reconnectEdgeEndpoint(
    edgeId: string,
    endpoint: 'source' | 'target',
    destinationNodeId: string,
  ) {
    await this.selectEdge(edgeId);
    const grip = this.page.locator(`.edge-endpoint-grip--${endpoint}`).first();
    await grip.waitFor({ state: 'visible', timeout: 5000 });

    const gripBox = await grip.boundingBox();
    if (!gripBox) throw new Error(`Endpoint grip (${endpoint}) for edge ${edgeId} not found`);

    const dest = this.getNode(destinationNodeId);
    const destBox = await dest.boundingBox();
    if (!destBox) throw new Error(`Destination node ${destinationNodeId} not found`);

    await this.page.mouse.move(gripBox.x + gripBox.width / 2, gripBox.y + gripBox.height / 2);
    await this.page.mouse.down();
    // Intermediate move so the gesture registers as a drag.
    await this.page.mouse.move(destBox.x + destBox.width / 2, destBox.y + destBox.height / 2, { steps: 8 });
    await this.page.mouse.up();
  }

  /**
   * Select multiple edges. The first is a plain click; subsequent edges are
   * shift-clicks so React Flow accumulates them into the multi-selection.
   * Uses a dispatched MouseEvent (with shiftKey) because the small quick-add
   * affordance over the edge midpoint makes real positional clicks flaky.
   */
  async selectMultipleEdges(edgeIds: string[]) {
    for (let i = 0; i < edgeIds.length; i++) {
      const edgeGroup = this.page.locator(
        `.react-flow__edge[data-testid="rf__edge-${edgeIds[i]}"], .react-flow__edge[data-id="${edgeIds[i]}"]`,
      ).first();
      // React Flow decides multi-select from the LIVE keyboard state
      // (multiSelectionKeyCode="Shift"), not from a synthetic event's shiftKey.
      // Hold Shift down for every edge after the first so the dispatched click
      // accumulates into the selection instead of replacing it.
      if (i === 0) {
        await edgeGroup.dispatchEvent('click');
      } else {
        await this.page.keyboard.down('Shift');
        await edgeGroup.dispatchEvent('click', { shiftKey: true });
        await this.page.keyboard.up('Shift');
      }
    }
  }

  /**
   * Drag a SPECIFIC already-selected edge's endpoint grip onto another node,
   * WITHOUT re-selecting (so a pre-established multi-selection is preserved).
   * Targets the grip by its `data-edge-id` so the correct edge's grip is used
   * even when several edges are selected.
   */
  async dragSelectedEdgeEndpoint(
    edgeId: string,
    endpoint: 'source' | 'target',
    destinationNodeId: string,
  ) {
    const grip = this.page
      .locator(`.edge-endpoint-grip--${endpoint}[data-edge-id="${edgeId}"]`)
      .first();
    await grip.waitFor({ state: 'visible', timeout: 5000 });
    const gripBox = await grip.boundingBox();
    if (!gripBox) throw new Error(`Endpoint grip (${endpoint}) for edge ${edgeId} not found`);

    const dest = this.getNode(destinationNodeId);
    const destBox = await dest.boundingBox();
    if (!destBox) throw new Error(`Destination node ${destinationNodeId} not found`);

    await this.page.mouse.move(gripBox.x + gripBox.width / 2, gripBox.y + gripBox.height / 2);
    await this.page.mouse.down();
    await this.page.mouse.move(destBox.x + destBox.width / 2, destBox.y + destBox.height / 2, { steps: 8 });
    await this.page.mouse.up();
  }

  /**
   * Begin dragging an endpoint grip and PAUSE mid-drag (mouse still down) over
   * the destination node, without releasing. Lets a test assert the live
   * reconnect preview line (issue #3585553) while the drag is in progress.
   * Call `endDrag()` afterward to release the mouse.
   */
  async startEndpointDrag(
    edgeId: string,
    endpoint: 'source' | 'target',
    destinationNodeId: string,
  ) {
    await this.selectEdge(edgeId);
    const grip = this.page.locator(`.edge-endpoint-grip--${endpoint}`).first();
    await grip.waitFor({ state: 'visible', timeout: 5000 });
    const gripBox = await grip.boundingBox();
    if (!gripBox) throw new Error(`Endpoint grip (${endpoint}) for edge ${edgeId} not found`);

    const dest = this.getNode(destinationNodeId);
    const destBox = await dest.boundingBox();
    if (!destBox) throw new Error(`Destination node ${destinationNodeId} not found`);

    await this.page.mouse.move(gripBox.x + gripBox.width / 2, gripBox.y + gripBox.height / 2);
    await this.page.mouse.down();
    await this.page.mouse.move(destBox.x + destBox.width / 2, destBox.y + destBox.height / 2, { steps: 8 });
    // Intentionally NOT releasing — the caller asserts the preview, then ends.
  }

  /** Release the mouse to finish a paused drag started by startEndpointDrag(). */
  async endDrag() {
    await this.page.mouse.up();
  }

  /** Locator for the live reconnect preview line drawn during an endpoint drag. */
  get reconnectPreview(): Locator {
    return this.page.locator('.edge-reconnect-preview');
  }

  /**
   * Drag a selected edge's endpoint grip onto empty canvas (should snap back).
   * Drops at an offset that is guaranteed to be outside every node.
   */
  async dragEndpointToEmptyCanvas(edgeId: string, endpoint: 'source' | 'target') {
    await this.selectEdge(edgeId);
    const grip = this.page.locator(`.edge-endpoint-grip--${endpoint}`).first();
    await grip.waitFor({ state: 'visible', timeout: 5000 });
    const gripBox = await grip.boundingBox();
    if (!gripBox) throw new Error(`Endpoint grip (${endpoint}) for edge ${edgeId} not found`);

    const canvasBox = await this.canvas.boundingBox();
    if (!canvasBox) throw new Error('Canvas not found');

    await this.page.mouse.move(gripBox.x + gripBox.width / 2, gripBox.y + gripBox.height / 2);
    await this.page.mouse.down();
    // Drop near the bottom-right corner of the canvas — clear of all nodes.
    await this.page.mouse.move(canvasBox.x + canvasBox.width - 10, canvasBox.y + canvasBox.height - 10, { steps: 8 });
    await this.page.mouse.up();
  }

  /**
   * Trigger a save and return the parsed POST payload sent to the backend.
   * Used to assert top-level edge source/target after a reconnection.
   */
  async saveAndGetPayload(): Promise<Record<string, unknown>> {
    const saveRequestPromise = this.page.waitForRequest(
      (request) => request.url().includes('modeler-api/model') && request.method() === 'POST',
    );
    await this.saveButton.click();
    const saveRequest = await saveRequestPromise;
    return JSON.parse(saveRequest.postData() || '{}');
  }

  /**
   * Wait for a node to appear on the canvas.
   */
  async waitForNode(nodeId: string) {
    await this.getNode(nodeId).waitFor({ state: 'visible' });
  }

  /**
   * Check if a node exists on the canvas.
   */
  async nodeExists(nodeId: string): Promise<boolean> {
    return await this.getNode(nodeId).isVisible();
  }

  /**
   * Get the property panel title (selected element type).
   */
  async getPropertyPanelTitle(): Promise<string | null> {
    const title = this.propertyPanel.locator('.component-type');
    return await title.textContent();
  }

  /**
   * Enter a value in a property field.
   */
  async setPropertyValue(fieldName: string, value: string) {
    const field = this.propertyPanel.locator(`[name="${fieldName}"]`);
    await field.fill(value);
  }

  /**
   * Zoom the canvas.
   */
  async zoom(delta: number) {
    await this.canvas.click();
    await this.page.mouse.wheel(0, delta);
  }

  /**
   * Fit the view to show all nodes using the Canvas Toolbar's View dropdown.
   */
  async fitView() {
    const viewButton = this.page.locator('.canvas-toolbar-view-trigger');
    await viewButton.click();
    const fitViewItem = this.page.locator('.canvas-toolbar-dropdown-item:has(.canvas-toolbar-dropdown-label:text("Fit View"))');
    await fitViewItem.click();
    // Wait for the viewport animation to complete (500ms duration in CanvasToolbar).
    await this.page.waitForTimeout(600);
  }

  /**
   * Enter Review view for the selected event node (revised entry, issue
   * project/modeler#3576269). Clicking "Review flow" auto-starts the live
   * listener AND loads historical replay data, so this is the single entry
   * point — there is no separate "Load replay data" button anymore.
   */
  async loadReplayData() {
    await this.enterReviewMode();
  }

  /**
   * The "Review flow" (go-to-replay) button shown in the Properties header.
   * Its filled-button class is distinct from the review-mode text-style
   * "Back" control.
   */
  getReviewModeButton(): Locator {
    return this.propertyPanel.locator('.header-review-btn[aria-label="Review flow"]');
  }

  /**
   * The review-mode text-style back control (left arrow + "Back") - since
   * #3589103 the only control in the Review header. Clicking it returns to
   * Properties.
   */
  getBackToPropertiesButton(): Locator {
    return this.propertyPanel.locator('.panel-header-back[aria-label="Back to properties"]');
  }

  /**
   * Backward-compatible alias. The old "Load replay data" refresh button was
   * replaced by the "Review flow" entry button; existing specs that referenced
   * the load button now resolve to it.
   */
  getReplayLoadButton(): Locator {
    return this.getReviewModeButton();
  }

  /**
   * Enter the Review view by clicking the header "Review flow" button. Doing so
   * starts (or, if a session is active, resumes) the Replay view. Best-effort:
   * only clicks when the button is present (an event node is selected and review
   * is available, or a session is already active).
   */
  async enterReviewMode() {
    const reviewBtn = this.getReviewModeButton();
    if (await reviewBtn.count() === 0) return;
    await reviewBtn.click();
    await this.page.waitForTimeout(200);
  }

  /**
   * Get the replay entry selector toggle button.
   */
  getReplayEntryToggle(): Locator {
    return this.replayPanel.locator('.replay-entry-toggle');
  }

  /**
   * Open the replay entry dropdown.
   */
  async openReplayEntryDropdown() {
    const toggle = this.getReplayEntryToggle();
    await toggle.click();
    await this.page.waitForSelector('.replay-entry-list', { state: 'visible', timeout: 5000 });
  }

  /**
   * Get the DATA replay entry items in the dropdown (one per execution).
   *
   * Unified panel (#3576269): the dropdown now has a persistent "listen" item
   * (`.replay-listen-item`) at the top which ALSO carries the `.replay-entry-item`
   * class. That item is the Test affordance, not an execution entry, so it is
   * excluded here — callers count/index real executions only. Use
   * `getReplayListenItem()` / `getTestButton()` for the listen item.
   */
  getReplayEntryItems(): Locator {
    return this.replayPanel.locator('.replay-entry-item:not(.replay-listen-item)');
  }

  /**
   * Get the persistent "listen for the event" item at the top of the dropdown.
   * Alias of getTestButton() for readability at call sites concerned with the
   * dropdown structure rather than the Test semantics.
   */
  getReplayListenItem(): Locator {
    return this.replayPanel.locator('.replay-listen-item');
  }

  /**
   * Select a replay entry by index from the dropdown.
   */
  async selectReplayEntry(index: number) {
    await this.openReplayEntryDropdown();
    const items = this.getReplayEntryItems();
    await items.nth(index).click();
    // Wait for dropdown to close
    await this.page.waitForTimeout(200);
  }

  /**
   * Get replay steps in the replay panel.
   */
  getReplaySteps(): Locator {
    return this.replayPanel.locator('.replay-step');
  }

  /**
   * Click the play/pause button in replay controls.
   */
  async startReplay() {
    const playBtn = this.replayPanel.locator('button[aria-label="Play"]');
    await playBtn.click();
  }

  /**
   * Pause replay.
   */
  async pauseReplay() {
    const pauseBtn = this.replayPanel.locator('button[aria-label="Pause"]');
    await pauseBtn.click();
  }

  /**
   * Click the stop button in replay controls.
   */
  async stopReplay() {
    const stopBtn = this.replayPanel.locator('button[aria-label="Stop & Reset"]');
    await stopBtn.click();
  }

  /**
   * Click the next step button in replay controls.
   */
  async nextReplayStep() {
    const nextBtn = this.replayPanel.locator('button[aria-label="Next Step"]');
    await nextBtn.click();
  }

  /**
   * Click the previous step button in replay controls.
   */
  async previousReplayStep() {
    const prevBtn = this.replayPanel.locator('button[aria-label="Previous Step"]');
    await prevBtn.click();
  }

  /**
   * Get the progress label text (e.g. "Step 1 of 2" or "Ready").
   */
  getProgressLabel(): Locator {
    return this.replayPanel.locator('.progress-label');
  }

  /**
   * Get the speed control select element.
   */
  getSpeedControl(): Locator {
    return this.replayPanel.locator('select[aria-label="Playback Speed"]');
  }

  /**
   * Check if a node is highlighted during replay.
   */
  async isNodeHighlighted(nodeId: string): Promise<boolean> {
    const node = this.getNode(nodeId);
    const classList = await node.getAttribute('class');
    return classList?.includes('replay-highlighted') ?? false;
  }

  // ============== Test Affordance Methods ==============
  //
  // Unified-panel refactor (issue project/modeler#3576269): the standalone
  // "Execution Replay" header bar — and with it the dedicated `.header-test-btn`
  // — was REMOVED. There is NO standalone Test button at page load anymore.
  // A test run is now initiated implicitly from WITHIN the Review flow: the user
  // enters Review mode (the "Review flow" header button) and picks the
  // persistent "Listen to event to happen" item at the top of the execution
  // replay dropdown (`.replay-listen-item`). Selecting it fires `onStartTest`
  // in Flow.tsx, which starts the single live listener. The waiting/spinner
  // state then renders as `.replay-test-waiting`.
  //
  // The "Test affordance" therefore = the listen item inside the entry dropdown,
  // and it is GATED behind the same capability/permission as the Review button
  // (no review capability ⇒ no Review button ⇒ no way to reach the listen item).

  /**
   * The Test affordance in the unified panel: the persistent "listen" item at
   * the top of the execution-replay dropdown. Reaching it requires being in
   * Review mode (open the entry dropdown first via `openReplayEntryDropdown()`).
   *
   * Returns a locator for the listen item. It only exists in the DOM while the
   * entry dropdown is open AND review capability is present.
   */
  getTestButton(): Locator {
    return this.replayPanel.locator('.replay-listen-item');
  }

  /**
   * Whether the Test affordance is reachable at all: i.e. the "Review flow"
   * button is present (capability + permission), which is the single gate that
   * controls access to the listen-to-test item. When this is false, there is no
   * way for the user to start a test — the security contract for `test`/`replay`
   * permission denial.
   */
  getTestAffordanceGate(): Locator {
    return this.getReviewModeButton();
  }

  /**
   * Start a test run via the unified flow. Selects the (single) event node so
   * the "Review flow" affordance appears, then enters Review mode. When a
   * test_url is configured, entering Review for an event AUTO-STARTS the single
   * live listener (Flow.enterReviewForNode → startTest) and auto-selects the
   * "listen" item, so the waiting state appears without any further click.
   * Best-effort: only proceeds when the Review affordance is present.
   *
   * @param eventNodeId The event node to review (defaults to the standard
   *   single event node in the mock model).
   */
  async startTest(eventNodeId = 'event_1') {
    await this.selectNode(eventNodeId);
    await this.page.waitForTimeout(300);
    const gate = this.getReviewModeButton();
    if (await gate.count() === 0) return;
    await this.enterReviewMode();
  }

  /**
   * Re-arm the listener from WITHIN Review mode by explicitly selecting the
   * persistent "listen" item in the open execution-replay dropdown. Assumes the
   * panel is already in Review mode.
   */
  async startTestViaListenItem() {
    await this.openReplayEntryDropdown();
    await this.getTestButton().click();
  }

  /**
   * Get the test waiting state container (shown during polling).
   */
  getTestWaitingState(): Locator {
    return this.replayPanel.locator('.replay-test-waiting');
  }

  /**
   * Get the cancel button shown during test polling.
   */
  getTestCancelButton(): Locator {
    return this.replayPanel.locator('.replay-test-waiting button');
  }

  /**
   * Cancel a running test by clicking the cancel button.
   */
  async cancelTest() {
    const cancelBtn = this.getTestCancelButton();
    await cancelBtn.click();
  }

  /**
   * Get the empty state message in the replay panel.
   */
  getReplayEmptyState(): Locator {
    return this.replayPanel.locator('.empty-state');
  }

  /**
   * Get the replay panel collapse/expand toggle button.
   */
  getReplayPanelToggle(): Locator {
    return this.replayPanel.locator('.collapse-toggle, .panel-collapse-widget');
  }

  // ============== Quick Add Methods ==============

  /**
   * Get the quick-add button for a node.
   */
  getQuickAddButton(nodeId: string): Locator {
    const node = this.getNode(nodeId);
    return node.locator('.quick-add-button');
  }

  /**
   * Get the quick-add edge button for a specific edge.
   *
   * Every edge is a plain default edge now (issue #3589093); the button is
   * rendered by QuickAddEdgeButton inside DefaultEdge's EdgeLabelRenderer
   * portal, positioned at the edge midpoint.  Selecting a condition from its
   * popup INSERTS a condition node on the edge; selecting an action/gateway
   * inserts that node instead.  The button keeps the historical CSS class
   * "quick-add-condition-button" and carries a data-edge-id attribute.
   */
  getQuickAddConditionButton(edgeId: string): Locator {
    // Use data-edge-id to target the specific edge's button.
    return this.page.locator(`.quick-add-condition-button[data-edge-id="${edgeId}"]`).first();
  }

  /**
   * Click the quick-add button on a node to open the popup.
   */
  async openQuickAddPopup(nodeId: string) {
    const quickAddBtn = this.getQuickAddButton(nodeId);
    await quickAddBtn.click();
    // Wait for popup to appear
    await this.page.waitForSelector('.quick-add-popup', { state: 'visible', timeout: 5000 });
  }

  /**
   * Click the quick-add condition button on an edge to open the popup.
   */
  async openQuickAddConditionPopup(edgeId: string) {
    // The quick-add-condition-button is rendered via EdgeLabelRenderer portal
    // Find it by title and click directly (it may have low opacity but should be clickable)
    const quickAddBtn = this.getQuickAddConditionButton(edgeId);
    
    // Hover to make button fully visible (it has opacity transition on hover)
    await quickAddBtn.hover({ force: true });
    await this.page.waitForTimeout(200);
    
    await quickAddBtn.click();
    // Wait for popup to appear
    await this.page.waitForSelector('.quick-add-popup', { state: 'visible', timeout: 5000 });
  }

  /**
   * Get the quick-add popup.
   */
  getQuickAddPopup(): Locator {
    return this.page.locator('.quick-add-popup');
  }

  /**
   * Get a component in the quick-add popup by label.
   */
  getQuickAddComponent(label: string): Locator {
    return this.getQuickAddPopup().locator(`.quick-add-component-item:has-text("${label}")`);
  }

  /**
   * Search for components in the quick-add popup.
   */
  async searchQuickAddComponents(query: string) {
    const searchInput = this.getQuickAddPopup().locator('input[type="text"]');
    await searchInput.fill(query);
    await this.page.waitForTimeout(250); // Wait for debounce
  }

  /**
   * Select a component from the quick-add popup by clicking it.
   */
  async selectQuickAddComponent(label: string) {
    const component = this.getQuickAddComponent(label);
    await component.click();
    // Wait for popup to close
    await this.page.waitForSelector('.quick-add-popup', { state: 'hidden', timeout: 5000 });
  }

  /**
   * Close the quick-add popup by clicking outside.
   */
  async closeQuickAddPopup() {
    await this.canvas.click({ position: { x: 10, y: 10 } });
    await this.page.waitForSelector('.quick-add-popup', { state: 'hidden', timeout: 5000 });
  }

  /**
   * Get the section headers in the quick-add popup.
   */
  getQuickAddSectionHeaders(): Locator {
    return this.getQuickAddPopup().locator('.quick-add-section-header');
  }

  /**
   * Close the quick-add popup using the close button.
   */
  async closeQuickAddPopupWithButton() {
    const closeBtn = this.getQuickAddPopup().locator('.quick-add-popup-close');
    await closeBtn.click();
    await this.page.waitForSelector('.quick-add-popup', { state: 'hidden', timeout: 5000 });
  }

  // ============== Metadata Modal Methods ==============

  /**
   * Get the metadata modal element.
   */
  getMetadataModal(): Locator {
    return this.page.locator('.metadata-modal');
  }

  /**
   * Get a control inside the metadata modal by its form key.
   *
   * Native controls (select, machine name, the YAML editor's textarea) carry
   * the `config-field-KEY` id. The text widgets are contenteditable divs,
   * which a `<label for>` can never reach, so they are named by their label
   * through aria-labelledby instead.
   */
  getMetadataField(fieldKey: string): Locator {
    return this.getMetadataModal().locator(
      `#config-field-${fieldKey}, [aria-labelledby="config-field-${fieldKey}-label"]`,
    );
  }

  /**
   * Whether a metadata control can be edited.
   *
   * The two widget kinds say so differently: a contenteditable div flips its
   * `contenteditable` attribute, a native control gets `disabled`.
   */
  async isMetadataFieldEditable(fieldKey: string): Promise<boolean> {
    const field = this.getMetadataField(fieldKey);
    const contentEditable = await field.getAttribute('contenteditable');
    if (contentEditable !== null) {
      return contentEditable === 'true';
    }
    return await field.isEnabled();
  }

  /**
   * Expand a collapsed group (`details`) in the metadata modal by its title.
   */
  async expandMetadataGroup(title: string): Promise<void> {
    const details = this.getMetadataModal().locator(`details:has(> summary:text-is("${title}"))`);
    await details.waitFor({ state: 'visible', timeout: 2000 });
    if (await details.evaluate((el) => el.hasAttribute('open'))) {
      return;
    }
    await details.locator('summary').first().click();
    await details.locator('.form-group-body').first().waitFor({ state: 'visible', timeout: 2000 });
  }

  /**
   * Get the template checkbox in the metadata modal, by its label.
   */
  getTemplateCheckbox(): Locator {
    return this.getMetadataModal()
      .locator('label.checkbox-wrapper', { hasText: 'Template' })
      .locator('input[type="checkbox"]');
  }

  /**
   * Get the metadata modal Save button.
   */
  getMetadataSaveButton(): Locator {
    return this.getMetadataModal().locator('button[type="submit"]');
  }

  // ============== Context Switcher Methods ==============

  /**
   * Get the context select dropdown in the toolbar.
   */
  getContextSelect(): Locator {
    return this.page.locator('#toolbar-context-select');
  }

  // ============== Quick Add Event Methods ==============

  /**
   * Get the "New event" toolbar button for adding event/start nodes.
   */
  getQuickAddEventButton(): Locator {
    return this.page.locator('.quick-add-event-button');
  }

  /**
   * Click the "New event" toolbar button to open the event popup.
   */
  async openQuickAddEventPopup() {
    const quickAddBtn = this.getQuickAddEventButton();
    await quickAddBtn.click();
    // Wait for popup to appear
    await this.page.waitForSelector('.quick-add-popup', { state: 'visible', timeout: 5000 });
  }

  /**
   * Select an event from the quick-add event popup by clicking it.
   */
  async selectQuickAddEvent(label: string) {
    const component = this.getQuickAddComponent(label);
    await component.click();
    // Wait for popup to close
    await this.page.waitForSelector('.quick-add-popup', { state: 'hidden', timeout: 5000 });
  }

  // ---------------------------------------------------------------------------
  // Export
  // ---------------------------------------------------------------------------

  /**
   * Get the export menu item from the kebab menu.
   * Note: the kebab menu must be opened first for the item to be visible.
   */
  getExportButton(): Locator {
    return this.exportButton;
  }

  /**
   * Open the kebab menu so menu items become visible.
   */
  async openKebabMenu() {
    await this.kebabMenuTrigger.click();
    // Wait for the dropdown to appear
    await this.page.locator('.toolbar-menu-dropdown').waitFor({ state: 'visible', timeout: 2000 });
  }

  /**
   * Open the export dialog via the kebab menu.
   */
  async openExportDialog() {
    await this.kebabMenuTrigger.click();
    await this.exportButton.waitFor({ state: 'visible', timeout: 2000 });
    await this.exportButton.click();
    await this.page.waitForSelector('.export-dialog', { state: 'visible', timeout: 5000 });
  }

  /**
   * Get the export dialog element.
   */
  getExportDialog(): Locator {
    return this.page.locator('.export-dialog');
  }

  /**
   * Select an export format in the export dialog.
   */
  async selectExportFormat(format: 'recipe' | 'archive' | 'json' | 'svg') {
    const labels: Record<string, string> = {
      recipe: 'Recipe',
      archive: 'Archive',
      json: 'JSON',
      svg: 'SVG',
    };
    const option = this.page.locator(`.export-format-option:has(.export-format-label:text("${labels[format]}"))`);
    await option.click();
  }

  /**
   * Click the Export button inside the export dialog.
   */
  async confirmExport() {
    const exportBtn = this.page.locator('.export-dialog-footer .btn-primary');
    await exportBtn.click();
  }

  /**
   * Close the export dialog via the Cancel button.
   */
  async cancelExport() {
    const cancelBtn = this.page.locator('.export-dialog-footer .btn-secondary');
    await cancelBtn.click();
    await this.page.waitForSelector('.export-dialog', { state: 'hidden', timeout: 5000 });
  }

  // ============== Condition Node Methods ==============
  //
  // Conditions are first-class nodes now (issue #3589093): a condition is a
  // ReactFlow node (type 'condition', data.__isConditionNode === true,
  // componentType 5) rendered by ConditionNode.tsx as a card with the class
  // `.condition-node`.  These helpers target conditions as nodes.

  /**
   * Get all condition node cards on the canvas (`.condition-node`).
   */
  getConditionNodes(): Locator {
    return this.page.locator('.react-flow__node .condition-node');
  }

  /**
   * Get the currently selected condition node card on the canvas.
   *
   * React Flow adds the `.selected` class to the `.react-flow__node` wrapper
   * for a node whose `selected` flag is true.  Inserting a condition on an edge
   * auto-selects the newly created condition node (see `handleAddCondition`),
   * so this targets that freshly inserted node specifically — distinct from any
   * pre-existing (e.g. promoted) condition node that shares the same label.
   */
  getSelectedConditionNode(): Locator {
    return this.page.locator('.react-flow__node.selected .condition-node');
  }

  /**
   * Get the React Flow node wrapper for a specific condition node id.
   * The condition node id scheme is `cond__<originalEdgeId>` for a
   * non-grouped condition and `cond__shared__<conditionId>__<target>` for a
   * reused (grouped) condition — see utils/modelUtils.ts.
   */
  getConditionNode(nodeId: string): Locator {
    return this.getNode(nodeId);
  }

  /**
   * Count the condition node cards currently rendered on the canvas.
   */
  async getConditionNodeCount(): Promise<number> {
    return await this.getConditionNodes().count();
  }

  /**
   * Whether a given node is a condition node (renders a `.condition-node` card).
   */
  async isConditionNode(nodeId: string): Promise<boolean> {
    return await this.getNode(nodeId).locator('.condition-node').count() > 0;
  }

  /**
   * Select a condition node by id (opens the node properties panel).
   */
  async selectConditionNode(nodeId: string) {
    await this.getNode(nodeId).click();
  }

  /**
   * Whether a node's source handle is rendered in the disabled state
   * (class `node-handle--disabled`).  Used to assert the condition
   * 1-outbound rule (issue #3589093).
   */
  async isNodeSourceHandleDisabled(nodeId: string): Promise<boolean> {
    return await this.getNode(nodeId).locator('.node-handle--disabled').count() > 0;
  }

  /**
   * Read the native `title` tooltip from a node's disabled source handle.
   * Returns null when the handle has no title.
   */
  async getNodeSourceHandleTitle(nodeId: string): Promise<string | null> {
    const handle = this.getNode(nodeId).locator('.node-handle--disabled').first();
    if (await handle.count() === 0) return null;
    return await handle.getAttribute('title');
  }

  /**
   * Get the node label input in the property panel (used to edit a selected
   * node's label — including a condition node's label, issue #3589093).
   */
  getNodeLabelInput(): Locator {
    return this.propertyPanel.locator('#modeler-component-label');
  }
}
