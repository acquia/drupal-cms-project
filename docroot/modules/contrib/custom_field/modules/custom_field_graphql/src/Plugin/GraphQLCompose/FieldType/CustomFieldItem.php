<?php

declare(strict_types=1);

namespace Drupal\custom_field_graphql\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemsInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function Symfony\Component\String\u;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "custom",
 * )
 */
class CustomFieldItem extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface, FieldProducerItemsInterface {

  use FieldProducerTrait;

  /**
   * The custom field type manager.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   */
  protected CustomFieldTypeManagerInterface $customFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->customFieldManager = $container->get('plugin.manager.custom_field_type');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItems(FieldItemListInterface $field, FieldContext $context): array {
    $results = [];

    foreach ($field as $item) {
      $results[] = $this->resolveFieldItem($item, $context);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    $settings = $item->getFieldDefinition()->getSettings();
    $custom_items = $this->customFieldManager->getCustomFieldItems($settings);
    $field_definition = $item->getFieldDefinition();
    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $bundle = $field_definition->getTargetBundle();
    $field_name = $field_definition->getName();

    $fields = [];

    foreach ($custom_items as $name => $custom_item) {
      $subfield_settings = $this->configFactory->get('graphql_compose.settings')->get("field_config.$entity_type_id.$bundle.$field_name.subfields.$name");
      $enabled = $subfield_settings['enabled'] ?? FALSE;
      if (!$enabled) {
        continue;
      }
      $name_sdl = $subfield_settings['name_sdl'] ?? u($name)->camel()->toString();

      // Pass the widget settings down as context.
      $context->setContextValue('settings', $custom_item->getFieldSettings() ?? []);
      $context->setContextValue('property_name', $name);
      $context->setContextValue('data_type', $custom_item->getDataType());
      $fields[$name_sdl] = $this->getSubField($name, $item, $context) ?: NULL;
    }

    return $fields;
  }

  /**
   * Get the subfield value for a subfield.
   *
   * @param string $subfield
   *   The name of the subfield. (first, second)
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The field context.
   *
   * @return mixed
   *   The value of the subfield.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \ReflectionException
   */
  protected function getSubField(string $subfield, FieldItemInterface $item, FieldContext $context): mixed {
    $value = $item->{$subfield};
    // If the value is empty, go no further.
    if ($value === NULL || $value === "") {
      return NULL;
    }

    // Attempt to load the plugin for the field type.
    $plugin = $this->getSubfieldPlugin($subfield);

    if (!$plugin) {
      return $value;
    }

    // Check if it has a resolver we can hijack.
    $class = new \ReflectionClass($plugin['class']);

    if (!$class->implementsInterface(FieldProducerItemInterface::class)) {
      return $value;
    }

    // Create an instance of the graphql plugin.
    /** @var \Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface $instance */
    $instance = $this->gqlFieldTypeManager->createInstance($plugin['id'], []);

    // Clone the current item into a new object for safety.
    $clone = clone $item;

    // Generically set the value. Relies on magic method __set().
    // @phpstan-ignore property.notFound
    $clone->value = $value;

    // Call the plugin resolver on the sub-field.
    return $instance->resolveFieldItem($clone, $context);
  }

  /**
   * {@inheritdoc}
   *
   * Override the type resolution for this field item.
   */
  public function getTypeSdl(): string {
    $type = u('Custom');
    $definition = $this->getFieldDefinition();
    $entity_type = $definition->getTargetEntityTypeId();
    $bundle = $definition->getTargetBundle();
    $type = $type->append(u($definition->getName())
      ->camel()
      ->title()
      ->toString());
    $type = $type->append(u($entity_type)->camel()->title()->toString());
    $type = $type->append(u($bundle)->camel()->title()->toString());

    return $type->toString();
  }

  /**
   * Get the subfield type for a subfield.
   *
   * @param string $subfield
   *   The subfield to get the type for. Eg first, second.
   *
   * @return string
   *   The SDL type of the subfield.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubfieldTypeSdl(string $subfield): string {
    $columns = $this->getFieldDefinition()->getFieldStorageDefinition()->getSetting('columns');
    $type = $columns[$subfield]['type'];
    if ($type === 'uri') {
      // We return the custom uri type without attributes.
      return 'CustomFieldUri';
    }
    $plugin = $this->getSubfieldPlugin($subfield);
    $id = $plugin['id'] ?? '';
    if ($id === 'custom_field_entity_reference') {
      return $this->getUnionTypeSdl($subfield);
    }
    return $plugin['type_sdl'] ?? 'String';
  }

  /**
   * Get the data definition type from DoubleField.
   *
   * @param string $subfield
   *   The subfield to get the plugin for. Eg first, second.
   *
   * @return array|null
   *   The plugin definition or NULL if not found.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getSubfieldPlugin(string $subfield): ?array {
    $columns = $this->getFieldDefinition()->getFieldStorageDefinition()->getSetting('columns');

    // Use the stored data type.
    $type = $columns[$subfield]['type'];

    // Coerce them back into our schema supported type.
    switch ($type) {

      case 'color':
        $type = 'string';
        break;

      case 'datetime':
        $type = 'custom_field_datetime';
        break;

      case 'daterange':
        $type = 'custom_field_daterange';
        break;

      case 'entity_reference':
        $type = 'custom_field_entity_reference';
        break;

      case 'file':
        $type = 'custom_field_file';
        break;

      case 'image':
        $type = 'custom_field_image';
        break;

      case 'time':
      case 'duration':
        $type = 'integer';
        break;

      case 'time_range':
        $type = 'custom_field_time_range';
        break;

      case 'uri':
      case 'link':
        $type = 'custom_field_link';
        break;

      case 'string_long':
        $type = 'custom_field_text';
        break;

      case 'map':
      case 'map_string':
        $type = 'custom_field_map';
        break;

      case 'viewfield':
        $type = 'custom_field_viewfield';
        break;
    }

    return $this->gqlFieldTypeManager->getDefinition($type, FALSE);
  }

  /**
   * The GraphQL union type for this field (non-generic).
   *
   * @param string $subfield
   *   The name of the subfield.
   *
   * @return string
   *   Type in format of {Entity}Union
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUnionTypeSdl(string $subfield): string {
    $settings = $this->getFieldDefinition()->getSettings();
    $custom_items = $this->customFieldManager->getCustomFieldItems($settings);
    $custom_item = $custom_items[$subfield];
    $target_type_id = $custom_item->getTargetType();

    if (!$target_type_id) {
      return 'UnsupportedType';
    }

    // Entity type is not defined.
    if (!$entity_type = $this->entityTypeManager->getDefinition($target_type_id, FALSE)) {
      return 'UnsupportedType';
    }

    // Entity type plugin is not defined.
    if (!$plugin_instance = $this->gqlEntityTypeManager->getPluginInstance($target_type_id)) {
      return 'UnsupportedType';
    }

    // No enabled plugin bundles.
    if (empty($plugin_instance->getBundles())) {
      return 'UnsupportedType';
    }

    // No bundle types on this entity.
    // No union required. Return normal name.
    if (!$entity_type->getBundleEntityType()) {
      return $plugin_instance->getTypeSdl();
    }

    return u($plugin_instance->getTypeSdl())
      ->camel()
      ->title()
      ->append('Union')
      ->toString();
  }

}
