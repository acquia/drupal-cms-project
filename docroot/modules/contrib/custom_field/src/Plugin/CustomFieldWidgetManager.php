<?php

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field\Attribute\CustomFieldWidget;

/**
 * Provides the custom field widget plugin manager.
 */
class CustomFieldWidgetManager extends DefaultPluginManager implements CustomFieldWidgetManagerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new CustomFieldWidgetManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface $customFieldTypeManager
   *   The custom field type manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, protected CustomFieldTypeManagerInterface $customFieldTypeManager) {
    parent::__construct(
      'Plugin/CustomField/FieldWidget',
      $namespaces,
      $module_handler,
      'Drupal\custom_field\Plugin\CustomFieldWidgetInterface',
      CustomFieldWidget::class,
      'Drupal\Core\Field\Annotation\FieldWidget'
    );

    $this->alterInfo('custom_field_widget_info');
    $this->setCacheBackend($cache_backend, 'custom_field_widget_plugins');
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

    return new $plugin_class($plugin_id, $plugin_definition, $configuration['field_name'], $configuration['custom_field_definition'], $configuration['settings'], $configuration['view_mode'], $configuration['third_party_settings']);
  }

  /**
   * {@inheritdoc}
   */
  public function createOptionsForInstance($field_name, $custom_item, string $widget_type, array $widget_settings, string $view_mode = 'default'): array {
    return [
      'field_name' => $field_name,
      'custom_field_definition' => $custom_item,
      'configuration' => [
        'type' => $widget_type,
        'settings' => $widget_settings,
      ],
      'view_mode' => $view_mode,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options): ?CustomFieldWidgetInterface {
    try {
      $configuration = $options['configuration'];
      $custom_field_definition = $options['custom_field_definition'];
      $field_name = $options['field_name'];

      assert($custom_field_definition instanceof CustomFieldTypeInterface);
      $field_type = $custom_field_definition->getDataType();

      // @todo Which subfield type uses this? Can this be dropped?
      // Fill in default configuration if needed.
      if (!isset($options['prepare']) || $options['prepare'] == TRUE) {
        $configuration = $this->prepareConfiguration($field_type, $configuration);
      }

      $plugin_id = $configuration['type'];

      // Switch back to the default widget if either:
      // - the configuration does not specify a widget class
      // - the field type is not allowed for the widget
      // - the widget is not applicable to the field definition.
      $definition = $this->getDefinition($configuration['type'], FALSE);
      if (!isset($definition['class']) || !in_array($field_type, $definition['field_types']) || !$definition['class']::isApplicable($custom_field_definition)) {

        // Grab the default widget for the field type.
        $field_type_definition = $this->customFieldTypeManager->getDefinition($field_type);
        if (empty($field_type_definition['default_widget'])) {
          return NULL;
        }
        $plugin_id = $field_type_definition['default_widget'];
      }

      $configuration += [
        'field_name' => $field_name,
        'custom_field_definition' => $custom_field_definition,
        'view_mode' => $options['view_mode'] ?? 'default',
      ];
      /** @var \Drupal\custom_field\Plugin\CustomFieldWidgetInterface $instance */
      $instance = $this->createInstance($plugin_id, $configuration);
      return $instance;
    }
    catch (\Exception $exception) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings(string $type): array {
    try {
      $plugin_definition = $this->getDefinition($type, FALSE);
      if (!empty($plugin_definition['class'])) {
        /** @var \Drupal\custom_field\Plugin\CustomFieldWidgetInterface $plugin_class */
        $plugin_class = DefaultFactory::getPluginClass($type, $plugin_definition);
        return $plugin_class::defaultSettings();
      }
    }
    catch (\Exception $exception) {
      // Silent fail, for now.
    }
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function prepareConfiguration(string $field_type, array $configuration): array {
    // Fill in defaults for missing properties.
    $configuration += [
      'settings' => [],
      'third_party_settings' => [],
    ];

    // If no formatter is specified, use the default formatter.
    if (!isset($configuration['type'])) {
      $field_type = $this->customFieldTypeManager->getDefinition($field_type);
      $configuration['type'] = $field_type['default_widget'];
    }
    // Filter out unknown settings, and fill in defaults for missing settings.
    $default_settings = $this->getDefaultSettings($configuration['type']);
    $configuration['settings'] = \array_intersect_key($configuration['settings'], $default_settings) + $default_settings;

    return $configuration;
  }

  /**
   * Performs extra processing on plugin definitions.
   *
   * @param array|\Drupal\Component\Plugin\Definition\PluginDefinitionInterface $definition
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
   * {@inheritdoc}
   */
  public function getWidgetsForField(string $type): array {
    $definitions = $this->getDefinitions();
    $widgets = [];
    foreach ($definitions as $definition) {
      if (in_array($type, $definition['field_types'])) {
        $widgets[] = $definition['id'];
      }
    }

    return $widgets;
  }

}
