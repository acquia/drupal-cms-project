<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

/**
 * Interface definition for Datetime subfields.
 */
interface DateTimeTypeInterface {

  /**
   * Defines the timezone that dates should be stored in.
   */
  const STORAGE_TIMEZONE = 'UTC';

  /**
   * Defines the format that date and time should be stored in.
   */
  const DATETIME_STORAGE_FORMAT = 'Y-m-d\TH:i:s';

  /**
   * Defines the format that dates should be stored in.
   */
  const DATE_STORAGE_FORMAT = 'Y-m-d';

}
