<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "custom_field_daterange",
 *   type_sdl = "CustomFieldDateRange",
 * )
 */
class CustomFieldDateRange extends CustomFieldDateTime implements FieldProducerItemInterface {

  use FieldProducerTrait;

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    $property = $context->getContextValue('property_name');
    $separator = '__';
    $start = $item->{$property . $separator . 'start_date'} ? $item->{$property} : NULL;
    $end = $item->{$property . $separator . 'end_date'} ? $item->{$property . $separator . 'end'} : NULL;
    $duration = $item->{$property . $separator . 'duration'} ?? 0;
    $start_value = $this->toDrupalDateTime($start);
    $end_value = $this->toDrupalDateTime($end);
    $start_date = $start_value ? $this->toDateTimeType($start_value) : NULL;
    if (!$start_date) {
      return NULL;
    }
    $end_date = $end_value ? $this->toDateTimeType($end_value) : NULL;

    return [
      'start' => $start_date,
      'end' => $end_date,
      'duration' => (int) $duration,
    ];
  }

}
