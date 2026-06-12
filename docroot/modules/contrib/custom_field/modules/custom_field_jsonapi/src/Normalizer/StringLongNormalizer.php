<?php

namespace Drupal\custom_field_jsonapi\Normalizer;

use Drupal\custom_field\Plugin\DataType\CustomFieldStringLongInterface;
use Drupal\serialization\Normalizer\PrimitiveDataNormalizer;

/**
 * Converts the string_long custom field value to a JSON:API structure.
 */
class StringLongNormalizer extends PrimitiveDataNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    if ($value = $object->getValue()) {
      return [
        'value' => (string) $value,
        'processed' => $object->getProcessed(),
      ];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      CustomFieldStringLongInterface::class => TRUE,
    ];
  }

}
