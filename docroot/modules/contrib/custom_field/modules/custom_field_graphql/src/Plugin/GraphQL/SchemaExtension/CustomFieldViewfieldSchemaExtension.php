<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension\ResolverOnlySchemaExtensionPluginBase;

/**
 * Add viewfield to the Schema.
 *
 * @SchemaExtension(
 *   id = "custom_field_viewfield",
 *   name = "GraphQL Compose Custom field",
 *   description = @Translation("Add viewfield to the Schema."),
 *   schema = "graphql_compose",
 * )
 */
class CustomFieldViewfieldSchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();
    $registry->addFieldResolver(
      'CustomFieldViewfield',
      'views',
      $builder->compose(
        $builder->callback(function ($parent) {
          return [
            'view_id' => $parent['view'] ?? NULL,
            'display_id' => $parent['display'] ?? NULL,
            'page_size' => $parent['pageSize'] ?? NULL,
            'arguments' => $parent['arguments'] ?? [],
          ];
        }),

        // Pass to normal view renderer.
        $builder->produce('views_executable')
          ->map('view_id', $builder->callback(fn($parent) => $parent['view_id']))
          ->map('display_id', $builder->callback(fn($parent) => $parent['display_id']))
          ->map('page', $builder->fromArgument('page'))
          ->map('page_size', $builder->callback(fn($parent) => $parent['page_size']))
          ->map('offset', $builder->fromArgument('offset'))
          ->map('filter', $builder->fromArgument('filter'))
          ->map('contextual_filter', $builder->callback(fn($parent) => $parent['arguments']))
          ->map('sort_key', $builder->fromArgument('sortKey'))
          ->map('sort_dir', $builder->fromArgument('sortDir'))
      )
    );
  }

}
