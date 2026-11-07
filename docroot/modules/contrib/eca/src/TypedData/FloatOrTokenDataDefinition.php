<?php

namespace Drupal\eca\TypedData;

use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines float data that may also contain an ECA token.
 */
final class FloatOrTokenDataDefinition extends DataDefinition {

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraints = parent::getConstraints();
    // FloatOrToken implements both the float and string interfaces so that
    // config schema type checks accept both stored representations. Core's
    // PrimitiveType validator would nevertheless apply its float check to a
    // token string, so the dedicated constraint replaces it.
    unset($constraints['PrimitiveType']);
    $constraints['EcaFloatOrToken'] ??= [];
    return $constraints;
  }

}
