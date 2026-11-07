<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint that the value is a time value.
 */
#[Constraint(
  id: 'CustomFieldTime',
  label: new TranslatableMarkup('Time', [], ['context' => 'Validation']),
)]
class TimeConstraint extends SymfonyConstraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public string $message = 'The value @time is not a valid time.';

}
