<?php

declare(strict_types=1);

namespace Drupal\custom_field\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a year range configuration element.
 */
#[FormElement('custom_field_date_year_range')]
class DateYearRange extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#element_validate' => [
        [$class, 'validateRange'],
      ],
      '#process' => [
        [$class, 'processRange'],
      ],
      '#theme' => 'custom_field_flex_wrapper',
      '#theme_wrappers' => ['fieldset'],
      '#attached' => [
        'library' => ['custom_field/custom-field-widget'],
      ],
      '#description' => t('Enter a relative value (-9, +9) or an absolute year such as 2015.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Convert the element's default value from a string to an array (to match
    // what we will get from the two text fields when the form is submitted).
    if ($input === FALSE && isset($element['#default_value'])) {
      [$years_back, $years_forward] = explode(':', $element['#default_value']);
      return [
        'years_back' => $years_back,
        'years_forward' => $years_forward,
      ];
    }
    return parent::valueCallback($element, $input, $form_state);
  }

  /**
   * Process callback.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $complete_form
   *   The complete form.
   *
   * @return array<string, mixed>
   *   The processed form element.
   */
  public static function processRange(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    // Year range is stored in the -3:+3 format but collected as two separate
    // text fields.
    $element['years_back'] = [
      '#type' => 'textfield',
      '#title' => t('Starting year'),
      '#default_value' => $element['#value']['years_back'],
      '#size' => 10,
      '#maxsize' => 10,
    ];
    $element['years_forward'] = [
      '#type' => 'textfield',
      '#title' => t('Ending year'),
      '#default_value' => $element['#value']['years_forward'],
      '#size' => 10,
      '#maxsize' => 10,
    ];

    $element['#tree'] = TRUE;

    return $element;
  }

  /**
   * Validate callback.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $complete_form
   *   The complete form.
   */
  public static function validateRange(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    // Recombine the two submitted form values into the -3:+3 format we will
    // validate and save.
    $year_range_submitted = $form_state->getValue($element['#parents']);
    $year_range = $year_range_submitted['years_back'] . ':' . $year_range_submitted['years_forward'];
    $form_state->setValue($element['#parents'], $year_range);
    if (!static::rangeIsValid($year_range)) {
      $form_state->setError($element['years_back'], t('Starting year must be a relative value (-9, +9) or an absolute year such as 1980.'));
      $form_state->setError($element['years_forward'], t('Ending year must be a relative value (-9, +9) or an absolute year such as 2030.'));
    }
  }

  /**
   * Check if the range is valid.
   *
   * @param string $range
   *   Range to validate.
   *
   * @return bool
   *   TRUE if the range is valid.
   */
  public static function rangeIsValid(string $range): bool {
    $matches = preg_match('@^([\+|\-][0-9]+|[0-9]{4}):([\+|\-][0-9]+|[0-9]{4})$@', $range);
    return !($matches < 1);
  }

}
