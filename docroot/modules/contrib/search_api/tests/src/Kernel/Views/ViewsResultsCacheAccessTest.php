<?php

namespace Drupal\Tests\search_api\Kernel\Views;

use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that cached Search API views results respect item access.
 *
 * The test view caches its results ("Search API (tag-based)") and leaves
 * "Skip item access checks" unchecked, so items the current user cannot view
 * are removed from the results after the search. The test index has no
 * "Content access" processor, so nothing in the search query differs between
 * the two test users.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ViewsResultsCacheAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The ID of the view used in the test.
   */
  protected const TEST_VIEW_ID = 'search_api_test_node_view';

  /**
   * The display ID used in the test.
   */
  protected const TEST_VIEW_DISPLAY_ID = 'page_1';

  /**
   * Test users, keyed by a description of their access.
   *
   * @var array<string, \Drupal\user\UserInterface>
   */
  protected array $users = [];

  /**
   * The test nodes, keyed by node title.
   *
   * @var array<string, \Drupal\node\NodeInterface>
   */
  protected array $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'rest',
    'search_api',
    'search_api_db',
    'search_api_test',
    'search_api_test_node_indexing',
    'search_api_test_views',
    'serialization',
    'system',
    'text',
    'user',
    'views',
    'views_test_data',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installConfig([
      'node',
      'search_api',
      'search_api_test_node_indexing',
      'search_api_test_views',
    ]);

    // UID 1 can bypass all access restrictions, so create it first and do not
    // use it in the test.
    $this->createUser();

    // Neither user has "bypass node access", so both get the same value for
    // the "user.node_grants:view" cache context the view varies by, and both
    // have the same permissions. Only the author may view the unpublished
    // node.
    $this->users['no-access'] = $this->createUser(['access content', 'view own unpublished content']);
    $this->users['author'] = $this->createUser(['access content', 'view own unpublished content']);

    NodeType::create([
      'name' => 'Page',
      'type' => 'page',
    ])->save();
    foreach (['Cheery' => TRUE, 'Carrot' => TRUE, 'Detritus' => FALSE] as $title => $status) {
      $this->nodes[$title] = Node::create([
        'title' => $title,
        'status' => $status,
        'type' => 'page',
        'uid' => $this->users['author']->id(),
      ]);
      $this->nodes[$title]->save();
    }
    Index::load('test_node_index')->indexItems();
  }

  /**
   * Tests that results cached for one user are not shown to another.
   *
   * @param bool $caching_enabled
   *   FALSE to test with an uncached view, as a control.
   * @param bool $same_permissions
   *   TRUE to leave both users with the same permissions, so that the rendered
   *   output of the view could be shared between them, too. FALSE to give the
   *   author an extra permission, so only the results cache could be shared.
   *
   * @dataProvider cachedResultsRespectItemAccessTestDataProvider
   */
  public function testCachedResultsRespectItemAccess(bool $caching_enabled, bool $same_permissions): void {
    if (!$same_permissions) {
      $this->users['author']->addRole($this->createRole(['access user profiles']))->save();
    }
    if (!$caching_enabled) {
      /** @var \Drupal\views\ViewEntityInterface $view */
      $view = $this->container->get('entity_type.manager')
        ->getStorage('view')
        ->load(self::TEST_VIEW_ID);
      $displays = $view->get('display');
      $displays['default']['display_options']['cache'] = [
        'type' => 'search_api_none',
        'options' => [],
      ];
      $view->set('display', $displays);
      $view->save();
    }

    $this->assertFalse($this->nodes['Detritus']->access('view', $this->users['no-access']), 'The unprivileged user must not be able to view the unpublished node directly.');

    // The author searches first and fills the results cache.
    $this->assertViewsResult('author', ['Cheery', 'Carrot', 'Detritus']);

    // The other user must not be served the author's results.
    $this->assertViewsResult('no-access', ['Cheery', 'Carrot']);
  }

  /**
   * Provides test data sets for testCachedResultsRespectItemAccess().
   *
   * @return array<string, array{0: bool, 1: bool}>
   *   Test data sets for testCachedResultsRespectItemAccess, keyed by label.
   */
  public static function cachedResultsRespectItemAccessTestDataProvider(): array {
    return [
      'cached, same permissions' => [TRUE, TRUE],
      'cached, different permissions' => [TRUE, FALSE],
      'uncached, same permissions' => [FALSE, TRUE],
      'uncached, different permissions' => [FALSE, FALSE],
    ];
  }

  /**
   * Tests that users with the same access share cached results.
   *
   * Access to the published nodes only depends on permissions, and neither
   * user may view the unpublished node regardless of who wrote it, so the
   * results only need to vary by permissions, not by user.
   */
  public function testCachedResultsSharedBetweenUsersWithSameAccess(): void {
    $this->users['editor'] = $this->createUser(['access content']);
    $this->users['other-editor'] = $this->createUser(['access content']);
    $this->users['other-permissions'] = $this->createUser(['access content', 'access user profiles']);

    $this->assertViewsResult('editor', ['Cheery', 'Carrot']);

    $this->assertTrue($this->isResultsCacheHit('other-editor'), 'A user with the same permissions must be served the cached results.');
    $this->assertFalse($this->isResultsCacheHit('other-permissions'), 'A user with different permissions must not be served the cached results.');
    $this->assertViewsResult('other-editor', ['Cheery', 'Carrot']);
  }

  /**
   * Tests that results only vary by user when item access depends on it.
   *
   * Access to the unpublished node depends on who wrote it for users with the
   * "view own unpublished content" permission, so the results must not be
   * shared even between users with the same permissions.
   */
  public function testCachedResultsVaryByUserWhenAccessDependsOnUser(): void {
    $this->assertViewsResult('author', ['Cheery', 'Carrot', 'Detritus']);

    $this->assertFalse($this->isResultsCacheHit('no-access'), 'Results that depend on the user must not be served to another user.');
    $this->assertTrue($this->isResultsCacheHit('author'), 'The results must still be cached for the user they were built for.');
  }

  /**
   * Checks whether the view's results are served from the cache for a user.
   *
   * @param string $user_key
   *   The key of the user to check.
   *
   * @return bool
   *   TRUE if cached results exist for the user, FALSE otherwise.
   */
  protected function isResultsCacheHit(string $user_key): bool {
    $this->container->get('cache_tags.invalidator')->resetChecksums();
    $this->container->get('current_user')->setAccount($this->users[$user_key]);

    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = $this->container->get('entity_type.manager')
      ->getStorage('view')
      ->load(self::TEST_VIEW_ID);
    $executable = $this->container->get('views.executable')->get($view);
    $executable->setDisplay(self::TEST_VIEW_DISPLAY_ID);
    $executable->build();
    /** @var \Drupal\views\Plugin\views\cache\CachePluginBase $cache */
    $cache = $executable->getDisplay()->getPlugin('cache');
    return (bool) $cache->cacheGet('results');
  }

  /**
   * Checks that the view for the given user contains the expected results.
   *
   * @param string $user_key
   *   The key of the user to check.
   * @param string[] $node_titles
   *   The titles of the nodes that are expected to be present in the results.
   */
  protected function assertViewsResult(string $user_key, array $node_titles): void {
    // Pretend every call is a new page request.
    $this->container->get('cache_tags.invalidator')->resetChecksums();
    $this->container->get('current_user')->setAccount($this->users[$user_key]);

    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = $this->container->get('entity_type.manager')
      ->getStorage('view')
      ->load(self::TEST_VIEW_ID);
    $executable = $this->container->get('views.executable')->get($view);
    $executable->setDisplay(self::TEST_VIEW_DISPLAY_ID);
    $render_array = $executable->buildRenderable();
    $renderer_config = $this->container->getParameter('renderer.config');
    $render_array['#cache']['contexts'] = Cache::mergeContexts(
      $render_array['#cache']['contexts'],
      $renderer_config['required_cache_contexts']
    );
    $html = (string) $this->container->get('renderer')->renderRoot($render_array);

    $node_titles = array_flip($node_titles);
    foreach (array_keys($this->nodes) as $node_title) {
      if (isset($node_titles[$node_title])) {
        $this->assertStringContainsString($node_title, $html, "User '$user_key' should see '$node_title'.");
      }
      else {
        $this->assertStringNotContainsString($node_title, $html, "User '$user_key' should not see '$node_title'.");
      }
    }
  }

}
