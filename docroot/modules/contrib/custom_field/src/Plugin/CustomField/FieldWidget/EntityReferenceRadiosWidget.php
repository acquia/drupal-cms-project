<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'entity_reference_radios' widget.
 */
#[CustomFieldWidget(
  id: 'entity_reference_radios',
  label: new TranslatableMarkup('Radios'),
  category: new TranslatableMarkup('Reference'),
  field_types: [
    'entity_reference',
  ],
)]
class EntityReferenceRadiosWidget extends EntityReferenceOptionsWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'empty_option' => 'N/A',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    // Prevent default value form rendering unset options.
    if (!isset($element['#options'])) {
      return [];
    }
    $options = $element['#options'];
    $flattened_options = [];

    // Flatten the options array and preserve keys.
    foreach ($options as $group => $option_set) {
      if (is_array($option_set)) {
        $flattened_options += $option_set;
      }
      else {
        $flattened_options[$group] = $option_set;
      }
    }

    // Add an empty option if the field is not required.
    if (!$field->getFieldSetting('required')) {
      $flattened_options = ['' => $settings['empty_option']] + $flattened_options;
    }

    $element['#type'] = 'radios';
    $element['#options'] = $flattened_options;

    return ['target_id' => $element];
  }

}
