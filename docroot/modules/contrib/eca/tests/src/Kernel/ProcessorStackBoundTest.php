<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Processor;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the execution stack is bounded, not merely checked for repeats.
 *
 * Covers issue #3590435.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ProcessorStackBoundTest extends KernelTestBase {

  /**
   * The depth at which the test gives up and calls the stack unbounded.
   *
   * A red run has no natural end, so the observing listener aborts the chain
   * once the stack passes this depth. Without that, a regression would not
   * fail the test, it would hang the run until PHP exhausted its memory. The
   * bounded case peaks at 20 with the shipped defaults, so this leaves ample
   * headroom while still terminating quickly.
   */
  protected const int RUNAWAY_DEPTH = 60;

  /**
   * The message of the throwable used to abort a runaway chain.
   *
   * @var string
   */
  protected const string RUNAWAY_MESSAGE = 'Execution stack is running away.';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'eca',
    'eca_test_array',
    'modeler_api',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
  }

  /**
   * Saves a model that re-triggers its own event and then triggers another.
   *
   * This is the witness shape from the issue. Writing $ownKey again re-enters
   * this very model, and writing $otherKey enters the other one, so the two
   * models feed each other without ever storing any state.
   *
   * @param string $id
   *   The model ID.
   * @param string $ownKey
   *   The array key this model reacts on, and re-writes first.
   * @param string $otherKey
   *   The array key of the other model, written second.
   */
  private function saveWitnessModel(string $id, string $ownKey, string $otherKey): void {
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => $id,
      'label' => 'ECA ' . $id,
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'ev' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Write event',
          'configuration' => ['key' => $ownKey, 'value' => 'go'],
          'successors' => [
            ['id' => 'again', 'condition' => ''],
            ['id' => 'other', 'condition' => ''],
          ],
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => [
        'again' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Re-trigger own event',
          'configuration' => ['key' => $ownKey, 'value' => 'go'],
          'successors' => [],
        ],
        'other' => [
          'plugin' => 'eca_test_array_write',
          'label' => 'Trigger the other model',
          'configuration' => ['key' => $otherKey, 'value' => 'go'],
          'successors' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Saves both witness models.
   */
  private function saveWitness(): void {
    $this->saveWitnessModel('model_a', 'a', 'b');
    $this->saveWitnessModel('model_b', 'b', 'a');
  }

  /**
   * Returns the processor's execution history.
   *
   * @return array
   *   The current stack.
   */
  private function history(): array {
    return (array) (new \ReflectionProperty(Processor::class, 'executionHistory'))
      ->getValue(\Drupal::service('eca.processor'));
  }

  /**
   * Sets the occurrence threshold on the processor service.
   *
   * The value normally comes from a container parameter, which a kernel test
   * cannot change after the container is built, so it is written directly.
   *
   * @param int $max
   *   The threshold, or 0 to disable the rule entirely, which reproduces the
   *   behavior before this issue.
   */
  private function setMaxEventOccurrences(int $max): void {
    (new \ReflectionProperty(Processor::class, 'maxEventOccurrences'))
      ->setValue(\Drupal::service('eca.processor'), $max === 0 ? PHP_INT_MAX : $max);
  }

  /**
   * Observes the stack depth and aborts once it runs away.
   *
   * @param array $depths
   *   Collects the observed depth of every execution, by reference.
   */
  private function observeDepth(array &$depths): void {
    \Drupal::service('event_dispatcher')->addListener(
      EcaEvents::BEFORE_INITIAL_EXECUTION,
      function () use (&$depths): void {
        $depth = count($this->history());
        $depths[] = $depth;
        if ($depth > self::RUNAWAY_DEPTH) {
          throw new \RuntimeException(self::RUNAWAY_MESSAGE);
        }
      },
      -1000,
    );
  }

  /**
   * Starts the witness by writing the key model A reacts on.
   */
  private function trigger(): void {
    \Drupal::service('plugin.manager.action')
      ->createInstance('eca_test_array_write', ['key' => 'a', 'value' => 'go'])
      ->execute();
  }

  /**
   * Tests that the witness runs away without the occurrence threshold.
   *
   * This is the claim the issue makes, so it is asserted rather than assumed.
   *
   * The observing listener throws once the stack passes ::RUNAWAY_DEPTH. That
   * throwable is not expected to reach this test: Objects\EcaAction::execute()
   * catches what a nested action raises and logs it, so the throw acts as a
   * pruning device that keeps the run finite rather than as a signal. The
   * assertion is therefore on the depth that was actually observed, which is
   * the thing being claimed anyway.
   */
  public function testWitnessRunsAwayWithoutTheOccurrenceThreshold(): void {
    $this->saveWitness();
    $this->setMaxEventOccurrences(0);

    $depths = [];
    $this->observeDepth($depths);

    try {
      $this->trigger();
    }
    catch (\RuntimeException $e) {
      // Tolerated: the guard fired on the outermost chain rather than a
      // nested one, so it escaped instead of being caught and logged.
      $this->assertSame(self::RUNAWAY_MESSAGE, $e->getMessage());
    }

    $this->assertGreaterThan(
      self::RUNAWAY_DEPTH,
      max($depths),
      'With the block rule alone the stack must exceed any depth chosen, which is what unbounded means here.',
    );
  }

  /**
   * Tests that the occurrence threshold bounds the witness.
   *
   * The bound is not just smaller; it is exactly the threshold multiplied by
   * the number of distinct enabled event objects, of which the witness has two.
   */
  public function testWitnessStackIsBounded(): void {
    $this->saveWitness();

    $depths = [];
    $this->observeDepth($depths);

    // No throwable may escape: the chain has to end on its own.
    $this->trigger();

    $this->assertNotEmpty($depths, 'The witness must actually have executed.');
    $this->assertLessThanOrEqual(
      Processor::DEFAULT_MAX_EVENT_OCCURRENCES * 2,
      max($depths),
      'The stack must be bounded by the threshold times the number of distinct event objects.',
    );
    $this->assertSame([], $this->history(), 'The stack must be fully unwound afterwards.');
  }

  /**
   * Tests the constructed bound at several thresholds.
   *
   * Two distinct event objects are enabled, so the stack must peak at exactly
   * twice the threshold. Asserting the exact peak rather than an upper bound
   * pins the formula, not just the fact that something got smaller.
   */
  public function testStackPeaksAtThresholdTimesDistinctEvents(): void {
    $this->saveWitness();

    foreach ([2, 3, 5] as $threshold) {
      $this->setMaxEventOccurrences($threshold);
      $depths = [];
      $this->observeDepth($depths);

      $this->trigger();

      $this->assertSame(
        $threshold * 2,
        max($depths),
        sprintf('At a threshold of %d over two event objects the stack must peak at exactly %d.', $threshold, $threshold * 2),
      );
    }
  }

  /**
   * Tests that a legitimately nested event is still allowed.
   *
   * The stack "A, X, A" pushing "A" is the case where the two rules disagree.
   * The repetition rule allows it, because the block "A" is preceded by "X",
   * and the occurrence rule must go on allowing it at the shipped default,
   * otherwise choosing a separate threshold would have bought nothing.
   */
  public function testLegitimateNestingIsStillAllowed(): void {
    /** @var \Drupal\eca\Processor $processor */
    $processor = \Drupal::service('eca.processor');
    $this->saveWitnessModel('model_a', 'a', 'b');
    $eca = Eca::load('model_a');
    $ecaEvent = $eca->getEcaEvent('ev');

    $method = new \ReflectionMethod(Processor::class, 'eventOccurrenceLimitReached');
    $history = new \ReflectionProperty(Processor::class, 'executionHistory');
    $history->setValue($processor, ['model_a:ev', 'model_a:other', 'model_a:ev']);

    $this->assertFalse(
      $method->invokeArgs($processor, [$eca, $ecaEvent]),
      'An event nested twice through a varying path must still be allowed at the default threshold.',
    );

    $this->assertSame(
      10,
      Processor::DEFAULT_MAX_EVENT_OCCURRENCES,
      'The default is chosen to leave legitimate nesting alone; changing it is a decision, not a tidy-up.',
    );
  }

  /**
   * Tests that the new rule is inert on the stacks the old rule already ends.
   *
   * On a simple periodic stack the two rules count the same thing: for
   * "A, A, A" pushing "A" the occurrence count is 3 and the block repetition
   * level is also 3. Their thresholds are nevertheless offset by one, because
   * "eca.max_recursion_level" counts repetitions *beyond* the first while
   * "eca.max_event_occurrences" counts total occurrences. Comparing the raw
   * numbers would therefore compare two different conventions.
   *
   * What actually matters for safety is that the new rule never fires before
   * the old one on these stacks, so a periodic runaway is still reported as
   * the recursion it is, at the same depth as before. With the shipped
   * defaults the old rule ends both cases at a stack of two while the new one
   * is nowhere near its limit of ten.
   */
  public function testNewRuleIsInertOnPeriodicStacks(): void {
    /** @var \Drupal\eca\Processor $processor */
    $processor = \Drupal::service('eca.processor');
    $this->saveWitnessModel('model_a', 'a', 'b');
    $eca = Eca::load('model_a');
    $ecaEvent = $eca->getEcaEvent('ev');

    $occurrence = new \ReflectionMethod(Processor::class, 'eventOccurrenceLimitReached');
    $block = new \ReflectionMethod(Processor::class, 'recursionThresholdSurpassed');
    $history = new \ReflectionProperty(Processor::class, 'executionHistory');

    // Leave both thresholds at their shipped values.
    $this->setMaxEventOccurrences(Processor::DEFAULT_MAX_EVENT_OCCURRENCES);

    foreach ([
      'repeating event' => ['model_a:ev', 'model_a:ev'],
      'alternating events' => ['model_a:ev', 'model_a:x', 'model_a:ev', 'model_a:x'],
    ] as $label => $stack) {
      $history->setValue($processor, $stack);

      $this->assertTrue(
        $block->invokeArgs($processor, [$eca, $ecaEvent]),
        sprintf('%s: the block rule must still end this chain, as it always did.', $label),
      );
      $this->assertFalse(
        $occurrence->invokeArgs($processor, [$eca, $ecaEvent]),
        sprintf('%s: the occurrence rule must not fire first, so periodic runaways keep being reported at the same depth.', $label),
      );
    }
  }

  /**
   * Tests that the block rule still fires where it always did.
   *
   * The occurrence rule is added alongside it, not in place of it, so a stack
   * that only the block rule catches must still be caught with the occurrence
   * threshold set high enough to be irrelevant.
   */
  public function testBlockRuleStillFiresOnItsOwn(): void {
    /** @var \Drupal\eca\Processor $processor */
    $processor = \Drupal::service('eca.processor');
    $this->saveWitnessModel('model_a', 'a', 'b');
    $eca = Eca::load('model_a');
    $ecaEvent = $eca->getEcaEvent('ev');

    $block = new \ReflectionMethod(Processor::class, 'recursionThresholdSurpassed');
    $occurrence = new \ReflectionMethod(Processor::class, 'eventOccurrenceLimitReached');
    $history = new \ReflectionProperty(Processor::class, 'executionHistory');

    // Two identical entries with the shipped threshold of 1 is a repeat.
    $history->setValue($processor, ['model_a:ev', 'model_a:ev']);
    $this->setMaxEventOccurrences(1000);

    $this->assertTrue(
      $block->invokeArgs($processor, [$eca, $ecaEvent]),
      'The block rule must still halt a directly repeating event.',
    );
    $this->assertFalse(
      $occurrence->invokeArgs($processor, [$eca, $ecaEvent]),
      'With a high occurrence threshold the halt above can only have come from the block rule.',
    );
  }

}
