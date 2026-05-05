<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Trait\EnumTrait;

/**
 * Plugin implementation of the 'array_object' widget.
 */
#[PropWidget(
  id: 'array_object',
  prop_type: 'array',
  items_types: ['object'],
  label: new TranslatableMarkup('Array object'),
)]
class PropWidgetArrayObject extends PropWidgetArrayBase {

  use EnumTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'maxItems' => '',
      'items' => [
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
        if (is_scalar($item_value['value'])) {
          if ($widget === 'boolean') {
            $item_value['value'] = $item_value['value'] ? self::BOOLEAN_TRUE : self::BOOLEAN_FALSE;
          }
          $summary[] = $this->t('@space@key: @value', [
            '@space' => $this->space(8),
            '@key' => $key,
            '@value' => $item_value['value'] ?: self::EMPTY_VALUE,
          ]);
        }
        elseif ($widget === 'image' && is_array($item_value['value'] ?? NULL)) {
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
    $properties = $settings['items']['properties'];
    $properties_required = $settings['items']['required'] ?? [];
    $id = $settings['items']['id'] ?? NULL;
    if ($id === 'json-schema-definitions://canvas.module/image') {
      $widget = $this->propWidgetManager->getPropWidget($settings['items']);
      $element['value']['value'] = $widget->widget($form, $form_state, NULL, FALSE);
    }

    else {
      foreach ($properties as $property => $property_info) {
        $widget = $this->propWidgetManager->getPropWidget($property_info);
        if (!$widget) {
          continue;
        }
        $is_required = \in_array($property, $properties_required);
        $element['value'][$property] = $widget->widget($form, $form_state, NULL, $is_required);
      }
    }

    return $element;
  }

}
