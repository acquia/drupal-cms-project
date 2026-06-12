<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for links receiving data allowed by its settings.
 */
#[Constraint(
  id: 'CustomFieldLinkType',
  label: new TranslatableMarkup('Link data valid for link type.', [], ['context' => 'Validation'])
)]
class LinkTypeConstraint extends SymfonyConstraint {

  /**
   * The validation message.
   *
   * @var string
   */
  public string $message = "The path '@uri' is invalid.";

}
