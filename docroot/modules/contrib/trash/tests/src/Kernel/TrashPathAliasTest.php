<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests path_alias integration in DefaultTrashHandler.
 *
 * @group trash
 */
class TrashPathAliasTest extends TrashKernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'path_alias',
  ];

  /**
   * The node storage.
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');

    // Enable path_alias for trash.
    $this->enableEntityTypesForTrash(['path_alias']);

    $this->nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
  }

  /**
   * Creates a node with a path alias.
   */
  protected function createNodeWithPathAlias(array $node_values = [], ?string $alias = NULL): array {
    $node_values += [
      'type' => 'article',
      'title' => 'Test Node',
      'status' => 1,
    ];
    $node = $this->createNode($node_values);

    $alias = $alias ?: '/test-node-' . $node->id();
    $path_alias = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => $alias,
      'langcode' => $node->language()->getId(),
    ]);
    $path_alias->save();

    return [$node, $path_alias];
  }

  /**
   * Tests that deleting and restoring a node also affects its path alias.
   */
  public function testNodeDeleteAndRestoreWithPathAlias(): void {
    $node = $this->createNode(['type' => 'article', 'title' => 'Test Node']);
    $node_id = $node->id();
    $node_path = '/node/' . $node_id;

    // Create multiple aliases for the same node.
    $alias1 = PathAlias::create([
      'path' => $node_path,
      'alias' => '/first-alias',
      'langcode' => 'en',
    ]);
    $alias1->save();
    $alias1_id = $alias1->id();

    $alias2 = PathAlias::create([
      'path' => $node_path,
      'alias' => '/second-alias',
      'langcode' => 'en',
    ]);
    $alias2->save();
    $alias2_id = $alias2->id();

    // Verify all entities exist initially.
    $this->assertNotEmpty(Node::load($node_id));
    $this->assertNotEmpty(PathAlias::load($alias1_id));
    $this->assertNotEmpty(PathAlias::load($alias2_id));

    // Delete the node.
    $node->delete();

    // Verify all are deleted in active context.
    $this->assertEmpty(Node::load($node_id));
    $this->assertEmpty(PathAlias::load($alias1_id));
    $this->assertEmpty(PathAlias::load($alias2_id));

    // Verify all are accessible in ignore context and have same timestamp.
    $deleted_node = $this->loadTrashedEntity('node', $node_id);
    $deleted_alias1 = $this->loadTrashedEntity('path_alias', $alias1_id);
    $deleted_alias2 = $this->loadTrashedEntity('path_alias', $alias2_id);

    $this->assertNotEmpty($deleted_node);
    $this->assertNotEmpty($deleted_alias1);
    $this->assertNotEmpty($deleted_alias2);
    $this->assertEquals($deleted_node->get('deleted')->value, $deleted_alias1->get('deleted')->value);
    $this->assertEquals($deleted_node->get('deleted')->value, $deleted_alias2->get('deleted')->value);

    // Restore the node using the original entity object.
    $this->nodeStorage->restoreFromTrash([$node]);

    // Verify all are restored.
    $restored_node = Node::load($node_id);
    $restored_alias1 = PathAlias::load($alias1_id);
    $restored_alias2 = PathAlias::load($alias2_id);

    $this->assertNotEmpty($restored_node);
    $this->assertNotEmpty($restored_alias1);
    $this->assertNotEmpty($restored_alias2);
    $this->assertNull($restored_node->get('deleted')->value);
    $this->assertNull($restored_alias1->get('deleted')->value);
    $this->assertNull($restored_alias2->get('deleted')->value);
    $this->assertEquals('/first-alias', $restored_alias1->getAlias());
    $this->assertEquals('/second-alias', $restored_alias2->getAlias());
  }

  /**
   * Tests restoring a node when its path alias conflicts with an existing one.
   */
  public function testNodeRestoreWithConflictingPathAlias(): void {
    [$node1, $path_alias1] = $this->createNodeWithPathAlias([], '/test');
    $node1_id = $node1->id();
    $alias1_id = $path_alias1->id();

    // Trash the first node (also trashes its path alias).
    $node1->delete();

    // Create a second node with the same alias.
    $this->createNodeWithPathAlias([], '/test');

    // Attempt to restore the first node.
    try {
      $this->nodeStorage->restoreFromTrash([$node1]);
      $this->fail('Expected exception was not thrown.');
    }
    catch (\Exception $e) {
      $this->assertEquals('Cannot restore path alias: An alias with the path "/test" already exists.', $e->getMessage());
    }

    // The node should not be restored if the path alias could not be restored.
    $this->assertEmpty(Node::load($node1_id));
    $this->assertEmpty(PathAlias::load($alias1_id));
  }

  /**
   * Tests that path alias integration is skipped when not enabled.
   */
  public function testPathAliasDisabledSkipsIntegration(): void {
    // Disable path_alias for trash.
    $this->disableEntityTypesForTrash(['path_alias']);

    [$node, $path_alias] = $this->createNodeWithPathAlias();

    // Delete the node.
    $node->delete();

    // Verify that the node is deleted but the alias remains active.
    $this->assertEmpty(Node::load($node->id()));
    $this->assertNotEmpty(PathAlias::load($path_alias->id()));
  }

  /**
   * Tests handling of nodes without path aliases.
   */
  public function testNoPathAliasesNoErrors(): void {
    $node = $this->createNode(['type' => 'article', 'title' => 'No Alias Node']);

    // Verify no errors when deleting node without aliases.
    $node->delete();
    $this->assertEmpty(Node::load($node->id()));

    // Verify no errors when restoring node without aliases.
    $this->restoreEntity('node', $node->id());
    $this->assertNotEmpty(Node::load($node->id()));
  }

  /**
   * Tests that only aliases matching the entity's path are restored.
   */
  public function testPathFilteringOnRestore(): void {
    $nodes = $aliases = [];
    // Create ten nodes so that we can test that restoring the alias for
    // /node/1 does not restore the alias for /node/10.
    for ($i = 1; $i <= 10; $i++) {
      $nodes[$i] = $this->createNode(['type' => 'article', 'title' => 'Node ' . $i]);
      $aliases[$i] = PathAlias::create([
        'path' => '/' . $nodes[$i]->toUrl()->getInternalPath(),
        'alias' => '/filtered-alias-' . $i,
        'langcode' => 'en',
      ]);
      $aliases[$i]->save();
    }

    $node1 = $nodes[1];
    $node10 = $nodes[10];
    $alias1 = $aliases[1];
    $alias10 = $aliases[10];

    // Delete all nodes to get the same deletion timestamp.
    $this->entityTypeManager->getStorage('node')->delete($nodes);

    // Verify both nodes and aliases are deleted.
    $this->assertEmpty(Node::load($node1->id()));
    $this->assertEmpty(Node::load($node10->id()));
    $this->assertEmpty(PathAlias::load($alias1->id()));
    $this->assertEmpty(PathAlias::load($alias10->id()));

    // Restore only node1.
    $this->nodeStorage->restoreFromTrash([$node1]);

    // Verify that only node1 and its alias are restored.
    $this->assertNotEmpty(Node::load($node1->id()));
    $this->assertNotEmpty(PathAlias::load($alias1->id()));

    // Node2 and its alias should still be deleted.
    $this->assertEmpty(Node::load($node10->id()));
    $this->assertEmpty(PathAlias::load($alias10->id()));
  }

}
