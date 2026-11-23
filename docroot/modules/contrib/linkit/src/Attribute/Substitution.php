<?php

declare(strict_types=1);

namespace Drupal\linkit\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a substitution attribute for plugin discovery.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Substitution extends Plugin {

  /**
   * Constructs a Substitution attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the substitution.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
