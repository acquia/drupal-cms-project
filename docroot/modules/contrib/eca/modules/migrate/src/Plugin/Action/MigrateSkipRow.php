<?php

namespace Drupal\eca_migrate\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Action to skip a migration row.
 */
#[Action(
  id: 'eca_migrate_skip_row',
  label: new TranslatableMarkup('Migrate: Skip Row'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Throws a MigrateSkipRowException to skip the current migration row.'),
  version_introduced: '2.1.17',
)]
class MigrateSkipRow extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'message' => '',
      'save_to_map' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Skip message'),
      '#description' => $this->t('Optional message explaining why the row was skipped.'),
      '#default_value' => $this->configuration['message'],
      '#weight' => 10,
      '#eca_token_replacement' => TRUE,
    ];

    $form['save_to_map'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save to map'),
      '#description' => $this->t('If checked, the skip will be recorded in the migration map table.'),
      '#default_value' => $this->configuration['save_to_map'],
      '#weight' => 20,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['message'] = $form_state->getValue('message');
    $this->configuration['save_to_map'] = $form_state->getValue('save_to_map');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $message = $this->configuration['message'];
    if (!empty($message)) {
      $message = $this->tokenService->replace($message);
    }

    $save_to_map = $this->configuration['save_to_map'];

    throw new MigrateSkipRowException($message, $save_to_map);
  }

  /**
   * {@inheritdoc}
   */
  public function handleExceptions(): bool {
    return TRUE;
  }

}
