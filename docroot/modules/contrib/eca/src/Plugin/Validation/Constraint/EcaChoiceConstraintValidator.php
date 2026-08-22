<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Drupal\Core\TypedData\Validation\TypedDataAwareValidatorTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ChoiceValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the EcaChoice constraint.
 *
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaChoiceConstraint
 */
final class EcaChoiceConstraintValidator extends ChoiceValidator {

  use TypedDataAwareValidatorTrait;

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof EcaChoiceConstraint) {
      throw new UnexpectedTypeException($constraint, EcaChoiceConstraint::class);
    }
    if ($constraint->choices === NULL && !$constraint->callback) {
      // Neither choices nor a callback: let the parent report that.
      parent::validate($value, $constraint);
      return;
    }
    // Resolve the declared choices once and hand the parent a constraint that
    // carries the expanded list, so that all remaining choice semantics stay
    // with the parent implementation.
    $expanded = clone $constraint;
    $expanded->choices = $this->expandChoices($constraint);
    $expanded->callback = NULL;
    parent::validate($value, $expanded);
  }

  /**
   * Builds the list of choices including ECA's companion token options.
   *
   * @param \Drupal\eca\Plugin\Validation\Constraint\EcaChoiceConstraint $constraint
   *   The constraint to build the list of choices for.
   *
   * @return array
   *   The declared choices, plus the "Defined by token" option, plus the
   *   "undefined" option when the validated key is not required.
   */
  private function expandChoices(EcaChoiceConstraint $constraint): array {
    $choices = $this->resolveDeclaredChoices($constraint);
    $choices[] = EcaChoiceConstraint::TOKEN_OPTION;
    if (!$this->isRequired()) {
      $choices[] = EcaChoiceConstraint::UNDEFINED_OPTION;
    }
    return $choices;
  }

  /**
   * Resolves the choices as declared by the constraint itself.
   *
   * @param \Drupal\eca\Plugin\Validation\Constraint\EcaChoiceConstraint $constraint
   *   The constraint to resolve the declared choices of.
   *
   * @return array
   *   The declared choices.
   *
   * @throws \Symfony\Component\Validator\Exception\ConstraintDefinitionException
   *   When the declared callback is not callable or does not return an array.
   *
   * @see \Symfony\Component\Validator\Constraints\ChoiceValidator::validate()
   */
  private function resolveDeclaredChoices(EcaChoiceConstraint $constraint): array {
    if (!$constraint->callback) {
      return array_values($constraint->choices ?? []);
    }
    // The same callback resolution order as the parent implementation uses.
    if (!is_callable($callback = [$this->context->getObject(), $constraint->callback])
      && !is_callable($callback = [$this->context->getClassName(), $constraint->callback])
      && !is_callable($callback = $constraint->callback)
    ) {
      throw new ConstraintDefinitionException('The EcaChoice constraint expects a valid callback.');
    }
    $choices = $callback();
    if (!is_array($choices)) {
      throw new ConstraintDefinitionException(sprintf('The EcaChoice constraint callback "%s" is expected to return an array, but returned "%s".', trim($this->formatValue($constraint->callback), '"'), get_debug_type($choices)));
    }
    return array_values($choices);
  }

  /**
   * Determines whether the validated configuration key requires a value.
   *
   * The form element flag "#required" has no counterpart in configuration
   * schema, where a required value is expressed by a "NotBlank" constraint on
   * the very same key. Reusing that instead of introducing a second place to
   * declare that a value is required keeps the two from drifting apart.
   *
   * @return bool
   *   TRUE if the validated key must not be empty, FALSE otherwise.
   */
  private function isRequired(): bool {
    $constraints = $this->getTypedData()->getDataDefinition()->getConstraints();
    return array_key_exists('NotBlank', $constraints);
  }

}
