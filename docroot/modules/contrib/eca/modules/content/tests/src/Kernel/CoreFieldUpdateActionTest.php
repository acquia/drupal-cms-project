<?php

namespace Drupal\Tests\eca_content\Kernel;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\PluginManager\Action;
use Drupal\eca_content\Plugin\Action\CoreFieldUpdateAction;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ECA's replacement of core's field update actions.
 */
#[Group('eca')]
#[Group('eca_content')]
#[RunTestsInSeparateProcesses]
class CoreFieldUpdateActionTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * The modules.
   *
   * @var string[]
   *   The modules.
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
  ];

  /**
   * The remapped action plugins and the field change each one performs.
   *
   * Keys are plugin IDs, values are the field name, the initial field value
   * and the value the action is expected to set.
   *
   * @var array<string, array{string, int, int}>
   */
  protected const REMAPPED_ACTIONS = [
    'node_promote_action' => ['promote', NodeInterface::NOT_PROMOTED, NodeInterface::PROMOTED],
    'node_unpromote_action' => ['promote', NodeInterface::PROMOTED, NodeInterface::NOT_PROMOTED],
    'node_make_sticky_action' => ['sticky', NodeInterface::NOT_STICKY, NodeInterface::STICKY],
    'node_make_unsticky_action' => ['sticky', NodeInterface::STICKY, NodeInterface::NOT_STICKY],
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(static::$modules);
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    // Set state so that \Drupal\eca\Processor::isEcaContext returns TRUE for
    // \Drupal\eca_content\Plugin\Action\FieldUpdateActionBase::save, even
    // though the actions get executed here without an event.
    \Drupal::state()->set('_eca_internal_test_context', TRUE);

    $this->createContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Tests that the field map is resolved from the original plugin class.
   *
   * This also proves that the premature entity save of core's base class stays
   * suppressed within an ECA context.
   */
  public function testFieldMapFromOriginalClass(): void {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo(User::load(1));

    $storage = \Drupal::entityTypeManager()->getStorage('node');

    foreach (static::REMAPPED_ACTIONS as $plugin_id => [$field, $initial, $expected]) {
      $node = Node::create([
        'type' => 'article',
        'uid' => 1,
        'title' => $this->randomMachineName(),
        $field => $initial,
      ]);
      $node->save();

      $action = Action::get()->createInstance($plugin_id);
      $this->assertInstanceOf(CoreFieldUpdateAction::class, $action, "The $plugin_id action must be instantiated as ECA's replacement.");
      $this->assertTrue($action->access($node, NULL, TRUE)->isAllowed(), "The privileged user must have access to execute $plugin_id.");

      $action->execute($node);
      $this->assertSame((int) $expected, (int) $node->get($field)->value, "The $plugin_id action must set the $field field on the given entity.");

      // The entity must not have been saved by the action itself.
      $storage->resetCache();
      /** @var \Drupal\node\NodeInterface $stored */
      $stored = $storage->load($node->id());
      $this->assertSame((int) $initial, (int) $stored->get($field)->value, "The $plugin_id action must not save the entity within an ECA context.");
    }

    $account_switcher->switchBack();
  }

  /**
   * Tests that access without an entity is forbidden instead of fatal.
   *
   * \Drupal\eca\Entity\Objects\EcaAction calls access(NULL, NULL, TRUE) when no
   * entity resolves and only catches \Exception, so an \Error raised here would
   * be an uncaught fatal error instead of a logged ECA warning.
   */
  public function testAccessWithoutEntity(): void {
    foreach (array_keys(static::REMAPPED_ACTIONS) as $plugin_id) {
      $action = Action::get()->createInstance($plugin_id);
      $result = $action->access(NULL, NULL, TRUE);
      $this->assertInstanceOf(AccessResultInterface::class, $result, "Access of $plugin_id without an entity must return an access result object.");
      $this->assertTrue($result->isForbidden(), "Access of $plugin_id without an entity must be forbidden.");
      $this->assertInstanceOf(AccessResultReasonInterface::class, $result);
      $this->assertSame('No entity provided.', $result->getReason(), "Access of $plugin_id without an entity must state the reason.");
    }
  }

  /**
   * Tests that a definition without a usable original class fails loudly.
   */
  public function testMissingOriginalClassFailsLoudly(): void {
    $node = Node::create([
      'type' => 'article',
      'uid' => 1,
      'title' => $this->randomMachineName(),
    ]);

    $definitions = [
      'without the original class' => [
        'id' => 'eca_test_broken_action',
      ],
      'with an unknown original class' => [
        'id' => 'eca_test_broken_action',
        'eca_original_class' => 'Drupal\eca_content\ThereIsNoSuchClass',
      ],
    ];

    foreach ($definitions as $case => $definition) {
      $action = CoreFieldUpdateAction::create($this->container, [], 'eca_test_broken_action', $definition);
      $exception = NULL;
      try {
        $action->execute($node);
      }
      catch (\Throwable $thrown) {
        $exception = $thrown;
      }
      $this->assertInstanceOf(\LogicException::class, $exception, "A definition $case must throw a logic exception.");
      $this->assertStringContainsString('eca_test_broken_action', $exception->getMessage(), "The exception for a definition $case must name the plugin ID.");
    }
  }

  /**
   * Tests that an unusable original class throws instead of raising an \Error.
   *
   * Bypassing the constructor of the original class leaves any service property
   * it declares uninitialized, and reading such a property raises an \Error.
   * SetFieldValue serves as a stand-in for a subclass built that way, because
   * its field map reads the injected token services. ECA only catches
   * \Exception, so an \Error must not escape.
   */
  public function testUnusableOriginalClassFailsLoudly(): void {
    $node = Node::create([
      'type' => 'article',
      'uid' => 1,
      'title' => $this->randomMachineName(),
    ]);

    $original_class = 'Drupal\eca_content\Plugin\Action\SetFieldValue';
    $action = CoreFieldUpdateAction::create($this->container, [], 'eca_test_unusable_action', [
      'id' => 'eca_test_unusable_action',
      'eca_original_class' => $original_class,
    ]);

    $exception = NULL;
    try {
      $action->execute($node);
    }
    catch (\Throwable $thrown) {
      $exception = $thrown;
    }
    $this->assertInstanceOf(\RuntimeException::class, $exception, 'An original class that cannot supply its field map must throw a runtime exception.');
    $this->assertNotInstanceOf(\Error::class, $exception, 'An original class that cannot supply its field map must not raise an error.');
    $this->assertStringContainsString('eca_test_unusable_action', $exception->getMessage(), 'The exception must name the plugin ID.');
    $this->assertStringContainsString($original_class, $exception->getMessage(), 'The exception must name the original class.');
  }

}
