<?php

namespace Drupal\Tests\eca_migrate\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;

/**
 * Kernel tests for the migration action plugin.
 */
class MigrationActionTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'migrate',
    'eca',
    'eca_migrate',
    'eca_migrate_test',
    'modeler_api',
  ];

  /**
   * The migration manager.
   */
  protected MigrationPluginManagerInterface $migrationManager;

  /**
   * The action manager.
   */
  protected ActionManager $actionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->migrationManager = \Drupal::service('plugin.manager.migration');
    $this->migrationManager->clearCachedDefinitions();

    $this->actionManager = \Drupal::service('plugin.manager.action');
  }

}
