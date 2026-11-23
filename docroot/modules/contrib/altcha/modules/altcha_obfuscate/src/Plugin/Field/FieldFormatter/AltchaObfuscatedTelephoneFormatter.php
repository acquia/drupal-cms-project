<?php

namespace Drupal\altcha_obfuscate\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'altcha_obfuscate_telephone' formatter.
 */
#[FieldFormatter(
  id: 'altcha_obfuscated_telephone',
  label: new TranslatableMarkup('ALTCHA Obfuscated telephone'),
  field_types: [
    'telephone',
  ],
)]
class AltchaObfuscatedTelephoneFormatter extends AltchaObfuscatedStringFormatter {

  /**
   * {@inheritdoc}
   */
  protected function transformValue(string $value): string {
    return "tel:$value";
  }

}
