<?php

declare(strict_types=1);

namespace Drupal\trash\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Trash routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TrashManagerInterface $trashManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($this->trashManager->isEntityTypeEnabled($entity_type)) {
        if ($entity_type->hasLinkTemplate('canonical')) {
          $base_path = $entity_type->getLinkTemplate('canonical');
        }
        else {
          $base_path = "/admin/content/trash/$entity_type_id/{" . $entity_type_id . '}';
        }

        $parameters = [
          $entity_type_id => [
            'type' => "entity:$entity_type_id",
          ],
        ];

        // Add a route for the restore form.
        $route = new Route($base_path . '/restore');
        $route
          ->addDefaults([
            '_entity_form' => "{$entity_type_id}.restore",
            'entity_type_id' => $entity_type_id,
          ])
          ->setRequirement('_entity_access', "{$entity_type_id}.restore")
          ->setOption('parameters', $parameters)
          ->setOption('_admin_route', TRUE)
          ->setOption('_trash_route', TRUE);
        $collection->add("entity.$entity_type_id.restore", $route);

        // Add a route for the purge form.
        $route = new Route($base_path . '/purge');
        $route
          ->addDefaults([
            '_entity_form' => "{$entity_type_id}.purge",
            'entity_type_id' => $entity_type_id,
          ])
          ->setRequirement('_entity_access', "{$entity_type_id}.purge")
          ->setOption('parameters', $parameters)
          ->setOption('_admin_route', TRUE)
          ->setOption('_trash_route', TRUE);
        $collection->add("entity.$entity_type_id.purge", $route);

        // Add a route for the restore multiple form.
        if ($entity_type->hasLinkTemplate('restore-multiple-form') && $entity_type->hasHandlerClass('form', 'restore-multiple-confirm')) {
          $route = new Route($entity_type->getLinkTemplate('restore-multiple-form'));
          $route
            ->addDefaults([
              '_form' => $entity_type->getFormClass('restore-multiple-confirm'),
              '_title' => 'Restore from trash',
              'entity_type_id' => $entity_type_id,
            ])
            ->setRequirement('_permission', 'access trash')
            ->setOption('_admin_route', TRUE)
            ->setOption('_trash_route', TRUE);
          $collection->add("entity.$entity_type_id.restore_multiple", $route);
        }

        // Add a route for the purge multiple form.
        if ($entity_type->hasLinkTemplate('purge-multiple-form') && $entity_type->hasHandlerClass('form', 'purge-multiple-confirm')) {
          $route = new Route($entity_type->getLinkTemplate('purge-multiple-form'));
          $route
            ->addDefaults([
              '_form' => $entity_type->getFormClass('purge-multiple-confirm'),
              '_title' => 'Permanently delete',
              'entity_type_id' => $entity_type_id,
            ])
            ->setRequirement('_permission', 'access trash')
            ->setOption('_admin_route', TRUE)
            ->setOption('_trash_route', TRUE);
          $collection->add("entity.$entity_type_id.purge_multiple", $route);
        }

        // Ensure that entity delete forms never load the latest revision.
        if ($route = $collection->get("entity.$entity_type_id.delete_form")) {
          $parameters = $route->getOption('parameters') ?? [];
          foreach ($parameters as &$parameter) {
            if (isset($parameter['type']) && $parameter['type'] === "entity:$entity_type_id") {
              unset($parameter['load_latest_revision']);
              $route->setOption('parameters', $parameters);
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // This needs to run after ContentModerationRouteSubscriber::alterRoutes().
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -210];
    return $events;
  }

}
