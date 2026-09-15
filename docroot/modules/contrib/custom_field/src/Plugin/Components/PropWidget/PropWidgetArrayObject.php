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
  public function settingsSummary(string $property, mixed $value, int $indent): array {
    if (!\is_array($value) || empty($value)) {
      return parent::settingsSummary($property, $value, $indent);
    }
    $properties = $this->getSetting('items')['properties'] ?? [];
    $summary = [
      $this->t('@space@property:', [
        '@space' => $this->space($indent),
        '@property' => $property,
      ]),
    ];

    foreach ($value as $item) {
      if (!\is_array($item)) {
        continue;
      }
      $summary[] = $this->t('@space-', [
        '@space' => $this->space($indent + 2),
      ]);

      // Iterate over $properties as the source of truth, so every defined
      // property appears in the summary, even if absent from $item.
      foreach ($properties as $key => $prop_definition) {
        $item_value = $item[$key] ?? NULL;

        // If the key is missing or malformed, show an empty placeholder.
        if (!\is_array($item_value) || !isset($item_value['value'])) {
          $summary[] = $this->t('@space@key: @value', [
            '@space' => $this->space($indent + 4),
            '@key' => $key,
            '@value' => self::EMPTY_VALUE,
          ]);
        }
        else {
          $prop_widget = $this->propWidgetManager->getPropWidget($prop_definition);
          if ($prop_widget) {
            $item_summary = $prop_widget->settingsSummary($key, $item_value['value'], $indent + 4);
            foreach ($item_summary as $item_summary_line) {
              $summary[] = $item_summary_line;
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
  public function widget(array &$form, FormStateInterface $form_state, $value = [], $required = FALSE, array $context = []): array {
    $element = parent::widget($form, $form_state, $value, $required, $context);
    $settings = $this->getSettings() + static::defaultSettings();
    $properties = $settings['items']['properties'];
    $properties_required = $settings['items']['required'] ?? [];
    $id = $settings['items']['id'] ?? NULL;
    if ($id === 'json-schema-definitions://canvas.module/image') {
      return $element;
    }

    $element['value']['#element_validate'][] = [static::class, 'validateArrayValues'];
    foreach ($properties as $property => $property_info) {
      $widget = $this->propWidgetManager->getPropWidget($property_info);
      if (!$widget) {
        continue;
      }
      $is_required = \in_array($property, $properties_required);
      $element['value'][$property] = $widget->widget($form, $form_state, NULL, $is_required, $context);
    }

    return $element;
  }

  /**
   * The #element_validate callback for array_object field array values.
   *
   * @param array<string, mixed> $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form for the form this element belongs to.
   *
   * @see \Drupal\Core\Render\Element\FormElement::processPattern()
   */
  public static function validateArrayValues(array $element, FormStateInterface $form_state): void {
    $values = $form_state->getValue($element['#parents']);

    if (!\is_array($values)) {
      return;
    }

    $values = self::stripIgnoredKeys($values);

    foreach ($values as $delta => &$item) {
      if (!\is_array($item)) {
        unset($values[$delta]);
        continue;
      }

      $all_empty = !empty($item) && array_reduce(
        $item,
        function (bool $carry, mixed $field): bool {
          return $carry && \is_array($field) && self::isEmptyValue($field['value'] ?? NULL);
        },
        TRUE
      );

      if ($all_empty) {
        unset($values[$delta]);
      }
    }

    $form_state->setValueForElement($element, \array_values($values));
  }

  /**
   * Determines if a value is considered empty.
   *
   * Recursively checks arrays.
   *
   * @param mixed $value
   *   The value to check.
   *
   * @return bool
   *   TRUE if the value is empty, FALSE otherwise.
   */
  private static function isEmptyValue(mixed $value): bool {
    if (!\is_array($value)) {
      return $value === '' || $value === NULL;
    }

    foreach ($value as $item) {
      if (\is_scalar($item)) {
        return $item === '';
      }
      if (\is_array($item)) {
        if (!self::isEmptyValue($item)) {
          return FALSE;
        }
      }
      elseif (isset($item['value'])) {
        if (!self::isEmptyValue($item['value'])) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function massageValue(array $value): array {
    $properties = $this->getSetting('items')['properties'] ?? [];

    $value = self::stripIgnoredKeys($value);

    foreach ($value['value'] as $delta => $item) {
      foreach ($properties as $key => $property) {
        $item_value = $item[$key] ?? NULL;
        if ($item_value) {
          $widget = $this->propWidgetManager->getPropWidget($property);
          if ($widget) {
            $value['value'][$delta][$key] = $widget->massageValue($item_value);
          }
        }
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(mixed $value, array $context = []): ?array {
    if (!\is_array($value)) {
      return NULL;
    }

    $properties = $this->getSetting('items')['properties'] ?? [];
    $required = $this->getSetting('items')['required'] ?? [];

    foreach ($value as $delta => &$items) {
      if (!\is_array($items)) {
        unset($value[$delta]);
      }
      foreach ($items as $key => $item) {
        $property_info = $properties[$key] ?? [];
        $widget = $this->propWidgetManager->getPropWidget($property_info);
        if (!$widget) {
          continue;
        }
        $prop_value = $widget->getPropValue($item['value'] ?? NULL, $context);
        if (in_array($key, $required) && ($prop_value === NULL || $prop_value === '')) {
          unset($value[$delta]);
          continue;
        }
        if ($prop_value === NULL || $prop_value === '') {
          unset($items[$key]);
          continue;
        }
        $items[$key] = $prop_value;
      }
    }

    return $value;
  }

}
