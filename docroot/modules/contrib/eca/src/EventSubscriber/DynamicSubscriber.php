<?php

namespace Drupal\eca\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Site\Settings;
use Drupal\eca\Processor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Subscribes to exactly those events that active ECA models actually use.
 *
 * Unlike a regular event subscriber, this one does not declare a fixed list
 * of events at compile time. Instead, it builds its subscription list
 * dynamically from data stored in Drupal's state, so that ECA only ever
 * reacts to events that are referenced by at least one enabled ECA model.
 * This keeps ECA's runtime footprint minimal: events that no model listens
 * to never reach the ECA processor.
 *
 * How the subscription list is built:
 * - The list of subscribed events lives in the state key `eca.subscribed`.
 *   It is keyed by event name and, for each event, holds the priority and
 *   the ECA models (and their events) that subscribe to it.
 * - ::getSubscribedEvents() reads that state and registers a single
 *   ::onEvent() callback for every event name found there. If the state is
 *   empty (no enabled models, or the state was never built), nothing is
 *   subscribed and ECA stays dormant.
 *
 * When and how the state is (re)built:
 * - The state is rebuilt by
 *   \Drupal\eca\Entity\EcaStorage::rebuildSubscribedEvents(), which scans all
 *   enabled, non-template ECA models and collects the events they use.
 * - This happens automatically whenever an ECA config entity is saved or
 *   deleted (see EcaStorage::doPostSave() and EcaStorage::delete()), because
 *   those are the only moments at which the set of used events can change.
 * - Note that the subscription state is recomputed only from ECA *models*.
 *   Adding a new event plugin in a custom module does not change anything
 *   here; an enabled ECA model has to actually use that event before ECA
 *   starts listening to it.
 * - After updating the state, EcaStorage re-registers this subscriber with
 *   the event dispatcher so the new subscription list takes effect within
 *   the same request, without requiring a container rebuild.
 *
 * Forcing a rebuild manually:
 * - In the rare case where the state has become stale (for example after a
 *   manual state edit, an interrupted save, or an inconsistent deployment),
 *   the list can be rebuilt explicitly with the Drush command
 *   `drush eca:subscriber:rebuild`, which calls
 *   EcaStorage::rebuildSubscribedEvents() directly.
 * - This command is a recovery fallback only. Under normal operation the
 *   state stays in sync automatically through the save/delete hooks above,
 *   so it should not be needed for day-to-day work.
 *
 * @see \Drupal\eca\Entity\EcaStorage::rebuildSubscribedEvents()
 * @see \Drupal\eca\Drush\Commands\EcaCommands::rebuildSubscribedEvents()
 * @see \Drupal\eca\Processor::execute()
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
   *
   * Builds the subscription list from the `eca.subscribed` state instead of a
   * static declaration. For every event name stored there, a single
   * ::onEvent() callback is registered at the priority recorded in the state.
   * The state itself is maintained by
   * \Drupal\eca\Entity\EcaStorage::rebuildSubscribedEvents().
   *
   * @see \Drupal\eca\Entity\EcaStorage::rebuildSubscribedEvents()
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
   * Registered by ::getSubscribedEvents() for every event name found in the
   * `eca.subscribed` state. When a subscribed event is dispatched, this
   * callback hands it to the ECA processor, which then evaluates the matching
   * ECA models. It is skipped when ECA has been deactivated at runtime via
   * ::setActive(FALSE) or disabled globally through the `eca_disable` setting
   * in settings.php.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The triggered event that gets processed by the ECA processor.
   * @param string $event_name
   *   The specific event name that got triggered for that event.
   *
   * @see \Drupal\eca\Processor::execute()
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
