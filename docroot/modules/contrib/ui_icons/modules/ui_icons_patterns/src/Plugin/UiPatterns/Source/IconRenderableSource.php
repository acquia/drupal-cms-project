<?php

declare(strict_types=1);

namespace Drupal\ui_icons_patterns\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\ui_patterns\Attribute\Source;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'icon_renderable',
  label: new TranslatableMarkup('Icon'),
  description: new TranslatableMarkup('Render an icon from UI Icons module.'),
  prop_types: ['slot']
)]
class IconRenderableSource extends IconSource {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $value = $this->getSetting('value');
    if (!$value) {
      return [];
    }

    return IconDefinition::getRenderable($value['target_id'] ?? $value['icon_id'] ?? '', $value['settings'] ?? $value['icon_settings'] ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $value = $this->getSetting('value');
    if (!$value) {
      return [];
    }

    return [
      $value['target_id'] ?? $value['icon_id'] ?? '',
    ];
  }

}
