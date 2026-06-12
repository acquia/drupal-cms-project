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
 * Plugin implementation of the 'map_key_value' widget.
 */
#[CustomFieldWidget(
  id: 'map_key_value',
  label: new TranslatableMarkup('Map: Key/Value'),
  category: new TranslatableMarkup('Map'),
  field_types: [
    'map',
  ],
)]
class MapKeyValueWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'key_label' => 'Key',
      'value_label' => 'Value',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['key_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key label'),
      '#description' => $this->t('The table header label for key column'),
      '#default_value' => $settings['key_label'],
      '#required' => TRUE,
      '#maxlength' => 128,
    ];
    $element['value_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value label'),
      '#description' => $this->t('The table header label for value column'),
      '#default_value' => $settings['value_label'],
      '#required' => TRUE,
      '#maxlength' => 128,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $element['#element_validate'] = [[static::class, 'validateArrayValues']];
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $items[$delta];
    $settings = $this->getSettings() + static::defaultSettings();

    $element['#type'] = 'custom_field_multivalue';
    $element['#default_value'] = $item->{$field->getName()} ?? [];
    $element['items'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['custom-field-element-grid-2'],
      ],
      'key' => [
        '#type' => 'textfield',
        '#title' => $settings['key_label'] ?: $this->t('Key'),
      ],
      'value' => [
        '#type' => 'textfield',
        '#title' => $settings['value_label'] ?: $this->t('Value'),
      ],
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
    $unique_keys = [];
    foreach ($values as $key => $value) {
      if (!is_array($value) || !isset($value['items'])) {
        continue;
      }
      $container = $element[$key]['items'];
      $items = $value['items'];
      $item_key = $items['key'] ? trim($items['key']) : '';
      $item_value = $items['value'] ? trim($items['value']) : '';

      // Skip if both values are empty.
      if ($item_key === '' && $item_value === '') {
        continue;
      }

      // If either key or value is empty, set an error.
      elseif ($item_key === '' || $item_value === '') {
        $form_state->setError($container, t('Both %key and %value are required.', [
          '%key' => $container['key']['#title'],
          '%value' => $container['value']['#title'],
        ]));
      }
      // Make sure each key is unique.
      $unique_key = strtolower($item_key);
      if (in_array($unique_key, $unique_keys)) {
        $has_errors = TRUE;
        break;
      }
      else {
        $unique_keys[] = $unique_key;
        $filtered_values[$key] = [
          'key' => $item_key,
          'value' => $item_value,
        ];
      }
    }

    if ($has_errors) {
      $form_state->setError($element, t('All keys must be unique.'));
    }
    else {
      $form_state->setValueForElement($element, $filtered_values);
    }
  }

}
