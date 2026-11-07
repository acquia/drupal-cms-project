<?php

namespace Drupal\project_browser\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Provides a Project Browser Source Manager.
 *
 * @see \Drupal\project_browser\Attribute\ProjectBrowserSource
 * @see \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface
 * @see plugin_api
 *
 * @api
 *   This class is covered by our backwards compatibility promise and can
 *   be safely relied upon.
 */
final class ProjectBrowserSourceManager extends DefaultPluginManager implements LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct(
      'Plugin/ProjectBrowserSource',
      $namespaces,
      $module_handler,
      ProjectBrowserSourceInterface::class,
      ProjectBrowserSource::class,
    );

    $this->alterInfo('project_browser_source_info');
    $this->setCacheBackend($cache_backend, 'project_browser_source_info_plugins');
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface
   *   The source plugin.
   */
  public function createInstance($plugin_id, ?array $configuration = []): ProjectBrowserSourceInterface {
    $configuration ??= $this->configFactory->get('project_browser.admin_settings')
      ->get("enabled_sources.$plugin_id") ?? [];

    $instance = parent::createInstance($plugin_id, $configuration);
    assert($instance instanceof ProjectBrowserSourceInterface);
    return $instance;
  }

  /**
   * Returns all plugin instances corresponding to the enabled_sources config.
   *
   * @return \Drupal\project_browser\Plugin\ProjectBrowserSourceInterface[]
   *   Array of plugin instances.
   */
  public function getAllEnabledSources(): array {
    $sources = $this->configFactory->get('project_browser.admin_settings')
      ->get('enabled_sources');

    $instances = [];
    foreach ($sources as $plugin_id => $configuration) {
      if ($this->hasDefinition($plugin_id)) {
        $instances[$plugin_id] = $this->createInstance($plugin_id, $configuration);
      }
      else {
        // Ignore if the plugin does not exist, but log it.
        $this->logger?->warning('Project Browser tried to load the enabled source %source, but the plugin does not exist. Make sure you have run update.php after updating the Project Browser module.', ['%source' => $plugin_id]);
      }
    }
    return $instances;
  }

}
