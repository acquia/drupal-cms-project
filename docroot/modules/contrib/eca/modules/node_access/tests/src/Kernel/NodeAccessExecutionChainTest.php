<?php

namespace Drupal\Tests\eca_node_access\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Entity\Eca;
use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Execution chain tests using plugins of eca_node_access.
 *
 * Proves the submodule end-to-end: an ECA model writes node access grant
 * records for saved nodes (via hook_node_access_records) and grants a specific
 * account the matching realm + grant IDs (via hook_node_grants), and that this
 * access is then honored by node_access-tagged queries (the Views equivalent
 * the issue is about) and by Node::access('view').
 */
#[Group('eca')]
#[Group('eca_node_access')]
#[RunTestsInSeparateProcesses]
class NodeAccessExecutionChainTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * The realm shared between the records and grants ECA models.
   *
   * @var string
   */
  protected const REALM = 'eca_node_access';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
    'eca_base',
    'eca_node_access',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    // The node_access table is what node_access-tagged queries read from and
    // what hook_node_access_records writes into.
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);

    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    Role::create(['id' => 'test_role_eca', 'label' => 'Test Role ECA'])->save();
    User::create([
      'uid' => 2,
      'name' => 'authenticated',
      'roles' => ['test_role_eca'],
    ])->save();
    user_role_grant_permissions('test_role_eca', [
      'access content',
    ]);

    // Create an Article content type (covered by the records model).
    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);
    // Create a Page content type (used for the negative assertions).
    $this->createContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'new_revision' => TRUE,
    ]);
  }

  /**
   * Tests that ECA-provided node grants are honored by node_access queries.
   *
   * Scenario:
   * - Records model: for every saved node (any bundle) write a grant record in
   *   realm "eca_node_access" with gid = the node's own nid and grant_view = 1.
   *   This makes each node its own grant ID within that realm.
   * - Grants model: the authenticated user (uid 2) receives, in the same realm,
   *   exactly the grant ID of the article it is supposed to see. Because the
   *   grants event only exposes the account (not a node) as token data, the
   *   target nid is set deterministically once it is known.
   *
   * The result is that uid 2 may view the granted article via both
   * Node::access('view') and a node_access-tagged entity query, while a node it
   * has no matching grant for (the page) remains hidden.
   */
  public function testNodeAccessGrantsAreHonored(): void {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(2));

    // Create the nodes up front so their node IDs are known. No ECA model and
    // no grant records exist yet at this point.
    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => TRUE,
    ]);
    $article->save();
    $articleNid = (int) $article->id();

    $page = Node::create([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'status' => TRUE,
    ]);
    $page->save();
    $pageNid = (int) $page->id();

    // Baseline: with no module writing node access records, Drupal's node grant
    // system falls back to the default "all" grant (realm "all", gid 0), which
    // is handed to every account. So both nodes are visible to uid 2 now. This
    // is the starting state the issue describes: the custom realm is not yet in
    // play. The meaningful proof below is that once ECA writes records, only
    // nodes the account was granted in the ECA realm remain visible.
    $baseline = $this->taggedNodeQuery();
    $this->assertContains($articleNid, $baseline,
      'Baseline: the article is visible via the default "all" node grant before any ECA records exist.');
    $this->assertContains($pageNid, $baseline,
      'Baseline: the page is visible via the default "all" node grant before any ECA records exist.');

    // The records model: write a grant record for every saved node, using the
    // node's own nid as the grant ID in the shared realm, granting view.
    $records_config = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access_records',
      'label' => 'ECA node access records',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'records' => [
          'plugin' => 'node_access:node_access_records',
          'label' => 'Node access records',
          // The records event fires unconditionally for every node bundle, so
          // it has no configuration.
          'configuration' => [],
          'successors' => [
            ['id' => 'write_record', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'write_record' => [
          'plugin' => 'eca_node_access_set_node_access_records',
          'label' => 'Write node access record',
          'configuration' => [
            'realm' => self::REALM,
            // The acted-on node is available as [node]/[entity].
            'grant_id' => '[node:nid]',
            'grant_view' => TRUE,
            'grant_update' => FALSE,
            'grant_delete' => FALSE,
          ],
          'successors' => [],
        ],
      ],
    ];
    Eca::create($records_config)->save();

    // The grants model: return, in the shared realm, exactly the article's
    // grant ID (its nid). The page's grant ID is deliberately never granted, so
    // even though every account that asks receives the article's grant, the
    // page remains hidden because nobody is granted the page's gid.
    $grants_config = [
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access_grants',
      'label' => 'ECA node access grants',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'grants' => [
          'plugin' => 'node_access:node_access_grants',
          'label' => 'Node access grants',
          // No operation restriction: applies for any node operation.
          'configuration' => [],
          'successors' => [
            ['id' => 'return_grants', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'return_grants' => [
          'plugin' => 'eca_node_access_set_node_grants',
          'label' => 'Set node grants',
          'configuration' => [
            'realm' => self::REALM,
            // Grant only the article's nid.
            'grant_ids' => (string) $articleNid,
          ],
          'successors' => [],
        ],
      ],
    ];
    Eca::create($grants_config)->save();

    // Now that the records model exists, (re)build the node access grants so
    // hook_node_access_records runs for the existing nodes and the node_access
    // table is populated.
    node_access_rebuild();
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();

    // The records model must have written one grant record per node, each in
    // the shared realm with the node's own nid as gid and grant_view = 1. This
    // proves the submodule wrote the right grants regardless of the grant-check
    // outcome below.
    $this->assertGrantRecord($articleNid);
    $this->assertGrantRecord($pageNid);

    // The authenticated user may view the granted article, proven via both the
    // entity access check and a node_access-tagged query (the Views
    // equivalent).
    $article = Node::load($articleNid);
    $this->assertTrue($article->access('view', User::load(2)),
      'uid 2 can view the article it has a matching grant for.');
    $this->assertContains($articleNid, $this->taggedNodeQuery(),
      'A node_access-tagged query run as uid 2 returns the granted article.');

    // The page has a grant record (gid = its own nid) but uid 2 was not granted
    // that gid, so it must remain hidden from the same tagged query and from
    // the entity access check.
    $page = Node::load($pageNid);
    $this->assertFalse($page->access('view', User::load(2)),
      'uid 2 cannot view the page it has no matching grant for.');
    $this->assertNotContains($pageNid, $this->taggedNodeQuery(),
      'A node_access-tagged query run as uid 2 does not return the page that is not granted.');
  }

  /**
   * Tests that a configured operation restricts when the grants event applies.
   *
   * A single grants model is restricted to the "view" operation and returns a
   * grant in the shared realm. It must contribute its grant when Drupal asks
   * for "view" grants, but contribute nothing when Drupal asks for "update" or
   * "delete" grants, because the event does not apply for the wrong operation.
   *
   * The hook is driven directly with
   * \Drupal::moduleHandler()->invokeAll('node_grants', [$account, $operation])
   * so the operation can be varied per invocation.
   */
  public function testNodeAccessGrantsOperationFilterRestricts(): void {
    $viewGid = 101;

    $this->createGrantsModel('node_access_grants_view', 'view', $viewGid);

    // Rebuild so the freshly created model registers its event subscriber.
    node_access_rebuild();

    $account = User::load(2);

    // For the "view" operation the model applies and contributes its grant.
    $this->assertContains($viewGid, $this->invokeNodeGrants($account, 'view'),
      'The view-restricted grants model contributes its grant for the "view" operation.');

    // For the "update" and "delete" operations the model must NOT apply, so its
    // grant is absent.
    $this->assertNotContains($viewGid, $this->invokeNodeGrants($account, 'update'),
      'The view-restricted grants model does not contribute its grant for the "update" operation.');
    $this->assertNotContains($viewGid, $this->invokeNodeGrants($account, 'delete'),
      'The view-restricted grants model does not contribute its grant for the "delete" operation.');
  }

  /**
   * Tests that an empty operation preserves the any-operation behavior.
   *
   * A single grants model with no operation restriction must contribute its
   * grant for every node operation (view, update and delete), proving the
   * pre-existing behavior is preserved when no operation is configured.
   */
  public function testNodeAccessGrantsOperationFilterAnyOperation(): void {
    $anyGid = 202;

    $this->createGrantsModel('node_access_grants_any', '', $anyGid);

    // Rebuild so the freshly created model registers its event subscriber.
    node_access_rebuild();

    $account = User::load(2);

    foreach (['view', 'update', 'delete'] as $operation) {
      $this->assertContains($anyGid, $this->invokeNodeGrants($account, $operation),
        sprintf('The unrestricted grants model contributes its grant for the "%s" operation.', $operation));
    }
  }

  /**
   * Tests that the [event:operation] token resolves on the grants event.
   *
   * A grants model with no operation restriction captures the requested
   * operation by writing the [event:operation] token into the Drupal state
   * (via the eca_base "Persistent state: write" action). When Drupal asks for
   * grants for a specific operation, the stored state must equal exactly that
   * operation, proving the token is exposed on the NodeGrants event and carries
   * the operation Drupal requested.
   */
  public function testNodeAccessGrantsOperationTokenResolves(): void {
    // A grants model that, for any operation, copies the [event:operation]
    // token into a Drupal state value so the test can observe it. The
    // eca_state_write action runs token replacement on its "value" (use_yaml
    // is FALSE), so [event:operation] is replaced with the requested operation.
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access_grants_token',
      'label' => 'ECA node access grants capture operation token',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'grants' => [
          'plugin' => 'node_access:node_access_grants',
          'label' => 'Node access grants',
          // No operation restriction: applies for any node operation.
          'configuration' => [
            'operation' => '',
          ],
          'successors' => [
            ['id' => 'capture_operation', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'capture_operation' => [
          'plugin' => 'eca_state_write',
          'label' => 'Capture operation token',
          'configuration' => [
            'key' => 'test_captured_operation',
            // The token is embedded in a surrounding string so the value is
            // resolved with full string token replacement, yielding a scalar
            // string the test can compare directly.
            'value' => 'op=[event:operation]',
            'use_yaml' => FALSE,
            'validate_yaml' => FALSE,
          ],
          'successors' => [],
        ],
      ],
    ])->save();

    // Rebuild so the freshly created model registers its event subscriber.
    node_access_rebuild();

    $account = User::load(2);
    // The eca_state_write action writes via ECA's own state service, which uses
    // a separate key-value backend from the core state service, so the captured
    // value must be read back through the same service.
    $ecaState = \Drupal::service('eca.state');

    // Ask Drupal for "update" grants and assert the captured token equals the
    // operation Drupal requested.
    $ecaState->delete('test_captured_operation');
    \Drupal::moduleHandler()->invokeAll('node_grants', [$account, 'update']);
    $this->assertSame('op=update', $ecaState->get('test_captured_operation'),
      'The [event:operation] token resolved to the "update" operation Drupal requested.');

    // Repeat for a different operation to prove the token tracks the request
    // rather than returning a constant.
    $ecaState->delete('test_captured_operation');
    \Drupal::moduleHandler()->invokeAll('node_grants', [$account, 'delete']);
    $this->assertSame('op=delete', $ecaState->get('test_captured_operation'),
      'The [event:operation] token resolved to the "delete" operation Drupal requested.');
  }

  /**
   * Tests that the records event accumulates multiple distinct records.
   *
   * A single records model has two chained "Set node access records" actions
   * that each contribute a record in a different realm with a different set of
   * grant flags. After a rebuild, both rows must exist in the node_access table
   * for the saved node, proving that:
   * - records accumulate across multiple actions on the same event,
   * - the boolean grant flags are persisted as the integer 0/1 the hook
   *   contract expects, and
   * - the update and delete flags work, not just view.
   */
  public function testNodeAccessRecordsAccumulateAcrossActions(): void {
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access_records_accumulate',
      'label' => 'ECA node access records accumulate',
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'records' => [
          'plugin' => 'node_access:node_access_records',
          'label' => 'Node access records',
          'configuration' => [],
          'successors' => [
            ['id' => 'write_record_a', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        // Action 1: realm_a grants view only.
        'write_record_a' => [
          'plugin' => 'eca_node_access_set_node_access_records',
          'label' => 'Write node access record A',
          'configuration' => [
            'realm' => 'realm_a',
            'grant_id' => '[node:nid]',
            'grant_view' => TRUE,
            'grant_update' => FALSE,
            'grant_delete' => FALSE,
          ],
          'successors' => [
            ['id' => 'write_record_b', 'condition' => ''],
          ],
        ],
        // Action 2: realm_b grants update only.
        'write_record_b' => [
          'plugin' => 'eca_node_access_set_node_access_records',
          'label' => 'Write node access record B',
          'configuration' => [
            'realm' => 'realm_b',
            'grant_id' => '[node:nid]',
            'grant_view' => FALSE,
            'grant_update' => TRUE,
            'grant_delete' => FALSE,
          ],
          'successors' => [],
        ],
      ],
    ])->save();

    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => TRUE,
    ]);
    $article->save();
    $nid = (int) $article->id();

    // Build the node access grants so hook_node_access_records runs and both
    // actions contribute their records to the node_access table.
    node_access_rebuild();

    // Both distinct records must have been written, with the boolean flags
    // persisted as the correct integer 0/1 values.
    $this->assertGrantRecordFull($nid, 'realm_a', 1, 0, 0);
    $this->assertGrantRecordFull($nid, 'realm_b', 0, 1, 0);
  }

  /**
   * Creates and saves a grants ECA model with a given operation restriction.
   *
   * @param string $id
   *   The Eca config entity ID.
   * @param string $operation
   *   The operation restriction for the event ('' means any operation).
   * @param int $grantId
   *   The grant ID the model returns in the shared realm.
   */
  protected function createGrantsModel(string $id, string $operation, int $grantId): void {
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => $id,
      'label' => 'ECA node access grants ' . $id,
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'grants' => [
          'plugin' => 'node_access:node_access_grants',
          'label' => 'Node access grants',
          'configuration' => [
            'operation' => $operation,
          ],
          'successors' => [
            ['id' => 'return_grants', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'return_grants' => [
          'plugin' => 'eca_node_access_set_node_grants',
          'label' => 'Set node grants',
          'configuration' => [
            'realm' => self::REALM,
            'grant_ids' => (string) $grantId,
          ],
          'successors' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Invokes hook_node_grants() for an account and operation, flattening grants.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to request grants for.
   * @param string $operation
   *   The node operation, such as 'view', 'update' or 'delete'.
   *
   * @return int[]
   *   The flattened list of grant IDs returned for the shared realm.
   */
  protected function invokeNodeGrants(AccountInterface $account, string $operation): array {
    $results = \Drupal::moduleHandler()->invokeAll('node_grants', [$account, $operation]);
    $grants = $results[self::REALM] ?? [];
    return array_map('intval', array_values($grants));
  }

  /**
   * Runs a node_access-tagged entity query as the current account.
   *
   * This mirrors what a Views listing with node access enabled executes: a
   * query tagged with "node_access" that is filtered down to the nodes the
   * acting account is allowed to view.
   *
   * @return int[]
   *   The list of node IDs the current account may view.
   */
  protected function taggedNodeQuery(): array {
    $ids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->sort('nid')
      ->execute();
    return array_map('intval', array_values($ids));
  }

  /**
   * Asserts that the expected ECA grant record row exists in node_access.
   *
   * @param int $nid
   *   The node ID, which is also the expected grant ID (gid) for that node.
   */
  protected function assertGrantRecord(int $nid): void {
    $count = (int) \Drupal::database()->select('node_access', 'na')
      ->condition('na.nid', $nid)
      ->condition('na.realm', self::REALM)
      ->condition('na.gid', $nid)
      ->condition('na.grant_view', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertSame(1, $count, sprintf('A node_access row exists for node %d in realm "%s" with gid %d and grant_view 1.', $nid, self::REALM, $nid));
  }

  /**
   * Asserts exactly one node_access row exists with the given realm and flags.
   *
   * @param int $nid
   *   The node ID, which is also the expected grant ID (gid) for that node.
   * @param string $realm
   *   The expected grant realm.
   * @param int $view
   *   The expected grant_view value (0 or 1).
   * @param int $update
   *   The expected grant_update value (0 or 1).
   * @param int $delete
   *   The expected grant_delete value (0 or 1).
   */
  protected function assertGrantRecordFull(int $nid, string $realm, int $view, int $update, int $delete): void {
    $count = (int) \Drupal::database()->select('node_access', 'na')
      ->condition('na.nid', $nid)
      ->condition('na.realm', $realm)
      ->condition('na.gid', $nid)
      ->condition('na.grant_view', $view)
      ->condition('na.grant_update', $update)
      ->condition('na.grant_delete', $delete)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertSame(1, $count, sprintf('Exactly one node_access row exists for node %d in realm "%s" with gid %d and flags view=%d update=%d delete=%d.', $nid, $realm, $nid, $view, $update, $delete));
  }

}
