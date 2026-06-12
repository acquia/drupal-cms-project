<?php

/**
 * @file
 * Post update hooks for easy_encryption module.
 */

declare(strict_types=1);

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Site\Settings;
use Drupal\key\KeyInterface;

/**
 * Migrate easy_encryption.keys config keys to the new schema naming.
 */
function easy_encryption_post_update_rename_keys_config_structure(array &$sandbox): void {
  $config = \Drupal::configFactory()->getEditable('easy_encryption.keys');
  $data = $config->getRawData();

  if ($data === []) {
    $sandbox['#finished'] = 1;
    return;
  }

  $changed = FALSE;

  // active_key_pair_id -> active_encryption_key_id.
  if (array_key_exists('active_key_pair_id', $data) && !array_key_exists('active_encryption_key', $data)) {
    $data['active_encryption_key_id'] = $data['active_key_pair_id'];
    unset($data['active_key_pair_id']);
    $changed = TRUE;
  }

  // key_pairs -> encryption_keys (and nested key_pair_id -> encryption_key_id)
  if (array_key_exists('key_pairs', $data) && !array_key_exists('encryption_keys', $data)) {
    $data['encryption_keys'] = $data['key_pairs'];
    unset($data['key_pairs']);
    $changed = TRUE;
  }

  if (isset($data['encryption_keys']) && is_array($data['encryption_keys'])) {
    foreach ($data['encryption_keys'] as $i => $item) {
      if (!is_array($item)) {
        continue;
      }
      if (array_key_exists('key_pair_id', $item) && !array_key_exists('encryption_key_id', $item)) {
        $data['encryption_keys'][$i]['encryption_key_id'] = $item['key_pair_id'];
        unset($data['encryption_keys'][$i]['key_pair_id']);
        $changed = TRUE;
      }
    }
  }

  if ($changed) {
    $config->setData($data)->save(TRUE);
  }

  $sandbox['#finished'] = 1;
}

/**
 * Rename easy_encrypted provider setting key_pair_id -> encryption_key_id.
 */
function easy_encryption_post_update_rename_key_pair_id_to_encryption_key_id(array &$sandbox): void {
  $storage = \Drupal::entityTypeManager()->getStorage('key');

  if (!isset($sandbox['ids'])) {
    // Find only Key entities using the easy_encrypted provider.
    $sandbox['ids'] = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('key_provider', 'easy_encrypted')
      ->execute();

    $sandbox['ids'] = array_values($sandbox['ids']);
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['current'] = 0;
    $sandbox['updated'] = 0;
    $sandbox['skipped'] = 0;
  }

  $limit = Settings::get('entity_update_batch_size', 50);
  $slice = array_slice($sandbox['ids'], $sandbox['current'], $limit);

  if ($slice === []) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\key\KeyInterface[] $keys */
  $keys = $storage->loadMultiple($slice);

  foreach ($keys as $key) {
    $changed = _easy_encryption_post_update_migrate_easy_encrypted_provider_settings($key, $storage);
    $sandbox['current']++;

    if ($changed) {
      $sandbox['updated']++;
    }
    else {
      $sandbox['skipped']++;
    }
  }

  $sandbox['#finished'] = ($sandbox['total'] > 0)
    ? ($sandbox['current'] / $sandbox['total'])
    : 1;
}

/**
 * Migrates a single Key entity's provider configuration.
 *
 * @return bool
 *   TRUE if the entity was changed and saved, FALSE otherwise.
 */
function _easy_encryption_post_update_migrate_easy_encrypted_provider_settings(KeyInterface $key, EntityStorageInterface $storage): bool {
  $config = $key->get('key_provider_settings');

  // Only migrate if the old key exists and the new one doesn't.
  if (!array_key_exists('key_pair_id', $config) || array_key_exists('encryption_key_id', $config)) {
    return FALSE;
  }

  $config['encryption_key_id'] = $config['key_pair_id'];
  unset($config['key_pair_id']);

  $key->set('key_provider_settings', $config);

  $storage->save($key);
  return TRUE;
}
