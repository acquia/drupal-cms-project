<?php

namespace Drupal\custom_field\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for a day.
 */
#[ViewsArgument(
  id: 'custom_field_datetime_day',
)]
class CustomFieldDayDate extends CustomFieldDate {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'd';

}
