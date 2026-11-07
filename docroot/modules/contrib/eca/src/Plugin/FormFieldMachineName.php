<?php

namespace Drupal\eca\Plugin;

use Drupal\Core\Form\FormStateInterface;

/**
 * Validates ECA plugin text fields used as machine names.
 *
 * The three patterns below are the single source of truth for what a
 * machine-name-like plugin field accepts. Both sides of the validation use
 * them: the form element validator in this class, and the configuration schema
 * through the "EcaElementsMachineName" constraint. Keeping them in one place is
 * what stops the user interface and configuration validation from drifting
 * apart.
 *
 * Every pattern accepts the empty string. Being required is a separate,
 * independent concern: on a form element it is expressed by "#required", and in
 * configuration schema by an additional "NotBlank" constraint.
 *
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaElementsMachineNameConstraint
 */
class FormFieldMachineName {

  /**
   * Pattern of a machine name without support for tokens.
   */
  public const string PATTERN_MACHINE_NAME = '/^[A-Za-z0-9_\-]*$/';

  /**
   * Pattern of a machine name referencing a token, hence allowing colons.
   */
  public const string PATTERN_TOKEN_REFERENCE = '/^[A-Za-z0-9:_\-]*$/';

  /**
   * Pattern of a machine name that supports token replacement.
   *
   * Brackets are allowed, so that the value may contain a token like
   * "[entity:value]", and so that a nested render array path may be expressed
   * as "details][title".
   */
  public const string PATTERN_TOKEN_REPLACEMENT = '/^[\[\]A-Za-z0-9:_\-]*$/';

  /**
   * Validates form element fields that are like machine names.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateElementsMachineName(array &$element, FormStateInterface $form_state): void {
    $value = $element['#value'];
    if ($value === '') {
      return;
    }
    if ($element['#eca_token_reference'] ?? FALSE) {
      // This field expects a token reference. Only characters, numbers,
      // hyphens, colons, and underscores are allowed.
      if (!self::isValidElementsMachineName($value, TRUE)) {
        $form_state->setError($element, t('The %name must be a machine-readable name (characters, numbers, hyphens, colons, and underscores only).', [
          '%name' => $element['#title'],
        ]));
      }
      return;
    }
    if (!($element['#eca_token_replacement'] ?? FALSE)) {
      // This field requires a machine name that doesn't support token
      // replacement. Only characters, numbers, hyphens, and underscores are
      // allowed.
      if (!self::isValidElementsMachineName($value)) {
        $form_state->setError($element, t('The %name must be a machine-readable name (characters, numbers, hyphens, and underscores only).', [
          '%name' => $element['#title'],
        ]));
      }
      return;
    }
    if (!self::isValidElementsMachineName($value, FALSE, TRUE)) {
      $form_state->setError($element, t('The %name must be a machine-readable name (characters, numbers, hyphens, colons, and underscores only). The name may also contain tokens, i.e. "[entity:value]" is allowed as well.', [
        '%name' => $element['#title'],
      ]));
    }
  }

  /**
   * Determines whether an elements machine name is valid.
   *
   * The empty string is valid for all three contracts. Whether a value is
   * required is decided elsewhere, see the class documentation.
   *
   * @param string $value
   *   The value to validate.
   * @param bool $token_reference
   *   Whether the value is a token reference.
   * @param bool $token_replacement
   *   Whether the value supports token replacement.
   *
   * @return bool
   *   TRUE if the value is valid, FALSE otherwise.
   */
  public static function isValidElementsMachineName(string $value, bool $token_reference = FALSE, bool $token_replacement = FALSE): bool {
    if ($token_reference) {
      return (bool) preg_match(self::PATTERN_TOKEN_REFERENCE, $value);
    }
    if (!$token_replacement) {
      return (bool) preg_match(self::PATTERN_MACHINE_NAME, $value);
    }
    return (bool) preg_match(self::PATTERN_TOKEN_REPLACEMENT, $value);
  }

}
