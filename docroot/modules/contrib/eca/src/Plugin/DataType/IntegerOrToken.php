<?php

namespace Drupal\eca\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\Core\TypedData\Type\StringInterface;

/**
 * The integer data type that also allows tokens.
 */
#[DataType(
  id: "eca_integer_or_token",
  label: new TranslatableMarkup("Integer or Token")
)]
class IntegerOrToken extends IntegerData implements StringInterface {

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    // Any string that is not a clean integer is passed through unchanged, not
    // only a value shaped like "[a:token]". ECA stores other non-numeric
    // placeholders in numeric configuration keys too, most notably the
    // "_eca_token" sentinel of a select element offering "Defined by token",
    // and casting those to an integer would silently lose them just the same.
    // is_numeric() is deliberately not used: it accepts floats ("1.5"), and
    // truncating a float to an integer would silently corrupt the value.
    if (is_string($this->value) && $this->value !== '' && filter_var($this->value, FILTER_VALIDATE_INT) === FALSE) {
      return $this->value;
    }
    return (int) $this->value;
  }

}
