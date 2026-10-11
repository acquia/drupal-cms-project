<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;

/**
 * Plugin implementation of the 'text' widget.
 */
#[CustomFieldWidget(
  id: 'text',
  label: new TranslatableMarkup('Text'),
  category: new TranslatableMarkup('Text'),
  field_types: [
    'string',
  ],
)]
class TextWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'size' => 60,
      'placeholder' => '',
      'maxlength' => '',
      'maxlength_js' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();
    $field_settings = $field->getFieldSettings();
    $default_maxlength = $field->getMaxLength();
    if (is_numeric($settings['maxlength']) && $settings['maxlength'] < $field->getMaxLength()) {
      $default_maxlength = $settings['maxlength'];
    }

    if (!empty($settings['maxlength_js'])) {
      $element['#maxlength_js'] = TRUE;
      $element['#attributes']['data-maxlength'] = $default_maxlength;
    }

    // Add prefix and suffix.
    if (isset($field_settings['prefix'])) {
      $element['#field_prefix'] = FieldFilteredMarkup::create($field_settings['prefix']);
    }
    if (isset($field_settings['suffix'])) {
      $element['#field_suffix'] = FieldFilteredMarkup::create($field_settings['suffix']);
    }

    return [
      '#type' => 'textfield',
      '#maxlength' => $default_maxlength,
      '#placeholder' => $settings['placeholder'] ?? NULL,
      '#size' => $settings['size'] ?? NULL,
    ] + $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();
    $default_maxlength = $field->getMaxLength();
    if (is_numeric($settings['maxlength']) && $settings['maxlength'] < $field->getMaxLength()) {
      $default_maxlength = $settings['maxlength'];
    }
    $element['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size of textfield'),
      '#default_value' => $settings['size'],
      '#required' => TRUE,
      '#min' => 1,
    ];
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $settings['placeholder'],
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];
    $element['maxlength'] = [
      '#type' => 'number',
      '#title' => $this->t('Max length'),
      '#description' => $this->t('The maximum amount of characters in the field'),
      '#default_value' => $default_maxlength,
      '#min' => 1,
      '#max' => $field->getMaxLength(),
      '#required' => TRUE,
    ];
    // Add additional setting if maxlength module is enabled.
    if ($this->moduleHandler->moduleExists('maxlength')) {
      $element['maxlength_js'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show max length character count'),
        '#default_value' => $settings['maxlength_js'],
      ];
    }

    return $element;
  }

}
