<?php

declare(strict_types=1);

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ListDataOperationBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Action to merge two lists into a new one.
 */
#[Action(
  id: 'eca_list_combine',
  label: new TranslatableMarkup('List: merge'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Merges two lists into a new one.'),
  version_introduced: '3.1.3',
)]
class ListMerge extends ListDataOperationBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'merged_list' => '',
      'list_token_left' => '',
      'list_token_right' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['merged_list'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token name'),
      '#description' => $this->t('The name of the new token, containing the combined list.'),
      '#default_value' => $this->configuration['merged_list'],
      '#weight' => -20,
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];
    $form['list_token_left'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token name of first list'),
      '#description' => $this->t('Token name of the first list that should be combined into a new one.'),
      '#default_value' => $this->configuration['list_token_left'],
      '#weight' => -15,
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];
    $form['list_token_right'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token name of second list'),
      '#description' => $this->t('Token name of the second list that should be combined into a new one.'),
      '#default_value' => $this->configuration['list_token_right'],
      '#weight' => -10,
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['merged_list'] = $form_state->getValue('merged_list');
    $this->configuration['list_token_left'] = $form_state->getValue('list_token_left');
    $this->configuration['list_token_right'] = $form_state->getValue('list_token_right');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($account === NULL) {
      $account = $this->currentUser;
    }
    $list_left_token_raw = $this->tokenService->getTokenData($this->configuration['list_token_left']);
    $list_right_token_raw = $this->tokenService->getTokenData($this->configuration['list_token_right']);

    if (!is_array($list_left_token_raw) && !($list_left_token_raw instanceof \Traversable)) {
      $access_result = AccessResult::forbidden('The left token does not provide an iterable list.');
    }
    elseif (!is_array($list_right_token_raw) && !($list_right_token_raw instanceof \Traversable)) {
      $access_result = AccessResult::forbidden('The right token does not provide an iterable list.');
    }
    else {
      $access_result = AccessResult::allowed();
    }

    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * Converts the given value into an array if necessary.
   *
   * @param mixed $value
   *   The value to be converted.
   *
   * @return array
   *   The converted array.
   */
  private function normalizeToArray(mixed $value): array {
    if ($value instanceof DataTransferObject) {
      return $value->toArray();
    }
    if ($value instanceof \Traversable) {
      return iterator_to_array($value);
    }
    return (array) $value;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $list_left_token_raw = $this->tokenService->getTokenData($this->configuration['list_token_left']);
    $list_right_token_raw = $this->tokenService->getTokenData($this->configuration['list_token_right']);

    $list_left_token = $this->normalizeToArray($list_left_token_raw);
    $list_right_token = $this->normalizeToArray($list_right_token_raw);

    $combined_list = array_merge($list_left_token, $list_right_token);

    $this->tokenService->addTokenData($this->configuration['merged_list'], $combined_list);
  }

}
