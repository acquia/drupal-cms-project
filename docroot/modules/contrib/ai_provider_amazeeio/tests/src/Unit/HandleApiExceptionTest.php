<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Unit;

use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai_provider_amazeeio\Plugin\AiProvider\AmazeeioAiProvider;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\Response;
use OpenAI\Exceptions\RateLimitException;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests that a 429 is classified from the API body, not the client message.
 *
 * @internal This class is not part of the module's public programming API.
 */
#[CoversClass(AmazeeioAiProvider::class)]
final class HandleApiExceptionTest extends UnitTestCase {

  /**
   * Tests that an exhausted budget is reported as a quota problem.
   */
  public function testBudgetExceededThrowsQuotaException(): void {
    $exception = $this->rateLimitException([
      'error' => [
        'message' => 'Budget has been exceeded! Key=test-key (sk-...T6ug) Current cost: 0.0, Max budget: 0.0',
        'type' => 'budget_exceeded',
      ],
    ]);

    $this->expectException(AiQuotaException::class);
    $this->expectExceptionMessageMatches('/Budget has been exceeded!/');
    $this->provider()->handleApiException($exception);
  }

  /**
   * Tests that a real rate limit is still reported as a rate limit.
   */
  public function testRateLimitStaysRateLimit(): void {
    $exception = $this->rateLimitException(['error' => ['message' => 'Too Many Requests']]);

    $this->expectException(AiRateLimitException::class);
    $this->provider()->handleApiException($exception);
  }

  /**
   * Tests that a 429 without a usable body falls back to the client message.
   */
  public function testUnparsableBodyFallsBackToClientMessage(): void {
    $exception = new RateLimitException(new Response(429, [], '<html>gateway</html>'));

    $this->expectException(AiRateLimitException::class);
    $this->expectExceptionMessage('Request rate limit has been exceeded.');
    $this->provider()->handleApiException($exception);
  }

  /**
   * Builds a 429 exception carrying the given decoded body.
   *
   * @param array $body
   *   The response payload to encode.
   *
   * @return \OpenAI\Exceptions\RateLimitException
   *   The exception as the OpenAI client would raise it.
   */
  private function rateLimitException(array $body): RateLimitException {
    return new RateLimitException(new Response(429, [], json_encode($body)));
  }

  /**
   * Builds the provider with only the state service it needs here.
   *
   * @return \Drupal\ai_provider_amazeeio\Plugin\AiProvider\AmazeeioAiProvider
   *   The provider plugin.
   */
  private function provider(): AmazeeioAiProvider {
    $provider = (new \ReflectionClass(AmazeeioAiProvider::class))->newInstanceWithoutConstructor();

    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturn(FALSE);

    $property = new \ReflectionProperty(AmazeeioAiProvider::class, 'state');
    $property->setValue($provider, $state);

    return $provider;
  }

}
