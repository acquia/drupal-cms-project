<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_dashboard\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_dashboard\Hook\AiDashboardHooks;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_dashboard hook implementations.
 *
 * @group ai_dashboard
 * @coversDefaultClass \Drupal\ai_dashboard\Hook\AiDashboardHooks
 */
#[RunTestsInSeparateProcesses]
class AiDashboardHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'block', 'key', 'ai', 'ai_dashboard'];

  /**
   * Data provider for ::testPreprocessBlockAddsHelpText().
   */
  public static function providerPreprocessBlock(): array {
    return [
      'project browser recommended block' => [
        'project_browser_block:ai_dashboard_recommended',
        'Apply pre-configured settings to enable safe and effective AI functionality.',
      ],
      'ai documentation block' => [
        'ai_documentation',
        'Access detailed guides, reference materials, and tutorials for all AI features.',
      ],
      'admin menu links block' => [
        'admin_menu_links',
        'Configure custom AI features and integrations tailored to your workflows.',
      ],
    ];
  }

  /**
   * Tests that recognized block plugin IDs get a help text suffix.
   *
   * @covers ::preprocessBlock
   * @dataProvider providerPreprocessBlock
   */
  public function testPreprocessBlockAddsHelpText(string $plugin_id, string $expected_text): void {
    /** @var \Drupal\ai_dashboard\Hook\AiDashboardHooks $hooks */
    $hooks = $this->container->get(AiDashboardHooks::class);

    $variables = ['plugin_id' => $plugin_id];
    $hooks->preprocessBlock($variables);

    $this->assertArrayHasKey('title_suffix', $variables);
    $markup = (string) $variables['title_suffix']['#markup'];
    $this->assertStringContainsString('block-help ai-font-size-xs', $markup);
    $this->assertStringContainsString($expected_text, $markup);
    // The markup must always be a well-formed paragraph.
    $this->assertStringStartsWith('<p class="block-help ai-font-size-xs">', $markup);
    $this->assertStringEndsWith('</p>', $markup);
  }

  /**
   * Tests that unrelated block plugin IDs are left untouched.
   *
   * @covers ::preprocessBlock
   */
  public function testPreprocessBlockIgnoresUnrelatedPlugins(): void {
    /** @var \Drupal\ai_dashboard\Hook\AiDashboardHooks $hooks */
    $hooks = $this->container->get(AiDashboardHooks::class);

    $variables = ['plugin_id' => 'some_other_block'];
    $hooks->preprocessBlock($variables);

    $this->assertArrayNotHasKey('title_suffix', $variables);
  }

  /**
   * Tests that the legacy procedural hook still delegates to the service.
   *
   * This guards the Drupal 12 compatible hook conversion: the procedural
   * hook_preprocess_block() implementation in ai_dashboard.module must keep
   * behaving exactly like the OO hook it delegates to.
   */
  public function testLegacyProceduralHookDelegatesToService(): void {
    $variables = ['plugin_id' => 'ai_documentation'];
    ai_dashboard_preprocess_block($variables);

    $this->assertArrayHasKey('title_suffix', $variables);
    $this->assertStringContainsString(
      'Access detailed guides, reference materials, and tutorials for all AI features.',
      (string) $variables['title_suffix']['#markup']
    );
  }

}
