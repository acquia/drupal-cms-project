<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\SchemaType;

use Drupal\custom_field_graphql\Plugin\GraphQLCompose\FieldType\CustomFieldItem;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use function Symfony\Component\String\u;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "CustomField",
 * )
 */
class CustomFieldType extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    // This assumes all fields have been bootstrapped.
    $fields = $this->gqlFieldTypeManager->getFields();

    array_walk_recursive($fields, function ($field) use (&$types) {
      if ($field instanceof CustomFieldItem) {
        $field_definition = $field->getFieldDefinition();
        $entity_type_id = $field_definition->getTargetEntityTypeId();
        $bundle = $field_definition->getTargetBundle();
        $field_name = $field_definition->getName();
        $columns = $field->getFieldDefinition()->getSetting('columns');
        $subfields = [];
        foreach ($columns as $name => $column) {
          $subfield_settings = $this->configFactory->get('graphql_compose.settings')->get("field_config.$entity_type_id.$bundle.$field_name.subfields.$name");
          $enabled = $subfield_settings['enabled'] ?? FALSE;
          if (!$enabled) {
            continue;
          }
          $name_sdl = $subfield_settings['name_sdl'] ?? u($name)->camel()->toString();
          $subfields[$name_sdl] = [
            'type' => static::type($field->getSubfieldTypeSdl($name)),
            'description' => (string) $this->t('The @field value of the custom field', ['@field' => $name]),
          ];
        }
        $types[$field->getTypeSdl()] = new ObjectType([
          'name' => $field->getTypeSdl(),
          'description' => (string) $this->t('A custom field is a field of fields.'),
          'fields' => fn() => $subfields,
        ]);
      }
    });

    return array_values($types);
  }

}
