import type { TestRunnerConfig } from '@storybook/test-runner';
import { injectAxe, configureAxe, checkA11y } from 'axe-playwright';

/**
 * Retry checkA11y to handle race conditions:
 * - "Axe is already running" from concurrent addon-a11y runs
 * - Transient a11y violations caused by async rendering (e.g. ReactFlow
 *   painting nodes/edges after the initial React commit)
 *
 * On each retry we re-inject and re-configure axe-core to guard against
 * stale configuration after Storybook page navigations, and we give the
 * page extra time to settle.
 */
async function checkA11yWithRetry(
  page: Parameters<typeof checkA11y>[0],
  selector: string,
  options: Parameters<typeof checkA11y>[2],
  retries = 3,
): Promise<void> {
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      await checkA11y(page, selector, options);
      return;
    } catch (error: unknown) {
      if (attempt < retries) {
        // Wait progressively longer, then re-inject axe to ensure a
        // clean configuration before the next attempt.
        await page.waitForTimeout(500 * attempt);
        await injectAxe(page);
        await configureAxe(page, {
          rules: [{ id: 'nested-interactive', enabled: false }],
        });
        continue;
      }
      throw error;
    }
  }
}

const axeCheckOptions: Parameters<typeof checkA11y>[2] = {
  detailedReport: true,
  detailedReportOptions: { html: true },
  axeOptions: {
    rules: {
      'nested-interactive': { enabled: false },
    },
  },
};

/**
 * Wait for the page to stabilize after a story renders.
 * ReactFlow paints nodes and edges asynchronously after the initial React
 * commit; running axe-core too early can hit partially-rendered DOM that
 * produces transient violations.
 */
async function waitForStableDOM(page: Parameters<typeof checkA11y>[0]): Promise<void> {
  // If the story contains a ReactFlow canvas, wait for its viewport to appear.
  const hasReactFlow = await page.locator('.react-flow').count();
  if (hasReactFlow > 0) {
    await page.locator('.react-flow__viewport').waitFor({ state: 'attached', timeout: 3000 }).catch(() => {
      // Not every ReactFlow story renders a viewport; ignore timeout.
    });
  }

  // Allow any remaining async paints / CSS transitions to settle.
  await page.waitForTimeout(150);
}

const config: TestRunnerConfig = {
  async preVisit(page) {
    await injectAxe(page);
    // Disable nested-interactive at the axe configuration level so it
    // persists across all runs within the page.  ReactFlow adds
    // role="button" to node wrappers which causes nested-interactive
    // violations when custom nodes contain buttons — this is an upstream
    // ReactFlow issue, not something we can fix in our code.
    // Configuring it here (in addition to axeOptions) prevents race
    // conditions where a concurrent axe run (e.g. from addon-a11y) may
    // reset runtime options.
    await configureAxe(page, {
      rules: [{ id: 'nested-interactive', enabled: false }],
    });
  },
  async postVisit(page) {
    // Wait for async rendering (ReactFlow, CSS transitions) to finish
    await waitForStableDOM(page);

    // ── Light mode audit ──
    await checkA11yWithRetry(page, '#storybook-root', axeCheckOptions);

    // ── Dark mode audit ──
    // Toggle dark mode by adding the class directly on the existing page.
    // This avoids a full page.goto() which would re-trigger story play
    // functions without the test context (__test is not defined errors).
    await page.evaluate(() => {
      const modelerEl = document.querySelector('.modeler');
      if (modelerEl) {
        modelerEl.classList.add('dark-mode');
      }
    });

    // Wait for CSS custom properties to take effect and styles to repaint
    await page.waitForTimeout(250);

    await checkA11yWithRetry(page, '#storybook-root', axeCheckOptions);

    // ── Restore light mode ──
    // Clean up so the next test starts in light mode.
    await page.evaluate(() => {
      const modelerEl = document.querySelector('.modeler');
      if (modelerEl) {
        modelerEl.classList.remove('dark-mode');
      }
    });
  },
};

export default config;
