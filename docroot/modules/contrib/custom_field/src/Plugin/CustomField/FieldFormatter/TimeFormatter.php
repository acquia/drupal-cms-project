<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldFormatterBase;
use Drupal\custom_field\Time;

/**
 * Plugin implementation of the 'time' formatter.
 */
#[FieldFormatter(
  id: 'time',
  label: new TranslatableMarkup('Default'),
  field_types: [
    'time',
  ],
)]
class TimeFormatter extends CustomFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'time_format' => 'h:i a',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['time_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Time format'),
      '#default_value' => $this->getSetting('time_format'),
      '#description' => $this->getTimeDescription(),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): ?string {
    $time = Time::createFromTimestamp($value);
    return $time?->format($this->getSetting('time_format'));
  }

  /**
   * Description of the time field.
   *
   * @return string
   *   Description of the time field.
   */
  private function getTimeDescription(): string {
    $output = $this->t('a - Lowercase am or pm') . '<br/>';
    $output .= $this->t('A - Uppercase AM or PM') . '<br/>';
    $output .= $this->t('B - Swatch Internet time (000 to 999)') . '<br/>';
    $output .= $this->t('g - 12-hour format of an hour (1 to 12)') . '<br/>';
    $output .= $this->t('G - 24-hour format of an hour (0 to 23)') . '<br/>';
    $output .= $this->t('h - 12-hour format of an hour (01 to 12)') . '<br/>';
    $output .= $this->t('H - 24-hour format of an hour (00 to 23)') . '<br/>';
    $output .= $this->t('i - Minutes with leading zeros (00 to 59)') . '<br/>';
    $output .= $this->t('s - Seconds, with leading zeros (00 to 59)') . '<br/>';
    return $output;
  }

}
