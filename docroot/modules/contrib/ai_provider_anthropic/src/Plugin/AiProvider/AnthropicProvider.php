<?php

namespace Drupal\ai_provider_anthropic\Plugin\AiProvider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\Traits\OperationType\ChatTrait;

/**
 * Plugin implementation of the 'anthropic' provider.
 */
#[AiProvider(
  id: 'anthropic',
  label: new TranslatableMarkup('Anthropic'),
)]
class AnthropicProvider extends OpenAiBasedProviderClientBase {

  use ChatTrait;

  /**
   * {@inheritdoc}
   */
  protected string $endpoint = 'https://api.anthropic.com/v1';

  /**
   * Run moderation call, before a normal call.
   *
   * @var bool
   */
  protected bool $moderation = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // Get the dynamic models from the API.
    $models = $this->fetchAvailableModels();

    // Apply capability filtering.
    if (in_array(AiModelCapability::ChatJsonOutput, $capabilities)) {
      return array_filter($models, function ($id) {
        // Keep models that support JSON output.
        // Updated to handle various model ID formats.
        return preg_match('/claude-3\.[57]|claude-3-[57]|claude-4|claude-(opus|sonnet)-4/i', $id);
      }, ARRAY_FILTER_USE_KEY);
    }

    if ($operation_type == 'chat') {
      return $models;
    }

    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    try {
      $models = $this->getConfiguredModels();
    }
    catch (\Exception $e) {
      // If we fail to get models dynamically, fall back to empty array.
      $models = [];
    }

    // Get the 4.5 models for complex tasks from the list.
    $default_complex_model = 'claude-opus-4-5-20251101';
    foreach ($models as $model_id => $model_name) {
      if (str_starts_with($model_id, 'claude-opus-4-5')) {
        // We found a 4.5 model, we can use it.
        $default_complex_model = $model_id;
        break;
      }
    }
    // Get the 4.5 sonnet model for general tasks from the list.
    $default_chat_model = 'claude-sonnet-4-5-20250929';
    foreach ($models as $model_id => $model_name) {
      if (str_starts_with($model_id, 'claude-sonnet-4-5')) {
        // We found a 4.5 sonnet model, we can use it.
        $default_chat_model = $model_id;
        break;
      }
    }

    $setup['key_config_name'] = 'api_key';
    if ($default_complex_model) {
      $setup['default_models']['chat_with_complex_json'] = $default_complex_model;
      $setup['default_models']['chat_with_tools'] = $default_complex_model;
      $setup['default_models']['chat_with_structured_response'] = $default_complex_model;
    }
    if ($default_chat_model) {
      $setup['default_models']['chat'] = $default_chat_model;
      $setup['default_models']['chat_with_image_vision'] = $default_chat_model;
    }
    return $setup;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    // If it's Claude 4.x or higher, we hide top_p as Anthropic API doesn't
    // allow both temperature and top_p to be specified together.
    if (preg_match('/^claude(?:-[a-z]+)*-(4(\.\d+)?|[5-9](\.\d+)?)(?:[.-]|$)/i', $model_id)) {
      unset($generalConfig['top_p']);
    }
    return $generalConfig;
  }

  /**
   * Enables moderation response, for all next coming responses.
   */
  public function enableModeration(): void {
    $this->moderation = TRUE;
  }

  /**
   * Disables moderation response, for all next coming responses.
   */
  public function disableModeration(): void {
    $this->moderation = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function loadClient(): void {
    // Set custom endpoint from host config if available.
    if (!empty($this->getConfig()->get('host'))) {
      $this->setEndpoint($this->getConfig()->get('host'));
    }

    try {
      parent::loadClient();
    }
    catch (AiSetupFailureException $e) {
      throw new AiSetupFailureException('Failed to initialize Anthropic client: ' . $e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * Fetches available models from Anthropic API.
   *
   * @return array
   *   Array of models keyed by model ID with display names as values.
   */
  protected function fetchAvailableModels(): array {
    // Check cache first.
    $cache_key = 'ai_provider_anthropic:models';
    $cached = $this->cacheBackend->get($cache_key);
    if ($cached && !empty($cached->data)) {
      return $cached->data;
    }

    try {
      // Ensure we have an API key.
      $api_key = $this->apiKey ?: $this->loadApiKey();

      // Make direct HTTP request to models endpoint.
      // Note: The models endpoint requires version 2023-06-01 specifically.
      $response = $this->httpClient->request('GET', 'https://api.anthropic.com/v1/models', [
        'headers' => [
          'x-api-key' => $api_key,
          // Models endpoint requires this specific version.
          'anthropic-version' => '2023-06-01',
          'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
      ]);

      $body = $response->getBody()->getContents();
      $data = Json::decode($body);

      $models = [];
      if (!empty($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $model) {
          if (!empty($model['id']) && !empty($model['display_name'])) {
            $models[$model['id']] = $model['display_name'];
          }
        }

        // Handle pagination if needed.
        if (!empty($data['has_more']) && !empty($data['last_id'])) {
          // For now, we'll limit to first page to avoid too many requests.
          // This could be expanded in the future.
          $this->loggerFactory->get('ai_provider_anthropic')
            ->notice('Additional models available via pagination, showing first page only.');
        }
      }

      // Cache for 24 hours (configurable via settings).
      $cache_ttl = $this->getConfig()->get('models_cache_ttl') ?? 86400;
      $this->cacheBackend->set($cache_key, $models, time() + $cache_ttl);

      // Log successful fetch.
      $this->loggerFactory->get('ai_provider_anthropic')
        ->info('Successfully fetched @count models from Anthropic API', ['@count' => count($models)]);

      // Log the model IDs for debugging.
      if (count($models) > 0) {
        $this->loggerFactory->get('ai_provider_anthropic')
          ->debug('Fetched models: @models', ['@models' => implode(', ', array_keys($models))]);
      }

      return $models;
    }
    catch (\Exception $e) {
      // Log error but don't throw - gracefully fall back.
      $this->loggerFactory->get('ai_provider_anthropic')
        ->warning('Failed to fetch Anthropic models dynamically: @error', ['@error' => $e->getMessage()]);

      // Return empty array - hardcoded models will still be available.
      return [];
    }
  }

  /**
   * Clears the cached models list.
   *
   * This can be called from an admin form or drush command.
   */
  public function clearModelsCache(): void {
    $this->cacheBackend->delete('ai_provider_anthropic:models');
    $this->loggerFactory->get('ai_provider_anthropic')
      ->info('Anthropic models cache cleared.');
  }

  /**
   * Handle API exceptions consistently.
   *
   * @param \Exception $e
   *   The exception to handle.
   *
   * @throws \Drupal\ai\Exception\AiRateLimitException
   * @throws \Drupal\ai\Exception\AiQuotaException
   * @throws \Exception
   */
  protected function handleApiException(\Exception $e): void {
    if (strpos($e->getMessage(), 'Your credit balance is too low to access the Anthropic API') !== FALSE) {
      throw new AiQuotaException($e->getMessage());
    }
    throw $e;
  }

}
