<?php

namespace Drupal\ConfigSyncWithoutSiteUuid;

use Drupal\Core\Config\ConfigDirectoryNotDefinedException;
use Drupal\Core\Site\Settings;

/**
 * Provides a factory for creating config file storage objects.
 */
class ConfigSyncStorageFactory {

  /**
   * Returns a FileStoragePlusDynamicSiteUuid object.
   *
   * @return FileStoragePlusDynamicSiteUuid
   *
   * @throws ConfigDirectoryNotDefinedException
   *   In case the sync directory does not exist or is not defined in
   *   $settings['config_sync_directory'].
   */
  public static function getSync() {
    $directory = Settings::get('config_sync_directory', FALSE);
    if ($directory === FALSE) {
      throw new ConfigDirectoryNotDefinedException('The config sync directory is not defined in $settings["config_sync_directory"]');
    }
    return new FileStoragePlusDynamicSiteUuid($directory);
  }

}
