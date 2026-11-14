<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * Validates a choice that additionally accepts ECA's companion token options.
 *
 * Use this instead of the plain "Choice" constraint for every configuration key
 * whose form element is flagged with "#eca_token_select_option", and only for
 * those. Such an element gets two extra options injected at build time, which
 * the declared list of choices does not contain:
 * - "_eca_token", telling the plugin to read the value from a companion token
 *   at runtime.
 * - "", the "undefined" option, but only when the element is not "#required".
 *
 * This constraint adds exactly the same two values to the list of valid
 * choices, so that a configuration the user interface allows to be built also
 * passes configuration validation. Being required is not declared a second
 * time: the empty string is accepted unless the very same configuration key
 * also carries a "NotBlank" constraint, which is how a required value is
 * already expressed in configuration schema.
 *
 * Everything else behaves like the "Choice" constraint: both an inline list of
 * "choices" and a "callback" are supported, and the choices are left untouched
 * otherwise.
 *
 * @see \Drupal\eca\Plugin\ECA\PluginFormTrait::updateConfigurationForm()
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaChoiceConstraintValidator
 */
#[Constraint(
  id: 'EcaChoice',
  label: new TranslatableMarkup('ECA choice', [], ['context' => 'Validation'])
)]
class EcaChoiceConstraint extends Choice {

  /**
   * The option that defers the value to a companion token at runtime.
   */
  public const string TOKEN_OPTION = '_eca_token';

  /**
   * The option that leaves the value undefined on non-required elements.
   */
  public const string UNDEFINED_OPTION = '';

}
