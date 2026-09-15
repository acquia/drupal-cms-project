<?php

namespace Drupal\custom_field\PluginManager;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field\Attribute\PropWidget;
use Drupal\custom_field\Plugin\PropWidgetInterface;

/**
 * Provides the custom field component prop widget plugin manager.
 */
class PropWidgetManager extends DefaultPluginManager implements PropWidgetManagerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new PropWidgetManager object.
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
      'Plugin/Components/PropWidget',
      $namespaces,
      $module_handler,
      PropWidgetInterface::class,
      PropWidget::class,
      'Drupal\custom_field\Annotation\PropWidget'
    );

    $this->alterInfo('custom_field_prop_widget_info');
    $this->setCacheBackend($cache_backend, 'custom_field_prop_widget_plugins');
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
  public function createOptionsForInstance(string $widget_type, array $widget_settings): array {
    return [
      'configuration' => [
        'type' => $widget_type,
        'settings' => $widget_settings,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options): ?PropWidgetInterface {
    try {
      $configuration = $options['configuration'];

      // Fill in the default configuration if needed.
      if (!isset($options['prepare']) || $options['prepare']) {
        $configuration = $this->prepareConfiguration($configuration);
      }

      $plugin_id = $configuration['type'];

      // Switch back to the default widget if either:
      // - the configuration does not specify a widget class
      // - the field type is not allowed for the widget
      // - the widget is not applicable to the field definition.
      $definition = $this->getDefinition($configuration['type'], FALSE);
      if (!isset($definition['class'])) {
        return NULL;
      }

      /** @var \Drupal\custom_field\Plugin\PropWidgetInterface $instance */
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
        /** @var \Drupal\custom_field\Plugin\PropWidgetInterface $plugin_class */
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
   */
  public function prepareConfiguration(array $configuration): array {
    // Fill in defaults for missing properties.
    $configuration += [
      'settings' => [],
    ];
    // Filter out unknown settings and fill in defaults for missing settings.
    $default_settings = $this->getDefaultSettings($configuration['type']);
    $configuration['settings'] = \array_intersect_key($configuration['settings'], $default_settings) + $default_settings;

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropWidget(array $property_info): ?PropWidgetInterface {
    $format = $property_info['format'] ?? NULL;
    $property_type = $property_info['type'] ?? NULL;
    if (!$property_type) {
      return NULL;
    }
    // Remove UI patterns module computed properties. Why this?
    if (isset($property_info['id']) && $property_info['id'] === 'ui-patterns://attributes') {
      return NULL;
    }
    if (is_array($property_type)) {
      $property_type = reset($property_type);
    }
    // Special case for Drupal\Core\Template\Attribute.
    if ($property_type == 'Drupal\Core\Template\Attribute') {
      $property_type = 'attributes';
    }
    // Special case for URI properties.
    if ($property_type == 'string' && \in_array($format, ['uri', 'uri-reference'], TRUE)) {
      $property_type = 'uri';
    }
    if ($property_type == 'array') {
      $items_type = $property_info['items']['type'] ?? NULL;
      $id = $property_info['items']['id'] ?? NULL;
      if ($items_type === 'object') {
        if ($id === self::CANVAS_IMAGE) {
          $property_type = 'array_image';
        }
        else {
          $property_type = 'array_object';
        }
      }
      elseif ($items_type === 'string') {
        $property_type = 'array_string';
      }
      elseif ($items_type === 'integer') {
        $property_type = 'array_integer';
      }
      elseif ($items_type === 'number') {
        $property_type = 'array_number';
      }
    }
    if ($property_type === 'object') {
      $id = $property_info['id'] ?? NULL;
      if ($id === self::CANVAS_IMAGE) {
        $property_type = 'image';
      }
    }
    try {
      $options = $this->createOptionsForInstance($property_type, $property_info);
      return $this->getInstance($options);
    }
    catch (\Exception $exception) {
      // Silent fail, for now.
    }

    return NULL;
  }

}
