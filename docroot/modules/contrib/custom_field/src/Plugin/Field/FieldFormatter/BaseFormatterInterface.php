<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;

/**
 * Defines an interface for custom field base formatter.
 */
interface BaseFormatterInterface {

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   * @param string $langcode
   *   The language code.
   *
   * @return array<string|int, mixed>
   *   The render array of the field and subfields.
   */
  public function viewValue(FieldItemInterface $item, string $langcode): array;

}
