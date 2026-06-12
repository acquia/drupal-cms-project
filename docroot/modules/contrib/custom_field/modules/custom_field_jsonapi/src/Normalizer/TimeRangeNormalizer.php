<?php

namespace Drupal\custom_field_jsonapi\Normalizer;

use Drupal\custom_field\Plugin\DataType\CustomFieldTimeRange;

/**
 * Converts the time_range custom field value to a JSON:API structure.
 */
class TimeRangeNormalizer extends DateTimeNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    assert($object instanceof CustomFieldTimeRange);
    if ($object->getValue()) {
      $parent = $object->getParent();
      $name = $object->getName();
      $start = $parent->get($name)->getValue();
      $end = $parent->get($name . '__end')->getValue();
      $duration = $parent->get($name . '__duration')->getValue();
      if (is_numeric($start)) {
        return [
          'start' => (int) $start,
          'end' => is_numeric($end) ? (int) $end : NULL,
          'duration' => is_numeric($duration) ? (int) $duration : NULL,
        ];
      }
      return NULL;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      CustomFieldTimeRange::class => TRUE,
    ];
  }

}
