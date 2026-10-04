<?php

namespace Drupal\ai_dashboard\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('ai.settings.menu')) {
      $route->setDefault('_title', 'AI Setup and Configuration');
      $route->setDefault('_controller', '\Drupal\ai_dashboard\Controller\AiDashboard::index');
    }
  }

}
