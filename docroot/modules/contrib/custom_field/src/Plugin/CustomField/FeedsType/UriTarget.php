<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'uri' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'uri',
  label: new TranslatableMarkup('Uri'),
  mark_unique: TRUE,
)]
class UriTarget extends BaseTarget {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): ?string {
    $value = trim((string) $value);

    // Support linking to nothing.
    if (in_array($value, ['<nolink>', '<none>', '<button>'], TRUE)) {
      $value = 'route:' . $value;
    }

    // Detect a schemeless string, map to 'internal:' URI.
    elseif (!empty($value) && parse_url($value, PHP_URL_SCHEME) === NULL) {
      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      // - '<front>' -> '/'
      // - '<front>#foo' -> '/#foo'
      if (str_starts_with($value, '<front>')) {
        $value = '/' . substr($value, strlen('<front>'));
      }
      // Prepend only with 'internal:' if the uri starts with '/', '?' or '#'.
      if (in_array($value[0], ['/', '?', '#'], TRUE)) {
        $value = 'internal:' . $value;
      }
    }
    // Test for valid url.
    elseif (!filter_var($value, FILTER_VALIDATE_URL)) {
      return NULL;
    }

    return $value;
  }

}
