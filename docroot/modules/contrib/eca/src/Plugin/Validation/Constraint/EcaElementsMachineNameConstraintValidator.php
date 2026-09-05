<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Drupal\eca\Plugin\FormFieldMachineName;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates an ECA elements machine name.
 *
 * The verdict is delegated to the same function that backs the form element
 * validator, so that a value the user interface accepts also passes
 * configuration validation, and the other way round.
 *
 * @see \Drupal\eca\Plugin\FormFieldMachineName::isValidElementsMachineName()
 */
class EcaElementsMachineNameConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof EcaElementsMachineNameConstraint) {
      throw new UnexpectedTypeException($constraint, EcaElementsMachineNameConstraint::class);
    }
    if ($value === NULL) {
      return;
    }
    if (!is_string($value)) {
      throw new UnexpectedValueException($value, 'string');
    }
    // Translate the declared contract into the arguments that select it, so
    // that both sides of the validation share one single code path.
    [$token_reference, $token_replacement] = match ($constraint->contract) {
      EcaElementsMachineNameConstraint::CONTRACT_MACHINE_NAME => [FALSE, FALSE],
      EcaElementsMachineNameConstraint::CONTRACT_TOKEN_REFERENCE => [TRUE, FALSE],
      EcaElementsMachineNameConstraint::CONTRACT_TOKEN_REPLACEMENT => [FALSE, TRUE],
      default => throw new ConstraintDefinitionException(sprintf('The contract "%s" is not supported by the "EcaElementsMachineName" constraint.', $constraint->contract)),
    };
    if (!FormFieldMachineName::isValidElementsMachineName($value, $token_reference, $token_replacement)) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('%value', $this->formatValue($value))
        ->addViolation();
    }
  }

}
