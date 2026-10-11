<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\custom_field\Plugin\CustomFieldFeedsTypeBase;

/**
 * Base class for CustomField feeds type plugins.
 */
class BaseTarget extends CustomFieldFeedsTypeBase {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): mixed {
    return is_string($value) ? trim($value) : $value;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(int $delta, array $configuration): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(array $configuration): array {
    return [];
  }

}
