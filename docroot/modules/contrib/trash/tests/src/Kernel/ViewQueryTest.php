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
   *
   * @dataProvider providerQueryTrashContext
   */
  public function testQueryTrashContext(
    string $display_id,
    array $results,
    string $initial_trash_context,
    string $pre_execute_trash_context,
    string $execute_trash_context,
    bool $render,
  ): void {
    for ($i = 0; $i < 5; $i++) {
      $entity = TrashTestEntity::create();
      $entity->save();
      // Delete the first three of them.
      if ($i < 3) {
        $entity->delete();
      }
    }

    // Specify the trash context before the view is executed or rendered.
    $this->getTrashManager()->setTrashContext($initial_trash_context);

    static::assertEquals($initial_trash_context, $this->getTrashManager()->getTrashContext());
    $view = Views::getView('trash_test_view');
    assert($view !== NULL);
    if ($render) {
      // Execute and render the view.
      $view->render($display_id);
    }
    else {
      $view->execute($display_id);
    }
    $this->assertIdenticalResultset($view, $results, ['id' => 'id']);
    // Check the trash context being used during execution.
    // Before execution, the current trash context stays the same.
    static::assertEquals($pre_execute_trash_context, \Drupal::keyValue('trash_test')->get('views_pre_execute.trash_context'));
    static::assertEquals($execute_trash_context, \Drupal::keyValue('trash_test')->get('views_post_execute.trash_context'));
    if ($render) {
      static::assertEquals($execute_trash_context, \Drupal::keyValue('trash_test')->get('views_pre_render.trash_context'));
      static::assertEquals($execute_trash_context, \Drupal::keyValue('trash_test')->get('views_post_render.trash_context'));
    }
    else {
      static::assertEquals(NULL, \Drupal::keyValue('trash_test')->get('views_pre_render.trash_context'));
      static::assertEquals(NULL, \Drupal::keyValue('trash_test')->get('views_post_render.trash_context'));
    }
    // Assert that the original trash context is the same after the view has
    // finished executing.
    static::assertEquals($initial_trash_context, $this->getTrashManager()->getTrashContext());
    $view->destroy();
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
   * Provides views test data under various trash contexts.
   */
  public static function providerQueryTrashContext(): \Generator {
    $non_trashed_entity_ids = [
      ['id' => 4],
      ['id' => 5],
    ];
    $trashed_entity_ids = [
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
    ];
    $all_entity_ids = [
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
      ['id' => 4],
      ['id' => 5],
    ];

    // Page 1 will keep the default trash context being used as the view doesn't
    // interact with the 'delete' filter.
    yield 'page_1 with "inactive" context' => [
      'page_1', $non_trashed_entity_ids, 'inactive', 'inactive', 'inactive', FALSE,
    ];
    yield 'page_1 with "active" context' => ['page_1', $non_trashed_entity_ids, 'active', 'active', 'active', FALSE];
    // The trash behavior is ignored, all entities should be returned.
    yield 'page_1 with "ignore" context' => ['page_1', $all_entity_ids, 'ignore', 'ignore', 'ignore', FALSE];

    yield 'page_1 with "inactive" context render' => [
      'page_1', $non_trashed_entity_ids, 'inactive', 'inactive', 'inactive', TRUE,
    ];
    yield 'page_1 with "active" context render' => [
      'page_1', $non_trashed_entity_ids, 'active', 'active', 'active', TRUE,
    ];
    // The trash behavior is ignored, all entities should be returned.
    yield 'page_1 with "ignore" context render' => ['page_1', $all_entity_ids, 'ignore', 'ignore', 'ignore', TRUE];

    yield 'page_2 with "inactive" context' => ['page_2', $trashed_entity_ids, 'inactive', 'ignore', 'ignore', FALSE];
    yield 'page_2 with "active" context' => ['page_2', $trashed_entity_ids, 'active', 'ignore', 'ignore', FALSE];
    // The trash behavior is ignored, however the filter is still taking
    // effect.
    yield 'page_2 with "ignore" context' => ['page_2', $trashed_entity_ids, 'ignore', 'ignore', 'ignore', FALSE];

    yield 'page_2 with "inactive" context render' => [
      'page_2', $trashed_entity_ids, 'inactive', 'ignore', 'ignore', TRUE,
    ];
    yield 'page_2 with "active" context render' => ['page_2', $trashed_entity_ids, 'active', 'ignore', 'ignore', TRUE];
    // The trash behavior is ignored, however the filter is still taking
    // effect.
    yield 'page_2 with "ignore" context render' => ['page_2', $trashed_entity_ids, 'ignore', 'ignore', 'ignore', TRUE];
  }

}
