<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "custom_field_time_range",
 *   type_sdl = "CustomFieldTimeRange",
 * )
 */
class CustomFieldTimeRange extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface {

  use FieldProducerTrait;

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    $property = $context->getContextValue('property_name');
    $separator = '__';
    $start = $item->{$property};
    if (!$start) {
      return NULL;
    }
    $end = $item->{$property . $separator . 'end'};
    $duration = $item->{$property . $separator . 'duration'} ?? 0;

    return [
      'start' => $start,
      'end' => $end ? (int) $end : NULL,
      'duration' => (int) $duration,
    ];
  }

}
