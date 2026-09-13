<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_amazeeio\Kernel;

use Drupal\ai_provider_amazeeio\Plugin\ConfigAction\EnsureAmazeeAiAccess;
use Drupal\ai_provider_amazeeio\TrialAccess\ProgressReporterInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerFactoryInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisionerInterface;
use Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisioningResult;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\State\StateInterface;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the ensureAmazeeAiAccess config action.
 *
 * @internal
 */
final class EnsureAmazeeAiAccessTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'key',
    'ai_provider_amazeeio',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai_provider_amazeeio']);
  }

  /**
   * Create the config action plugin.
   *
   * Deferred out of setUp() so that a test can swap the mocked http_client
   * into the container *before* the trial account provisioner factory (and
   * the AmazeeClient) are first instantiated - otherwise those services are
   * cached with the real http_client and the mock never takes effect.
   */
  private function createAction(): EnsureAmazeeAiAccess {
    return \Drupal::service('plugin.manager.config_action')->createInstance('ensureAmazeeAiAccess');
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
   * Test the config action exists.
   */
  public function testActionExists(): void {
    $this->expectNotToPerformAssertions();
    $this->createAction()->apply('ai_provider_amazeeio.settings', []);
  }

  /**
   * A provisioning failure (e.g. amazee.ai at trial capacity) must not throw.
   *
   * The trial account is an anonymous convenience; a recipe that runs this
   * config action must complete successfully even when amazee.ai returns an
   * HTTP 429 "Trial capacity reached" response, so the site can be
   * configured with any AI provider afterwards.
   */
  public function testDoesNotThrowWhenProvisioningFails(): void {
    $mockHandler = new MockHandler([
      new Response(
        429,
        ['Content-Type' => 'application/json'],
        '{"detail":"Trial capacity reached. Please contact sales to continue."}'
      ),
    ]);
    $stack = HandlerStack::create($mockHandler);
    $this->container->set('http_client', new Client(['handler' => $stack]));

    // Create the action AFTER swapping in the mock so the provisioner
    // factory (and AmazeeClient) are built with the mocked http_client.
    $action = $this->createAction();

    $action->apply('ai_provider_amazeeio.settings', []);

    self::assertNull(
      $this->container->get(StateInterface::class)->get('ai_provider_amazeeio.trial_account'),
      'Trial flag must not be set when provisioning fails.'
    );
  }

  /**
   * ANY unexpected failure during provisioning must also be swallowed.
   *
   * This is a graceful fallback, not a contract limited to
   * TrialAccountProvisioningException: an unrelated \Throwable escaping
   * from anywhere in the provisioning path (e.g. the AI provider's
   * post-setup, or a Key/Config entity save) must not abort the recipe
   * apply either.
   */
  public function testDoesNotThrowOnUnexpectedThrowableDuringProvisioning(): void {
    $provisioner = new class implements TrialAccountProvisionerInterface {

      /**
       * {@inheritdoc}
       */
      public function provision(): TrialAccountProvisioningResult {
        throw new \RuntimeException('Unexpected failure unrelated to amazee.ai trial provisioning.');
      }

    };

    $factory = new class($provisioner) implements TrialAccountProvisionerFactoryInterface {

      public function __construct(
        private readonly TrialAccountProvisionerInterface $provisioner,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function create(ProgressReporterInterface $progressReporter): TrialAccountProvisionerInterface {
        return $this->provisioner;
      }

    };

    $this->container->set(TrialAccountProvisionerFactoryInterface::class, $factory);

    $this->expectNotToPerformAssertions();
    $this->createAction()->apply('ai_provider_amazeeio.settings', []);
  }

}
