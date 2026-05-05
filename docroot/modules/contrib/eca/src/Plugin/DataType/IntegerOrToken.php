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
    if (str_starts_with($this->value, '[') && str_ends_with($this->value, ']')) {
      return (string) $this->value;
    }
    return (int) $this->value;
  }

}
