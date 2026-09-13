<?php

declare(strict_types=1);

namespace Drupal\custom_field\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;

/**
 * Provides a custom_field_datetime element.
 */
#[FormElement('custom_field_datetime')]
class Datetime extends DatetimeBase {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The processed element.
   */
  public static function processDatetime(&$element, FormStateInterface $form_state, &$complete_form): array {
    $element = parent::processDatetime($element, $form_state, $complete_form);
    if ($element['#timezone_element']) {
      $element['date']['#title_display'] = 'before';
      $element['time']['#title_display'] = 'before';
    }

    return $element;
  }

}
