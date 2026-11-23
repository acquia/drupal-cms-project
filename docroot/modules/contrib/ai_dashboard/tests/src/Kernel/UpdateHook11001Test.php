<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ai_dashboard_update_11001().
 *
 * @group ai_dashboard
 */
#[RunTestsInSeparateProcesses]
class UpdateHook11001Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'key',
    'ai',
    'layout_builder',
    'layout_discovery',
    'dashboard',
    'project_browser',
    'ai_dashboard',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['project_browser', 'ai_dashboard']);
    require_once dirname(__DIR__, 3) . '/ai_dashboard.install';
  }

  /**
   * Replaces the dashboard layout with the one shipped before the update.
   */
  protected function useLegacyLayout(): void {
    $config = $this->config('dashboard.dashboard.ai_dashboard');
    $layout = $config->get('layout');
    $this->assertNotEmpty($layout);
    foreach ($layout[0]['components'] as $uuid => $component) {
      if ($component['configuration']['id'] === 'project_browser_block:ai_dashboard_extensions') {
        $layout[0]['components'][$uuid]['configuration'] = [
          'id' => 'module_list_packages',
          'label' => 'Extensions',
          'label_display' => 'visible',
          'provider' => 'ai_dashboard',
          'context_mapping' => [],
          'packages' => 'ai',
        ];
      }
    }
    $config->set('layout', $layout)->save();
    // Simulate a site that predates the new Project Browser sources.
    $this->config('project_browser.admin_settings')
      ->clear('enabled_sources.ai_dashboard_extensions')
      ->save();
  }

  /**
   * Returns the configuration of every component in the dashboard.
   */
  protected function getComponentConfigurations(): array {
    $configurations = [];
    $layout = $this->config('dashboard.dashboard.ai_dashboard')->get('layout');
    foreach ($layout as $section) {
      foreach ($section['components'] as $uuid => $component) {
        $configurations[$uuid] = $component;
      }
    }
    return $configurations;
  }

  /**
   * Tests that the old module list block is replaced by a project browser one.
   */
  public function testOldBlockIsReplaced(): void {
    $this->useLegacyLayout();
    $before = $this->getComponentConfigurations();
    $old = array_filter($before, fn (array $c) => $c['configuration']['id'] === 'module_list_packages');
    $this->assertCount(1, $old);
    [$uuid, $old_component] = [key($old), current($old)];

    ai_dashboard_update_11001();
    $after = $this->getComponentConfigurations();

    $ids = array_column(array_column($after, 'configuration'), 'id');
    $this->assertNotContains('module_list_packages', $ids);
    $this->assertCount(count($before), $after, 'No component was added or removed.');

    // The replacement keeps its identity, region and weight.
    $this->assertArrayHasKey($uuid, $after);
    $new = $after[$uuid];
    $this->assertSame($old_component['region'], $new['region']);
    $this->assertSame($old_component['weight'], $new['weight']);

    $configuration = $new['configuration'];
    $this->assertSame('project_browser_block:ai_dashboard_extensions', $configuration['id']);
    $this->assertSame('Extensions', $configuration['label']);
    $this->assertSame('project_browser', $configuration['provider']);
    // Sort options must be ones the wrapped Drupal.org source supports,
    // otherwise Project Browser throws an exception.
    $this->assertSame(['best_match', 'created'], $configuration['sort_options']);
    $this->assertSame('best_match', $configuration['default_sort']);
    $this->assertSame(['search'], $configuration['filters']);
  }

  /**
   * Tests that the update enables the Project Browser sources.
   */
  public function testSourcesAreEnabled(): void {
    $this->useLegacyLayout();
    ai_dashboard_update_11001();

    $sources = $this->config('project_browser.admin_settings')->get('enabled_sources');
    $this->assertArrayHasKey('ai_dashboard_extensions', $sources);
    $this->assertArrayHasKey('ai_dashboard_recommended', $sources);
  }

  /**
   * Tests that the update leaves other components untouched.
   */
  public function testOtherComponentsAreUntouched(): void {
    $this->useLegacyLayout();
    $before = $this->getComponentConfigurations();
    ai_dashboard_update_11001();
    $after = $this->getComponentConfigurations();

    foreach ($before as $uuid => $component) {
      if ($component['configuration']['id'] !== 'module_list_packages') {
        $this->assertSame($component, $after[$uuid]);
      }
    }
  }

  /**
   * Tests that the update does not fail when the dashboard does not exist.
   */
  public function testMissingDashboardIsIgnored(): void {
    $this->container->get('entity_type.manager')
      ->getStorage('dashboard')
      ->load('ai_dashboard')
      ->delete();
    ai_dashboard_update_11001();
    $this->assertArrayHasKey('ai_dashboard_extensions', $this->config('project_browser.admin_settings')->get('enabled_sources'));
  }

}
