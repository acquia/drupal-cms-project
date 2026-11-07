<?php

declare(strict_types=1);

namespace Drupal\custom_field\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a duration element.
 */
#[FormElement('custom_field_duration')]
class Duration extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processDuration'],
      ],
      '#pre_render' => [
        [$class, 'preRenderDuration'],
      ],
      '#element_validate' => [
        [$class, 'validateDuration'],
      ],
      '#theme' => 'custom_field_flex_wrapper',
      '#theme_wrappers' => ['container'],
      '#duration_prefix' => '',
      '#duration_granularity' => 'days:hours:minutes',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state): int {
    if (is_array($input)) {
      $days = $input['days'] ?? 0;
      $hours = $input['hours'] ?? 0;
      $minutes = $input['minutes'] ?? 0;

      // Convert to seconds.
      return ((int) $days * 86400) + ((int) $hours * 3600) + ((int) $minutes * 60);
    }
    // If $input is FALSE, use the default value (if set).
    if ($input === FALSE && isset($element['#default_value'])) {
      return (int) $element['#default_value'];
    }

    return 0;
  }

  /**
   * Process callback for duration form element.
   *
   * @param array<string, mixed> $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array<string, mixed> $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processDuration(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    // Validate and split granularity string (e.g., 'minutes').
    $valid_granularity = ['days', 'hours', 'minutes'];
    $granularity = array_intersect(explode(':', $element['#duration_granularity'] ?? ''), $valid_granularity);
    $parents = $element['#parents'] ?? [];
    // Define maximum values based on a 365-day limit (31,536,000 seconds).
    // 8,760 hours.
    $max_hours = 365 * 24;
    // 525,600 minutes.
    $max_minutes = 365 * 24 * 60;

    // Define form elements based on granularity.
    foreach ($granularity as $part) {
      switch ($part) {
        case 'days':
          $element['days'] = [
            '#type' => 'number',
            '#title' => t('Days'),
            '#min' => 0,
            '#max' => 365,
            '#size' => 3,
            '#parents' => array_merge($parents, ['days']),
          ];
          break;

        case 'hours':
          $element['hours'] = [
            '#type' => 'number',
            '#title' => t('Hours'),
            '#min' => 0,
            '#max' => in_array('days', $granularity) ? 23 : $max_hours,
            '#size' => 2,
            '#parents' => array_merge($parents, ['hours']),
          ];
          break;

        case 'minutes':
          $element['minutes'] = [
            '#type' => 'number',
            '#title' => t('Minutes'),
            '#min' => 0,
            '#max' => in_array('hours', $granularity) ? 59 : $max_minutes,
            '#size' => 2,
            '#parents' => array_merge($parents, ['minutes']),
          ];
          break;
      }

      if (count($granularity) === 1) {
        $element['#theme'] = NULL;
        $element[$part]['#title_display'] = 'invisible';
      }
    }

    // Parse the default value (in seconds) into available granularity parts.
    if (isset($element['#default_value']) && is_numeric($element['#default_value'])) {
      $seconds = (int) $element['#default_value'];

      // Ensure the default value is non-negative and within a safe integer
      // range (32-bit max).
      if ($seconds >= 0 && $seconds <= 4294967295) {
        $remaining_seconds = $seconds;

        // Process days if available.
        if (in_array('days', $granularity) && isset($element['days'])) {
          $element['days']['#default_value'] = floor($remaining_seconds / 86400);
          $remaining_seconds %= 86400;
        }

        // Process hours if available.
        if (in_array('hours', $granularity) && isset($element['hours'])) {
          $element['hours']['#default_value'] = floor($remaining_seconds / 3600);
          $remaining_seconds %= 3600;
        }

        // Process minutes if available.
        if (in_array('minutes', $granularity) && isset($element['minutes'])) {
          $element['minutes']['#default_value'] = floor($remaining_seconds / 60);
        }
      }
    }

    $element['#tree'] = TRUE;

    return $element;
  }

  /**
   * Pre-render callback for the duration element.
   */
  public static function preRenderDuration(array $element): array {
    // Add a CSS class for styling.
    $element['#attributes']['class'][] = 'custom-field-duration';

    // Ensure sub-elements are properly structured for rendering.
    foreach (['days', 'hours', 'minutes'] as $part) {
      if (isset($element[$part])) {
        Element::setAttributes($element[$part], [
          'id',
          'name',
          'value',
          'size',
        ]);
      }
    }

    return $element;
  }

  /**
   * Validation callback for the duration element.
   */
  public static function validateDuration(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    // Get the processed value from valueCallback.
    $value = static::valueCallback($element, $form_state->getValue($element['#parents']), $form_state);

    // Validate non-zero duration (if required).
    if ($value <= 0 && $element['#required']) {
      $form_state->setError($element, t('The duration must be greater than zero.'));
    }

    // Set the processed value (seconds) in the form state.
    $form_state->setValue($element['#parents'], $value);
  }

}
