<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\EcaEvents;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca_test_array\Plugin\Action\ArrayIncrement;
use Drupal\eca_test_array\Plugin\Action\ArrayWrite;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the recursion detection of the ECA processor engine.
 *
 * These tests drive the real \Drupal\eca\Processor::execute() pipeline with
 * ECA models that trigger each other, so that the execution history is built
 * up by the processor itself instead of being injected by reflection.
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class ProcessorRecursionTest extends KernelTestBase {

  /**
   * The scripted nesting pattern of the misaligned repetition test.
   *
   * Every entry names the model whose execution is nested into the previous
   * one, so the entries repeat the pattern "a, a, b, b". The script is
   * deliberately finite: it acts as the safety net of that test, because an
   * undetected repetition would otherwise nest until PHP runs out of memory.
   * It holds twice as many entries as the recursion detection is expected to
   * allow, which is enough to tell a working detection from a broken one.
   */
  private const array SCRIPT = [
    'a', 'a', 'b', 'b',
    'a', 'a', 'b', 'b',
    'a', 'a', 'b', 'b',
    'a', 'a', 'b', 'b',
  ];

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
   * The number of ECA models that the processor started to execute.
   *
   * @var int
   */
  protected int $numInitialExecutions = 0;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'name' => 'guest'])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    // The test actions keep their results in static properties, which must not
    // leak from one scenario into the next one.
    ArrayWrite::$restrictAccess = FALSE;
    ArrayWrite::$array = [];
    ArrayIncrement::$array = [];

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->addListener(EcaEvents::BEFORE_INITIAL_EXECUTION, function (BeforeInitialExecutionEvent $event) {
      $this->numInitialExecutions++;
    });
  }

  /**
   * Tests that two models triggering each other stop at the threshold.
   *
   * The two models form a cycle: writing "ping" makes the first model write
   * "pong", which makes the second model write "ping" again. Without recursion
   * detection this would never terminate. With the default threshold of one,
   * the repeating block "ping model, pong model" is allowed to occur twice and
   * the execution is halted when it is about to occur a third time.
   */
  public function testRepeatedBlockSurpassesThreshold(): void {
    $this->assertSame(1, \Drupal::getContainer()->getParameter('eca.max_recursion_level'), 'This test assumes the default recursion threshold.');

    $this->createModel('ping_model', 'ping', 'pong');
    $this->createModel('pong_model', 'pong', 'ping');

    $this->triggerWrite('ping');

    // The initial write is the root execution of the ping model, which is
    // never checked for recursion. It triggers the pong model, which triggers
    // the ping model a second time, which triggers the pong model a second
    // time. The third ping model execution completes the repeating block for a
    // second time and is therefore blocked.
    $this->assertSame(2, ArrayIncrement::$array['ping'] ?? 0, 'The ping model must have been executed exactly twice.');
    $this->assertSame(2, ArrayIncrement::$array['pong'] ?? 0, 'The pong model must have been executed exactly twice.');
    $this->assertSame(4, $this->numInitialExecutions, 'The processor must have started exactly four model executions.');
  }

  /**
   * Tests that a chain of models without a cycle runs to completion.
   *
   * This is the control for the test above: the models trigger each other in
   * exactly the same nested way, but never repeat an event, so the recursion
   * detection must not interfere with any of them.
   */
  public function testNonRecursiveChainCompletes(): void {
    $this->createModel('first_model', 'first', 'second');
    $this->createModel('second_model', 'second', 'third');
    $this->createModel('third_model', 'third', NULL);

    $this->triggerWrite('first');

    $this->assertSame(1, ArrayIncrement::$array['first'] ?? 0, 'The first model must have been executed.');
    $this->assertSame(1, ArrayIncrement::$array['second'] ?? 0, 'The second model must have been executed.');
    $this->assertSame(1, ArrayIncrement::$array['third'] ?? 0, 'The third model must have been executed.');
    $this->assertSame(3, $this->numInitialExecutions, 'The processor must have started exactly three model executions.');
  }

  /**
   * Tests a repeating pattern that is not aligned with the checked event.
   *
   * Two models nest their executions into each other by the repeating pattern
   * "A, A, B, B". Looking only at the most recent execution of the model that
   * is about to be executed never reveals that pattern, because that
   * occurrence opens the misaligned block "A, B, B" instead of the block "A,
   * A, B, B" that truly repeats. The execution must nevertheless be halted
   * once the pattern has occurred more often than the threshold allows, which
   * with the default threshold of one is after the eighth nested execution.
   *
   * Building this pattern needs a model to trigger a different successor
   * depending on how deeply it is already nested, which a stateless model
   * cannot do: an event object always has the same successors, so it always
   * triggers the same events. The models below therefore follow a script,
   * kept in the "step" key of the static array, that tells every execution
   * which model to trigger next. That script is also the safety net of this
   * test: it ends after its last entry, so an undetected repetition stops
   * there instead of nesting until PHP runs out of memory.
   */
  public function testMisalignedRepeatingPatternIsHalted(): void {
    $this->assertSame(1, \Drupal::getContainer()->getParameter('eca.max_recursion_level'), 'This test assumes the default recursion threshold.');

    $this->createScriptedModel('pattern_a', 'a');
    $this->createScriptedModel('pattern_b', 'b');

    // Start the script and trigger its first step.
    $this->triggerWrite('step', '1');
    $this->triggerWrite('a');

    $this->assertSame(8, $this->numInitialExecutions, sprintf('The repeating pattern "%s" must be halted after eight nested executions.', implode(', ', array_slice(self::SCRIPT, 0, 8))));
    $this->assertSame('9', ArrayWrite::$array['step'] ?? '', 'The script must have stopped right before its ninth step.');
    $this->assertSame(4, ArrayIncrement::$array['a'] ?? 0, 'The first model must have been executed four times.');
    $this->assertSame(4, ArrayIncrement::$array['b'] ?? 0, 'The second model must have been executed four times.');
  }

  /**
   * Creates and saves a model that follows the scripted nesting pattern.
   *
   * The model reacts upon a write of its own array key and looks up the
   * current position in the script. For that position it advances the script
   * by one and triggers the model that the next script entry names, which
   * nests that model's execution inside this one.
   *
   * @param string $id
   *   The ID of the ECA config entity.
   * @param string $key
   *   The array key whose write event the model reacts upon, which is also
   *   the name used for this model within the script.
   */
  protected function createScriptedModel(string $id, string $key): void {
    $successors = [];
    $conditions = [];
    $actions = [
      'count' => [
        'plugin' => 'eca_test_array_increment',
        'label' => 'Count the executions of this model',
        'configuration' => ['key' => $key],
        'successors' => [],
      ],
    ];
    $successors[] = ['id' => 'count', 'condition' => ''];
    foreach (self::SCRIPT as $step => $model) {
      if ($model !== $key) {
        continue;
      }
      // The script is one based, while the array of steps is zero based.
      $position = $step + 1;
      $advance_successors = [];
      if (isset(self::SCRIPT[$position])) {
        $advance_successors[] = ['id' => 'trigger_' . $position, 'condition' => ''];
        $actions['trigger_' . $position] = [
          'plugin' => 'eca_test_array_write',
          'label' => 'Trigger step ' . ($position + 1) . ' of the script',
          'configuration' => ['key' => self::SCRIPT[$position], 'value' => 'go'],
          'successors' => [],
        ];
      }
      $actions['advance_' . $position] = [
        'plugin' => 'eca_test_array_write',
        'label' => 'Advance the script beyond step ' . $position,
        'configuration' => ['key' => 'step', 'value' => (string) ($position + 1)],
        'successors' => $advance_successors,
      ];
      $conditions['at_' . $position] = [
        'plugin' => 'eca_test_array_has_key_and_value',
        'configuration' => ['key' => 'step', 'value' => (string) $position],
      ];
      $successors[] = ['id' => 'advance_' . $position, 'condition' => 'at_' . $position];
    }
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => $id,
      'label' => 'ECA scripted recursion test model ' . $id,
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Static array write of ' . $key,
          'configuration' => ['key' => $key, 'value' => 'go'],
          'successors' => $successors,
        ],
      ],
      'conditions' => $conditions,
      'gateways' => [],
      'actions' => $actions,
    ])->save();
  }

  /**
   * Creates and saves an ECA model that reacts upon a static array write.
   *
   * The model counts its own executions by incrementing the array key it
   * reacts upon, and optionally writes another key afterwards, which triggers
   * the next model in the chain.
   *
   * @param string $id
   *   The ID of the ECA config entity.
   * @param string $listen_key
   *   The array key whose write event the model reacts upon.
   * @param string|null $write_key
   *   The array key the model writes into, or NULL to end the chain.
   */
  protected function createModel(string $id, string $listen_key, ?string $write_key): void {
    $successors = [['id' => 'count', 'condition' => '']];
    $actions = [
      'count' => [
        'plugin' => 'eca_test_array_increment',
        'label' => 'Count the executions of this model',
        'configuration' => ['key' => $listen_key],
        'successors' => [],
      ],
    ];
    if ($write_key !== NULL) {
      $successors[] = ['id' => 'write', 'condition' => ''];
      $actions['write'] = [
        'plugin' => 'eca_test_array_write',
        'label' => 'Trigger the next model in the chain',
        'configuration' => ['key' => $write_key, 'value' => 'go'],
        'successors' => [],
      ];
    }
    Eca::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => $id,
      'label' => 'ECA recursion test model ' . $id,
      'modeller' => 'fallback',
      'version' => '1.0.0',
      'events' => [
        'write' => [
          'plugin' => 'eca_test_array:write',
          'label' => 'Static array write of ' . $listen_key,
          'configuration' => ['key' => $listen_key, 'value' => 'go'],
          'successors' => $successors,
        ],
      ],
      'conditions' => [],
      'gateways' => [],
      'actions' => $actions,
    ])->save();
  }

  /**
   * Writes into the static array, which starts the ECA processing.
   *
   * @param string $key
   *   The array key to write into.
   * @param string $value
   *   The value to write.
   */
  protected function triggerWrite(string $key, string $value = 'go'): void {
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = \Drupal::service('plugin.manager.action');
    $action_manager->createInstance('eca_test_array_write', [
      'key' => $key,
      'value' => $value,
    ])->execute();
  }

}
