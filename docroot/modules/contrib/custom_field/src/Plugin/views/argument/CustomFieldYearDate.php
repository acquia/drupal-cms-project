<?php

namespace Drupal\custom_field\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for a year.
  */
#[ViewsArgument(
  id: 'custom_field_datetime_year',
)]
class CustomFieldYearDate extends CustomFieldDate {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'Y';

}
