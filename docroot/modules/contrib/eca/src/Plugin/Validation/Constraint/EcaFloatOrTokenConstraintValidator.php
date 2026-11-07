<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates a float or an ECA token value.
 *
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaFloatOrTokenConstraint
 */
final class EcaFloatOrTokenConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof EcaFloatOrTokenConstraint) {
      throw new UnexpectedTypeException($constraint, EcaFloatOrTokenConstraint::class);
    }
    if ($value === NULL) {
      return;
    }
    // is_numeric() is the predicate FloatOrToken::getCastedValue() branches on,
    // so using it here is what makes the accepted set and the stored set the
    // same one. The integer validator asks filter_var() instead, because its
    // data type does: is_numeric() would accept "1.5" on an integer key and a
    // cast would then truncate it.
    if (is_numeric($value)) {
      return;
    }
    // Not a number, so the same rule applies that decides what reaches
    // configuration storage unchanged: every non-empty string is passed
    // through. Accepting exactly that set is what keeps validation from
    // rejecting a value the storage happily keeps. It covers a single token, a
    // whole expression built from several of them, and the "_eca_token"
    // sentinel of a select element offering "Defined by token" alike, so none
    // of those needs to be recognized individually.
    // The empty string is not among them: castValue() turns it into NULL,
    // which is how an unset numeric value is stored.
    if (!is_string($value) || $value === '') {
      $this->context->addViolation($constraint->message);
    }
  }

}
