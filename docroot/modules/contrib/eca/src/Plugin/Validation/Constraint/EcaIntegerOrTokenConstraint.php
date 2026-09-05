<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates an integer or an ECA token value.
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

}
