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
 *   id = "CustomFieldViewfield",
 * )
 */
class CustomFieldViewfield extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    if (!$this->moduleHandler->moduleExists('graphql_compose_views')) {
      return $types;
    }

    $args = [
      'page' => [
        'type' => Type::int(),
        'description' => (string) $this->t('If enabled: The page number to display.'),
      ],
      'offset' => [
        'type' => Type::int(),
        'description' => (string) $this->t('If enabled: The number of items skipped from beginning of this view.'),
      ],
      'filter' => [
        'type' => Type::listOf($this->gqlSchemaTypeManager->get('KeyValueInput')),
        'description' => (string) $this->t('If enabled: The filters to apply to this view. Filters may not apply unless exposed.'),
      ],
      'sortKey' => [
        'type' => Type::string(),
        'description' => (string) $this->t('If enabled: Sort the view by this key.'),
      ],
      'sortDir' => [
        'type' => $this->gqlSchemaTypeManager->get('SortDirection'),
        'description' => (string) $this->t('If enabled: Sort the view direction.'),
      ],
    ];

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'fields' => fn() => [
        'views' => [
          'type' => static::type('ViewResultUnion'),
          'args' => $args,
        ],
      ],
    ]);

    return $types;
  }

}
