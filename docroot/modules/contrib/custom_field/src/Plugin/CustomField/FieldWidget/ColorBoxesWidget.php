<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'color_boxes' widget.
 */
#[CustomFieldWidget(
  id: 'color_boxes',
  label: new TranslatableMarkup('Color boxes'),
  category: new TranslatableMarkup('Color'),
  field_types: [
    'color',
  ],
)]
class ColorBoxesWidget extends ColorWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'default_colors' => [
        '#ac725e',
        '#d06b64',
        '#f83a22',
        '#fa573c',
        '#ff7537',
        '#ffad46',
        '#42d692',
        '#16a765',
        '#7bd148',
        '#b3dc6c',
        '#fbe983',
        '#92e1c0',
        '#9fe1e7',
        '#9fc6e7',
        '#4986e7',
        '#9a9cff',
        '#b99aff',
        '#c2c2c2',
        '#cabdbf',
        '#cca6ac',
        '#f691b2',
        '#cd74e6',
        '#a47ae2',
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();
    $colors = is_array($settings['default_colors']) ? implode(',', $settings['default_colors']) : $settings['default_colors'];

    $element['default_colors'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default colors'),
      '#default_value' => $colors,
      '#required' => TRUE,
      '#element_validate' => [
        [$this, 'settingsColorValidate'],
      ],
      '#description' => $this->t('Default colors for pre-selected color boxes. Enter as 6 digit upper case hex - such as #FF0000.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();
    $element['#uid'] = Html::getUniqueId('color-field-' . $field->getName());

    // Ensure the default value is the required format.
    if ($element['#default_value']) {
      $element['#default_value'] = strtoupper($element['#default_value']);

      if (strlen($element['#default_value']) === 6) {
        $element['#default_value'] = '#' . $element['#default_value'];
      }
    }
    elseif ($element['#required']) {
      // If the element is required but has no default value and the element is
      // hidden like the color boxes widget does, prevent HTML5 Validation from
      // being invisible and blocking save with no apparent reason.
      $element['#attributes']['class'][] = 'color_field_widget_box__color';
    }

    $element['#attached']['library'][] = 'custom_field/custom-field-color-box';

    // Set Drupal settings.
    $settings[$element['#uid']] = [
      'required' => $field->getFieldSetting('required'),
    ];

    $default_colors = is_array($settings['default_colors']) ? implode(',', $settings['default_colors']) : $settings['default_colors'];
    preg_match_all("/#[0-9A-F]{6}/i", $default_colors, $matches, PREG_SET_ORDER);

    foreach ($matches as $color) {
      $settings[$element['#uid']]['palette'][] = $color[0];
    }

    $element['#attached']['drupalSettings']['custom_field']['color_box']['settings'] = $settings;

    return [
      '#type' => 'container',
      'value' => [
        '#type' => 'textfield',
        '#maxlength' => 7,
        '#size' => 7,
        '#attributes' => ['class' => ['visually-hidden']],
      ] + $element,
      'color_picker' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['custom-field-color-box-container'],
          'id' => $element['#uid'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    $value = $value['value'] ?? NULL;
    if (empty($value)) {
      return NULL;
    }
    return $value;
  }

  /**
   * Use element validator to make sure that hex values are in correct format.
   *
   * @param array<string, mixed> $element
   *   The Default colors element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function settingsColorValidate(array $element, FormStateInterface $form_state): void {
    $default_colors = $form_state->getValue($element['#parents']);
    $colors = '';
    if (!empty($default_colors)) {
      preg_match_all("/#[0-9a-f]{6}/i", $default_colors, $default_colors, PREG_SET_ORDER);

      foreach ($default_colors as $color) {
        if (!empty($colors)) {
          $colors .= ',';
        }

        $colors .= strtolower($color[0]);
      }
    }

    $form_state->setValue($element['#parents'], $colors);
  }

}
