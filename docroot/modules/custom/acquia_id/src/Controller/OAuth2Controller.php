<?php

declare(strict_types=1);

namespace Drupal\acquia_id\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\acquia_id\Events\OAuth2AuthorizationEvent;
use Drupal\acquia_id\OAuth2\AccessTokenRepository;
use Drupal\acquia_id\OAuth2\Provider\AcquiaIdProvider;
use GuzzleHttp\Exception\RequestException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class OAuth2Controller implements ContainerInjectionInterface {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  private const OAUTH2_STATE = 'oauth2_state';
  private const OAUTH2_PKCE = 'oauth2_pkce';

  public function __construct(
    private readonly AcquiaIdProvider $provider,
    private readonly SessionInterface $session,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly AccessTokenRepository $accessTokenRepository,
    private readonly string $idpLogoutRedirectUri,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('acquia_id.oauth2.provider'),
      $container->get('session'),
      $container->get('event_dispatcher'),
      $container->get('acquia_id.oauth2.access_token_repository'),
      $container->getParameter('acquia_id.idp_logout_redirect_uri'),
    );
  }

  /**
   * Handles the OAuth2 authorization code PKCE flow.
   *
   * @link https://oauth.net/2/pkce/
   */
  public function __invoke(Request $request): RedirectResponse {
    // Check if this is a subrequest being made on access denied exception.
    // @see \Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase::onException().
    if ($request->attributes->has('exception')) {
      $request->query->replace();
    }

    if (!$request->query->has('code') && !$request->query->has('state')) {
      if ($request->query->has('destination')) {
        $this->session->set('oauth2_destination', $request->query->get('destination'));
        $request->query->set('destination', '');
      }
      $response = new TrustedRedirectResponse(
        $this->provider->getAuthorizationUrl(),
        Response::HTTP_SEE_OTHER
      );
      $this->session->set(self::OAUTH2_STATE, $this->provider->getState());
      $this->session->set(self::OAUTH2_PKCE, $this->provider->getPkceCode());
      return $response;
    }

    if (!$request->query->has('state')) {
      throw new AccessDeniedHttpException('Missing state');
    }
    if ($request->query->get('state') !== $this->session->get(self::OAUTH2_STATE)) {
      throw new AccessDeniedHttpException('Invalid state');
    }

    if ($request->query->has('error')) {
      return $this->accessDeniedRedirect($request->query->get('error_description', ''));
    }

    try {
      $this->provider->setPkceCode($this->session->get(self::OAUTH2_PKCE));
      $token = $this->provider->getAccessToken('authorization_code', [
        'code' => $request->query->get('code', ''),
      ]);
      assert($token instanceof AccessToken);
    }
    catch (\Exception $e) {
      throw new AccessDeniedHttpException($e->getMessage(), $e);
    }

    $event = new OAuth2AuthorizationEvent($this->provider, $token);
    try {
      $this->eventDispatcher->dispatch($event);
    }
    catch (\Exception $e) {
      return $this->accessDeniedRedirect($e->getMessage());
    }

    $user = $event->getUser();
    if ($user === NULL) {
      return $this->accessDeniedRedirect('User not determined from OAuth authorization.');
    }

    user_login_finalize($user);
    $this->accessTokenRepository->store((int) $user->id(), $token);

    if ($destination = $this->session->get('oauth2_destination')) {
      $url = Url::fromUserInput($destination)->setAbsolute();
      $this->session->remove('oauth2_destination');
    }
    else {
      $url = Url::fromRoute('<front>');
    }

    return new RedirectResponse($url->toString(), Response::HTTP_SEE_OTHER);
  }

  /**
   * Checks access for the SSO route.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $token = NULL;
    try {
      $token = $this->accessTokenRepository->get((int) $account->id());
    }
    catch (IdentityProviderException) {
      return AccessResult::allowed();
    }
    catch (RequestException) {
    }

    return AccessResult::allowedIf($account->isAnonymous() || $token === NULL);
  }

  private function accessDeniedRedirect(string $logMessage = 'Access denied'): RedirectResponse {
    $this->getLogger('acquia_id')->error($this->t('Error: @message', ['@message' => $logMessage]));
    return new TrustedRedirectResponse($this->idpLogoutRedirectUri, Response::HTTP_SEE_OTHER);
  }

}
