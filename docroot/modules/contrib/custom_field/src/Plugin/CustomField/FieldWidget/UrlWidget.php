<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;

/**
 * Plugin implementation of the 'url' widget.
 */
#[CustomFieldWidget(
  id: 'url',
  label: new TranslatableMarkup('Url'),
  category: new TranslatableMarkup('Url'),
  field_types: [
    'uri',
  ],
)]
class UrlWidget extends UrlWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $settings['placeholder'],
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    // Overrides for this widget.
    $element['#type'] = 'container';
    $element['uri']['#placeholder'] = $settings['placeholder'];
    if (!empty($element['#description'])) {
      // If we have the description of the type of field together with
      // the user provided description, we want to make a distinction
      // between "core help text" and "user entered help text". To make
      // this distinction more clear, we put them in an unordered list.
      $element['uri']['#description'] = [
        '#theme' => 'item_list',
        '#items' => [
          // Assume the user-specified description has the most relevance,
          // so place it first.
          $element['#description'],
          $element['uri']['#description'],
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, $column): ?string {
    if (empty($value['uri'])) {
      return NULL;
    }
    $value['uri'] = static::getUserEnteredStringAsUri($value['uri']);

    return $value['uri'];
  }

}
