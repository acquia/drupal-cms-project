<?php

namespace Drupal\ConfigSyncWithoutSiteUuid;

use Drupal\Core\Config\FileStorage;

/**
 * Defines the file storage.
 */
class FileStoragePlusDynamicSiteUuid extends FileStorage {

  /**
   * @inheritDoc
   */
  public function read($name) {
    $data = parent::read($name);
    if ($name === 'system.site') {
      $data['uuid'] = \Drupal::config('system.site')->get('uuid') ?: \Drupal::service('uuid')->generate();
    }
    return $data;
  }

}
