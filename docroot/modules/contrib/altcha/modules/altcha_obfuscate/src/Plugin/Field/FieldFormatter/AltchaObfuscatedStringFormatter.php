<?php

namespace Drupal\altcha_obfuscate\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'altcha_obfuscate_string' formatter.
 */
#[FieldFormatter(
  id: 'altcha_obfuscated_string',
  label: new TranslatableMarkup('ALTCHA Obfuscated string'),
  field_types: [
    'string',
  ],
)]
class AltchaObfuscatedStringFormatter extends AltchaObfuscatedFormatterBase {

  /**
   * {@inheritdoc}
   */
  protected function transformValue(string $value): string {
    return $value;
  }

}
