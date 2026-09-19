<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\FieldType;

use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "custom_field_map",
 *   type_sdl = "UntypedStructuredData"
 * )
 */
class CustomFieldMap extends GraphQLComposeFieldTypeBase {

  use FieldProducerTrait;

}
