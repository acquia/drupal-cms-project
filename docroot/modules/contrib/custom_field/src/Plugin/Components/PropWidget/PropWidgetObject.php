<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Plugin\PropWidgetBase;

/**
 * Plugin implementation of the 'object' widget.
 */
#[PropWidget(
  id: 'object',
  prop_type: 'object',
  label: new TranslatableMarkup('Object'),
)]
class PropWidgetObject extends PropWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'properties' => [],
      'required' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $property, mixed $value, int $indent): array {
    if (!\is_array($value) || empty($value)) {
      return parent::settingsSummary($property, $value, $indent);
    }
    $summary = [
      $this->t('@space@property:', [
        '@space' => $this->space($indent),
        '@property' => $property,
      ]),
    ];
    foreach ($value as $key => $item_value) {
      $widget = $item_value['widget'];
      if (is_array($item_value['value'])) {
        continue;
      }
      if ($widget === 'boolean') {
        $item_value['value'] = $item_value['value'] ? self::BOOLEAN_TRUE : self::BOOLEAN_FALSE;
      }
      $summary[] = $this->t('@space@key: @value', [
        '@space' => $this->space($indent + 2),
        '@key' => $key,
        '@value' => $item_value['value'] ?: self::EMPTY_VALUE,
      ]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value, $required, array $context = []): array {
    $element = parent::widget($form, $form_state, $value, $required, $context);
    $settings = $this->getSettings() + static::defaultSettings();
    $properties = $settings['properties'];
    $required_properties = $settings['required'] ?? [];
    $element['value']['#type'] = 'details';
    if (!\is_array($value)) {
      $value = [];
    }
    $value = $value['value'] ?? [];
    foreach ($properties as $property => $property_info) {
      $title = $property_info['title'] ?? ucfirst($property);
      $property_info['title'] = $title;
      $plugin = $this->propWidgetManager->getPropWidget($property_info);
      if (!$plugin) {
        continue;
      }
      $is_required = \in_array($property, $required_properties);
      $element['value'][$property] = $plugin->widget($form, $form_state, $value[$property] ?? NULL, $is_required, $context);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageValue(array $value): array {
    $properties = $this->getSetting('properties') ?? [];

    $value = self::stripIgnoredKeys($value);

    foreach ($value['value'] as $key => $item) {
      $property_info = $properties[$key] ?? [];
      $widget = $this->propWidgetManager->getPropWidget($property_info);
      if ($widget && isset($value['value'][$key])) {
        $value['value'][$key] = $widget->massageValue($item);
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

    $properties = $this->getSetting('properties') ?? [];
    $required = $this->getSetting('required') ?? [];
    $is_valid = TRUE;
    foreach ($value as $key => $item) {
      $property_info = $properties[$key] ?? [];
      $widget = $this->propWidgetManager->getPropWidget($property_info);
      $prop_value = $widget->getPropValue($item['value'] ?? NULL, $context);
      if (in_array($key, $required) && ($prop_value === NULL || $prop_value === '')) {
        $is_valid = FALSE;
        break;
      }
      if ($prop_value === NULL || $prop_value === '') {
        unset($value[$key]);
      }
      else {
        $value[$key] = $prop_value;
      }
    }
    if (!$is_valid) {
      return NULL;
    }

    return $value;
  }

}
