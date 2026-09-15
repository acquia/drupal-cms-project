<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;

/**
 * Plugin implementation of the 'map_text' widget.
 */
#[CustomFieldWidget(
  id: 'map_text',
  label: new TranslatableMarkup('Map: Text'),
  category: new TranslatableMarkup('Map'),
  field_types: [
    'map_string',
  ],
)]
class MapTextWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $element['#element_validate'] = [[static::class, 'validateArrayValues']];
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $items[$delta];

    $element['#type'] = 'custom_field_multivalue';
    $element['#default_value'] = $item->{$field->getName()} ?? [];
    $element['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#title_display' => 'invisible',
    ];

    return $element;
  }

  /**
   * The #element_validate callback for map field array values.
   *
   * @param array<string, mixed> $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form for the form this element belongs to.
   *
   * @see \Drupal\Core\Render\Element\FormElement::processPattern()
   */
  public static function validateArrayValues(array $element, FormStateInterface $form_state): void {
    $values = $element['#value'] ?? [];
    $filtered_values = [];
    $has_errors = FALSE;
    foreach ($values as $key => $value) {
      if (!is_array($value)) {
        continue;
      }
      $filtered_value = $value['value'] ? trim($value['value']) : '';
      if ($filtered_value === '') {
        continue;
      }
      // Make sure each value is unique.
      if (in_array($filtered_value, $filtered_values)) {
        $has_errors = TRUE;
        break;
      }
      else {
        $filtered_values[$key] = $filtered_value;
      }
    }

    if ($has_errors) {
      $form_state->setError($element, t('All values must be unique.'));
    }
    else {
      $form_state->setValueForElement($element, $filtered_values);
    }
  }

}
