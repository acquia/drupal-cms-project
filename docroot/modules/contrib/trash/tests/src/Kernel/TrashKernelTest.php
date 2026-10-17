<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\trash_test\Entity\TrashTestEntity;
use Drupal\user\Entity\User;

/**
 * Tests basic trash functionality.
 *
 * @group trash
 */
class TrashKernelTest extends TrashKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'image',
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
  }

  /**
   * Test trashing entities.
   */
  public function testDeletion(): void {
    $entity = TrashTestEntity::create();
    assert($entity instanceof ContentEntityInterface);
    $entity->save();
    $entity_id = $entity->id();

    $this->assertNotEmpty(TrashTestEntity::load($entity_id));
    $this->assertFalse(trash_entity_is_deleted($entity));
    $this->assertTrue($entity->get('deleted')->isEmpty());
    $this->assertNull($entity->get('deleted')->value);

    $entity->delete();

    // Test the default 'active' trash context.
    $this->assertEmpty(TrashTestEntity::load($entity_id), 'Deleted entities can not be loaded in the default (active) trash context.');

    // Test the 'ignore' trash context.
    $entity = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_id) {
      return TrashTestEntity::load($entity_id);
    });
    assert($entity instanceof ContentEntityInterface);
    $this->assertNotEmpty($entity, 'Deleted entities can still be loaded in the "ignore" trash context.');
    $this->assertTrue(trash_entity_is_deleted($entity));
    $this->assertEquals(\Drupal::time()->getRequestTime(), $entity->get('deleted')->value);

    $second_entity = TrashTestEntity::create();
    $second_entity->save();
    $second_entity_id = $second_entity->id();

    // Test the default 'active' trash context for multiple load.
    $entities = TrashTestEntity::loadMultiple();
    $this->assertCount(1, $entities);
    $this->assertEquals($second_entity_id, $entities[$second_entity_id]->id());

    // Test the 'ignore' trash context  for multiple load.
    $entities = $this->getTrashManager()->executeInTrashContext('ignore', function () {
      return TrashTestEntity::loadMultiple();
    });
    $this->assertCount(2, $entities);
    $this->assertEquals($entity_id, $entities[$entity_id]->id());
    $this->assertEquals($second_entity_id, $entities[$second_entity_id]->id());
  }

  /**
   * Test prevention of stale entities in the caches.
   */
  public function testPersistentCache(): void {
    $live_node = $this->createNode(['type' => 'article']);
    $live_node->save();

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('node');
    $nid = $live_node->id();
    $loaded = $storage->load($nid);

    // Sanity checks that caches work.
    $this->assertNotEmpty($loaded);
    $this->assertSame(1, $storage->getLatestRevisionId($nid));
    $this->assertEquals(1, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));

    $loaded->delete();

    // Deleting an entity should reset the in-memory and persistent entity
    // caches per ids [and revisions in D11].
    $this->assertEmpty($storage->load($nid));
    $this->assertNull($storage->getLatestRevisionId($nid));
    // @todo This returns the pre-deleted revision, looks like a bug.
    $this->assertSame(1, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));

    // Deactivate the Trash context and fill the caches.
    $this->getTrashManager()->setTrashContext('inactive');
    $this->assertNotEmpty($storage->load($nid));
    $this->assertSame(2, $storage->getLatestRevisionId($nid));
    $this->assertSame(2, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));

    // Re-activate the caches and ensure the caches have been cleared.
    $this->getTrashManager()->setTrashContext('active');
    $this->assertEmpty($storage->load($nid));
    $this->assertNull($storage->getLatestRevisionId($nid));
    // @todo This returns the pre-deleted revision, looks like a bug.
    $this->assertSame(1, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));

    // Repeat the above with executeInTrashContext().
    $this->getTrashManager()->executeInTrashContext('inactive', function () use ($storage, $nid) {
      $this->assertNotEmpty($storage->load($nid));
      $this->assertSame(2, $storage->getLatestRevisionId($nid));
      $this->assertSame(
        2,
        $storage->getLatestTranslationAffectedRevisionId($nid, 'en')
      );
    });

    // Re-activate the caches and ensure the caches have been cleared.
    $this->assertEmpty($storage->load($nid));
    $this->assertNull($storage->getLatestRevisionId($nid));
    // @todo This returns the pre-deleted revision, looks like a bug.
    $this->assertSame(1, $storage->getLatestTranslationAffectedRevisionId($nid, 'en'));
  }

  /**
   * @covers ::trash_entity_is_deleted
   */
  public function testDisabledEntityType(): void {
    // Check a node bundle that's not trash-enabled.
    $nonDeletableNode = $this->createNode(['type' => 'page']);
    $nonDeletableNode->save();
    $this->assertFalse(trash_entity_is_deleted($nonDeletableNode));

    $nonDeletableNode->delete();
    $this->assertEmpty(Node::load($nonDeletableNode->id()));

    // Check an entity type that's not trash-enabled.
    $nonDeletableEntity = $this->createUser();
    $nonDeletableEntity->save();
    $this->assertFalse(trash_entity_is_deleted($nonDeletableEntity));

    $nonDeletableEntity->delete();
    $this->assertEmpty(User::load($nonDeletableEntity->id()));
  }

  /**
   * @covers \Drupal\trash\TrashManager::isEntityTypeEnabled
   *
   * @dataProvider providerIsEntityTypeEnabled
   */
  public function testIsEntityTypeEnabled($entity_type_id, $bundle, $enabled): void {
    $this->assertSame($enabled, $this->getTrashManager()->isEntityTypeEnabled($entity_type_id, $bundle));
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $this->assertSame($enabled, $this->getTrashManager()->isEntityTypeEnabled($entity_type, $bundle));
  }

  /**
   * Data provider for testIsEntityTypeEnabled().
   */
  public static function providerIsEntityTypeEnabled(): array {
    return [
      // An entity type with a single bundle enabled.
      ['node', NULL, TRUE],
      ['node', 'article', TRUE],
      ['node', 'page', FALSE],
      // An entity type with all bundles enabled.
      ['trash_test_entity', NULL, TRUE],
      ['trash_test_entity', 'random_bundle_id', TRUE],
      // A disabled entity type.
      ['media', NULL, FALSE],
      ['media', 'random_bundle_id', FALSE],
    ];
  }

  /**
   * Tests that the trash context is appropriately switched based on the user.
   */
  public function testTrashContextSwitching(): void {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = $this->container->get('account_switcher');
    $administer_trash_user = $this->createUser(['administer trash']);
    $access_trash_user = $this->createUser(['access trash']);
    $view_deleted_trash_user = $this->createUser(['view deleted entities']);
    $normal_user = $this->createUser();

    // The trash context should be 'active' by default.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The trash context should stay the same.
    $account_switcher->switchTo($administer_trash_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The trash context should stay the same.
    $account_switcher->switchTo($access_trash_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The trash context should stay the same.
    $account_switcher->switchTo($view_deleted_trash_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The trash context should be switched if the "in_trash" query string is
    // set.
    \Drupal::request()->query->set('in_trash', '1');
    $account_switcher->switchTo($administer_trash_user);
    static::assertEquals('ignore', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // The trash context should switch back to the 'active' state.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    $account_switcher->switchTo($access_trash_user);
    static::assertEquals('ignore', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // The trash context should switch back to the 'active' state.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    $account_switcher->switchTo($view_deleted_trash_user);
    static::assertEquals('ignore', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // The trash context should switch back to the 'active' state.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // The state should still be active as the user does not have the necessary
    // permissions.
    $account_switcher->switchTo($normal_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // Assert that if the trash context is switched to 'ignore' even if it was
    // previously 'inactive' as the "in_trash" query string takes precedence.
    $this->getTrashManager()->setTrashContext('inactive');
    $account_switcher->switchTo($administer_trash_user);
    static::assertEquals('ignore', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // The trash context should switch back to the 'active' state.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());

    // Assert that the trash context is now 'active' even if it was previously
    // 'inactive'.
    $this->getTrashManager()->setTrashContext('inactive');
    $account_switcher->switchTo($normal_user);
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
    $account_switcher->switchBack();
    // Should now be active.
    static::assertEquals('active', $this->getTrashManager()->getTrashContext());
  }

  /**
   * Tests that deleted entities are not stored in cache.
   */
  public function testDeletedEntitiesNotInCache(): void {
    $entity = TrashTestEntity::create();
    $entity->save();
    $entity_id = $entity->id();

    // Load the entity to ensure it gets cached.
    $storage = $this->getEntityTypeManager()->getStorage('trash_test_entity');
    $storage->resetCache();
    TrashTestEntity::load($entity_id);

    // Verify the entity is in the persistent cache.
    $persistent_cache = \Drupal::cache('entity');
    $cid = "values:trash_test_entity:$entity_id";
    $this->assertNotNull($persistent_cache->get($cid));

    // Verify the entity is in the memory cache.
    $memory_cache = \Drupal::service('entity.memory_cache');
    $this->assertNotNull($memory_cache->get($cid));

    // Delete the entity (soft-delete).
    $entity->delete();

    // The cache entries should be deleted after the entity is soft-deleted.
    $this->assertFalse($persistent_cache->get($cid));
    $this->assertFalse($memory_cache->get($cid));

    // Load the deleted entity in the 'ignore' trash context and verify caches
    // while still inside the context.
    $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_id, $persistent_cache, $memory_cache, $cid) {
      TrashTestEntity::load($entity_id);

      // Verify the deleted entity is not in the persistent cache.
      $this->assertFalse($persistent_cache->get($cid));

      // Verify the deleted entity is not in the memory cache.
      $this->assertFalse($memory_cache->get($cid));
    });
  }

  /**
   * Tests getting the latest revision ID while switching trash contexts.
   */
  public function testGetLatestRevisionId(): void {
    $storage = $this->getEntityTypeManager()->getStorage('trash_test_entity');
    assert($storage instanceof ContentEntityStorageInterface);

    // Create a test entity (revision 1).
    $entity = $storage->create(['label' => 'Test entity']);
    assert($entity instanceof ContentEntityInterface);
    $entity->save();
    $default_revision_id = $entity->getRevisionId();

    // Create a pending revision (revision 2).
    $entity->setNewRevision(TRUE);
    $entity->isDefaultRevision(FALSE);
    $entity->save();
    $pending_revision_id = $entity->getRevisionId();

    // Soft-delete the entity (revision 3).
    $storage->delete([$entity]);
    $deleted_revision_id = $entity->getRevisionId();

    // Verify we have 3 distinct revisions.
    $this->assertEquals([1, 2, 3], [$default_revision_id, $pending_revision_id, $deleted_revision_id]);

    // Verify the correct value in the 'active' context after deletion.
    $this->assertEquals('active', $this->getTrashManager()->getTrashContext());
    $this->assertNull($storage->getLatestRevisionId($entity->id()));

    // Switch to the 'ignore' context and call getLatestRevisionId().
    $this->getTrashManager()->setTrashContext('ignore');
    $this->assertEquals($deleted_revision_id, $storage->getLatestRevisionId($entity->id()));

    // Switch back to the 'active' context.
    $this->getTrashManager()->setTrashContext('active');
    $this->assertNull($storage->getLatestRevisionId($entity->id()));
  }

  /**
   * Tests that hook_modules_installed preserves existing enabled_entity_types.
   */
  public function testModulesInstalledPreservesConfig(): void {
    // TrashKernelTestBase::setUp() seeds enabled_entity_types.node with a
    // specific bundle list, mimicking a distribution/profile that ships a
    // pre-configured trash.settings. Firing hook_modules_installed (as
    // happens at the end of a profile install) must not overwrite that
    // bundle list with an empty array.
    $before = \Drupal::config('trash.settings')->get('enabled_entity_types');
    $this->assertSame(['article'], $before['node']);

    \Drupal::moduleHandler()->invokeAll('modules_installed', [['node'], FALSE]);

    $after = \Drupal::config('trash.settings')->get('enabled_entity_types');
    $this->assertSame(['article'], $after['node']);
  }

}
