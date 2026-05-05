<?php

namespace Drupal\eca\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Site\Settings;
use Drupal\eca\Processor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A dynamic subscriber, listening on used events of active ECA configurations.
 */
class DynamicSubscriber implements EventSubscriberInterface {

  /**
   * Whether ECA is active.
   *
   * @var bool
   */
  protected static bool $isActive = TRUE;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];

    // Guard against container build phase to avoid circular dependencies.
    // During container compilation, the state service may not be ready and
    // attempting to access it can trigger circular dependency chains.
    if (!\Drupal::hasContainer()) {
      return $events;
    }

    try {
      // Only access state if the service is available.
      if (\Drupal::hasService('state')) {
        foreach (\Drupal::state()->get('eca.subscribed', []) as $name => $prioritized) {
          $events[$name][] = ['onEvent', key($prioritized)];
        }
      }
    }
    catch (\Throwable $e) {
      // During container compilation, accessing state or other services
      // may cause circular dependencies. Return empty array to allow
      // container build to complete. Events will be properly registered
      // when ECA configurations are saved/loaded after container is built.
    }

    return $events;
  }

  /**
   * Set the processor to be active or not.
   *
   * @param bool $active
   *   Set TRUE to be active, FALSE otherwise.
   */
  public static function setActive(bool $active): void {
    self::$isActive = $active;
  }

  /**
   * Get to know whether the processor is active or not.
   *
   * @return bool
   *   Returns TRUE when active, FALSE otherwise.
   */
  public static function isActive(): bool {
    return self::$isActive;
  }

  /**
   * Callback forwarding the given event to the ECA processor.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The triggered event that gets processed by the ECA processor.
   * @param string $event_name
   *   The specific event name that got triggered for that event.
   */
  public function onEvent(Event $event, string $event_name): void {
    if (!self::$isActive) {
      return;
    }
    try {
      if (!Settings::get('eca_disable', FALSE)) {
        Processor::get()->execute($event, $event_name);
      }
      // @phpstan-ignore-next-line
      elseif (\Drupal::currentUser()->hasPermission('administer eca')) {
        // @phpstan-ignore-next-line
        \Drupal::messenger()
          ->addWarning('ECA is disabled in your settings.php file.');
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // This is thrown during installation of eca and we can ignore this.
    }
  }

}
