<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates an integer or an ECA token value.
 */
final class EcaIntegerOrTokenConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof EcaIntegerOrTokenConstraint) {
      throw new UnexpectedTypeException($constraint, EcaIntegerOrTokenConstraint::class);
    }
    $isToken = $value === EcaChoiceConstraint::TOKEN_OPTION || (is_string($value) && preg_match('/^\[[^\[\]]+\]$/', $value) === 1);
    if ($value === NULL || filter_var($value, FILTER_VALIDATE_INT) !== FALSE || $isToken) {
      return;
    }
    $this->context->addViolation($constraint->message);
  }

}
