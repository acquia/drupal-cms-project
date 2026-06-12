<?php

declare(strict_types=1);

namespace Drupal\ui_icons_ckeditor5\Plugin\CKEditor5Plugin;

use Drupal\Core\Url;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;

/**
 * Icon settings for UI Icons.
 */
class IconPlugin extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $filterFormat = $editor->getFilterFormat();
    if (NULL === $filterFormat) {
      return [];
    }

    // This plugin is only loaded when icon_embed is enabled.
    assert($filterFormat->filters()->has('icon_embed'));

    $dynamic_plugin_config = $static_plugin_config;
    $dynamic_plugin_config['icon']['dialogURL'] = Url::fromRoute('ui_icons_ckeditor5.icon_dialog')
      ->setRouteParameter('filter_format', $filterFormat->id())
      ->toString(TRUE)
      ->getGeneratedUrl();
    return $dynamic_plugin_config;
  }

}
