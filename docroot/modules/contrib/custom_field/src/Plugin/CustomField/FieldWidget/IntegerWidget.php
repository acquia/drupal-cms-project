<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'integer' widget.
 */
#[CustomFieldWidget(
  id: 'integer',
  label: new TranslatableMarkup('Integer'),
  category: new TranslatableMarkup('Number'),
  field_types: [
    'integer',
  ],
)]
class IntegerWidget extends NumberWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $field_settings = $field->getFieldSettings();

    $min_setting = $field_settings['min'] ?? NULL;
    // Make sure we force positive numbers when unsigned.
    if ($field->isUnsigned() && (!is_numeric($min_setting) || $min_setting < 0)) {
      $element['#min'] = 0;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, $column): mixed {
    if (!is_numeric($value)) {
      return NULL;
    }

    return $value;
  }

}
