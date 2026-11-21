<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "CustomFieldDateRange",
 * )
 */
class CustomFieldDateRange extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('A Date range has a start and an end.'),
      'fields' => fn() => [
        'start' => [
          'type' => static::type('DateTime'),
          'description' => (string) $this->t('The start of the date range.'),
        ],
        'end' => [
          'type' => static::type('DateTime'),
          'description' => (string) $this->t('The end of the date range.'),
        ],
        'duration' => [
          'type' => Type::int(),
          'description' => (string) $this->t('The duration of the date range in seconds.'),
        ],
      ],
    ]);

    return $types;
  }

}
