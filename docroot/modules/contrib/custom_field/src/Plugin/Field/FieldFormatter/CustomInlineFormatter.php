<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'custom_inline' formatter.
 */
#[FieldFormatter(
  id: 'custom_inline',
  label: new TranslatableMarkup('Inline'),
  description: new TranslatableMarkup('Renders the items inline using a simple separator and no additional wrapper markup'),
  field_types: [
    'custom',
  ],
  weight: 1,
)]
class CustomInlineFormatter extends BaseFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'show_labels' => FALSE,
      'label_separator' => ': ',
      'item_separator' => ', ',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $id = 'customfield-show-labels';

    $form['show_labels'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show labels?'),
      '#default_value' => $this->getSetting('show_labels'),
      '#attributes' => [
        'data-id' => $id,
      ],
    ];
    $form['label_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label separator'),
      '#default_value' => $this->getSetting('label_separator'),
      '#states' => [
        'visible' => [
          ':input[data-id="' . $id . '"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['item_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Item separator'),
      '#default_value' => $this->getSetting('item_separator'),
    ];
    foreach ($this->getCustomFieldItems() as $name => $item) {
      // Remove non-applicable settings.
      unset($form['fields'][$name]['content']['formatter_settings']['label_display']);
      unset($form['fields'][$name]['content']['wrappers']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $summary[] = $this->t('Show labels: @show_labels', ['@show_labels' => $this->getSetting('label_display') ? 'Yes' : 'No']);
    if ($this->getSetting('label_display')) {
      $summary[] = $this->t('Label separator: @sep', ['@sep' => $this->getSetting('label_separator')]);
    }
    $summary[] = $this->t('Item separator: @sep', ['@sep' => $this->getSetting('item_separator')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, mixed>
   *   The render array of the field and subfields.
   */
  public function viewValue(FieldItemInterface $item, string $langcode): array {
    $field_name = $this->fieldDefinition->getName();
    $output = [];
    $values = $this->getFormattedValues($item, $langcode);
    $valid_items = [];

    // Force no wrappers.
    $inline_wrappers = [
      'field_wrapper_tag' => 'none',
      'field_tag' => 'none',
      'label_tag' => 'none',
    ];

    foreach ($values as $value) {
      if ($value === NULL || $value['type'] === 'map') {
        continue;
      }

      // Build the render array for each item.
      $item_render = [
        '#theme' => 'custom_field_item',
        '#field_name' => $field_name,
        '#name' => $value['name'],
        '#value' => $value['value'],
        '#label' => $value['label'],
        '#label_display' => 'hidden',
        '#type' => $value['type'],
        '#wrappers' => $inline_wrappers,
        '#entity_type' => $value['entity_type'],
        '#lang_code' => $langcode,
      ];

      if ($this->getSetting('show_labels')) {
        $valid_items[] = [
          '#type' => 'inline_template',
          '#template' => '{{ label }}{{ separator }}{{ item }}',
          '#context' => [
            'label' => $value['label'],
            'separator' => $this->getSetting('label_separator'),
            'item' => $item_render,
          ],
        ];
      }
      else {
        $valid_items[] = $item_render;
      }
    }

    // Now build the output with separators between items.
    foreach ($valid_items as $index => $item_render) {
      $output[] = $item_render;

      // Add the item_separator after each item except the last one.
      if ($index < count($valid_items) - 1) {
        $output[] = [
          '#markup' => Xss::filterAdmin((string) $this->getSetting('item_separator')),
        ];
      }
    }

    return $output;
  }

}
