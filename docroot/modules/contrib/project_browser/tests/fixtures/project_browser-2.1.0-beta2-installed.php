<?php
// phpcs:ignoreFile
/**
 * @file
 * Extends a core database dump with Project Browser being installed.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$connection->insert('config')
->fields([
  'collection',
  'name',
  'data',
])
->values([
  'collection' => '',
  'name' => 'project_browser.admin_settings',
  'data' => 'a:5:{s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"QyMU4r3arwQQZoSVWKz-5imWdf-_dkvLYCDgQpOZQVM";}s:15:"enabled_sources";a:2:{i:0;s:17:"drupalorg_jsonapi";i:1;s:7:"recipes";}s:16:"allow_ui_install";b:0;s:16:"allowed_projects";a:0:{}s:14:"max_selections";N;}',
])
->execute();

// Add Project Browser to core.extension.
$data = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$data = unserialize($data);
$data['module']['project_browser'] = 0;
$connection->update('config')
  ->fields([
    'data' => serialize($data),
  ])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

$connection->insert('key_value')
->fields([
  'collection',
  'name',
  'value',
])
->values([
  'collection' => 'system.schema',
  'name' => 'project_browser',
  'value' => 'i:9019;',
])
->execute();
