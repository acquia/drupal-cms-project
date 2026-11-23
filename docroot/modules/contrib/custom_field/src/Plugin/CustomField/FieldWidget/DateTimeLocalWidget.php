<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'datetime_local' widget.
 */
#[CustomFieldWidget(
  id: 'datetime_local',
  label: new TranslatableMarkup('Date and time (local)'),
  category: new TranslatableMarkup('Date'),
  field_types: [
    'datetime',
  ],
)]
class DateTimeLocalWidget extends DateTimeWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $field_settings = $field->getFieldSettings();

    $element['value']['#type'] = 'custom_field_datetime_date';
    $element['value'] += [
      '#date_date_format' => DateTimeTypeInterface::DATETIME_STORAGE_FORMAT,
      '#date_date_element' => 'datetime-local',
      '#date_date_callbacks' => [],
      '#date_time_format' => '',
      '#date_time_element' => 'none',
      '#date_time_callbacks' => [],
    ];
    if ($field_settings['timezone_enabled']) {
      $element['#theme'] = 'custom_field_flex_wrapper';
      $element['#theme_wrappers'] = ['fieldset', 'container'];
      $element['value']['#title'] = $this->t('Date');
      $element['value']['#description'] = NULL;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(CustomFieldTypeInterface $custom_item): bool {
    return $custom_item->getDatetimeType() === DateTimeType::DATETIME_TYPE_DATETIME;
  }

}
