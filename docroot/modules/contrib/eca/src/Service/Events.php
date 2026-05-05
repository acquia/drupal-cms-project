<?php

namespace Drupal\eca\Service;

use Drupal\Component\Plugin\Exception\PluginException;
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
   * Events constructor.
   *
   * @param \Drupal\eca\PluginManager\Event $event_manager
   *   The ECA event plugin manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_manager
   *   The module extension manager.
   */
  public function __construct(Event $event_manager, LoggerChannelInterface $logger, ModuleExtensionList $extension_manager) {
    $this->eventManager = $event_manager;
    $this->logger = $logger;
    $this->extensionManager = $extension_manager;
  }

  /**
   * Returns a sorted list of event plugins.
   *
   * @return \Drupal\eca\Plugin\ECA\Event\EventInterface[]
   *   The sorted list of events.
   */
  public function events(): array {
    static $events;
    if ($events === NULL) {
      $this->enableExtendedErrorHandling('Collecting all available events');
      $events = [];
      foreach ($this->eventManager->getDefinitions() as $plugin_id => $definition) {
        try {
          /** @var \Drupal\eca\Plugin\ECA\Event\EventInterface $plugin */
          $plugin = $this->eventManager->createInstance($plugin_id);
          $events[] = $plugin;
        }
        // @phpstan-ignore catch.neverThrown
        catch (PluginException | \Throwable) {
          // Can be ignored.
        }
      }
      $this->resetExtendedErrorHandling();
      $this->sortPlugins($events, $this->extensionManager);
    }
    return $events;
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
