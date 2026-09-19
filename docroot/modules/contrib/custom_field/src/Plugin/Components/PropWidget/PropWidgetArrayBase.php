<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Components\PropWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_field\Plugin\PropWidgetBase;

/**
 * Base plugin class for array prop widgets.
 */
class PropWidgetArrayBase extends PropWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'maxItems' => '',
      'items' => [
        'type' => '',
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

    $summary = [
      $this->t('@space@property:', [
        '@space' => $this->space($indent),
        '@property' => $property,
      ]),
    ];
    foreach ($value as $item) {
      $summary[] = $this->t('@space - @value', [
        '@space' => $this->space($indent + 2),
        '@value' => $item,
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value, $required, array $context = []): array {
    $settings = $this->getSettings() + static::defaultSettings();
    $value = \is_array($value) ? $value : [];
    $element = [
      '#type' => 'container',
      'widget' => [
        '#type' => 'hidden',
        '#value' => $this->getPluginId(),
      ],
      'value' => [
        '#type' => 'custom_field_multivalue',
        '#title' => $settings['title'],
        '#description' => $settings['description'],
        '#default_value' => $value['value'] ?? [],
        '#required' => !empty($required),
      ],
    ];
    if (!empty($settings['maxItems'])) {
      $element['value']['#cardinality'] = $settings['maxItems'];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(mixed $value, array $context = []): ?array {
    if (!is_array($value) || empty($value)) {
      return NULL;
    }

    $filtered = array_values(array_filter($value, fn($item) => $this->isValidItem($item)));

    return !empty($filtered) ? $filtered : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function massageValue(array $value): array {
    if (!isset($value['value']) || !is_array($value['value'])) {
      $value['value'] = [];
      return $value;
    }

    $filtered = array_values(array_filter(
      $value['value'],
      fn($item) => $this->isValidItem($item)
    ));

    $value['value'] = $filtered;

    return $value;
  }

  /**
   * Determines whether an individual array item is valid for this widget type.
   *
   * Subclasses should override this method to apply type-specific validation.
   *
   * @param mixed $item
   *   The item to validate.
   *
   * @return bool
   *   TRUE if the item is valid, FALSE otherwise.
   */
  protected function isValidItem(mixed $item): bool {
    return !empty($item);
  }

}
