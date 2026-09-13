<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\PluginManager\Action;
use Drupal\eca_content\Plugin\Action\CoreFieldUpdateAction;
use Drupal\eca_content\Plugin\Action\SetFieldValue;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;
use Drupal\node\Plugin\Action\DemoteNode;
use Drupal\node\Plugin\Action\PromoteNode;
use Drupal\node\Plugin\Action\StickyNode;
use Drupal\node\Plugin\Action\UnstickyNode;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the decorator of the action manager.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ActionDecoratorTest extends KernelTestBase {

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
    'eca_test_array',
    'modeler_api',
  ];

  /**
   * The action plugins that core builds on its own FieldUpdateActionBase.
   *
   * Keys are plugin IDs, values are the original plugin classes that must be
   * preserved in the definition as "eca_original_class".
   *
   * @var array<string, class-string>
   */
  protected const CORE_FIELD_UPDATE_ACTIONS = [
    'node_promote_action' => PromoteNode::class,
    'node_unpromote_action' => DemoteNode::class,
    'node_make_sticky_action' => StickyNode::class,
    'node_make_unsticky_action' => UnstickyNode::class,
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
  }

  /**
   * Tests the expected behavior of the action manager decorator.
   */
  public function testDecorator(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    $decorated_manager = Action::get()->getDecoratedActionManager();
    $this->assertTrue($action_manager instanceof Action, "Action manager must be the decorator.");
    $this->assertSame($action_manager, Action::get());
    $this->assertNotSame($action_manager, $decorated_manager);
    $this->assertFalse($decorated_manager instanceof Action);
    $this->assertTrue($decorated_manager instanceof ActionManager);

    $filtered_definitions = $action_manager->getDefinitions();
    $unfiltered_definitions = $decorated_manager->getDefinitions();
    $this->assertTrue(isset($filtered_definitions['action_send_email_action']));
    $this->assertFalse(isset($filtered_definitions['eca_test_array_increment']));
    $this->assertFalse(isset($filtered_definitions['eca_test_array_write']));
    $this->assertTrue(isset($unfiltered_definitions['action_send_email_action']));
    $this->assertTrue(isset($unfiltered_definitions['eca_test_array_increment']));
    $this->assertTrue(isset($unfiltered_definitions['eca_test_array_write']));

    $this->assertTrue($action_manager->hasDefinition('eca_test_array_write'), "Decorator must have definition when explicitly requested.");
    $this->assertTrue($decorated_manager->hasDefinition('eca_test_array_write'));
    $this->assertTrue($action_manager->createInstance('eca_test_array_write') instanceof ArrayWrite);
    $this->assertTrue($decorated_manager->createInstance('eca_test_array_write') instanceof ArrayWrite);
  }

  /**
   * Tests the remap of the action plugins built on core's field update base.
   *
   * Those plugins must stay visible both in ECA's decorator and in the
   * decorated inner manager, and their class must have been swapped for ECA's
   * replacement while the original class is preserved in the definition.
   */
  public function testCoreFieldUpdateActionRemap(): void {
    $action_manager = Action::get();
    $decorated_manager = $action_manager->getDecoratedActionManager();
    $managers = [
      'ECA decorator' => $action_manager,
      'decorated inner manager' => $decorated_manager,
    ];

    foreach (static::CORE_FIELD_UPDATE_ACTIONS as $plugin_id => $original_class) {
      foreach ($managers as $manager_name => $manager) {
        $definitions = $manager->getDefinitions();
        $this->assertArrayHasKey($plugin_id, $definitions, "The $plugin_id action must be available in the $manager_name.");
        $this->assertSame(CoreFieldUpdateAction::class, $definitions[$plugin_id]['class'], "The class of $plugin_id must have been remapped in the $manager_name.");
        $this->assertArrayHasKey('eca_original_class', $definitions[$plugin_id], "The definition of $plugin_id must keep the original class in the $manager_name.");
        $this->assertSame($original_class, $definitions[$plugin_id]['eca_original_class'], "The original class of $plugin_id must have been preserved in the $manager_name.");
        $this->assertTrue($manager->hasDefinition($plugin_id), "The $manager_name must have a definition for $plugin_id.");
        $this->assertInstanceOf(CoreFieldUpdateAction::class, $manager->createInstance($plugin_id), "The $manager_name must instantiate $plugin_id as ECA's replacement.");
      }
    }

    // Plugin discovery must ignore the attribute-less replacement class
    // itself, so no definition other than the remapped ones may use it.
    $remapped = array_keys(array_filter($decorated_manager->getDefinitions(), static function (array $definition): bool {
      return ($definition['class'] ?? NULL) === CoreFieldUpdateAction::class;
    }));
    sort($remapped);
    $expected_ids = array_keys(static::CORE_FIELD_UPDATE_ACTIONS);
    sort($expected_ids);
    $this->assertSame($expected_ids, $remapped, "Only the actions built on core's field update base may use ECA's replacement class.");

    // ECA's own subclass of the replacement base must not be remapped, and it
    // must remain hidden outside of ECA.
    $unfiltered_definitions = $decorated_manager->getDefinitions();
    $this->assertArrayHasKey('eca_set_field_value', $unfiltered_definitions);
    $this->assertSame(SetFieldValue::class, $unfiltered_definitions['eca_set_field_value']['class']);
    $this->assertArrayNotHasKey('eca_original_class', $unfiltered_definitions['eca_set_field_value']);
    $this->assertArrayNotHasKey('eca_set_field_value', $action_manager->getDefinitions());
  }

}
