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
    // Any non-numeric string is passed through unchanged, not only a value
    // shaped like "[a:token]". ECA stores other non-numeric placeholders in
    // numeric configuration keys too, most notably the "_eca_token" sentinel of
    // a select element offering "Defined by token", and casting those to a
    // float would silently lose them just the same.
    if (is_string($this->value) && $this->value !== '' && !is_numeric($this->value)) {
      return $this->value;
    }
    return (float) $this->value;
  }

}
