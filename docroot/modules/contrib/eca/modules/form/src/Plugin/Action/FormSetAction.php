<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Set the action URL to use when submitting the form.
 */
#[Action(
  id: 'eca_form_set_action',
  label: new TranslatableMarkup('Form: set action'),
  type: 'form',
)]
#[EcaAction(
  description: new TranslatableMarkup('Set the action URL to use when submitting the form.'),
  version_introduced: '1.1.0',
)]
class FormSetAction extends FormActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if (!($form = &$this->getCurrentForm())) {
      return;
    }
    $form['#action'] = trim((string) $this->tokenService->replaceClear($this->configuration['action']));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'action' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['action'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Action URL'),
      '#description' => $this->t('The URL of a form action like <em>www.example.com/search</em>.'),
      '#weight' => -10,
      '#default_value' => $this->configuration['action'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['action'] = $form_state->getValue('action');
  }

}
