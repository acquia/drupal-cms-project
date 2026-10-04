<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\Component\Serialization\Json;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests hostname_filter_disabled against serialized agent state.
 *
 * Covers tool-call arguments in both directions, across a looped run and an
 * externally-driven one: with hostname_filter_disabled set they survive being
 * serialized and rebuilt, and without it they are filtered either way.
 *
 * @group ai_agents
 * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::rebuildChatHistory()
 * @see \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult::setValue()
 */
#[RunTestsInSeparateProcesses]
final class AiAgentEntityWrapperHostnameFilterSerializationTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The opening prompt the scripted fixture is keyed on.
   */
  private const HTML_PROMPT = 'Return the rich HTML block.';

  /**
   * The rich HTML the scripted response passes to dummy_tool.
   *
   * Its link and image point at a host absent from allowed_hosts, so an active
   * hostname filter strips both attributes and leaves the text.
   */
  private const RICH_HTML = '<div class="card"><h2>Docs</h2><p>Read the <a href="https://example.com/docs">documentation</a> for details.</p><img src="https://example.com/shot.png" alt="Screenshot"></div>';

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

    // allowed_hosts is empty by default, so every absolute URL in the payload
    // is one the filter removes when it is active. Asserted rather than assumed
    // because the whole test hinges on it.
    $this->assertEmpty($this->config('ai.settings')->get('allowed_hosts'));

    $this->container->get('config.factory')
      ->getEditable('ai.settings')
      ->set('default_providers.chat_with_tools', [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ])
      ->save();
  }

  /**
   * With the flag a looped run passes the HTML to the tool unchanged.
   */
  public function testLoopedModeKeepsToolArgumentsIntact(): void {
    // Leaves looping enabled, so this one call decides, runs the tool and
    // finishes without serializing in between.
    $agent = $this->getAgentWrapper($this->createLoopTestAgent(hostname_filter_disabled: TRUE));
    $agent->setChatInput(new ChatInput([new ChatMessage('user', self::HTML_PROMPT)]));
    $agent->determineSolvability();

    $executed = $agent->getToolResults();
    $this->assertCount(1, $executed);
    $this->assertSame('dummy_tool', $executed[0]->getFunctionName());
    // dummy_tool echoes its input, so its output is the argument it received.
    $this->assertSame(self::RICH_HTML, $executed[0]->getReadableOutput());
  }

  /**
   * Without the flag a looped run still filters the tool arguments.
   */
  public function testLoopedModeFiltersToolArgumentsWithoutTheFlag(): void {
    $agent = $this->getAgentWrapper($this->createLoopTestAgent(hostname_filter_disabled: FALSE));
    $agent->setChatInput(new ChatInput([new ChatMessage('user', self::HTML_PROMPT)]));
    $agent->determineSolvability();

    $executed = $agent->getToolResults();
    $this->assertCount(1, $executed);
    $this->assertDisallowedUrlsStripped($executed[0]->getReadableOutput());
  }

  /**
   * With the flag the first externally-driven hop serializes the HTML intact.
   */
  public function testFirstHopSerializesToolArgumentsIntact(): void {
    $stored = $this->runFirstHop(hostname_filter_disabled: TRUE);

    $this->assertSame(
      ['input' => self::RICH_HTML],
      Json::decode($stored['chat_history'][1]['tools'][0]['function']['arguments']),
    );
  }

  /**
   * With the flag a resumed agent rebuilds the stored arguments unchanged.
   */
  public function testResumedAgentKeepsToolArgumentsIntact(): void {
    $stored = $this->runFirstHop(hostname_filter_disabled: TRUE);

    $resumed = $this->getAgentWrapper('loop_test_agent');
    $resumed->fromArray($stored);

    $this->assertSame(
      ['input' => self::RICH_HTML],
      Json::decode($resumed->toArray()['chat_history'][1]['tools'][0]['function']['arguments']),
      'Rebuilding serialized state must not filter tool arguments of an agent that has hostname filtering disabled.',
    );
  }

  /**
   * With the flag the tool parked by the first hop gets the HTML unchanged.
   */
  public function testResumedAgentExecutesParkedToolWithIntactArguments(): void {
    $stored = $this->runFirstHop(hostname_filter_disabled: TRUE);

    $resumed = $this->getAgentWrapper('loop_test_agent');
    $resumed->fromArray($stored);
    // Executes the parked dummy_tool, which returns its result directly and
    // ends the run before another chat call is made.
    $resumed->determineSolvability();

    $executed = $resumed->getToolResults();
    $this->assertCount(1, $executed);
    $this->assertSame(
      self::RICH_HTML,
      $executed[0]->getReadableOutput(),
      'A tool resumed from serialized state must receive the arguments the model produced.',
    );
  }

  /**
   * Without the flag a resumed run still filters the tool arguments.
   */
  public function testResumedAgentFiltersToolArgumentsWithoutTheFlag(): void {
    $stored = $this->runFirstHop(hostname_filter_disabled: FALSE);

    $resumed = $this->getAgentWrapper('loop_test_agent');
    $resumed->fromArray($stored);
    $this->assertDisallowedUrlsStripped(
      Json::decode($resumed->toArray()['chat_history'][1]['tools'][0]['function']['arguments'])['input'],
    );

    $resumed->determineSolvability();

    $executed = $resumed->getToolResults();
    $this->assertCount(1, $executed);
    $this->assertDisallowedUrlsStripped($executed[0]->getReadableOutput());
  }

  /**
   * Asserts that the payload lost its disallowed URLs but kept its text.
   *
   * @param string $html
   *   The tool argument to check.
   */
  private function assertDisallowedUrlsStripped(string $html): void {
    $this->assertStringNotContainsString('https://example.com/docs', $html);
    $this->assertStringNotContainsString('https://example.com/shot.png', $html);
    $this->assertStringContainsString('documentation', $html);
  }

  /**
   * Runs the first externally-driven hop and returns the serialized state.
   *
   * @param bool $hostname_filter_disabled
   *   Whether the agent has hostname filtering disabled.
   *
   * @return array
   *   The agent state a caller would persist between hops.
   */
  private function runFirstHop(bool $hostname_filter_disabled): array {
    $agent = $this->getAgentWrapper($this->createLoopTestAgent($hostname_filter_disabled));
    // Stop after the model has decided, so the tool call is parked and the
    // state is serialized before it runs.
    $agent->setLooped(FALSE);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', self::HTML_PROMPT)]));
    // fixture: tests/modules/ai_agents_tools_test/tests/resources/ai_test/requests/chat/hostname-filter-loop-1-html-tool-call.yml.
    $agent->determineSolvability();

    return $agent->toArray();
  }

  /**
   * Creates the loop test agent.
   *
   * @param bool $hostname_filter_disabled
   *   Whether to disable hostname filtering for the agent.
   *
   * @return string
   *   The saved agent id.
   */
  private function createLoopTestAgent(bool $hostname_filter_disabled): string {
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/config/ai_agents.ai_agent.loop_test_agent.yml');
    $data['hostname_filter_disabled'] = $hostname_filter_disabled;
    // Returning the tool result directly ends the run as soon as dummy_tool has
    // run, so every flow here makes exactly one chat call and needs only the
    // one recorded fixture.
    $data['tool_settings']['ai_agents_tools_test:dummy_tool']['return_directly'] = 1;
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

}
