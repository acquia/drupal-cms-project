<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Validation\Constraint;

use Drupal\custom_field\Time;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that the submitted value can be converted into a valid time object.
 */
class TimeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   *
   * @param mixed $value
   *   The value to validate.
   * @param \Drupal\custom_field\Plugin\Validation\Constraint\TimeConstraint $constraint
   *   The constraint to apply.
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (Time::isEmpty($value)) {
      return;
    }
    $time_is_valid = TRUE;

    try {
      Time::createFromTimestamp($value);
    }
    catch (\InvalidArgumentException $exception) {
      $time_is_valid = FALSE;
    }

    if (!$time_is_valid) {
      $this->context->addViolation($constraint->message, ['@time' => $value]);
    }
  }

}
