<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;

/**
 * Plugin implementation of the 'array_number' widget.
 */
#[PropWidget(
  id: 'array_number',
  prop_type: 'array',
  items_types: ['number'],
  label: new TranslatableMarkup('Array number'),
)]
class PropWidgetArrayNumber extends PropWidgetArrayBase {

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value = [], $required = FALSE): array {
    $element = parent::widget($form, $form_state, $value, $required);
    $element['value']['#element_validate'][] = [static::class, 'validateArrayValues'];
    $element['value']['value'] = [
      '#type' => 'number',
      '#title' => $this->t('Value'),
      '#title_display' => 'invisible',
      '#step' => 'any',
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
    $values = $element['#value'] ?? NULL;
    $filtered_values = [];
    $has_errors = FALSE;
    if (\is_array($values)) {
      foreach ($values as $key => $value) {
        if (!\is_array($value)) {
          continue;
        }
        $filtered_value = $value['value'];
        // Make sure each value is unique.
        if (\in_array($filtered_value, $filtered_values)) {
          $has_errors = TRUE;
          break;
        }
        else {
          if (!empty($filtered_value)) {
            $filtered_values[$key] = $filtered_value;
          }
        }
      }
    }
    if ($has_errors) {
      $form_state->setError($element, t('All values must be unique.'));
    }
    else {
      $form_state->setValueForElement($element, array_values($filtered_values));
    }
  }

}
