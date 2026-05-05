<?php

namespace Drupal\custom_field_jsonapi\Normalizer;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\custom_field\Plugin\DataType\CustomFieldDatetime;
use Drupal\serialization\Normalizer\PrimitiveDataNormalizer;

/**
 * Converts the datetime custom field value to a JSON:API structure.
 */
class DateTimeNormalizer extends PrimitiveDataNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    assert($object instanceof CustomFieldDatetime);
    if ($object->getValue()) {
      $parent = $object->getParent();
      $name = $object->getName();
      $date = $parent->get($name . '__date')->getValue();
      return $date instanceof DrupalDateTime ? $this->toDateTimeType($date) : NULL;
    }

    return NULL;
  }

  /**
   * Convert a DrupalDateTime object to a date time type.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $value
   *   The DrupalDateTime object to convert.
   *
   * @return array
   *   The converted date time type as GraphQL expects.
   */
  protected function toDateTimeType(DrupalDateTime $value): array {
    return [
      'timestamp' => $value->getTimestamp(),
      'timezone' => $value->getTimezone()->getName(),
      'offset' => $value->format('P'),
      'time' => $value->format(\DateTime::RFC3339),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      CustomFieldDatetime::class => TRUE,
    ];
  }

}
