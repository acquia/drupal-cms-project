<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\PluginBase;

use Drupal\ai_agents\Event\AgentToolFinishedExecutionEvent;
use Drupal\ai_agents\Event\AgentToolPreExecuteEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests event dispatch for default information tools in AiAgentEntityWrapper.
 *
 * @group ai_agents
 */
final class DefaultInformationToolsEventsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'ai',
    'ai_agents',
  ];

  /**
   * The agent wrapper under test.
   *
   * @var \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper
   */
  protected $agentWrapper;

  /**
   * Collected dispatched events.
   *
   * @var array
   */
  protected array $collectedEvents = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig('ai_agents');

    // Uid 1 has all permissions (needed by list_entity_types).
    $this->setUpCurrentUser(['uid' => 1], [], TRUE);

    // Create agent config entity with default_information_tools configured.
    $this->container->get('entity_type.manager')
      ->getStorage('ai_agent')
      ->create([
        'id' => 'test_execute_tool',
        'label' => 'Test Execute Tool Agent',
        'description' => 'Agent for testing executeTool event dispatch.',
        'system_prompt' => 'Test prompt.',
        'tools' => [],
        'tool_settings' => [],
        'tool_usage_limits' => [],
        'orchestration_agent' => FALSE,
        'triage_agent' => FALSE,
        'max_loops' => 3,
        'default_information_tools' => "list_entities:\n  label: 'List of entity types'\n  description: 'The current list of entity types on this system'\n  tool: 'ai_agent:list_entity_types'\n  parameters:\n    type_of_entity: content\n",
        'masquerade_roles' => [],
        'exclude_users_role' => FALSE,
      ])->save();

    // Get the wrapper via the plugin manager (production path).
    $this->agentWrapper = $this->container
      ->get('plugin.manager.ai_agents')
      ->createInstance('test_execute_tool');
    $this->agentWrapper->setRunnerId('test-runner-id');

    // Register listeners to capture tool events.
    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(
      AgentToolPreExecuteEvent::EVENT_NAME,
      function (AgentToolPreExecuteEvent $event) {
        $this->collectedEvents[] = $event;
      },
      100
    );
    $dispatcher->addListener(
      AgentToolFinishedExecutionEvent::EVENT_NAME,
      function (AgentToolFinishedExecutionEvent $event) {
        $this->collectedEvents[] = $event;
      },
      100
    );
  }

  /**
   * Tests executeTool with agent_decision=FALSE (default information tool).
   *
   * When a tool is executed as a default information tool, both
   * AgentToolPreExecuteEvent and AgentToolFinishedExecutionEvent must fire
   * with isAgentDecision() === FALSE and a deterministic toolsId generated.
   */
  public function testDefaultInformationToolFiresEvents(): void {
    $tool = $this->container->get('plugin.manager.ai.function_calls')
      ->createInstance('ai_agent:list_entity_types');
    $tool->setContextValue('type_of_entity', 'content');

    // No toolsId set yet.
    $this->assertEmpty($tool->getToolsId());

    $this->agentWrapper->executeTool($tool, FALSE);

    // Pre + finished = 2 events.
    $this->assertCount(2, $this->collectedEvents);

    $preEvent = $this->collectedEvents[0];
    $finishedEvent = $this->collectedEvents[1];

    $this->assertInstanceOf(AgentToolPreExecuteEvent::class, $preEvent);
    $this->assertInstanceOf(AgentToolFinishedExecutionEvent::class, $finishedEvent);

    // Both events must flag this as a default information tool,
    // not an agent decision.
    $this->assertFalse($preEvent->isAgentDecision());
    $this->assertFalse($finishedEvent->isAgentDecision());

    // A deterministic toolsId must have been generated.
    $this->assertNotEmpty($tool->getToolsId());
    $this->assertSame($tool->getToolsId(), $preEvent->getToolId());
    $this->assertSame($tool->getToolsId(), $finishedEvent->getToolId());

    // Agent metadata.
    $this->assertSame('test_execute_tool', $preEvent->getAgentId());
    $this->assertSame('test-runner-id', $preEvent->getAgentRunnerId());
  }

  /**
   * Tests executeTool with agent_decision=TRUE (LLM-decided tool).
   *
   * When the LLM selects a tool, both events must fire with
   * isAgentDecision() === TRUE and the original toolsId preserved.
   */
  public function testAgentDecidedToolFiresEventsWithAgentDecisionTrue(): void {
    $tool = $this->container->get('plugin.manager.ai.function_calls')
      ->createInstance('ai_agent:list_entity_types');
    $tool->setContextValue('type_of_entity', 'content');
    $tool->setToolsId('llm-tool-call-123');

    $this->agentWrapper->executeTool($tool, TRUE);

    $this->assertCount(2, $this->collectedEvents);

    $preEvent = $this->collectedEvents[0];
    $finishedEvent = $this->collectedEvents[1];

    // is_agent_decision must be TRUE on both.
    $this->assertTrue($preEvent->isAgentDecision());
    $this->assertTrue($finishedEvent->isAgentDecision());

    // Original toolsId must be preserved.
    $this->assertSame('llm-tool-call-123', $preEvent->getToolId());
    $this->assertSame('llm-tool-call-123', $finishedEvent->getToolId());
  }

  /**
   * Tests that same plugin + same context values produce the same toolsId.
   */
  public function testDeterministicToolIdIsStableForSameInputs(): void {
    $manager = $this->container->get('plugin.manager.ai.function_calls');

    $tool1 = $manager->createInstance('ai_agent:list_entity_types');
    $tool1->setContextValue('type_of_entity', 'content');
    $this->agentWrapper->executeTool($tool1, FALSE);

    $tool2 = $manager->createInstance('ai_agent:list_entity_types');
    $tool2->setContextValue('type_of_entity', 'content');
    $this->agentWrapper->executeTool($tool2, FALSE);

    $this->assertSame($tool1->getToolsId(), $tool2->getToolsId());
  }

  /**
   * Tests that different context values produce different toolsIds.
   */
  public function testDeterministicToolIdDiffersForDifferentInputs(): void {
    $manager = $this->container->get('plugin.manager.ai.function_calls');

    $tool1 = $manager->createInstance('ai_agent:list_entity_types');
    $tool1->setContextValue('type_of_entity', 'content');
    $this->agentWrapper->executeTool($tool1, FALSE);

    $tool2 = $manager->createInstance('ai_agent:list_entity_types');
    $tool2->setContextValue('type_of_entity', 'config');
    $this->agentWrapper->executeTool($tool2, FALSE);

    $this->assertNotSame($tool1->getToolsId(), $tool2->getToolsId());
  }

}
