<?php

namespace Drupal\custom_field;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Gathers and provides the tags that can be used to wrap fields.
 */
class TagManager extends DefaultPluginManager implements TagManagerInterface, PluginManagerInterface, CachedDiscoveryInterface {

  use StringTranslationTrait;

  /**
   * The theme handler object.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * A set of defaults to be referenced by $this->processDefinition().
   *
   * Allows for additional processing of plugins when necessary or helpful for
   * development purposes.
   *
   * @var string[]
   */
  protected $defaults = [
    'label' => '',
    'group' => '',
    'description' => '',
  ];

  /**
   * Constructs a new TagManager instance.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(\Traversable $namespaces, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, CacheBackendInterface $cache_backend) {
    // Call the parent constructor with the necessary parameters.
    // Here, FALSE indicates that plugins are not in a subdirectory.
    parent::__construct(FALSE, $namespaces, $module_handler);

    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->setCacheBackend($cache_backend, 'custom_field', ['custom_field_tags']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery(): DiscoveryInterface|ContainerDerivativeDiscoveryDecorator {
    if (!$this->discovery) {
      $this->discovery = new YamlDiscovery('custom_field_tags', $this->moduleHandler->getModuleDirectories());
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getTagOptions(array $tags = []): array {
    $options = [
      TagManagerInterface::NO_MARKUP_VALUE => (string) $this->t('None (No wrapping HTML)'),
    ];
    $definitions = $this->getDefinitions();
    if (!empty($tags)) {
      $definitions = array_intersect_key($definitions, array_flip($tags));
    }
    foreach ($definitions as $id => $definition) {
      $options[$definition['group']][(string) $id] = (string) $this->t('@label (@tag)', [
        '@label' => $definition['label'],
        '@tag' => $id,
      ]);
    }
    return $options;
  }

}
