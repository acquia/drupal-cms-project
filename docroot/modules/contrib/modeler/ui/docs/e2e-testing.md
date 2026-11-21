# End-to-End Testing

Comprehensive E2E tests using Playwright for real user interaction testing.

## E2E Test Structure

### Test Organization
```
tests/e2e/
├── fixtures/
│   └── mocks.ts              # Mock API data and route handlers
├── pages/
│   └── ModelerPage.ts        # Page Object for the modeler
├── modeler.spec.ts           # Core functionality tests
├── label-editing.spec.ts     # Label editing tests
├── property-panel.spec.ts    # Property panel tests
├── quick-add.spec.ts         # Quick add feature tests
├── replay.spec.ts            # Replay system tests
├── accessibility.spec.ts     # Accessibility E2E tests
├── test-server.ts            # Standalone test server
└── global-setup.ts           # Pre-test setup verification
```

## Running Tests

### Development Commands
```bash
# Run all E2E tests
npm run e2e

# Interactive UI mode (recommended for development)
npm run e2e:ui

# Headed browsers (visible)
npm run e2e:headed

# Debug mode with Playwright Inspector
npm run e2e:debug

# Generate tests with codegen
npm run e2e:codegen http://localhost:3000

# View test report
npm run e2e:report
```

### Browser Coverage
Tests run on all major browsers:
- **Chromium** (Chrome/Edge)
- **Firefox**
- **WebKit** (Safari)

## Mock System

### Mock Setup
```typescript
// In your test file or mocks.ts
import { setupMocks } from './fixtures/mocks';

test.beforeEach(async ({ page }) => {
  await setupMocks(page);
  // ... test code
});
```

### Available Mocks
| Endpoint | Description |
|----------|-------------|
| `/modeler-api/components` | Component library (events, actions, conditions, gateways) |
| `/modeler-api/model/**` | Model load/save operations |
| `/modeler-api/tokens` | Token browser data |
| `/modeler-api/config/**` | Configuration forms |
| `/modeler-api/replay` | Replay execution entries (POST, returns ReplayEntry[]) |
| `/modeler-api/test` | Test endpoint (POST, initiate + poll for results; requires `withTestUrl` option) |

### Mock Data Structure
```typescript
// Mock API responses in fixtures/mocks.ts
export const mockComponents = {
  1: [ // Events
    {
      plugin: 'entity:entity_create',
      label: 'Create Entity',
      description: 'Triggers when an entity is created',
      documentationUrl: 'https://docs.example.com/create-entity'
    }
  ],
  4: [ // Actions
    {
      plugin: 'entity:entity_save',
      label: 'Save Entity',
      description: 'Saves an entity to the database'
    }
  ],
  5: [ // Conditions
    {
      plugin: 'entity:entity_is_new',
      label: 'Entity is New',
      description: 'Checks if entity is newly created'
    }
  ]
};

export const mockModel = {
  nodes: [
    {
      id: 'event_1',
      type: 'start',
      position: { x: 100, y: 100 },
      data: { label: 'Start Event', plugin: 'entity:entity_create' }
    },
    {
      id: 'action_1', 
      type: 'element',
      position: { x: 300, y: 100 },
      data: { label: 'Save Action', plugin: 'entity:entity_save' }
    }
  ],
  edges: [
    {
      id: 'edge_1',
      source: 'event_1',
      target: 'action_1',
      data: { condition: 'entity:entity_is_new' }
    }
  ]
};
```

## Page Object Pattern

### ModelerPage Class
```typescript
// pages/ModelerPage.ts
export class ModelerPage {
  constructor(private page: Page) {}

  // Navigation
  async goto(modelId?: string) {
    const url = modelId ? `/modeler/${modelId}` : '/modeler';
    await this.page.goto(url);
  }

  async waitForLoad() {
    await this.page.waitForSelector('[data-testid="flow-canvas"]');
    await this.page.waitForLoadState('networkidle');
  }

  // Node operations
  async selectNode(nodeId: string) {
    await this.page.click(`[data-node-id="${nodeId}"]`);
  }

  async selectMultipleNodes(nodeIds: string[]) {
    // First node selects normally
    await this.selectNode(nodeIds[0]);
    
    // Additional nodes with Shift+click
    for (let i = 1; i < nodeIds.length; i++) {
      await this.page.keyboard.down('Shift');
      await this.page.click(`[data-node-id="${nodeIds[i]}"]`);
      await this.page.keyboard.up('Shift');
    }
  }

  async moveNode(nodeId: string, x: number, y: number) {
    const node = await this.page.locator(`[data-node-id="${nodeId}"]`);
    await node.dragTo(this.page.locator('.react-flow__pane'), {
      sourcePosition: { x: 0, y: 0 },
      targetPosition: { x, y }
    });
  }

  async connectNodes(sourceId: string, targetId: string) {
    const source = await this.page.locator(`[data-node-id="${sourceId}"] .output-handle`);
    const target = await this.page.locator(`[data-node-id="${targetId}"] .input-handle`);
    
    await source.dragTo(target);
  }

  // Drag and drop
  async dragComponentToCanvas(componentLabel: string, x: number, y: number) {
    const component = await this.page.locator(`[data-component-label="${componentLabel}"]`);
    const canvas = await this.page.locator('.react-flow__pane');
    
    await component.dragTo(canvas, {
      sourcePosition: { x: 0, y: 0 },
      targetPosition: { x, y }
    });
  }

  // Keyboard shortcuts
  async deleteSelected() {
    await this.page.keyboard.press('Delete');
  }

  async copySelected() {
    const isMac = process.platform === 'darwin';
    await this.page.keyboard.press(isMac ? 'Meta+C' : 'Control+C');
  }

  async paste() {
    const isMac = process.platform === 'darwin';
    await this.page.keyboard.press(isMac ? 'Meta+V' : 'Control+V');
  }

  async openSearch() {
    const isMac = process.platform === 'darwin';
    await this.page.keyboard.press(isMac ? 'Meta+F' : 'Control+F');
  }

  // Search interactions
  async searchComponents(searchTerm: string) {
    const searchInput = await this.page.locator('[data-testid="component-search"]');
    await searchInput.fill(searchTerm);
  }

  async expandCategory(categoryName: string) {
    await this.page.click(`[data-category="${categoryName}"]`);
  }

  // Toolbar interactions
  async autoLayout() {
    await this.page.click('[data-testid="auto-layout-button"]');
  }

  async openSettings() {
    await this.page.click('[data-testid="settings-button"]');
  }

  async closeModal() {
    await this.page.click('[data-testid="modal-close"]');
  }

  // Replay interactions
  async loadReplayData() {
    // Click replay load button on event node
    await this.page.click('[data-testid="replay-load-button"]');
  }

  async startReplay() {
    await this.page.click('[data-testid="replay-play-button"]');
  }

  async pauseReplay() {
    await this.page.click('[data-testid="replay-pause-button"]');
  }

  async stopReplay() {
    await this.page.click('[data-testid="replay-stop-button"]');
  }

  async nextReplayStep() {
    await this.page.click('[data-testid="replay-next-button"]');
  }

  async previousReplayStep() {
    await this.page.click('[data-testid="replay-previous-button"]');
  }

  // Canvas interactions
  async zoom(amount: number) {
    const canvas = await this.page.locator('.react-flow__pane');
    await canvas.hover();
    await this.page.mouse.wheel(0, amount);
  }

  async fitView() {
    await this.page.click('[data-testid="fit-view-button"]');
  }

  // Getters for assertions
  async getNodeCount(): Promise<number> {
    const nodes = await this.page.locator('[data-node-id]').all();
    return nodes.length;
  }

  async getEdgeCount(): Promise<number> {
    const edges = await this.page.locator('.react-flow__edge').all();
    return edges.length;
  }

  async getSelectedNodeIds(): Promise<string[]> {
    const selectedNodes = await this.page.locator('[data-node-id].selected').all();
    return selectedNodes.map(async node => 
      await node.getAttribute('data-node-id')
    );
  }

  async isReplayPlaying(): Promise<boolean> {
    const playButton = await this.page.locator('[data-testid="replay-play-button"]');
    const pauseButton = await this.page.locator('[data-testid="replay-pause-button"]');
    
    return await pauseButton.isVisible();
  }
}
```

## Test Writing Patterns

### Basic Test Structure
```typescript
import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

test.describe('Feature Name', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
    await modeler.waitForLoad();
  });

  test('should perform basic functionality', async ({ page }) => {
    // Arrange
    await modeler.selectNode('event_1');

    // Act
    await modeler.copySelected();
    await modeler.paste();

    // Assert
    const nodeCount = await modeler.getNodeCount();
    expect(nodeCount).toBeGreaterThan(1);
  });
});
```

### Advanced Test Patterns
```typescript
test('complex workflow scenario', async ({ page }) => {
  // Arrange: Create initial workflow
  await modeler.dragComponentToCanvas('Create Entity', 100, 100);
  await modeler.dragComponentToCanvas('Save Entity', 300, 100);
  await modeler.connectNodes('event_1', 'action_1');

  // Act: Configure and test
  await modeler.selectNode('event_1');
  await page.click('[data-testid="configure-button"]');
  await page.fill('[data-testid="entity-type-input"]', 'user');
  await page.click('[data-testid="save-config-button"]');

  // Assert: Verify configuration saved
  await expect(page.locator('[data-testid="success-message"]')).toBeVisible();
});
```

### Accessibility Testing
```typescript
test.describe('Accessibility', () => {
  test('keyboard navigation works', async ({ page }) => {
    await modeler.goto();
    
    // Tab through interface
    await page.keyboard.press('Tab');
    await expect(page.locator(':focus')).toBeVisible();
    
    // Use Enter to activate
    await page.keyboard.press('Enter');
    
    // Verify interaction
    await expect(page.locator('[data-testid="expected-result"]')).toBeVisible();
  });

  test('screen reader announcements work', async ({ page }) => {
    await modeler.goto();
    
    // Check for aria-live region
    const liveRegion = await page.locator('[aria-live="polite"]');
    await expect(liveRegion).toBeVisible();
    
    // Trigger announcement
    await modeler.selectNode('event_1');
    
    // Verify announcement content
    const announcement = await liveRegion.textContent();
    expect(announcement).toContain('Event node selected');
  });
});
```

## Test Data Management

### Mock Server Configuration
```typescript
// test-server.ts
import express from 'express';
import { mockComponents, mockModel, mockReplayEntries } from './fixtures/mocks';

const app = express();

// Component library endpoint
app.get('/modeler-api/components', (req, res) => {
  res.json(mockComponents);
});

// Model save endpoint
app.post('/modeler-api/save', (req, res) => {
  // Validate CSRF token
  const token = req.headers['x-csrf-token'];
  if (token !== 'mock-csrf-token') {
    return res.status(403).json({ error: 'Invalid CSRF token' });
  }
  
  // Return success
  res.json({ success: true });
});

// Replay endpoint
app.post('/modeler-api/replay', (req, res) => {
  const { modelId, componentId } = req.body;
  
  // Return mock replay data
  res.json(mockReplayEntries);
});

// CSRF token endpoint
app.get('/session/token', (req, res) => {
  res.type('text/plain').send('mock-csrf-token');
});

export default app;
```

### Custom Route Handlers
```typescript
// Adding custom mocks in tests
test.beforeEach(async ({ page }) => {
  await page.route('**/custom-endpoint/**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ custom: 'data' })
    });
  });
});
```

## Visual Testing

### Screenshots
```typescript
test('visual regression', async ({ page }) => {
  await modeler.goto();
  await modeler.waitForLoad();
  
  // Take screenshot
  await expect(page).toHaveScreenshot('modeler-initial.png');
});

test('component visual states', async ({ page }) => {
  await modeler.goto();
  
  // Test different component states
  const component = await page.locator('[data-testid="my-component"]');
  
  await component.hover();
  await expect(page).toHaveScreenshot('component-hover.png');
  
  await component.click();
  await expect(page).toHaveScreenshot('component-active.png');
});
```

### Viewport Testing
```typescript
test('responsive design', async ({ page }) => {
  // Test different screen sizes
  await page.setViewportSize({ width: 1920, height: 1080 });
  await modeler.goto();
  await expect(page).toHaveScreenshot('desktop.png');
  
  await page.setViewportSize({ width: 768, height: 1024 });
  await expect(page).toHaveScreenshot('tablet.png');
  
  await page.setViewportSize({ width: 375, height: 667 });
  await expect(page).toHaveScreenshot('mobile.png');
});
```

## CI Integration

### GitLab CI Configuration
```yaml
reactapp e2e:
  stage: test
  image: node:20-bookworm
  variables:
    GIT_STRATEGY: fetch
    GIT_CHECKOUT: "true"
  needs:
    - reactapp build
  script:
    - cd ui
    - npx playwright install --with-deps chromium
    - npm run e2e
  artifacts:
    when: always
    paths:
      - ui/tests/playwright-report/
      - ui/tests/test-results/
```

### Test Reporting
```typescript
// playwright.config.ts
export default defineConfig({
  reporter: [
    ['html', { outputFolder: 'playwright-report' }],
    ['json', { outputFile: 'test-results.json' }],
    ['junit', { outputFile: 'test-results.xml' }]
  ],
  use: {
    // Configure test options
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure'
  }
});
```

## Debugging Techniques

### Playwright Inspector
```bash
# Debug with inspector
npm run e2e:debug

# In test code
await page.pause(); // Pause execution
await page.locator('.element').highlight(); // Highlight element
```

### Browser DevTools
```bash
# Run with headed browser to see DevTools
npm run e2e:headed

# Slow down execution for debugging
await page.waitForTimeout(2000); // 2 second delay
```

### Logging and Inspection
```typescript
// Log page state
console.log(await page.content()); // Page HTML
console.log(await page.locator('.selector').textContent()); // Element text

// Inspect network requests
page.on('request', request => console.log(request.url()));
page.on('response', response => console.log(response.status()));
```

## E2E Testing Guidelines

### Test Quality
- [ ] Tests cover critical user journeys
- [ ] Each test is independent (no state leakage)
- [ ] Page Object Pattern used for maintainability
- [ ] Proper waits and assertions
- [ ] Cross-browser compatibility
- [ ] Visual consistency checks

### Performance Testing
- [ ] Measure load times for large models
- [ ] Test memory usage with many components
- [ ] Verify smooth animations and transitions
- [ ] Check responsiveness with complex workflows

### Accessibility Testing
- [ ] Full keyboard navigation support
- [ ] Screen reader compatibility
- [ ] ARIA attributes are correct
- [ ] Color contrast compliance
- [ ] Focus management in modals



## Test Structure

```
tests/e2e/
├── fixtures/
│   └── mocks.ts              # Mock API data and route handlers
├── pages/
│   └── ModelerPage.ts        # Page Object for the modeler
├── modeler.spec.ts           # Core functionality tests
├── label-editing.spec.ts     # Label editing tests
├── property-panel.spec.ts    # Property panel tests
├── quick-add.spec.ts         # Quick add feature tests
├── replay.spec.ts            # Replay system tests
├── accessibility.spec.ts     # Accessibility E2E tests
├── test-server.ts            # Standalone test server
└── global-setup.ts           # Pre-test setup verification
.storybook/
└── test-runner.ts            # axe-core a11y test configuration (light + dark mode)
playwright.config.ts          # Playwright configuration
package.json                  # Scripts and dependencies
```

## Running Tests

```bash
# Run all E2E tests
npm run e2e

# Run tests with UI mode (interactive)
npm run e2e:ui

# Run tests in headed browsers (visible)
npm run e2e:headed

# Debug tests with Playwright Inspector
npm run e2e:debug

# View test report
npm run e2e:report

# Generate tests with codegen
npm run e2e:codegen

# Start the test server (for manual testing)
npm run e2e:server
```

## Browser Coverage

Tests run on all major browsers:
- **Chromium** (Chrome/Edge)
- **Firefox**
- **WebKit** (Safari)

## Mock System

Tests use mocked API responses instead of a live Drupal backend:

```typescript
import { setupMocks } from './fixtures/mocks';

test.beforeEach(async ({ page }) => {
  await setupMocks(page);
  // ... test code
});
```

### Available Mocks

| Endpoint | Description |
|----------|-------------|
| `/modeler-api/components` | Component library (events, actions, conditions, gateways) |
| `/modeler-api/model/**` | Model load/save operations |
| `/modeler-api/tokens` | Token browser data |
| `/modeler-api/config/**` | Configuration forms |
| `/modeler-api/replay` | Replay execution entries (POST, returns ReplayEntry[]) |
| `/modeler-api/test` | Test endpoint (POST, initiate + poll for results; requires `withTestUrl` option) |

### Mock Data

- `mockComponents` - Component definitions with fields
- `mockModel` - Sample workflow with 2 nodes and 1 edge
- `mockEmptyModel` - Empty workflow for new model tests
- `mockTokens` - Token definitions for token browser
- `mockReplayEntries` - Replay execution entries (ReplayEntry[] format with model_id, event_id, history, timestamp, user, ip, url)
- `mockTestJobId` - Test job ID returned by initial test request
- `mockTestReplayData` - Replay steps returned after test polling completes

### SetupMocks Options

The `setupMocks(page, options)` function accepts an options bag of type `SetupMocksOptions`:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `isNew` | boolean | `false` | Whether the model is new (triggers metadata modal) |
| `withTestUrl` | boolean | `false` | Inject `test_url` into `drupalSettings.modeler_api` and register test endpoint mock |
| `testPollWaitCount` | number | `1` | Number of poll requests to return `{ status: "waiting" }` before returning replay data |
| `testInitError` | string | — | If set, the initial test request returns `{ error: <value> }` |
| `testInitWarning` | string | — | If set, the initial test request returns `{ jobId, warning: <value> }` |
| `testPollError` | string | — | If set, poll requests return `{ error: <value> }` |

**Note on `withTestUrl`**: The test server does NOT include `test_url` in `drupalSettings` by default. When `withTestUrl: true` is passed, `setupMocks` uses `page.route()` to intercept the HTML document response and inject `test_url: '/modeler-api/test'` via string replacement before the browser parses it. This is necessary because `page.addInitScript()` runs after the server's inline `<script>` tags have already set the settings.

## Page Object Pattern

The `ModelerPage` class provides a clean API for interacting with the modeler:

```typescript
const modeler = new ModelerPage(page);

// Navigation
await modeler.goto('model-id');
await modeler.waitForLoad();

// Node operations
await modeler.selectNode('node-id');
await modeler.selectMultipleNodes(['node-1', 'node-2']);
await modeler.moveNode('node-id', 100, 50);
await modeler.connectNodes('source-id', 'target-id');

// Drag and drop
await modeler.dragComponentToCanvas('Set Message', 300, 200);

// Keyboard shortcuts
await modeler.deleteSelected();
await modeler.copySelected();
await modeler.paste();
await modeler.undo();
await modeler.redo();
await modeler.openSearch();
await modeler.saveModel();

// Toolbar
await modeler.autoLayout();
await modeler.openSettings();
await modeler.closeModal();

// Replay - Loading & Entry Selection
await modeler.loadReplayData();              // Click replay load button on event node
await modeler.getReplayLoadButton();         // Get the replay load button locator
await modeler.getReplayEntryToggle();        // Get the entry dropdown toggle
await modeler.openReplayEntryDropdown();     // Open the entry selector dropdown
await modeler.getReplayEntryItems();         // Get all entry items in dropdown
await modeler.selectReplayEntry(index);      // Select a specific replay entry

// Replay - Playback Controls
await modeler.startReplay();                 // Start auto-playback
await modeler.pauseReplay();                 // Pause auto-playback
await modeler.stopReplay();                  // Stop and exit replay mode
await modeler.nextReplayStep();              // Navigate to next step
await modeler.previousReplayStep();          // Navigate to previous step
await modeler.getProgressLabel();            // Get "Step X of Y" label
await modeler.getSpeedControl();             // Get speed dropdown
await modeler.getReplaySteps();              // Get all replay step elements

// Test Feature
await modeler.getTestButton();               // Get Test button in replay panel header
await modeler.startTest();                   // Click the Test button
await modeler.getTestWaitingState();         // Get test waiting/polling container
await modeler.getTestCancelButton();         // Get cancel button during polling
await modeler.cancelTest();                  // Click cancel to stop a running test
await modeler.getReplayEmptyState();         // Get empty state message in replay panel
await modeler.getReplayPanelToggle();        // Get collapse/expand toggle button

// Canvas
await modeler.zoom(-100);
await modeler.fitView();
const nodeCount = await modeler.getNodeCount();
const edgeCount = await modeler.getEdgeCount();
```

## Test Categories

### Currently Passing (125 tests)

**Core Functionality (`modeler.spec.ts`)**
- Initial load with model data, nodes, and edges
- Node selection (single, multi-select, deselect)
- Node manipulation (delete)

**Drag and Drop**
- Adding new nodes to canvas

**Keyboard Shortcuts**
- Copy/paste selected nodes
- Undo/redo actions
- Open search (Ctrl+F)

**Toolbar Actions**
- Save model
- Open/close settings modal
- Trigger auto-layout

**Label Editing (`label-editing.spec.ts`)**
- Inline label editing on nodes and edges
- Label persistence after editing

**Quick Add (`quick-add.spec.ts`)**
- Quick add button on node hover
- Adding successor nodes via quick add

**Replay System (`replay.spec.ts`)** - 43 tests across 10 categories
- Loading Replay Data (5): Load button visibility, fetching entries, loading state, panel display, entry count
- Replay Entry Selector (7): Dropdown toggle, entry display, entry switching, outside-click close, entry metadata
- Replay Panel UI (8): Step list display, progress label, playback controls, speed selector, stop button
- Replay Step Navigation (7): Next/previous step, first/last step boundaries, step highlighting, step selection
- Replay Playback (4): Auto-play start/pause, speed changes, playback progression
- Inline Step Data (3): Token data rendering, condition results, step metadata
- Replay Panel Info Popup (2): Info popup display, metadata content
- Test Button Visibility (2): Test button shown with test_url + event selected, hidden without test_url
- Test Execution (4): Click test starts polling, waiting state with cancel, successful result display, cancel stops test
- Test Error Handling (2): Init error shown as message, poll error shown as message

**Accessibility (`accessibility.spec.ts`)** - 14 tests across 4 categories
- Keyboard Navigation (4): Tab through toolbar, Ctrl+F search toggle, Escape to close, arrow key navigation in results
- Focus Trapping in Dialogs (3): Focus trapped in modal, Escape closes modal, focus restored after close
- ARIA Attributes (4): aria-live status region, aria-labels on buttons, combobox role on search, dialog ARIA attributes
- Dynamic Interaction Accessibility (3): Accessible state after node selection, category switching, search result announcements

### Skipped (Need More Work)

**Property Panel (`property-panel.spec.ts`)** - Needs data-testid attributes
- Node configuration forms
- Multi-select mode
- Edge configuration
- Annotation editing
- Token browser

**Canvas Controls** - May need different selectors
- Minimap display
- Zoom controls

## Writing New Tests

### Basic Test Structure

```typescript
import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

test.describe('Feature Name', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test('should do something', async ({ page }) => {
    // Arrange
    await modeler.selectNode('event_1');

    // Act
    await modeler.deleteSelected();

    // Assert
    const count = await modeler.getNodeCount();
    expect(count).toBe(1);
  });
});
```

### Test Server Configuration

The test server (`test-server.ts`) provides a standalone Express server with:
- Static HTML page embedding `drupalSettings.modeler_api` with all API URLs including `replay_url: '/modeler-api/replay'` (note: `test_url` is NOT included by default — it is injected via route interception when `withTestUrl: true`)
- Mock `Drupal.t()` function with `@`-variable interpolation support (e.g., `'Step @current of @total'` → `'Step 1 of 2'`)
- CSRF token endpoint returning plain text (`'mock-csrf-token'`)
- Replay POST endpoint returning `ReplayEntry[]` format with execution history
- Test POST endpoint (`/modeler-api/test`) handling both initiation (returns `{ jobId }`) and polling (returns `{ status: "waiting" }` or `ReplayStep[]`)

### Adding Custom Mocks

```typescript
// In your test file or mocks.ts
await page.route('**/custom-endpoint/**', async (route) => {
  await route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ custom: 'data' })
  });
});
```

### Visual Testing

```typescript
test('visual regression', async ({ page }) => {
  await modeler.goto();
  await expect(page).toHaveScreenshot('modeler-initial.png');
});
```

## CI Integration

### GitLab CI Pipeline

The project uses GitLab CI with dedicated jobs for E2E and accessibility testing. Both jobs depend on the `reactapp build` stage which provides `node_modules/` as an artifact.

#### E2E Tests Job

```yaml
reactapp e2e:
  stage: test
  image: node:20-bookworm
  variables:
    GIT_STRATEGY: fetch
    GIT_CHECKOUT: "true"
  needs:
    - reactapp build
  script:
    - cd ui
    - npx playwright install --with-deps chromium
    - npm run e2e
  artifacts:
    when: always
    paths:
      - ui/tests/playwright-report/
      - ui/tests/test-results/
```

#### Accessibility (a11y) Tests Job

```yaml
reactapp a11y:
  stage: test
  image: node:20-bookworm
  variables:
    GIT_STRATEGY: fetch
    GIT_CHECKOUT: "true"
  needs:
    - reactapp build
  script:
    - cd ui
    - npx playwright install --with-deps chromium
    - npm run test-storybook:ci
  artifacts:
    when: always
    paths:
      - ui/tests/storybook-static/
```

The a11y job uses `test-storybook:ci` which builds a static Storybook, serves it with `http-server`, waits for it to be ready, then runs axe-core audits via `@storybook/test-runner` against all stories.

## Debugging Tips

1. **Use UI mode** for interactive debugging:
   ```bash
   npm run e2e:ui
   ```

2. **Use headed mode** to see browsers:
   ```bash
   npm run e2e:headed
   ```

3. **Use debug mode** with Playwright Inspector:
   ```bash
   npm run e2e:debug
   ```

4. **Add pauses** in tests:
   ```typescript
   await page.pause();
   ```

5. **Check traces** in test-results folder after failures

6. **Use codegen** to record interactions:
   ```bash
   npm run e2e:codegen http://localhost:3000
   ```

## Test Data Attributes

The modeler uses `data-testid` attributes for reliable element selection:

| Attribute | Element |
|-----------|---------|
| `flow-canvas` | Main ReactFlow canvas |
| `property-panel` | Configuration panel |
| `toolbar` | Top toolbar |
| `replay-panel` | Replay control panel |
| `save-button` | Save model button |
| `settings-button` | Open settings button |
| `search-input` | Search input field |
| `config-form` | Configuration form |
| `node-label-input` | Node label editor |
| `token-browser` | Token browser modal |

## Storybook Accessibility (a11y) Testing

In addition to Playwright E2E tests, the project runs automated accessibility audits against every Storybook story using axe-core.

### How It Works

1. **`@storybook/test-runner`** loads each story in a real Chromium browser
2. **`axe-playwright`** injects axe-core and runs a full a11y audit in light mode
3. The test-runner toggles dark mode by adding `.dark-mode` class via `page.evaluate()` DOM manipulation
4. A second axe-core audit runs against the dark mode rendering
5. Dark mode class is removed to restore light mode for the next test
6. Any WCAG violation fails the test with a detailed report including the violating HTML

### Configuration

The test runner config lives at `.storybook/test-runner.ts`:

```typescript
const config: TestRunnerConfig = {
  async preVisit(page) {
    await injectAxe(page);
    await configureAxe(page, {
      rules: [{ id: 'nested-interactive', enabled: false }],
    });
  },
  async postVisit(page) {
    // Light mode audit
    await checkA11yWithRetry(page, '#storybook-root', axeCheckOptions);

    // Dark mode audit — toggle class via DOM (no page.goto)
    await page.evaluate(() => {
      document.querySelector('.modeler')?.classList.add('dark-mode');
    });
    await page.waitForTimeout(250); // CSS repaint
    await checkA11yWithRetry(page, '#storybook-root', axeCheckOptions);

    // Restore light mode
    await page.evaluate(() => {
      document.querySelector('.modeler')?.classList.remove('dark-mode');
    });
  },
};
```

**Key design decisions:**
- **DOM class toggle** instead of `page.goto()` with URL globals — navigating to a dark theme URL re-triggers story play functions without test context (`__test is not defined` errors)
- **250ms paint delay** after toggling dark mode — CSS custom property repaint needs time; 100ms caused intermittent false positives
- **`checkA11yWithRetry`** wrapper handles race conditions when the Storybook a11y addon is also running axe-core
- **`nested-interactive` disabled** — upstream ReactFlow issue (node wrappers have `role="button"`)

### Running a11y Tests Locally

```bash
# Start Storybook (must be running on port 6006)
npm run storybook

# In another terminal, run a11y audits
npm run test-storybook:a11y
```

### Current Status

- **165 stories pass** axe-core audits with zero violations in both light and dark mode
- **3 stories skip** (PanelErrorBoundary error states) because they deliberately throw errors
- **Disabled rules**: `nested-interactive` (upstream ReactFlow issue)
- **WCAG AA compliant**: All color contrast ratios meet requirements (4.5:1 text, 3:1 non-text)

### Dark Mode a11y Approach

The test-runner toggles dark mode via DOM class manipulation rather than URL navigation:

1. **`.modeler` root** sets `color: var(--modeler-color-text-primary)` — ensures all children inherit adaptive text color (overrides `all: revert` browser defaults)
2. **`.modeler.dark-mode`** sets `background-color: var(--modeler-color-bg-canvas)` — gives axe-core a real dark background to evaluate contrast against
3. **Button elements** (`.data-header`, `.section-header`) explicitly set `background: transparent; border: none` — overrides browser default `ButtonFace` background that `all: revert` restores
4. **Dark mode button overrides** — `.btn-danger` uses `#dc2626` (4.63:1 with white) instead of `#f87171` (2.74:1); `.toolbar-btn.primary` uses `#2563eb` (4.58:1)
5. **Story decorators** use CSS custom properties instead of hardcoded hex colors — ensures proper adaptation when dark mode class is toggled

## Performance Considerations

- Tests run in parallel by default
- Use `test.describe.serial` for tests that must run sequentially
- Mocked APIs ensure fast, deterministic tests
- Artifacts (screenshots, videos) captured only on failure
