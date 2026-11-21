<?php

namespace Drupal\geofield\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for geospatial values.
 */
#[Constraint(
  id: 'GeoType',
  label: new TranslatableMarkup('Geo data valid for geofield type.', [], ['context' => 'Validation']),
)]
class GeoConstraint extends SymfonyConstraint {

  /**
   * Message for invalid value.
   *
   * @var string
   */
  public $message = '"@value" is not a valid geospatial content.';

}
