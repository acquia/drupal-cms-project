<?php

namespace Drupal\ai_dashboard_test\Plugin\AiProvider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;

/**
 * Test AI provider that requires an API key.
 *
 * Used to test the "Add Provider" form behaviour for providers that need an
 * API key to be set up, such as most cloud providers.
 */
#[AiProvider(
  id: 'ai_dashboard_test_keyed',
  label: new TranslatableMarkup('AI Dashboard Test (Keyed)'),
)]
class KeyedTestProvider extends AiProviderClientBase {

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    return [
      'key_config_name' => 'api_key',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    return (bool) $this->getConfig()->get('api_key');
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

}
