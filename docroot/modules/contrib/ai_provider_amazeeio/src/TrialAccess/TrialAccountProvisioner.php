<?php

declare(strict_types=1);

namespace Drupal\ai_provider_amazeeio\TrialAccess;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use Drupal\ai_provider_amazeeio\Form\AmazeeioAiConfigForm;
use Drupal\ai_provider_amazeeio\Plugin\AiProvider\AmazeeioAiProvider;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Default trial account provisioner.
 *
 * This writes provider configuration, creates Key entities, runs provider
 * post-setup (unless in test module context), and validates that the resulting
 * credentials authorize successfully.
 *
 * @internal
 */
final class TrialAccountProvisioner implements TrialAccountProvisionerInterface {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ClientInterface $httpClient,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AiProviderPluginManager $aiProviderManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly AmazeeClient $apiClient,
    protected readonly StateInterface $state,
    protected readonly LoggerInterface $logger,
    protected readonly ProgressReporterInterface $progressReporter = new NullProgressReporter(),
  ) {}

  /**
   * {@inheritdoc}
   */
  public function provision(): TrialAccountProvisioningResult {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $keyStorage */
    $keyStorage = $this->entityTypeManager->getStorage('key');

    /** @var \Drupal\key\Entity\Key|null $key */
    $key = $keyStorage->load(AmazeeioAiConfigForm::API_KEY_NAME);

    if ($key && $key->getKeyValue() !== '') {
      $this->logger->info('amazee.ai trial account already provisioned.');
      $this->progressReporter->info('amazee.ai trial account already provisioned, skipping.');
      return TrialAccountProvisioningResult::AlreadyProvisioned;
    }

    $data = $this->fetchTrialAccountData();

    $trialAccessKey = $data->key ?? NULL;
    /** @var string|null $trialAccessToken */
    $trialAccessToken = $data->token->access_token ?? NULL;

    if (!$trialAccessKey) {
      throw new TrialAccountProvisioningException('Could not extract trial account credentials.');
    }

    $this->apiClient->setToken($trialAccessToken);
    $this->apiClient->setHost(AmazeeClient::AMAZEE_API_HOST);

    $config = $this->configFactory->getEditable('ai_provider_amazeeio.settings');
    $config->set('host', $trialAccessKey->litellm_api_url)
      ->set('postgres_host', $trialAccessKey->database_host)
      ->set('postgres_port', $trialAccessKey->database_port ?? AmazeeioAiConfigForm::POSTGRES_PORT_DEFAULT)
      ->set('postgres_default_database', $trialAccessKey->database_name)
      ->set('postgres_username', $trialAccessKey->database_username)
      ->save();

    // @todo Make sure that Key creation is aligned with fixes
    //   introduced in https://www.drupal.org/i/3566091.
    /** @var \Drupal\key\Entity\Key $key */
    $key = $keyStorage->load(AmazeeioAiConfigForm::API_KEY_NAME) ??
      $keyStorage->create([
        'id' => AmazeeioAiConfigForm::API_KEY_NAME,
        'label' => 'amazee.ai AI API Key',
        'description' => 'Anonymous trial credentials provisioned by the amazee.ai provider.',
      ]);

    $key
      ->set('key_provider', 'config')
      ->set('key_provider_settings', ['key_value' => $trialAccessKey->litellm_token])
      ->set('key_input', 'text_field')
      ->save();

    // @todo Make sure that Key creation is aligned with fixes
    //   introduced in https://www.drupal.org/i/3566091.
    /** @var \Drupal\key\Entity\Key $databaseKey */
    $databaseKey = $keyStorage->load(AmazeeioAiConfigForm::VDB_PASSWORD_NAME) ??
      $keyStorage->create([
        'id' => AmazeeioAiConfigForm::VDB_PASSWORD_NAME,
        'label' => 'amazee.ai AI Database Key',
        'description' => 'Anonymous trial credentials provisioned by the amazee.ai provider.',
      ]);

    $databaseKey
      ->set('key_provider', 'config')
      ->set('key_provider_settings', ['key_value' => $trialAccessKey->database_password])
      ->set('key_input', 'text_field')
      ->save();

    /** @var \Drupal\ai_provider_amazeeio\Plugin\AiProvider\AmazeeioAiProvider $provider */
    $provider = $this->aiProviderManager->createInstance(AmazeeioAiProvider::PROVIDER_ID);

    if (!$this->moduleHandler->moduleExists('ai_provider_amazeeio_test')) {
      $provider->postSetup();
    }

    if (!$this->apiClient->authorized()) {
      throw new TrialAccountProvisioningException('Unable to authorize with amazee.ai using the provisioned trial credentials.');
    }

    // Track this site as using an anonymous trial account.
    $this->state->set('ai_provider_amazeeio.trial_account', TRUE);

    $this->logger->info('Successfully provisioned an anonymous amazee.ai trial account.');
    $this->progressReporter->info('Successfully provisioned an anonymous amazee.ai trial account.');

    return TrialAccountProvisioningResult::Provisioned;
  }

  /**
   * Fetches trial account credentials from the amazee.ai API.
   *
   * @return object
   *   The decoded response data.
   *
   * @throws \Drupal\ai_provider_amazeeio\TrialAccess\TrialAccountProvisioningException
   */
  private function fetchTrialAccountData(): object {
    $trialAccessUrl = AmazeeClient::AMAZEE_API_HOST . '/auth/generate-trial-access';

    $options = [
      'timeout' => 30,
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Referer' => 'drupal-install',
      ],
    ];

    $this->progressReporter->start('Provisioning amazee.ai trial account...');

    $options['progress'] = function () : void {
      $this->progressReporter->advance();
    };

    try {
      $response = $this->httpClient
        ->requestAsync('POST', $trialAccessUrl, $options)
        ->wait();

      if ($response->getStatusCode() !== 200) {
        throw new TrialAccountProvisioningException('Failed to provision trial account. Status code: ' . $response->getStatusCode());
      }

      $raw = (string) $response->getBody();

      try {
        $data = json_decode($raw, flags: JSON_THROW_ON_ERROR);
      }
      catch (\JsonException) {
        throw new TrialAccountProvisioningException(sprintf(
          'Malformed response from the server, expected JSON, got: %s',
          $raw
        ));
      }

      if (!isset($data->key, $data->token)) {
        throw new TrialAccountProvisioningException(sprintf(
          'Unexpected payload returned by the server, expected "key" and "token", got: %s',
          print_r($data, TRUE)
        ));
      }

      $this->progressReporter->finish('Trial credentials received.');

      return $data;
    }
    catch (\Exception $e) {
      $this->progressReporter->finish();
      $this->progressReporter->error('Failed: ' . $e->getMessage());

      if ($e instanceof TrialAccountProvisioningException) {
        throw $e;
      }
      throw new TrialAccountProvisioningException($e->getMessage(), (int) $e->getCode(), $e);
    }
  }

}
