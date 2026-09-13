<?php

namespace Drupal\eca_migrate_test\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca_migrate\Plugin\Action\RunMigration;
use Drupal\eca_migrate_test\Plugin\migrate\id_map\TestIdMap;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Runs a specified test migration.
 */
#[Action(
  id: 'eca_migrate_test_run_migration',
  label: new TranslatableMarkup('Migrate: Test run migration'),
  type: 'migration',
)]
#[EcaAction(
  description: new TranslatableMarkup('Triggers a test migration run by ID.'),
  version_introduced: '3.0.0',
)]
class TestRunMigration extends RunMigration {

  /**
   * {@inheritdoc}
   */
  protected function getMigrationIdMap(MigrationInterface $migration): MigrateIdMapInterface {
    return new TestIdMap([], NULL, NULL);
  }

}
