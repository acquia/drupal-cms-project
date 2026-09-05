<?php

namespace Drupal\eca_migrate\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Rollback a specified migration.
 */
#[Action(
  id: 'eca_migrate_rollback_migration',
  label: new TranslatableMarkup('Migrate: Rollback migration'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Triggers a migration rollback by ID.'),
  version_introduced: '3.0.11',
)]
class RollbackMigration extends MigrationActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $this->migrationResult = $this->migrationRollback($this->migration);
  }

  /**
   * Rollback a migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration instance.
   *
   * @return int
   *   The migration result code.
   */
  protected function migrationRollback(MigrationInterface $migration): int {
    $migration_id = $migration->id();

    try {
      $executable = new MigrateExecutable($migration, new MigrateMessage());
      $result = $executable->rollback();
    }
    catch (\Exception $e) {
      $this->logger->error($this->t('Migration "@id" failed: @message', [
        '@id' => $migration_id,
        '@message' => $e->getMessage(),
      ]));
      $migration->setStatus(MigrationInterface::STATUS_IDLE);
      $result = MigrationInterface::RESULT_FAILED;
    }

    return $result;
  }

}
