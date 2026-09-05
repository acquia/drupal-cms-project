<?php

namespace Drupal\Tests\smart_date\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\smart_date\Plugin\Field\FieldWidget\SmartDateWidgetBase;

/**
 * Minimal concrete subclass of SmartDateWidgetBase for unit testing.
 *
 * - getFilteredDescription() is overridden to avoid the \Drupal::token() call
 *   that the base implementation requires.
 * - formSingleElement() is overridden to return a stub element so that
 *   formMultipleElements() can be exercised without rendering a full date
 *   widget form tree.
 */
class SmartDateWidgetBaseTestDouble extends SmartDateWidgetBase {

  /**
   * {@inheritdoc}
   */
  protected function getFilteredDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  protected function formSingleElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    return $element + ['#markup' => 'item-' . $delta];
  }

}
