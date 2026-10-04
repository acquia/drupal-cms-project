<?php

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\drupal_cms_helper\Telemetry;
use Drupal\drupal_cms_helper\TelemetryManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends telemetry events in response to certain events.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final readonly class TelemetrySubscriber implements EventSubscriberInterface {

  public function __construct(
    private RouteMatchInterface $routeMatch,
    private TelemetryManager $telemetry,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::CONTROLLER => 'onControllerResolved',
    ];
  }

  /**
   * Reacts when a request is made.
   */
  public function onControllerResolved(): void {
    $route_name = $this->routeMatch->getRouteName();

    $routes = [
      'automatic_updates.update_form',
      'checklistapi.checklists.language_checklist',
      'checklistapi.checklists.seo_checklist',
      'entity.webform.collection',
    ];
    if (in_array($route_name, $routes, TRUE)) {
      $this->telemetry->log(Telemetry::AdminPage, ['page' => $route_name]);
    }

    if ($route_name === 'project_browser.browse') {
      $source = $this->routeMatch->getParameter('source');

      if ($source instanceof PluginInspectionInterface && $source->getPluginId() === 'recommended') {
        $this->telemetry->log(Telemetry::AdminPage, ['page' => 'recommended add-ons']);
      }
    }
  }

}
