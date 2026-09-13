<?php

namespace Drupal\eca_migrate\Event;

use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\migrate\Row;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched on migrate row value processing.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_migrate\Event
 */
class EcaMigrateProcessEvent extends Event {

  /**
   * The value to transform.
   */
  protected mixed $value;

  /**
   * The migration row being processed.
   */
  protected Row $row;

  /**
   * The migration destination property.
   */
  protected string $destinationProperty;

  /**
   * The migration plugin ID being run.
   */
  protected string $migrationId;

  /**
   * Constructs an event.
   *
   * @param mixed $value
   *   The value to transform.
   * @param \Drupal\migrate\Row $row
   *   The migration row being processed.
   * @param string $destination_property
   *   The migration destination property.
   * @param string $migration_id
   *   The migration plugin ID being run.
   */
  public function __construct(
    mixed $value,
    Row $row,
    string $destination_property,
    string $migration_id = '',
  ) {
    $this->value = $value;
    $this->row = $row;
    $this->destinationProperty = $destination_property;
    $this->migrationId = $migration_id;
  }

  /**
   * Returns processed value.
   *
   * @return mixed
   *   The processed value.
   */
  public function getValue(): mixed {
    return $this->value;
  }

  /**
   * Sets the processed value.
   *
   * @param mixed $value
   *   Value to set.
   */
  public function setValue(mixed $value): void {
    if ($value instanceof DataTransferObject) {
      // A DTO with no properties only carries a scalar/string representation,
      // so return that scalar directly. Any array of actual properties
      // (indexed or associative, single or multiple elements) is returned
      // unchanged.
      $this->value = $value->count() ? $value->toArray() : $value->getValue();
    }
    else {
      $this->value = $value;
    }
  }

  /**
   * Returns the processed migration row.
   *
   * @return \Drupal\migrate\Row
   *   The processed migration row.
   */
  public function getRow(): Row {
    return $this->row;
  }

  /**
   * Returns the destination property.
   *
   * @return string
   *   The destination property.
   */
  public function getDestinationProperty(): string {
    return $this->destinationProperty;
  }

  /**
   * Returns the migration plugin ID being run.
   *
   * @return string
   *   The migration plugin ID.
   */
  public function getMigrationId(): string {
    return $this->migrationId;
  }

}
