<?php

namespace Drupal\eca\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eca\ErrorHandlerTrait;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\PluginManager\Event;

/**
 * Service class for event plugins in ECA.
 */
class Events {

  use ErrorHandlerTrait;
  use ServiceTrait;

  /**
   * The ECA event plugin manager.
   *
   * @var \Drupal\eca\PluginManager\Event
   */
  protected Event $eventManager;

  /**
   * Logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The module extension manager.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $extensionManager;

  /**
   * The memory cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * Events constructor.
   *
   * @param \Drupal\eca\PluginManager\Event $event_manager
   *   The ECA event plugin manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_manager
   *   The module extension manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The memory cache backend.
   */
  public function __construct(Event $event_manager, LoggerChannelInterface $logger, ModuleExtensionList $extension_manager, CacheBackendInterface $cache) {
    $this->eventManager = $event_manager;
    $this->logger = $logger;
    $this->extensionManager = $extension_manager;
    $this->cache = $cache;
  }

  /**
   * Returns a sorted list of event plugins.
   *
   * Successfully collected plugins are cached for the current request by the
   * "cache.memory" service under "eca_events". Delete that item to force a
   * rebuild.
   *
   * @return \Drupal\eca\Plugin\ECA\Event\EventInterface[]
   *   The sorted list of events.
   */
  public function events(): array {
    $cached = $this->cache->get('eca_events');
    if ($cached !== FALSE) {
      return $cached->data;
    }

    $this->enableExtendedErrorHandling('Collecting all available events');
    $collected = [];
    try {
      foreach ($this->eventManager->getDefinitions() as $plugin_id => $definition) {
        if ($plugin = $this->createInstance($plugin_id)) {
          $collected[] = $plugin;
        }
      }
    }
    finally {
      // Without this, a throwable from anywhere inside the loop - including
      // the ::getDefinitions() call itself - would leave error reporting
      // suppressed and the echoing shutdown function armed for the rest of
      // the request.
      $this->resetExtendedErrorHandling();
    }
    $this->sortPlugins($collected, $this->extensionManager);
    $this->cache->set('eca_events', $collected);
    return $collected;
  }

  /**
   * Get an event plugin by id.
   *
   * @param string $plugin_id
   *   The id of the event plugin to be returned.
   * @param array $configuration
   *   The optional configuration array.
   *
   * @return \Drupal\eca\Plugin\ECA\Event\EventInterface|null
   *   The event plugin.
   */
  public function createInstance(string $plugin_id, array $configuration = []): ?EventInterface {
    try {
      /**
       * @var \Drupal\eca\Plugin\ECA\Event\EventInterface $event
       */
      $event = $this->eventManager->createInstance($plugin_id, $configuration);
    }
    // @phpstan-ignore catch.neverThrown
    catch (\Exception | \Throwable $e) {
      $event = NULL;
      $this->logger->error('The event plugin %pluginid can not be initialized. ECA is ignoring this event. The issue with this event: %msg', [
        '%pluginid' => $plugin_id,
        '%msg' => $e->getMessage(),
      ]);
    }
    return $event;
  }

}
