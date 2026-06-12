<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\trash\TrashManagerInterface;

/**
 * Base class for Trash kernel tests.
 */
abstract class TrashKernelTestBase extends KernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'node',
    'text',
    'trash',
    'trash_test',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('trash_test_entity');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['node', 'filter', 'trash_test']);

    $this->createContentType(['type' => 'article']);
    $this->createContentType(['type' => 'page']);

    $this->enableEntityTypesForTrash([
      'trash_test_entity' => [],
      'node' => ['article'],
    ]);
  }

  /**
   * Gets the trash manager.
   */
  protected function getTrashManager(): TrashManagerInterface {
    return $this->container->get('trash.manager');
  }

  /**
   * Gets the entity type manager.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->container->get('entity_type.manager');
  }

  /**
   * Gets the entity repository.
   */
  protected function getEntityRepository(): EntityRepositoryInterface {
    return $this->container->get('entity.repository');
  }

  /**
   * Enables entity types for trash.
   *
   * @param array $entity_types
   *   An array keyed by entity type ID, with values being an array of bundle
   *   names to enable, or an empty array to enable all bundles. If an array key
   *   is numeric, its value is expected to be an entity type ID for which all
   *   bundles will be enabled. For example:
   *   @code
   *   ['node' => ['article', 'page'], 'path_alias' => [], 'redirect']
   *   @endcode
   */
  protected function enableEntityTypesForTrash(array $entity_types): void {
    $config = \Drupal::configFactory()->getEditable('trash.settings');
    $enabled_entity_types = $config->get('enabled_entity_types');
    foreach ($entity_types as $entity_type_id => $bundles) {
      // Support simple arrays like ['path_alias', 'redirect'].
      if (is_int($entity_type_id)) {
        $entity_type_id = $bundles;
        $bundles = [];
      }
      $enabled_entity_types[$entity_type_id] = $bundles;
    }
    $config->set('enabled_entity_types', $enabled_entity_types);
    $config->save();

    // Rebuild the container so trash handlers are available.
    $this->container->get('kernel')->rebuildContainer();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Disables entity types for trash.
   *
   * @param array $entity_type_ids
   *   An array of entity type IDs to disable.
   */
  protected function disableEntityTypesForTrash(array $entity_type_ids): void {
    $config = \Drupal::configFactory()->getEditable('trash.settings');
    $enabled_entity_types = $config->get('enabled_entity_types');
    foreach ($entity_type_ids as $entity_type_id) {
      unset($enabled_entity_types[$entity_type_id]);
    }
    $config->set('enabled_entity_types', $enabled_entity_types);
    $config->save();

    // Rebuild the container.
    $this->container->get('kernel')->rebuildContainer();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Loads a trashed entity by ID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The loaded entity, or NULL if not found.
   */
  protected function loadTrashedEntity(string $entity_type_id, int|string $entity_id): ?ContentEntityInterface {
    return $this->getTrashManager()->executeInTrashContext('ignore', fn () =>
      $this->getEntityTypeManager()->getStorage($entity_type_id)->load($entity_id)
    );
  }

  /**
   * Restores a trashed entity.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $entity_id
   *   The entity ID.
   */
  protected function restoreEntity(string $entity_type_id, int|string $entity_id): void {
    $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_type_id, $entity_id): void {
      if ($entity = $this->getEntityTypeManager()->getStorage($entity_type_id)->load($entity_id)) {
        trash_restore_entity($entity);
      }
    });
  }

  /**
   * Permanently deletes a trashed entity.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $entity_id
   *   The entity ID.
   */
  protected function purgeEntity(string $entity_type_id, int|string $entity_id): void {
    $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_type_id, $entity_id): void {
      if ($entity = $this->getEntityTypeManager()->getStorage($entity_type_id)->load($entity_id)) {
        $this->getEntityTypeManager()->getStorage($entity_type_id)->delete([$entity]);
      }
    });
  }

}
