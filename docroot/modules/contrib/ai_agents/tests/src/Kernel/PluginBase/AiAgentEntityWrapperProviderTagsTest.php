<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests that a custom provider tag survives a live chat() call.
 *
 * @group ai_agents
 * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::setProviderTags()
 * @see https://www.drupal.org/project/ai_agents/issues/3557418
 */
#[RunTestsInSeparateProcesses]
final class AiAgentEntityWrapperProviderTagsTest extends KernelTestBase {

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
    // even though this test drives echoai from its generic fallback only.
    $this->installEntitySchema('ai_mock_provider_result');
    $this->installConfig(['ai', 'ai_agents', 'ai_test']);
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);

    // Drive the call through the ai module's echoai provider.
    $this->setEchoAiAsProvider();
  }

  /**
   * Makes the ai module's echoai provider the default for chat_with_tools.
   */
  private function setEchoAiAsProvider(): void {
    $this->container->get('config.factory')
      ->getEditable('ai.settings')
      ->set('default_providers.chat_with_tools', [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ])
      ->save();
  }

  /**
   * A tag set via setProviderTags() is still there after a real chat() call.
   */
  public function testProviderTagSurvivesChatCall(): void {
    $agent = $this->getAgentWrapper($this->createTagsTestAgent());
    $agent->setProviderTags(['my_custom_tag']);
    $agent->setChatInput(new ChatInput([new ChatMessage('user', 'Hello.')]));

    $agent->determineSolvability();

    $this->assertSame(['my_custom_tag'], $agent->getProviderTags());
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
   * Creates and saves a tools-free copy of the shared loop test agent.
   *
   * No tools/tool_settings so echoai's generic text fallback answers the
   * call directly, without a tool-calling round trip.
   *
   * @return string
   *   The saved agent id.
   */
  private function createTagsTestAgent(): string {
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

}
