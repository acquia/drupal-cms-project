<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_cms_helper\Plugin\Block\BrandingBlock;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class PluginHooks {

  use StringTranslationTrait;

  #[Hook('block_alter')]
  public function blockAlter(array &$definitions): void {
    // @todo Remove this when https://www.drupal.org/node/2852838 is released.
    $definitions['system_branding_block']['class'] = BrandingBlock::class;
  }

  /**
   * @todo Remove when https://www.drupal.org/i/3569875 is released.
   */
  #[Hook('menu_local_actions_alter')]
  public function alterLocalActions(array &$definitions): void {
    // Make bulk upload the default administrative experience for adding media.
    if (isset($definitions['media_library_bulk_upload.list'], $definitions['media.add'])) {
      $definitions['media_library_bulk_upload.list']['title'] = $definitions['media.add']['title'];
      // Make the original action appear nowhere, but don't unset it entirely
      // in case other code needs to alter it.
      $definitions['media.add']['appears_on'] = [];
    }
  }

  /**
   * @todo Remove when Drupal 11.4 is released.
   */
  #[Hook(
    'menu_links_discovered_alter',
    order: new OrderAfter(['navigation']),
  )]
  public function alterDiscoveredMenuLinks(array &$definitions): void {
    $disable = [
      'navigation.create.user',
      'navigation.content.media_type.image',
      'navigation.content.media_type.document',
    ];
    foreach ($disable as $id) {
      if (isset($definitions[$id])) {
        $definitions[$id]['enabled'] = FALSE;
      }
    }
  }
}
