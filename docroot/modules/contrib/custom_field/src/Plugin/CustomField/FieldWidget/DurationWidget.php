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
 * Plugin implementation of the 'duration' widget.
 */
#[CustomFieldWidget(
  id: 'duration',
  label: new TranslatableMarkup('Duration'),
  category: new TranslatableMarkup('Date/Time'),
  field_types: [
    'duration',
  ],
)]
class DurationWidget extends CustomFieldWidgetBase {

  const DURATION_ELEMENT_OPTIONS = 'options';
  const DURATION_ELEMENT_INPUT = 'input';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'duration_element' => self::DURATION_ELEMENT_OPTIONS,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();
    $element['duration_element'] = [
      '#type' => 'select',
      '#title' => $this->t('Duration element'),
      '#description' => $this->t('Select the duration element to use. <em>Pre-defined options</em> will render a select form element with list of options derived from field settings while <em>Input fields</em> will display numeric inputs for entering a duration manually.'),
      '#options' => [
        self::DURATION_ELEMENT_OPTIONS => $this->t('Pre-defined options'),
        self::DURATION_ELEMENT_INPUT => $this->t('Input fields'),
      ],
      '#default_value' => $settings['duration_element'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $item = $items[$delta];
    $duration_value = $item->{$field->getName()};
    $duration_element = $this->getSetting('duration_element');

    // Return the select form element.
    if ($duration_element === self::DURATION_ELEMENT_OPTIONS) {
      $duration_options = $field->getFieldSettings()['duration_options'] ?? [];
      $options = [];
      foreach ($duration_options as $option) {
        $options[(int) $option['key']] = $option['label'];
      }
      if (empty($duration_value) || !array_key_exists($duration_value, $options)) {
        $duration_value = 'custom';
      }
      return [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => array_key_exists($duration_value, $options) ? $duration_value : NULL,
      ] + $element;
    }
    // Return the custom field duration element.
    else {
      $element['#theme_wrappers'] = ['fieldset', 'container'];
      return [
        '#type' => 'custom_field_duration',
        '#default_value' => $duration_value,
      ] + $element;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): ?int {
    return is_numeric($value) ? (int) $value : NULL;
  }

}
