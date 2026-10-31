<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\eca\Entity\Eca;
use Drupal\node\Entity\Node;
use Drupal\workspaces\Entity\Workspace;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for revisionable node types with workspace.
 */
#[Group('eca')]
#[Group('eca_content')]
#[RunTestsInSeparateProcesses]
class ContentUpdateWorkspaceTest extends KernelTestBase {

  use ContentTypeCreationTrait;

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
    'eca_content',
    'modeler_api',
    'workspaces',
  ];

  /**
   * The workspace.
   *
   * @var \Drupal\workspaces\Entity\Workspace
   */
  protected Workspace $workspace;

  /**
   * Indicator if the live workspace is active or the staging workflow.
   *
   * @var bool
   */
  protected bool $isLiveWorkspace = TRUE;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('workspace');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('workspaces', ['workspace_association', 'workspace_association_revision']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'anonymous'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    // Create the Article content type with revisioning enabled.
    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);

    // Create a workspace.
    $this->workspace = Workspace::create([
      'id' => 'stage',
      'label' => 'Stage',
    ]);
    $this->workspace->save();

    // Now switch to privileged user.
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));
  }

  /**
   * Switches to the workspace, or back to the default workspace.
   */
  protected function switchWorkspace(): void {
    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager */
    $workspace_manager = $this->container->get('workspaces.manager');
    if ($this->isLiveWorkspace) {
      $workspace_manager->setActiveWorkspace($this->workspace);
    }
    else {
      $workspace_manager->switchToLive();
    }
    $this->isLiveWorkspace = !$this->isLiveWorkspace;
  }

  /**
   * Asserts whether the node is tracked in the 'stage' workspace.
   *
   * The workspace metadata FIELD ('workspace') is populated at presave and is
   * set regardless of the ECA bug, so asserting on it cannot detect the
   * regression. The actual breakage is in the workspace_association tracking
   * table, written at entity_update time only when getLoadedRevisionId() !=
   * getRevisionId(). ECA's updateLoadedRevisionId() makes them equal, so core
   * skips the tracking unless ECA's hook runs last.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to check.
   * @param bool $expected
   *   TRUE if the node should be tracked in the 'stage' workspace.
   *
   * @see \Drupal\workspaces\Provider\WorkspaceProviderBase::entityUpdate()
   * @see \Drupal\eca_content\Hook\ContentHooks::entityUpdate()
   */
  protected function assertNodeTrackedInStage(Node $node, bool $expected): void {
    /** @var \Drupal\workspaces\WorkspaceTrackerInterface $tracker */
    $tracker = $this->container->get('workspaces.tracker');
    $workspace_ids = $tracker->getEntityTrackingWorkspaceIds($node);
    if ($expected) {
      $this->assertContains('stage', $workspace_ids, 'The node is tracked in the stage workspace.');
    }
    else {
      $this->assertNotContains('stage', $workspace_ids, 'The node is not tracked in the stage workspace.');
    }
  }

  /**
   * Test on node update in a workspace with ECA involved.
   */
  public function testNodeUpdateInWorkspaceWithEcaModel(): void {
    Eca::create([
      'id' => 'test_node_update_revision',
      'label' => 'Test node update revision',
      'events' => [
        'update' => [
          'plugin' => 'content_entity:update',
          'label' => 'Update',
          'configuration' => [
            'type' => 'node article',
          ],
          'successors' => [
            ['id' => 'update_title', 'condition' => ''],
          ],
        ],
      ],
      'actions' => [
        'update_title' => [
          'plugin' => 'eca_set_field_value',
          'label' => 'Set title',
          'configuration' => [
            'field_name' => 'title',
            'field_value' => 'Updated title by ECA',
            'method' => 'set:clear',
            'strip_tags' => FALSE,
            'trim' => FALSE,
            'save_entity' => FALSE,
            'object' => 'node',
          ],
          'successors' => [
            ['id' => 'save', 'condition' => ''],
          ],
        ],
        'save' => [
          'plugin' => 'eca_save_entity',
          'label' => 'Save',
          'configuration' => [
            'object' => 'node',
          ],
        ],
      ],
    ])->save();

    $node = Node::create([
      'title' => 'Initial title',
      'type' => 'article',
    ]);
    $node->save();

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    $revisions_before = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('nid', $node->id())
      ->count()
      ->execute();
    $this->assertEquals(1, $revisions_before);

    $node->set('title', 'Updated title');
    $node->setNewRevision();
    $node->save();

    $revisions_after = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('nid', $node->id())
      ->count()
      ->execute();

    // Reload node to make sure we really test the stored revision.
    $node = Node::load($node->id());

    // Make sure the ECA model got executed.
    $this->assertEquals('Updated title by ECA', $node->label());
    // We expect 2 revisions: one initial, and one from the update.
    // The ECA save action should NOT create a third revision.
    $this->assertEquals(2, $revisions_after);

    $this->switchWorkspace();
    $node->set('title', 'Updated title in workspace');
    $node->setNewRevision();
    $node->save();

    // Reload node to make sure we really test the stored revision.
    $node = Node::load($node->id());

    // The changed node MUST be tracked in the workspace association. This is
    // the assertion that detects the regression: ECA's updateLoadedRevisionId()
    // makes getLoadedRevisionId() equal getRevisionId(), so without the
    // Order::Last fix core Workspaces skips tracking and this assertion fails.
    $this->assertNodeTrackedInStage($node, TRUE);
    // Make sure the ECA model got executed.
    $this->assertEquals('Updated title by ECA', $node->label());
    // Verify that the node is in the 'stage' workspace.
    $this->assertEquals('stage', $node->get('workspace')->target_id);
  }

  /**
   * Test on node update in a workspace.
   */
  public function testNodeUpdateInWorkspaceWithoutEcaModel(): void {
    $node = Node::create([
      'title' => 'Initial title',
      'type' => 'article',
    ]);
    $node->save();

    $this->switchWorkspace();
    $node->set('title', 'Updated title in workspace');
    $node->setNewRevision();
    $node->save();

    // Reload node to make sure we really test the stored revision.
    $node = Node::load($node->id());

    // The core regression: the changed node MUST be tracked in the workspace
    // association. This is the assertion that detects the regression. ECA's
    // updateLoadedRevisionId() makes getLoadedRevisionId() == getRevisionId(),
    // so without the Order::Last fix core Workspaces skips tracking and this
    // assertion fails.
    $this->assertNodeTrackedInStage($node, TRUE);
    // Make sure the title got updated correctly.
    $this->assertEquals('Updated title in workspace', $node->label());
    // Verify that the node is in the 'stage' workspace.
    $this->assertEquals('stage', $node->get('workspace')->target_id);

    // Go to the live workspace and make sure that the node title is unchanged.
    $this->switchWorkspace();
    $node = Node::load($node->id());
    $this->assertEquals('Initial title', $node->label());
  }

}
