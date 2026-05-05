<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\path_alias\AliasManagerInterface;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\trash\Exception\UnrestorableEntityException;

/**
 * Tests the AliasRepository service class overrides.
 *
 * @group trash
 */
class TrashAliasRepositoryTest extends TrashKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'path_alias',
  ];

  /**
   * The alias repository service.
   */
  protected AliasRepositoryInterface $aliasRepository;

  /**
   * The alias manager service.
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');

    // Enable trash for path_alias entity type.
    $this->enableEntityTypesForTrash(['path_alias']);

    // Get services after container rebuild.
    $this->aliasRepository = $this->container->get('path_alias.repository');
    $this->aliasManager = $this->container->get('path_alias.manager');
  }

  /**
   * Tests that deleting and restoring aliases affects repository lookups.
   */
  public function testDeleteAndRestoreLifecycle(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');

    // Create a path alias.
    $alias = PathAlias::create([
      'path' => '/node/1',
      'alias' => '/test-alias',
      'langcode' => 'en',
    ]);
    $alias->save();
    $alias_id = $alias->id();

    // Verify the alias can be found.
    $result = $this->aliasRepository->lookupByAlias('/test-alias', 'en');
    $this->assertNotNull($result, 'Alias found before deletion.');
    $this->assertEquals('/node/1', $result['path']);

    // Soft delete the alias.
    $alias->delete();

    // Verify the alias is soft deleted by querying directly.
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $alias_id)
      ->condition('deleted', NULL, 'IS NOT NULL')
      ->execute();
    $this->assertNotEmpty($query, 'Alias has been soft deleted.');

    // Verify the deleted alias cannot be found via repository.
    $result = $this->aliasRepository->lookupByAlias('/test-alias', 'en');
    $this->assertNull($result, 'Deleted alias is not found by repository.');

    // Also test lookupBySystemPath.
    $result = $this->aliasRepository->lookupBySystemPath('/node/1', 'en');
    $this->assertNull($result, 'Deleted alias is not found by system path.');

    // Restore the alias (use the same object we deleted).
    $storage->restoreFromTrash([$alias]);

    // Verify the alias is restored.
    $alias = $storage->load($alias_id);
    $this->assertTrue($alias->get('deleted')->isEmpty(), 'Alias has no deleted timestamp after restore.');

    // Verify it's now accessible via repository.
    $result = $this->aliasRepository->lookupByAlias('/test-alias', 'en');
    $this->assertNotNull($result, 'Restored alias is accessible.');
    $this->assertEquals('/node/1', $result['path']);
  }

  /**
   * Tests that repository methods properly exclude deleted aliases.
   */
  public function testRepositoryMethodsExcludeDeleted(): void {
    // Create multiple aliases.
    $alias1 = PathAlias::create([
      'path' => '/node/10',
      'alias' => '/page-ten',
      'langcode' => 'en',
    ]);
    $alias1->save();

    $alias2 = PathAlias::create([
      'path' => '/node/11',
      'alias' => '/page-eleven',
      'langcode' => 'en',
    ]);
    $alias2->save();

    $alias3 = PathAlias::create([
      'path' => '/admin/config',
      'alias' => '/configuration',
      'langcode' => 'en',
    ]);
    $alias3->save();

    // Test preloadPathAlias - should find both node aliases.
    $preloaded = $this->aliasRepository->preloadPathAlias(['/node/10', '/node/11'], 'en');
    $this->assertCount(2, $preloaded, 'Both aliases found in preload.');

    // Test pathHasMatchingAlias - should find admin alias.
    $this->assertTrue(
      $this->aliasRepository->pathHasMatchingAlias('/admin'),
      'Path has matching alias before deletion.'
    );

    // Delete two aliases.
    $alias1->delete();
    $alias3->delete();

    // Test preloadPathAlias - should now only find one.
    $preloaded = $this->aliasRepository->preloadPathAlias(['/node/10', '/node/11'], 'en');
    $this->assertCount(1, $preloaded, 'Only non-deleted alias found in preload.');
    $this->assertArrayHasKey('/node/11', $preloaded);
    $this->assertArrayNotHasKey('/node/10', $preloaded);

    // Test pathHasMatchingAlias - should no longer find admin alias.
    $this->assertFalse(
      $this->aliasRepository->pathHasMatchingAlias('/admin'),
      'Path has no matching alias after deletion.'
    );
  }

  /**
   * Tests that the repository works when trash is not enabled for path_alias.
   */
  public function testRepositoryWithTrashDisabled(): void {
    // Disable trash for path_alias.
    $this->disableEntityTypesForTrash(['path_alias', 'trash_test_entity', 'node']);

    // Get services after container rebuild.
    $this->aliasRepository = $this->container->get('path_alias.repository');

    // Create an alias.
    $alias = PathAlias::create([
      'path' => '/node/20',
      'alias' => '/test-no-trash',
      'langcode' => 'en',
    ]);
    $alias->save();

    // Verify the alias can be found.
    $result = $this->aliasRepository->lookupByAlias('/test-no-trash', 'en');
    $this->assertNotNull($result, 'Alias found with trash disabled.');

    // Delete the alias (will be hard deleted since trash is disabled).
    $alias->delete();

    // Try to reload - should be null since it was hard deleted.
    $this->assertEmpty(PathAlias::load($alias->id()), 'Alias was hard deleted when trash is disabled.');
  }

  /**
   * Tests cache clearing on delete and restore operations.
   */
  public function testCacheClearingOnOperations(): void {
    // Create a path alias.
    $alias = PathAlias::create([
      'path' => '/node/30',
      'alias' => '/test-cache',
      'langcode' => 'en',
    ]);
    $alias->save();

    // Prime the cache by looking up the alias.
    $path = $this->aliasManager->getPathByAlias('/test-cache', 'en');
    $this->assertEquals('/node/30', $path);

    // Delete the alias.
    $alias->delete();

    // The cache should be cleared, so lookup should return the alias itself.
    $path = $this->aliasManager->getPathByAlias('/test-cache', 'en');
    $this->assertEquals('/test-cache', $path, 'Deleted alias not found after cache clear.');

    // Restore the alias.
    $this->restoreEntity('path_alias', $alias->id());

    // The cache should be cleared again, so lookup should find it.
    $path = $this->aliasManager->getPathByAlias('/test-cache', 'en');
    $this->assertEquals('/node/30', $path, 'Restored alias found after cache clear.');
  }

  /**
   * Tests that deleted aliases don't conflict with new ones.
   */
  public function testDeletedAliasesAllowReuse(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('path_alias');

    // Create a path alias.
    $alias = PathAlias::create([
      'path' => '/node/40',
      'alias' => '/test-uuid',
      'langcode' => 'en',
    ]);
    $alias->save();

    // Verify original lookup works.
    $result = $this->aliasRepository->lookupByAlias('/test-uuid', 'en');
    $this->assertNotNull($result, 'Alias found before deletion.');

    // Delete the alias (soft delete).
    $alias->delete();

    // Verify the alias is no longer found via repository.
    $result = $this->aliasRepository->lookupByAlias('/test-uuid', 'en');
    $this->assertNull($result, 'Deleted alias not found via repository.');

    // Verify we can create a new alias with the same values.
    $new_alias = PathAlias::create([
      'path' => '/node/40',
      'alias' => '/test-uuid',
      'langcode' => 'en',
    ]);
    $new_alias->save();
    $this->assertNotNull($new_alias->id(), 'New alias with same values was created successfully.');

    // Verify the new alias is found.
    $result = $this->aliasRepository->lookupByAlias('/test-uuid', 'en');
    $this->assertNotNull($result, 'New alias found via repository.');
    $this->assertEquals('/node/40', $result['path']);

    // Try to restore the original alias - should fail due to conflict.
    $this->expectException(UnrestorableEntityException::class);
    $this->expectExceptionMessage('Cannot restore path alias: An alias with the path "/test-uuid" already exists.');
    $storage->restoreFromTrash([$alias]);
  }

}
