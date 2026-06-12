<?php

/**
 * @file
 * Install, update, and uninstall functions for the Trash module.
 */

/**
 * Update the enabled entity types and bundles configuration.
 */
function trash_post_update_set_enabled_entity_types_bundles(): void {
  // This was moved to trash_update_9301.
  // @see https://www.drupal.org/project/trash/issues/3453832
}

/**
 * Add missing 'auto_purge' configuration.
 */
function trash_post_update_fix_missing_auto_purge(): void {
  $config = \Drupal::configFactory()->getEditable('trash.settings');
  if ($config->get('auto_purge') === NULL) {
    $config->set('auto_purge', [
      'enabled' => FALSE,
      'after' => '30 days',
    ]);
    $config->save(TRUE);
  }
}

/**
 * Rebuild the container to register trash handlers.
 */
function trash_post_update_add_trash_handlers(): void {
  // Empty update to trigger a container rebuild.
}

/**
 * Create action config entities for trash bulk operations.
 */
function trash_post_update_create_trash_actions(): void {
  $config = \Drupal::config('trash.settings');
  $enabled_entity_types = $config->get('enabled_entity_types') ?? [];

  if (empty($enabled_entity_types)) {
    return;
  }

  $entity_type_manager = \Drupal::entityTypeManager();
  $action_storage = $entity_type_manager->getStorage('action');

  foreach (array_keys($enabled_entity_types) as $entity_type_id) {
    $entity_type = $entity_type_manager->getDefinition($entity_type_id, FALSE);
    if (!$entity_type) {
      continue;
    }

    $singular_label = $entity_type->getSingularLabel();

    // Create restore action if it doesn't exist.
    $restore_action_id = "{$entity_type_id}_restore_action";
    if (!$action_storage->load($restore_action_id)) {
      $action_storage->create([
        'id' => $restore_action_id,
        'label' => "Restore $singular_label from trash",
        'type' => $entity_type_id,
        'plugin' => "entity:restore_action:{$entity_type_id}",
        'configuration' => [],
      ])->save();
    }

    // Create purge action if it doesn't exist.
    $purge_action_id = "{$entity_type_id}_purge_action";
    if (!$action_storage->load($purge_action_id)) {
      $action_storage->create([
        'id' => $purge_action_id,
        'label' => "Permanently delete $singular_label",
        'type' => $entity_type_id,
        'plugin' => "entity:purge_action:{$entity_type_id}",
        'configuration' => [],
      ])->save();
    }
  }
}
