<?php

namespace Drupal\Tests\eca_config\Kernel;

use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_config_action" action plugin.
 */
#[Group('eca')]
#[Group('eca_config')]
#[RunTestsInSeparateProcesses]
class ConfigActionTest extends Base {

  /**
   * Tests the boolean and object forms of ConfigAction::access().
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccess(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $config_action_manager */
    $config_action_manager = \Drupal::service('plugin.manager.config_action');
    $definitions = $config_action_manager->getDefinitions();
    $this->assertNotEmpty($definitions, 'Expected at least one config action plugin.');
    $action_id = (string) array_key_first($definitions);

    $config = [
      'config_name' => 'system.site',
      'action_id' => $action_id,
      'data' => '',
    ];

    // Anonymous user (no "administer site configuration" permission) is denied
    // in both the boolean and the object form.
    /** @var \Drupal\eca_config\Plugin\Action\ConfigAction $action */
    $action = $action_manager->createInstance('eca_config_action', $config);
    $this->assertFalse($action->access(NULL), 'Anonymous user must be denied (boolean form).');
    $this->assertFalse($action->access(NULL, NULL, TRUE)->isAllowed(), 'Anonymous user must be denied (object form).');

    // An admin with the permission and a valid action must be allowed in the
    // boolean form. Regression: access() previously always returned FALSE in
    // the boolean form regardless of the result.
    $admin = User::load(1);
    $action = $action_manager->createInstance('eca_config_action', $config);
    $this->assertTrue($action->access(NULL, $admin), 'Admin with permission and valid action must be allowed (boolean form).');
    $this->assertTrue($action->access(NULL, $admin, TRUE)->isAllowed(), 'Admin with permission and valid action must be allowed (object form).');

    // An unknown config action id is denied even for an admin.
    $invalid = $config;
    $invalid['action_id'] = 'no_such_config_action';
    $action = $action_manager->createInstance('eca_config_action', $invalid);
    $this->assertFalse($action->access(NULL, $admin), 'Invalid config action must be denied (boolean form).');
    $this->assertFalse($action->access(NULL, $admin, TRUE)->isAllowed(), 'Invalid config action must be denied (object form).');
  }

}
