<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel\Plugin\BetterExposedFilters;

use Drupal\Core\Form\FormStateInterface;
use Drupal\tagify\Plugin\better_exposed_filters\filter\Tagify;

/**
 * A testable subclass that avoids Views bootstrap dependencies.
 *
 * Overrides the two parent methods that access $this->handler and $this->view
 * so that unit-style assertions can be made on Tagify::exposedFormAlter()
 * logic in a Kernel test context.
 */
class TestableTagify extends Tagify {

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
   * handler and view object. Only the Tagify-specific branching logic is
   * exercised here.
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $field_id = $this->getExposedFilterFieldId();
    if (!isset($form[$field_id])) {
      return;
    }

    if (isset($form[$field_id]['#target_type']) && isset($form[$field_id]['#tags'])) {
      $form[$field_id] = [
        '#type' => 'entity_autocomplete_tagify',
        '#target_type' => $form[$field_id]['#target_type'],
        '#tags' => $form[$field_id]['#tags'],
        '#selection_handler' => $form[$field_id]['#selection_handler'] ?? 'default',
        '#selection_settings' => $form[$field_id]['#selection_settings'] ?? [],
        '#match_operator' => $this->configuration['advanced']['match_operator'],
        '#max_items' => (int) $this->configuration['advanced']['max_items'],
        '#placeholder' => $this->configuration['advanced']['placeholder'],
        '#attributes' => [
          'class' => [$field_id],
        ],
        '#element_validate' => [[$this, 'elementValidate']],
      ];
      return;
    }

  }

}
