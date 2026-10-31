<?php

namespace Drupal\eca_migrate\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Reset a specified migration to idle.
 */
#[Action(
  id: 'eca_migrate_reset_migration',
  label: new TranslatableMarkup('Migrate: Reset migration'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Triggers a migration reset by ID.'),
  version_introduced: '3.0.11',
)]
class ResetMigration extends MigrationActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $this->migration->setStatus(MigrationInterface::STATUS_IDLE);
    $this->migrationResult = MigrationInterface::RESULT_COMPLETED;
  }

}
