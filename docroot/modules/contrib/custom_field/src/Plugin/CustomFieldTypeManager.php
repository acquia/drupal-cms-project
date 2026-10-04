<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field\Attribute\CustomFieldType;

/**
 * Provides the custom field type plugin manager.
 */
class CustomFieldTypeManager extends DefaultPluginManager implements CustomFieldTypeManagerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new CustomFieldTypeManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/CustomField/FieldType',
      $namespaces,
      $module_handler,
      'Drupal\custom_field\Plugin\CustomFieldTypeInterface',
      CustomFieldType::class,
      'Drupal\custom_field\Annotation\CustomFieldType'
    );

    $this->alterInfo('custom_field_info');
    $this->setCacheBackend($cache_backend, 'custom_field_type_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);

    // @todo This is copied from \Drupal\Core\Plugin\Factory\ContainerFactory.
    //   Find a way to restore sanity to
    //   \Drupal\Core\Field\FormatterBase::__construct().
    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      // @todo Find a better way to solve this, if possible at all.
      // @phpstan-ignore-next-line
      return $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition);
    }

    return new $plugin_class($plugin_id, $plugin_definition, $configuration['settings']);
  }

  /**
   * {@inheritdoc}
   */
  public function createOptionsForInstance(array $settings, array $column): array {
    $type = $column['type'];
    $definition = $this->getDefinitions()[$type];
    return [
      'configuration' => [
        'settings' => $column + [
          'field_settings' => $settings,
          'never_check_empty' => $definition['never_check_empty'] ?? FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomFieldItems(array $settings): array {
    $items = [];
    $field_settings = $settings['field_settings'] ?? [];

    // Table element rows weight property not working so lets
    // sort the data ahead of time in this function.
    $columns = $this->sortFieldsByWeight($settings['columns'], $field_settings);

    foreach ($columns as $name => $column) {
      if (!isset($column['type'])) {
        continue;
      }
      unset($column['weight']);
      if (isset($column['remove'])) {
        unset($column['remove']);
      }
      $settings = $field_settings[$name] ?? [];
      $type = $column['type'];
      $options = self::createOptionsForInstance($settings, $column);
      try {
        /** @var \Drupal\custom_field\Plugin\CustomFieldTypeInterface $instance */
        $instance = $this->createInstance($type, $options['configuration']);
        $items[$name] = $instance;
      }
      catch (PluginException $e) {
        // Should we log the error?
      }
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   *
   * @param string[] $definition
   *   The plugin definition.
   * @param string $plugin_id
   *   The plugin id.
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);
    // Ensure that every field type has a category.
    if (empty($definition['category'])) {
      $definition['category'] = $this->t('General');
    }
  }

  /**
   * Sort fields by weight.
   *
   * @param array<string, mixed> $columns1
   *   Columns from \Drupal\custom_field\Plugin\Field\FieldType\CustomItem
   *   settings.
   * @param array<string, mixed> $field_settings
   *   Field settings \Drupal\custom_field\Plugin\Field\FieldType\CustomItem
   *   settings.
   *
   * @return array<string, mixed>
   *   An array of fields sorted by weight.
   */
  private function sortFieldsByWeight(array $columns1, array $field_settings): array {
    $columns = [];
    foreach ($columns1 as $name => $column) {
      $weight = $field_settings[$name]['weight'] ?? 0;
      $column['weight'] = $weight;
      $columns[$name] = $column;
    }
    uasort($columns, function ($item1, $item2) {
      return $item1['weight'] <=> $item2['weight'];
    });

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldTypeOptions(): array {
    $options = [];
    $definitions = $this->getDefinitions();
    // Sort the types by category and then by name.
    uasort($definitions, function ($a, $b) {
      if ($a['category'] != $b['category']) {
        return strnatcasecmp((string) $a['category'], (string) $b['category']);
      }
      return strnatcasecmp((string) $a['label'], (string) $b['label']);
    });
    foreach ($definitions as $id => $definition) {
      /** @var \Drupal\custom_field\Plugin\CustomFieldTypeInterface $plugin_class */
      $plugin_class = DefaultFactory::getPluginClass($id, $definition);
      if (!$plugin_class::isApplicable()) {
        continue;
      }
      $category = $definition['category'];
      // Add category grouping for multiple options.
      $options[(string) $category][$id] = $definition['label'];
    }

    return $options;
  }

}
