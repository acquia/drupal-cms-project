<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'radios' widget.
 */
#[CustomFieldWidget(
  id: 'radios',
  label: new TranslatableMarkup('Radios'),
  category: new TranslatableMarkup('Lists'),
  field_types: [
    'string',
    'integer',
    'float',
  ],
)]
class RadiosWidget extends ListWidgetBase {

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
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $field_settings = $field->getFieldSettings();
    $settings = $this->getSettings() + static::defaultSettings();

    // Add our widget type and additional properties and return.
    $element['#type'] = 'radios';
    if (!$field_settings['required']) {
      $options = $element['#options'];
      $options = ['' => $settings['empty_option']] + $options;
      $element['#options'] = $options;
    }

    return $element;
  }

}
