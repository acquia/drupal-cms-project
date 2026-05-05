<?php

/**
 * @file
 * Post-update hooks for the Project Browser module.
 */

declare(strict_types=1);

/**
 * Clears the cache so that Project Browser's OO hooks are registered.
 */
function project_browser_post_update_rebuild_container_for_oo_hooks(): void {
  // No need to do anything; the container will be rebuilt.
}

/**
 * Updates Project Browser settings to support source-specific configuration.
 */
function project_browser_post_update_convert_enabled_sources_to_arrays(): void {
  $config = \Drupal::configFactory()
    ->getEditable('project_browser.admin_settings');

  $enabled_sources = [];
  foreach ($config->get('enabled_sources') as $source_id) {
    $enabled_sources[$source_id] = [];
  }
  $config->set('enabled_sources', $enabled_sources)->save();
}

/**
 * Removes the `allowed_projects` config setting.
 */
function project_browser_post_update_remove_allowed_projects(): void {
  \Drupal::configFactory()
    ->getEditable('project_browser.admin_settings')
    ->clear('allowed_projects')
    ->save();
}
