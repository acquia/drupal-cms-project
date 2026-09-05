<?php

namespace Drupal\Tests\eca\Kernel\Token;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\ProcessDebugger;
use Drupal\eca\Token\Browser;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests cross-step '@same' deduplication against real normalized entity data.
 *
 * Covers issue #3590333: where '@ref' deduplicates the same entity within a
 * single step and '@prev' deduplicates whole identical steps, '@same'
 * collapses a fully-normalized sub-tree that is content-identical to one
 * emitted earlier across steps. Crucially, equality is content-based: an
 * entity that is mutated between steps must NOT collapse, because identity is
 * not equality across time.
 *
 * This test builds genuine normalized token data via
 * Browser::normalizedTokenData() for the same entity at two points in time,
 * assembles a history, and runs the storage-time dedup pass over it.
 *
 * @see \Drupal\eca\ProcessDebugger::deduplicateHistory()
 * @see \Drupal\eca\Token\Browser::normalizedTokenData()
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class BrowserSameTest extends KernelTestBase {

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
   * Normalizes the current token data for the test event.
   *
   * @return array
   *   The normalized token data.
   */
  private function normalize(): array {
    return $this->browser->normalizedTokenData('test_event');
  }

  /**
   * Tests that an entity carried unchanged across two steps collapses.
   */
  public function testUnchangedEntityAcrossStepsCollapses(): void {
    $user = User::create([
      'name' => 'stable_user',
      'mail' => 'stable@example.com',
    ]);
    $user->save();

    // Step 0: snapshot the user under the 'account' token.
    $this->tokenServices->addTokenData('account', $user);
    $step0 = $this->normalize();

    // Step 1: the user is unchanged; snapshot again. The processed-value cache
    // keys on the same serialized value, so the sub-tree is identical.
    $step1 = $this->normalize();

    $history = [
      ['type' => 'started', 'id' => 'event', 'data' => $step0],
      ['type' => 'execute', 'id' => 'action', 'data' => $step1],
    ];

    $deduplicated = ProcessDebugger::deduplicateHistory($history);

    $this->assertArrayHasKey('account', $deduplicated[1]['data']);
    $this->assertArrayHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $deduplicated[1]['data']['account'],
      'An unchanged entity sub-tree in a later step must collapse to @same.',
    );
    $this->assertSame(
      0,
      $deduplicated[1]['data']['account'][ProcessDebugger::TOKEN_DATA_SAME]['step'],
      'The @same marker must point at the first step.',
    );
    $this->assertSame(
      'account',
      $deduplicated[1]['data']['account'][ProcessDebugger::TOKEN_DATA_SAME]['path'],
      'The top-level path must be the bare token key.',
    );

    // Round-trip: expandHistory() reconstructs step 1 to equal step 0's data.
    $expanded = ProcessDebugger::expandHistory($deduplicated);
    $this->assertEquals(
      $expanded[0]['data']['account'],
      $expanded[1]['data']['account'],
      'expandHistory() must reconstruct the collapsed sub-tree identically.',
    );
  }

  /**
   * Tests that an entity mutated between steps is NOT collapsed.
   *
   * This is the correctness guarantee: a changed field yields a different
   * content hash, so no '@same' marker may be emitted.
   */
  public function testMutatedEntityAcrossStepsIsNotCollapsed(): void {
    $user = User::create([
      'name' => 'before_change',
      'mail' => 'change@example.com',
    ]);
    $user->save();

    $this->tokenServices->addTokenData('account', $user);
    $step0 = $this->normalize();

    // Mutate the entity between steps, as an action would.
    $user->setUsername('after_change');
    $user->save();
    $this->tokenServices->addTokenData('account', $user);
    $step1 = $this->normalize();

    // Sanity: the normalized name actually differs between the two snapshots.
    $this->assertNotEquals(
      $step0['account'],
      $step1['account'],
      'The mutated entity must normalize to a different sub-tree.',
    );

    $history = [
      ['type' => 'started', 'id' => 'event', 'data' => $step0],
      ['type' => 'execute', 'id' => 'action', 'data' => $step1],
    ];

    $deduplicated = ProcessDebugger::deduplicateHistory($history);

    $this->assertArrayNotHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $deduplicated[1]['data']['account'],
      'A mutated entity must not be collapsed to @same.',
    );
    $this->assertArrayHasKey(
      'data',
      $deduplicated[1]['data']['account'],
      'The mutated entity must keep its full normalized data.',
    );
  }

  /**
   * Tests the end-to-end storage path keeps '@same' markers compact.
   *
   * The storeHistory() method applies the dedup pass before compression, and
   * the read paths return the compact, marker-bearing data (deferred
   * expansion). This proves the marker survives a real store/retrieve
   * round-trip.
   */
  public function testStorageRoundTripPreservesSameMarker(): void {
    $user = User::create([
      'name' => 'persisted_user',
      'mail' => 'persist@example.com',
    ]);
    $user->save();

    $this->tokenServices->addTokenData('account', $user);
    $step0 = $this->normalize();
    $step1 = $this->normalize();

    $history = [
      ['type' => 'started', 'id' => 'event', 'data' => $step0],
      ['type' => 'execute', 'id' => 'action', 'data' => $step1],
    ];

    $hash = $this->browser->storeHistory($history);
    $this->assertIsString($hash);

    $retrieved = $this->browser->getHistoryByHash($hash);
    $this->assertArrayHasKey(
      ProcessDebugger::TOKEN_DATA_SAME,
      $retrieved[1]['data']['account'],
      'The stored, retrieved history must still carry the compact @same marker.',
    );

    // And it expands back to the original sub-tree server-side.
    $expanded = ProcessDebugger::expandHistory($retrieved);
    $this->assertEquals(
      $expanded[0]['data']['account'],
      $expanded[1]['data']['account'],
    );
  }

}
