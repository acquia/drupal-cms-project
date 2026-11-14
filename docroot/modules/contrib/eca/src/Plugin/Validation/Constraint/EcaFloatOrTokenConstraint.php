<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates a float or an ECA token value.
 *
 * Unlike its integer counterpart this constraint takes no "min" and "max"
 * options, and their absence is deliberate rather than an omission. Those
 * options exist there to rescue bounds that a retyped key would otherwise leave
 * behind: core declares its "weight" type with a "Range" constraint, so
 * retyping a "weight" key to "eca_integer_or_token" would drop that bound along
 * with the type. Core's "float" type declares no constraints at all, so a
 * retyped "float" key has nothing to lose and there is nothing to reconstruct.
 *
 * @see \Drupal\eca\Hook\ConfigSchemaHooksTrait::alterSchemaFieldType()
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaIntegerOrTokenConstraint
 */
#[Constraint(
  id: 'EcaFloatOrToken',
  label: new TranslatableMarkup('ECA float or token', [], ['context' => 'Validation'])
)]
final class EcaFloatOrTokenConstraint extends SymfonyConstraint {

  /**
   * The violation message.
   */
  public string $message = 'This value should be a float or an ECA token.';

}
