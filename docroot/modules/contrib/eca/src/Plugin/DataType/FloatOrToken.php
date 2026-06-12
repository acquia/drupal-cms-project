<?php

namespace Drupal\eca\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\FloatData;
use Drupal\Core\TypedData\Type\StringInterface;

/**
 * The float data type that also allows tokens.
 */
#[DataType(
  id: "eca_float_or_token",
  label: new TranslatableMarkup("Float or Token")
)]
class FloatOrToken extends FloatData implements StringInterface {

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    if (str_starts_with($this->value, '[') && str_ends_with($this->value, ']')) {
      return (string) $this->value;
    }
    return (float) $this->value;
  }

}
