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
 * Tests the depth cap and per-path cycle guard in the token Browser.
 *
 * Covers issue #3590324: self-referencing entity-reference chains used to
 * re-open a fresh expansion budget for the same entity, causing the
 * normalized debug/replay tree to explode (observed depth 11, 200,000+
 * nodes, 28 MB per step). After the fix the normalization must:
 *   - never produce a tree deeper than the configured depth limit, and
 *   - never expand the same entity twice along a single path.
 *
 * The test constructs a self-referencing content entity whose
 * entity-reference field points back to itself, mirroring the
 * "employer_id -> employer_id" loop from the real debug data.
 *
 * @see \Drupal\eca\Token\Browser::normalizeValue()
 * @see \Drupal\eca\Token\Browser::normalizeRecursive()
 */
#[Group('eca')]
#[Group('eca_core')]
class BrowserCycleGuardTest extends TestCase {

  /**
   * Creates a Browser with stubbed dependencies and a fixed depth limit.
   *
   * @param \Drupal\eca\Token\TokenServices $tokenServices
   *   The token services mock.
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
   * Builds a content entity that references itself through 'employer_id'.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $id
   *   The entity id.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The self-referencing entity mock.
   */
  private function createSelfReferencingEntity(string $entityTypeId, string $id): ContentEntityInterface {
    $entity = $this->createStub(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entityTypeId);
    $entity->method('id')->willReturn($id);
    $entity->method('isNew')->willReturn(FALSE);

    $itemList = $this->createStub(EntityReferenceFieldItemListInterface::class);
    // The reference points back to the very same entity -> cycle.
    $itemList->method('referencedEntities')->willReturn([$entity]);

    $entity->method('hasField')->willReturnCallback(
      static fn(string $name): bool => $name === 'employer_id',
    );
    $entity->method('get')->willReturnCallback(
      static function (string $name) use ($itemList) {
        return $name === 'employer_id' ? $itemList : NULL;
      }
    );

    return $entity;
  }

  /**
   * Builds token info describing a single self-referencing entity type.
   *
   * The type 'contact' has exactly one token, 'employer_id', whose type is
   * again 'contact'. This is the minimal definition that reproduces the
   * "employer_id -> employer_id" recursion.
   *
   * @return array
   *   The token info array.
   */
  private function selfReferencingTokenInfo(): array {
    return [
      'types' => [
        'contact' => [
          'name' => 'Contact',
          'needs-data' => 'contact',
        ],
      ],
      'tokens' => [
        'contact' => [
          'employer_id' => [
            'name' => 'Employer',
            'type' => 'contact',
          ],
        ],
      ],
    ];
  }

  /**
   * Computes the maximum nesting depth of a normalized token tree.
   *
   * The top-level value is depth 1, its children depth 2, and so on.
   *
   * @param array $tree
   *   The normalized token tree.
   * @param int $level
   *   The current level (internal).
   *
   * @return int
   *   The maximum depth found.
   */
  private function maxDepth(array $tree, int $level = 1): int {
    $max = $level;
    foreach ($tree as $node) {
      if (is_array($node) && isset($node['data']) && is_array($node['data'])) {
        $max = max($max, $this->maxDepth($node['data'], $level + 1));
      }
    }
    return $max;
  }

  /**
   * Counts how deep a single self-referencing chain was expanded.
   *
   * Walks straight down the 'employer_id' branch and counts the number of
   * nested expansions.
   *
   * @param array $tree
   *   The normalized token tree (top level).
   *
   * @return int
   *   The number of nested 'employer_id' expansions.
   */
  private function countReferenceChain(array $tree): int {
    $count = 0;
    $node = $tree['employer_id'] ?? NULL;
    while (is_array($node) && isset($node['data']['employer_id'])) {
      $count++;
      $node = $node['data']['employer_id'];
    }
    return $count;
  }

  /**
   * Tests that a self-referencing entity is capped and not expanded twice.
   */
  public function testSelfReferenceIsCappedAndDeduplicated(): void {
    $entity = $this->createSelfReferencingEntity('contact', '42');

    $tokenServices = $this->createStub(TokenServices::class);
    $tokenServices->method('getInfo')
      ->willReturn($this->selfReferencingTokenInfo());
    $tokenServices->method('getTokenData')
      ->willReturnCallback(
        static function (?string $key = NULL) use ($entity) {
          if ($key === NULL) {
            return ['contact' => $entity];
          }
          return $key === 'contact' ? $entity : NULL;
        }
      );
    $tokenServices->method('hasTokenData')
      ->willReturnCallback(
        static fn(?string $key = NULL): bool => $key === NULL || $key === 'contact',
      );
    $tokenServices->method('getDataProviders')->willReturn([]);
    $tokenServices->method('replaceClear')->willReturn('');

    $depth = 5;
    $browser = $this->createBrowser($tokenServices, $depth);
    $result = $browser->normalizedTokenData('test_event');

    $this->assertArrayHasKey('contact', $result);

    // (a) The produced tree must never exceed the configured depth limit.
    $maxDepth = $this->maxDepth($result);
    $this->assertLessThanOrEqual(
      $depth,
      $maxDepth,
      sprintf('Normalized tree depth %d exceeds the configured cap of %d.', $maxDepth, $depth),
    );

    // (b) The cyclic "employer_id -> employer_id" reference must NOT expand
    // the same entity twice along one path. The self-reference resolves back
    // to the same entity (contact:42), so after expanding it once the guard
    // must stop. We therefore expect at most a single nested expansion.
    $tree = $result['contact']['data'] ?? [];
    $chainLength = $this->countReferenceChain($tree);
    $this->assertLessThanOrEqual(
      1,
      $chainLength,
      sprintf('Cyclic entity reference expanded %d times along one path; expected at most 1.', $chainLength),
    );
  }

  /**
   * Tests that the depth cap alone also bounds non-cyclic deep references.
   *
   * Uses distinct entity ids on every hop so the cycle guard never fires;
   * the depth limit must still bound the tree.
   */
  public function testDistinctReferenceChainBoundedByDepth(): void {
    // Each access to the reference returns a brand-new entity with a unique
    // id, so the cycle guard never triggers and only the depth cap applies.
    // Build a chain of distinct entities, each referencing the next, so the
    // cycle guard never fires and only the depth cap can bound the tree.
    $entities = [];
    for ($i = 1; $i <= 10; $i++) {
      $entity = $this->createStub(ContentEntityInterface::class);
      $entity->method('getEntityTypeId')->willReturn('contact');
      $entity->method('id')->willReturn((string) $i);
      $entity->method('isNew')->willReturn(FALSE);
      $entity->method('hasField')
        ->willReturnCallback(static fn(string $name): bool => $name === 'employer_id');
      $entities[$i] = $entity;
    }
    foreach ($entities as $i => $entity) {
      $next = $entities[$i + 1] ?? $entities[10];
      $itemList = $this->createStub(EntityReferenceFieldItemListInterface::class);
      $itemList->method('referencedEntities')->willReturn([$next]);
      $entity->method('get')->willReturnCallback(
        static function (string $name) use ($itemList) {
          return $name === 'employer_id' ? $itemList : NULL;
        }
      );
    }

    $root = $entities[1];

    $tokenServices = $this->createStub(TokenServices::class);
    $tokenServices->method('getInfo')
      ->willReturn($this->selfReferencingTokenInfo());
    $tokenServices->method('getTokenData')
      ->willReturnCallback(
        static function (?string $key = NULL) use ($root) {
          if ($key === NULL) {
            return ['contact' => $root];
          }
          return $key === 'contact' ? $root : NULL;
        }
      );
    $tokenServices->method('hasTokenData')
      ->willReturnCallback(
        static fn(?string $key = NULL): bool => $key === NULL || $key === 'contact',
      );
    $tokenServices->method('getDataProviders')->willReturn([]);
    $tokenServices->method('replaceClear')->willReturn('');

    $depth = 3;
    $browser = $this->createBrowser($tokenServices, $depth);
    $result = $browser->normalizedTokenData('test_event');

    $maxDepth = $this->maxDepth($result);
    $this->assertLessThanOrEqual(
      $depth,
      $maxDepth,
      sprintf('Normalized tree depth %d exceeds the configured cap of %d.', $maxDepth, $depth),
    );
  }

}
