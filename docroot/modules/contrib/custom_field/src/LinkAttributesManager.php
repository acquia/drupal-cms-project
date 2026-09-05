<?php

namespace Drupal\custom_field;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides the link_attributes plugin manager.
 */
class LinkAttributesManager extends DefaultPluginManager implements PluginManagerInterface {

  /**
   * Provides default values for all link_attributes plugins.
   *
   * @var array
   */
  protected $defaults = [
    'title' => '',
    'type' => '',
    'description' => '',
  ];

  /**
   * Constructs a LinkAttributesManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(\Traversable $namespaces, ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    // Call the parent constructor with the necessary parameters.
    // Here, FALSE indicates that plugins are not in a subdirectory.
    parent::__construct(FALSE, $namespaces, $module_handler);
    $this->alterInfo('custom_field_link_attributes_plugin');
    $this->moduleHandler = $module_handler;
    $this->setCacheBackend($cache_backend, 'custom_field_link_attributes', ['custom_field_link_attributes']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!$this->discovery) {
      $this->discovery = new YamlDiscovery('custom_field_link_attributes', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('title');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $definition
   *   The plugin definition.
   * @param string $plugin_id
   *   The plugin id.
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);

    // Make sure each plugin definition had at least a field type.
    if (empty($definition['type'])) {
      $definition['type'] = 'textfield';
    }
    // Translate options.
    if (!empty($definition['options'])) {
      foreach ($definition['options'] as $property => $option) {
        $definition['options'][$property] = new TranslatableMarkup($option); // phpcs:ignore
      }
    }
  }

}
