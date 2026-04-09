# Acquia ID

Provides OAuth2 single sign-on via Acquia ID (`id.acquia.com`) using the PKCE authorization code flow.

## Configuration

Set the following service parameters, typically in `settings.php` or a `services.yml` override:

```yaml
parameters:
  acquia_id.client_id: 'your-oauth2-client-id'
  acquia_id.idp_base_uri: 'https://id.acquia.com/oauth2/default'
  acquia_id.cloud_api_base_uri: 'https://cloud.acquia.com'
  acquia_id.idp_logout_redirect_uri: 'https://cloud.acquia.com'
```

The SSO route is `/acquia-id/sso`.

## Implementing user resolution

This module dispatches `\Drupal\acquia_id\Events\OAuth2AuthorizationEvent` once the
OAuth2 token exchange succeeds. **You must provide an event subscriber** that calls
`$event->setUser($user)` with the resolved Drupal user entity. If no user is set
after the event is dispatched, the SSO flow redirects to `idp_logout_redirect_uri`.

Example subscriber:

```php
use Drupal\acquia_id\Events\OAuth2AuthorizationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MyAuthorizationSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [OAuth2AuthorizationEvent::class => 'onAuthorization'];
  }

  public function onAuthorization(OAuth2AuthorizationEvent $event): void {
    $resourceOwner = $event->provider->getResourceOwner($event->accessToken);
    $user = user_load_by_mail($resourceOwner->getId());
    if ($user) {
      $event->setUser($user);
    }
  }

}
```

Throw `\Drupal\Core\Access\AccessException` from the subscriber to deny login and
redirect to `idp_logout_redirect_uri`.
