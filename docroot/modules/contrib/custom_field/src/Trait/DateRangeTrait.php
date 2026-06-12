<?php

namespace Drupal\custom_field\Trait;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Trait for various date range methods.
 */
trait DateRangeTrait {

  /**
   * Helper function to determine all-day status for a date range.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime|null $start_date
   *   The start date.
   * @param \Drupal\Core\Datetime\DrupalDateTime|null $end_date
   *   The end date.
   *
   * @return bool
   *   The all-day status.
   */
  protected function isAllDay(?DrupalDateTime $start_date, ?DrupalDateTime $end_date): bool {
    if (!$start_date instanceof DrupalDateTime || !$end_date instanceof DrupalDateTime) {
      return FALSE;
    }
    $start_date->setTimezone(timezone_open('UTC'));
    $end_date->setTimezone(timezone_open('UTC'));
    $diff = $start_date->diff($end_date);

    return $diff->h === 23 && $diff->i === 59;
  }

  /**
   * Helper function to determine same-day status for a date range.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime|null $start_date
   *   The start date.
   * @param \Drupal\Core\Datetime\DrupalDateTime|null $end_date
   *   The end date.
   * @param string|null $timezone
   *   The timezone.
   *
   * @return bool
   *   The same-day status.
   */
  protected function isSameDay(?DrupalDateTime $start_date, ?DrupalDateTime $end_date, ?string $timezone = NULL): bool {
    if (!$start_date instanceof DrupalDateTime || !$end_date instanceof DrupalDateTime) {
      return FALSE;
    }

    try {
      $user_timezone = new \DateTimeZone(!empty($timezone) ? $timezone : date_default_timezone_get());
      $start_date->setTimezone($user_timezone);
      $end_date->setTimezone($user_timezone);
      $temp_start = $start_date->format('Y-m-d');
      $temp_end = $end_date->format('Y-m-d');
      return $temp_start === $temp_end;
    }
    catch (\Exception) {
      return FALSE;
    }
  }

}
