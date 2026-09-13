<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;
use Drupal\custom_field\Time;

/**
 * Plugin implementation of the 'time_widget' custom field widget.
 */
#[CustomFieldWidget(
  id: 'time_widget',
  label: new TranslatableMarkup('Time'),
  category: new TranslatableMarkup('Time'),
  field_types: [
    'time',
  ],
)]
class TimeWidget extends CustomFieldWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $field_settings = $field->getFieldSettings();

    $item = $items[$delta];
    $time = $item->{$field->getName()} ?? NULL;

    // Determine if we're showing seconds in the widget.
    $show_seconds = (bool) $field_settings['seconds_enabled'];
    $additional = [
      '#type' => 'time_cf',
      '#default_value' => Time::createFromTimestamp($time)?->formatForWidget($show_seconds),
    ];

    // We need this to be a correct Time also.
    $element['#default_value'] = Time::createFromTimestamp($element['#default_value'])?->formatForWidget($show_seconds);

    // Add the step attribute if we're showing seconds in the widget.
    if ($show_seconds) {
      $additional['#attributes']['step'] = $field_settings['seconds_step'];
    }
    // Set a property to determine the format in TimeElement::preRenderTime().
    $additional['#show_seconds'] = $show_seconds;

    return $element + $additional;
  }

}
