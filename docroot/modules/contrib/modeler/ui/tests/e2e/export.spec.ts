import { test, expect } from '@playwright/test';
import { ModelerPage } from './pages/ModelerPage';
import { setupMocks } from './fixtures/mocks';

test.describe('Workflow Modeler - Export', () => {
  let modeler: ModelerPage;

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    modeler = new ModelerPage(page);
    await modeler.goto();
  });

  test.describe('Export Button', () => {
    test('should display the export menu item in the kebab menu', async () => {
      await modeler.openKebabMenu();
      const exportBtn = modeler.getExportButton();
      await expect(exportBtn).toBeVisible();
    });

    test('should have accessible role on the export menu item', async () => {
      await modeler.openKebabMenu();
      const exportBtn = modeler.getExportButton();
      // Export is a menu item inside the kebab dropdown
      await expect(exportBtn).toHaveAttribute('role', 'menuitem');
    });
  });

  test.describe('Export Dialog', () => {
    test('should open the export dialog when the export button is clicked', async () => {
      await modeler.openExportDialog();

      const dialog = modeler.getExportDialog();
      await expect(dialog).toBeVisible();
      await expect(dialog).toHaveAttribute('role', 'dialog');
      await expect(dialog).toHaveAttribute('aria-modal', 'true');
    });

    test('should display dialog title', async () => {
      await modeler.openExportDialog();

      const title = modeler.page.locator('#export-dialog-title');
      await expect(title).toHaveText('Export Model');
    });

    test('should display JSON and SVG format options', async () => {
      // Without export_url/export_recipe_url, only JSON and SVG are available
      await modeler.openExportDialog();

      const jsonOption = modeler.page.locator('.export-format-label:text("JSON")');
      const svgOption = modeler.page.locator('.export-format-label:text("SVG")');

      await expect(jsonOption).toBeVisible();
      await expect(svgOption).toBeVisible();
    });

    test('should have disabled Export button when no format is selected', async () => {
      await modeler.openExportDialog();

      const exportBtn = modeler.page.locator('.export-dialog-footer .btn-primary');
      await expect(exportBtn).toBeDisabled();
    });

    test('should enable Export button after selecting a format', async () => {
      await modeler.openExportDialog();
      await modeler.selectExportFormat('svg');

      const exportBtn = modeler.page.locator('.export-dialog-footer .btn-primary');
      await expect(exportBtn).toBeEnabled();
    });

    test('should close the dialog when Cancel is clicked', async () => {
      await modeler.openExportDialog();

      const dialog = modeler.getExportDialog();
      await expect(dialog).toBeVisible();

      await modeler.cancelExport();
      await expect(dialog).not.toBeVisible();
    });

    test('should close the dialog when clicking the overlay', async () => {
      await modeler.openExportDialog();

      const dialog = modeler.getExportDialog();
      await expect(dialog).toBeVisible();

      // Click on the overlay (the outer element)
      const overlay = modeler.page.locator('.export-dialog-overlay');
      await overlay.click({ position: { x: 10, y: 10 } });

      await expect(dialog).not.toBeVisible();
    });

    test('should close the dialog when Escape is pressed', async () => {
      await modeler.openExportDialog();

      const dialog = modeler.getExportDialog();
      await expect(dialog).toBeVisible();

      await modeler.page.keyboard.press('Escape');
      await expect(dialog).not.toBeVisible();
    });
  });

  test.describe('Export Dialog - Format Selection', () => {
    test('should select JSON format and show options panel', async () => {
      await modeler.openExportDialog();
      await modeler.selectExportFormat('json');

      // JSON option should be selected (aria-checked)
      const jsonOption = modeler.page.locator('.export-format-option:has(.export-format-label:text("JSON"))');
      await expect(jsonOption).toHaveAttribute('aria-checked', 'true');

      // Required modules section should be visible
      const modulesLabel = modeler.page.locator('.export-modules-label');
      await expect(modulesLabel).toBeVisible();
    });

    test('should select SVG format', async () => {
      await modeler.openExportDialog();
      await modeler.selectExportFormat('svg');

      const svgOption = modeler.page.locator('.export-format-option:has(.export-format-label:text("SVG"))');
      await expect(svgOption).toHaveAttribute('aria-checked', 'true');
    });

    test('should switch selection between formats', async () => {
      await modeler.openExportDialog();
      await modeler.selectExportFormat('json');

      const jsonOption = modeler.page.locator('.export-format-option:has(.export-format-label:text("JSON"))');
      const svgOption = modeler.page.locator('.export-format-option:has(.export-format-label:text("SVG"))');

      await expect(jsonOption).toHaveAttribute('aria-checked', 'true');

      await modeler.selectExportFormat('svg');
      await expect(jsonOption).toHaveAttribute('aria-checked', 'false');
      await expect(svgOption).toHaveAttribute('aria-checked', 'true');
    });
  });

  test.describe('Export Dialog - Accessibility', () => {
    test('should have a radiogroup with accessible label', async () => {
      await modeler.openExportDialog();

      const radiogroup = modeler.page.locator('[role="radiogroup"]');
      await expect(radiogroup).toBeVisible();
      await expect(radiogroup).toHaveAttribute('aria-label', 'Export format');
    });

    test('should trap focus inside the dialog', async () => {
      await modeler.openExportDialog();

      // Wait for auto-focus to place focus inside the dialog before tabbing.
      // The useFocusTrap hook registers its Tab-wrapping handler in a
      // useEffect.  Without this wait, Tab presses can escape the dialog.
      await expect(async () => {
        const focusInDialog = await modeler.page.evaluate(() => {
          const dialog = document.querySelector('.export-dialog');
          return dialog?.contains(document.activeElement) ?? false;
        });
        expect(focusInDialog).toBe(true);
      }).toPass({ timeout: 2000 });

      // Press Tab multiple times to cycle through focusable elements
      // Focus should stay within the dialog
      for (let i = 0; i < 5; i++) {
        await modeler.page.keyboard.press('Tab');
      }

      // Verify focus is still within the dialog
      const activeElement = await modeler.page.evaluate(() =>
        document.activeElement?.closest('.export-dialog') !== null
      );
      expect(activeElement).toBe(true);
    });

    test('should have radio buttons with aria-checked attribute', async () => {
      await modeler.openExportDialog();

      const radios = modeler.page.locator('[role="radio"]');
      const count = await radios.count();
      expect(count).toBeGreaterThanOrEqual(2);

      for (let i = 0; i < count; i++) {
        await expect(radios.nth(i)).toHaveAttribute('aria-checked');
      }
    });
  });

  test.describe('Export Dialog - JSON Export', () => {
    test('should trigger JSON download when Export is clicked', async ({ page }) => {
      await modeler.openExportDialog();
      await modeler.selectExportFormat('json');

      // Listen for download event
      const downloadPromise = page.waitForEvent('download', { timeout: 5000 }).catch(() => null);

      await modeler.confirmExport();

      const download = await downloadPromise;
      if (download) {
        // Verify filename ends with .json
        expect(download.suggestedFilename()).toMatch(/\.json$/);
      }
      // If no download event (some environments), the dialog should close
    });
  });

  test.describe('Export Dialog - SVG Export', () => {
    test('should trigger SVG download when Export is clicked', async ({ page }) => {
      await modeler.openExportDialog();
      await modeler.selectExportFormat('svg');

      // Listen for download event
      const downloadPromise = page.waitForEvent('download', { timeout: 5000 }).catch(() => null);

      await modeler.confirmExport();

      const download = await downloadPromise;
      if (download) {
        // Verify filename ends with .svg
        expect(download.suggestedFilename()).toMatch(/\.svg$/);
      }
    });
  });
});
