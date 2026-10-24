<?php

declare(strict_types=1);

namespace Drupal\ui_icons;

use Drupal\Core\Theme\Icon\IconDefinitionInterface;

/**
 * Handle an Icon preview.
 */
class IconPreview {

  public const SPINNER_ICON = '/core/themes/claro/images/icons/003ecc/spinner.svg';
  private const ICON_DEFAULT_SIZE = 48;

  /**
   * {@inheritdoc}
   *
   * @todo do not work with an icon from getIcon to avoid too much cache.
   */

  /**
   * Render icon preview.
   *
   * @param \Drupal\Core\Theme\Icon\IconDefinitionInterface $icon
   *   The icon object to use.
   * @param array $settings
   *   The icon settings to pass.
   *
   * @return array
   *   The icon renderable.
   */
  public static function getPreview(IconDefinitionInterface $icon, array $settings = []): array {
    if ($preview = $icon->getData('preview')) {
      $context = [
        'label' => $icon->getId(),
        'icon_id' => $icon->getIconId(),
        'pack_id' => $icon->getPackId(),
        'extractor' => $icon->getData('extractor'),
        'source' => $icon->getSource(),
        'content' => $icon->getData('content'),
        // Size is mandatory for preview.
        'size' => $settings['size'] ?? self::ICON_DEFAULT_SIZE,
      ];
      $context = array_merge($settings, $context);
      $renderable = [
        '#type' => 'inline_template',
        '#template' => $preview,
        '#context' => $context,
      ];
      if ($library = $icon->getLibrary()) {
        $renderable['#attached'] = ['library' => [$library]];
      }

      return $renderable;
    }

    // Fallback to template based preview.
    $renderable = [
      '#theme' => 'icon_preview',
      '#icon_label' => $icon->getId(),
      '#icon_id' => $icon->getIconId(),
      '#pack_id' => $icon->getPackId(),
      '#extractor' => $icon->getData('extractor'),
      '#source' => $icon->getSource(),
      '#library' => $icon->getLibrary(),
      '#settings' => $settings,
    ];

    return $renderable;
  }

}
