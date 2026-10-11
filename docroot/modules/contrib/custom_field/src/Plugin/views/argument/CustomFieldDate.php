<?php

namespace Drupal\custom_field\Plugin\views\argument;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\Date as NumericDate;

/**
 * Abstract argument handler for dates.
 *
 * Adds an option to set a default argument based on the current date.
 *
 * Definitions terms:
 * - many to one: If true, the "many to one" helper will be used.
 * - invalid input: A string to give to the user for obviously invalid input.
 *                  This is deprecated in favor of argument validators.
 *
 * @see \Drupal\views\ManyToOneHelper
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
  id: 'custom_field_datetime',
)]
class CustomFieldDate extends NumericDate {

  /**
   * Determines if the timezone offset is calculated.
   *
   * @var bool
   */
  protected bool $calculateOffset = TRUE;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteMatchInterface $route_match,
    DateFormatterInterface $date_formatter,
    TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match, $date_formatter, $time);

    if ($configuration['datetime_type'] === DateTimeType::DATETIME_TYPE_DATE) {
      // Timezone offset calculation is not applicable to dates that are stored
      // as date-only.
      $this->calculateOffset = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDateField(): string {
    // Use string date storage/formatting since datetime fields are stored as
    // strings rather than UNIX timestamps.
    return $this->query->getDateField("$this->tableAlias.$this->realField", TRUE, $this->calculateOffset);
  }

  /**
   * {@inheritdoc}
   */
  public function getDateFormat($format): string {
    // Pass in the string-field option.
    return $this->query->getDateFormat($this->getDateField(), $format, TRUE);
  }

}
