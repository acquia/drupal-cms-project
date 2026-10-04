<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai_agents\Event\AgentFinishedExecutionEvent;
use Drupal\ai_agents\Event\AgentResponseEvent;
use Drupal\ai_agents\Event\AgentStartedExecutionEvent;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for the streamed chat response path.
 *
 * Tool calls in a streamed response cannot be exercised yet: the mocked
 * EchoProvider only attaches tool output to a non-streamed ChatMessage, so
 * these tests cover the plain (no tool call) streamed response only.
 *
 * @see https://www.drupal.org/i/3538174
 */
#[Group('ai_agents')]
#[RunTestsInSeparateProcesses]
final class AiAgentEntityWrapperStreamingTest extends KernelTestBase {

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
   * The saved tools-free test agent's ID.
   *
   * @var string
   */
  protected string $agentId;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    // Required for EchoProvider::chat() to look up scripted mock responses.
    $this->installEntitySchema('ai_mock_provider_result');

    $this->installConfig('ai_agents');
    $this->installConfig('ai');
    $this->installConfig('ai_test');

    $this->agentId = $this->createStreamingTestAgent();

    // The ai_agent config entity's calculateDependencies() triggers
    // ai_function_calls discovery mid-save, which lazy-instantiates
    // plugin.manager.ai_agents before the new entity is committed to
    // storage. The manager ends up with a stale definition that omits the
    // entity being saved. Reset it so subsequent lookups see it.
    $this->container->set('plugin.manager.ai_agents', NULL);

    // EchoProvider already supports streaming; use it directly instead of a
    // custom mock provider, so these tests exercise the real
    // AiProviderPluginManager/ProviderProxy streaming bridge.
    $this->config('ai.settings')
      ->set('default_providers.chat_with_tools', [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ])
      ->save();
  }

  /**
   * Creates and saves a tools-free copy of the shared loop test agent.
   *
   * No tools/tool_settings so EchoProvider's generic text fallback answers
   * directly, without a tool-calling round trip. Tool calls in a streamed
   * response aren't testable yet (see the class docblock).
   *
   * @return string
   *   The saved agent ID.
   *
   * @see \Drupal\Tests\ai_agents\Kernel\PluginBase\AiAgentEntityWrapperProviderTagsTest::createTagsTestAgent()
   */
  protected function createStreamingTestAgent(): string {
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/config/ai_agents.ai_agent.loop_test_agent.yml');
    $data = array_replace($data, [
      'tools' => [],
      'tool_settings' => [],
    ]);
    $this->container->get('entity_type.manager')
      ->getStorage('ai_agent')
      ->create($data)
      ->save();

    return $data['id'];
  }

  /**
   * Loads the agent wrapper directly, the same way real code would.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
   *   The agent wrapper.
   *
   * @see \Drupal\Tests\ai_agents\Kernel\PluginBase\AiAgentEntityWrapperProviderTagsTest::getAgentWrapper()
   */
  protected function getAgentWrapper(): AiAgentInterface {
    return $this->container->get('plugin.manager.ai_agents')->createInstance($this->agentId);
  }

  /**
   * Builds a single-message chat input, optionally with streaming enabled.
   */
  protected function createChatInput(bool $streamed): ChatInput {
    $chat_input = new ChatInput([
      new ChatMessage('user', 'Hello.'),
    ]);
    $chat_input->setStreamedOutput($streamed);
    return $chat_input;
  }

  /**
   * Tests that a streamed response resolves to the streaming interface.
   *
   * When the AI provider returns a streamed response, solve() should
   * return a StreamedChatMessageIteratorInterface rather than a plain
   * string.
   */
  public function testStreamingReturnsStreamedChatMessageIteratorInterface(): void {
    $agent_wrapper = $this->getAgentWrapper();
    $agent_wrapper->setChatInput($this->createChatInput(TRUE));

    $result = $agent_wrapper->determineSolvability();

    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $result, 'A streamed response with no tool calls should resolve as solvable.');
    $this->assertInstanceOf(StreamedChatMessageIteratorInterface::class, $agent_wrapper->solve());
  }

  /**
   * Tests that the agent streams the exact text EchoProvider produces.
   *
   * EchoProvider's response text is deterministic for a given input and
   * configuration but can't be hardcoded here, since it echoes back the
   * agent's constructed system prompt. Instead, a second, freshly built
   * agent is run non-streamed on the same input as the ground truth, and
   * the streamed response is asserted to reconstruct to that same text.
   */
  public function testStreamingYieldsExactMockedText(): void {
    $non_streaming_wrapper = $this->getAgentWrapper();
    $non_streaming_wrapper->setChatInput($this->createChatInput(FALSE));
    $non_streaming_wrapper->determineSolvability();
    $expected_text = $non_streaming_wrapper->solve();
    $this->assertIsString($expected_text);
    $this->assertNotSame('', $expected_text);

    $streaming_wrapper = $this->getAgentWrapper();
    $streaming_wrapper->setChatInput($this->createChatInput(TRUE));
    $streaming_wrapper->determineSolvability();

    $streamed_text = '';
    foreach ($streaming_wrapper->solve() as $streamed_message) {
      $streamed_text .= $streamed_message->getText();
    }

    $this->assertSame($expected_text, $streamed_text, 'The streamed response should reconstruct to the same text EchoProvider returns synchronously for the same input.');
  }

  /**
   * Tests that chat history is preserved after streaming finishes.
   *
   * Iterating the stream to completion triggers postStreamingCallback(),
   * which should append the reconstructed assistant message to the chat
   * history.
   */
  public function testStreamingPreservesChatHistoryAfterCallback(): void {
    $agent_wrapper = $this->getAgentWrapper();
    $agent_wrapper->setChatInput($this->createChatInput(TRUE));
    $agent_wrapper->determineSolvability();

    // Drain the stream so postStreamingCallback() runs; iterating to
    // completion is all that matters here, not the yielded values.
    $stream = $agent_wrapper->solve();
    $streamed_text = implode('', array_map(
      static fn ($message) => $message->getText(),
      iterator_to_array($stream),
    ));

    $chat_history = $agent_wrapper->getChatHistory();
    $last_message = end($chat_history);

    $this->assertInstanceOf(ChatMessage::class, $last_message);
    $this->assertSame($streamed_text, $last_message->getText(), 'The reconstructed assistant message should be appended to chat history.');
  }

  /**
   * Tests that streaming skips two of the agent execution events.
   *
   * Only AgentStartedExecutionEvent should fire on the streaming path; see
   * the documentation added to AgentResponseEvent, AgentFinishedExecutionEvent,
   * and postStreamingCallback().
   */
  public function testStreamingDoesNotDispatchResponseOrFinishedEvents(): void {
    $started_count = 0;
    $response_count = 0;
    $finished_count = 0;

    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(AgentStartedExecutionEvent::EVENT_NAME, function () use (&$started_count) {
      $started_count++;
    });
    $dispatcher->addListener(AgentResponseEvent::EVENT_NAME, function () use (&$response_count) {
      $response_count++;
    });
    $dispatcher->addListener(AgentFinishedExecutionEvent::EVENT_NAME, function () use (&$finished_count) {
      $finished_count++;
    });

    $agent_wrapper = $this->getAgentWrapper();
    $agent_wrapper->setChatInput($this->createChatInput(TRUE));
    $agent_wrapper->determineSolvability();
    // Drain the stream so postStreamingCallback() runs.
    iterator_to_array($agent_wrapper->solve());

    $this->assertSame(1, $started_count, 'AgentStartedExecutionEvent should still be dispatched when streaming.');
    $this->assertSame(0, $response_count, 'AgentResponseEvent should not be dispatched when streaming.');
    $this->assertSame(0, $finished_count, 'AgentFinishedExecutionEvent should not be dispatched when streaming and the agent finishes normally.');
  }

}
