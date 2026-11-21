<?php

namespace Drupal\Tests\eca_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a mid-chain account switch is visible in the debug token data.
 *
 * Covers issue #3590431. The normalized value cache is scoped to one execution
 * chain, which is enough to keep one chain from seeing another chain's data.
 * It is not enough here: the "eca_switch_account" action changes the current
 * user *within* a chain, so the 'user' token contributed by
 * CurrentUserDataProvider legitimately changes between two debug steps of the
 * same chain. Hashing the #[Token] attribute could never see that, so the
 * inspector kept reporting the account the chain started under.
 *
 * @see \Drupal\eca_user\Plugin\Action\SwitchAccount
 * @see \Drupal\eca\Token\CurrentUserDataProvider
 * @see \Drupal\eca\Token\Browser::normalizedTokenData()
 */
#[Group('eca')]
#[Group('eca_user')]
#[RunTestsInSeparateProcesses]
class SwitchAccountTokenBrowserTest extends KernelTestBase {

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
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => ''])->save();
    User::create(['uid' => 1, 'name' => 'chain_starter'])->save();
    User::create(['uid' => 2, 'name' => 'switched_to'])->save();
  }

  /**
   * Tests that the user token reflects a switch made within one chain.
   *
   * Both normalization passes belong to the same execution chain, so nothing
   * resets the normalized value cache in between. That is deliberate: the
   * reset exists to bound memory across chains, not to paper over a validity
   * hash that cannot see its own data change.
   */
  public function testUserTokenReflectsSwitchWithinOneChain(): void {
    /** @var \Drupal\eca\Token\Browser $browser */
    $browser = \Drupal::service('eca.token_browser');
    /** @var \Drupal\Core\Action\ActionManager $actionManager */
    $actionManager = \Drupal::service('plugin.manager.action');

    // The chain starts under UID 1.
    /** @var \Drupal\eca_user\Plugin\Action\SwitchAccount $toStarter */
    $toStarter = $actionManager->createInstance('eca_switch_account', ['user_id' => '1']);
    $toStarter->execute();
    $this->assertSame('1', (string) \Drupal::currentUser()->id());

    $before = $browser->normalizedTokenData('test_event');
    $this->assertSame(
      'chain_starter',
      $before['user']['data']['name']['value'],
      'The first debug step must report the account the chain runs under.',
    );

    // A step later in the very same chain switches the account again.
    /** @var \Drupal\eca_user\Plugin\Action\SwitchAccount $toOther */
    $toOther = $actionManager->createInstance('eca_switch_account', ['user_id' => '2']);
    $toOther->execute();
    $this->assertSame('2', (string) \Drupal::currentUser()->id());

    $after = $browser->normalizedTokenData('test_event');
    $this->assertSame(
      'switched_to',
      $after['user']['data']['name']['value'],
      'A debug step taken after a mid-chain account switch must report the new account.',
    );

    // Unwind, and confirm the token follows that too.
    $toOther->cleanupAfterSuccessors();
    $this->assertSame('1', (string) \Drupal::currentUser()->id());
    $unwound = $browser->normalizedTokenData('test_event');
    $this->assertSame(
      'chain_starter',
      $unwound['user']['data']['name']['value'],
      'Unwinding the switch must be visible in the debug data as well.',
    );

    $toStarter->cleanupAfterSuccessors();
  }

}
