<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ListOperationBase;
use Drupal\eca_base\Plugin\ListCountTrait;

/**
 * Action to count items in a list and store resulting number as token.
 */
#[Action(
  id: 'eca_count',
  label: new TranslatableMarkup('List: count items'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Counts the items in a list based on several properties.'),
  version_introduced: '1.0.0',
)]
class ListCount extends ListOperationBase {

  use ListCountTrait;

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $result = $this->countValue($this->configuration['list_token']);
    $this->tokenService->addTokenData($this->configuration['token_name'], $result);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('Provide the name of a new token where the counting result should be stored.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -10,
      '#eca_token_reference' => TRUE,
    ];
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['list_token']['#description'] = $this->t('Provide the name of the token that contains a list from which the number of items should be counted.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
