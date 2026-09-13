<?php

namespace Drupal\Tests\eca\Unit\Token;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\eca\PluginManager\Event as EventPluginManager;
use Drupal\eca\Token\Browser;
use Drupal\eca\Token\TokenServices;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the per-path cycle guard across two distinct entities.
 *
 * Issue #3590446. The existing BrowserCycleGuardTest covers the shape from
 * #3590324, where a single entity references itself ("employer_id ->
 * employer_id"). In that shape the root entity and the referenced entity are
 * the same object, so any lookup that asks the root entity for the current
 * field name happens to return the right entity at every depth.
 *
 * This test uses the guard's own documented back-reference example instead
 * ("owner -> user_picture -> owner"): two distinct entities that reference
 * each other, reached through two differently named fields.
 *
 *   contact:1 --(ref_profile)--> profile:2 --(ref_contact)--> contact:1
 *
 * Correct behavior is that the root entity is expanded exactly once along
 * this path: after "contact:ref_profile" has expanded profile:2, the
 * "ref_contact" sub-token resolves back to contact:1, which is already on the
 * path, so it must not be expanded a second time.
 *
 * The two test methods deliberately separate the two possible failure shapes:
 *   - ::testBackReferenceIsNotExpandedTwice() asserts the observable outcome
 *     (was the root re-expanded?).
 *   - ::testGuardResolvesAgainstTheReferencedEntity() asserts the mechanism
 *     (which entity did the guard interrogate, and for which field?).
 * A failure of only the first would mean the cycle is missed for some other
 * reason; a failure of both points at the resolution step itself.
 *
 * The depth limit is set high enough that it cannot be what terminates the
 * traversal, so that a bounded tree can only be the cycle guard's doing.
 *
 * All doubles are created with ::createStub() and never with ::createMock():
 * they only supply return values, and a mock without expectations raises a
 * notice that the "phpunit (next major)" lane treats as a failure. The probe
 * log below is likewise recorded from a stub callback rather than through
 * ::expects(), for the same reason.
 *
 * @see \Drupal\eca\Token\Browser::normalizeRecursive()
 * @see \Drupal\eca\Token\Browser::resolveReferencedEntity()
 * @see \Drupal\Tests\eca\Unit\Token\BrowserCycleGuardTest
 */
#[Group('eca')]
#[Group('eca_core')]
class BrowserEntityChainCycleGuardTest extends TestCase {

  /**
   * Records every field probe the cycle guard performs.
   *
   * Each entry has the form "<entityType>:<id>::hasField(<fieldName>)" and is
   * appended by the entity stubs below. This is what makes the two failure
   * shapes distinguishable: it shows which entity the guard asked, not just
   * what the guard concluded.
   *
   * @var string[]
   */
  private array $fieldProbes = [];

  /**
   * Creates a Browser with stubbed dependencies and a fixed depth limit.
   *
   * Mirrors the helper of the same name in BrowserCycleGuardTest. It is
   * duplicated rather than shared because that class is the regression test
   * for #3590324 and should stay independent of this diagnostic one.
   *
   * @param \Drupal\eca\Token\TokenServices $tokenServices
   *   The token services stub.
   * @param int $depth
   *   The configured maximum depth.
   *
   * @return \Drupal\eca\Token\Browser
   *   The browser instance.
   */
  private function createBrowser(TokenServices $tokenServices, int $depth): Browser {
    $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
    $eventDispatcher->method('getListeners')->willReturn([]);

    $eventPluginManager = $this->createStub(EventPluginManager::class);
    $eventPluginManager->method('getPluginIdForSystemEvent')
      ->willReturn('test_event_plugin');
    $eventPluginManager->method('getDefinition')
      ->willThrowException(new PluginNotFoundException('test_event_plugin'));

    $state = $this->createStub(StateInterface::class);
    $state->method('get')->willReturnCallback(
      static function (string $key, $default = NULL) use ($depth) {
        if ($key === '_eca_internal_debug_data_depth') {
          return $depth;
        }
        return $default;
      }
    );

    return new Browser(
      $eventDispatcher,
      $tokenServices,
      $eventPluginManager,
      $this->createStub(PrivateTempStoreFactory::class),
      $this->createStub(SharedTempStoreFactory::class),
      $this->createStub(AccountProxyInterface::class),
      $this->createStub(RequestStack::class),
      $this->createStub(TimeInterface::class),
      $state,
    );
  }

  /**
   * Builds the mutually referencing pair of entities.
   *
   * The contact carries a 'ref_profile' reference to the profile, and the
   * profile carries a 'ref_contact' reference back to the contact. The field
   * names are deliberately different, so that asking the wrong entity for a
   * field name cannot accidentally succeed.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The contact (root) and the profile, in that order.
   */
  private function createReferencePair(): array {
    $contact = $this->createStub(ContentEntityInterface::class);
    $contact->method('getEntityTypeId')->willReturn('contact');
    $contact->method('id')->willReturn('1');
    $contact->method('uuid')->willReturn('11111111-1111-1111-1111-111111111111');
    $contact->method('isNew')->willReturn(FALSE);

    $profile = $this->createStub(ContentEntityInterface::class);
    $profile->method('getEntityTypeId')->willReturn('profile');
    $profile->method('id')->willReturn('2');
    $profile->method('uuid')->willReturn('22222222-2222-2222-2222-222222222222');
    $profile->method('isNew')->willReturn(FALSE);

    $contactToProfile = $this->createStub(EntityReferenceFieldItemListInterface::class);
    $contactToProfile->method('referencedEntities')->willReturn([$profile]);

    $profileToContact = $this->createStub(EntityReferenceFieldItemListInterface::class);
    $profileToContact->method('referencedEntities')->willReturn([$contact]);

    $contact->method('hasField')->willReturnCallback(
      function (string $name): bool {
        $this->fieldProbes[] = 'contact:1::hasField(' . $name . ')';
        return $name === 'ref_profile';
      }
    );
    $contact->method('get')->willReturnCallback(
      static fn(string $name) => $name === 'ref_profile' ? $contactToProfile : NULL,
    );

    $profile->method('hasField')->willReturnCallback(
      function (string $name): bool {
        $this->fieldProbes[] = 'profile:2::hasField(' . $name . ')';
        return $name === 'ref_contact';
      }
    );
    $profile->method('get')->willReturnCallback(
      static fn(string $name) => $name === 'ref_contact' ? $profileToContact : NULL,
    );

    return [$contact, $profile];
  }

  /**
   * Builds token info for the two mutually referencing types.
   *
   * @return array
   *   The token info array.
   */
  private function chainTokenInfo(): array {
    return [
      'types' => [
        'contact' => [
          'name' => 'Contact',
          'needs-data' => 'contact',
        ],
        'profile' => [
          'name' => 'Profile',
          'needs-data' => 'profile',
        ],
      ],
      'tokens' => [
        'contact' => [
          'ref_profile' => [
            'name' => 'Profile Reference',
            'type' => 'profile',
          ],
        ],
        'profile' => [
          'ref_contact' => [
            'name' => 'Contact Reference',
            'type' => 'contact',
          ],
        ],
      ],
    ];
  }

  /**
   * Runs a normalization over the mutually referencing pair.
   *
   * @param int $depth
   *   The configured maximum depth.
   *
   * @return array
   *   The normalized token data.
   */
  private function normalizeReferencePair(int $depth): array {
    [$contact] = $this->createReferencePair();

    $tokenServices = $this->createStub(TokenServices::class);
    $tokenServices->method('getInfo')->willReturn($this->chainTokenInfo());
    $tokenServices->method('getTokenData')->willReturnCallback(
      static function (?string $key = NULL) use ($contact) {
        if ($key === NULL) {
          return ['contact' => $contact];
        }
        return $key === 'contact' ? $contact : NULL;
      }
    );
    $tokenServices->method('hasTokenData')->willReturnCallback(
      static fn(?string $key = NULL): bool => $key === NULL || $key === 'contact',
    );
    $tokenServices->method('getDataProviders')->willReturn([]);
    $tokenServices->method('replaceClear')->willReturn('');

    return $this->createBrowser($tokenServices, $depth)
      ->normalizedTokenData('test_event');
  }

  /**
   * Collects the token path of every node that was expanded into children.
   *
   * A node counts as expanded when it carries a 'data' key, i.e. when the
   * normalizer recursed into the token type behind it.
   *
   * @param array $tree
   *   The normalized token tree.
   *
   * @return string[]
   *   The token paths of all expanded nodes, in document order.
   */
  private function expandedTokenPaths(array $tree): array {
    $paths = [];
    foreach ($tree as $node) {
      if (!is_array($node) || !isset($node['data']) || !is_array($node['data'])) {
        continue;
      }
      $paths[] = (string) ($node['token'] ?? '');
      $paths = array_merge($paths, $this->expandedTokenPaths($node['data']));
    }
    return $paths;
  }

  /**
   * Tests that a two entity back reference is not expanded a second time.
   *
   * Along the single path contact -> ref_profile -> ref_contact the last hop
   * resolves back to the root contact, which is already on the path. The
   * guard must therefore stop there, leaving 'contact:ref_profile:ref_contact'
   * without any expanded children.
   */
  public function testBackReferenceIsNotExpandedTwice(): void {
    // A generous depth budget, so that the depth cap cannot be mistaken for
    // the cycle guard: if the tree terminates, only the guard can have done it.
    $result = $this->normalizeReferencePair(8);

    $this->assertArrayHasKey('contact', $result);
    $expanded = $this->expandedTokenPaths($result);

    // The first two expansions are legitimate: the root itself, and the
    // distinct profile entity it references.
    $this->assertContains('contact', $expanded);
    $this->assertContains('contact:ref_profile', $expanded);

    // The third hop returns to the root entity and must not be expanded.
    $this->assertNotContains(
      'contact:ref_profile:ref_contact',
      $expanded,
      sprintf(
        "The back reference to the root entity (contact:1) was expanded a second time along one path.\nExpanded paths: %s\nField probes: %s",
        implode(', ', $expanded),
        implode(', ', $this->fieldProbes),
      ),
    );
  }

  /**
   * Tests that the guard interrogates the referenced entity, not the root.
   *
   * At the second level the current token type is 'profile' and the sub-token
   * is 'ref_contact', which is a field of profile:2. The cycle guard can only
   * resolve that reference if it looks the field up on profile:2. If it
   * instead asks the root contact:1, the lookup silently misses and no cycle
   * is ever detected.
   *
   * This is the mechanism behind the outcome asserted above, and it also
   * records which of the two recursion paths the chain traveled: only the
   * recursion in ::normalizeValue() rebinds the token data to the referenced
   * entity, so a probe against profile:2 proves that route was taken.
   */
  public function testGuardResolvesAgainstTheReferencedEntity(): void {
    $this->normalizeReferencePair(8);

    $this->assertContains(
      'contact:1::hasField(ref_profile)',
      $this->fieldProbes,
      'The guard never resolved the first hop against the root entity.',
    );

    $this->assertContains(
      'profile:2::hasField(ref_contact)',
      $this->fieldProbes,
      sprintf(
        "The guard never asked profile:2 for its own 'ref_contact' field, so the back reference could not be resolved.\nField probes: %s",
        implode(', ', $this->fieldProbes),
      ),
    );

    // The root entity has no 'ref_contact' field. Asking it for one is the
    // signature of a lookup performed against the wrong entity.
    $this->assertNotContains(
      'contact:1::hasField(ref_contact)',
      $this->fieldProbes,
      sprintf(
        "The guard asked the root entity contact:1 for 'ref_contact', a field that belongs to profile:2.\nField probes: %s",
        implode(', ', $this->fieldProbes),
      ),
    );
  }

}
