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
      $summary[] = $this->t('@space - @value', [
        '@space' => $this->space(4),
        '@value' => $item,
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value, $required): array {
    $settings = $this->getSettings() + static::defaultSettings();
    $value = \is_array($value) ? $value : [];
    $element = [
      '#type' => 'container',
      'widget' => [
        '#type' => 'value',
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

}
