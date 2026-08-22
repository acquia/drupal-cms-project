<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ListOperationBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Sorts a list.
 */
#[Action(
  id: 'eca_list_sort',
  label: new TranslatableMarkup('List: sort items'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Sorts a list.'),
  version_introduced: '3.1.3',
)]
class ListSort extends ListOperationBase {

  use PluginFormTrait;

  /**
   * Gets the supported sorting methods.
   *
   * @return array
   *   The supported sorting methods.
   */
  public function getSupportedSortMethods(): array {
    return [
      'asort' => $this->t('Ascending by value'),
      'arsort' => $this->t('Descending by value'),
      'ksort' => $this->t('Ascending by key'),
      'krsort' => $this->t('Descending by key'),
      'natsort' => $this->t('Natural'),
      'natcasesort' => $this->t('Natural (case insensitive)'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'sort_method' => 'asort',
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['sort_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort method'),
      '#options' => $this->getSupportedSortMethods(),
      '#default_value' => $this->configuration['sort_method'],
      '#weight' => 0,
    ];

    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('The sorted list will be loaded into this specified token. If this is not provided, the list will be sorted in-place.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => 4,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['sort_method'] = $form_state->getValue('sort_method');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $list = $this->getItemList();
    $items = ($list instanceof DataTransferObject) ? $list->toArray() : \iterator_to_array($list);

    // Note - verbosity is intended to aid in static analysis.
    switch ($this->configuration['sort_method']) {
      case 'asort':
        \asort($items);
        break;

      case 'arsort':
        \arsort($items);
        break;

      case 'ksort':
        \ksort($items);
        break;

      case 'krsort':
        \krsort($items);
        break;

      case 'natsort':
        \natsort($items);
        break;

      case 'natcasesort':
        \natcasesort($items);
        break;
    }

    $dest_token = trim((string) $this->configuration['token_name']);
    if (!$dest_token) {
      $dest_token = trim((string) $this->configuration['list_token']);
    }
    $this->tokenService->addTokenData($dest_token, DataTransferObject::create($items));
  }

}
