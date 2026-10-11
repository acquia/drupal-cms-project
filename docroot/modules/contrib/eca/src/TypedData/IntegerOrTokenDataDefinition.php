<?php

namespace Drupal\eca\TypedData;

use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines integer data that may also contain an ECA token.
 */
final class IntegerOrTokenDataDefinition extends DataDefinition {

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraints = parent::getConstraints();
    // IntegerOrToken implements both the integer and string interfaces so that
    // config schema type checks accept both stored representations. Core's
    // PrimitiveType validator would nevertheless apply its integer check to a
    // token string, so the dedicated constraint replaces it.
    unset($constraints['PrimitiveType']);
    $constraints['EcaIntegerOrToken'] ??= [];
    return $constraints;
  }

}
