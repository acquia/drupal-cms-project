<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;

/**
 * Plugin implementation of the 'duration' formatter.
 */
#[FieldFormatter(
  id: 'duration',
  label: new TranslatableMarkup('Duration'),
  field_types: [
    'duration',
    'daterange',
  ],
)]
class DurationFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'duration_granularity' => 'days:hours:minutes',
      'duration_separator' => ', ',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    $settings = $this->getSettings() + static::defaultSettings();
    $element['duration_granularity'] = [
      '#type' => 'select',
      '#title' => $this->t('Granularity'),
      '#options' => [
        'days:hours:minutes' => $this->t('Days, hours, and minutes'),
        'days:hours' => $this->t('Days and hours'),
        'days' => $this->t('Days'),
      ],
      '#description' => $this->t('The granularity of the output (e.g., "days", "days:hours", "days:hours:minutes").'),
      '#default_value' => $settings['duration_granularity'],
    ];
    $element['duration_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator'),
      '#description' => $this->t('The string to separate duration parts.'),
      '#default_value' => $settings['duration_separator'],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): mixed {
    $settings = $this->getSettings() + static::defaultSettings();
    if (is_array($value) && !empty($value['duration'])) {
      $value = $value['duration'];
    }
    $granularity = $settings['duration_granularity'];
    $separator = $settings['duration_separator'];

    return $this->formatDuration((int) $value, $granularity, $separator);
  }

  /**
   * Helper function to format a duration.
   *
   * @param int $seconds
   *   The duration value in seconds.
   * @param string $granularity
   *   The granularity of the output (e.g., 'days', 'days:hours',
   *   'days:hours:minutes').
   * @param string $separator
   *   The separator to use between duration parts.
   *
   * @return string|null
   *   The formatted duration.
   */
  protected function formatDuration(int $seconds, string $granularity = 'days:hours:minutes', string $separator = ''): ?string {
    if (empty($seconds)) {
      return NULL;
    }

    // Explode granularity into parts.
    $granularityParts = explode(':', $granularity);
    $includeHours = in_array('hours', $granularityParts);
    $includeMinutes = in_array('minutes', $granularityParts);

    // Always calculate days.
    $days = floor($seconds / (24 * 3600));
    $seconds %= (24 * 3600);
    $hours = 0;
    $minutes = 0;

    // Calculate hours if included in granularity.
    if ($includeHours) {
      $hours = floor($seconds / 3600);
      $seconds %= 3600;
    }

    // Calculate minutes if included in granularity.
    if ($includeMinutes) {
      $minutes = floor($seconds / 60);
    }

    // Build the output string.
    $parts = [];
    if ($days > 0) {
      $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
    }
    if ($includeHours && $hours > 0) {
      $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
    }
    if ($includeMinutes && $minutes > 0) {
      $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    }

    return implode($separator, $parts);
  }

}
