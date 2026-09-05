<?php

declare(strict_types=1);

namespace Drupal\trash\Hook\TrashHandler;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\trash\Handler\DefaultTrashHandler;
use Drupal\trash\Plugin\Search\TrashNodeSearch;

/**
 * Provides a trash handler for the 'node' entity type.
 */
class NodeTrashHandler extends DefaultTrashHandler {

  /**
   * Implements hook_query_TAG_alter() for the 'search_node_search' tag.
   */
  #[Hook('query_search_node_search_alter')]
  public function querySearchNodeSearchAlter(AlterableInterface $query): void {
    if (!$this->trashManager->isEntityTypeEnabled('node')) {
      return;
    }

    // The Search module is not using an entity query, so we need to alter its
    // query manually.
    // @see \Drupal\node\Plugin\Search\NodeSearch::findResults()
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query->isNull('n.deleted');
  }

  /**
   * Implements hook_search_plugin_alter().
   */
  #[Hook('search_plugin_alter')]
  public function searchPluginAlter(array &$definitions): void {
    if (!$this->trashManager->isEntityTypeEnabled('node')) {
      return;
    }

    // Swap the NodeSearch plugin for a trash-aware subclass so that indexing
    // runs in the 'ignore' trash context. Without this, trash-aware storage
    // hides trashed nodes from loadMultiple(), causing the indexer to
    // repeatedly select trashed IDs it cannot load and starving live nodes out
    // of the cron batch. Search-time filtering is handled separately by
    // querySearchNodeSearchAlter().
    if (isset($definitions['node_search'])) {
      $definitions['node_search']['class'] = TrashNodeSearch::class;
    }
  }

}
