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
   * Skips parent::exposedFormAlter(), which requires a fully bootstrapped
   * Views handler and view object. The Tagify-specific build delegated to
   * below is the real production code path.
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->alterTagifyElement($form);
  }

}
