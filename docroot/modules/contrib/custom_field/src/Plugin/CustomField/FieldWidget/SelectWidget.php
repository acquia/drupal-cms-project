<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'select' widget.
 */
#[CustomFieldWidget(
  id: 'select',
  label: new TranslatableMarkup('Select list'),
  category: new TranslatableMarkup('Lists'),
  field_types: [
    'string',
    'integer',
    'float',
  ],
)]
class SelectWidget extends ListWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    return [
      '#empty_option' => $settings['empty_option'],
    ] + $element;
  }

}
