<?php

/**
 * @file
 * Contains post-update hooks for Automatic Updates.
 *
 * DELETE THIS FILE FROM CORE MERGE REQUEST.
 */

declare(strict_types=1);

/**
 * Rebuilds the container to account for Package Manager moving to core.
 */
function automatic_updates_post_update_rebuild_for_core_package_manager(): void {
  // Intentionally empty to force a container rebuild.
}

/**
 * Rebuilds the container to sync with changes to routing and local tasks.
 */
function automatic_updates_post_update_rebuild_routes(): void {
  // Intentionally empty to force a container rebuild. This would not be
  // necessary on Drupal 11.3, but 11.2 is still supported.
}

/**
 * Implements hook_removed_post_updates().
 */
function automatic_updates_removed_post_updates(): array {
  return [
    'automatic_updates_post_update_create_status_check_mail_config' => '3.0.0',
  ];
}
