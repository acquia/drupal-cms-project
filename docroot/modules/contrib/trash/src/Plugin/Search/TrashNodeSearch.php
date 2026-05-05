<?php

declare(strict_types=1);

namespace Drupal\trash\Plugin\Search;

use Drupal\node\Plugin\Search\NodeSearch;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A node_search plugin that runs indexing in the 'ignore' trash context.
 *
 * Without this, trash-aware storage hides trashed nodes from loadMultiple(),
 * stalling the indexer on IDs it cannot load. Trashed nodes are indexed like
 * unpublished ones and filtered at search time by NodeTrashHandler.
 */
class TrashNodeSearch extends NodeSearch {

  /**
   * The trash manager.
   */
  protected TrashManagerInterface $trashManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->trashManager = $container->get(TrashManagerInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(): void {
    $this->trashManager->executeInTrashContext(
      'ignore',
      fn () => parent::updateIndex(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function indexStatus(): mixed {
    return $this->trashManager->executeInTrashContext(
      'ignore',
      fn () => parent::indexStatus(),
    );
  }

}
