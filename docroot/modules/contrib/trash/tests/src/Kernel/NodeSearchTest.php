<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\trash\Plugin\Search\TrashNodeSearch;

/**
 * Tests that search indexing handles trashed nodes.
 *
 * @group trash
 */
class NodeSearchTest extends TrashKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search', ['search_index', 'search_dataset', 'search_total']);
    $this->installConfig(['search', 'system']);
  }

  /**
   * Trashed nodes are indexed like unpublished ones; cron does not stall.
   */
  public function testIndexing(): void {
    $plugin = \Drupal::service('plugin.manager.search')->createInstance('node_search');
    $this->assertInstanceOf(TrashNodeSearch::class, $plugin);

    // Create more nodes than cron_limit so indexing runs in multiple batches,
    // then trash one. Before the fix this starved the indexer.
    $cron_limit = (int) \Drupal::config('search.settings')->get('index.cron_limit');
    $total = $cron_limit + 2;
    $nodes = [];
    for ($i = 0; $i < $total; $i++) {
      $nodes[] = $this->createNode(['type' => 'article']);
    }
    $nodes[1]->setTitle('banana');
    $nodes[1]->save();
    $nodes[1]->delete();

    $iterations = 0;
    do {
      $plugin->updateIndex();
      $status = $plugin->indexStatus();
      $this->assertLessThan(10, ++$iterations, 'Indexing stalled on a trashed node.');
    } while ($status['remaining'] > 0);

    $this->assertEquals($total, $status['total']);

    $indexed = \Drupal::database()->select('search_dataset', 'sd')
      ->fields('sd', ['sid'])
      ->condition('type', 'node_search')
      ->execute()
      ->fetchCol();
    $this->assertCount($total, $indexed);
    $this->assertContains((string) $nodes[1]->id(), $indexed);

    // Search results filter out the trashed node even though it is indexed.
    $plugin->setSearch('banana', [], []);
    $this->assertEmpty($plugin->execute());

    // With node disabled for trash, the plugin is not swapped.
    $this->disableEntityTypesForTrash(['node']);
    $plugin = \Drupal::service('plugin.manager.search')->createInstance('node_search');
    $this->assertNotInstanceOf(TrashNodeSearch::class, $plugin);
  }

}
