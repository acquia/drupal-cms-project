<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Defines a protocol validation constraint for links to external URLs.
 */
#[Constraint(
  id: 'CustomFieldLinkExternalProtocols',
  label: new TranslatableMarkup('No dangerous external protocols', [], ['context' => 'Validation'])
)]
class LinkExternalProtocolsConstraint extends SymfonyConstraint {

  /**
   * The validation message.
   *
   * @var string
   */
  public string $message = "The path '@uri' is invalid.";

}
