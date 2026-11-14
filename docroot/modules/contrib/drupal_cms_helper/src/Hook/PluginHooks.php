<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_cms_helper\Plugin\Block\BrandingBlock;
use Drupal\drupal_cms_helper\Plugin\ConfigAction\PageVariantEntityCreate;

/**
 * Implements plugin alter hooks for Drupal CMS.
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class PluginHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Replaces the system branding block with a custom version.
   *
   * @phpstan-param array<string, array<mixed>> $definitions
   */
  #[Hook('block_alter')]
  public function blockAlter(array &$definitions): void {
    // @todo Remove this when https://www.drupal.org/node/2852838 is released.
    $definitions['system_branding_block']['class'] = BrandingBlock::class;
  }

  /**
   * Makes bulk upload the default action for adding media.
   *
   * @phpstan-param array<string, array<mixed>> $definitions
   *
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
   * Implements hook_config_action_alter().
   *
   * @phpstan-param array<string, array<mixed>> $definitions
   */
  #[Hook('config_action_alter')]
  public function alterConfigActions(array &$definitions): void {
    if ($this->moduleHandler->moduleExists('canvas')) {
      $definitions['entity_create:create']['class'] = $definitions['entity_create:createIfNotExists']['class'] = PageVariantEntityCreate::class;
    }
  }

}
