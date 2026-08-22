<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai_agents\Event\AgentFinishedExecutionEvent;
use Drupal\ai_agents\Event\AgentStartedExecutionEvent;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for determineSolvability event dispatch behavior.
 *
 * Verifies that agents do not get stuck in "started" state when
 * max_loops is exceeded or when the AI provider chat call fails.
 *
 * @group ai_agents
 * @RunTestsInSeparateProcesses
 * @see https://www.drupal.org/project/ai_agents/issues/3553458
 */
final class AiAgentEntityWrapperDetermineSolvabilityTest extends KernelTestBase {

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected $functionCallManager;

  /**
   * Tracks dispatched AgentStartedExecutionEvent count.
   *
   * @var int
   */
  protected int $startedEventCount = 0;

  /**
   * Tracks dispatched AgentFinishedExecutionEvent count.
   *
   * @var int
   */
  protected int $finishedEventCount = 0;

  /**
   * Tags captured from the mock AI provider's chat() call.
   *
   * Public so the anonymous mock provider classes below can write to it.
   *
   * @var array
   */
  public array $capturedTags = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'key',
    'ai',
    'ai_agents',
    'system',
    'field',
    'link',
    'text',
    'field_ui',
    'ai_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    $this->installConfig('ai_agents');
    $this->installConfig('ai');
    $this->installConfig('ai_test');

    // Install a test agent config.
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/config/ai_agents.ai_agent.drupal_cms_assistant.yml');
    $agent = $this->container->get('entity_type.manager')
      ->getStorage('ai_agent')
      ->create($data);
    $agent->save();

    // The ai_agent config entity's calculateDependencies() triggers
    // ai_function_calls discovery mid-save, which lazy-instantiates
    // plugin.manager.ai_agents before the new entity is committed to
    // storage. Both managers end up with stale definitions that omit the
    // entity being saved. Reset them so subsequent lookups see it.
    $this->container->set('plugin.manager.ai_agents', NULL);
    $this->container->get('plugin.manager.ai.function_calls')->clearCachedDefinitions();

    // Set echoai as the default provider for chat_with_tools.
    $this->container->get('config.factory')
      ->getEditable('ai.settings')
      ->set('default_providers.chat_with_tools', [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ])
      ->save();

    // Register event listeners to track started/finished events.
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(AgentStartedExecutionEvent::EVENT_NAME, function () {
      $this->startedEventCount++;
    }, 100);
    $dispatcher->addListener(AgentFinishedExecutionEvent::EVENT_NAME, function () {
      $this->finishedEventCount++;
    }, 100);
  }

  /**
   * Resets event counters between tests.
   */
  protected function resetEventCounters(): void {
    $this->startedEventCount = 0;
    $this->finishedEventCount = 0;
  }

  /**
   * Tests that max_loops does not dispatch started event without finished.
   *
   * When the agent has already looped the maximum number of times,
   * determineSolvability() should return JOB_NOT_SOLVABLE without
   * dispatching AgentStartedExecutionEvent.
   */
  public function testMaxLoopsDoesNotDispatchStartedWithoutFinished(): void {
    $this->resetEventCounters();

    $wrapper = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');

    // Access the inner AiAgentEntityWrapper via reflection.
    $inner_ref = new \ReflectionProperty($wrapper, 'agent');
    $inner_ref->setAccessible(TRUE);
    $agent_wrapper = $inner_ref->getValue($wrapper);

    // Set the loop counter to the max_loops value so the next call exceeds it.
    $max_loops = $agent_wrapper->getAiAgentEntity()->get('max_loops');
    $looped_ref = new \ReflectionProperty($agent_wrapper, 'looped');
    $looped_ref->setAccessible(TRUE);
    $looped_ref->setValue($agent_wrapper, (int) $max_loops);

    // Provide chat input.
    $chat_input = new ChatInput([
      new ChatMessage('user', 'Test message.'),
    ]);
    $wrapper->setChatInput($chat_input);

    $result = $wrapper->determineSolvability();

    $this->assertSame(AiAgentInterface::JOB_NOT_SOLVABLE, $result);
    $this->assertSame(0, $this->startedEventCount, 'No AgentStartedExecutionEvent should be dispatched when max_loops is exceeded.');
    $this->assertSame(0, $this->finishedEventCount, 'No AgentFinishedExecutionEvent should be dispatched when max_loops is exceeded.');
  }

  /**
   * Tests that chat exception dispatches finished event.
   *
   * When the AI provider chat() call throws an exception,
   * determineSolvability() should catch it, dispatch
   * AgentFinishedExecutionEvent, and return JOB_NOT_SOLVABLE.
   */
  public function testChatExceptionDispatchesFinishedEvent(): void {
    $this->resetEventCounters();

    $wrapper = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');

    // Access the inner AiAgentEntityWrapper.
    $inner_ref = new \ReflectionProperty($wrapper, 'agent');
    $inner_ref->setAccessible(TRUE);
    $agent_wrapper = $inner_ref->getValue($wrapper);

    // Create a mock provider that throws on chat().
    $mock_provider = new class () {

      /**
       * {@inheritdoc}
       */
      public function setChatSystemRole(string $message): void {
      }

      /**
       * {@inheritdoc}
       */
      public function chat(mixed $input, string $model_id, array $tags = []): ChatOutput {
        throw new \RuntimeException('No budget/quota left');
      }

    };

    // Inject the failing provider.
    $agent_wrapper->setAiProvider($mock_provider);
    $agent_wrapper->setModelName('test-model');

    // Provide chat input.
    $chat_input = new ChatInput([
      new ChatMessage('user', 'Test message.'),
    ]);
    $wrapper->setChatInput($chat_input);

    $result = $wrapper->determineSolvability();

    $this->assertSame(AiAgentInterface::JOB_NOT_SOLVABLE, $result);
    $this->assertSame(1, $this->startedEventCount, 'AgentStartedExecutionEvent should be dispatched before the chat call.');
    $this->assertSame(1, $this->finishedEventCount, 'AgentFinishedExecutionEvent should be dispatched when chat() throws.');
  }

  /**
   * Tests normal execution dispatches both started and finished events.
   *
   * When the AI provider chat() succeeds and no tools are returned,
   * both started and finished events should be dispatched.
   */
  public function testNormalExecutionDispatchesBothEvents(): void {
    $this->resetEventCounters();

    $wrapper = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');

    // Access the inner AiAgentEntityWrapper.
    $inner_ref = new \ReflectionProperty($wrapper, 'agent');
    $inner_ref->setAccessible(TRUE);
    $agent_wrapper = $inner_ref->getValue($wrapper);

    // Create a mock provider that returns a simple response (no tools).
    $response_message = new ChatMessage('assistant', 'Hello, I can help!');
    $expected_output = new ChatOutput($response_message, NULL, NULL);

    $mock_provider = new class ($expected_output) {

      /**
       * Constructor.
       */
      public function __construct(protected ChatOutput $output) {
      }

      /**
       * {@inheritdoc}
       */
      public function setChatSystemRole(string $message): void {
      }

      /**
       * {@inheritdoc}
       */
      public function chat(mixed $input, string $model_id, array $tags = []): ChatOutput {
        return $this->output;
      }

    };

    $agent_wrapper->setAiProvider($mock_provider);
    $agent_wrapper->setModelName('test-model');

    // Provide chat input.
    $chat_input = new ChatInput([
      new ChatMessage('user', 'Hello.'),
    ]);
    $wrapper->setChatInput($chat_input);

    $result = $wrapper->determineSolvability();

    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $result);
    $this->assertSame(1, $this->startedEventCount, 'AgentStartedExecutionEvent should be dispatched for normal execution.');
    $this->assertSame(1, $this->finishedEventCount, 'AgentFinishedExecutionEvent should be dispatched for normal execution.');
  }

  /**
   * Tests that extra tags include the caller runner and thread ids.
   *
   * When the agent has a caller agent runner id and a progress thread id
   * set, determineSolvability() should pass tags for both of them to the
   * AI provider's chat() call, alongside the base runner tag.
   */
  public function testExtraTagsIncludeCallerRunnerAndThreadIds(): void {
    $wrapper = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');

    // Access the inner AiAgentEntityWrapper.
    $inner_ref = new \ReflectionProperty($wrapper, 'agent');
    $inner_ref->setAccessible(TRUE);
    $agent_wrapper = $inner_ref->getValue($wrapper);

    $agent_wrapper->setCallerAgentRunnerId('caller-runner-id');
    $agent_wrapper->setProgressThreadId('thread-id');

    // Create a mock provider that captures the tags it was called with.
    // It also needs getPluginId(), since setting a thread id makes
    // AgentStatusSubscriber log the request, which reads it off the
    // provider.
    $response_message = new ChatMessage('assistant', 'Hello, I can help!');
    $expected_output = new ChatOutput($response_message, NULL, NULL);

    $mock_provider = new class ($expected_output, $this) {

      /**
       * Constructor.
       */
      public function __construct(
        protected ChatOutput $output,
        protected AiAgentEntityWrapperDetermineSolvabilityTest $test,
      ) {
      }

      /**
       * {@inheritdoc}
       */
      public function setChatSystemRole(string $message): void {
      }

      /**
       * {@inheritdoc}
       */
      public function getPluginId(): string {
        return 'mock_provider';
      }

      /**
       * {@inheritdoc}
       */
      public function chat(mixed $input, string $model_id, array $tags = []): ChatOutput {
        $this->test->capturedTags = $tags;
        return $this->output;
      }

    };

    $agent_wrapper->setAiProvider($mock_provider);
    $agent_wrapper->setModelName('test-model');

    // Provide chat input.
    $chat_input = new ChatInput([
      new ChatMessage('user', 'Hello.'),
    ]);
    $wrapper->setChatInput($chat_input);

    $wrapper->determineSolvability();

    $this->assertContains('ai_agents_caller_runner_caller-runner-id', $this->capturedTags, 'Tags should include the caller agent runner id.');
    $this->assertContains('ai_agents_thread_thread-id', $this->capturedTags, 'Tags should include the progress thread id.');
  }

  /**
   * Tests that extra tags omit caller runner and thread ids when unset.
   *
   * When no caller agent runner id or progress thread id has been set,
   * determineSolvability() should not add tags for either of them, while
   * still adding the base runner tag.
   */
  public function testExtraTagsExcludeCallerRunnerAndThreadIdsWhenUnset(): void {
    $wrapper = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');

    // Access the inner AiAgentEntityWrapper.
    $inner_ref = new \ReflectionProperty($wrapper, 'agent');
    $inner_ref->setAccessible(TRUE);
    $agent_wrapper = $inner_ref->getValue($wrapper);

    // Create a mock provider that captures the tags it was called with.
    $response_message = new ChatMessage('assistant', 'Hello, I can help!');
    $expected_output = new ChatOutput($response_message, NULL, NULL);

    $mock_provider = new class ($expected_output, $this) {

      /**
       * Constructor.
       */
      public function __construct(
        protected ChatOutput $output,
        protected AiAgentEntityWrapperDetermineSolvabilityTest $test,
      ) {
      }

      /**
       * {@inheritdoc}
       */
      public function setChatSystemRole(string $message): void {
      }

      /**
       * {@inheritdoc}
       */
      public function chat(mixed $input, string $model_id, array $tags = []): ChatOutput {
        $this->test->capturedTags = $tags;
        return $this->output;
      }

    };

    $agent_wrapper->setAiProvider($mock_provider);
    $agent_wrapper->setModelName('test-model');

    // Provide chat input.
    $chat_input = new ChatInput([
      new ChatMessage('user', 'Hello.'),
    ]);
    $wrapper->setChatInput($chat_input);

    $wrapper->determineSolvability();

    $runner_id = $agent_wrapper->getRunnerId();
    $this->assertContains('ai_agents_runner_' . $runner_id, $this->capturedTags, 'Tags should include the base runner tag.');
    foreach ($this->capturedTags as $tag) {
      $this->assertStringStartsNotWith('ai_agents_caller_runner_', $tag, 'Tags should not include a caller runner tag when unset.');
      $this->assertStringStartsNotWith('ai_agents_thread_', $tag, 'Tags should not include a thread tag when unset.');
    }
  }

}
