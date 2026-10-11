<?php

namespace Drupal\custom_field_jsonapi\Normalizer;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\custom_field\Plugin\DataType\CustomFieldDateRange;

/**
 * Converts the daterange custom field value to a JSON:API structure.
 */
class DateRangeNormalizer extends DateTimeNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    assert($object instanceof CustomFieldDateRange);
    if ($object->getValue()) {
      $parent = $object->getParent();
      $name = $object->getName();
      $start_date = $parent->get($name . '__start_date')->getValue();
      $end_date = $parent->get($name . '__end_date')->getValue();
      $duration = $parent->get($name . '__duration')->getValue();
      if ($start_date instanceof DrupalDateTime) {
        return [
          'start' => $this->toDateTimeType($start_date),
          'end' => $end_date instanceof DrupalDateTime ? $this->toDateTimeType($end_date) : NULL,
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
      CustomFieldDateRange::class => TRUE,
    ];
  }

}
