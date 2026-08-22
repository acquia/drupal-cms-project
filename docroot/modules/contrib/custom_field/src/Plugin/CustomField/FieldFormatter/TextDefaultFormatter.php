<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;

/**
 * Plugin implementation of the 'text_default' formatter.
 */
#[FieldFormatter(
  id: 'text_default',
  label: new TranslatableMarkup('Default'),
  field_types: [
    'string_long',
  ],
)]
class TextDefaultFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): mixed {
    $field_settings = $this->customFieldDefinition->getFieldSettings();
    $formatted = $field_settings['formatted'] ?? FALSE;
    if ($formatted) {
      // The ProcessedText element already handles cache context & tag bubbling.
      // @see \Drupal\filter\Element\ProcessedText::preRenderText()
      $build = [
        '#type' => 'processed_text',
        '#text' => $value,
        '#format' => $field_settings['default_format'],
        '#langcode' => $item->getLangcode(),
      ];
      $value = $build;
    }

    return $value;
  }

}
