<?php

namespace Drupal\Tests\trash\Kernel;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests trash functionality for taxonomy terms.
 *
 * @group trash
 */
class TrashTaxonomyTermTest extends TrashKernelTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
  ];

  /**
   * The test vocabulary.
   */
  protected VocabularyInterface $vocabulary;

  /**
   * The taxonomy term storage.
   */
  protected TermStorageInterface $termStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy']);
    $this->enableEntityTypesForTrash(['taxonomy_term']);

    $this->vocabulary = $this->createVocabulary();
    $this->termStorage = $this->getEntityTypeManager()->getStorage('taxonomy_term');
  }

  /**
   * Tests single-parent hierarchy trash and restore cascading.
   */
  public function testSingleParentHierarchy(): void {
    $parent = $this->createTerm($this->vocabulary, ['name' => 'Parent']);
    $child1 = $this->createTerm($this->vocabulary, ['name' => 'Child 1', 'parent' => $parent->id()]);
    $child2 = $this->createTerm($this->vocabulary, ['name' => 'Child 2', 'parent' => $parent->id()]);

    // Trashing parent cascade-trashes children.
    $parent->delete();
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $parent->id())));
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $child1->id())));
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $child2->id())));

    // Restoring parent cascade-restores children.
    $this->restoreEntity('taxonomy_term', $parent->id());
    $this->assertNotEmpty(Term::load($parent->id()));
    $this->assertNotEmpty(Term::load($child1->id()));
    $this->assertNotEmpty(Term::load($child2->id()));

    // A child can be restored independently while parent remains trashed. It
    // should appear in the taxonomy tree (moved to root).
    $parent->delete();
    $this->restoreEntity('taxonomy_term', $child1->id());
    $this->assertFalse(trash_entity_is_deleted(Term::load($child1->id())));
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $parent->id())));
    $tree_tids = array_column($this->termStorage->loadTree($this->vocabulary->id()), 'tid');
    $this->assertContains($child1->id(), $tree_tids);
  }

  /**
   * Tests multi-parent hierarchy cascade-trash behavior.
   *
   * A child stays active when one parent is trashed, but gets cascade-trashed
   * when all parents are gone.
   */
  public function testMultiParentHierarchy(): void {
    $parent1 = $this->createTerm($this->vocabulary, ['name' => 'Parent 1']);
    $parent2 = $this->createTerm($this->vocabulary, ['name' => 'Parent 2']);
    $child = $this->createTerm($this->vocabulary, ['name' => 'Child', 'parent' => [$parent1->id(), $parent2->id()]]);

    // Trashing one parent keeps the child active.
    $parent1->delete();
    $this->assertFalse(trash_entity_is_deleted(Term::load($child->id())));

    // Trashing the last parent orphans and cascade-trashes the child.
    $parent2->delete();
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $child->id())));
  }

  /**
   * Tests deep hierarchy cascade trash/restore and hierarchy preservation.
   */
  public function testDeepHierarchy(): void {
    $grandparent = $this->createTerm($this->vocabulary, ['name' => 'Grandparent']);
    $parent = $this->createTerm($this->vocabulary, ['name' => 'Parent', 'parent' => $grandparent->id()]);
    $child = $this->createTerm($this->vocabulary, ['name' => 'Child', 'parent' => $parent->id()]);

    $grandparent->delete();
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $grandparent->id())));
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $parent->id())));
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $child->id())));

    $this->restoreEntity('taxonomy_term', $grandparent->id());
    $this->assertNotEmpty(Term::load($grandparent->id()));
    $this->assertNotEmpty(Term::load($parent->id()));
    $this->assertNotEmpty(Term::load($child->id()));
    $this->assertContains($grandparent->id(), $this->getParentIds(Term::load($parent->id())));
    $this->assertContains($parent->id(), $this->getParentIds(Term::load($child->id())));

    // All terms should be visible in the taxonomy tree.
    $tree_tids = array_column($this->termStorage->loadTree($this->vocabulary->id()), 'tid');
    $this->assertContains($grandparent->id(), $tree_tids);
    $this->assertContains($parent->id(), $tree_tids);
    $this->assertContains($child->id(), $tree_tids);
  }

  /**
   * Tests that restore only affects children with a matching deleted timestamp.
   *
   * An independently trashed child (different timestamp) should not be restored
   * when its parent is restored. In a multi-parent scenario, restoring the
   * "wrong" parent should not restore the child either.
   */
  public function testRestoreTimestampScoping(): void {
    // Independently trashed child: different timestamp, not restored.
    $parent = $this->createTerm($this->vocabulary, ['name' => 'Parent']);
    $child = $this->createTerm($this->vocabulary, ['name' => 'Child', 'parent' => $parent->id()]);

    $child->delete();
    \Drupal::keyValue('trash_test')->set('time_offset', 100);
    $parent->delete();

    $this->restoreEntity('taxonomy_term', $parent->id());
    $this->assertNotEmpty(Term::load($parent->id()));
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $child->id())));

    // Multi-parent partial restore: child cascade-trashed with B's timestamp,
    // restoring A should not restore it, restoring B should.
    \Drupal::keyValue('trash_test')->set('time_offset', 0);
    $parent_a = $this->createTerm($this->vocabulary, ['name' => 'Parent A']);
    $parent_b = $this->createTerm($this->vocabulary, ['name' => 'Parent B']);
    $child2 = $this->createTerm($this->vocabulary, ['name' => 'Child 2', 'parent' => [$parent_a->id(), $parent_b->id()]]);

    $parent_a->delete();
    \Drupal::keyValue('trash_test')->set('time_offset', 200);
    $parent_b->delete();

    $this->restoreEntity('taxonomy_term', $parent_a->id());
    $this->assertEmpty(Term::load($child2->id()));

    $this->restoreEntity('taxonomy_term', $parent_b->id());
    $this->assertNotEmpty(Term::load($child2->id()));
  }

  /**
   * Tests that trashed terms are excluded from queries and tree operations.
   *
   * Covers entity queries in active and ignore context, and direct database
   * queries tagged with 'taxonomy_term_access' (e.g. TermStorage::loadTree()).
   */
  public function testQueryFiltering(): void {
    $active = $this->createTerm($this->vocabulary, ['name' => 'Active']);
    $trashed = $this->createTerm($this->vocabulary, ['name' => 'Trashed']);

    $trashed->delete();

    // Entity query in active context.
    $result = $this->termStorage->getQuery()
      ->condition('vid', $this->vocabulary->id())
      ->accessCheck(FALSE)
      ->execute();
    $this->assertContains($active->id(), $result);
    $this->assertNotContains($trashed->id(), $result);

    // Entity query in ignore context.
    $result = $this->getTrashManager()->executeInTrashContext('ignore', fn () =>
      $this->termStorage->getQuery()
        ->condition('vid', $this->vocabulary->id())
        ->accessCheck(FALSE)
        ->execute()
    );
    $this->assertContains($trashed->id(), $result);

    // Taxonomy tree (direct database query with 'taxonomy_term_access' tag).
    $tree_tids = array_column($this->termStorage->loadTree($this->vocabulary->id()), 'tid');
    $this->assertContains($active->id(), $tree_tids);
    $this->assertNotContains($trashed->id(), $tree_tids);
  }

  /**
   * Tests purge for single terms, hierarchies, and multi-parent children.
   */
  public function testPurge(): void {
    // Single term: trash, restore, re-trash, purge.
    $term = $this->createTerm($this->vocabulary, ['name' => 'Term']);
    $term->delete();
    $this->assertTrue(trash_entity_is_deleted($this->loadTrashedEntity('taxonomy_term', $term->id())));
    $this->restoreEntity('taxonomy_term', $term->id());
    $this->assertFalse(trash_entity_is_deleted(Term::load($term->id())));
    Term::load($term->id())->delete();
    $this->purgeEntity('taxonomy_term', $term->id());
    $this->assertEmpty($this->loadTrashedEntity('taxonomy_term', $term->id()));

    // Parent with children: purge removes all.
    $parent = $this->createTerm($this->vocabulary, ['name' => 'Parent']);
    $child1 = $this->createTerm($this->vocabulary, ['name' => 'Child 1', 'parent' => $parent->id()]);
    $child2 = $this->createTerm($this->vocabulary, ['name' => 'Child 2', 'parent' => $parent->id()]);
    $parent->delete();
    $this->purgeEntity('taxonomy_term', $parent->id());
    $this->assertEmpty($this->loadTrashedEntity('taxonomy_term', $parent->id()));
    $this->assertEmpty($this->loadTrashedEntity('taxonomy_term', $child1->id()));
    $this->assertEmpty($this->loadTrashedEntity('taxonomy_term', $child2->id()));

    // Multi-parent child: purging one parent keeps the child.
    $p1 = $this->createTerm($this->vocabulary, ['name' => 'P1']);
    $p2 = $this->createTerm($this->vocabulary, ['name' => 'P2']);
    $shared = $this->createTerm($this->vocabulary, ['name' => 'Shared', 'parent' => [$p1->id(), $p2->id()]]);
    $p1->delete();
    $this->purgeEntity('taxonomy_term', $p1->id());
    $this->assertEmpty($this->loadTrashedEntity('taxonomy_term', $p1->id()));
    $this->assertNotEmpty(Term::load($shared->id()));
  }

  /**
   * Gets the parent term IDs from a term's parent field.
   */
  protected function getParentIds(Term $term): array {
    return array_column($term->get('parent')->getValue(), 'target_id');
  }

}
