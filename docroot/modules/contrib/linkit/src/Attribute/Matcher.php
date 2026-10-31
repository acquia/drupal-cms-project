<?php

declare(strict_types=1);

namespace Drupal\linkit\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a matcher attribute for plugin discovery.
 *
 * Plugin Namespace: Plugin\Linkit\Matcher.
 *
 * @see \Drupal\linkit\MatcherInterface
 * @see \Drupal\linkit\MatcherBase
 * @see \Drupal\linkit\MatcherManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Matcher extends Plugin {

  /**
   * Constructs a Matcher attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the matcher.
   * @param string $target_entity
   *   (optional) The target entity.
   * @param string $provider
   *   The module provider.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $target_entity = NULL,
    public ?string $provider = NULL,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
