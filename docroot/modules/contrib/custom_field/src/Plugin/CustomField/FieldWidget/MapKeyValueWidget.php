<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

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
class MapKeyValueWidget extends MapWidgetBase {

  /**
   * Default new item.
   *
   * @return string[]
   *   The default value for new items.
   */
  protected static function newItem(): array {
    return [
      'key' => '',
      'value' => '',
    ];
  }

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
    $field_settings = $field->getFieldSettings();
    $settings = $this->getSettings() + static::defaultSettings();
    $field_name = $items->getFieldDefinition()->getName();
    $custom_field_name = $field->getName();
    $wrapper_id = $this->getUniqueElementId($form, $field_name, $delta, $custom_field_name);
    $name_key = str_replace('-', '_', $wrapper_id);
    $field_parents = $element['#field_parents'];

    if (!$form_state->has($wrapper_id)) {
      $default_value = $item->{$custom_field_name} ?? [];
      $form_state->set($wrapper_id, $default_value);
    }

    $map_items = $form_state->get($wrapper_id);

    $element['data'] = [
      '#type' => 'table',
      '#header' => [
        $settings['key_label'] ?? $this->t('Key'),
        $settings['value_label'] ?? $this->t('Label'),
        '',
      ],
      '#empty' => $field_settings['table_empty'] ?: NULL,
      '#attributes' => [
        'class' => ['customfield-map-table'],
        'style' => 'margin:0;',
      ],
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#wrapper_id' => $wrapper_id,
    ];
    foreach ($map_items as $key => $value) {
      $element['data'][$key]['key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Key'),
        '#title_display' => 'invisible',
        '#default_value' => $value['key'] ?? '',
        '#required' => TRUE,
      ];
      $element['data'][$key]['value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#title_display' => 'invisible',
        '#default_value' => $value['value'] ?? '',
        '#required' => TRUE,
      ];
      $element['data'][$key]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#submit' => [[static::class, 'removeItem']],
        '#name' => $name_key . '_' . $key . '_remove',
        '#attributes' => ['data-key' => $key],
        '#ajax' => [
          'callback' => [$this, 'actionCallback'],
          'wrapper' => $wrapper_id,
        ],
        '#limit_validation_errors' => [[...$field_parents, 'data', $key, 'remove']],
      ];
    }

    $element['add_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add item'),
      '#submit' => [[static::class, 'addItem']],
      '#name' => $name_key . '_add_item',
      '#ajax' => [
        'callback' => [$this, 'actionCallback'],
        'wrapper' => $wrapper_id,
      ],
      '#limit_validation_errors' => [$field_parents],
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
    $wrapper_id = $element['data']['#wrapper_id'];
    $values = $element['data']['#value'] ?? NULL;
    $filtered_values = [];
    $has_errors = FALSE;
    if (is_array($values)) {
      $unique_keys = [];
      foreach ($values as $key => $value) {
        if (!is_array($value)) {
          continue;
        }
        $filtered_value = [
          'key' => $value['key'] ? trim($value['key']) : '',
          'value' => $value['value'] ? trim($value['value']) : '',
        ];
        // Make sure each key is unique.
        if (in_array($filtered_value['key'], $unique_keys)) {
          $has_errors = TRUE;
          break;
        }
        else {
          $unique_keys[] = $filtered_value['key'];
          $filtered_values[$key] = $filtered_value;
        }
      }
    }
    if ($has_errors) {
      $form_state->setError($element, t('All keys must be unique.'));
    }
    else {
      $form_state->set($wrapper_id, $filtered_values);
      $form_state->setValueForElement($element, $filtered_values);
    }
  }

}
