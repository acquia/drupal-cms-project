<?php

/**
 * @file
 * Contains post-update hooks for Automatic Updates Extensions.
 */

declare(strict_types=1);

/**
 * Implements hook_removed_post_updates().
 */
function automatic_updates_extensions_removed_post_updates(): array {
  return [
    'automatic_updates_extensions_post_update_rebuild_for_core_package_manager' => '4.1.0',
  ];
}
