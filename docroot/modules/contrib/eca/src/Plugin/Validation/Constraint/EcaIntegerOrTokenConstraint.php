<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates an integer or an ECA token value.
 *
 * The optional "min" and "max" options are named after the options of the
 * "Range" constraint, because that is where they come from: a configuration key
 * declared with a schema type that bounds its value, such as core's "weight",
 * is retyped to "eca_integer_or_token" and would otherwise leave those bounds
 * behind along with the type. They apply to genuine integers only. A token has
 * no numeric value to compare against and is accepted whatever the bounds are.
 *
 * That pass-through is why the "Range" constraint cannot simply be kept on the
 * retyped key: its validator reports every non-numeric value as an invalid
 * number, so it would reject every token this data type exists to allow.
 *
 * @see \Drupal\eca\Hook\ConfigSchemaHooksTrait::alterSchemaFieldType()
 * @see \Symfony\Component\Validator\Constraints\RangeValidator::validate()
 */
#[Constraint(
  id: 'EcaIntegerOrToken',
  label: new TranslatableMarkup('ECA integer or token', [], ['context' => 'Validation'])
)]
final class EcaIntegerOrTokenConstraint extends SymfonyConstraint {

  /**
   * The violation message.
   */
  public string $message = 'This value should be an integer or an ECA token.';

  /**
   * The violation message for an integer outside both bounds.
   */
  public string $notInRangeMessage = 'This value should be between %min and %max.';

  /**
   * The violation message for an integer below the lower bound.
   */
  public string $minMessage = 'This value should be %limit or more.';

  /**
   * The violation message for an integer above the upper bound.
   */
  public string $maxMessage = 'This value should be %limit or less.';

  /**
   * Constructs an EcaIntegerOrTokenConstraint object.
   *
   * @param int|null $min
   *   The lowest integer the validated key accepts, or NULL for no lower
   *   bound.
   * @param int|null $max
   *   The highest integer the validated key accepts, or NULL for no upper
   *   bound.
   * @param string[]|null $groups
   *   The validation groups this constraint belongs to.
   * @param mixed $payload
   *   Domain-specific data attached to this constraint.
   */
  #[HasNamedArguments]
  public function __construct(
    public readonly ?int $min = NULL,
    public readonly ?int $max = NULL,
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(NULL, $groups, $payload);
  }

}
