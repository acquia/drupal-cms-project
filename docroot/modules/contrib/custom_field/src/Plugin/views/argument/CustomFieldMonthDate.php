<?php

namespace Drupal\custom_field\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for a month.
 */
#[ViewsArgument(
  id: 'custom_field_datetime_month',
)]
class CustomFieldMonthDate extends CustomFieldDate {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'm';

}
