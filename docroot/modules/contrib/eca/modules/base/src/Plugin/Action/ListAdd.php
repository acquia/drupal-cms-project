<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ListAddBase;

/**
 * Action to add an item to a list.
 */
#[Action(
  id: 'eca_list_add',
  label: new TranslatableMarkup('List: add item'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Add an item to a list using a specified token.'),
  version_introduced: '1.1.0',
)]
class ListAdd extends ListAddBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $value = $this->tokenService->getOrReplace($this->configuration['value']);
    $this->addItem($value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'value' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Value to add'),
      '#default_value' => $this->configuration['value'],
      '#weight' => 20,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['value'] = $form_state->getValue('value');
  }

}
