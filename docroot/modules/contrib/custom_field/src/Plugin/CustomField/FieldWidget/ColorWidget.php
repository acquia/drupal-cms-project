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
 * Plugin implementation of the 'color' widget.
 */
#[CustomFieldWidget(
  id: 'color',
  label: new TranslatableMarkup('Color (default)'),
  category: new TranslatableMarkup('Color'),
  field_types: [
    'color',
  ],
)]
class ColorWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);

    // Add our widget type and additional properties and return.
    return [
      '#type' => 'color',
      '#maxlength' => 7,
      '#size' => 7,
    ] + $element;
  }

}
