<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the custom_list formatter.
 */
#[FieldFormatter(
  id: 'custom_list',
  label: new TranslatableMarkup('HTML list'),
  description: new TranslatableMarkup('Renders the items as an item list.'),
  field_types: [
    'custom',
  ],
  weight: 3,
)]
class CustomListFormatter extends BaseFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'list_type' => 'ul',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['list_type'] = [
      '#type' => 'select',
      '#title' => $this->t('List type'),
      '#options' => [
        'ul' => $this->t('Unordered list'),
        'ol' => $this->t('Numbered list'),
      ],
      '#default_value' => $this->getSetting('list_type'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $options = [
      'ul' => $this->t('Unordered list'),
      'ol' => $this->t('Numbered list'),
    ];
    $summary[] = $this->t('List type: @type', ['@type' => $options[$this->getSetting('list_type')]]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }

    $field_name = $this->fieldDefinition->getName();
    $class = Html::cleanCssIdentifier($field_name);
    $elements = [];
    foreach ($items as $delta => $item) {
      if (!$item->isEmpty()) {
        $value = $this->viewValue($item, $langcode);
        if (!empty($value['#items'])) {
          $elements[$delta] = $this->viewValue($item, $langcode);
        }
      }
    }
    if (!empty($elements)) {
      return [
        [
          '#theme' => 'item_list',
          '#items' => $elements,
          '#list_type' => $this->getSetting('list_type'),
          '#attributes' => [
            'class' => [$class, $class . '--list'],
          ],
        ],
      ];
    }

    return [];
  }

}
