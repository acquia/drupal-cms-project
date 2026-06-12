<?php

namespace Drupal\ai_provider_amazeeio\Form;

use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use Drupal\ai_provider_amazeeio\AmazeeIoApi\ClientInterface;
use Drupal\ai_provider_amazeeio\Plugin\AiProvider\AmazeeioAiProvider;
use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Configure amazee.ai AI API access Form.
 */
class AmazeeioAiConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  public const string CONFIG_NAME = 'ai_provider_amazeeio.settings';

  /**
   * The known key name for the amazee.ai API key.
   */
  public const string API_KEY_NAME = 'amazeeio_ai';

  /**
   * The known key name for the management token.
   */
  public const string MANAGEMENT_TOKEN_NAME = 'amazeeio_ai_management_token';

  /**
   * The known key name for the amazee.ai database password.
   */
  public const string VDB_PASSWORD_NAME = 'amazeeio_ai_database';

  /**
   * The default Postgres port.
   */
  public const int POSTGRES_PORT_DEFAULT = 5432;

  /**
   * Not connected to amazee.ai.
   */
  public const string STATE_DISCONNECTED = 'disconnected';

  /**
   * Email address has been entered, waiting for  verification code.
   */
  public const string STATE_VERIFICATION = 'validation';

  /**
   * Email verification successful, region selection.
   */
  public const string STATE_VERIFIED = 'validated';

  /**
   * Region has been selected, keys are generated, everything is set up.
   */
  public const string STATE_CONNECTED = 'connected';

  /**
   * Show a confirmation step before disconnecting.
   */
  public const string STATE_CONFIRM_DISCONNECT = 'confirm_disconnect';

  /**
   * Constructs a new AmazeeioAiConfigForm object.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected AiProviderPluginManager $aiProviderManager,
    protected KeyRepositoryInterface $keyRepository,
    protected ClientInterface $amazeeClient,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected Client $httpClient,
    protected CacheBackendInterface $cacheDefault,
    RequestStack $requestStack,
    protected StateInterface $state,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
    $this->requestStack = $requestStack;
    $this->amazeeClient->setHost(AmazeeClient::AMAZEE_API_HOST);
    $this->amazeeClient->setToken($this->getTempStore()->get('access_token') ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('ai.provider'),
      $container->get('key.repository'),
      $container->get('ai_provider_amazeeio.api_client'),
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('request_stack'),
      $container->get('state')
    );
  }

  /**
   * A helper function to get a key value from the key repository.
   *
   * @param string $key_name
   *   The name of the key to retrieve.
   *
   * @return string|null
   *   The key value, or NULL if the key is not found.
   */
  private function getKeyValue(string $key_name): ?string {
    return $this->keyRepository->getKey($key_name)?->getKeyValue();
  }

  /**
   * Check the health status of the LLM host.
   *
   * @return array
   *   Array with 'status' (bool), 'message' (string),
   *   and 'checked_at' (string).
   */
  private function checkLlmHealth(): array {
    $config = $this->configFactory->get(static::CONFIG_NAME);
    $host = $config->get('host');
    $apiKey = $this->getKeyValue(static::API_KEY_NAME);

    if (empty($host) || empty($apiKey)) {
      return [
        'status' => FALSE,
        'message' => $this->t('LLM host or API key not configured'),
        'checked_at' => '',
        'litellm_version' => '',
      ];
    }

    $cacheId = 'amazeeio_llm_health_status';
    $cache = $this->cacheDefault->get($cacheId);

    if ($cache !== FALSE && !$this->isForceRefresh()) {
      return $cache->data;
    }

    try {
      $response = $this->httpClient->get($host . '/health/liveliness', [
        'headers' => [
          'Authorization' => "Bearer $apiKey",
          'Content-Type' => 'application/json',
        ],
        'timeout' => 5,
      ]);

      $statusCode = $response->getStatusCode();
      $body = (string) $response->getBody();
      $isHealthy = $statusCode === 200 && str_contains($body, "I'm alive!");

      // Try to get LiteLLM version.
      $liteLlmVersion = '';
      try {
        $openapiResponse = $this->httpClient->get($host . '/openapi.json', [
          'headers' => [
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
          ],
          'timeout' => 3,
        ]);
        $openapiData = json_decode((string) $openapiResponse->getBody(), TRUE);
        $liteLlmVersion = $openapiData['info']['version'] ?? '';
      }
      catch (\Exception) {
        // Silently fail if version cannot be fetched.
      }

      if (!$isHealthy) {
        \Drupal::logger('ai_provider_amazeeio')->error('LLM health check failed for @host. Status: @status, Body: @body', [
          '@host' => $host,
          '@status' => $statusCode,
          '@body' => $body,
        ]);
      }

      $result = [
        'status' => $isHealthy,
        'message' => $isHealthy ? $this->t('Online') : $this->t('Offline (@url)', ['@url' => $host . '/health/liveliness']),
        'checked_at' => date('Y-m-d H:i:s'),
        'litellm_version' => $liteLlmVersion,
      ];
    }
    catch (\Exception $e) {
      \Drupal::logger('ai_provider_amazeeio')->error('LLM health check failed for @host: @error', [
        '@host' => $host,
        '@error' => $e->getMessage(),
      ]);
      $result = [
        'status' => FALSE,
        'message' => $this->t('Offline (@url)', ['@url' => $host . '/health/liveliness']),
        'checked_at' => date('Y-m-d H:i:s'),
        'litellm_version' => '',
      ];
    }

    $this->cacheDefault->set($cacheId, $result, time() + 10);

    return $result;
  }

  /**
   * Check if a health refresh was requested.
   *
   * @return bool
   *   TRUE if refresh was requested.
   */
  private function isForceRefresh(): bool {
    return (bool) $this->requestStack->getCurrentRequest()->query->get('health_refresh', FALSE);
  }

  /**
   * Fetch key information from the LLM host.
   *
   * @return array
   *   Array of key info or error message.
   */
  private function getLlmKeyInfo(): array {
    $config = $this->configFactory->get(static::CONFIG_NAME);
    $host = $config->get('host');
    $apiKey = $this->getKeyValue(static::API_KEY_NAME);

    if (empty($host) || empty($apiKey)) {
      return ['error' => $this->t('LLM host or API key not configured')];
    }

    $cacheId = 'amazeeio_llm_key_info';
    $cache = $this->cacheDefault->get($cacheId);

    if ($cache !== FALSE && !$this->isForceRefresh()) {
      return $cache->data;
    }

    try {
      $response = $this->httpClient->get($host . '/key/info', [
        'headers' => [
          'Authorization' => "Bearer $apiKey",
          'Content-Type' => 'application/json',
        ],
      ]);

      $data = json_decode((string) $response->getBody(), TRUE);

      if (empty($data['info']) || !\is_array($data['info'])) {
        $result = ['error' => $this->t('No key info returned from LLM host')];
        $this->cacheDefault->set($cacheId, $result, time() + 300);
        return $result;
      }

      $result = [
        'key_alias' => $data['info']['key_alias'] ?? '',
        'key_name' => $data['info']['key_name'] ?? '',
      ];

      $this->cacheDefault->set($cacheId, $result, time() + 300);
      return $result;
    }
    catch (\Exception $e) {
      $result = ['error' => $this->t('Failed to fetch key info: @error', ['@error' => $e->getMessage()])];
      $this->cacheDefault->set($cacheId, $result, time() + 60);
      return $result;
    }
  }

  /**
   * Fetch available models from the LLM host.
   *
   * @return array
   *   Array of model data with id, token cost, and description.
   */
  private function getLlmHostModels(): array {
    $config = $this->configFactory->get(static::CONFIG_NAME);
    $host = $config->get('host');
    $apiKey = $this->getKeyValue(static::API_KEY_NAME);

    if (empty($host) || empty($apiKey)) {
      return ['error' => $this->t('LLM host or API key not configured')];
    }

    $cacheId = 'amazeeio_llm_models';
    $cache = $this->cacheDefault->get($cacheId);

    if ($cache !== FALSE) {
      return $cache->data;
    }

    try {
      $response = $this->httpClient->get($host . '/models', [
        'headers' => [
          'Authorization' => "Bearer $apiKey",
          'Content-Type' => 'application/json',
        ],
      ]);

      $data = json_decode((string) $response->getBody(), TRUE);

      if (empty($data['data']) || !\is_array($data['data'])) {
        $result = ['error' => $this->t('No models returned from LLM host')];
        $this->cacheDefault->set($cacheId, $result, time() + 300);
        return $result;
      }

      $models = [];
      foreach ($data['data'] as $model) {
        $models[] = [
          'id' => $model['id'] ?? '',
          'token_cost' => $this->formatTokenCost($model['pricing'] ?? []),
          'description' => $model['description'] ?? '',
        ];
      }

      $this->cacheDefault->set($cacheId, $models, time() + 300);
      return $models;
    }
    catch (\Exception $e) {
      $result = ['error' => $this->t('Failed to fetch models: @error', ['@error' => $e->getMessage()])];
      $this->cacheDefault->set($cacheId, $result, time() + 60);
      return $result;
    }
  }

  /**
   * Format token cost from pricing data.
   *
   * @param array $pricing
   *   The pricing array from the model data.
   *
   * @return string
   *   Formatted token cost string.
   */
  private function formatTokenCost(array $pricing): string {
    if (empty($pricing)) {
      return '-';
    }

    $parts = [];
    if (isset($pricing['prompt'])) {
      $parts[] = 'Prompt: ' . $pricing['prompt'];
    }
    if (isset($pricing['completion'])) {
      $parts[] = 'Completion: ' . $pricing['completion'];
    }

    return !empty($parts) ? implode(', ', $parts) : '-';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'amazeeio_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [static::CONFIG_NAME];
  }

  /**
   * Determine the current form state.
   *
   * Based on the current `$form_state` as well as the authorization
   * status.
   */
  public function currentState(FormStateInterface $form_state): string {
    if ($state = $form_state->get('state')) {
      return $state;
    }

    if ($this->amazeeClient->authorized()) {
      return static::STATE_CONNECTED;
    }

    // Check if we have LLM key and VDB already setup.
    /** @var \Drupal\Core\Entity\EntityStorageInterface $key_storage */
    $key_storage = $this->entityTypeManager->getStorage('key');
    $ai_key = $key_storage->load(static::API_KEY_NAME);
    if ($ai_key && $ai_key->getKeyValue() !== '') {
      $vdb_key = $key_storage->load(static::VDB_PASSWORD_NAME);
      if ($vdb_key && $vdb_key->getKeyValue() !== '') {
        return static::STATE_CONNECTED;
      }
    }

    return static::STATE_DISCONNECTED;
  }

  /**
   * Determine if the module is in "test mode".
   */
  protected function testMode(): bool {
    return $this->moduleHandler->moduleExists('ai_provider_amazeeio_test');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Attach the AI global library for consistent styling.
    $form['#attached']['library'][] = 'ai/ai_global';

    // Attach the module's CSS for table styling.
    $form['#attached']['library'][] = 'ai_provider_amazeeio/ai_provider_amazeeio';

    // Get the configuration with overrides.
    $config = $this->configFactory->get(static::CONFIG_NAME);

    $this->amazeeClient->setToken($this->getTempStore()->get('access_token') ?? '');

    $buttonAjax = [
      'callback' => '::ajaxUpdate',
      'event' => 'click',
      'wrapper' => 'amazee-ai-config-form',
      'progress' => [
        'type' => 'throbber',
      ],
    ];

    $state = $this->currentState($form_state);

    $module_path = $this->moduleHandler->getModule('ai_provider_amazeeio')->getPath();
    $logo_path = '/' . $module_path . '/logo.png';
    $form['image'] = [
      '#markup' => '<p><img src="' . $logo_path . '" alt="amazee.ai" width="250"/></p>',
      '#access' => $state !== static::STATE_CONNECTED,
    ];
    $ajax = [
      '#prefix' => '<div id="amazee-ai-config-form">',
      '#suffix' => '</div>',
    ];
    $support_note = '<p><small>' . $this->t('Need support? Contact the amazee.ai team via email ai.support[at]amazee.io') . '</small></p>';

    if ($state === static::STATE_DISCONNECTED) {
      $ajax['markup'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t("Let's get you started! Enter your email address and we'll send you a code to sign in to <strong>amazee.ai</strong>."),
      ];
      $ajax['email'] = [
        // When in 'test mode' we use a simple text field, so the BrowserTest
        // is actually able to enter an invalid email address.
        '#type' => $this->testMode() ? 'textfield' : 'email',
        '#title' => $this->t('Email'),
        '#description' => $this->t('By entering your email address, you agree to amazee.ai\'s <a href="https://amazee.ai/terms-and-conditions">Terms of Service.</a>'),
      ];
      $ajax['submit_email'] = [
        '#type' => 'submit',
        '#value' => $this->t('Sign in'),
        '#ajax' => $buttonAjax,
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
      $ajax['support_note'] = [
        '#markup' => $support_note,
      ];
    }

    if ($state === static::STATE_VERIFICATION) {
      $ajax['markup'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Check your inbox. Enter the verification code we just sent to your email.'),
      ];
      $ajax['code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Code'),
      ];
      $ajax['submit_code'] = [
        '#type' => 'submit',
        '#value' => $this->t('Validate'),
        '#ajax' => $buttonAjax,
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
      $ajax['support_note'] = [
        '#markup' => $support_note,
      ];
    }

    if ($state === static::STATE_VERIFIED) {
      try {
        $regions = $this->amazeeClient->getRegions();
      }
      catch (ClientException $e) {
        $response = $e->getResponse();
        $response_body = json_decode((string) $response->getBody(), TRUE);
        $error_message = $response_body['detail'] ?? $e->getMessage();
        $this->messenger()->addError($this->t('An error occurred while retrieving the available regions. @error. Please consult the Drupal error log for full details.', ['@error' => rtrim($error_message, '.')]));
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('An error occurred while retrieving the available regions. @error. Please consult the Drupal error log for full details.', ['@error' => rtrim($e->getMessage(), '.')]));
      }

      // Check if we already have a key for this host.
      $key_name = static::generatePrivateKeyName();
      $api_keys = array_filter(
        $this->amazeeClient->getPrivateApiKeys(),
        fn($key) => $key->name === $key_name
      );
      $api_key = reset($api_keys);
      $all_api_keys = $this->amazeeClient->getPrivateApiKeys();

      if ($api_key) {
        $ajax['markup'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('We found an existing key for this host <em>@host</em>.', ['@host' => static::generatePrivateKeyName()]),
        ];
      }

      $email = $this->getTempStore()->get('email');
      if ($email && !empty($all_api_keys)) {
        $note_markup = $this->t('Here are all of the keys found for your account <em>@email</em>:', ['@email' => $email]);

        $ajax['keys_note'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $note_markup,
        ];

        $header = [
          'name' => $this->t('Name'),
          'region' => $this->t('Region'),
          'url' => $this->t('URL'),
        ];

        $options = [];
        foreach ($all_api_keys as $index => $key) {
          $key_region = !empty($key->region_label) ? $key->region_label . ' (' . $key->region . ')' : $key->region;
          $key_url = $key->litellm_api_url ?? '-';

          $options[$index] = [
            'name' => $key->name,
            'region' => $key_region,
            'url' => $key_url,
          ];
        }

        $ajax['selected_key'] = [
          '#type' => 'tableselect',
          '#header' => $header,
          '#options' => $options,
          '#multiple' => FALSE,
          '#empty' => $this->t('No keys found'),
          '#attributes' => ['class' => ['ai-keys-table']],
        ];

        $ajax['use_selected_key'] = [
          '#type' => 'submit',
          '#value' => $this->t('Use selected key'),
          '#name' => 'use_selected_key',
          '#access' => !empty($all_api_keys),
          '#attributes' => ['class' => ['button', 'button--primary']],
          '#submit' => ['::submitUseSelectedKey', '::submitForm'],
        ];
      }

      $ajax['markup_new'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Or create a new key:'),
      ];

      $ajax['key_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Key Name'),
        '#default_value' => static::generatePrivateKeyName(),
        '#title_display' => 'before',
      ];

      $ajax['region'] = [
        '#type' => 'select',
        '#title' => $this->t('Region'),
        '#options' => $regions ?? [],
        '#title_display' => 'before',
        '#access' => !empty($regions),
      ];
      $ajax['submit_region'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create new key'),
        '#name' => 'submit_region',
        '#access' => !empty($regions),
        '#attributes' => ['class' => ['button', 'button--primary']],
        '#submit' => ['::submitCreateNewKey', '::submitForm'],
      ];

      if (empty($regions)) {
        $ajax['regions_unavailable'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('No regions are currently available. Please try again, or if the problem persists, contact the amazee.ai team via email ai.support[at]amazee.io'),
          '#attributes' => ['class' => ['messages', 'messages--warning']],
        ];
        $ajax['retry_regions'] = [
          '#type' => 'submit',
          '#value' => $this->t('Retry'),
          '#name' => 'retry_regions',
          '#attributes' => ['class' => ['button']],
          '#submit' => ['::submitForm'],
        ];
      }
      $ajax['support_note'] = [
        '#markup' => $support_note,
      ];
    }

    if ($state === static::STATE_CONNECTED) {
      // Check if we're using a Trial Account.
      $trial_account = $this->state->get('ai_provider_amazeeio.trial_account');

      if ($trial_account) {
        $ajax['trial_account_message'] = [
          '#markup' => '<p class="ai-text-muted ai-description">' .
          $this->t('You are currently using a free anonymous trial account.') . ' ' .
          $this->t('This account has a very limited budget.') . ' ' .
          $this->t('You may want to disconnect and connect with a full user account.') .
          '</p>',
        ];
      }

      $health = $this->checkLlmHealth();
      $host = $config->get('host');

      // Fetch Team info.
      $team_info = NULL;
      $management_key_info = NULL;
      $management_token = $this->getKeyValue(static::MANAGEMENT_TOKEN_NAME);
      $llm_api_key = $this->getKeyValue(static::API_KEY_NAME);

      if (!$trial_account) {
        $cache_id = 'amazeeio_ai_dashboard_data_' . ($management_token ? hash('sha256', $management_token) : 'no_token');
        $cache = $this->cacheDefault->get($cache_id);

        if ($cache !== FALSE && !$this->isForceRefresh()) {
          $team_info = $cache->data['team'] ?? NULL;
          $management_key_info = $cache->data['key'] ?? NULL;
        }
        else {
          // If we don't have a valid session token, try the management token.
          $using_management_token = FALSE;
          if (!$this->amazeeClient->authorized() && $management_token) {
            $this->amazeeClient->setToken($management_token);
            $this->amazeeClient->setHost(AmazeeClient::AMAZEE_API_HOST);
            $using_management_token = TRUE;
          }

          if ($this->amazeeClient->authorized()) {
            $team_id = $this->amazeeClient->getTeamId();
            if ($team_id) {
              $team_data = $this->amazeeClient->getTeam((int) $team_id);
              if ($team_data) {
                $team_info = ['name' => $team_data->name ?? ''];
              }
            }

            if ($llm_api_key) {
              $api_key_data = $this->amazeeClient->getPrivateApiKey($llm_api_key);
              if ($api_key_data) {
                $management_key_info = ['name' => $api_key_data->name ?? ''];
              }
            }

            $this->cacheDefault->set($cache_id, [
              'team' => $team_info,
              'key' => $management_key_info,
            ], time() + 300);
          }

          // Restore original token if we swapped it.
          if ($using_management_token) {
            $this->amazeeClient->setToken($this->getTempStore()->get('access_token') ?? '');
          }
        }
      }

      $key_info = (empty($host) || !$this->getKeyValue(static::API_KEY_NAME)) ? [] : $this->getLlmKeyInfo();

      // Prepare dashboard elements that need to be within the form tree
      // (like submit buttons) to ensure their callbacks function correctly.
      $ajax['dashboard'] = [
        '#theme' => 'amazeeio_ai_dashboard',
        '#logo' => $logo_path,
        '#health' => $health,
        '#team' => $team_info,
        '#key_info' => $key_info,
        '#management_key_info' => $management_key_info,
        '#models' => (empty($host) || !$this->getKeyValue(static::API_KEY_NAME)) ? [] : $this->getLlmHostModels(),
        '#host' => $host,
        '#database' => $config->get('postgres_default_database'),
        '#key_name' => static::generatePrivateKeyName(),
        '#trial_account' => $this->state->get('ai_provider_amazeeio.trial_account'),
        'submit_disconnect' => [
          '#type' => 'submit',
          '#value' => $this->t('Disconnect'),
          '#attributes' => ['class' => ['button', 'button--danger']],
        ],
        'health_refresh' => [
          '#type' => 'submit',
          '#value' => $this->t('Check Health'),
          '#submit' => ['::submitHealthRefresh'],
          '#attributes' => ['class' => ['button', 'button--secondary']],
        ],
      ];
      $ajax['support_note'] = [
        '#markup' => $support_note,
      ];

      // Because the "models" are evaluated above, we handle the refresh logic
      // conditionally without needing to rewrite logic.
      if (isset($ajax['dashboard']['#models']['error'])) {
        $ajax['dashboard']['refresh'] = [
          '#type' => 'submit',
          '#value' => $this->t('Refresh Models'),
          '#submit' => ['::submitModelsRefresh'],
          '#attributes' => ['class' => ['button', 'button--secondary']],
        ];
      }
      elseif ($ajax['dashboard']['#models']) {
        // Sort models as previously done for neatness in table rendering.
        usort($ajax['dashboard']['#models'], fn($a, $b) => strcmp($a['id'], $b['id']));
      }
    }

    if ($state === static::STATE_CONFIRM_DISCONNECT) {
      $ajax['markup'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Are you sure you want to disconnect from <strong>amazee.ai</strong>?'),
      ];
      $ajax['submit_confirm_disconnect'] = [
        '#type' => 'submit',
        '#value' => $this->t('Disconnect'),
        '#attributes' => ['class' => ['button', 'button--danger']],
      ];
      $ajax['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('No! Go back!'),
        '#ajax' => $buttonAjax,
        '#attributes' => ['class' => ['button', 'button--secondary']],
      ];
    }

    $form['ajax'] = $ajax;

    return $form;
  }

  /**
   * Ajax callback to dynamically update the form.
   */
  public static function ajaxUpdate(array &$form, FormStateInterface $form_state): array {
    return $form['ajax'];
  }

  /**
   * Signup form validation.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $state = $this->currentState($form_state);

    if ($state === static::STATE_DISCONNECTED) {
      $email = $form_state->getValue('email');
      if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_state->set('email', $email);
        $this->amazeeClient->requestCode($email);
        $form_state->set('state', static::STATE_VERIFICATION);
      }
      else {
        $form_state->setErrorByName('email', $this->t('Invalid email address.'));
      }
    }

    if ($state === static::STATE_VERIFICATION) {
      $email = $form_state->get('email');
      $code = $form_state->getValue('code');
      $token = $this->amazeeClient->validateCode($email, $code);
      if ($token) {
        $this->getTempStore()->set('access_token', $token);
        $this->getTempStore()->set('email', $email);
        $form_state->set('state', static::STATE_VERIFIED);
      }
      else {
        $form_state->setErrorByName('code', $this->t('The provided code is incorrect or has expired.'));
      }
    }

    if ($state === static::STATE_VERIFIED) {
      $action = $form_state->get('action');
      $region = $form_state->getValue('region');
      $key_name = $form_state->getValue('key_name');
      $all_api_keys = $this->amazeeClient->getPrivateApiKeys();
      $element = $form_state->getTriggeringElement();
      $triggeringName = $element['#name'] ?? '';

      // Handle "Use selected key" action.
      if ($action === 'use_selected_key' || $triggeringName === 'use_selected_key') {
        $selected_key = $form_state->getValue('selected_key');
        if ($selected_key !== NULL && isset($all_api_keys[$selected_key])) {
          $form_state->set('selected_key_index', $selected_key);
          $form_state->set('selected_key_name', $all_api_keys[$selected_key]->name);
          return;
        }
        else {
          $form_state->setErrorByName('selected_key', $this->t('Please select a key from the table.'));
          $form_state->setRebuild();
          return;
        }
      }

      // Handle "Create new key" action (button click or Enter key in new key
      // fields).
      if ($action === 'create_new_key' || $triggeringName === 'key_name' || $triggeringName === 'region' || $triggeringName === 'submit_region') {
        if (empty($key_name)) {
          $form_state->setErrorByName('key_name', $this->t('Please enter a key name.'));
          $form_state->setRebuild();
          return;
        }

        try {
          $private_key = $this->amazeeClient->createPrivateAiKey(
            $region,
            $key_name,
            $this->amazeeClient->getTeamId()
          );
        }
        catch (ClientException $e) {
          $response = $e->getResponse();
          $response_body = json_decode((string) $response->getBody(), TRUE);
          $error_message = $response_body['detail'] ?? $e->getMessage();
          $form_state->setErrorByName('region', $this->t('An error occurred while generating the private key. @error. Please consult the Drupal error log for full details.', ['@error' => rtrim($error_message, '.')]));
          return;
        }

        if (!$private_key) {
          $form_state->setErrorByName('region', $this->t('An error occurred while generating the private key. Please consult the Drupal error log for full details.'));
        }
        else {
          $form_state->set('created_new_key', TRUE);
          return;
        }
      }

      // Default: require region selection.
      if (empty($region)) {
        $form_state->setErrorByName('region', $this->t('Please select a region.'));
      }
    }

    if ($state === static::STATE_CONNECTED) {
      $element = $form_state->getTriggeringElement();
      if ($element['#id'] === 'edit-submit-disconnect') {
        $form_state->set('state', static::STATE_CONFIRM_DISCONNECT);
      }
    }

    if ($state === static::STATE_CONFIRM_DISCONNECT) {
      $element = $form_state->getTriggeringElement();
      if ($element['#id'] === 'edit-submit-confirm-disconnect') {
        $form_state->set('state', static::STATE_CONFIRM_DISCONNECT);
        // Return now to not rebuild the form but submit it.
        return;
      }
      else {
        $form_state->set('state', static::STATE_CONNECTED);
      }
    }

    $form_state->setRebuild();
  }

  /**
   * Generate a key name for this installation.
   *
   * Assumes that each Drupal installation has a single API key.
   */
  public static function generatePrivateKeyName(): string {
    return \Drupal::request()->getHost();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->get('state') === static::STATE_VERIFIED) {
      $this->amazeeClient->setToken($this->getTempStore()->get('access_token') ?? '');
      $this->amazeeClient->setHost(AmazeeClient::AMAZEE_API_HOST);

      // Check if user selected an existing key.
      $selected_key_index = $form_state->get('selected_key_index');
      $selected_key_name = $form_state->get('selected_key_name');
      $created_new_key = $form_state->get('created_new_key');
      $all_api_keys = $this->amazeeClient->getPrivateApiKeys();

      if ($selected_key_name) {
        // Find key by name (more reliable than index).
        $api_keys = array_filter(
          $all_api_keys,
          fn($key) => $key->name === $selected_key_name
        );
        $api_key = reset($api_keys);
      }
      elseif ($selected_key_index !== NULL && isset($all_api_keys[$selected_key_index])) {
        $api_key = $all_api_keys[$selected_key_index];
      }
      elseif ($created_new_key) {
        // Use the newly created key (most recent one with matching name).
        $key_name = $form_state->getValue('key_name');
        $api_keys = array_filter(
          $all_api_keys,
          fn($key) => $key->name === $key_name
        );
        $api_key = end($api_keys);
      }
      else {
        // Fall back to finding key by hostname.
        $key_name = static::generatePrivateKeyName();
        $api_keys = array_filter(
          $all_api_keys,
          fn($key) => $key->name === $key_name
        );
        $api_key = reset($api_keys);
      }

      if ($api_key) {
        // Set the provider config, using a known key name to ease support
        // preconfigured environments.
        $this->config(static::CONFIG_NAME)
          ->set('host', $api_key->litellm_api_url)
          ->set('postgres_host', $api_key->database_host)
          ->set('postgres_port', $api_key->database_port ?? static::POSTGRES_PORT_DEFAULT)
          ->set('postgres_default_database', $api_key->database_name)
          ->set('postgres_username', $api_key->database_username)
          ->set('postgres_password', static::VDB_PASSWORD_NAME)
          ->set('api_key', static::API_KEY_NAME)
          ->save();

        // Load or create the amazee.ai key.
        /** @var \Drupal\Core\Entity\EntityStorageInterface $key_storage */
        $key_storage = $this->entityTypeManager->getStorage('key');
        /** @var \Drupal\key\Entity\Key $key */
        $key = $key_storage->load(static::API_KEY_NAME) ??
          $key_storage->create(
            [
              'id' => static::API_KEY_NAME,
              'label' => 'amazee.ai AI API Key',
              'description' => 'Automatically created by the amazee.ai AI provider.',
            ]
          );
        // Update the key config.
        $key
          ->set('key_provider', 'config')
          ->set('key_provider_settings', ['key_value' => $api_key->litellm_token])
          ->set('key_input', 'text_field')
          ->set('dependencies', [
            'module' => [
              'ai_provider_amazeeio',
            ],
          ])
          ->save();

        // Load or create the amazee.ai Postgres key.
        /** @var \Drupal\key\Entity\Key $database_key */
        $database_key = $key_storage->load(static::VDB_PASSWORD_NAME) ??
          $key_storage->create(
            [
              'id' => static::VDB_PASSWORD_NAME,
              'label' => 'amazee.ai AI Database Key',
              'description' => 'Automatically created by the amazee.ai AI provider.',
            ]
          );
        // Update the key config.
        $database_key
          ->set('key_provider', 'config')
          ->set('key_provider_settings', ['key_value' => $api_key->database_password])
          ->set('key_input', 'text_field')
          ->set('dependencies', [
            'module' => [
              'ai_provider_amazeeio',
            ],
          ])
          ->save();

        // Create management token.
        $management_key = $key_storage->load(static::MANAGEMENT_TOKEN_NAME);
        if (!$management_key || !$management_key->getKeyValue()) {
          $token_name = 'drupal_management_token';
          // Check if token already exists on server.
          $existing_tokens = $this->amazeeClient->listManagementTokens();
          foreach ($existing_tokens as $token) {
            if ($token->name === $token_name) {
              $this->amazeeClient->deleteManagementToken((int) $token->id);
            }
          }

          $management_token = $this->amazeeClient->createManagementToken($token_name);
          if ($management_token) {
            $management_key = $management_key ?? $key_storage->create(
              [
                'id' => static::MANAGEMENT_TOKEN_NAME,
                'label' => 'amazee.ai Management Token',
                'description' => 'Automatically created by the amazee.ai AI provider.',
              ]
            );
            $management_key
              ->set('key_provider', 'config')
              ->set('key_provider_settings', ['key_value' => $management_token])
              ->set('key_input', 'text_field')
              ->set('dependencies', [
                'module' => [
                  'ai_provider_amazeeio',
                ],
              ])
              ->save();
          }
        }

        // Set the default models where available.
        /** @var \Drupal\ai_provider_amazeeio\Plugin\AiProvider\AmazeeioAiProvider $provider */
        $provider = $this->aiProviderManager->createInstance(AmazeeioAiProvider::PROVIDER_ID);
        // Run post-setup when not in unit tests, since it connects to the
        // real LLM.
        if (!$this->testMode()) {
          $provider->postSetup();
        }

        // Fetch setup data.
        $setup_data = $provider->getSetupData();

        // Ensure the setup data is valid.
        if (!empty($setup_data) && is_array($setup_data) && !empty($setup_data['default_models']) && is_array($setup_data['default_models'])) {
          // Loop through and set default models for each operation type.
          foreach ($setup_data['default_models'] as $op_type => $model_id) {
            $this->aiProviderManager->defaultIfNone($op_type, AmazeeioAiProvider::PROVIDER_ID, $model_id);
          }
        }

        $this->messenger()->addStatus($this->t('This website has been connected to <strong>amazee.ai</strong>.'));
      }
    }

    if ($form_state->get('state') === static::STATE_CONFIRM_DISCONNECT) {
      $this->config(static::CONFIG_NAME)
        ->set('host', '')
        ->set('postgres_host', '')
        ->set('postgres_port', static::POSTGRES_PORT_DEFAULT)
        ->set('postgres_default_database', '')
        ->set('postgres_username', '')
        ->set('postgres_password', static::VDB_PASSWORD_NAME)
        ->set('api_key', static::API_KEY_NAME)
        ->save();

      $this->getTempStore()->delete('access_token');

      /** @var \Drupal\Core\Entity\EntityStorageInterface $key_storage */
      $key_storage = $this->entityTypeManager->getStorage('key');

      $apiKey = $key_storage->load(static::API_KEY_NAME);
      if ($apiKey) {
        $apiKey->delete();
      }

      $dbKey = $key_storage->load(static::VDB_PASSWORD_NAME);
      if ($dbKey) {
        $dbKey->delete();
      }

      $managementKey = $key_storage->load(static::MANAGEMENT_TOKEN_NAME);
      if ($managementKey) {
        $managementKey->delete();
      }

      // Ensure Drupal State for trial account is removed too.
      $this->state->delete('ai_provider_amazeeio.trial_account');

      $this->messenger()->addWarning($this->t('This website has been disconnected from <strong>amazee.ai</strong>.'));
    }
  }

  /**
   * Get the temp store.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   The temp store.
   */
  protected function getTempStore(): PrivateTempStore {
    return $this->tempStoreFactory->get('amazeeio_ai');
  }

  /**
   * Submit handler for the health refresh button.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitHealthRefresh(array &$form, FormStateInterface $form_state): void {
    $this->cacheDefault->delete('amazeeio_llm_health_status');
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the models refresh button.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitModelsRefresh(array &$form, FormStateInterface $form_state): void {
    $this->cacheDefault->delete('amazeeio_llm_models');
    $form_state->setRebuild();
  }

  /**
   * Submit handler for "Use selected key" button.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitUseSelectedKey(array &$form, FormStateInterface $form_state): void {
    $form_state->set('action', 'use_selected_key');
  }

  /**
   * Submit handler for "Create new key" button.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitCreateNewKey(array &$form, FormStateInterface $form_state): void {
    $form_state->set('action', 'create_new_key');
  }

}
