<?php

declare(strict_types=1);

namespace Drupal\Tests\eca\Kernel\Hook;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Hook\ProjectBrowserHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the ECA Guide Library source is auto-enabled in Project Browser.
 *
 * The hook must add `eca_guide_library` to Project Browser's enabled sources
 * whenever both the ECA and Project Browser modules are installed, regardless
 * of the order in which they are installed.
 */
#[Group('eca')]
#[Group('eca_core')]
#[CoversClass(ProjectBrowserHooks::class)]
#[RunTestsInSeparateProcesses]
final class ProjectBrowserAutoEnableTest extends KernelTestBase {

  /**
   * The Project Browser admin settings config object.
   */
  private const string PB_SETTINGS = 'project_browser.admin_settings';

  /**
   * The source plugin ID that should be auto-enabled.
   */
  private const string SOURCE_ID = 'eca_guide_library';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * Installs modules at runtime, triggering hook_modules_installed().
   *
   * @param string[] $modules
   *   The machine names of the modules to install.
   */
  private function installModules(array $modules): void {
    $this->container->get('module_installer')->install($modules);
    // Refresh the local container reference after the rebuild.
    $this->container = \Drupal::getContainer();
  }

  /**
   * Returns the current enabled_sources map from Project Browser settings.
   *
   * @return array
   *   The enabled_sources value, or an empty array if unset.
   */
  private function getEnabledSources(): array {
    return $this->config(self::PB_SETTINGS)->get('enabled_sources') ?? [];
  }

  /**
   * Tests Order A: Project Browser is installed after ECA.
   */
  public function testProjectBrowserInstalledLast(): void {
    // Install ECA (and its dependencies) first. Project Browser is absent, so
    // the source must not be enabled yet.
    $this->installModules(['eca']);
    $this->assertArrayNotHasKey(self::SOURCE_ID, $this->getEnabledSources());

    // Installing Project Browser last must trigger the auto-enable.
    $this->installModules(['project_browser']);
    $enabled = $this->getEnabledSources();
    $this->assertArrayHasKey(self::SOURCE_ID, $enabled);
    $this->assertSame([], $enabled[self::SOURCE_ID]);

    // The pre-existing sources and default_source must be preserved.
    $this->assertArrayHasKey('drupalorg_jsonapi', $enabled);
    $this->assertArrayHasKey('recipes', $enabled);
    $this->assertSame('drupalorg_jsonapi', $this->config(self::PB_SETTINGS)->get('default_source'));
  }

  /**
   * Tests Order B: ECA is installed after Project Browser.
   */
  public function testEcaInstalledLast(): void {
    // Install Project Browser first. Our source must not be present in the
    // default configuration.
    $this->installModules(['project_browser']);
    $this->assertArrayNotHasKey(self::SOURCE_ID, $this->getEnabledSources());

    // Installing ECA last must trigger the auto-enable.
    $this->installModules(['eca']);
    $enabled = $this->getEnabledSources();
    $this->assertArrayHasKey(self::SOURCE_ID, $enabled);
    $this->assertSame([], $enabled[self::SOURCE_ID]);

    // The pre-existing sources and default_source must be preserved.
    $this->assertArrayHasKey('drupalorg_jsonapi', $enabled);
    $this->assertArrayHasKey('recipes', $enabled);
    $this->assertSame('drupalorg_jsonapi', $this->config(self::PB_SETTINGS)->get('default_source'));
  }

  /**
   * Tests that installing an unrelated module does not alter the entry.
   */
  public function testIdempotentOnUnrelatedInstall(): void {
    $this->installModules(['eca', 'project_browser']);
    $before = $this->getEnabledSources();
    $this->assertArrayHasKey(self::SOURCE_ID, $before);

    // Installing an unrelated module must not duplicate or alter the entry, and
    // the enabled_sources map must remain identical.
    $this->installModules(['field']);
    $after = $this->getEnabledSources();
    $this->assertSame($before, $after);
  }

}
