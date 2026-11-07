<?php

namespace Drupal\Tests\eca_config\Kernel;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that eca_config actions return access results without cache metadata.
 *
 * Covers issue #3590440. ECA does not attach cache metadata to action access
 * results, a rule settled in #3590399 and now written down on
 * ActionBase::access(). The eca_config actions were the only place in the
 * module that contradicted it: they called ::cachePerPermissions() on every
 * result they handed out.
 *
 * Nothing ever read that metadata - no caller of an ECA action plugin's
 * ::access() keeps the result object - and on ConfigAction it was not even
 * true, because two of its forbidden results are decided by token replacement
 * rather than by the permission set.
 *
 * These tests pin the bare results so that re-adding cacheability is caught
 * rather than merely discouraged by a docblock. Access *reasons* are a
 * separate concern and are asserted here too, so that a future cleanup cannot
 * strip them along with the metadata.
 *
 * @see \Drupal\eca\Plugin\Action\ActionBase::access()
 * @see \Drupal\eca_config\Plugin\Action\ConfigActionBase::access()
 * @see \Drupal\eca_config\Plugin\Action\ConfigAction::access()
 */
#[Group('eca')]
#[Group('eca_config')]
#[RunTestsInSeparateProcesses]
class ConfigAccessCacheabilityTest extends Base {

  /**
   * Asserts that an access result carries no cache metadata at all.
   *
   * A bare AccessResult reports no contexts, no tags and a permanent max-age.
   * Any ::cachePerPermissions(), ::cachePerUser(), ::addCacheContexts(),
   * ::addCacheTags() or max-age call shows up in exactly one of these three.
   *
   * @param mixed $result
   *   The access result to check.
   * @param string $case
   *   A description of the case under test, for the failure message.
   */
  protected function assertNoCacheMetadata(mixed $result, string $case): void {
    $this->assertInstanceOf(AccessResultInterface::class, $result, $case . ': the object form must return an access result.');
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result, $case . ': core access results implement the cacheability interface, which is what makes the emptiness below meaningful.');
    $this->assertSame([], $result->getCacheContexts(), $case . ': an ECA action access result must declare no cache contexts.');
    $this->assertSame([], $result->getCacheTags(), $case . ': an ECA action access result must declare no cache tags.');
    $this->assertSame(Cache::PERMANENT, $result->getCacheMaxAge(), $case . ': an ECA action access result must not set a max-age.');
  }

  /**
   * Returns an account holding "administer site configuration".
   *
   * @return \Drupal\user\Entity\User
   *   The account.
   */
  protected function permittedAccount(): User {
    $account = User::load(2);
    $this->assertTrue($account->hasPermission('administer site configuration'), 'The fixture account must hold the permission under test.');
    return $account;
  }

  /**
   * Tests the ConfigActionBase paths, through one of its subclasses.
   *
   * Both branches of the base implementation are exercised: the allowed one
   * for an account holding the permission, and the forbidden one for anonymous.
   * ConfigWrite is used as the subclass because it also delegates to the base
   * via parent::access(), which lets the same assertions cover the inherited
   * result.
   */
  public function testConfigActionBaseReturnsBareResults(): void {
    /** @var \Drupal\Core\Action\ActionManager $actionManager */
    $actionManager = \Drupal::service('plugin.manager.action');
    $config = [
      'config_name' => 'system.site',
      'config_key' => 'page.front',
      'config_value' => '/eca-frontpage',
      'use_yaml' => FALSE,
      'save_config' => TRUE,
    ];

    $action = $actionManager->createInstance('eca_config_write', $config);
    $forbidden = $action->access(NULL, NULL, TRUE);
    $this->assertFalse($forbidden->isAllowed(), 'Anonymous must be denied.');
    $this->assertNoCacheMetadata($forbidden, 'ConfigActionBase forbidden');

    $action = $actionManager->createInstance('eca_config_write', $config);
    $allowed = $action->access(NULL, $this->permittedAccount(), TRUE);
    $this->assertTrue($allowed->isAllowed(), 'The permitted account must be allowed.');
    $this->assertNoCacheMetadata($allowed, 'ConfigActionBase allowed');
  }

  /**
   * Tests that both ConfigWrite branches are now symmetric.
   *
   * ConfigWrite inherits the allowed result from ConfigActionBase and builds a
   * fresh, bare AccessResult::forbidden() on the YAML-invalid path. While the
   * base attached cache contexts, those two branches disagreed about
   * cacheability for no reason anybody could state. Removing the metadata from
   * the base removes the asymmetry as a side effect, which is what this pins.
   *
   * @see \Drupal\eca_config\Plugin\Action\ConfigWrite::access()
   */
  public function testConfigWriteBranchesAgree(): void {
    /** @var \Drupal\Core\Action\ActionManager $actionManager */
    $actionManager = \Drupal::service('plugin.manager.action');
    $account = $this->permittedAccount();

    $valid = $actionManager->createInstance('eca_config_write', [
      'config_name' => 'system.site',
      'config_key' => 'page',
      'config_value' => "front: /valid\n",
      'use_yaml' => TRUE,
      'validate_yaml' => TRUE,
      'save_config' => TRUE,
    ]);
    $inherited = $valid->access(NULL, $account, TRUE);
    $this->assertTrue($inherited->isAllowed(), 'Valid YAML must keep the inherited allowed result.');
    $this->assertNoCacheMetadata($inherited, 'ConfigWrite inherited allowed');

    $invalid = $actionManager->createInstance('eca_config_write', [
      'config_name' => 'system.site',
      'config_key' => 'page',
      'config_value' => "\tnot: [valid",
      'use_yaml' => TRUE,
      'validate_yaml' => TRUE,
      'save_config' => TRUE,
    ]);
    $rejected = $invalid->access(NULL, $account, TRUE);
    $this->assertFalse($rejected->isAllowed(), 'Invalid YAML must be denied.');
    $this->assertNoCacheMetadata($rejected, 'ConfigWrite YAML invalid');

    $this->assertSame(
      $inherited->getCacheContexts(),
      $rejected->getCacheContexts(),
      'The inherited branch and the locally built branch must agree about cacheability.',
    );
    $this->assertInstanceOf(AccessResultReasonInterface::class, $rejected);
    $this->assertSame(
      'YAML data is not valid.',
      $rejected->getReason(),
      'Dropping cache metadata must not drop the access reason.',
    );
  }

  /**
   * Tests every result path of ConfigAction::access().
   *
   * The two token-decided paths are the interesting ones. Their outcome turns
   * on ::replaceClear() of the configured config name and action ID, so
   * describing them as varying per permission set was inaccurate, not merely
   * unused.
   */
  public function testConfigActionReturnsBareResultsOnEveryPath(): void {
    /** @var \Drupal\Core\Action\ActionManager $actionManager */
    $actionManager = \Drupal::service('plugin.manager.action');
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $configActionManager */
    $configActionManager = \Drupal::service('plugin.manager.config_action');
    $definitions = $configActionManager->getDefinitions();
    $this->assertNotEmpty($definitions, 'Expected at least one config action plugin.');
    $actionId = (string) array_key_first($definitions);
    $account = $this->permittedAccount();

    $config = [
      'config_name' => 'system.site',
      'action_id' => $actionId,
      'data' => '',
    ];

    // Path 1: no permission at all.
    $action = $actionManager->createInstance('eca_config_action', $config);
    $noPermission = $action->access(NULL, NULL, TRUE);
    $this->assertFalse($noPermission->isAllowed());
    $this->assertNoCacheMetadata($noPermission, 'ConfigAction without permission');

    // Path 2: allowed.
    $action = $actionManager->createInstance('eca_config_action', $config);
    $allowed = $action->access(NULL, $account, TRUE);
    $this->assertTrue($allowed->isAllowed());
    $this->assertNoCacheMetadata($allowed, 'ConfigAction allowed');

    // Path 3: decided by token replacement, not by permissions. The config
    // name resolves to an empty string through a token that has no value.
    $action = $actionManager->createInstance('eca_config_action', [
      'config_name' => '[unresolvable:token]',
    ] + $config);
    $blankName = $action->access(NULL, $account, TRUE);
    $this->assertFalse($blankName->isAllowed());
    $this->assertNoCacheMetadata($blankName, 'ConfigAction invalid config name');
    $this->assertInstanceOf(AccessResultReasonInterface::class, $blankName);
    $this->assertSame('Invalid config name.', $blankName->getReason());

    // Path 4: also decided by token replacement.
    $action = $actionManager->createInstance('eca_config_action', [
      'action_id' => 'no_such_config_action',
    ] + $config);
    $badAction = $action->access(NULL, $account, TRUE);
    $this->assertFalse($badAction->isAllowed());
    $this->assertNoCacheMetadata($badAction, 'ConfigAction invalid config action');
    $this->assertInstanceOf(AccessResultReasonInterface::class, $badAction);
    $this->assertSame('Invalid config action.', $badAction->getReason());
  }

  /**
   * Tests that the boolean form is unaffected by any of this.
   *
   * The overwhelming majority of callers take the boolean, so a change to the
   * object form must be invisible to them.
   */
  public function testBooleanFormIsUnchanged(): void {
    /** @var \Drupal\Core\Action\ActionManager $actionManager */
    $actionManager = \Drupal::service('plugin.manager.action');
    $config = [
      'config_name' => 'system.site',
      'config_key' => 'page.front',
      'config_value' => '/eca-frontpage',
      'use_yaml' => FALSE,
      'save_config' => TRUE,
    ];

    $action = $actionManager->createInstance('eca_config_write', $config);
    $this->assertFalse($action->access(NULL), 'Anonymous must be denied in the boolean form.');

    $action = $actionManager->createInstance('eca_config_write', $config);
    $this->assertTrue($action->access(NULL, $this->permittedAccount()), 'The permitted account must be allowed in the boolean form.');
  }

}
