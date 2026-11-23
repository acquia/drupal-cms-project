<?php

declare(strict_types=1);

namespace Drupal\google_tag\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Attribute class for Google tag event plugins.
 *
 * @see \Drupal\google_tag\Annotation\GoogleTagEvent
 * @see \Drupal\google_tag\GoogleTagEventManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class GoogleTagEvent extends Plugin {

  /**
   * Constructs a GoogleTagEvent attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The plugin label.
   * @param string|null $event_name
   *   (optional) The event name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A short description of the event.
   * @param string|null $dependency
   *   (optional) A module dependency, if any.
   * @param array $context_definitions
   *   (optional) An array of context definitions describing the context used
   *   by the plugin, keyed by context names.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $event_name = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $dependency = NULL,
    public readonly array $context_definitions = [],
    public readonly ?string $deriver = NULL,
  ) {}

}
