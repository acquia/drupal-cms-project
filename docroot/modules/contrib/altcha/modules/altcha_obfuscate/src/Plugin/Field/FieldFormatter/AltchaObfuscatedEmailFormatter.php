<?php

namespace Drupal\altcha_obfuscate\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'altcha_obfuscate_email' formatter.
 */
#[FieldFormatter(
  id: 'altcha_obfuscated_email',
  label: new TranslatableMarkup('ALTCHA Obfuscated email'),
  field_types: [
    'email',
  ],
)]
class AltchaObfuscatedEmailFormatter extends AltchaObfuscatedStringFormatter {

  /**
   * {@inheritdoc}
   */
  protected function transformValue(string $value): string {
    return "mailto:$value";
  }

}
