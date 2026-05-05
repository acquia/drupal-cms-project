<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Trait\EnumTrait;

/**
 * Plugin implementation of the 'array_image' widget.
 */
#[PropWidget(
  id: 'array_image',
  prop_type: 'array',
  items_types: ['object'],
  label: new TranslatableMarkup('Array image'),
)]
class PropWidgetArrayImage extends PropWidgetArrayObject {

  use EnumTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'maxItems' => '',
      'items' => [
        'id' => '',
        'type' => '',
        'properties' => [],
        'required' => [],
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $property, mixed $value): array {
    if (!\is_array($value) || empty($value)) {
      return parent::settingsSummary($property, $value);
    }
    $summary = [
      $this->t('@space@property:', [
        '@space' => $this->space(),
        '@property' => $property,
      ]),
    ];
    foreach ($value as $item) {
      $summary[] = $this->t('@space-', [
        '@space' => $this->space(4),
      ]);
      foreach ($item as $key => $item_value) {
        $widget = $item_value['widget'];
        if ($widget === 'image' && is_array($item_value['value'] ?? NULL)) {
          foreach ($item_value['value'] as $sub_key => $sub_value) {
            if (in_array($sub_key, ['src', 'alt', 'width', 'height'])) {
              $summary[] = $this->t('@space@key: @value', [
                '@space' => $this->space(8),
                '@key' => $sub_key,
                '@value' => $sub_value ?: self::EMPTY_VALUE,
              ]);
            }
          }
        }
      }
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value = [], $required = FALSE): array {
    $element = parent::widget($form, $form_state, $value, $required);
    $settings = $this->getSettings() + static::defaultSettings();
    $id = $settings['items']['id'] ?? NULL;
    if ($id === 'json-schema-definitions://canvas.module/image') {
      $widget = $this->propWidgetManager->getPropWidget($settings['items']);
      $element['value']['value'] = $widget->widget($form, $form_state, NULL, FALSE);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageValue(array $value): array {
    $settings = $this->getSettings();
    foreach ($value['value'] as $key => $item) {
      $item_value = $item['value'] ?? NULL;
      if (!\is_array($item_value) || !isset($item_value['widget'])) {
        unset($value['value'][$key]);
        continue;
      }
      $widget = $this->propWidgetManager->getPropWidget($settings['items']);
      $image = $widget->massageValue($item_value);
      if (empty($image['value'])) {
        unset($value['value'][$key]);
      }
      else {
        $value['value'][$key]['value'] = $image;
      }
    }

    return $value;
  }

}
