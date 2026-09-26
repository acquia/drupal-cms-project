<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\ToolUsageLimits;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests force_value + hide_property data-type conversion for tool restrictions.
 *
 * Regression test for the case where a forced value combined with
 * hide_property was written onto the context definition as the raw scalar,
 * causing crashes for non-scalar data types (entity:*, list, JSON, YAML).
 *
 * @group ai_agents
 */
#[RunTestsInSeparateProcesses]
final class ForceValueHidePropertyKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'key',
    'ai',
    'ai_agents',
  ];

  /**
   * Plugin id of the AI function-call wrapper around the node save action.
   */
  private const SAVE_NODE_PLUGIN_ID = 'action_plugin:entity:save_action:node';

  /**
   * Function name emitted by the action-plugin deriver for save node.
   */
  private const SAVE_NODE_FUNCTION_NAME = 'action_plugin__entity__save_action__node';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    // node_access is required to execute the tool.
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'filter', 'node', 'ai_agents']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    // The save-node action checks 'access content', so the current user
    // needs it to execute the tool.
    $this->setCurrentUser($this->createUser(['access content']));
  }

  /**
   * Forced entity value is converted to a real entity when hidden from LLM.
   */
  public function testForcedEntityValueIsConvertedWhenHidden(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Forced node',
    ]);
    $node->save();
    $raw = 'node:' . $node->id();

    $this->createAgentWithForcedValue(hide_property: TRUE, forced_value: $raw);

    $tool = $this->getSaveNodeTool('force_value_hidden_agent');
    $default = $tool->getContextDefinition('entity:node')->getDefaultValue();

    $this->assertInstanceOf(
      NodeInterface::class,
      $default,
      'When hide_property is enabled, the forced value must be converted to a Node entity before being stored as the context default.',
    );
    $this->assertSame((int) $node->id(), (int) $default->id());
  }

  /**
   * Forced value stays raw when the property is visible to the LLM.
   *
   * Regression guard: the visible path relies on FunctionCallBase::
   * setContextValue() to run the data-type converter on the LLM-supplied
   * argument. Converting here as well would change what the FixedValue
   * constraint compares against (object identity vs. string equality) and
   * break the previously working flow.
   */
  public function testForcedEntityValueStaysRawWhenVisible(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Visible forced node',
    ]);
    $node->save();
    $raw = 'node:' . $node->id();

    $this->createAgentWithForcedValue(hide_property: FALSE, forced_value: $raw, id: 'force_value_visible_agent');

    $tool = $this->getSaveNodeTool('force_value_visible_agent');
    $default = $tool->getContextDefinition('entity:node')->getDefaultValue();

    $this->assertSame(
      $raw,
      $default,
      'When hide_property is disabled, the forced value must remain the raw scalar so the existing setContextValue conversion path is unchanged.',
    );
  }

  /**
   * Default information tools execute with parameter keys containing colons.
   *
   * @see \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper::getDefaultInformationTools()
   * @see \Drupal\ai\Base\FunctionCallBase::populateValues()
   */
  public function testDefaultInformationToolWithColonParameterKeyExecutes(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Default info tool target',
    ]);
    $node->save();

    $plugin_id = self::SAVE_NODE_PLUGIN_ID;
    $yaml = <<<YAML
save_target:
  label: 'Save the target node'
  description: 'Re-saves the target node.'
  tool: '{$plugin_id}'
  parameters:
    entity__colon__node: 'node:{$node->id()}'
YAML;

    $this->createAgent('default_info_tool_agent', [
      'default_information_tools' => $yaml,
    ]);

    $wrapper = $this->container->get('plugin.manager.ai_agents')->createInstance('default_info_tool_agent');
    $wrapper->setRunnerId('test-runner-id');
    // Execute the tools.
    $output = $wrapper->getDefaultInformationTools();

    $this->assertStringContainsString(self::SAVE_NODE_FUNCTION_NAME, $output);
    // ActionPluginBase reports "status: success" once the entity has been
    // saved by the underlying action.
    $this->assertStringContainsString('status: success', $output);
  }

  /**
   * Creates an AI agent with default config, merging in the given overrides.
   */
  private function createAgent(string $id, array $overrides = []): void {
    $defaults = [
      'id' => $id,
      'label' => 'Test agent',
      'description' => '',
      'system_prompt' => 'Prompt.',
      'tools' => [],
      'tool_settings' => [],
      'tool_usage_limits' => [],
      'orchestration_agent' => FALSE,
      'triage_agent' => FALSE,
      'max_loops' => 3,
      'masquerade_roles' => [],
      'exclude_users_role' => FALSE,
    ];
    $this->container->get('entity_type.manager')->getStorage('ai_agent')
      ->create($overrides + $defaults)
      ->save();
  }

  /**
   * Creates an AI agent configured with a save-node tool restriction.
   */
  private function createAgentWithForcedValue(bool $hide_property, string $forced_value, string $id = 'force_value_hidden_agent'): void {
    $this->createAgent($id, [
      'label' => 'Force value test agent',
      'tools' => [
        self::SAVE_NODE_PLUGIN_ID => TRUE,
      ],
      'tool_usage_limits' => [
        self::SAVE_NODE_PLUGIN_ID => [
          'entity__colon__node' => [
            'action' => 'force_value',
            'hide_property' => $hide_property,
            'values' => [$forced_value],
          ],
        ],
      ],
    ]);
  }

  /**
   * Instantiates the agent wrapper and returns the save-node tool instance.
   */
  private function getSaveNodeTool(string $agent_id) {
    $wrapper = $this->container->get('plugin.manager.ai_agents')->createInstance($agent_id);
    $functions = $wrapper->getFunctions();
    $this->assertArrayHasKey(self::SAVE_NODE_FUNCTION_NAME, $functions['object']);
    return $functions['object'][self::SAVE_NODE_FUNCTION_NAME];
  }

}
