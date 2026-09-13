<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\DataType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\custom_field\Plugin\Field\FieldType\CustomItem;
use Drupal\custom_field\Time;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;

/**
 * The custom_field_time_range data type.
 */
#[DataType(
  id: 'custom_field_time_range',
  label: new TranslatableMarkup('Time range'),
  definition_class: CustomFieldDataDefinition::class,
)]
class CustomFieldTimeRange extends CustomFieldDataTypeBase {

  /**
   * The end time value.
   *
   * @var int|null
   */
  protected ?int $endTime;

  /**
   * The duration in seconds.
   *
   * @var int|null
   */
  protected ?int $duration;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?FieldItemInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    if ($parent) {
      $end_value = $parent->get($this->getName() . CustomItem::SEPARATOR . 'end')
        ->getValue();
      $duration = $parent->get($this->getName() . CustomItem::SEPARATOR . 'duration')
        ->getValue();
      $this->endTime = $end_value ? (int) $end_value : NULL;
      $this->duration = $duration ? (int) $duration : NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    // Treat the values as property value of the main property, if no array is
    // given.
    $parent = $this->getParent();
    if (isset($value) && !is_array($value)) {
      $value = [
        'value' => $value,
        'end_value' => NULL,
      ];
    }

    $this->value = $value['value'] ?? NULL;
    $this->endTime = $value['end_value'] ?? NULL;
    if (!Time::isEmpty($this->value) && !Time::isEmpty($this->endTime)) {
      $parent->set($this->getName() . CustomItem::SEPARATOR . 'end', $this->endTime);
    }
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
