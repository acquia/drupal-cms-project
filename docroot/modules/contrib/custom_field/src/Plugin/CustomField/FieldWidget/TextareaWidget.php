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
 * Plugin implementation of the 'textarea' widget.
 */
#[CustomFieldWidget(
  id: 'textarea',
  label: new TranslatableMarkup('Text area (multiple rows)'),
  category: new TranslatableMarkup('Text'),
  field_types: [
    'string_long',
  ],
)]
class TextareaWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'rows' => 5,
      'placeholder' => '',
      'maxlength' => '',
      'maxlength_js' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $field_settings = $field->getFieldSettings();
    $settings = $this->getSettings() + static::defaultSettings();
    $type = isset($field_settings['formatted']) && $field_settings['formatted'] ? 'text_format' : 'textarea';

    if (isset($field_settings['formatted']) && $field_settings['formatted'] && !empty($field_settings['default_format'])) {
      $element['#format'] = $field_settings['default_format'];
      $element['#allowed_formats'] = [$field_settings['default_format']];
      // Pass settings via #after_build_data to avoid serializing $this.
      $element['#after_build'][] = [static::class, 'unsetFilters'];
      $element['#after_build_data'] = $field_settings;
    }

    if (isset($settings['maxlength'])) {
      $element['#attributes']['data-maxlength'] = $settings['maxlength'];
    }
    if (isset($settings['maxlength_js']) && $settings['maxlength_js']) {
      $element['#maxlength_js'] = TRUE;
    }

    return [
      '#type' => $type,
      '#rows' => $settings['rows'] ?? 5,
      '#size' => NULL,
      '#placeholder' => $settings['placeholder'] ?? NULL,
    ] + $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['rows'] = [
      '#type' => 'number',
      '#title' => $this->t('Rows'),
      '#description' => $this->t('Text editors (like CKEditor) may override this setting.'),
      '#default_value' => $settings['rows'],
      '#required' => TRUE,
      '#min' => 1,
    ];
    $element['placeholder'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $settings['placeholder'],
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];
    $element['maxlength'] = [
      '#type' => 'number',
      '#title' => $this->t('Max length'),
      '#description' => $this->t('The maximum amount of characters in the field'),
      '#default_value' => is_numeric($settings['maxlength']) ? $settings['maxlength'] : NULL,
      '#min' => 1,
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

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, $column): mixed {
    // If text field is formatted, the value is an array.
    if (is_array($value)) {
      $value = $value['value'];
    }
    if (trim($value) === '') {
      return NULL;
    }

    return $value;
  }

  /**
   * Helper function to modify filter settings output.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return array<string, mixed>
   *   The modified form element.
   */
  public static function unsetFilters(array $element, FormStateInterface $formState): array {
    // Retrieve settings from #after_build_data.
    $settings = $element['#after_build_data'] ?? static::defaultSettings();
    $hide_guidelines = FALSE;
    $hide_help = FALSE;
    if (!$settings['format']['guidelines']) {
      $hide_guidelines = TRUE;
      unset($element['format']['guidelines']);
    }
    if (!$settings['format']['help']) {
      $hide_help = TRUE;
      unset($element['format']['help']);
    }
    if ($hide_guidelines && $hide_help) {
      unset($element['format']['#theme_wrappers']);
    }

    return $element;
  }

}
