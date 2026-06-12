<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
use Psr\Log\NullLogger;

/**
 * Verifies model metadata is cached within a request.
 *
 * @internal
 */
final class AmazeeClientModelsCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_provider_amazeeio',
    'key',
  ];

  /**
   * Ensures repeated model lookups reuse the first response.
   */
  public function testModelsAreMemoizedPerClientInstance(): void {
    $transactions = [];
    $mock_handler = new MockHandler([
      new Response(200, [], Utils::jsonEncode([
        'data' => [
          [
            'model_name' => 'embeddings',
            'model_info' => [
              'mode' => 'embedding',
              'supports_function_calling' => FALSE,
              'supports_tool_choice' => FALSE,
              'supports_vision' => FALSE,
              'supports_audio_input' => FALSE,
              'supports_audio_output' => FALSE,
              'supports_prompt_caching' => FALSE,
              'supports_response_schema' => FALSE,
              'supports_moderation' => FALSE,
              'supports_pdf_input' => FALSE,
              'supports_system_messages' => FALSE,
              'supported_openai_params' => ['dimensions'],
            ],
          ],
        ],
      ])),
    ]);
    $handler_stack = HandlerStack::create($mock_handler);
    $handler_stack->push(Middleware::history($transactions));
    $http_client = new Client(['handler' => $handler_stack]);

    $client = new AmazeeClient(
      $http_client,
      new NullLogger(),
      $this->container->get('config.factory'),
    );
    $client->setHost('https://api.amazee.ai');
    $client->setToken('test-token');

    $first = $client->models();
    $second = $client->models();

    self::assertCount(1, $first);
    self::assertSame($first, $second);
    self::assertCount(1, $transactions, 'Model metadata should be fetched once per client instance.');
  }

}
