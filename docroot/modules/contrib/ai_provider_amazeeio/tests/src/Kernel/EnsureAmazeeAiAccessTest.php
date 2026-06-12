<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\ai_provider_amazeeio\Plugin\ConfigAction\EnsureAmazeeAiAccess;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the ensureAmazeeAiAccess config action.
 *
 * @internal
 */
final class EnsureAmazeeAiAccessTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_provider_amazeeio',
  ];

  /**
   * The ensureAmazeeAiAccess action plugin.
   */
  protected EnsureAmazeeAiAccess $action;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->action = \Drupal::service('plugin.manager.config_action')->createInstance('ensureAmazeeAiAccess');
  }

  /**
   * Test the config action exists.
   */
  public function testActionExists(): void {
    $this->expectNotToPerformAssertions();
    $this->action->apply('ai_provider_amazeeio.settings', []);
  }

}
