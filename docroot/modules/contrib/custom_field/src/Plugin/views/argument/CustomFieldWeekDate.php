<?php

namespace Drupal\custom_field\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for a week.
 */
#[ViewsArgument(
  id: 'custom_field_datetime_week'
)]
class CustomFieldWeekDate extends CustomFieldDate {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'W';

}
