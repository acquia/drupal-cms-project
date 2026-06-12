<?php

declare(strict_types=1);

namespace Drupal\ui_icons_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;

/**
 * Represents a configurable icon field.
 */
class IconFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state): array {
    $default_value = parent::defaultValuesFormSubmit($element, $form, $form_state);
    // Clean value as the FormElement will return 'target_id' or 'icon' and
    // 'settings'.
    foreach ($default_value as $delta => $properties) {
      if (isset($properties['value'])) {
        unset($default_value[$delta]['value']);
      }
    }
    return $default_value;
  }

}
