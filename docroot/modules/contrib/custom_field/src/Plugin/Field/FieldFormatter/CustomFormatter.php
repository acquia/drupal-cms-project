<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'custom_formatter' formatter.
 */
#[FieldFormatter(
  id: 'custom_formatter',
  label: new TranslatableMarkup('Default'),
  description: new TranslatableMarkup('Generic formatter, renders the items using the custom_field theme hook implementation.'),
  field_types: [
    'custom',
  ],
  weight: 0,
)]
class CustomFormatter extends BaseFormatter {
}
