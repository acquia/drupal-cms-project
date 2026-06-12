<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\system\Entity\Menu;

/**
 * Tests menu_link_content integration with the Trash module.
 *
 * @group trash
 */
class TrashMenuLinkTest extends TrashKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
  ];

  /**
   * The menu link content storage.
   */
  protected EntityStorageInterface $menuLinkStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('menu_link_content');

    Menu::create([
      'id' => 'test-menu',
      'label' => 'Test menu',
    ])->save();

    $this->enableEntityTypesForTrash(['menu_link_content']);
    $this->menuLinkStorage = $this->getEntityTypeManager()->getStorage('menu_link_content');
  }

  /**
   * Creates a menu link in the test menu.
   */
  protected function createMenuLink(string $title, string $uri = 'internal:/', ?string $parent_uuid = NULL, string $menu_name = 'test-menu'): MenuLinkContent {
    $values = [
      'title' => $title,
      'link' => [['uri' => $uri]],
      'menu_name' => $menu_name,
    ];
    if ($parent_uuid !== NULL) {
      $values['parent'] = 'menu_link_content:' . $parent_uuid;
    }
    $link = MenuLinkContent::create($values);
    $link->save();
    return $link;
  }

  /**
   * Loads the menu tree for the test menu.
   */
  protected function loadMenuTree(string $menu_name): array {
    return \Drupal::menuTree()->load($menu_name, new MenuTreeParameters());
  }

  /**
   * Builds the menu render array with access checking, like SystemMenuBlock.
   *
   * @return array
   *   The menu render array with #cache metadata.
   */
  protected function buildMenu(string $menu_name): array {
    $menu_tree = \Drupal::menuTree();
    $tree = $this->loadMenuTree($menu_name);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menu_tree->transform($tree, $manipulators);
    return $menu_tree->build($tree);
  }

  /**
   * Tests that a menu link can be soft-deleted and restored.
   */
  public function testMenuLinkDeleteAndRestore(): void {
    $link = $this->createMenuLink('Test Link', 'internal:/user/login');
    $link->set('weight', 5);
    $link->set('expanded', TRUE);
    $link->set('enabled', TRUE);
    $link->save();
    $link_id = $link->id();
    $plugin_id = $link->getPluginId();

    // Verify the link exists and is in the menu tree.
    $this->assertNotEmpty(MenuLinkContent::load($link_id));
    $this->assertArrayHasKey($plugin_id, $this->loadMenuTree('test-menu'));

    // Trash the link.
    $link->delete();

    // Verify the link is not loadable and is removed from the menu tree.
    $this->assertEmpty(MenuLinkContent::load($link_id));
    $this->assertArrayNotHasKey($plugin_id, $this->loadMenuTree('test-menu'));

    // Verify the link is accessible in trash with a deleted timestamp.
    $deleted_link = $this->loadTrashedEntity('menu_link_content', $link_id);
    $this->assertNotEmpty($deleted_link);
    $this->assertNotEmpty($deleted_link->get('deleted')->value);

    // Restore the link and verify it is back in the menu tree with all its
    // properties preserved.
    $this->restoreEntity('menu_link_content', $link_id);
    $restored_link = MenuLinkContent::load($link_id);
    $this->assertNotEmpty($restored_link);
    $this->assertEmpty($restored_link->get('deleted')->value);
    $this->assertEquals('Test Link', $restored_link->getTitle());
    $this->assertEquals('internal:/user/login', $restored_link->get('link')->getValue()[0]['uri']);
    $this->assertEquals('test-menu', $restored_link->getMenuName());
    $this->assertEquals(5, $restored_link->getWeight());
    $this->assertTrue($restored_link->isExpanded());
    $this->assertTrue($restored_link->isEnabled());
    $this->assertArrayHasKey($plugin_id, $this->loadMenuTree('test-menu'));
  }

  /**
   * Tests that trashing a node cascades to its menu links.
   */
  public function testNodeTrashCascadesToMenuLinks(): void {
    $node_storage = $this->getEntityTypeManager()->getStorage('node');

    // Trashing and restoring a node without menu links should not cause errors.
    $node_without_links = $this->createNode(['type' => 'article']);
    $node_without_links->delete();
    $node_storage->restoreFromTrash([$node_without_links]);
    $this->assertNotEmpty(Node::load($node_without_links->id()));

    // Create a second menu so we can test links in different menus.
    Menu::create(['id' => 'other-menu', 'label' => 'Other menu'])->save();

    // Create two nodes: node1 with two menu links, node2 with one.
    $node1 = $this->createNode(['type' => 'article', 'title' => 'Node 1']);
    $link1a = $this->createMenuLink('Link 1a', 'entity:node/' . $node1->id());
    $link1a->set('weight', 5);
    $link1a->set('expanded', TRUE);
    $link1a->save();
    $link1b = $this->createMenuLink('Link 1b', 'entity:node/' . $node1->id(), NULL, 'other-menu');

    $node2 = $this->createNode(['type' => 'article', 'title' => 'Node 2']);
    $link2 = $this->createMenuLink('Link 2', 'entity:node/' . $node2->id());

    // Trash both nodes in one call so they share the same deletion timestamp.
    $node_storage->delete([$node1, $node2]);

    // All three menu links should be cascade-trashed.
    $this->assertEmpty(MenuLinkContent::load($link1a->id()));
    $this->assertEmpty(MenuLinkContent::load($link1b->id()));
    $this->assertEmpty(MenuLinkContent::load($link2->id()));

    // Verify timestamps match the node's.
    $deleted_node1 = $this->loadTrashedEntity('node', $node1->id());
    $deleted_link1a = $this->loadTrashedEntity('menu_link_content', $link1a->id());
    $deleted_link1b = $this->loadTrashedEntity('menu_link_content', $link1b->id());
    $this->assertEquals($deleted_node1->get('deleted')->value, $deleted_link1a->get('deleted')->value);
    $this->assertEquals($deleted_node1->get('deleted')->value, $deleted_link1b->get('deleted')->value);

    // Verify links are gone from the menu tree.
    $this->assertArrayNotHasKey($link1a->getPluginId(), $this->loadMenuTree('test-menu'));
    $this->assertArrayNotHasKey($link1b->getPluginId(), $this->loadMenuTree('other-menu'));

    // The menu block render cache should not be poisoned with max-age=0.
    $this->assertNotEquals(0, $this->buildMenu('test-menu')['#cache']['max-age']);
    $this->assertNotEquals(0, $this->buildMenu('other-menu')['#cache']['max-age']);

    // Restore only node1.
    $node_storage->restoreFromTrash([$node1]);

    // Node1's links should be restored with properties intact.
    $restored_link1a = MenuLinkContent::load($link1a->id());
    $restored_link1b = MenuLinkContent::load($link1b->id());
    $this->assertNotEmpty($restored_link1a);
    $this->assertNotEmpty($restored_link1b);
    $this->assertNull($restored_link1a->get('deleted')->value);
    $this->assertNull($restored_link1b->get('deleted')->value);
    $this->assertEquals('Link 1a', $restored_link1a->getTitle());
    $this->assertEquals(5, $restored_link1a->getWeight());
    $this->assertTrue($restored_link1a->isExpanded());
    $this->assertEquals('test-menu', $restored_link1a->getMenuName());
    $this->assertEquals('other-menu', $restored_link1b->getMenuName());

    // Both links should be back in the menu tree.
    $this->assertArrayHasKey($restored_link1a->getPluginId(), $this->loadMenuTree('test-menu'));
    $this->assertArrayHasKey($restored_link1b->getPluginId(), $this->loadMenuTree('other-menu'));

    // Node2's link should still be trashed.
    $this->assertEmpty(MenuLinkContent::load($link2->id()));
    $this->assertNotEmpty($this->loadTrashedEntity('menu_link_content', $link2->id()));

    // Purging node2 should also purge its cascade-trashed menu link.
    $this->purgeEntity('node', $node2->id());
    $this->assertEmpty($this->loadTrashedEntity('node', $node2->id()));
    $this->assertEmpty($this->loadTrashedEntity('menu_link_content', $link2->id()));
  }

  /**
   * Tests that cascade is skipped when menu_link_content is not trashable.
   */
  public function testMenuLinkContentDisabledSkipsIntegration(): void {
    $this->disableEntityTypesForTrash(['menu_link_content']);

    $node = $this->createNode(['type' => 'article']);
    $link = $this->createMenuLink('Link', 'entity:node/' . $node->id());

    $node->delete();

    // The node is trashed but the menu link remains active.
    $this->assertEmpty(Node::load($node->id()));
    $this->assertNotEmpty(MenuLinkContent::load($link->id()));

    // Without the integration, the orphaned menu link poisons the render cache
    // with max-age=0 because the access check cannot load the trashed node.
    $this->assertEquals(0, $this->buildMenu('test-menu')['#cache']['max-age']);
  }

  /**
   * Tests hierarchy handling when menu links are trashed and restored.
   *
   * Covers moving children to a new parent on trash, verifying that restoring
   * a trashed link does not undo the move, and that trashing a top-level link
   * moves its children to the root.
   */
  public function testMenuLinkHierarchy(): void {
    // Create hierarchy:
    // - parent
    // -- child-1
    // --- grandchild-1
    // --- grandchild-2
    // -- child-2.
    $parent = $this->createMenuLink('Parent');
    $child1 = $this->createMenuLink('Child 1', 'internal:/', $parent->uuid());
    $grandchild1 = $this->createMenuLink('Grandchild 1', 'internal:/', $child1->uuid());
    $grandchild2 = $this->createMenuLink('Grandchild 2', 'internal:/', $child1->uuid());
    $child2 = $this->createMenuLink('Child 2', 'internal:/', $parent->uuid());

    // Trash child-1: both grandchildren should be moved to parent, and child-2
    // should be unaffected.
    $child1->delete();

    $grandchild1 = MenuLinkContent::load($grandchild1->id());
    $grandchild2 = MenuLinkContent::load($grandchild2->id());
    $child2 = MenuLinkContent::load($child2->id());
    $this->assertEquals('menu_link_content:' . $parent->uuid(), $grandchild1->getParentId());
    $this->assertEquals('menu_link_content:' . $parent->uuid(), $grandchild2->getParentId());
    $this->assertEquals('menu_link_content:' . $parent->uuid(), $child2->getParentId());

    // The menu tree should reflect the updated hierarchy.
    $tree = $this->loadMenuTree('test-menu');
    $this->assertCount(3, $tree[$parent->getPluginId()]->subtree);

    // Purge (permanently delete) child-1.
    $this->purgeEntity('menu_link_content', $child1->id());

    // Verify child-1 is permanently gone.
    $this->assertEmpty($this->loadTrashedEntity('menu_link_content', $child1->id()));

    // Verify moved children are still intact after the purge.
    $grandchild1 = MenuLinkContent::load($grandchild1->id());
    $grandchild2 = MenuLinkContent::load($grandchild2->id());
    $this->assertEquals('menu_link_content:' . $parent->uuid(), $grandchild1->getParentId());
    $this->assertEquals('menu_link_content:' . $parent->uuid(), $grandchild2->getParentId());
    $tree = $this->loadMenuTree('test-menu');
    $this->assertCount(3, $tree[$parent->getPluginId()]->subtree);

    // Now test the restore flow with a fresh trashed link. Trash child-2: it
    // has no children, so no hierarchy changes occur.
    $child2->delete();

    // Restore child-2: it should come back under parent.
    $this->restoreEntity('menu_link_content', $child2->id());

    $restored_child2 = MenuLinkContent::load($child2->id());
    $this->assertNotEmpty($restored_child2);
    $this->assertEquals('menu_link_content:' . $parent->uuid(), $restored_child2->getParentId());

    // Parent now has 3 direct children (child-2, grandchild-1, grandchild-2).
    $tree = $this->loadMenuTree('test-menu');
    $this->assertCount(3, $tree[$parent->getPluginId()]->subtree);

    // Trash the top-level parent: all its children become root-level items.
    $parent->delete();

    $restored_child2 = MenuLinkContent::load($restored_child2->id());
    $this->assertEquals('', $restored_child2->getParentId());
    $grandchild1 = MenuLinkContent::load($grandchild1->id());
    $this->assertEquals('', $grandchild1->getParentId());

    $tree = $this->loadMenuTree('test-menu');
    $this->assertArrayNotHasKey($parent->getPluginId(), $tree);
    $this->assertArrayHasKey($restored_child2->getPluginId(), $tree);
    $this->assertArrayHasKey($grandchild1->getPluginId(), $tree);

    // Restore the parent: it comes back as a root-level item. Children that
    // were already moved to root stay there.
    $this->restoreEntity('menu_link_content', $parent->id());
    $restored_parent = MenuLinkContent::load($parent->id());
    $this->assertNotEmpty($restored_parent);
    $this->assertEquals('', $restored_parent->getParentId());

    $tree = $this->loadMenuTree('test-menu');
    $this->assertArrayHasKey($restored_parent->getPluginId(), $tree);
    $this->assertEmpty($tree[$restored_parent->getPluginId()]->subtree);
    $this->assertArrayHasKey($restored_child2->getPluginId(), $tree);
    $this->assertArrayHasKey($grandchild1->getPluginId(), $tree);
  }

}
