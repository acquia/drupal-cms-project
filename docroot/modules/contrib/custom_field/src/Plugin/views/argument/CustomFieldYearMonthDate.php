<?php

namespace Drupal\custom_field\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for a year plus month (CCYYMM).
  */
#[ViewsArgument(
  id: 'custom_field_datetime_year_month',
)]
class CustomFieldYearMonthDate extends CustomFieldDate {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'Ym';

}
