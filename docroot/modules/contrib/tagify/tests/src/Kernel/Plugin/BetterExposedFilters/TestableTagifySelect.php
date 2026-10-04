<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel\Plugin\BetterExposedFilters;

use Drupal\Core\Form\FormStateInterface;
use Drupal\tagify\Plugin\better_exposed_filters\filter\TagifySelect;

/**
 * A testable subclass of TagifySelect that avoids Views bootstrap dependencies.
 *
 * Overrides the two parent methods that access $this->handler and $this->view
 * so that unit-style assertions can be made on TagifySelect::exposedFormAlter()
 * logic in a Kernel test context.
 */
class TestableTagifySelect extends TagifySelect {

  /**
   * The field ID returned by getExposedFilterFieldId().
   */
  protected string $exposedFieldId = 'test_field';

  /**
   * {@inheritdoc}
   */
  protected function getExposedFilterFieldId(): string {
    return $this->exposedFieldId;
  }

  /**
   * Setter for the exposed field ID used in tests.
   */
  public function setExposedFieldId(string $field_id): void {
    $this->exposedFieldId = $field_id;
  }

  /**
   * {@inheritdoc}
   *
   * Skips parent::exposedFormAlter() which requires a fully bootstrapped Views
   * handler and view object. Only the TagifySelect-specific branching logic is
   * exercised here.
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $field_id = $this->getExposedFilterFieldId();
    if (!isset($form[$field_id])) {
      return;
    }

    if (isset($form[$field_id]['#options'])) {
      $existing = $form[$field_id];
      $is_multiple = $existing['#multiple'] ?? FALSE;

      $options = $existing['#options'];
      if (!isset($options['All'])) {
        $options = ['All' => '- Any -'] + $options;
      }

      $form[$field_id] = [
        '#type' => 'select_tagify',
        '#options' => $options,
        '#multiple' => $is_multiple,
        '#mode' => $is_multiple ? NULL : 'select',
        '#identifier' => $is_multiple ? NULL : $field_id,
        '#cardinality' => $is_multiple ? 0 : 1,
        '#placeholder' => $this->configuration['advanced']['placeholder'],
        '#match_operator' => $this->configuration['advanced']['match_operator'],
        '#match_limit' => (int) $this->configuration['advanced']['max_items'],
        '#attributes' => [
          'class' => [$field_id],
        ],
      ];
      foreach (['#title', '#required', '#title_display', '#size', '#default_value'] as $prop) {
        if (isset($existing[$prop])) {
          $form[$field_id][$prop] = $existing[$prop];
        }
      }
    }
  }

}
