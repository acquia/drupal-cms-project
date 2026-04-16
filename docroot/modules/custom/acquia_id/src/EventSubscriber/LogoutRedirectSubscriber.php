<?php

declare(strict_types=1);

namespace Drupal\acquia_id\EventSubscriber;

use Drupal\acquia_id\OAuth2\LogoutResponseGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Replaces the Drupal logout redirect with an IdP logout redirect.
 *
 * When a user logs out of Drupal, this subscriber intercepts the response on
 * the user.logout route and replaces it with a redirect through the IdP's
 * logout endpoint, so the OKTA session is also terminated.
 */
final class LogoutRedirectSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LogoutResponseGenerator $logoutResponseGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run early so we replace the response before other subscribers.
    return [
      KernelEvents::RESPONSE => ['onResponse', 100],
    ];
  }

  /**
   * Replaces the logout response with an IdP logout redirect.
   */
  public function onResponse(ResponseEvent $event): void {
    $route_name = $event->getRequest()->attributes->get('_route');
    if ($route_name !== 'user.logout') {
      return;
    }
    $event->setResponse($this->logoutResponseGenerator->get());
  }

}
