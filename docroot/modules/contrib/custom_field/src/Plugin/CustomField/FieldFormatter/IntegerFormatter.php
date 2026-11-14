<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'number_integer' formatter.
 *
 * The 'Default' formatter is different for integer fields on the one hand, and
 * for decimal and float fields on the other hand, in order to be able to use
 * different settings.
 */
#[FieldFormatter(
  id: 'number_integer',
  label: new TranslatableMarkup('Default'),
  field_types: [
    'integer',
  ],
)]
class IntegerFormatter extends NumericFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'thousand_separator' => ',',
      'prefix_suffix' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function numberFormat(mixed $number): string {
    return number_format($number, 0, '', $this->getSetting('thousand_separator'));
  }

}
