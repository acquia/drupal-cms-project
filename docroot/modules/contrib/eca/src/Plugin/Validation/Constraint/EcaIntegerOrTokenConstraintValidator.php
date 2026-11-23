<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates an integer or an ECA token value.
 *
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaIntegerOrTokenConstraint
 */
final class EcaIntegerOrTokenConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof EcaIntegerOrTokenConstraint) {
      throw new UnexpectedTypeException($constraint, EcaIntegerOrTokenConstraint::class);
    }
    if ($value === NULL) {
      return;
    }
    $integer = filter_var($value, FILTER_VALIDATE_INT);
    if ($integer === FALSE) {
      // Not an integer, so the same rule applies that decides what reaches
      // configuration storage unchanged: every non-empty string is passed
      // through. Accepting exactly that set is what keeps validation from
      // rejecting a value the storage happily keeps. It covers a single token,
      // a whole expression built from several of them, and the "_eca_token"
      // sentinel of a select element offering "Defined by token" alike, so none
      // of those needs to be recognized individually.
      // The empty string is not among them: castValue() turns it into NULL,
      // which is how an unset numeric value is stored.
      if (!is_string($value) || $value === '') {
        $this->context->addViolation($constraint->message);
      }
      return;
    }
    $this->validateBounds($integer, $constraint);
  }

  /**
   * Reports an integer outside the bounds the validated key declares.
   *
   * The bounds are inherited from the schema type the key was retyped away
   * from, and only a genuine integer is compared against them.
   *
   * The violation codes are the ones the "Range" constraint uses for the same
   * three cases, so that a bound violation stays recognizable as such no matter
   * which of the two constraints reported it.
   *
   * @param int $value
   *   The integer to check.
   * @param \Drupal\eca\Plugin\Validation\Constraint\EcaIntegerOrTokenConstraint $constraint
   *   The constraint carrying the bounds.
   *
   * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\RangeConstraintValidator::validate()
   */
  private function validateBounds(int $value, EcaIntegerOrTokenConstraint $constraint): void {
    $min = $constraint->min;
    $max = $constraint->max;
    $tooLow = $min !== NULL && $value < $min;
    $tooHigh = $max !== NULL && $value > $max;
    if (!$tooLow && !$tooHigh) {
      return;
    }
    if ($min !== NULL && $max !== NULL) {
      $this->context->buildViolation($constraint->notInRangeMessage)
        ->setParameter('{{ value }}', $this->formatValue($value))
        ->setParameter('{{ min }}', $this->formatValue($min))
        ->setParameter('{{ max }}', $this->formatValue($max))
        ->setCode(Range::NOT_IN_RANGE_ERROR)
        ->addViolation();
      return;
    }
    $this->context->buildViolation($tooLow ? $constraint->minMessage : $constraint->maxMessage)
      ->setParameter('{{ value }}', $this->formatValue($value))
      ->setParameter('{{ limit }}', $this->formatValue($tooLow ? $min : $max))
      ->setCode($tooLow ? Range::TOO_LOW_ERROR : Range::TOO_HIGH_ERROR)
      ->addViolation();
  }

}
