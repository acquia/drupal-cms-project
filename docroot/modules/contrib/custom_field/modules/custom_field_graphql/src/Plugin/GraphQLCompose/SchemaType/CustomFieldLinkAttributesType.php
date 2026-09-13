<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function Symfony\Component\String\u;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "CustomFieldLinkAttributes",
 * )
 */
class CustomFieldLinkAttributesType extends GraphQLComposeSchemaTypeBase {

  /**
   * Link attributes plugin manager.
   *
   * @var \Drupal\custom_field\LinkAttributesManager|null
   */
  protected $linkAttributesManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $instance->linkAttributesManager = $container->get('plugin.manager.custom_field_link_attributes');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('Link item attributes set within the CMS.'),
      'fields' => function () {
        $fields = [];

         /** @var array<string,array> $definitions */
        $definitions = $this->linkAttributesManager->getDefinitions();

        foreach ($definitions as $id => $attribute) {
          $description = $attribute['description'] ?? $attribute['title'] ?? NULL;

          $fields[u($id)->camel()->toString()] = [
            'type' => Type::string(),
            'description' => (string) ($description ?: $this->t('Link attribute @id.', ['@id' => $id])),
          ];
        }

        ksort($fields);

        return $fields;
      },
    ]);

    return $types;
  }

}
