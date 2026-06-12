<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Dto\HostnameFilterDto;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests that the hostname_filter_disabled flag is honored on chat dispatch.
 *
 * @group ai_agents
 */
final class AiAgentEntityWrapperHostnameFilterTest extends KernelTestBase {

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected $functionCallManager;

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

    // Install a test agent config from the shared fixture.
    $data = Yaml::parseFile(__DIR__ . '/../../../assets/config/ai_agents.ai_agent.drupal_cms_assistant.yml');
    $agent = $this->container->get('entity_type.manager')
      ->getStorage('ai_agent')
      ->create($data);
    $agent->save();

    // Set echoai as the default provider for chat_with_tools so the wrapper
    // bootstraps; the actual chat() call is intercepted by a mock below.
    $this->container->get('config.factory')
      ->getEditable('ai.settings')
      ->set('default_providers.chat_with_tools', [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ])
      ->save();
  }

  /**
   * When hostname_filter_disabled is TRUE, a fullTrust DTO is set on input.
   */
  public function testHostnameFilterDtoSetWhenDisabledFlagTrue(): void {
    $this->setHostnameFilterDisabled(TRUE);

    $captured = $this->runChatAndCaptureInput();

    $filter = $captured->getHostnameFilter();
    $this->assertInstanceOf(HostnameFilterDto::class, $filter, 'A HostnameFilterDto should be attached to the ChatInput.');
    $this->assertTrue($filter->fullTrust, 'fullTrust should be TRUE when hostname_filter_disabled is TRUE.');
  }

  /**
   * When hostname_filter_disabled is FALSE, no DTO is injected by this path.
   */
  public function testHostnameFilterUnchangedWhenDisabledFlagFalse(): void {
    $this->setHostnameFilterDisabled(FALSE);

    $captured = $this->runChatAndCaptureInput();

    $this->assertNull($captured->getHostnameFilter(), 'No HostnameFilterDto should be set when hostname_filter_disabled is FALSE.');
  }

  /**
   * When hostname_filter_disabled is unset/NULL, no DTO is injected either.
   */
  public function testHostnameFilterUnchangedWhenDisabledFlagNull(): void {
    // Fixture does not define the property; leave it untouched.
    $captured = $this->runChatAndCaptureInput();

    $this->assertNull($captured->getHostnameFilter(), 'No HostnameFilterDto should be set when hostname_filter_disabled is NULL.');
  }

  /**
   * Sets hostname_filter_disabled on the saved agent and re-saves it.
   */
  protected function setHostnameFilterDisabled(bool $value): void {
    $agent = $this->container->get('entity_type.manager')
      ->getStorage('ai_agent')
      ->load('drupal_cms_assistant');
    $agent->set('hostname_filter_disabled', $value);
    $agent->save();
  }

  /**
   * Runs determineSolvability() with a capturing mock provider.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatInput
   *   The ChatInput instance that reached the provider's chat() method.
   */
  protected function runChatAndCaptureInput(): ChatInput {
    $wrapper = $this->functionCallManager->createInstance('ai_agents::ai_agent::drupal_cms_assistant');

    // Reach the inner AiAgentEntityWrapper to swap the provider.
    $inner_ref = new \ReflectionProperty($wrapper, 'agent');
    $inner_ref->setAccessible(TRUE);
    $agent_wrapper = $inner_ref->getValue($wrapper);

    $mock_provider = new class () {
      /**
       * Captures the ChatInput passed to chat() for inspection in the test.
       */
      public ?ChatInput $capturedInput = NULL;

      /**
       * Stub method to satisfy the interface; not used in this test.
       */
      public function setChatSystemRole(string $message): void {
      }

      /**
       * Capture the input for inspection in the test.
       */
      public function chat(mixed $input, string $model_id, array $tags = []): ChatOutput {
        $this->capturedInput = $input;
        return new ChatOutput(new ChatMessage('assistant', 'ok'), NULL, NULL);
      }

    };

    $agent_wrapper->setAiProvider($mock_provider);
    $agent_wrapper->setModelName('test-model');

    $wrapper->setChatInput(new ChatInput([
      new ChatMessage('user', 'Test message.'),
    ]));

    $wrapper->determineSolvability();

    $this->assertNotNull($mock_provider->capturedInput, 'The mock provider should have received a ChatInput.');
    return $mock_provider->capturedInput;
  }

}
