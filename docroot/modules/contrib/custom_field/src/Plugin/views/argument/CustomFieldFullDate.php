<?php

namespace Drupal\custom_field\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for a full date (CCYYMMDD).
 */
#[ViewsArgument(
  id: 'custom_field_datetime_full_date',
)]
class CustomFieldFullDate extends CustomFieldDate {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'Ymd';

}
