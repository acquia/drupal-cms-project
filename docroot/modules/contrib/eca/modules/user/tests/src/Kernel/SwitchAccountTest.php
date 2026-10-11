<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_switch_account" action plugin.
 */
#[Group('eca')]
#[Group('eca_user')]
#[RunTestsInSeparateProcesses]
class SwitchAccountTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_user',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
  }

  /**
   * Tests SwitchAccount.
   *
   * The access() assertions below are not an oversight. This action is
   * deliberately not gated on a permission: switching the account is the very
   * purpose of it, and gating it on a permission held by the account *before*
   * the switch would defeat the service account pattern it exists for.
   * Restricting who may cause the switch is a model restriction requirement,
   * documented in the plugin description and on the configuration form. The
   * assertions pin that deliberate permissiveness, so that turning it into a
   * permission check cannot happen silently.
   *
   * @see https://git.drupalcode.org/project/eca/-/work_items/3590400
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testSwitchAccount(): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    // Create an action for switching the user account.
    $defaults = [
      'user_id' => NULL,
    ];
    /** @var \Drupal\eca_user\Plugin\Action\SwitchAccount $action */
    $action = $action_manager->createInstance('eca_switch_account', [] + $defaults);
    $this->assertSame(0, \Drupal::currentUser()->id(), 'User UID must not have been changed yet.');
    $this->assertTrue($action->access(NULL), 'Access must be granted.');
    $this->assertTrue($action->access(User::load(0)), 'Access must be granted.');
    $action->execute();
    $this->assertSame(0, \Drupal::currentUser()->id(), 'User UID must not have been changed because of unspecified UID.');
    $action->cleanupAfterSuccessors();
    $this->assertSame(0, \Drupal::currentUser()->id(), 'User UID must not have been changed because of unspecified UID.');

    /** @var \Drupal\eca_user\Plugin\Action\SwitchAccount $action */
    $action = $action_manager->createInstance('eca_switch_account', [
      'user_id' => '1',
    ] + $defaults);
    $this->assertSame("0", (string) \Drupal::currentUser()->id(), 'User UID must not have been changed yet.');
    $this->assertTrue($action->access(NULL), 'Access must be granted.');
    $this->assertTrue($action->access(User::load(0)), 'Access must be granted.');
    $action->execute();
    $this->assertSame("1", (string) \Drupal::currentUser()->id(), 'User UID must have been changed.');
    $action->cleanupAfterSuccessors();
    $this->assertSame("0", (string) \Drupal::currentUser()->id(), 'User UID must have been changed back to previous UID.');

    $user2 = User::load(2);
    $token_services->addTokenData('user2', $user2);
    /** @var \Drupal\eca_user\Plugin\Action\SwitchAccount $action */
    $action = $action_manager->createInstance('eca_switch_account', [
      'user_id' => '[user2:uid]',
    ] + $defaults);
    $this->assertSame("0", (string) \Drupal::currentUser()->id(), 'User UID must not have been changed yet.');
    $this->assertTrue($action->access(NULL), 'Access must be granted.');
    $this->assertTrue($action->access(User::load(0)), 'Access must be granted.');
    $action->execute();
    $this->assertSame("2", (string) \Drupal::currentUser()->id(), 'User UID must have been changed to UID 2.');
    $action->cleanupAfterSuccessors();
    $this->assertSame("0", (string) \Drupal::currentUser()->id(), 'User UID must have been changed back to previous UID.');
  }

}
