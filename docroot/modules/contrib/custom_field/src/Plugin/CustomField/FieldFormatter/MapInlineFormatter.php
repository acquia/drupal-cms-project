<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;

/**
 * Plugin implementation of the 'map_inline' formatter.
 */
#[FieldFormatter(
  id: 'map_inline',
  label: new TranslatableMarkup('Inline'),
  field_types: [
    'map_string',
  ],
)]
class MapInlineFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'item_separator' => ', ',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements['item_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Item separator'),
      '#default_value' => $this->getSetting('item_separator'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?array {
    if (!\is_array($value) || empty($value)) {
      return NULL;
    }

    $separator = Xss::filterAdmin($this->getSetting('item_separator'));

    $safe_values = \array_map(
      fn($v) => Html::escape((string) $v),
      $value
    );

    return [
      '#markup' => \implode($separator, $safe_values),
    ];
  }

}
