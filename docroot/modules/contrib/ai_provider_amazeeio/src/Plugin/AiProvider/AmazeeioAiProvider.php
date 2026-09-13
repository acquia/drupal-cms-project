<?php

namespace Drupal\ai_provider_amazeeio\Plugin\AiProvider;

use Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Enum\AiProviderCapability;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextInterface;
use Drupal\ai\OperationType\TranslateText\TranslateTextOutput;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'amazee.ai AI' provider.
 */
#[AiProvider(
  id: 'amazeeio',
  label: new TranslatableMarkup('amazee.ai AI'),
)]
class AmazeeioAiProvider extends OpenAiBasedProviderClientBase implements TranslateTextInterface {

  /**
   * Default provider ID.
   */
  const PROVIDER_ID = 'amazeeio';

  /**
   * The AmazeeAI API client.
   *
   * @var \Drupal\ai_provider_amazeeio\AmazeeIoApi\AmazeeClient|null
   */
  protected AmazeeClient|null $amazeeClient = NULL;

  /**
   * The state service.
   */
  protected StateInterface $state;

  /**
   * The HTTP client factory.
   */
  protected ClientFactory $httpClientFactory;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->state = $container->get('state');
    $plugin->logger = $container->get('logger.channel.ai_provider_amazeeio');
    $plugin->httpClientFactory = $container->get('http_client_factory');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  protected function loadClient(): void {
    if ($this->amazeeClient === NULL) {
      // Keep the raw Guzzle client for AmazeeClient, which stamps the header
      // itself. For the OpenAI SDK the header rides as a Guzzle default
      // option on a real GuzzleHttp\Client: the SDK can only make streamed
      // requests (forced under BigPipe's Fibers) against an actual Guzzle
      // client, so a PSR-18 decorator would break streaming (#3586239).
      $guzzle = $this->httpClient;
      $this->setHttpClient($this->httpClientFactory->fromOptions([
        'headers' => [AmazeeClient::CLIENT_HEADER => AmazeeClient::clientHeaderValue()],
      ]));
      $this->amazeeClient = new AmazeeClient(
        $guzzle,
        $this->logger,
        $this->configFactory,
      );
      $host = $this->amazeeClient->getHost();
      $this->setEndpoint($host);
      try {
        $this->amazeeClient->setToken($this->loadApiKey());
      }
      catch (AiSetupFailureException $e) {
        throw new AiSetupFailureException('Failed to initialize amazee.ai client: ' . $e->getMessage(), $e->getCode(), $e);
      }
    }
    parent::loadClient();
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(): ?string {
    return $this->configFactory->get('ai_provider_amazeeio.settings')->get('host');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // Build cache key based on operation type and capabilities.
    $cache_key_parts = ['amazeeai', 'models', $operation_type ?? 'all'];

    if (!empty($capabilities)) {
      $capability_names = array_map(fn($cap) => $cap->value ?? $cap, $capabilities);
      sort($capability_names);
      $cache_key_parts[] = implode('_', $capability_names);
    }

    $cache_key = implode(':', $cache_key_parts);

    // Try to get from cache.
    $cached = $this->cacheBackend->get($cache_key);

    if ($cached !== FALSE) {
      return $cached->data;
    }

    $this->loadClient();

    $models = $this->getModels($operation_type ?? '', $capabilities);

    // Cache for 24 hours (86400 seconds).
    $this->cacheBackend->set($cache_key, $models, time() + 86400);

    return $models;
  }

  /**
   * Retrieves and filters a list of models from the AmazeeAI client.
   *
   * Filters out deprecated or unsupported models based on the operation type.
   * The AmazeeAI API does not natively filter these models.
   *
   * @param string $operation_type
   *   The bundle to filter models by.
   * @param array $capabilities
   *   The capabilities to filter models by.
   *
   * @return array
   *   A filtered list of public models.
   */
  public function getModels(string $operation_type, array $capabilities): array {
    $models = [];
    foreach ($this->amazeeClient->models() as $model) {
      switch ($operation_type) {
        case 'text_to_image':
          if ($model->supportsImageGeneration) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'text_to_speech':
          if ($model->supportsAudioOutput) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'audio_to_audio':
          if ($model->supportsAudioInput && $model->supportsAudioOutput) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'speech_to_text':
          if ($model->supportsAudioTranscription) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'moderation':
          if ($model->supportsModeration) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'embeddings':
          if ($model->supportsEmbeddings) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'chat':
        case 'translate_text':
          if ($model->supportsChat) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'chat_with_image_vision':
          if ($model->supportsVision) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'chat_with_structured_response':
        case 'chat_with_complex_json':
          if ($model->supportsResponseSchema) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'chat_with_tools':
          if ($model->supportsFunctionCalling || $model->supportsToolChoice) {
            $models[$model->name] = $model->name;
          }
          break;

        default:
          break;
      }
    }
    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    $this->loadClient();
    $model_info = $this->amazeeClient->models()[$model_id] ?? NULL;

    if (!$model_info) {
      return $generalConfig;
    }

    foreach (array_keys($generalConfig) as $name) {
      if (!in_array($name, $model_info->supportedOpenAiParams)) {
        unset($generalConfig[$name]);
      }
    }

    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCapabilities(): array {
    return [
      AiProviderCapability::StreamChatOutput,
      AiProviderCapability::ChatFiberSupport,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (!parent::isUsable($operation_type, $capabilities)) {
      return FALSE;
    }
    if ($operation_type === NULL) {
      return TRUE;
    }
    // The endpoint decides which models are available, so only claim support
    // for an operation type when at least one model can actually serve it.
    try {
      return (bool) $this->getConfiguredModels($operation_type, $capabilities);
    }
    catch (\Exception) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
      'chat_with_complex_json',
      'chat_with_image_vision',
      'chat_with_structured_response',
      'chat_with_tools',
      'embeddings',
      'text_to_image',
      'translate_text',
      'speech_to_text',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    try {
      $this->loadClient();
      $models = $this->amazeeClient->models();
    }
    catch (\Exception) {
      $models = [];
    }

    $setup = [
      'key_config_name' => 'api_key',
      'default_models' => [],
    ];

    if (isset($models['chat']) && ($models['chat']->supportsChat)) {
      $setup['default_models']['chat'] = 'chat';
      $setup['default_models']['chat_with_tools'] = 'chat';
      $setup['default_models']['chat_with_structured_response'] = 'chat';
      $setup['default_models']['chat_with_complex_json'] = 'chat';
      $setup['default_models']['translate_text'] = 'chat';

      if ($models['chat']->supportsVision) {
        $setup['default_models']['chat_with_image_vision'] = 'chat';
      }
    }

    if (isset($models['embeddings']) && $models['embeddings']->supportsEmbeddings) {
      $setup['default_models']['embeddings'] = 'embeddings';
    }

    foreach ($models as $model) {
      if ($model->supportsImageGeneration) {
        $setup['default_models']['text_to_image'] = $model->name;
        break;
      }
    }

    return $setup;
  }

  /**
   * {@inheritdoc}
   */
  public function handleApiException(\Throwable $e): void {
    if (str_contains($e->getMessage(), 'Budget has been exceeded!')) {
      $message = 'Your budget has been exceeded!';

      if ($this->state->get('ai_provider_amazeeio.trial_account')) {
        $url = Url::fromRoute('ai_provider_amazeeio.settings_form')->toString();
        $message = str_replace(':url', $url, 'Your anonymous free trial budget has been exceeded! To continue using amazee.ai, please upgrade to a free account by going to the amazee.ai AI settings at :url and validating your email address.');
      }

      throw new AiQuotaException($message . ' ' . $e->getMessage());
    }

    if (str_contains($e->getMessage(), 'Request rate limit has been exceeded')) {
      throw new AiRateLimitException($e->getMessage());
    }

    // Delegate to the base handler so its rate limit and quota detection
    // ("Too Many Requests", "Request too large", ...) still applies.
    if ($e instanceof \Exception) {
      parent::handleApiException($e);
    }

    throw $e;
  }

  /**
   * {@inheritdoc}
   */
  public function translateText(TranslateTextInput $input, string $model_id, array $options = []): TranslateTextOutput {
    $source_language = $input->getSourceLanguage() ?: 'source language';
    $target_language = $input->getTargetLanguage();
    $prompt = sprintf(
      'Translate the following text from %s to %s. Return only the translated text without any extra commentary. Text: %s',
      $source_language,
      $target_language,
      $input->getText()
    );

    $messages = new ChatInput([
      new ChatMessage('user', $prompt),
    ]);
    $messages->setSystemPrompt('You are a helpful translator.');

    $chat_output = $this->chat($messages, $model_id, $options);

    return new TranslateTextOutput(
      $chat_output->getNormalized()->getText(),
      $chat_output->getRawOutput(),
      [],
    );
  }

}
