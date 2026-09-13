<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\trash_test\Entity\TrashTestEntity;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Views;

/**
 * Tests views integration for Trash.
 *
 * @group trash
 */
class ViewQueryTest extends TrashKernelTestBase {

  use ViewResultAssertionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views'];

  /**
   * {@inheritdoc}
   */
  protected bool $installNode = FALSE;

  /**
   * Tests that deleted entities are excluded from views results.
   */
  public function testQueryWithoutDeletedAccess(): void {
    $entities = [];

    for ($i = 0; $i < 5; $i++) {
      $entity = TrashTestEntity::create();
      $entity->save();
      $entities[] = $entity;
    }

    // Test whether they appear in the view.
    $view = Views::getView('trash_test_view');
    $view->execute('page_1');
    $this->assertIdenticalResultset($view, [
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
      ['id' => 4],
      ['id' => 5],
    ], ['id' => 'id']);
    $view->destroy();

    // Delete the first three of them. They should no longer appear in the view.
    for ($i = 0; $i < 3; $i++) {
      $entities[$i]->delete();
    }

    $view = Views::getView('trash_test_view');
    $view->execute('page_1');
    $this->assertIdenticalResultset($view, [
      ['id' => 4],
      ['id' => 5],
    ], ['id' => 'id']);
    $view->destroy();
  }

  /**
   * Tests that trash states are properly restored for simple views queries.
   */
  public function testQueryTrashContext(): void {
    foreach (self::queryTrashContextCases() as $name => $case) {
      [
        $display_id,
        $expected_positions,
        $initial_trash_context,
        $pre_execute_trash_context,
        $execute_trash_context,
        $render,
      ] = $case;

      // Create five entities, deleting the first three. IDs are recorded in
      // creation order so the expected positions can be mapped to the actual
      // IDs, which keep incrementing across loop iterations.
      $ids = [];
      for ($i = 0; $i < 5; $i++) {
        $entity = TrashTestEntity::create();
        $entity->save();
        $ids[] = (int) $entity->id();
        if ($i < 3) {
          $entity->delete();
        }
      }
      $expected = array_map(static fn (int $position): array => ['id' => $ids[$position - 1]], $expected_positions);

      $trash_manager = $this->getTrashManager();
      // Specify the trash context before the view is executed or rendered.
      $trash_manager->setTrashContext($initial_trash_context);
      static::assertEquals($initial_trash_context, $trash_manager->getTrashContext(), $name);

      $view = Views::getView('trash_test_view');
      assert($view !== NULL);
      if ($render) {
        // Execute and render the view.
        $view->render($display_id);
      }
      else {
        $view->execute($display_id);
      }
      $this->assertIdenticalResultset($view, $expected, ['id' => 'id'], $name);
      // Check the trash context being used during execution.
      // Before execution, the current trash context stays the same.
      static::assertEquals($pre_execute_trash_context, \Drupal::keyValue('trash_test')->get('views_pre_execute.trash_context'), $name);
      static::assertEquals($execute_trash_context, \Drupal::keyValue('trash_test')->get('views_post_execute.trash_context'), $name);
      if ($render) {
        static::assertEquals($execute_trash_context, \Drupal::keyValue('trash_test')->get('views_pre_render.trash_context'), $name);
        static::assertEquals($execute_trash_context, \Drupal::keyValue('trash_test')->get('views_post_render.trash_context'), $name);
      }
      else {
        static::assertNull(\Drupal::keyValue('trash_test')->get('views_pre_render.trash_context'), $name);
        static::assertNull(\Drupal::keyValue('trash_test')->get('views_post_render.trash_context'), $name);
      }
      // Assert that the original trash context is the same after the view has
      // finished executing.
      static::assertEquals($initial_trash_context, $trash_manager->getTrashContext(), $name);
      $view->destroy();

      // Reset state for the next case: clear the recorded trash contexts, purge
      // all entities (including trashed ones), and restore the default context.
      \Drupal::keyValue('trash_test')->deleteAll();
      $trash_manager->executeInTrashContext('ignore', function (): void {
        $storage = $this->getEntityTypeManager()->getStorage('trash_test_entity');
        if ($entities = $storage->loadMultiple()) {
          $storage->delete($entities);
        }
      });
      $trash_manager->setTrashContext('active');
    }
  }

  /**
   * Tests that deleted entities are excluded from views results.
   */
  public function testQueryWithDeletedAccess(): void {
    $entities = [];

    for ($i = 0; $i < 5; $i++) {
      $entity = TrashTestEntity::create();
      $entity->save();
      $entities[] = $entity;
    }

    // Test whether they appear in the view.
    $view = Views::getView('trash_test_view');
    $view->execute('page_1');
    $this->assertIdenticalResultset($view, [
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
      ['id' => 4],
      ['id' => 5],
    ], ['id' => 'id']);
    $view->destroy();

    // Delete the first three of them. They should all be individual loadable
    // but no longer accessible via the view.
    for ($i = 0; $i < 3; $i++) {
      $entities[$i]->delete();
    }

    // Only the entities that were not deleted will be visible.
    $view = Views::getView('trash_test_view');
    $view->execute('page_1');
    $this->assertIdenticalResultset($view, [
      ['id' => 4],
      ['id' => 5],
    ], ['id' => 'id']);
    $view->destroy();

    // The default filter will only pick up deleted entities.
    $view = Views::getView('trash_test_view');
    $view->execute('page_2');
    $this->assertIdenticalResultset($view, [
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
    ], ['id' => 'id']);
    $view->destroy();

    // A 'not empty' (IS NOT NULL) filter on deleted also shows only trashed
    // entities. This requires 'allow empty' in the filter definition, which
    // viewsDataAlter() ensures is present.
    $view = Views::getView('trash_test_view');
    $view->execute('page_3');
    $this->assertIdenticalResultset($view, [
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
    ], ['id' => 'id']);
    $view->destroy();
  }

  /**
   * Tests that entities referencing a deleted entity are excluded from views.
   */
  public function testRelationshipToDeletedEntity(): void {
    // Create three entities: A, B, C.
    $entityA = TrashTestEntity::create();
    $entityA->save();

    $entityB = TrashTestEntity::create(['reference' => $entityA->id()]);
    $entityB->save();

    $entityC = TrashTestEntity::create(['reference' => $entityA->id()]);
    $entityC->save();

    // The view should return the list of entities that reference the entity
    // passed in. Confirm that when we pass in entityA, we get back
    // entityB and entityC.
    $view = Views::getView('trash_test_view_relationship');
    $view->setArguments([$entityA->id()]);
    $view->execute('default');
    $this->assertIdenticalResultset($view, [
      ['trash_test_trash_test_id' => 2],
      ['trash_test_trash_test_id' => 3],
    ], ['trash_test_field_data_trash_test_field_data_id' => 'trash_test_trash_test_id']);
    $view->destroy();
    // Re-enable trash. Executing the view disabled it, and the post render hook
    // that re-enables it automatically isn't executed due to the way we're
    // executing the view.
    $this->getTrashManager()->setTrashContext('active');

    // Now move EntityB to the trash.
    $entityB->delete();

    // The same view should no longer include entityB in its result set since
    // it's in the trash.
    $view = Views::getView('trash_test_view_relationship');
    $view->setArguments([$entityA->id()]);
    $view->execute('default');
    $this->assertIdenticalResultset($view, [
      ['trash_test_trash_test_id' => 3],
    ], ['trash_test_field_data_trash_test_field_data_id' => 'trash_test_trash_test_id']);
    $view->destroy();
    $this->getTrashManager()->setTrashContext('active');
  }

  /**
   * Provides views test cases under various trash contexts.
   *
   * Expected results are creation-order positions (1-5) of the five entities
   * created per case; the first three are trashed. testQueryTrashContext()
   * maps these positions to the actual entity IDs.
   */
  protected static function queryTrashContextCases(): array {
    $non_trashed = [4, 5];
    $trashed = [1, 2, 3];
    $all = [1, 2, 3, 4, 5];

    return [
      // Page 1 keeps the default trash context being used as the view doesn't
      // interact with the 'delete' filter.
      'page_1 with "inactive" context' => ['page_1', $non_trashed, 'inactive', 'inactive', 'inactive', FALSE],
      'page_1 with "active" context' => ['page_1', $non_trashed, 'active', 'active', 'active', FALSE],
      // The trash behavior is ignored, all entities should be returned.
      'page_1 with "ignore" context' => ['page_1', $all, 'ignore', 'ignore', 'ignore', FALSE],
      'page_1 with "inactive" context render' => ['page_1', $non_trashed, 'inactive', 'inactive', 'inactive', TRUE],
      'page_1 with "active" context render' => ['page_1', $non_trashed, 'active', 'active', 'active', TRUE],
      // The trash behavior is ignored, all entities should be returned.
      'page_1 with "ignore" context render' => ['page_1', $all, 'ignore', 'ignore', 'ignore', TRUE],

      'page_2 with "inactive" context' => ['page_2', $trashed, 'inactive', 'ignore', 'ignore', FALSE],
      'page_2 with "active" context' => ['page_2', $trashed, 'active', 'ignore', 'ignore', FALSE],
      // The trash behavior is ignored, however the filter is still taking
      // effect.
      'page_2 with "ignore" context' => ['page_2', $trashed, 'ignore', 'ignore', 'ignore', FALSE],
      'page_2 with "inactive" context render' => ['page_2', $trashed, 'inactive', 'ignore', 'ignore', TRUE],
      'page_2 with "active" context render' => ['page_2', $trashed, 'active', 'ignore', 'ignore', TRUE],
      // The trash behavior is ignored, however the filter is still taking
      // effect.
      'page_2 with "ignore" context render' => ['page_2', $trashed, 'ignore', 'ignore', 'ignore', TRUE],

      // Page 3 uses a 'not empty' (IS NOT NULL) filter on deleted. It behaves
      // the same as page 2: trash context is set to 'ignore' during execution,
      // and only deleted entities are returned.
      'page_3 with "inactive" context' => ['page_3', $trashed, 'inactive', 'ignore', 'ignore', FALSE],
      'page_3 with "active" context' => ['page_3', $trashed, 'active', 'ignore', 'ignore', FALSE],
      'page_3 with "ignore" context' => ['page_3', $trashed, 'ignore', 'ignore', 'ignore', FALSE],
      'page_3 with "inactive" context render' => ['page_3', $trashed, 'inactive', 'ignore', 'ignore', TRUE],
      'page_3 with "active" context render' => ['page_3', $trashed, 'active', 'ignore', 'ignore', TRUE],
      'page_3 with "ignore" context render' => ['page_3', $trashed, 'ignore', 'ignore', 'ignore', TRUE],
    ];
  }

}
