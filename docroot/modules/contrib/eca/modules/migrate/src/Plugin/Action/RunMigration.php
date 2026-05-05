<?php

namespace Drupal\eca_migrate\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Runs a specified migration.
 */
#[Action(
  id: 'eca_migrate_run_migration',
  label: new TranslatableMarkup('Migrate: Run migration'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Triggers a migration run by ID.'),
  version_introduced: '3.0.0',
)]
class RunMigration extends MigrationActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!empty($this->configuration['update'])) {
      $this->getMigrationIdMap($this->migration)->prepareUpdate();
    }

    $this->migrationResult = $this->migrationRun($this->migration);
  }

  /**
   * Run a migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration instance.
   *
   * @return int
   *   The migration result code.
   */
  protected function migrationRun(MigrationInterface $migration): int {
    $migration_id = $migration->id();

    try {
      $executable = new MigrateExecutable($migration, new MigrateMessage());
      $result = $executable->import();
      $this->logger->info($this->t('Migration "@id" run with result code @result.', [
        '@id' => $migration_id,
        '@result' => $result,
      ]));
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

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'update' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update existing records'),
      '#default_value' => $this->configuration['update'],
      '#description' => $this->t('If checked, existing migrated items will be updated.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['update'] = $form_state->getValue('update');
    parent::submitConfigurationForm($form, $form_state);
  }

}
