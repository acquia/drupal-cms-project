<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\ToolUsageLimits;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the runtime contract of force_value + hide_property tool restrictions.
 *
 * Two things must hold for a forced, hidden argument:
 * 1. It is removed from the tool schema sent to the model (the model must not
 *    see it and cannot supply it).
 * 2. Its value is still applied to the tool when the tool is executed, both
 *    during a normal agent run and when a serialized run is resumed - because
 *    the executed tool is built fresh and never passes through getFunctions()
 *    where limits are otherwise applied.
 *
 * The forced argument used here (list_config_entities' "entity_type") is a
 * plain string, matching the real-world report where a forced index name was
 * dropped at execution time.
 *
 * @group ai_agents
 */
#[RunTestsInSeparateProcesses]
final class ForceValueHidePropertyExecutionKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    // Required by the "echoai" provider (key.repository service).
    'key',
    'ai',
    // Provides the "echoai" provider so a serialized run can be rehydrated.
    'ai_test',
    'ai_agents',
  ];

  /**
   * Plugin id of the tool whose "entity_type" argument we force and hide.
   */
  private const TOOL_PLUGIN_ID = 'ai_agent:list_config_entities';

  /**
   * Function name the tool is exposed to the model under.
   */
  private const TOOL_FUNCTION_NAME = 'list_config_entities';

  /**
   * The tool argument that is forced (and, in most tests, hidden).
   */
  private const FORCED_PROPERTY = 'entity_type';

  /**
   * The value forced onto that argument: the config entity type to list.
   */
  private const FORCED_VALUE = 'user_role';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'ai_agents']);

    // The list_config_entities tool refuses to run without this permission.
    $this->setCurrentUser($this->createUser(['administer permissions']));
  }

  /**
   * A forced, hidden argument is stripped from the schema sent to the model.
   *
   * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::getFunctions()
   */
  public function testHiddenForcedArgumentIsRemovedFromModelSchema(): void {
    $this->createAgent(hide_property: TRUE);
    $wrapper = $this->container->get('plugin.manager.ai_agents')->createInstance('test_agent');

    // The normalized schema is what is sent to the LLM as the tool definition.
    $tool_schema = $wrapper->getFunctions()['normalized'][self::TOOL_FUNCTION_NAME];

    $this->assertNull(
      $tool_schema->getPropertyByName(self::FORCED_PROPERTY),
      'A forced + hidden argument must not be offered to the model.',
    );
    $this->assertNotNull(
      $tool_schema->getPropertyByName('amount'),
      'Only the hidden argument is removed; other arguments stay in the schema.',
    );
  }

  /**
   * Forcing a value without hiding it keeps the argument visible to the model.
   *
   * The negative boundary for testHiddenForcedArgumentIsRemovedFromModelSchema.
   */
  public function testVisibleForcedArgumentRemainsInModelSchema(): void {
    $this->createAgent(hide_property: FALSE);
    $wrapper = $this->container->get('plugin.manager.ai_agents')->createInstance('test_agent');

    // The force_value restriction constrains the value but still expects the
    // model to supply it, so the argument stays in the schema.
    $tool_schema = $wrapper->getFunctions()['normalized'][self::TOOL_FUNCTION_NAME];

    $this->assertNotNull(
      $tool_schema->getPropertyByName(self::FORCED_PROPERTY),
      'force_value without hide_property must leave the argument visible to the model.',
    );
  }

  /**
   * The forced value is applied when the model calls the tool during a run.
   *
   * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::executeTool()
   * @see \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager::getFunctionCallFromFunctionName()
   */
  public function testHiddenForcedValueIsAppliedWhenModelCallsTool(): void {
    // The tool lists entities of the forced type (user_role). Create a role so
    // it appears in the output, proving the tool ran with the forced value.
    // @see \Drupal\ai_agents\Plugin\AiFunctionCall\ListConfigEntities::execute()
    Role::create(['id' => 'forced_value_role', 'label' => 'Forced value role'])->save();

    $this->createAgent(hide_property: TRUE);
    $wrapper = $this->container->get('plugin.manager.ai_agents')->createInstance('test_agent');
    $wrapper->setRunnerId('test-runner-id');

    // Build the tool the bare way the agent does when the model calls it: no
    // tool usage limits applied and no value for the hidden "entity_type".
    $tool = $this->container->get('plugin.manager.ai.function_calls')
      ->getFunctionCallFromFunctionName(self::TOOL_FUNCTION_NAME);

    // Executing must apply the forced value.
    $wrapper->executeTool($tool, TRUE);

    $this->assertSame(
      self::FORCED_VALUE,
      $tool->getContextValue(self::FORCED_PROPERTY),
      'The forced value must reach the tool that actually executes.',
    );
    $this->assertStringContainsString(
      'forced_value_role',
      $tool->getReadableOutput(),
      'The tool must have operated on the forced value, listing that entity type.',
    );
  }

  /**
   * The forced value is applied when a serialized run is resumed and executed.
   *
   * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::fromArray()
   * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::getData()
   */
  public function testHiddenForcedValueIsAppliedWhenResumingSerializedRun(): void {
    // A role for the tool to list, as above.
    Role::create(['id' => 'forced_value_role', 'label' => 'Forced value role'])->save();

    $this->createAgent(hide_property: TRUE);
    $wrapper = $this->container->get('plugin.manager.ai_agents')->createInstance('test_agent');
    $wrapper->setRunnerId('test-runner-id');

    // A run persisted mid-conversation: the model called the tool, but the
    // hidden "entity_type" was never in the recorded arguments.
    $wrapper->fromArray([
      'provider_id' => 'echoai',
      'chat_history' => [
        [
          'role' => 'assistant',
          'text' => '',
          'images' => [],
          'tool_id' => NULL,
          'tools' => [
            [
              'id' => 'call_1',
              'function' => [
                'name' => self::TOOL_FUNCTION_NAME,
                'arguments' => '{}',
              ],
            ],
          ],
        ],
      ],
      'tool_results' => [],
      'context_tools' => [['tools_id' => 'call_1']],
    ]);

    // getData() returns the pending tools rebuilt from the serialized run.
    $context_tools = $wrapper->getData();
    $this->assertCount(1, $context_tools);
    $tool = $context_tools[0];

    // Resuming and executing must apply the forced value, just as during a
    // normal agent run, even though the recorded arguments never contained it.
    $wrapper->executeTool($tool, TRUE);

    $this->assertSame(
      self::FORCED_VALUE,
      $tool->getContextValue(self::FORCED_PROPERTY),
      'The forced value must reach the tool rebuilt from a serialized run.',
    );
    $this->assertStringContainsString(
      'forced_value_role',
      $tool->getReadableOutput(),
      'The resumed tool must have operated on the forced value.',
    );
  }

  /**
   * Creates a "test_agent" that forces (and optionally hides) "entity_type".
   */
  private function createAgent(bool $hide_property): void {
    $this->container->get('entity_type.manager')->getStorage('ai_agent')->create([
      'id' => 'test_agent',
      'label' => 'Force value test agent',
      'description' => '',
      'system_prompt' => 'Prompt.',
      'tools' => [
        self::TOOL_PLUGIN_ID => TRUE,
      ],
      'tool_settings' => [],
      'tool_usage_limits' => [
        self::TOOL_PLUGIN_ID => [
          self::FORCED_PROPERTY => [
            'action' => 'force_value',
            'hide_property' => $hide_property,
            'values' => [self::FORCED_VALUE],
          ],
        ],
      ],
      'orchestration_agent' => FALSE,
      'triage_agent' => FALSE,
      'max_loops' => 3,
      'masquerade_roles' => [],
      'exclude_users_role' => FALSE,
    ])->save();
  }

}
