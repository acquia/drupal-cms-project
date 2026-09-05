<?php

namespace Drupal\custom_field\Enum;

/**
 * Declares constants used in the DateRangeDefaultFormatter.
 */
enum DateTimeRangeDisplayOptions: string {

  // Values for the 'from_to' formatter setting.
  case Both = 'both';
  case StartDate = 'start_date';
  case EndDate = 'end_date';

}
