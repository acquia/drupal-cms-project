<?php

namespace Drupal\ai_dashboard;

use Drupal\ai_dashboard\Plugin\AiDocumentation\AiDocumentation;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Collects AI Documentation links from `.ai_documentation.yml` files.
 */
class AiDocumentationManager extends DefaultPluginManager {

  /**
   * Default values for each ai_documentation plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
    'description' => '',
    'url' => '',
    'class' => AiDocumentation::class,
  ];

  /**
   * Constructs a new AiDocumentationManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    $this->moduleHandler = $module_handler;
    $this->setCacheBackend($cache_backend, 'ai_documentation', ['ai_documentation']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('ai_documentation', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    $definition['id'] = $definition['provider'] . '_' . $plugin_id;
    foreach (['label', 'url'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The ai_documentation %s must define the %s property.', $plugin_id, $required_property));
      }
    }
  }

}
