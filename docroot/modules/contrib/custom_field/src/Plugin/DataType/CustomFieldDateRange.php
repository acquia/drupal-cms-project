<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\DataType;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeTypeInterface;
use Drupal\custom_field\Plugin\Field\FieldType\CustomItem;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;

/**
 * The custom_field_daterange data type.
 */
#[DataType(
  id: 'custom_field_daterange',
  label: new TranslatableMarkup('Date range'),
  definition_class: CustomFieldDataDefinition::class,
)]
class CustomFieldDateRange extends CustomFieldDataTypeBase {

  /**
   * The end date value.
   *
   * @var string|null
   */
  protected ?string $endDate;

  /**
   * The time zone value.
   *
   * @var string|null
   */
  protected ?string $timezone = NULL;

  /**
   * The duration in seconds.
   *
   * @var int|null
   */
  protected ?int $duration;

  /**
   * Date format for SQL conversion.
   *
   * @var string
   *
   * @see \Drupal\views\Plugin\views\query\Sql::getDateFormat()
   */
  protected string $dateFormat = DateTimeTypeInterface::DATETIME_STORAGE_FORMAT;

  /**
   * The date type.
   *
   * @var string
   */
  protected string $datetimeType = DateTimeType::DATETIME_TYPE_DATETIME;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?FieldItemInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    if ($definition->getSetting('datetime_type') === DateTimeType::DATETIME_TYPE_DATE) {
      $this->datetimeType = DateTimeType::DATETIME_TYPE_DATE;
      $this->dateFormat = DateTimeTypeInterface::DATE_STORAGE_FORMAT;
    }
    if ($parent) {
      $this->endDate = $parent->get($this->getName() . CustomItem::SEPARATOR . 'end')
        ->getValue();
      $this->timezone = $parent->get($this->getName() . CustomItem::SEPARATOR . 'timezone')
        ->getValue();
      $duration = $parent->get($this->getName() . CustomItem::SEPARATOR . 'duration')
        ->getValue();
      $this->duration = $duration ? (int) $duration : NULL;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \DateMalformedIntervalStringException
   */
  public function setValue($value, $notify = TRUE): void {
    // Treat the values as property value of the main property, if no array is
    // given.
    $parent = $this->getParent();
    if (isset($value) && !is_array($value)) {
      $value = [
        'value' => $value,
        'end_value' => NULL,
        'timezone' => NULL,
      ];
    }

    $this->value = !empty($value['value']) && is_string($value['value']) ? $value['value'] : NULL;
    $this->endDate = !empty($value['end_value']) && is_string($value['end_value']) ? $value['end_value'] : NULL;
    if (!empty($this->value)) {
      $start = new DrupalDateTime($this->value, 'UTC');
      if (!empty($this->endDate)) {
        $end = new DrupalDateTime($this->endDate, 'UTC');
        $seconds = $end->getTimestamp() - $start->getTimestamp();
        $this->duration = $seconds;
        $parent->set($this->getName() . CustomItem::SEPARATOR . 'end', $this->endDate);
      }
      if (isset($value['timezone'])) {
        $this->timezone = (string) $value['timezone'];
        $parent->set($this->getName() . CustomItem::SEPARATOR . 'timezone', $this->timezone);
      }
    }
  }

  /**
   * Gets the stored timezone.
   *
   * @return string|null
   *   The stored timezone.
   */
  public function getTimezone(): ?string {
    return $this->timezone;
  }

  /**
   * Gets the duration.
   *
   * @return int|null
   *   The duration value in seconds.
   */
  public function getDuration(): ?int {
    return $this->duration;
  }

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    return $this->value;
  }

}
