<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_id\Kernel\HttpClientMiddleware;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Guzzle middleware that intercepts requests to the Acquia ID IdP.
 *
 * Mocks the OKTA token endpoint for kernel tests.
 */
final class MockedIdpMiddleware {

  public function __invoke(): callable {
    return static function (callable $handler): callable {
      return static function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
        if ($request->getUri()->getHost() !== 'id.acquia.com') {
          return $handler($request, $options);
        }
        $path = $request->getUri()->getPath();

        if ($path === '/oauth2/default/v1/token') {
          $params = [];
          parse_str((string) $request->getBody(), $params);

          if ($params['grant_type'] === 'authorization_code') {
            if ($params['code'] === 'CLIENT_ERROR') {
              return new FulfilledPromise(
                new Response(
                  403,
                  ['Content-Type' => 'application/json'],
                  Json::encode(['error' => 'Forbidden']),
                ),
              );
            }
            if ($params['code'] === 'NO_APP_ACCESS') {
              $access_token = 'NO_APP_ACCESS_TOKEN';
            }
            else {
              $access_token = 'VALID_ACCESS_TOKEN';
            }
            return new FulfilledPromise(
              new Response(
                200,
                ['Content-Type' => 'application/json'],
                Json::encode([
                  'access_token' => $access_token,
                  'refresh_token' => 'REFRESH_TOKEN',
                  'id_token' => 'eyJhbGciOiJSUzI1NiJ9.test-id-token',
                  'expires_in' => 3600,
                  'token_type' => 'Bearer',
                ]),
              ),
            );
          }

          if ($params['grant_type'] === 'refresh_token') {
            if ($params['refresh_token'] === 'REFRESH_TOKEN_INVALID') {
              return new FulfilledPromise(
                new Response(
                  401,
                  ['Content-Type' => 'application/json'],
                  Json::encode([
                    'error' => 'invalid_grant',
                    'error_description' => 'The refresh token is invalid.',
                  ]),
                ),
              );
            }
            return new FulfilledPromise(
              new Response(
                200,
                ['Content-Type' => 'application/json'],
                Json::encode([
                  'access_token' => 'REFRESHED_ACCESS_TOKEN',
                  'refresh_token' => 'REFRESHED_REFRESH_TOKEN',
                  'expires_in' => 3600,
                  'token_type' => 'Bearer',
                ]),
              ),
            );
          }
        }

        throw new \RuntimeException(__CLASS__ . ' request URI not mocked: ' . $request->getUri());
      };
    };
  }

}
