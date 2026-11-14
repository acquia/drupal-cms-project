<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_dashboard\Plugin\ProjectBrowserSource\AiDashboardExtensions;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the "ai_dashboard_extensions" Project Browser source is wired up.
 *
 * @group ai_dashboard
 */
#[RunTestsInSeparateProcesses]
class AiDashboardExtensionsSourcePluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'project_browser',
    'ai',
    'ai_dashboard',
  ];

  /**
   * Ensures the source is discoverable and correctly instantiated via DI.
   *
   * This deliberately does not exercise getProjects()/getFilterDefinitions(),
   * because the default wrapped source talks to Drupal.org over the network;
   * that forwarding behaviour is covered by the isolated unit test instead.
   *
   * @see \Drupal\Tests\ai_dashboard\Unit\Plugin\ProjectBrowserSource\AiDashboardExtensionsTest
   */
  public function testSourceIsDiscoverable(): void {
    $manager = $this->container->get(ProjectBrowserSourceManager::class);

    $definitions = $manager->getDefinitions();
    $this->assertArrayHasKey('ai_dashboard_extensions', $definitions);

    $instance = $manager->createInstance('ai_dashboard_extensions');
    $this->assertInstanceOf(AiDashboardExtensions::class, $instance);
  }

}
