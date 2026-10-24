<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca_user\Plugin\Action\SwitchServiceAccount;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_switch_service_account" action plugin.
 */
#[Group('eca')]
#[Group('eca_user')]
#[RunTestsInSeparateProcesses]
class SwitchServiceAccountTest extends KernelTestBase {

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
    User::create(['uid' => 2, 'name' => 'service'])->save();
  }

  /**
   * Creates the action plugin with the given service user setting.
   *
   * The plugin reads the "service_user" setting once, when it gets
   * instantiated, so that setting has to be in place before the instance is
   * created.
   *
   * @param string $service_user
   *   The value for the "service_user" setting of "eca.settings", either a
   *   numeric user ID, a UUID, or an empty string for no service account.
   *
   * @return \Drupal\eca_user\Plugin\Action\SwitchServiceAccount
   *   The action plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createAction(string $service_user): SwitchServiceAccount {
    $this->config('eca.settings')->set('service_user', $service_user)->save();
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\eca_user\Plugin\Action\SwitchServiceAccount $action */
    $action = $action_manager->createInstance('eca_switch_service_account', []);
    return $action;
  }

  /**
   * Tests that access is granted unconditionally, which is deliberate.
   *
   * This action is intentionally not gated on a permission. Switching the
   * account is the entire purpose of it, and a low privileged trigger elevating
   * to a configured service account is the intended use, not an abuse. Gating
   * it on a permission held by the account *before* the switch would defeat
   * exactly that pattern. Restricting who may cause the switch is therefore the
   * responsibility of the model author, who has to make sure the triggering
   * event and the preceding conditions are not reachable by an account that
   * should not be able to cause it.
   *
   * The assertions below pin that deliberate permissiveness, so that turning it
   * into a permission check cannot happen silently: it would break this test
   * and require a fresh decision.
   *
   * @see https://git.drupalcode.org/project/eca/-/work_items/3590400
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessIsGrantedByDesign(): void {
    // The current user is anonymous and holds no permission whatsoever.
    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'The current user must be anonymous.');

    // With a service account configured. ECA calls access() asking for the
    // access result as an object, so pin that call signature as well.
    // @see \Drupal\eca\Entity\Objects\EcaAction::execute()
    $action = $this->createAction('2');
    $this->assertTrue($action->access(NULL), 'Access must be granted, this action is not gated on purpose.');
    $this->assertTrue($action->access(User::load(0)), 'Access must be granted, this action is not gated on purpose.');
    $access_result = $action->access(NULL, NULL, TRUE);
    $this->assertInstanceOf(AccessResultInterface::class, $access_result);
    $this->assertTrue($access_result->isAllowed(), 'Access must be granted, this action is not gated on purpose.');

    // Without a service account configured.
    $action = $this->createAction('');
    $this->assertTrue($action->access(NULL), 'Access must be granted, this action is not gated on purpose.');
    $this->assertTrue($action->access(User::load(0)), 'Access must be granted, this action is not gated on purpose.');
    $access_result = $action->access(NULL, NULL, TRUE);
    $this->assertInstanceOf(AccessResultInterface::class, $access_result);
    $this->assertTrue($access_result->isAllowed(), 'Access must be granted, this action is not gated on purpose.');
  }

  /**
   * Tests switching to a service account identified by its user ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testSwitchByUid(): void {
    $action = $this->createAction('2');
    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'User UID must not have been changed yet.');
    $action->execute();
    $this->assertSame('2', (string) \Drupal::currentUser()->id(), 'User UID must have been changed to the service account.');
    $action->cleanupAfterSuccessors();
    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'User UID must have been changed back to the previous UID.');
  }

  /**
   * Tests switching to a service account identified by its UUID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testSwitchByUuid(): void {
    $service_user = User::load(2);
    $this->assertNotNull($service_user, 'The service account user must exist.');
    $action = $this->createAction($service_user->uuid());
    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'User UID must not have been changed yet.');
    $action->execute();
    $this->assertSame('2', (string) \Drupal::currentUser()->id(), 'User UID must have been changed to the service account.');
    $action->cleanupAfterSuccessors();
    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'User UID must have been changed back to the previous UID.');
  }

  /**
   * Tests that an empty service account setting makes the action a no-op.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testEmptyServiceAccountSetting(): void {
    $action = $this->createAction('');
    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'User UID must not have been changed yet.');
    $action->execute();
    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'User UID must not have been changed because no service account is configured.');
    // The cleanup must not pop an account that was never pushed onto the
    // stack, so the current user has to stay untouched here as well.
    $action->cleanupAfterSuccessors();
    $this->assertSame('0', (string) \Drupal::currentUser()->id(), 'User UID must not have been changed by the cleanup either.');
  }

  /**
   * Tests the configuration form of this action.
   *
   * The service account is a site wide setting, not a per model one, so the
   * "user_id" field inherited from the parent action must not be part of this
   * form. The notice about restricting the switch within the model, however,
   * must be inherited, as it applies to this action just as much.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testConfigurationForm(): void {
    $action = $this->createAction('2');
    $form = $action->buildConfigurationForm([], new FormState());
    $this->assertArrayNotHasKey('user_id', $form, 'The service account action must not offer a user ID field.');
    $this->assertArrayNotHasKey('user_id', $action->getConfiguration(), 'The service account action must not carry a user ID setting.');
    $this->assertArrayHasKey('eca_account_switch_notice', $form, 'The model restriction notice must be part of the form.');
  }

}
