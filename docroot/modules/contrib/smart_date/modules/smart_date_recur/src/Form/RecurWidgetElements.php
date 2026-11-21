<?php

namespace Drupal\smart_date_recur\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Handles the recurring rule elements added to a Smart Date widget.
 *
 * The elements are built by smart_date_recur_widget_extra_fields(), and are
 * intended to move here as well.
 *
 * States resolve a selector against the whole page, so a selector built from
 * the delta alone matches the selects of every recurring field on the form.
 * The final name of a select is only known once the form is built, so the
 * states are added in an #after_build callback, from the names Drupal renders.
 */
class RecurWidgetElements {

  /**
   * Form API #after_build callback for a recurring Smart Date widget element.
   *
   * @param array $element
   *   The built widget element for one delta.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The element, with #states added to its recurring rule elements.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state): array {
    // If an alter hook moved or removed the selects, leave the element alone.
    if (!isset($element['repeat']['#name'], $element['repeat-end']['#name'])) {
      return $element;
    }
    $repeat = 'select[name="' . $element['repeat']['#name'] . '"]';
    $repeat_end = 'select[name="' . $element['repeat-end']['#name'] . '"]';

    foreach (static::getStates($repeat, $repeat_end) as [$parents, $states]) {
      if (NestedArray::keyExists($element, $parents)) {
        NestedArray::setValue($element, array_merge($parents, ['#states']), $states);
      }
    }
    return $element;
  }

  /**
   * Gets the #states for each element that depends on the repeat selects.
   *
   * @param string $repeat
   *   The selector for the select of the repeat frequency.
   * @param string $repeat_end
   *   The selector for the select of the repeat limit type.
   *
   * @return array
   *   A list of pairs: the parents of an element, and its #states.
   */
  protected static function getStates(string $repeat, string $repeat_end): array {
    // Hidden until the rule repeats.
    $repeats = ['invisible' => [$repeat => ['value' => '']]];
    // Shown for the given frequencies only.
    $frequency = static fn (string ...$values): array => [
      'visible' => array_map(static fn ($value) => [$repeat => ['value' => $value]], $values),
    ];

    return [
      [['interval'], $repeats],
      [['repeat-end'], $repeats],
      [['repeat-end-count'], [
        'visible' => [
          $repeat => ['!value' => ''],
          $repeat_end => ['value' => 'COUNT'],
        ],
      ],
      ],
      [['repeat-end-date'], [
        'visible' => [
          $repeat => ['!value' => ''],
          $repeat_end => ['value' => 'UNTIL'],
        ],
      ],
      ],
      [['repeat-advanced'], $repeats],
      [['repeat-advanced', 'byday'], $frequency('MINUTELY', 'HOURLY', 'DAILY', 'WEEKLY')],
      [['repeat-advanced', 'which'], $frequency('MONTHLY', 'YEARLY')],
      [['repeat-advanced', 'weekday'], $frequency('MONTHLY', 'YEARLY')],
      [['repeat-advanced', 'restrict-hours'], $frequency('MINUTELY', 'HOURLY')],
      [['repeat-advanced', 'restrict-hours', 'byhour'], $frequency('MINUTELY', 'HOURLY')],
      [['repeat-advanced', 'restrict-minutes'], $frequency('MINUTELY')],
      [['repeat-advanced', 'restrict-minutes', 'byminute'], $frequency('MINUTELY')],
    ];
  }

}
