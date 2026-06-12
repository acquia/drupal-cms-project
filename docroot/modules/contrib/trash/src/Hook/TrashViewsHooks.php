<?php

declare(strict_types=1);

namespace Drupal\trash\Hook;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\trash\TrashManagerInterface;
use Drupal\views\Plugin\views\field\BulkForm;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Views hook implementations for Trash.
 */
class TrashViewsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected TrashManagerInterface $trashManager,
    #[Autowire(service: 'plugin.manager.views.join')]
    protected ?ViewsHandlerManager $joinHandler = NULL,
  ) {}

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    foreach ($this->trashManager->getEnabledEntityTypes() as $entity_type_id) {
      // Add the trash_operations field for entity types without a list builder.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
      if (!$entity_type) {
        continue;
      }

      if ($entity_type->hasListBuilderClass()) {
        continue;
      }

      $base_table = $entity_type->getBaseTable();
      if ($base_table && isset($data[$base_table])) {
        $data[$base_table]['trash_operations'] = [
          'field' => [
            'title' => $this->t('Trash operations'),
            'help' => $this->t('Provides links to restore or purge trashed entities.'),
            'id' => 'trash_operations',
            'entity_type' => $entity_type_id,
          ],
        ];
      }
    }
  }

  /**
   * Implements hook_views_query_alter().
   */
  #[Hook('views_query_alter', order: Order::First)]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    // Don't alter any non-sql views queries.
    if (!$query instanceof Sql || !$this->trashManager->shouldAlterQueries()) {
      return;
    }

    // Bail out early if the query has already been altered.
    if (in_array('trash_altered', $query->tags, TRUE)) {
      return;
    }

    // Add a deleted condition for every entity table in the query that has
    // trash enabled.
    foreach ($query->getEntityTableInfo() as $info) {
      // Skip entity types without trash integration.
      if (!$this->trashManager->isEntityTypeEnabled($info['entity_type'])) {
        continue;
      }

      $deleted_table_alias = $info['alias'];

      // If this is a revision table, we need to join and use the data table
      // which holds the relevant "deleted" state.
      if ($info['revision']) {
        $entity_type = $this->entityTypeManager->getDefinition($info['entity_type']);
        $id_key = $entity_type->getKey('id');
        $data_table = $entity_type->getDataTable() ?? $entity_type->getBaseTable();

        if (empty($data_table)) {
          throw new \UnexpectedValueException("Missing data table for the {$info['base']} revision table.");
        }

        $definition = [
          'type' => 'LEFT',
          'table' => $data_table,
          'field' => $id_key,
          'left_table' => $info['base'],
          'left_field' => $id_key,
        ];
        $join = $this->joinHandler->createInstance('standard', $definition);

        $deleted_table_alias = $query->addTable($data_table, $info['alias'], $join);
      }

      $this->alterQueryForEntityType($query, $info['entity_type'], $deleted_table_alias);
    }
  }

  /**
   * Alters the entity type tables for a Views query.
   *
   * Adds a data_table.deleted IS NULL condition unless there is a specific
   * filter for the deleted field already.
   *
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query plugin object for the query.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $deleted_table_alias
   *   The alias of the data table which holds the 'deleted' column.
   */
  protected function alterQueryForEntityType(Sql $query, string $entity_type_id, string $deleted_table_alias): void {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    assert($storage instanceof SqlEntityStorageInterface);
    $table_mapping = $storage->getTableMapping();
    assert($table_mapping instanceof DefaultTableMapping);
    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);

    // Try to find out whether any filter (normal or conditional filter) filters
    // by the delete column. In case it does opt out of adding a specific
    // delete column.
    $deleted_table_column = $table_mapping->getFieldColumnName($field_storage_definitions['deleted'], 'value');

    $has_delete_condition = $this->hasDeleteCondition($query, $deleted_table_alias, $deleted_table_column);

    // If we couldn't find any condition that filters out explicitly on deleted,
    // ensure that we just return not deleted entities.
    if (!$has_delete_condition) {
      $query->addWhere(0, "{$deleted_table_alias}.{$deleted_table_column}", NULL, 'IS NULL');
      $query->addTag('trash_altered');
    }
    // Otherwise ignore trash for the duration of this view, so it can load and
    // display deleted entities. It will restore the context after the view has
    // finished execution.
    else {
      if (!in_array('ignore_trash', $query->tags, TRUE)) {
        $original_context = $this->trashManager->getTrashContext();
        $this->trashManager->setTrashContext('ignore');
        $query->addTag('ignore_trash');
        $query->addTag("original_trash_context:$original_context");
      }
    }
  }

  /**
   * Check if any filter of the query contains a delete condition.
   *
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query plugin object for the query.
   * @param string $deleted_table_alias
   *   The alias of the table to check.
   * @param string $deleted_table_column
   *   Name of delete column.
   *
   * @return bool
   *   <code>TRUE</code> if the query has a delete condition, <code>FALSE</code>
   *   otherwise.
   */
  protected function hasDeleteCondition(Sql $query, string $deleted_table_alias, string $deleted_table_column): bool {
    foreach ($query->where as $group) {
      foreach ($group['conditions'] as $condition) {
        if (!isset($condition['field']) || !is_string($condition['field'])) {
          continue;
        }
        // Note: We use strpos because views for some reason has a field
        // looking like "trash_test.Deleted > 0".
        if (strpos($condition['field'], "{$deleted_table_alias}.{$deleted_table_column}") !== FALSE) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Implements hook_views_post_build().
   */
  #[Hook('views_post_build', order: Order::Last)]
  public function viewsPostBuild(ViewExecutable $view): void {
    if ($view->executed) {
      // The view was flagged as executed during the build phase.
      $this->restoreTrashContext($view);
    }
  }

  /**
   * Implements hook_views_post_execute().
   */
  #[Hook('views_post_execute', order: Order::Last)]
  public function viewsPostExecute(ViewExecutable $view): void {
    $this->restoreTrashContext($view);
  }

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render', order: Order::First)]
  public function viewsPreRender(ViewExecutable $view): void {
    $query = $view->getQuery();

    // Reorder bulk form actions for trash views to show Restore first.
    $query_tags = $query->options['query_tags'] ?? [];
    if (in_array('trash_views_overview', $query_tags, TRUE)) {
      $this->reorderTrashBulkFormActions($view);
    }
    else {
      // For non-trash views, remove trash-specific actions entirely.
      $this->removeTrashBulkFormActions($view);
    }

    // If the view is also being rendered, then attempt to reignore the trash
    // context, it'll be restored in the post_render hook.
    if ($query instanceof Sql && in_array('ignore_trash', $query->tags, TRUE)) {
      $this->trashManager->setTrashContext('ignore');
    }
  }

  /**
   * Reorders bulk form actions to show the Restore action first.
   */
  protected function reorderTrashBulkFormActions(ViewExecutable $view): void {
    foreach ($view->field as $field) {
      if ($field instanceof BulkForm) {
        // Access the protected $actions property and reorder it.
        \Closure::bind(function () {
          $restore = array_filter($this->actions, fn($id) => str_ends_with($id, '_restore_action'), ARRAY_FILTER_USE_KEY);
          $this->actions = $restore + $this->actions;
        }, $field, BulkForm::class)();

        break;
      }
    }
  }

  /**
   * Removes trash-specific actions from non-trash views.
   */
  protected function removeTrashBulkFormActions(ViewExecutable $view): void {
    foreach ($view->field as $field) {
      if ($field instanceof BulkForm) {
        \Closure::bind(function () {
          $this->actions = array_filter($this->actions, fn($id) => !str_ends_with($id, '_restore_action') && !str_ends_with($id, '_purge_action'), ARRAY_FILTER_USE_KEY);
        }, $field, BulkForm::class)();

        break;
      }
    }
  }

  /**
   * Implements hook_views_post_render().
   */
  #[Hook('views_post_render', order: Order::Last)]
  public function viewsPostRender(ViewExecutable $view): void {
    // Restore the trash context after the view has been built.
    $this->restoreTrashContext($view);
  }

  /**
   * Restore the trash context after a view is finished executing.
   */
  protected function restoreTrashContext(ViewExecutable $view): void {
    $query = $view->getQuery();
    if ($query instanceof Sql && in_array('ignore_trash', $query->tags, TRUE)) {
      foreach ($query->tags as $tag) {
        assert(is_string($tag));
        if (str_starts_with($tag, 'original_trash_context:')) {
          [, $previous_trash_context] = explode(':', $tag, 2);
          // Restore the trash context to what it originally was.
          $this->trashManager->setTrashContext($previous_trash_context);
          break;
        }
      }
    }
  }

}
