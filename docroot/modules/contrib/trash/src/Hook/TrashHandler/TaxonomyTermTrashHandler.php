<?php

declare(strict_types=1);

namespace Drupal\trash\Hook\TrashHandler;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\trash\Handler\DefaultTrashHandler;

/**
 * Provides a trash handler for the 'taxonomy_term' entity type.
 */
class TaxonomyTermTrashHandler extends DefaultTrashHandler {

  /**
   * Implements hook_query_TAG_alter() for the 'taxonomy_term_access' tag.
   */
  #[Hook('query_taxonomy_term_access_alter')]
  public function queryTaxonomyTermAccessAlter(AlterableInterface $query): void {
    $this->excludeDeletedTerms($query);
  }

  /**
   * Implements hook_query_TAG_alter() for the 'term_access' tag.
   */
  #[Hook('query_term_access_alter')]
  public function queryTermAccessAlter(AlterableInterface $query): void {
    $this->excludeDeletedTerms($query);
  }

  /**
   * Excludes deleted taxonomy terms from a database query.
   *
   * This prevents deleted taxonomy terms from appearing in taxonomy tree
   * operations and overview pages.
   */
  protected function excludeDeletedTerms(AlterableInterface $query): void {
    if (!$this->trashManager->shouldAlterQueries() || !$this->trashManager->isEntityTypeEnabled('taxonomy_term')) {
      return;
    }

    $data_table = $this->entityTypeManager->getDefinition('taxonomy_term')->getDataTable();

    assert($query instanceof SelectInterface);
    foreach ($query->getTables() as $alias => $table_info) {
      if (($table_info['table'] ?? NULL) === $data_table) {
        $query->isNull($alias . '.deleted');
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFormAlter(array &$form, FormStateInterface $form_state, bool $multiple = FALSE): void {
    $form['description']['#markup'] = $this->t('Deleting this term will move it to the <a href=":link">trash</a>, including all its children if there are any. You can restore it from the trash at a later date if necessary.', [
      ':link' => Url::fromRoute('trash.admin_content_trash_entity_type', [
        'entity_type_id' => $this->entityTypeId,
      ])->toString(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function restoreFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['description']['#markup'] = $this->t('Restoring this term will also restore all its children if there are any.');
  }

  /**
   * {@inheritdoc}
   */
  public function preTrashRestore(EntityInterface $entity): void {
    parent::preTrashRestore($entity);
    assert($entity instanceof FieldableEntityInterface);

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Remove references to parents that are still trashed so the restored term
    // is visible in the taxonomy tree. Without this, the term would be active
    // but unreachable because its parent is hidden.
    // During cascade restore this is a no-op: the parent is already restored
    // by the time its children are processed.
    $entity->get('parent')->filter(function ($item) use ($storage) {
      assert($item instanceof EntityReferenceItem);
      if ($item->target_id == 0) {
        return TRUE;
      }
      $parent = $storage->load($item->target_id);
      return $parent && !trash_entity_is_deleted($parent);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function postTrashDelete(EntityInterface $entity): void {
    parent::postTrashDelete($entity);

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Find non-deleted children of this term.
    $child_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('parent', $entity->id())
      ->notExists('deleted')
      ->execute();

    if (empty($child_ids)) {
      return;
    }

    $children = $storage->loadMultiple($child_ids);

    // Build a map of each child's other parent IDs (excluding the trashed
    // term and root) so we can check for active parents in a single query.
    $all_other_parent_ids = [];
    $child_other_parents = [];
    foreach ($children as $child) {
      foreach ($child->get('parent') as $item) {
        assert($item instanceof EntityReferenceItem);
        if ($item->target_id != $entity->id() && $item->target_id != 0) {
          $all_other_parent_ids[$item->target_id] = $item->target_id;
          $child_other_parents[$child->id()][] = $item->target_id;
        }
      }
    }

    // If no child has another parent, they are all orphans.
    if (empty($all_other_parent_ids)) {
      $storage->delete(array_values($children));
      return;
    }

    // Check which other parents are still active.
    $active_parent_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('tid', array_values($all_other_parent_ids), 'IN')
      ->notExists('deleted')
      ->execute();

    // Cascade-trash orphaned children. This goes through TrashStorageTrait,
    // which soft-deletes them with the same request timestamp. Crucially, their
    // parent field is NOT modified, so the hierarchy is preserved for restore.
    // Each child's postTrashDelete will recursively handle deeper descendants.
    $orphans = array_filter($children, fn ($child) =>
      !isset($child_other_parents[$child->id()]) || !array_intersect($child_other_parents[$child->id()], $active_parent_ids)
    );

    if (!empty($orphans)) {
      $storage->delete(array_values($orphans));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postTrashRestore(EntityInterface $entity, int|string $deleted_timestamp): void {
    parent::postTrashRestore($entity, $deleted_timestamp);

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Find direct children that were cascade-trashed at the same time. The
    // explicit condition on 'deleted' causes the trash entity query alter to
    // skip this query. Scoping to direct children means each level's
    // postTrashRestore naturally cascades to the next without recursion issues.
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('parent', $entity->id())
      ->condition('deleted', $deleted_timestamp)
      ->execute();

    if (!empty($ids)) {
      $children = $storage->loadMultiple($ids);
      $storage->restoreFromTrash(array_values($children));
    }
  }

}
