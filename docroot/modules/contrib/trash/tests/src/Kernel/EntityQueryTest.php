<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\trash_test\Entity\TrashTestEntity;

/**
 * Tests entity query integration for Trash.
 *
 * @group trash
 */
class EntityQueryTest extends TrashKernelTestBase {

  /**
   * Tests that deleted entities are excluded from entity query results.
   */
  public function testQueryWithoutDeletedAccess(): void {
    $entities = [];
    $entity_storage = \Drupal::entityTypeManager()->getStorage('trash_test_entity');

    for ($i = 0; $i < 5; $i++) {
      $entity = TrashTestEntity::create();
      $entity->save();
      $entities[] = $entity;
    }

    // Test whether they appear in an entity query.
    $this->assertCount(5, $entity_storage->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(5, $entity_storage->getAggregateQuery()->accessCheck(FALSE)->groupBy('id')->execute());

    // Delete the first three of them. They should no longer accessible via the
    // entity query.
    for ($i = 0; $i < 3; $i++) {
      $entities[$i]->delete();
    }
    $this->assertCount(2, $entity_storage->getQuery()->accessCheck(FALSE)->execute());
    $this->assertCount(2, $entity_storage->getAggregateQuery()->accessCheck(FALSE)->groupBy('id')->execute());

    // Check that deleted entities can still be retrieved by an entity query if
    // the trash context is disabled.
    $result = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_storage) {
      return $entity_storage->getQuery()->accessCheck(FALSE)->execute();
    });
    $this->assertCount(5, $result);

    $result = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_storage) {
      return $entity_storage->getAggregateQuery()->accessCheck(FALSE)->groupBy('id')->execute();
    });
    $this->assertCount(5, $result);

    // Check that we can also manually set the trash context on the query.
    $result = $entity_storage->getQuery()->accessCheck(FALSE)->addMetaData('trash', 'ignore')->execute();
    $this->assertCount(5, $result);
  }

  /**
   * Test entity queries with OR conjunction.
   */
  public function testQueryWithOrConjunction(): void {
    $entity_storage = \Drupal::entityTypeManager()->getStorage('trash_test_entity');

    // Create six test entities, three of which will be deleted.
    $ham = TrashTestEntity::create(['label' => 'ham']);
    $ham->save();
    $cheese = TrashTestEntity::create(['label' => 'cheese']);
    $cheese->save();
    $tomato = TrashTestEntity::create(['label' => 'tomato']);
    $tomato->save();

    $salad = TrashTestEntity::create(['label' => 'salad']);
    $salad->save();
    $salad->delete();
    $ketchup = TrashTestEntity::create(['label' => 'ketchup']);
    $ketchup->save();
    $ketchup->delete();
    $bread = TrashTestEntity::create(['label' => 'bread']);
    $bread->save();
    $bread->delete();

    // Check entity queries for non-deleted entities.
    $query = $entity_storage->getQuery('OR')
      ->accessCheck()
      ->condition('label', 'ham')
      ->condition('label', 'cheese');
    $this->assertCount(2, $query->execute());

    $aggregate_query = $entity_storage->getAggregateQuery('OR')
      ->accessCheck()
      ->condition('label', 'ham')
      ->condition('label', 'tomato')
      ->groupBy('label');
    $this->assertCount(2, $aggregate_query->execute());

    // Check that deleted entities can still be retrieved by an entity query if
    // the trash context is disabled.
    $result = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_storage) {
      return $entity_storage
        ->getQuery('OR')
        ->accessCheck()
        ->condition('label', 'ham')
        ->condition('label', 'salad')
        ->condition('label', 'ketchup')
        ->execute();
    });
    $this->assertCount(3, $result);

    $result = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($entity_storage) {
      return $entity_storage->getAggregateQuery('OR')
        ->accessCheck()
        ->condition('label', 'cheese')
        ->condition('label', 'salad')
        ->condition('label', 'bread')
        ->groupBy('label')
        ->execute();
    });
    $this->assertCount(3, $result);
  }

}
