<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * An event listener that makes exported config generic (recipe-ready).
 *
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class GenericConfigurationListener implements ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * @todo Remove when https://www.drupal.org/i/1503146 is released.
   */
  public bool $convertFrontPagePathToAlias = FALSE;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AliasManagerInterface $aliasManager,
  ) {}

  public function __invoke(StorageTransformEvent $event): void {
    $storage = $event->getStorage();
    foreach ($storage->listAll() as $name) {
      $data = $storage->read($name);

      unset($data['_core']);
      if ($this->shouldDeleteUuid($name)) {
        unset($data['uuid']);
      }
      // @todo Remove when https://www.drupal.org/i/1503146 is released.
      if ($name === 'system.site' && $this->convertFrontPagePathToAlias) {
        $data['page']['front'] = '/' . ltrim($this->aliasManager->getAliasByPath($data['page']['front'], $data['langcode'] ?? NULL), '/');
      }
      $storage->write($name, $data);
    }
  }

  /**
   * Determines if the UUID should be deleted from a config object.
   *
   * @param string $name
   *   The name of the config being exported.
   *
   * @return bool
   *   TRUE if the UUID should be removed, FALSE otherwise.
   */
  private function shouldDeleteUuid(string $name): bool {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type instanceof ConfigEntityTypeInterface && str_starts_with($name, $entity_type->getConfigPrefix() . '.')) {
        // All config entities have a `uuid` key. We want to keep it if it also
        // serves as the entity ID; for example, Canvas folders do this.
        // @see core.data_types.schema.yml
        return $entity_type->getKey('id') !== 'uuid';
      }
    }
    return TRUE;
  }

}
