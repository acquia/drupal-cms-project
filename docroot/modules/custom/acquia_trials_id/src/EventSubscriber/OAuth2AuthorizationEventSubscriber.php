<?php

declare(strict_types=1);

namespace Drupal\acquia_trials_id\EventSubscriber;

use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\acquia_id\Events\OAuth2AuthorizationEvent;
use Drupal\acquia_trials_id\Api\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class OAuth2AuthorizationEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ClientFactory $clientFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      OAuth2AuthorizationEvent::class => 'onAuthorization',
    ];
  }

  /**
   * Verifies application access and resolves the Drupal user.
   *
   * @throws \Drupal\Core\Access\AccessException
   *   If the application UUID is unavailable or the user does not have access.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
   */
  public function onAuthorization(OAuth2AuthorizationEvent $event): void {
    $applicationUuid = AcquiaDrupalEnvironmentDetector::getAhApplicationUuid();
    if ($applicationUuid === NULL) {
      throw new AccessException('Acquia application UUID is not available.');
    }

    $accessToken = $event->getAccessToken();

    try {
      $this->clientFactory->get($accessToken->getToken())->getApplication($applicationUuid);
    }
    catch (GuzzleException) {
      throw new AccessException('User does not have access to this application.');
    }

    $resourceOwner = $event->getProvider()->getResourceOwner($accessToken);
    $email = $resourceOwner->getId();

    $user = user_load_by_mail($email);
    if ($user === FALSE) {
      $user = $this->entityTypeManager->getStorage('user')->create([
        'mail' => $email,
        'name' => $email,
        'status' => 1,
      ]);
      $user->save();
    }

    $event->setUser($user);
  }

}
