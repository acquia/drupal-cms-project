<?php

declare(strict_types=1);

namespace Drupal\automatic_updates\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Modifies route definitions.
 *
 * @internal
 *   This is an internal part of Automatic Updates and may be changed or removed
 *   at any time without warning. External code should not interact with this
 *   class.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Try to run after other route subscribers, to minimize the chances of
      // conflicting with other code that is modifying Update module routes.
      RoutingEvents::ALTER => ['onAlterRoutes', -1000],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Disable status checks on certain routes.
    $disabled_routes = [
      'system.theme_install',
      'update.status',
      'system.status',
      'system.batch_page.html',
    ];
    foreach ($disabled_routes as $route) {
      $collection->get($route)
        ?->setOption('_automatic_updates_status_messages', 'skip');
    }

    // Clone the update route so that there can be distinct local tasks for it.
    $clone = (clone $collection->get('automatic_updates.update_form'))
      ->setPath('/admin/modules/update');
    $collection->add('automatic_updates.module_update', $clone);

    $clone = (clone $collection->get('automatic_updates.update_form'))
      ->setPath('/admin/appearance/update');
    $collection->add('automatic_updates.theme_update', $clone);
  }

}
