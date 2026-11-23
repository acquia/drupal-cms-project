<?php

declare(strict_types=1);

namespace Drupal\trash\EventSubscriber;

use Drupal\Core\DefaultContent\PreImportEvent;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountEvents;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSetEvent;
use Drupal\Core\Update\UpdateKernel;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\trash\TrashManagerInterface;
use Drupal\workspaces\Event\WorkspacePostPublishEvent;
use Drupal\workspaces\Event\WorkspacePrePublishEvent;
use Drupal\workspaces\Event\WorkspacePublishEvent;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens to events where trash context has to be ignored.
 */
class TrashIgnoreSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected HttpKernelInterface $httpKernel,
    #[AutowireServiceClosure('entity_type.manager')]
    protected \Closure $entityTypeManager,
    protected TrashManagerInterface $trashManager,
    protected RouteMatchInterface $routeMatch,
    protected AccountInterface $currentUser,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Sets the trash context to ignore if needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\KernelEvent $event
   *   The KernelEvent to process.
   */
  public function onRequestPreRouting(KernelEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    // Specify the default trash context.
    $this->setDefaultTrashContext($event->getKernel(), $this->currentUser, $event->getRequest());
  }

  /**
   * Sets the default trash context for the currently set user.
   */
  public function onSetAccount(AccountSetEvent $event): void {
    $request = $this->requestStack->getCurrentRequest();
    if ($request) {
      try {
        $this->setDefaultTrashContext($this->httpKernel, $event->getAccount(), $request);
      }
      catch (ParamNotConvertedException) {
        // This exception may be thrown while Drupal core tries to cache
        // the permission variable cache context as the language can be within
        // the cache context.
        // It's fine to ignore as it's mainly used to trigger 404s, and will be
        // rechecked by the ::onRequestPreRouting() listener anyway.
      }
    }
  }

  /**
   * Specifies the default trash context for the given user and request.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The current HTTP kernel.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The set account.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  protected function setDefaultTrashContext(HttpKernelInterface $http_kernel, AccountInterface $account, Request $request): void {
    // This is needed so upgrades affecting entities will affect all entities,
    // no matter if they have been trashed.
    if ($http_kernel instanceof UpdateKernel) {
      $this->trashManager->setTrashContext('ignore');
    }
    elseif ($request->query->has('in_trash')) {
      // Only respect the "in_trash" query string if the user has the permission
      // to use it. This stops the leakage of whether an entity is trashed or
      // not to anonymous users through the response's status code.
      if (
        $account->hasPermission('administer trash') ||
        $account->hasPermission('access trash') ||
        $account->hasPermission('view deleted entities')
      ) {
        $this->trashManager->setTrashContext('ignore');
      }
      else {
        // Ensure that it is now active. This is useful in the event that the
        // user account is switched multiple times within the same request.
        $this->trashManager->setTrashContext('active');
      }
    }
  }

  /**
   * Sets the trash context to ignore if needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\KernelEvent $event
   *   The KernelEvent to process.
   */
  public function onRequest(KernelEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    // Some entity types that act as bundles for other entities have a custom
    // UI-only delete protection (i.e. a content type can not be deleted if
    // there are existing nodes of that type.) Trash needs to allow this
    // protection to work even when there are trashed entities of that type.
    if ($entity_form = $this->routeMatch->getRouteObject()->getDefault('_entity_form')) {
      // If no operation is provided, use 'default'.
      $entity_form .= '.default';
      [$entity_type_id, $operation] = explode('.', $entity_form);
      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
      $entity_type_manager = ($this->entityTypeManager)();
      $entity_type = $entity_type_manager->getDefinition($entity_type_id);

      if ($operation === 'delete' && ($bundle_of = $entity_type->getBundleOf())) {
        if (!$this->trashManager->isEntityTypeEnabled($entity_type_id)
          && $this->trashManager->isEntityTypeEnabled($bundle_of)
        ) {
          $this->trashManager->setTrashContext('ignore');
        }
      }
    }

    // Allow trashed entities to be displayed on the workspace manage page.
    if ($this->routeMatch->getRouteName() === 'entity.workspace.canonical') {
      $this->trashManager->setTrashContext('ignore');
    }

    // Allow trashed entities to be loaded on trash listing routes, including
    // during bulk operations form submissions.
    if (str_starts_with($this->routeMatch->getRouteName() ?? '', 'trash.admin_content_trash')) {
      $this->trashManager->setTrashContext('ignore');
    }
  }

  /**
   * Sets the trash context to 'ignore'.
   */
  public function ignoreTrashContext(): void {
    $this->trashManager->setTrashContext('ignore');
  }

  /**
   * Sets the trash context to 'active'.
   */
  public function restoreTrashContext(): void {
    $this->trashManager->setTrashContext('active');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Our ignore subscriber needs to run before language negotiation (which has
    // a priority of 255), but immediately after the authentication subscriber
    // (which has a priority of 300) in order to allow route enhancers (e.g.
    // entity param converter) to load the deleted entity.
    // It's possible for the language negotiation to be triggered earlier than
    // expected by a different subscriber with a priority of 299, so this
    // listener must be called immediately after the authentication subscriber
    // to ensure the user has the appropriate trash context.
    // This critical requirement is further enforced by the trash service
    // provider.
    $events[KernelEvents::REQUEST][] = ['onRequestPreRouting', 299];
    // Add a subscriber that reacts to when the user account is set.
    // This allows the default trash context to be set or reset based on the
    // currently set user, even before the onRequestPreRouting listener is
    // called.
    $events[AccountEvents::SET_USER][] = ['onSetAccount'];

    // Add another subscriber for setting the ignore trash context when the
    // current route is known.
    $events[KernelEvents::REQUEST][] = ['onRequest'];

    if (class_exists(WorkspacePublishEvent::class)) {
      $events[WorkspacePrePublishEvent::class][] = ['ignoreTrashContext'];
      $events[WorkspacePostPublishEvent::class][] = ['restoreTrashContext'];
    }
    if (class_exists(PreImportEvent::class)) {
      $events[PreImportEvent::class][] = ['ignoreTrashContext'];
    }
    if (class_exists(MigrateEvents::class)) {
      $events[MigrateEvents::PRE_ROW_DELETE][] = ['ignoreTrashContext'];
      $events[MigrateEvents::POST_ROW_DELETE][] = ['restoreTrashContext'];
    }

    return $events;
  }

}
