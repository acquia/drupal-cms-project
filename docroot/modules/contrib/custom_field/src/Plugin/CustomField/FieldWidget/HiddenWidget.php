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
 * Plugin implementation of the 'hidden' widget.
 */
#[CustomFieldWidget(
  id: 'hidden',
  label: new TranslatableMarkup('Hidden'),
  category: new TranslatableMarkup('General'),
  field_types: [
    'boolean',
    'color',
    'daterange',
    'datetime',
    'decimal',
    'duration',
    'email',
    'entity_reference',
    'file',
    'float',
    'image',
    'integer',
    'link',
    'map',
    'map_string',
    'string',
    'string_long',
    'telephone',
    'time',
    'time_range',
    'uri',
    'viewfield',
  ],
)]
class HiddenWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $item = $items[$delta];

    // Add our widget type and additional properties and return.
    return [
      '#type' => 'value',
      '#value' => $item->{$field->getName()},
    ] + $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element['description'] = [
      '#type' => 'fieldset',
      '#description' => $this->t('This widget will be hidden in forms and allow value to be set programmatically.'),
    ];

    return $element;
  }

}
