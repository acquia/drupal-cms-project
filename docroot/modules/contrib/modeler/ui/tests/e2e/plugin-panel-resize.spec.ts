import { expect, test } from '@playwright/test';
import { setupMocks } from './fixtures/mocks';
import { ModelerPage } from './pages/ModelerPage';

test('floating plugin panel resizes vertically and remains reachable', async ({ page }) => {
  await setupMocks(page);
  const modeler = new ModelerPage(page);
  await modeler.goto();

  await page.evaluate(() => {
    const runtime = window as typeof window & {
      WorkflowModeler?: {
        registerPanel: (descriptor: Record<string, unknown>) => void;
      };
      __pluginPanelResizeEvents?: Array<[number, number]>;
    };
    runtime.__pluginPanelResizeEvents = [];
    runtime.WorkflowModeler?.registerPanel({
      id: 'e2e-resizable-panel',
      label: 'Resizable Inspector',
      position: 'left',
      floating: true,
      width: 300,
      render(container: HTMLElement) {
        const content = document.createElement('div');
        content.style.height = '360px';
        content.textContent = 'Scrollable plugin content';
        container.appendChild(content);
      },
      destroy(container: HTMLElement) {
        container.replaceChildren();
      },
      onResize(width: number, height: number) {
        runtime.__pluginPanelResizeEvents?.push([width, height]);
      },
    });
  });

  const panel = page.locator('[data-plugin-panel-id="e2e-resizable-panel"]');
  const content = panel.locator('.plugin-panel-content');
  const handle = panel.getByRole('separator', { name: 'Resize Resizable Inspector panel height' });
  await expect(panel).toBeVisible();
  await expect(handle).toBeVisible();

  const initial = await panel.boundingBox();
  const initialModeler = await page.locator('.workflow-modeler').boundingBox();
  expect(initial).not.toBeNull();
  expect(initialModeler).not.toBeNull();
  expect(await panel.evaluate((element) => element.style.height)).toBe('');
  expect(await content.locator(':scope > *').count()).toBe(1);
  const maximumInitialHeight = initialModeler!.height - (initial!.y - initialModeler!.y) - 16;
  expect(initial!.height).toBeLessThan(maximumInitialHeight - 100);

  const handleBox = await handle.boundingBox();
  expect(handleBox).not.toBeNull();
  await page.mouse.move(handleBox!.x + handleBox!.width / 2, handleBox!.y + 4);
  await page.mouse.down();
  await page.mouse.move(handleBox!.x + handleBox!.width / 2, handleBox!.y + 84);
  await page.mouse.up();

  const grown = await panel.boundingBox();
  expect(grown!.height).toBeGreaterThan(initial!.height + 60);

  await handle.focus();
  for (let step = 0; step < 6; step += 1) {
    await page.keyboard.press('Shift+ArrowUp');
  }
  const shrunk = await panel.boundingBox();
  expect(shrunk!.height).toBeLessThan(grown!.height - 35);
  expect(await content.evaluate((element) => element.scrollHeight > element.clientHeight)).toBe(true);

  const callbackCount = await page.evaluate(() => (
    window as typeof window & { __pluginPanelResizeEvents?: Array<[number, number]> }
  ).__pluginPanelResizeEvents?.length ?? 0);
  expect(callbackCount).toBeGreaterThanOrEqual(2);

  await page.setViewportSize({ width: 800, height: 500 });
  const resizedPanel = await panel.boundingBox();
  const modelerBox = await page.locator('.workflow-modeler').boundingBox();
  expect(resizedPanel).not.toBeNull();
  expect(modelerBox).not.toBeNull();
  expect(resizedPanel!.y + resizedPanel!.height).toBeLessThanOrEqual(
    modelerBox!.y + modelerBox!.height - 15,
  );
});
