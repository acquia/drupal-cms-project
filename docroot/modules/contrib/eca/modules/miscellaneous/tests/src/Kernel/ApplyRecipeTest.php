<?php

namespace Drupal\Tests\eca_misc\Kernel;

use Composer\InstalledVersions;
use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the "eca_apply_recipe" action plugin.
 *
 * Applying a recipe installs modules and executes config actions, so the
 * action is gated on the "administer site configuration" permission. These
 * tests only exercise ::access(), which resolves the configured Composer
 * package name to an install path; they never call ::execute() and therefore
 * never apply a recipe.
 */
#[Group('eca')]
#[Group('eca_misc')]
#[RunTestsInSeparateProcesses]
class ApplyRecipeTest extends KernelTestBase {

  /**
   * A Composer package name that is guaranteed to be installed.
   *
   * ::access() only asks whether the configured package name resolves to an
   * installed Composer package, so any installed package is a valid fixture
   * for it. "drupal/core" is present in every Composer-managed Drupal site,
   * which keeps this test portable across environments.
   */
  protected const VALID_PACKAGE_NAME = 'drupal/core';

  /**
   * A Composer package name that is guaranteed not to be installed.
   */
  protected const INVALID_PACKAGE_NAME = 'drupal/no_such_recipe_package';

  /**
   * The permission that guards the action.
   */
  protected const REQUIRED_PERMISSION = 'administer site configuration';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_misc',
    'modeler_api',
  ];

  /**
   * Action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * A user without the "administer site configuration" permission.
   *
   * @var \Drupal\user\UserInterface|null
   */
  protected ?UserInterface $unprivilegedUser;

  /**
   * A user holding the "administer site configuration" permission by role.
   *
   * @var \Drupal\user\UserInterface|null
   */
  protected ?UserInterface $privilegedUser;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    // The root user bypasses all permission checks.
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    // An ordinary authenticated user, holding no permissions at all.
    $this->unprivilegedUser = User::create([
      'uid' => 2,
      'name' => 'unprivileged',
    ]);
    $this->unprivilegedUser->save();
    // A user that holds the required permission through a role, so that the
    // grant is proven by the permission itself and not by the uid 1 bypass.
    Role::create([
      'id' => 'test_role_eca_misc',
      'label' => 'Test Role ECA misc',
    ])->save();
    user_role_grant_permissions('test_role_eca_misc', [
      self::REQUIRED_PERMISSION,
    ]);
    $this->privilegedUser = User::create([
      'uid' => 3,
      'name' => 'privileged',
      'roles' => ['test_role_eca_misc'],
    ]);
    $this->privilegedUser->save();
    $this->actionManager = \Drupal::service('plugin.manager.action');
  }

  /**
   * Builds an instance of the action for the given package name.
   *
   * @param string $package_name
   *   The Composer package name to configure.
   *
   * @return \Drupal\eca_misc\Plugin\Action\ApplyRecipe
   *   The action plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createAction(string $package_name) {
    return $this->actionManager->createInstance('eca_apply_recipe', [
      'recipe_package_name' => $package_name,
    ]);
  }

  /**
   * Tests that the fixtures used by the access tests behave as assumed.
   */
  public function testPackageNameFixtures(): void {
    $this->assertTrue(
      InstalledVersions::isInstalled(self::VALID_PACKAGE_NAME),
      'The package name used as the valid fixture must be installed, otherwise the access tests would pass for the wrong reason.'
    );
    $this->assertFalse(
      InstalledVersions::isInstalled(self::INVALID_PACKAGE_NAME),
      'The package name used as the invalid fixture must not be installed.'
    );
    $this->assertFalse(
      $this->unprivilegedUser->hasPermission(self::REQUIRED_PERMISSION),
      'The unprivileged user must not hold the required permission.'
    );
    $this->assertTrue(
      $this->privilegedUser->hasPermission(self::REQUIRED_PERMISSION),
      'The privileged user must hold the required permission.'
    );
  }

  /**
   * Tests that a user without the permission is denied for a valid package.
   *
   * This is the security assertion: the package name is valid, so the
   * pre-existing path check alone would grant access. Only the permission
   * gate can deny here.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessDeniedWithoutPermission(): void {
    $action = $this->createAction(self::VALID_PACKAGE_NAME);
    $this->assertFalse(
      $action->access(NULL, $this->unprivilegedUser),
      'A user without the permission must be denied even for a valid package name (boolean form).'
    );
    $result = $action->access(NULL, $this->unprivilegedUser, TRUE);
    $this->assertFalse(
      $result->isAllowed(),
      'A user without the permission must be denied even for a valid package name (object form).'
    );
    $this->assertNotSame(
      'The configured package name is invalid.',
      $result->getReason(),
      'The denial must come from the missing permission, not from the package name check.'
    );
  }

  /**
   * Tests that the current user is used when no account is passed.
   *
   * ECA calls ::access() with a NULL account, so the check runs against the
   * user that the model is executing as.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessDeniedForUnprivilegedCurrentUser(): void {
    $this->container->get('current_user')->setAccount($this->unprivilegedUser);
    $action = $this->createAction(self::VALID_PACKAGE_NAME);
    $this->assertFalse(
      $action->access(NULL),
      'The unprivileged current user must be denied when no account is passed (boolean form).'
    );
    $this->assertFalse(
      $action->access(NULL, NULL, TRUE)->isAllowed(),
      'The unprivileged current user must be denied when no account is passed (object form).'
    );

    $this->container->get('current_user')->setAccount($this->privilegedUser);
    $action = $this->createAction(self::VALID_PACKAGE_NAME);
    $this->assertTrue(
      $action->access(NULL),
      'The privileged current user must be allowed when no account is passed (boolean form).'
    );
    $this->assertTrue(
      $action->access(NULL, NULL, TRUE)->isAllowed(),
      'The privileged current user must be allowed when no account is passed (object form).'
    );
  }

  /**
   * Tests that a permitted user is allowed for a valid package name.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessAllowedWithPermission(): void {
    $action = $this->createAction(self::VALID_PACKAGE_NAME);
    $this->assertTrue(
      $action->access(NULL, $this->privilegedUser),
      'A user with the permission must be allowed for a valid package name (boolean form).'
    );
    $this->assertTrue(
      $action->access(NULL, $this->privilegedUser, TRUE)->isAllowed(),
      'A user with the permission must be allowed for a valid package name (object form).'
    );

    // The root user bypasses permissions and must be allowed as well.
    $admin = User::load(1);
    $this->assertTrue(
      $action->access(NULL, $admin),
      'The root user must be allowed for a valid package name (boolean form).'
    );
    $this->assertTrue(
      $action->access(NULL, $admin, TRUE)->isAllowed(),
      'The root user must be allowed for a valid package name (object form).'
    );
  }

  /**
   * Tests that an invalid package name is denied for a permitted user.
   *
   * The permission gate is added on top of the pre-existing package name
   * check, it does not replace it.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAccessDeniedWithInvalidPackageName(): void {
    $action = $this->createAction(self::INVALID_PACKAGE_NAME);
    $this->assertFalse(
      $action->access(NULL, $this->privilegedUser),
      'An invalid package name must be denied even for a user with the permission (boolean form).'
    );
    $result = $action->access(NULL, $this->privilegedUser, TRUE);
    $this->assertFalse(
      $result->isAllowed(),
      'An invalid package name must be denied even for a user with the permission (object form).'
    );
    $this->assertSame(
      'The configured package name is invalid.',
      $result->getReason(),
      'The pre-existing package name denial reason must be preserved.'
    );

    // An empty package name never resolves and must be denied as well.
    $action = $this->createAction('');
    $this->assertFalse(
      $action->access(NULL, $this->privilegedUser),
      'An empty package name must be denied even for a user with the permission.'
    );
  }

}
