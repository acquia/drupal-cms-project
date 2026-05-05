<?php

namespace Drupal\ai_provider_amazeeio_test;

use Drupal\Core\State\StateInterface;
use Drupal\ai_provider_amazeeio\Form\AmazeeioAiConfigForm;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Mock the http_client service.
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
class MockHttpClient extends Client {

  /**
   * The decorated http_client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $innerService;

  /**
   * A state service for simple in-test states.
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(Client $inner_service, StateInterface $state) {
    $this->state = $state;
    $this->innerService = $inner_service;
  }

  /**
   * Mocked endpoints map.
   *
   * @value List of mocked endpoints, mapping to methods that execute them.
   */
  protected array $requests = [
    'POST:/auth/validate-email' => 'mockValidateEmail',
    'POST:/auth/sign-in' => 'mockSignIn',
    'GET:/auth/me' => 'mockMe',
    'GET:/regions' => 'mockRegions',
    'POST:/private-ai-keys' => 'mockPostPrivateKey',
    'GET:/private-ai-keys' => 'mockGetPrivateKeys',
    'GET:/ch1/key/info' => 'mockKeyInfo',
    // Used for testing the testing framework.
    'GET:/mocked/request' => 'mockTestRequest',
  ];

  /**
   * Mock requests for email validation.
   */
  protected function mockValidateEmail(ParameterBag $body, ParameterBag $header): ResponseInterface {
    // Nothing to do here. In testing we don't really send email.
    // Verification code "42" works.
    return $this->success([]);
  }

  /**
   * Mock requests for code-based sign-in.
   */
  protected function mockSignIn(ParameterBag $body, ParameterBag $header): ResponseInterface {
    if (!($body->get('username') === 'john@doe.com' && $body->get('verification_code') === '42')) {
      $this->error(401, 'Invalid code.');
    }
    return $this->success(
      [
        'access_token' => '1234',
        'token_type' => 'bearer',
      ]
    );
  }

  /**
   * Mock the user info request.
   */
  protected function mockMe(ParameterBag $body, ParameterBag $header): ResponseInterface {
    if ($err = $this->authorizeAccess($body, $header)) {
      return $err;
    }
    return $this->success(
      [
        "email" => "john@doe.com",
        "id" => 0,
        "is_active" => TRUE,
        "is_admin" => TRUE,
        "team_id" => 1,
        "team_name" => "amazee.io",
        "role" => "gm",
      ]
    );
  }

  /**
   * Verify key access.
   */
  protected function authorizeAccess(ParameterBag $body, ParameterBag $header): ?ResponseInterface {
    if (!$header->has('Authorization')) {
      $this->error(401, 'Missing authorization header.');
    }
    if (!in_array($header->get('Authorization'), ['Bearer 1234', 'Bearer 4321'])) {
      $this->error(403, 'Invalid authorization header.');
    }
    return NULL;
  }

  /**
   * Mocked region list for testing.
   */
  const REGIONS = [
    [
      "id" => 0,
      "name" => "us-1",
      "label" => "US 1",
      "postgres_host" => "https://amazeeio.vdb/us1",
      "litellm_api_url" => "https://amazeeio.llm/us1",
      "is_active" => TRUE,
      "created_at" => "2025-05-12T18:56:01.272Z",
    ],
    [
      "id" => 1,
      "name" => "inactive-region",
      "label" => "Inactive Region",
      "postgres_host" => "https://amazeeio.vdb/inactive",
      "litellm_api_url" => "https://amazeeio.llm/inactive",
      "is_active" => FALSE,
      "created_at" => "2025-05-12T18:56:01.272Z",
    ],
    [
      "id" => 2,
      "name" => "ch-1",
      "label" => "CH 1",
      "postgres_host" => "https://amazeeio.vdb/ch1",
      "litellm_api_url" => "https://amazeeio.llm/ch1",
      "is_active" => TRUE,
      "created_at" => "2025-05-12T18:56:01.272Z",
    ],
  ];

  /**
   * Mock result of the regions list.
   */
  protected function mockRegions(ParameterBag $body, ParameterBag $header): ResponseInterface {
    if ($err = $this->authorizeAccess($body, $header)) {
      return $err;
    }
    return $this->success(static::REGIONS);
  }

  /**
   * Mock private key generation.
   */
  protected function mockPostPrivateKey(ParameterBag $body, ParameterBag $header): ResponseInterface {
    if ($this->state->get('ai_provider_amazeeio_test')) {
      throw new \Exception('Key has already been created.');
    }
    if ($err = $this->authorizeAccess($body, $header)) {
      return $err;
    }
    if (!$body->has('region_id')) {
      $this->error(400, 'Missing region_id.');
    }
    $region_id = $body->get('region_id');
    if ($region_id === "0") {
      // Simulate a broken region for error handling tests.
      $this->error(500, 'Region "US 1" is down.');
    }
    if (!array_key_exists($region_id, static::REGIONS)) {
      $this->error(400, "Invalid region_id $region_id.");
    }
    $region = static::REGIONS[$region_id];
    $this->state->set('ai_provider_amazeeio_test', TRUE);
    return $this->success(
      [
        'litellm_token' => '4321',
        'litellm_api_url' => $region['litellm_api_url'],
      ]
    );
  }

  /**
   * Mock private key list.
   */
  protected function mockGetPrivateKeys(ParameterBag $body, ParameterBag $header): ResponseInterface {
    if ($err = $this->authorizeAccess($body, $header)) {
      return $err;
    }
    $keys = [
      [
        "id" => 0,
        "name" => "some other key",
        "region" => static::REGIONS[0]['name'],
        "label" => static::REGIONS[0]['label'],
        "database_host" => static::REGIONS[0]['postgres_host'],
        "database_name" => "db_name_us1",
        "database_username" => "db_user_us1",
        "database_password" => "db_pass_us1",
        "litellm_api_url" => static::REGIONS[0]['litellm_api_url'],
        "litellm_token" => "5678",
        "created_at" => "2025-05-13T05:42:48.124Z",
        "owner_id" => 0,
        "team_id" => 0,
      ],
    ];
    if ($this->state->get('ai_provider_amazeeio_test')) {
      $keys[] = [
        "id" => 1,
        "name" => AmazeeioAiConfigForm::generatePrivateKeyName(),
        "region" => static::REGIONS[2]['name'],
        "label" => static::REGIONS[2]['label'],
        "database_host" => static::REGIONS[2]['postgres_host'],
        "database_name" => "db_name_ch1",
        "database_username" => "db_user_ch1",
        "database_password" => "db_pass_ch1",
        "litellm_api_url" => static::REGIONS[2]['litellm_api_url'],
        "litellm_token" => "4321",
        "created_at" => "2025-05-13T05:42:48.124Z",
        "owner_id" => 0,
        "team_id" => 0,
      ];
    }
    return $this->success($keys);
  }

  /**
   * Mock key info request.
   */
  protected function mockKeyInfo(ParameterBag $body, ParameterBag $header): ResponseInterface {
    if ($err = $this->authorizeAccess($body, $header)) {
      return $err;
    }
    $key = substr($header->get('Authorization'), strlen('Bearer '));
    return $this->success(
      [
        'key' => $key,
        'info' => [
          'key_alias' => AmazeeioAiConfigForm::generatePrivateKeyName(),
          'key_name' => $key,
          'spend' => 200,
          'max_budget' => 500,
          'blocked' => FALSE,
        ],
      ]
    );
  }

  /**
   * Mock request for testing the testing framework.
   */
  protected function mockTestRequest(ParameterBag $body, ParameterBag $header): ResponseInterface {
    if (!$header->has('Authentication')) {
      $this->error(401, 'Missing authentication header');
    }
    if (!$body->has('message')) {
      $this->error(400, 'Missing message argument');
    }
    return $this->success(
      [
        'uppercase' => strtoupper($body->get('message')),
      ]
    );
  }

  /**
   * Produce a successful response.
   *
   * @param array $body
   *   The JSON response body as an associative array.
   */
  protected function success(array $body): Response {
    return new Response(200, [], Utils::jsonEncode($body));
  }

  /**
   * Throw a client exception.
   */
  protected function error(int $status, string $message): void {
    throw new ClientException(
      message: $message,
      request: new Request('GET', ''),
      response: new Response($status, [], Utils::jsonEncode(['detail' => $message]))
    );
  }

  /**
   * {@inheritdoc}
   */
  public function request($method, $uri = '', array $options = []): ResponseInterface {
    $host = parse_url($uri, PHP_URL_HOST);
    if (strpos($host, 'amazee') === FALSE) {
      return $this->innerService->get($uri, $options);
    }

    $path = parse_url($uri, PHP_URL_PATH);
    $mockKey = "$method:$path";
    if (array_key_exists($mockKey, $this->requests)) {
      $method = $this->requests[$mockKey];
      $body = new ParameterBag(Utils::jsonDecode($options['body'] ?? '{}', TRUE));
      $headers = new ParameterBag($options['headers'] ?? []);
      return $this->$method($body, $headers);
    }
    throw new ClientException(
      message: "MockHttpClient: Unhandled request $method:$uri",
      request: new Request($method, $uri),
      response: new Response(400),
    );
  }

}
