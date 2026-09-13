<?php

namespace Drupal\Tests\smart_date\Unit;

use Drupal\smart_date\SmartDateTrait;

/**
 * Exposes SmartDateTrait static methods for unit testing.
 *
 * LoadSmartDateFormat() is stubbed because it requires an entity manager.
 * FormatDurationTime() is re-declared public so tests can invoke it without
 * resorting to reflection.
 */
class SmartDateTraitTestDouble {

  use SmartDateTrait;

  /**
   * Stub: returns NULL to avoid requiring an entity manager.
   */
  public static function loadSmartDateFormat($formatName) {
    return NULL;
  }

  /**
   * Public proxy for the protected formatDurationTime() trait method.
   */
  public static function exposedFormatDurationTime(string $string): string {
    return static::formatDurationTime($string);
  }

}
