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
  public function settingsSummary(string $property, mixed $value, int $indent): array {
    if (!\is_array($value) || empty($value)) {
      return parent::settingsSummary($property, $value, $indent);
    }
    $properties = $this->getSetting('items')['properties'] ?? [];
    $image_summaries = [];

    foreach ($value as $item) {
      if (!\is_array($item) || !isset($item['value'])) {
        continue;
      }

      // The pre-saved value may have extra wrapper.
      if (isset($item['value']['value']) && \is_array($item['value']['value'])) {
        $item['value'] = $item['value']['value'];
      }

      if (!\is_array($item['value']) || !\array_key_exists('src', $item['value'])) {
        continue;
      }

      $image_summaries[] = $this->t('@space-', [
        '@space' => $this->space($indent + 2),
      ]);

      // Iterate over $properties as the source of truth, so every defined
      // property appears in the summary, even if absent from $item.
      foreach ($properties as $key => $prop_definition) {
        $item_value = $item['value'][$key] ?? NULL;
        if ($item_value === NULL) {
          continue;
        }
        $prop_widget = $this->propWidgetManager->getPropWidget($prop_definition);
        if ($prop_widget) {
          $item_summary = $prop_widget->settingsSummary($key, $item_value, $indent + 4);
          foreach ($item_summary as $item_summary_line) {
            $image_summaries[] = $item_summary_line;
          }
        }
      }
    }
    if (empty($image_summaries)) {
      return parent::settingsSummary($property, [], $indent);
    }
    else {
      $summary = [
        $this->t('@space@property:', [
          '@space' => $this->space($indent),
          '@property' => $property,
        ]),
      ];
      foreach ($image_summaries as $item) {
        $summary[] = $item;
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value = [], $required = FALSE, array $context = []): array {
    $element = parent::widget($form, $form_state, $value, $required, $context);
    $settings = $this->getSettings() + static::defaultSettings();
    $widget = $this->propWidgetManager->getPropWidget($settings['items']);

    $element['value']['value'] = $widget->widget($form, $form_state, NULL, FALSE, $context);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageValue(array $value): array {
    $settings = $this->getSettings();
    $widget = $this->propWidgetManager->getPropWidget($settings['items']);

    foreach ($value['value'] as $key => $item) {
      // Each item should be an array with a nested 'value' key from the
      // managed_file element. Drop anything malformed.
      if (!\is_array($item) || !\is_array($item['value'] ?? NULL)) {
        unset($value['value'][$key]);
        continue;
      }

      $normalized = isset($item['widget']) ? $item : ($item['value'] ?? []);

      if (empty($normalized)) {
        unset($value['value'][$key]);
        continue;
      }

      // Delegate to PropWidgetImage::massageValue() which handles fids ->
      // fid normalization, file permanence, and src/alt/width/height.
      $massaged = $widget->massageValue($normalized);

      if (empty($massaged['value'])) {
        unset($value['value'][$key]);
      }
      else {
        $value['value'][$key] = $massaged;
      }
    }

    // Re-index after any removals so the array stays sequential.
    $value['value'] = array_values($value['value']);

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(mixed $value, array $context = []): ?array {
    if (!\is_array($value)) {
      return NULL;
    }

    foreach ($value as $delta => &$items) {
      if (!\is_array($items) || !isset($items['value'])) {
        unset($value[$delta]);
        continue;
      }
      $item_value = $items['value'];
      if (!$item_value) {
        unset($value[$delta]);
      }
      else {
        $value[$delta] = $item_value;
      }
    }

    return array_values($value);
  }

}
