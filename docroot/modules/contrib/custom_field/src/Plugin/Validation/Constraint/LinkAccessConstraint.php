<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Defines an access validation constraint for links.
 */
#[Constraint(
  id: 'CustomFieldLinkAccess',
  label: new TranslatableMarkup('Link URI can be accessed by the user.', [], ['context' => 'Validation'])
)]
class LinkAccessConstraint extends SymfonyConstraint {

  /**
   * The validation message.
   *
   * @var string
   */
  public string $message = "The path '@uri' is inaccessible.";

}
