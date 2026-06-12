<?php

declare(strict_types=1);

namespace Drupal\ui_icons\Template;

use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\ui_icons\IconPreview;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for UI Icons preview.
 */
class IconPreviewTwigExtension extends AbstractExtension {

  public function __construct(
    private readonly IconPackManagerInterface $pluginManagerIconPack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('icon_preview', $this->getIconPreview(...)),
    ];
  }

  /**
   * Get an icon preview.
   *
   * @param string $pack_id
   *   The icon set ID.
   * @param string $icon_id
   *   The icon ID.
   * @param array $settings
   *   An array of settings for the icon.
   *
   * @return array
   *   The icon renderable.
   */
  public function getIconPreview(string $pack_id, string $icon_id, ?array $settings = []): array {
    $icon_full_id = IconDefinition::createIconId($pack_id, $icon_id);
    $icon = $this->pluginManagerIconPack->getIcon($icon_full_id);
    if (!$icon) {
      return [];
    }

    return IconPreview::getPreview($icon, $settings ?? ['size' => 32]);
  }

}
