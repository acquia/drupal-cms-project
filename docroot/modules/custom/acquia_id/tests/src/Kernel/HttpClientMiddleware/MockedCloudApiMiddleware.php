<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_id\Kernel\HttpClientMiddleware;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Guzzle middleware that intercepts requests to the Acquia Cloud API.
 *
 * Mocks /api/account and /api/applications/{uuid} endpoints.
 */
final class MockedCloudApiMiddleware {

  public function __invoke(): callable {
    return static function (callable $handler): callable {
      return static function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
        if ($request->getUri()->getHost() !== 'cloud.acquia.com') {
          return $handler($request, $options);
        }

        $path = $request->getUri()->getPath();
        $access_token = str_replace('Bearer ', '', $request->getHeaderLine('Authorization'));

        // Resource owner details endpoint.
        if ($path === '/api/account') {
          return new FulfilledPromise(
            new Response(
              200,
              ['Content-Type' => 'application/json'],
              Json::encode([
                'uuid' => 'user-uuid-12345',
                'mail' => 'test@example.com',
                'timezone' => 'America/New_York',
                'first_name' => 'Test',
                'last_name' => 'User',
              ]),
            ),
          );
        }

        // Application access check endpoint (reject empty UUID path).
        if ($path === '/api/applications/' || $path === '/api/applications') {
          return new FulfilledPromise(
            new Response(
              404,
              ['Content-Type' => 'application/json'],
              Json::encode(['error' => 'Not Found']),
            ),
          );
        }
        if (preg_match('#^/api/applications/(.+)$#', $path, $matches)) {
          if ($access_token === 'NO_APP_ACCESS_TOKEN') {
            return new FulfilledPromise(
              new Response(
                403,
                ['Content-Type' => 'application/json'],
                Json::encode(['error' => 'Forbidden']),
              ),
            );
          }
          return new FulfilledPromise(
            new Response(
              200,
              ['Content-Type' => 'application/json'],
              Json::encode([
                'uuid' => $matches[1],
                'name' => 'Test Application',
              ]),
            ),
          );
        }

        throw new \RuntimeException(__CLASS__ . ' request URI not mocked: ' . $request->getUri());
      };
    };
  }

}
