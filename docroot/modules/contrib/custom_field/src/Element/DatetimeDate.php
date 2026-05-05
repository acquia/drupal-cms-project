<?php

declare(strict_types=1);

namespace Drupal\custom_field\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;

/**
 * Provides a custom_field_datetime_date element.
 */
#[FormElement('custom_field_datetime_date')]
class DatetimeDate extends DatetimeBase {

  /**
   * {@inheritdoc}
   */
  public static function processDatetime(&$element, FormStateInterface $form_state, &$complete_form): array {
    $element = parent::processDatetime($element, $form_state, $complete_form);
    if (!empty($element['#title'])) {
      $element['date']['#title'] = $element['#title'];
    }
    $element['date']['#title_display'] = 'before';
    if (!empty($element['#description'])) {
      $element['date']['#description'] = $element['#description'];
      $element['date']['#description_display'] = $element['#description_display'] ?? 'after';
    }
    if (!empty($element['#wrapper_attributes'])) {
      $element['date']['#wrapper_attributes'] = $element['#wrapper_attributes'];
    }
    if (!empty($element['#states'])) {
      $element['date']['#states'] = $element['#states'];
    }

    return $element;
  }

}
