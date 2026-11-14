<?php

namespace Drupal\Tests\entity_reference_revisions\Kernel;

use Drupal\entity_reference_revisions\Plugin\QueueWorker\OrphanPurger;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that only composites are queued and processed.
 *
 * @group entity_reference_revisions
 */
#[RunTestsInSeparateProcesses]
#[Group('entity_reference_revisions')]
class EntityReferenceRevisionsOrphanQueueTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'field',
    'entity_reference_revisions',
  ];

  /**
   * The orphan purger service.
   *
   * @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsOrphanPurger
   */
  protected $orphanPurger;

  /**
   * @var \Drupal\entity_reference_revisions\Plugin\QueueWorker\OrphanPurger
   */
  protected OrphanPurger $orphanPurgerWorker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'field']);

    $this->orphanPurger = $this->container->get('entity_reference_revisions.orphan_purger');
    $this->orphanPurgerWorker = new OrphanPurger([], 'entity_reference_revisions_orphan_purger', [], $this->entityTypeManager, $this->orphanPurger, $this->container->get('database'));

    $this->setupContentType();
  }

  /**
   * Test that deleting a parent revision doesn't trigger fatals during cron run.
   */
  public function testQueueProcessing() {
    $child = Node::create([
      'type' => 'revisionable',
      'title' => 'Child',
    ]);
    $child->save();

    $parent = Node::create([
      'type' => 'revisionable',
      'title' => 'Test parent node',
      'field_composite_entity' => $child,
    ]);
    $parent->save();
    $parent_rev_1_id = $parent->getRevisionId();

    // Verify initial state: 1 revision each.
    $this->assertRevisionCount(1, 'node', $child->id());
    $this->assertRevisionCount(1, 'node', $parent->id());

    // Create Node revision 2 that references the current A.
    $parent->setNewRevision(TRUE);
    $parent->set('field_composite_entity', $child);
    $parent->set('title', 'Test parent node - rev 2');
    $parent->save();

    $this->assertRevisionCount(1, 'node', $child->id());
    $this->assertRevisionCount(2, 'node', $parent->id());

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    // Delete parent revision 1.
    $nodeStorage->deleteRevision($parent_rev_1_id);
    $this->assertNull($nodeStorage->loadRevision($parent_rev_1_id));

    $queue = $this->container->get('queue')->get('entity_reference_revisions_orphan_purger');
    $this->assertEquals(0, $queue->numberOfItems(), 'Child node has not been added to queue');
  }



  /**
   * Sets up the content type with entity reference revisions fields.
   */
  protected function setupContentType(): void {
    // Create a revisionable content type.
    $node_type = NodeType::create([
      'type' => 'revisionable',
      'name' => 'Revisionable',
      'new_revision' => TRUE,
    ]);
    $node_type->save();

    // Create the entity reference revisions field on nodes.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_composite_entity',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'revisionable',
      'label' => 'Composite Entity',
    ]);
    $field->save();
  }


  /**
   * Asserts the revision count of a certain entity.
   *
   * @param int $expected
   *   The expected count.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID.
   * @param string $message
   *   Optional assertion message.
   */
  protected function assertRevisionCount(int $expected, string $entity_type_id, int $entity_id, string $message = ''): void {
    $id_field = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('id');
    $revision_count = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->getQuery()
      ->condition($id_field, $entity_id)
      ->allRevisions()
      ->count()
      ->accessCheck(FALSE)
      ->execute();

    if (empty($message)) {
      $message = "Expected $expected revisions for $entity_type_id:$entity_id, found $revision_count.";
    }

    $this->assertEquals($expected, $revision_count, $message);
  }


}
