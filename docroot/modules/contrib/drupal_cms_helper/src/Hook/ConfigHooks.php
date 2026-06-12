<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final readonly class ConfigHooks {

  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    // @todo Remove when https://www.drupal.org/i/3569887 is released.
    if (isset($definitions['block.settings.project_browser_block:*'])) {
      $definitions['block.settings.project_browser_block:*']['mapping']['default_sort']['constraints']['NotBlank']['allowNull'] = TRUE;
    }
  }

}
