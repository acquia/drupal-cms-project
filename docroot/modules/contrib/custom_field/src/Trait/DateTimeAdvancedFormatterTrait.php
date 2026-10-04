<?php

namespace Drupal\custom_field\Trait;

/**
 * Trait for various date range methods.
 */
trait DateTimeAdvancedFormatterTrait {

  /**
   * Helper function to get the date parts to display.
   *
   * @return array
   *   The filtered date parts.
   */
  protected function getFilteredDateParts(): array {
    $date_parts = $this->getSetting('date_format_parts') ?? [];
    return array_filter($date_parts, function ($part) {
      return !empty($part['format']);
    });
  }

  /**
   * Helper function to get the time parts to display.
   *
   * @return array
   *   The filtered time parts.
   */
  protected function getFilteredTimeParts(): array {
    $time_parts = $this->getSetting('time_format_parts') ?? [];
    return array_filter($time_parts, function ($part) {
      return !empty($part['format']);
    });
  }

  /**
   * Helper function to fill time parts form element.
   *
   * @return array<string, mixed>
   *   The date parts.
   */
  protected function getTimePartElements(): array {
    return [
      'hour' => [
        'label' => $this->t('Hour'),
        'options' => [
          'g',
          'G',
          'h',
          'H',
        ],
      ],
      'minute' => [
        'label' => $this->t('Minute'),
        'options' => [
          'i',
        ],
      ],
      'second' => [
        'label' => $this->t('Second'),
        'options' => [
          's',
        ],
      ],
      'am_pm' => [
        'label' => $this->t('AM/PM'),
        'options' => [
          'a',
          'A',
        ],
      ],
    ];
  }

  /**
   * Helper function to fill date parts form element.
   *
   * @return array<string, mixed>
   *   The date parts.
   */
  protected function getDatePartElements(): array {
    return [
      'day' => [
        'label' => $this->t('Day'),
        'options' => [
          'd',
          'D',
          'j',
          'l',
          'N',
          'jS',
          'w',
          'z',
        ],
      ],
      'month' => [
        'label' => $this->t('Month'),
        'options' => [
          'F',
          'm',
          'M',
          'n',
          't',
        ],
      ],
      'year' => [
        'label' => $this->t('Year'),
        'options' => [
          'Y',
          'y',
        ],
      ],
    ] + $this->getTimePartElements();
  }

}
