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
 * Plugin implementation of the 'checkbox' widget.
 */
#[CustomFieldWidget(
  id: 'checkbox',
  label: new TranslatableMarkup('Checkbox'),
  category: new TranslatableMarkup('General'),
  field_types: [
    'boolean',
  ],
)]
class CheckboxWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $item = $items[$delta];

    // Add our widget type and additional properties and return.
    return [
      '#type' => 'checkbox',
      '#default_value' => !empty($item->{$field->getName()}),
    ] + $element;
  }

}
