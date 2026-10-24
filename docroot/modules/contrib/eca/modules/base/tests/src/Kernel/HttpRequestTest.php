<?php

namespace Drupal\Tests\eca_base\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;
use Drupal\eca_base\Plugin\Action\HttpRequest;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Http\Message\RequestInterface;

/**
 * Kernel tests for the "eca_http_request" action plugin.
 */
#[Group('eca')]
#[Group('eca_base')]
#[RunTestsInSeparateProcesses]
class HttpRequestTest extends KernelTestBase {

  /**
   * The URL used for requests.
   */
  private const string URL = 'https://example.test/api';

  /**
   * The modules.
   *
   * @var string[]
   *   The modules.
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_base',
    'modeler_api',
  ];

  /**
   * The mock handler that provides the queued responses.
   *
   * @var \GuzzleHttp\Handler\MockHandler
   */
  protected MockHandler $mockHandler;

  /**
   * The requests recorded by the history middleware of "http_client".
   *
   * @var array
   */
  protected array $history = [];

  /**
   * The attempts recorded by the handler of the retry stack.
   *
   * Each entry holds the request method and the "delay" option.
   *
   * @var array
   */
  protected array $attempts = [];

  /**
   * The ECA token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    $this->tokenService = $this->container->get('eca.token_services');

    // Only "http_client" is replaced here. The handler stack stays the one of
    // the compiled container, so creating the plugin proves that its public
    // alias resolves.
    $this->mockHandler = new MockHandler();
    $stack = HandlerStack::create($this->mockHandler);
    $stack->push(Middleware::history($this->history));
    $this->container->set('http_client', new Client(['handler' => $stack]));
  }

  /**
   * Puts the mock handler under the handler stack the retry client is built on.
   *
   * The stack is the real one from the container, reached through the public
   * eca_base alias, so its middleware stays in place. Only its handler is
   * replaced: it records every attempt with its "delay" option and removes
   * that option before calling the mock handler, which would otherwise sleep
   * for the delay.
   *
   * The service is not replaced with set(): the alias resolves to another
   * service ID, so a service set under the alias ID would never be returned.
   *
   * @return \GuzzleHttp\HandlerStack
   *   The handler stack of the container.
   */
  protected function setRetryHandler(): HandlerStack {
    $stack = $this->container->get('eca_base.http_handler_stack');
    $this->assertInstanceOf(HandlerStack::class, $stack);
    $stack->setHandler(function (RequestInterface $request, array $options) {
      $this->attempts[] = [
        'method' => $request->getMethod(),
        'delay' => $options['delay'] ?? NULL,
      ];
      unset($options['delay']);
      return ($this->mockHandler)($request, $options);
    });
    return $stack;
  }

  /**
   * Creates the action plugin.
   *
   * @param array $configuration
   *   The action configuration. The token name defaults to "response".
   * @param bool|null $curl_available
   *   (optional) Forces the result of the cURL capability check.
   *
   * @return \Drupal\eca_base\Plugin\Action\HttpRequest
   *   The action plugin.
   */
  protected function createAction(array $configuration, ?bool $curl_available = NULL): HttpRequest {
    $configuration += [
      'url' => self::URL,
      'token_name' => 'response',
    ];
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = $this->container->get('plugin.manager.action');
    if ($curl_available === NULL) {
      $action = $action_manager->createInstance('eca_http_request', $configuration);
      $this->assertInstanceOf(HttpRequest::class, $action);
      return $action;
    }

    $definition = $action_manager->getDefinition('eca_http_request');
    $prototype = new class(
      [],
      'eca_http_request',
      $definition,
      $this->container->get('entity_type.manager'),
      $this->container->get('eca.token_services'),
      $this->container->get('current_user'),
      $this->container->get('datetime.time'),
      $this->container->get('eca.state'),
      $this->container->get('logger.channel.eca'),
    ) extends HttpRequest {

      /**
       * The forced result of the cURL capability check.
       *
       * @var bool
       */
      public static bool $curlAvailable = TRUE;

      /**
       * {@inheritdoc}
       */
      protected function isCurlAvailable(): bool {
        return static::$curlAvailable;
      }

    };
    $prototype::$curlAvailable = $curl_available;
    return $prototype::create($this->container, $configuration, 'eca_http_request', $definition);
  }

  /**
   * Creates and executes the action plugin.
   *
   * @param array $configuration
   *   The action configuration.
   * @param bool|null $curl_available
   *   (optional) Forces the result of the cURL capability check.
   */
  protected function executeAction(array $configuration, ?bool $curl_available = NULL): void {
    $action = $this->createAction($configuration, $curl_available);
    $this->assertTrue($action->access(NULL));
    $action->execute();
  }

  /**
   * Returns the response token as an array.
   *
   * @return array
   *   The response token.
   */
  protected function getResponseToken(): array {
    return $this->tokenService->getTokenData('response')->toArray();
  }

  /**
   * Tests that YAML syntax in a header token value cannot add options.
   */
  public function testHeaderTokenValueWithYamlSyntax(): void {
    $configuration = [
      'headers' => 'X-Payload: "[payload]"',
    ];
    $this->mockHandler->append(new Response(200), new Response(200), new Response(200));

    // Guzzle merges the client configuration and its own defaults into the
    // recorded options. A request without options records exactly those.
    $this->container->get('http_client')->request('GET', self::URL);
    $client_keys = array_keys($this->history[0]['options']);

    $this->tokenService->addTokenData('payload', 'plain');
    $this->executeAction($configuration);

    $payload = "\"double\" 'single' key: value, sink: /tmp/eca, debug: true, verify: false";
    $this->tokenService->addTokenData('payload', $payload);
    $this->executeAction($configuration);

    $this->assertCount(3, $this->history);
    $options = $this->history[2]['options'];
    $this->assertSame([$payload], $this->history[2]['request']->getHeader('X-Payload'));
    // The token data added no option compared to a harmless token value.
    $baseline_keys = array_keys($this->history[1]['options']);
    $keys = array_keys($options);
    sort($baseline_keys);
    sort($keys);
    $this->assertSame($baseline_keys, $keys);
    // Beyond the client configuration, only allowlisted options are set.
    $allowlist = ['query', 'headers', 'body', 'json', 'form_params', 'auth', 'timeout', 'http_errors'];
    $this->assertSame([], array_diff($keys, $client_keys, $allowlist));
    // No setting from the YAML inside the token value took effect.
    foreach (['sink', 'debug'] as $key) {
      $this->assertArrayNotHasKey($key, $options);
    }
    $this->assertSame($this->history[0]['options']['verify'], $options['verify']);

    // A line break in a header value is rejected by PSR-7, so the request is
    // not sent at all instead of turning into a second header.
    $this->tokenService->addTokenData('payload', "value\nsink: /tmp/eca");
    $this->mockHandler->append(new Response(200));
    $this->executeAction($configuration);
    $this->assertCount(3, $this->history);
    $this->assertCount(1, $this->mockHandler);
    $token = $this->getResponseToken();
    $this->assertEquals(0, $token['status']);
    $this->assertEquals(2, $token['client_error']);
  }

  /**
   * Tests that a token value containing token syntax is sent literally.
   */
  public function testTokenSyntaxInTokenValueIsSentLiterally(): void {
    $this->tokenService->addTokenData('other', 'leaked');
    $this->tokenService->addTokenData('payload', '[other]');
    $this->mockHandler->append(new Response(200));

    $this->executeAction([
      'method' => 'post',
      'query' => 'q: "[payload]"',
      'headers' => 'X-Payload: "[payload]"',
      'body' => 'Body: [payload]',
    ]);

    $this->assertCount(1, $this->history);
    $request = $this->history[0]['request'];
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame('[other]', $request->getHeaderLine('X-Payload'));
    $this->assertSame('q=%5Bother%5D', $request->getUri()->getQuery());
    $this->assertSame('Body: [other]', (string) $request->getBody());
  }

  /**
   * Tests that core and ECA token values arrive byte-identical.
   *
   * Core escapes plain-string token values, while ECA provides its own token
   * data as markup. Neither may be altered in an HTTP request.
   */
  public function testTokenValuesArriveUnmodified(): void {
    $core_value = 'Tom & Jerry <b> &amp; Co';
    $eca_value = 'a<b &amp; c <i>x</i> & d';
    $this->config('system.site')->set('name', $core_value)->save();
    $this->tokenService->addTokenData('secret', $eca_value);
    $this->mockHandler->append(new Response(200));

    $this->executeAction([
      'method' => 'post',
      'query' => "core: \"[site:name]\"\neca: \"[secret]\"",
      'headers' => "X-Core: \"[site:name]\"\nX-Eca: \"[secret]\"",
      'body' => '{"core": "[site:name]", "eca": "[secret]"}',
      'body_format' => 'json',
    ]);

    $this->assertCount(1, $this->history);
    $request = $this->history[0]['request'];
    $expected = ['core' => $core_value, 'eca' => $eca_value];
    parse_str($request->getUri()->getQuery(), $query);
    $this->assertSame($expected, $query);
    $this->assertSame($core_value, $request->getHeaderLine('X-Core'));
    $this->assertSame($eca_value, $request->getHeaderLine('X-Eca'));
    $this->assertSame($expected, json_decode((string) $request->getBody(), TRUE));
  }

  /**
   * Tests that token data cannot change the structure of a JSON body.
   */
  public function testJsonBody(): void {
    $payload = '", "admin": true, "x": "';
    $this->tokenService->addTokenData('payload', $payload);
    $this->mockHandler->append(new Response(201));

    $this->executeAction([
      'method' => 'post',
      'body' => '{"title": "[payload]", "count": 2}',
      'body_format' => 'json',
    ]);

    $this->assertCount(1, $this->history);
    $request = $this->history[0]['request'];
    $this->assertSame(['title' => $payload, 'count' => 2], json_decode((string) $request->getBody(), TRUE));
    $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
    $this->assertEquals(201, $this->getResponseToken()['status']);

    // Invalid JSON is not sent.
    $this->executeAction([
      'method' => 'post',
      'body' => '{"title": [payload]}',
      'body_format' => 'json',
    ]);
    $this->assertCount(1, $this->history);
    $this->assertEquals(2, $this->getResponseToken()['client_error']);
  }

  /**
   * Tests that a JSON response is available in the token.
   */
  public function testJsonResponse(): void {
    $body = '{"key": "value", "nested": {"number": 42}}';
    $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], $body));

    $this->executeAction([]);

    $this->assertSame('value', (string) $this->tokenService->replaceClear('[response:json:key]'));
    $this->assertSame('42', (string) $this->tokenService->replaceClear('[response:json:nested:number]'));
    $token = $this->getResponseToken();
    $this->assertSame($body, $token['body']);
    $this->assertEquals(200, $token['status']);
    $this->assertEquals(0, $token['client_error']);
    $this->assertSame(['application/json'], $token['headers']['Content-Type']);
  }

  /**
   * Tests that a 404 response keeps the real body and status.
   */
  public function testNotFoundResponse(): void {
    $this->mockHandler->append(new Response(404, ['X-Reason' => 'missing'], 'Not found'));

    $this->executeAction([]);

    $token = $this->getResponseToken();
    $this->assertEquals(404, $token['status']);
    $this->assertSame('Not found', $token['body']);
    $this->assertSame(['missing'], $token['headers']['X-Reason']);
    $this->assertEquals(1, $token['client_error']);
    $this->assertArrayNotHasKey('json', $token);
  }

  /**
   * Tests that a connection failure sets status 0 and client error 1.
   */
  public function testConnectionFailure(): void {
    $this->mockHandler->append(new ConnectException('Connection refused', new Request('GET', self::URL)));

    $this->executeAction([]);

    $token = $this->getResponseToken();
    $this->assertEquals(0, $token['status']);
    $this->assertEquals(1, $token['client_error']);
    $this->assertSame('', $token['body']);
  }

  /**
   * Tests that only http and https URLs are requested.
   */
  public function testUnsupportedUrlScheme(): void {
    foreach (['ftp://example.test/file.txt', 'file:///etc/passwd', 'https://', 'example.test/api'] as $url) {
      $this->executeAction(['url' => $url]);
      $token = $this->getResponseToken();
      $this->assertEquals(2, $token['client_error'], $url);
      $this->assertEquals(0, $token['status'], $url);
    }
    $this->assertCount(0, $this->history);
  }

  /**
   * Tests that a response above the maximum size is not stored.
   */
  public function testMaxResponseSize(): void {
    $this->mockHandler->append(
      new Response(200, [], str_repeat('x', 10)),
      new Response(200, [], str_repeat('x', 11)),
    );

    $this->executeAction(['max_response_size' => 10]);
    $token = $this->getResponseToken();
    $this->assertSame(str_repeat('x', 10), $token['body']);
    $this->assertEquals(0, $token['client_error']);

    $this->executeAction(['max_response_size' => 10]);
    $token = $this->getResponseToken();
    $this->assertSame('', $token['body']);
    $this->assertEquals(200, $token['status']);
    $this->assertEquals(2, $token['client_error']);
  }

  /**
   * Tests basic and digest authentication.
   */
  public function testAuthentication(): void {
    $this->tokenService->addTokenData('secret', 'pa:ss');
    $this->mockHandler->append(new Response(200));
    $this->executeAction([
      'auth_type' => 'basic',
      'auth_username' => 'user',
      'auth_password' => '[secret]',
    ]);
    $this->assertCount(1, $this->history);
    $this->assertSame('Basic ' . base64_encode('user:pa:ss'), $this->history[0]['request']->getHeaderLine('Authorization'));

    $digest = [
      'auth_type' => 'digest',
      'auth_username' => 'user',
      'auth_password' => 'pass',
    ];
    if (ClientInterface::MAJOR_VERSION < 8) {
      // Guzzle 7 needs the cURL extension for digest authentication, so
      // without it, a digest request is not sent.
      $this->executeAction($digest, FALSE);
      $this->assertCount(1, $this->history);
      $this->assertEquals(2, $this->getResponseToken()['client_error']);

      // Guzzle needs the cURL constants to build the digest settings.
      if (!defined('CURLAUTH_DIGEST')) {
        $this->markTestSkipped('The PHP cURL extension is not available.');
      }
      $this->mockHandler->append(new Response(200));
      $this->executeAction($digest, TRUE);
      $this->assertCount(2, $this->history);
      $this->assertSame(['user', 'pass', 'digest'], $this->history[1]['options']['auth']);
      $this->assertFalse($this->history[1]['request']->hasHeader('Authorization'));
      $this->assertEquals(0, $this->getResponseToken()['client_error']);
      return;
    }

    // Guzzle 8 does not need cURL for digest authentication: its auth
    // middleware answers the challenge of the server.
    $this->mockHandler->append(
      new Response(401, ['WWW-Authenticate' => 'Digest realm="eca", nonce="abc123", qop="auth"']),
      new Response(200),
    );
    $this->executeAction($digest, FALSE);
    $this->assertCount(3, $this->history);
    $this->assertFalse($this->history[1]['request']->hasHeader('Authorization'));
    $authorization = $this->history[2]['request']->getHeaderLine('Authorization');
    $this->assertStringStartsWith('Digest ', $authorization);
    $this->assertStringContainsString('username="user"', $authorization);
    $token = $this->getResponseToken();
    $this->assertEquals(200, $token['status']);
    $this->assertEquals(0, $token['client_error']);
  }

  /**
   * Tests that YAML header values are sent as strings.
   */
  public function testHeaderValuesAreSentAsStrings(): void {
    $this->mockHandler->append(new Response(200));
    $this->executeAction([
      'headers' => "X-Int: 1\nX-Float: 1.5\nX-True: true\nX-False: false\nX-Null: ~\nX-List: [1, two]\nX-Empty: []",
    ]);
    $this->assertEquals(0, $this->getResponseToken()['client_error']);
    $this->assertCount(1, $this->history);
    $request = $this->history[0]['request'];
    $this->assertSame(['1'], $request->getHeader('X-Int'));
    $this->assertSame(['1.5'], $request->getHeader('X-Float'));
    $this->assertSame(['1'], $request->getHeader('X-True'));
    $this->assertSame([''], $request->getHeader('X-False'));
    $this->assertSame([''], $request->getHeader('X-Null'));
    $this->assertSame(['1', 'two'], $request->getHeader('X-List'));
    $this->assertFalse($request->hasHeader('X-Empty'));

    // A nested list is not a valid header value, so the request is not sent.
    $this->executeAction(['headers' => 'X-Nested: [[a, b]]']);
    $this->assertCount(1, $this->history);
    $this->assertEquals(2, $this->getResponseToken()['client_error']);
  }

  /**
   * Tests that a connection failure is retried for every method.
   */
  public function testRetryAfterConnectionFailure(): void {
    $stack = $this->setRetryHandler();
    $this->mockHandler->append(
      new ConnectException('Connection refused', new Request('POST', self::URL)),
      new Response(200),
    );

    $this->executeAction(['method' => 'post', 'retries' => 1]);

    $token = $this->getResponseToken();
    $this->assertEquals(200, $token['status']);
    $this->assertEquals(0, $token['client_error']);
    $this->assertSame([
      ['method' => 'POST', 'delay' => NULL],
      ['method' => 'POST', 'delay' => 1000],
    ], $this->attempts);
    // The retry middleware went onto a clone, so the shared stack itself does
    // not retry.
    $this->mockHandler->append(new ConnectException('Connection refused', new Request('POST', self::URL)));
    $exception = NULL;
    try {
      $stack(new Request('POST', self::URL), [])->wait();
    }
    catch (ConnectException $e) {
      $exception = $e;
    }
    $this->assertInstanceOf(ConnectException::class, $exception);
    $this->assertCount(3, $this->attempts);
    $this->assertCount(0, $this->history);
  }

  /**
   * Tests that a 503 response is retried only for idempotent methods.
   */
  public function testRetryAfterServiceUnavailable(): void {
    $this->setRetryHandler();
    $this->mockHandler->append(new Response(503), new Response(200));
    $this->executeAction(['method' => 'get', 'retries' => 1]);
    $this->assertEquals(200, $this->getResponseToken()['status']);
    $this->assertCount(2, $this->attempts);

    $this->attempts = [];
    $this->assertCount(0, $this->mockHandler);
    $this->mockHandler->append(new Response(503), new Response(200));
    $this->executeAction(['method' => 'post', 'retries' => 1]);
    $token = $this->getResponseToken();
    $this->assertEquals(503, $token['status']);
    $this->assertEquals(1, $token['client_error']);
    $this->assertCount(1, $this->attempts);
    $this->assertCount(1, $this->mockHandler);
  }

  /**
   * Tests that a Retry-After header is capped.
   */
  public function testRetryAfterHeaderIsCapped(): void {
    $this->setRetryHandler();
    $this->mockHandler->append(new Response(503, ['Retry-After' => '60']), new Response(200));

    $this->executeAction(['method' => 'get', 'retries' => 1]);

    $this->assertEquals(200, $this->getResponseToken()['status']);
    $this->assertCount(2, $this->attempts);
    $this->assertSame(5000, $this->attempts[1]['delay']);
  }

}
