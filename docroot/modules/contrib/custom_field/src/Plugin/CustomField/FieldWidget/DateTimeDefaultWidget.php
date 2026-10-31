<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'datetime_default' widget.
 */
#[CustomFieldWidget(
  id: 'datetime_default',
  label: new TranslatableMarkup('Date and time'),
  category: new TranslatableMarkup('Date'),
  field_types: [
    'datetime',
  ],
)]
class DateTimeDefaultWidget extends DateTimeWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $date_storage = $this->entityTypeManager->getStorage('date_format');
    $datetime_type = $field->getDatetimeType();

    // Wrap date and time elements with a fieldset.
    if ($datetime_type === DateTimeType::DATETIME_TYPE_DATETIME) {
      $element['#title'] = $element['value']['#title'];
      $element['#theme'] = 'custom_field_flex_wrapper';
      $element['#theme_wrappers'] = ['fieldset', 'container'];
      $element['value']['#type'] = 'custom_field_datetime';
      $element['value']['#theme_wrappers'] = [];
    }
    else {
      $element['value']['#type'] = 'custom_field_datetime_date';
    }

    // Identify the type of date and time elements to use.
    $date_format = $date_storage->load('html_date')->getPattern();
    switch ($datetime_type) {
      case DateTimeType::DATETIME_TYPE_DATE:
        $time_type = 'none';
        $time_format = '';
        break;

      default:
        $time_type = 'time';
        $time_format = $date_storage->load('html_time')->getPattern();
        break;
    }

    $element['value'] += [
      '#date_date_format' => $date_format,
      '#date_date_element' => 'date',
      '#date_date_callbacks' => [],
      '#date_time_format' => $time_format,
      '#date_time_element' => $time_type,
      '#date_time_callbacks' => [],
    ];

    return $element;
  }

}
