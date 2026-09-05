<?php

namespace Drupal\Tests\eca\Kernel\Token;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\ProcessDebugger;
use Drupal\eca\Token\Browser;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the per-step entity deduplication in the token Browser.
 *
 * Covers issue #3590332: the top-level entity dedup in
 * Browser::normalizedTokenData() used to key on "entityType:id" and was
 * guarded by !$entity->isNew(). That failed for unsaved entities: on the
 * user-registration form, the tokens 'entity' and 'user' are the SAME
 * anonymous user (identical UUID) but have no id yet, so the guard skipped
 * dedup and both trees serialized in full.
 *
 * The fix keys dedup on "entityType:UUID" and drops the isNew() guard.
 * Content entities receive their UUID at object construction (before save),
 * so the UUID is populated even for new entities, and "type:UUID" is globally
 * unique, so it cannot mis-collapse two distinct new entities.
 *
 * @see \Drupal\eca\Token\Browser::normalizedTokenData()
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class BrowserDedupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'modeler_api',
  ];

  /**
   * The token browser.
   *
   * @var \Drupal\eca\Token\Browser
   */
  protected Browser $browser;

  /**
   * The ECA token services.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected $tokenServices;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user']);
    $this->browser = \Drupal::service('eca.token_browser');
    $this->tokenServices = \Drupal::service('eca.token_services');
  }

  /**
   * Tests that the same new entity under two keys is deduplicated by UUID.
   *
   * This reproduces the user-registration form case: the unsaved anonymous
   * user is exposed under both 'entity' and 'user'. Because it is new it has
   * no id, but it does have a UUID, so the second occurrence must be emitted
   * as a '@ref' marker pointing at the first.
   */
  public function testSameNewEntityDeduplicatedByUuid(): void {
    // A genuinely new, unsaved user: id() is NULL, isNew() is TRUE, but the
    // UUID is already populated at construction time.
    $user = User::create([
      'name' => 'anonymous_registration',
      'mail' => 'register@example.com',
    ]);
    $this->assertTrue($user->isNew(), 'The user must be new (unsaved).');
    $this->assertNull($user->id(), 'A new user must not yet have an id.');
    $this->assertNotEmpty($user->uuid(), 'A new user must already have a UUID.');

    // Expose the SAME object under two token keys, as the registration form
    // does with 'entity' and 'user'.
    $this->tokenServices->addTokenData('entity', $user);
    $this->tokenServices->addTokenData('user', $user);

    $result = $this->browser->normalizedTokenData('test_event');

    $this->assertArrayHasKey('entity', $result);
    $this->assertArrayHasKey('user', $result);

    // Exactly one of the two keys must be emitted as a '@ref' marker pointing
    // at the other; the other must be fully normalized. The dedup walks the
    // token data in insertion order, so 'entity' is normalized first and
    // 'user' becomes the reference.
    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_REF,
      $result['entity'],
      'The first occurrence must be fully normalized, not a reference.',
    );
    $this->assertArrayHasKey(
      ProcessDebugger::TOKEN_DATA_REF,
      $result['user'],
      'The second occurrence of the same new entity must be a @ref marker.',
    );
    $this->assertSame(
      'entity',
      $result['user'][ProcessDebugger::TOKEN_DATA_REF],
      'The @ref marker must point at the first token key.',
    );
    // The reference entry must not carry an expanded data sub-tree.
    $this->assertArrayNotHasKey(
      'data',
      $result['user'],
      'The @ref entry must not be expanded into a data sub-tree.',
    );
  }

  /**
   * Tests that distinct new entities of the same type are both normalized.
   *
   * Two different unsaved users have different UUIDs, so neither must be
   * collapsed into a '@ref' marker. This guards against the empty-id
   * mis-collapse that keying on the id would have caused ("user:" == "user:").
   */
  public function testDistinctNewEntitiesAreNotDeduplicated(): void {
    $userA = User::create([
      'name' => 'new_user_a',
      'mail' => 'a@example.com',
    ]);
    $userB = User::create([
      'name' => 'new_user_b',
      'mail' => 'b@example.com',
    ]);
    $this->assertTrue($userA->isNew());
    $this->assertTrue($userB->isNew());
    $this->assertNotSame(
      $userA->uuid(),
      $userB->uuid(),
      'Two distinct new users must have distinct UUIDs.',
    );

    $this->tokenServices->addTokenData('user_a', $userA);
    $this->tokenServices->addTokenData('user_b', $userB);

    $result = $this->browser->normalizedTokenData('test_event');

    $this->assertArrayHasKey('user_a', $result);
    $this->assertArrayHasKey('user_b', $result);

    // Neither key may be collapsed into a reference: they are different
    // entities, so both must be fully normalized.
    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_REF,
      $result['user_a'],
      'Distinct entity A must not be a reference.',
    );
    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_REF,
      $result['user_b'],
      'Distinct entity B must not be a reference.',
    );
  }

}
