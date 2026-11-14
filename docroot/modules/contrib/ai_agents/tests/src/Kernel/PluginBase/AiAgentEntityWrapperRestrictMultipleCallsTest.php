<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the per-tool restrict_multiple_calls setting.
 *
 * A tool flagged with restrict_multiple_calls may be called at most once per
 * LLM response. When it appears more than once in one response, every tool
 * call in that response is rejected: none execute, each tool-call id receives
 * a tool-role error message (the flagged tool its configured/default message,
 * the rest a generic rejection), and the agent retries. Tools that are not
 * flagged, or a flagged tool called only once, are unaffected.
 *
 * Each hop is driven through the ai module's echoai provider, matching the
 * request to a recorded fixture under
 * ai_agents_tools_test/tests/resources/ai_test/requests/chat.
 *
 * @group ai_agents
 * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::determineSolvability()
 * @see https://www.drupal.org/project/ai_agents/issues/3586051
 */
#[RunTestsInSeparateProcesses]
final class AiAgentEntityWrapperRestrictMultipleCallsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'link',
    'field_ui',
    'key',
    'ai',
    'ai_test',
    'ai_agents',
    'ai_agents_tools_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    // ai_test defines the ai_mock_provider_result entity type; with it enabled
    // echoai's dbRequestsToTest() queries that table, so its schema must exist
    // even though this test drives echoai from file-based fixtures only.
    $this->installEntitySchema('ai_mock_provider_result');
    $this->installConfig(['ai', 'ai_agents', 'ai_test']);
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);

    $this->container->get('config.factory')
      ->getEditable('ai.settings')
      ->set('default_providers.chat_with_tools', [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ])
      ->save();
  }

  /**
   * A restricted tool called twice rejects every call with its custom message.
   */
  public function testParallelCallWithCustomMessageRejectsEveryCallWithoutLooping(): void {
    $agent = $this->getAgentWrapper($this->createRestrictAgent([
      'ai_agents_tools_test:dummy_tool' => [
        'restrict_multiple_calls' => TRUE,
        'multiple_call_error_message' => 'Custom: [tool_name] once only.',
      ],
    ]));
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page with parallel tools.')]));

    // Fixture (dummy_tool ×2, dummy_tool_two ×1):
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/parallel-tool-calls-loop-1-batch.yml.
    $result = $agent->determineSolvability();

    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $result);
    $this->assertFalse($agent->isFinished());
    // dummy_tool appeared twice, so the whole batch was rejected before any
    // call ran.
    $this->assertEmpty($agent->getToolResults());
    $this->assertSame(['user', 'assistant', 'tool', 'tool', 'tool'], $this->historyRoles($agent));

    // The restricted tool's two calls get its configured message, with
    // [tool_name] replaced; the unrestricted dummy_tool_two call gets the
    // generic collateral rejection naming the offending tool.
    $messages = $this->toolMessages($agent);
    $this->assertSame('Custom: dummy_tool once only.', $messages['call_1']);
    $this->assertSame('Custom: dummy_tool once only.', $messages['call_2']);
    $this->assertStringContainsString('was not executed, because the tool(s) "dummy_tool"', $messages['call_3']);
  }

  /**
   * An empty configured message falls back to the built-in default message.
   */
  public function testParallelCallWithDefaultMessageRejectsEveryCallWithoutLooping(): void {
    $agent = $this->getAgentWrapper($this->createRestrictAgent([
      'ai_agents_tools_test:dummy_tool' => [
        'restrict_multiple_calls' => TRUE,
        'multiple_call_error_message' => '',
      ],
    ]));
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page with parallel tools.')]));

    // Fixture (dummy_tool ×2, dummy_tool_two ×1):
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/parallel-tool-calls-loop-1-batch.yml.
    $agent->determineSolvability();

    $this->assertEmpty($agent->getToolResults());
    $messages = $this->toolMessages($agent);
    $this->assertStringContainsString('The tool "dummy_tool" may only be called once per response', $messages['call_1']);
    $this->assertStringContainsString('The tool "dummy_tool" may only be called once per response', $messages['call_2']);
    $this->assertStringContainsString('was not executed, because the tool(s) "dummy_tool"', $messages['call_3']);
  }

  /**
   * A restricted tool absent from the response does not block another tool.
   */
  public function testRestrictedToolAbsentFromResponseDoesNotBlockWithoutLooping(): void {
    // dummy_tool_two is restricted, but the response only calls dummy_tool.
    $agent = $this->getAgentWrapper($this->createRestrictAgent([
      'ai_agents_tools_test:dummy_tool_two' => [
        'restrict_multiple_calls' => TRUE,
        'multiple_call_error_message' => '',
      ],
    ]));
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page.')]));

    // Loop 1 parks the single dummy_tool call; the restriction is not in play.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-1-tool-call.yml.
    $agent->determineSolvability();
    $this->assertSame([], $this->toolMessages($agent));
    $parked = $agent->getData();
    $this->assertCount(1, $parked);
    $this->assertSame('dummy_tool', $parked[0]->getFunctionName());

    // Loop 2 executes the parked dummy_tool; no rejection is ever raised.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-2-tool-call.yml.
    $agent->determineSolvability();
    $executed = $agent->getToolResults();
    $this->assertCount(1, $executed);
    $this->assertSame('dummy_tool', $executed[0]->getFunctionName());
    $this->assertSame('alpha', $executed[0]->getReadableOutput());
    $this->assertNoRejection($agent);
  }

  /**
   * A restricted tool called exactly once executes normally.
   */
  public function testRestrictedToolCalledOnceExecutesWithoutLooping(): void {
    // dummy_tool is restricted, and the response calls it exactly once.
    $agent = $this->getAgentWrapper($this->createRestrictAgent([
      'ai_agents_tools_test:dummy_tool' => [
        'restrict_multiple_calls' => TRUE,
        'multiple_call_error_message' => '',
      ],
    ]));
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page.')]));

    // Loop 1: one call to the restricted tool is not a violation, so it parks.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-1-tool-call.yml.
    $agent->determineSolvability();
    $this->assertSame([], $this->toolMessages($agent));
    $parked = $agent->getData();
    $this->assertCount(1, $parked);
    $this->assertSame('dummy_tool', $parked[0]->getFunctionName());

    // Loop 2: the parked restricted tool executes normally.
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/shared-loop-2-tool-call.yml.
    $agent->determineSolvability();
    $executed = $agent->getToolResults();
    $this->assertCount(1, $executed);
    $this->assertSame('dummy_tool', $executed[0]->getFunctionName());
    $this->assertSame('alpha', $executed[0]->getReadableOutput());
    $this->assertNoRejection($agent);
  }

  /**
   * In looped mode the block still fires, then the agent retries and recovers.
   *
   * Unlike the externally-driven tests, looping is left enabled, so the single
   * determineSolvability() call recurses internally: loop 1 detects the
   * violation and rejects the batch, loop 2 is re-invoked with those rejection
   * messages in history and returns a plain text recovery that ends the run.
   */
  public function testLoopedModeBlocksParallelCallsThenRecovers(): void {
    $agent = $this->getAgentWrapper($this->createRestrictAgent([
      'ai_agents_tools_test:dummy_tool' => [
        'restrict_multiple_calls' => TRUE,
        'multiple_call_error_message' => '',
      ],
    ]));
    // Leave looping enabled (the default): one call recurses to completion.
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Build a page with parallel tools.')]));

    // Fixtures, one per internal loop in order: the rejected batch, then the
    // retry returning a text reply with the rejection messages in history.
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/parallel-tool-calls-loop-1-batch.yml.
    // tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/parallel-tool-calls-loop-2-error-recovery.yml.
    $result = $agent->determineSolvability();

    $this->assertSame(AiAgentInterface::JOB_SOLVABLE, $result);
    $this->assertTrue($agent->isFinished());
    // Looping recursed into a second hop before ending.
    $this->assertSame(2, $agent->toArray()['looped']);
    $this->assertSame('Recovered after rejection.', $agent->answerQuestion());
    // The batch never executed, even though looping was enabled.
    $this->assertEmpty($agent->getToolResults());
    // Loop 1 wrote one rejection per call id; loop 2's text reply added no
    // further tool messages.
    $this->assertSame(['user', 'assistant', 'tool', 'tool', 'tool', 'assistant'], $this->historyRoles($agent));
    $messages = $this->toolMessages($agent);
    $this->assertStringContainsString('The tool "dummy_tool" may only be called once per response', $messages['call_1']);
    $this->assertStringContainsString('The tool "dummy_tool" may only be called once per response', $messages['call_2']);
    $this->assertStringContainsString('was not executed, because the tool(s) "dummy_tool"', $messages['call_3']);
  }

  /**
   * Returns a fresh agent wrapper for the given agent id.
   *
   * @param string $agent_id
   *   The agent id.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface
   *   The agent wrapper.
   */
  private function getAgentWrapper(string $agent_id) {
    return $this->container->get('plugin.manager.ai_agents')->createInstance($agent_id);
  }

  /**
   * Creates the loop test agent with the given per-tool settings.
   *
   * @param array $tool_settings
   *   The tool_settings map to apply to the agent.
   *
   * @return string
   *   The saved agent id.
   */
  private function createRestrictAgent(array $tool_settings): string {
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/config/ai_agents.ai_agent.loop_test_agent.yml');
    $data['tool_settings'] = $tool_settings;
    $this->container->get('entity_type.manager')
      ->getStorage('ai_agent')
      ->create($data)
      ->save();

    // Saving an ai_agent lazy-instantiates both plugin managers mid-save with
    // definitions that omit the new entity. Reset them so later lookups see it.
    $this->container->set('plugin.manager.ai_agents', NULL);
    $this->container->get('plugin.manager.ai.function_calls')->clearCachedDefinitions();

    return $data['id'];
  }

  /**
   * Collects the chat-history tool-role message texts, keyed by tool-call id.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent
   *   The agent.
   *
   * @return array
   *   The tool message texts, keyed by tool-call id.
   */
  private function toolMessages($agent): array {
    $messages = [];
    foreach ($agent->getChatHistory() as $message) {
      if ($message->getRole() === 'tool') {
        $messages[$message->getToolsId()] = $message->getText();
      }
    }
    return $messages;
  }

  /**
   * Asserts no tool-role message is a single-call rejection.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent
   *   The agent.
   */
  private function assertNoRejection($agent): void {
    foreach ($this->toolMessages($agent) as $text) {
      $this->assertStringNotContainsString('may only be called once per response', $text);
    }
  }

  /**
   * Returns the ordered list of chat-history message roles.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent
   *   The agent.
   *
   * @return string[]
   *   The roles, in order.
   */
  private function historyRoles($agent): array {
    return array_map(
      static fn (ChatMessage $message): string => $message->getRole(),
      $agent->getChatHistory(),
    );
  }

}
