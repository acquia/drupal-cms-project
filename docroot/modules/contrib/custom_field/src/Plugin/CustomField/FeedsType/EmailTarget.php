<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FeedsType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldFeedsType;

/**
 * Plugin implementation of the 'email' feeds type.
 */
#[CustomFieldFeedsType(
  id: 'email',
  label: new TranslatableMarkup('Email'),
  mark_unique: TRUE,
)]
class EmailTarget extends BaseTarget {

  /**
   * {@inheritdoc}
   */
  public function prepareValue(mixed $value, array $configuration, string $langcode): ?string {
    $value = is_string($value) ? trim($value) : $value;
    if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
      $value = NULL;
    }
    if (!empty($value) && $configuration['defuse']) {
      return 'FEEDS_TEST_' . $value;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'defuse' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(int $delta, array $configuration): array {
    $form['defuse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Defuse email addresses'),
      '#default_value' => $configuration['defuse'],
      '#description' => $this->t('This prepends FEEDS_TEST_ to all imported email addresses to ensure they cannot be used as recipients.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(array $configuration): array {
    $summary[] = $configuration['defuse'] ?
      $this->t('Addresses <strong>will</strong> be defused.') :
      $this->t('Addresses will not be defused.');

    return $summary;
  }

}
