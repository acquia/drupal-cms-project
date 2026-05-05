<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use Drupal\ai_provider_amazeeio\Form\AmazeeioAiConfigForm;
use Drupal\ai_provider_amazeeio\TrialAccess\NullProgressReporter;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerFactoryInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisioningException;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisioningResult;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\State\StateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Tests amazee.ai trial account provisioning.
 *
 * @internal This class is not part of the module's public programming API.
 */
final class TrialAccessProvisioningTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'key',
    'ai_provider_amazeeio',
  ];

  /**
   * The mock handler.
   */
  private MockHandler $mockHandler;

  /**
   * Path to the fixtures directory.
   */
  private string $fixtureBasePath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai_provider_amazeeio']);

    $this->fixtureBasePath = dirname(__DIR__) . '/fixtures';
    $this->mockHandler = new MockHandler();
    $stack = HandlerStack::create($this->mockHandler);
    $client = new Client(['handler' => $stack]);

    $this->container->set('http_client', $client);

    // Default: authorization succeeds so tests can focus on provisioning steps.
    $this->container->set('ai_provider_amazeeio.api_client', new class($this->container->get('http_client'), new NullLogger()) extends AmazeeClient {

      /**
       * {@inheritdoc}
       */
      public function authorized(): bool {
        return TRUE;
      }

    });
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // Guardrails are in place, we can bypass the built-in protection
    // that disables trial access provisioning in tests.
    $container->setParameter('ai_provider_amazeeio.internal.disable_environment_detection', TRUE);
  }

  /**
   * Provisions trial credentials and persists config, keys, and the trial flag.
   */
  public function testSuccessfulTrialAccessProvisioning(): void {
    $fixture_path = $this->fixtureBasePath . '/auth/generate-trial-access/success.json';

    self::assertFileExists(
      $fixture_path,
      sprintf('Fixture JSON must exist at "%s" so the HTTP mock can return a valid API response.', $fixture_path)
    );

    $this->mockHandler->append(new Response(
      200,
      ['Content-Type' => 'application/json'],
      (string) file_get_contents($fixture_path),
    ));

    $api_key_entity = Key::load(AmazeeioAiConfigForm::API_KEY_NAME);
    self::assertNotNull(
      $api_key_entity,
      'The LiteLLM credentials Key entity must exist before provisioning so it can be populated.'
    );
    self::assertSame(
      '',
      (string) $api_key_entity->getKeyValue(TRUE),
      'The LiteLLM password must be empty before provisioning runs.'
    );

    $vdb_key_entity = Key::load(AmazeeioAiConfigForm::VDB_PASSWORD_NAME);
    self::assertNotNull(
      $vdb_key_entity,
      'The VDB credentials Key entity must exist before provisioning so it can be populated.'
    );
    self::assertSame(
      '',
      (string) $vdb_key_entity->getKeyValue(TRUE),
      'The VDB password must be empty before provisioning runs.'
    );

    $factory = $this->container->get(TrialAccountProvisionerFactoryInterface::class);

    $result = $factory->create(new NullProgressReporter())->provision();
    self::assertSame(
      TrialAccountProvisioningResult::Provisioned,
      $result,
      'Provisioning must return Provisioned for a successful first-time run.'
    );

    $api_key_entity = Key::load(AmazeeioAiConfigForm::API_KEY_NAME);
    self::assertNotNull(
      $api_key_entity,
      'The LiteLLM credentials Key entity must still be loadable after provisioning.'
    );
    self::assertSame(
      'llm-secret',
      (string) $api_key_entity->getKeyValue(TRUE),
      'The provisioned LiteLLM password must be stored in the LiteLLM credentials Key entity.'
    );

    $vdb_key_entity = Key::load(AmazeeioAiConfigForm::VDB_PASSWORD_NAME);
    self::assertSame(
      'db-secret',
      (string) $vdb_key_entity->getKeyValue(TRUE),
      'The provisioned VDB password must be stored in the VDB credentials Key entity.'
    );

    $trial_flag = $this->container->get(StateInterface::class)->get('ai_provider_amazeeio.trial_account');
    self::assertTrue(
      (bool) $trial_flag,
      'State key "ai_provider_amazeeio.trial_account" must be TRUE after successful trial provisioning.'
    );

    $config = $this->config('ai_provider_amazeeio.settings');
    self::assertSame(
      'vectordb1.de103.amazee.ai',
      (string) $config->get('postgres_host'),
      'Provisioning must write the expected postgres_host to ai_provider_amazeeio.settings.'
    );
    self::assertSame(
      'db_1234',
      (string) $config->get('postgres_default_database'),
      'Provisioning must write the expected postgres_default_database to ai_provider_amazeeio.settings.'
    );
    self::assertSame(
      'user_12345',
      (string) $config->get('postgres_username'),
      'Provisioning must write the expected postgres_username to ai_provider_amazeeio.settings.'
    );

    self::assertSame(
      AmazeeioAiConfigForm::API_KEY_NAME,
      (string) $config->get('api_key'),
      'Provisioning must not override the configured LiteLLM credentials Key entity ID.'
    );
    self::assertSame(
      AmazeeioAiConfigForm::VDB_PASSWORD_NAME,
      (string) $config->get('postgres_password'),
      'Provisioning must not override the configured VDB password credentials Key entity ID.'
    );
  }

  /**
   * Fails provisioning when the API returns an HTTP 4xx response.
   */
  public function testProvisioningFailsOnHttp4xx(): void {
    $this->expectException(TrialAccountProvisioningException::class);

    $this->mockHandler->append(new Response(
      401,
      ['Content-Type' => 'application/json'],
      '{"message":"unauthorized"}'
    ));

    $factory = $this->container->get(TrialAccountProvisionerFactoryInterface::class);
    $factory->create(new NullProgressReporter())->provision();
  }

  /**
   * Fails provisioning when the API returns an HTTP 5xx response.
   */
  public function testProvisioningFailsOnHttp5xx(): void {
    $this->expectException(TrialAccountProvisioningException::class);

    $this->mockHandler->append(new Response(
      503,
      ['Content-Type' => 'application/json'],
      '{"message":"service unavailable"}'
    ));

    $factory = $this->container->get(TrialAccountProvisionerFactoryInterface::class);
    $factory->create(new NullProgressReporter())->provision();
  }

  /**
   * Fails provisioning when the API returns malformed JSON.
   */
  public function testProvisioningFailsOnMalformedJson(): void {
    $this->expectException(TrialAccountProvisioningException::class);
    $this->expectExceptionMessage('Malformed response');

    $this->mockHandler->append(new Response(
      200,
      ['Content-Type' => 'application/json'],
      '{not-json'
    ));

    $factory = $this->container->get(TrialAccountProvisionerFactoryInterface::class);
    $factory->create(new NullProgressReporter())->provision();
  }

  /**
   * Does not persist credentials or trial state if a 4xx response is returned.
   */
  public function testProvisioningDoesNotWriteAnythingOnHttp4xx(): void {
    $this->mockHandler->append(new Response(401, [], 'nope'));

    $factory = $this->container->get(TrialAccountProvisionerFactoryInterface::class);

    try {
      $factory->create(new NullProgressReporter())->provision();
      self::fail('Provisioning must throw when the API returns HTTP 401.');
    }
    catch (TrialAccountProvisioningException $e) {
      $api_key_entity = Key::load(AmazeeioAiConfigForm::API_KEY_NAME);
      self::assertNotNull(
        $api_key_entity,
        'The LiteLLM credentials Key entity must exist after a failed provisioning attempt.'
      );
      self::assertSame(
        '',
        (string) $api_key_entity->getKeyValue(TRUE),
        'The LiteLLM credential must remain empty after a failed provisioning attempt.'
      );

      $trial_flag = $this->container->get(StateInterface::class)->get('ai_provider_amazeeio.trial_account');
      self::assertFalse(
        (bool) $trial_flag,
        'Trial flag must not be set when provisioning fails.'
      );
    }
  }

  /**
   * Fails provisioning when the API credentials do not authorize afterwards.
   */
  public function testProvisioningFailsOnAuthorizationFailure(): void {
    $fixture_path = $this->fixtureBasePath . '/auth/generate-trial-access/success.json';

    self::assertFileExists(
      $fixture_path,
      sprintf('Fixture JSON must exist at "%s" so the HTTP mock can return a valid API response.', $fixture_path)
    );

    // Return a successful trial access response, but fail authorization after.
    $this->mockHandler->append(new Response(
      200,
      ['Content-Type' => 'application/json'],
      (string) file_get_contents($fixture_path),
    ));

    $this->container->set('ai_provider_amazeeio.api_client', new class($this->container->get('http_client'), new NullLogger()) extends AmazeeClient {

      public function __construct(Client $client, LoggerInterface $logger, public bool $authorizedGotCalled = FALSE) {
        parent::__construct($client, $logger);
      }

      /**
       * {@inheritdoc}
       */
      public function authorized(): bool {
        $this->authorizedGotCalled = TRUE;
        return FALSE;
      }

    });

    self::assertFalse($this->container->get('ai_provider_amazeeio.api_client')->authorizedGotCalled, 'AmazeeClient::authorized() must not have been called yet.');
    $factory = $this->container->get(TrialAccountProvisionerFactoryInterface::class);

    try {
      $factory->create(new NullProgressReporter())->provision();
      self::assertTrue($this->container->get('ai_provider_amazeeio.api_client')->authorizedGotCalled, 'AmazeeClient::authorized() must have been called.');
      self::fail('Provisioning must throw when AmazeeClient::authorized() returns FALSE.');
    }
    catch (TrialAccountProvisioningException) {
      self::assertNull($this->container->get(StateInterface::class)->get('ai_provider_amazeeio.trial_account'), 'Trial flag must not be set when authorization fails.');
    }
  }

  /**
   * Skips provisioning if credentials already exist.
   */
  public function testProvisioningReturnsAlreadyProvisionedWhenCredentialsExist(): void {
    // Do not enqueue any HTTP response. If the provisioner makes an HTTP call
    // despite existing credentials, the mock queue will be empty and the test
    // should fail, which is exactly what we want.
    $api_key_entity = Key::load(AmazeeioAiConfigForm::API_KEY_NAME);
    self::assertNotNull(
      $api_key_entity,
      'The LiteLLM credentials Key entity must exist so it can be pre-populated for this test.'
    );
    $api_key_entity->set('key_provider', 'config')
      ->set('key_provider_settings', ['key_value' => 'preexisting-llm-secret'])
      ->set('key_input', 'text_field')
      ->save();

    $vdb_key_entity = Key::load(AmazeeioAiConfigForm::VDB_PASSWORD_NAME);
    self::assertNotNull(
      $vdb_key_entity,
      'The VDB credentials Key entity must exist so it can be pre-populated for this test.'
    );
    $vdb_key_entity->set('key_provider', 'config')
      ->set('key_provider_settings', ['key_value' => 'preexisting-db-secret'])
      ->set('key_input', 'text_field')
      ->save();

    $factory = $this->container->get(TrialAccountProvisionerFactoryInterface::class);
    $result = $factory->create(new NullProgressReporter())->provision();

    self::assertSame(
      TrialAccountProvisioningResult::AlreadyProvisioned,
      $result,
      'Provisioning must return AlreadyProvisioned when credentials already exist.'
    );

    self::assertNull($this->container->get(StateInterface::class)->get('ai_provider_amazeeio.trial_account'), 'Trial flag must not be set by the AlreadyProvisioned path (it should only be set on a successful provisioning run).'
    );
  }

}
