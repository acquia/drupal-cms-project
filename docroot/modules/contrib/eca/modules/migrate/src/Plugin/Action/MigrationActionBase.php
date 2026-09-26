<?php

namespace Drupal\eca_migrate\Plugin\Action;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for migration actions.
 */
abstract class MigrationActionBase extends ConfigurableActionBase {

  /**
   * The migration plugin manager.
   */
  protected MigrationPluginManagerInterface $migrationManager;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The migration instance to run.
   */
  protected MigrationInterface $migration;

  /**
   * The migration result code.
   *
   * @see MigrateExecutable::import()
   */
  protected int $migrationResult;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->migrationManager = $container->get('plugin.manager.migration');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    $access_result = $this->checkMigrationAccess();
    return ($return_as_object) ? $access_result : $access_result->isAllowed();
  }

  /**
   * Check if migration instance is successfully created.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Whether the migration instance was successfully created.
   */
  protected function checkMigrationAccess(): AccessResultInterface {
    $migration_id = $this->tokenService->replace($this->configuration['migration_id']);
    if (empty($migration_id)) {
      return AccessResult::forbidden('Migration ID is missing.');
    }

    try {
      $migration = $this->migrationManager->createInstance($migration_id);
      if ($migration instanceof MigrationInterface) {
        $this->migration = $migration;
      }
      else {
        return AccessResult::forbidden('Invalid migration instance.');
      }
    }
    catch (PluginException $e) {
      return AccessResult::forbidden('Failed to load migration.');
    }

    return AccessResult::allowed();
  }

  /**
   * Returns the migration id map.
   *
   * This is extracted to make testing easier by mocking the migration id map.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration instance.
   *
   * @return \Drupal\migrate\Plugin\MigrateIdMapInterface
   *   The migration id map.
   */
  protected function getMigrationIdMap(MigrationInterface $migration): MigrateIdMapInterface {
    return $migration->getIdMap();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'migration_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['migration_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Migration ID'),
      '#default_value' => $this->configuration['migration_id'],
      '#description' => $this->t('The ID of the migration to run.'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['migration_id'] = $form_state->getValue('migration_id');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Returns the migration result.
   *
   * @return ?int
   *   The migration result.
   */
  public function getMigrationResult(): ?int {
    return ($this->migrationResult ?? NULL);
  }

}
