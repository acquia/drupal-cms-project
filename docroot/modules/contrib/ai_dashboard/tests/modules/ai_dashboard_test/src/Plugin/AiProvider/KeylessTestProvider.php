<?php

namespace Drupal\ai_dashboard_test\Plugin\AiProvider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;

/**
 * Test AI provider that does not require an API key.
 *
 * Mimics a local provider such as Ollama, which is configured through a host
 * name rather than an API key, and is considered "usable" as soon as that
 * host name is set.
 */
#[AiProvider(
  id: 'ai_dashboard_test_keyless',
  label: new TranslatableMarkup('AI Dashboard Test (Keyless)'),
)]
class KeylessTestProvider extends AiProviderClientBase {

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    return (bool) $this->getConfig()->get('host_name');
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
