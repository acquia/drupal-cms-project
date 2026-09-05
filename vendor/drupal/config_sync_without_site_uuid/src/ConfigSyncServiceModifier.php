<?php

namespace Drupal\ConfigSyncWithoutSiteUuid;

use Drupal\Core\Config\FileStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Overrides the 'config.storage.sync' service.
 */
class ConfigSyncServiceModifier implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_name = 'config.storage.sync';
    if ($container->has($service_name) && ($container->getDefinition($service_name)->getFactory() === [FileStorageFactory::class, 'getSync'])) {
      $container->getDefinition($service_name)->setFactory([ConfigSyncStorageFactory::class, 'getSync']);
    }
  }

}
