<?php

namespace Drupal\office_hours\Plugin\Field\FieldType;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\office_hours\OfficeHoursDateHelper;

/**
 * Plugin implementation of the 'office_hours' field type.
 *
 * @FieldType(
 *   id = "office_hours_exceptions",
 *   label = @Translation("Office hours exception"),
 *   list_class = "\Drupal\office_hours\Plugin\Field\FieldType\OfficeHoursItemList",
 *   no_ui = TRUE,
 * )
 */
class OfficeHoursExceptionsItem extends OfficeHoursItem {

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    // @todo Add random Exception day value in past and in near future.
    $value = [];
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function formatTimeSlot(array $settings): string {
    $result = match (TRUE) {
      // Exceptions header does not have time slot.
      $this->isExceptionHeader() => '',

      default => parent::formatTimeSlot($settings),
    };
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function label(array $settings): string {
    $day = $this->day;

    $label = match (TRUE) {
      // Caption on Exceptions header; avoid translating empty string.
      OfficeHoursDateHelper::isExceptionHeader($day)
      && $settings['exceptions']['title'] == ''
      => '',
      OfficeHoursDateHelper::isExceptionHeader($day)
      => $this->t(Html::escape($settings['exceptions']['title'])),

      // Normal label on Exceptions date.
      default => parent::label($settings),
    };
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function isExceptionDay(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isInRange(int $from, int $to): bool {
    if ($to < $from || $to < 0) {
      // @todo Error. Raise try/catch exception for $to < $from.
      // @todo Undefined result for <0. Raise try/catch exception.
      return FALSE;
    }

    if ($to == 0) {
      // All exceptions are OK.
      return TRUE;
    }

    $date = OfficeHoursDateHelper::format($from, 'Y-m-d');
    $time = OfficeHoursDateHelper::getRequestTime(0, $this->getParent());
    $today = OfficeHoursDateHelper::today();
    $yesterday = strtotime('-1 day', $today);
    $day = $this->day;

    if (OfficeHoursDateHelper::isExceptionDay($to)) {
      // $from, $to are calendar dates.
      // @todo Support not only ($from = today, $to = today).
    }
    else {
      // $from, $to is a range, e.g., 0..90 days.
      // Time slots from yesterday with endhours after midnight are included.
      // @todo Call parent::isInRange();
      // @todo Support $from <> 0.
      $to = strtotime("$date +$to day");
    }

    if ($day < $yesterday) {
      return FALSE;
    }
    elseif ($day == $yesterday && $day <= $to) {
      // We were open yesterday evening, check if we are still open.
      return parent::isOpen($time);
    }
    elseif ($day > $yesterday && $day <= $to) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isOpen($time): bool {
    $is_open = FALSE;

    $today = OfficeHoursDateHelper::today();
    $yesterday = strtotime('-1 day', $today);
    $day = $this->day;

    if ($day == $yesterday || $day == $today) {
      $is_open = parent::isOpen($time);
    }
    return (bool) $is_open;
  }

}
