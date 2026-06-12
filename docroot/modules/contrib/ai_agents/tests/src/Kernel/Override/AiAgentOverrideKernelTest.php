<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_agents\Kernel\Override;

use Drupal\Core\Form\FormState;
use Drupal\ai_agents\Entity\AiAgentOverride;
use Drupal\ai_agents\Exception\ParentAgentNotFoundException;
use Drupal\ai_agents\Hook\AiAgentCollectionFormAlter;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests runtime application of AI agent overrides.
 *
 * @group ai_agents
 */
final class AiAgentOverrideKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'ai',
    'ai_agents',
    'modeler_api',
    'field',
    'link',
    'text',
    'field_ui',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    'ai_agents.ai_agent_override.test_override_prefix',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('ai_agents');
  }

  /**
   * Ensures overrides are merged into runtime agent instances.
   */
  public function testOverridesAreAppliedToAgent(): void {
    $agentStorage = $this->container->get('entity_type.manager')->getStorage('ai_agent');
    $agentStorage->create([
      'id' => 'test_agent',
      'label' => 'Test Agent',
      'description' => 'Base agent used for override testing.',
      'system_prompt' => 'Base instructions. [replace-me]',
      'tools' => [
        'old_tool' => TRUE,
      ],
      'tool_settings' => [
        'old_tool' => [
          'return_directly' => FALSE,
          'progress_message' => 'Base message',
        ],
      ],
      'tool_usage_limits' => [
        'old_tool' => [
          'vid' => [
            'action' => '',
            'hide_property' => FALSE,
            'values' => [],
          ],
        ],
      ],
      'orchestration_agent' => FALSE,
      'triage_agent' => FALSE,
      'max_loops' => 3,
      'masquerade_roles' => [],
      'exclude_users_role' => FALSE,
    ])->save();

    $overrideStorage = $this->container->get('entity_type.manager')->getStorage('ai_agent_override');

    $overrideStorage->create([
      'id' => 'test_override_prefix',
      'label' => 'Prefix override',
      'status' => TRUE,
      'weight' => -10,
      'parent_agent' => 'test_agent',
      'tools_add' => ['new_tool'],
      'tools_remove' => ['old_tool'],
      'prompt_extra_text' => 'Prefix instructions.',
      'prompt_mod_strategy' => AiAgentOverride::PROMPT_MOD_PREFIX,
      'tool_settings' => [
        'new_tool' => [
          'return_directly' => TRUE,
          'progress_message' => 'Override message',
        ],
      ],
      'tool_usage_limits' => [
        'new_tool' => [
          'vid' => [
            'action' => 'only_allow',
            'hide_property' => FALSE,
            'values' => ['design_vocab'],
          ],
        ],
      ],
    ])->save();

    $overrideStorage->create([
      'id' => 'test_override_suffix',
      'label' => 'Suffix override',
      'status' => TRUE,
      'weight' => 0,
      'parent_agent' => 'test_agent',
      'prompt_extra_text' => 'Suffix instructions.',
      'prompt_mod_strategy' => AiAgentOverride::PROMPT_MOD_SUFFIX,
    ])->save();

    $overrideStorage->create([
      'id' => 'test_override_replace',
      'label' => 'Replace override',
      'status' => TRUE,
      'weight' => 10,
      'parent_agent' => 'test_agent',
      'prompt_extra_text' => 'Replaced instructions.',
      'prompt_mod_strategy' => AiAgentOverride::PROMPT_MOD_REPLACE,
      'prompt_replace_token' => '[replace-me]',
    ])->save();

    $wrapper = $this->container->get('plugin.manager.ai_agents')->createInstance('test_agent');
    $agent = $wrapper->getAiAgentEntity();

    $tools = $agent->get('tools');
    self::assertIsArray($tools);
    self::assertArrayHasKey('new_tool', $tools);
    self::assertArrayNotHasKey('old_tool', $tools);

    $overriddenSettings = $agent->get('tool_settings');
    $this->assertIsArray($overriddenSettings);
    $this->assertTrue($overriddenSettings['new_tool']['return_directly']);
    $this->assertSame('Override message', $overriddenSettings['new_tool']['progress_message']);
    $this->assertArrayNotHasKey('old_tool', $overriddenSettings);

    $overriddenLimits = $agent->get('tool_usage_limits');
    $this->assertIsArray($overriddenLimits);
    $this->assertSame('only_allow', $overriddenLimits['new_tool']['vid']['action']);
    $this->assertSame(['design_vocab'], $overriddenLimits['new_tool']['vid']['values']);

    $expectedPrompt = 'Prefix instructions.Base instructions. Replaced instructions.Suffix instructions.';
    $this->assertSame($expectedPrompt, $agent->get('system_prompt'));

    $originalAgent = $agentStorage->load('test_agent');
    $this->assertArrayHasKey('old_tool', $originalAgent->get('tools'));
    $this->assertSame('Base instructions. [replace-me]', $originalAgent->get('system_prompt'));
    $this->assertArrayHasKey('old_tool', $originalAgent->get('tool_settings'));
  }

  /**
   * Ensures saving an override can fail when parent does not exist.
   */
  public function testFailIfParentMissingThrowsException(): void {
    $overrideStorage = $this->container->get('entity_type.manager')->getStorage('ai_agent_override');

    $this->expectException(ParentAgentNotFoundException::class);
    $overrideStorage->create([
      'id' => 'missing_parent_override',
      'label' => 'Missing parent override',
      'status' => TRUE,
      'parent_agent' => 'missing_agent',
      'fail_if_parent_missing' => TRUE,
      'prompt_mod_strategy' => AiAgentOverride::PROMPT_MOD_SUFFIX,
    ])->save();
  }

  /**
   * Ensures the list builder exposes override information.
   */
  public function testListBuilderDisplaysOverridesSummary(): void {
    $agentStorage = $this->container->get('entity_type.manager')->getStorage('ai_agent');
    $overrideStorage = $this->container->get('entity_type.manager')->getStorage('ai_agent_override');

    $agentStorage->create([
      'id' => 'list_agent',
      'label' => 'List Agent',
      'description' => 'List agent.',
      'system_prompt' => 'List prompt.',
      'tools' => [],
      'tool_settings' => [],
      'tool_usage_limits' => [],
      'orchestration_agent' => FALSE,
      'triage_agent' => FALSE,
      'max_loops' => 3,
      'masquerade_roles' => [],
      'exclude_users_role' => FALSE,
    ])->save();

    $agentStorage->create([
      'id' => 'plain_agent',
      'label' => 'Plain Agent',
      'description' => 'Plain agent.',
      'system_prompt' => 'Plain prompt.',
      'tools' => [],
      'tool_settings' => [],
      'tool_usage_limits' => [],
      'orchestration_agent' => FALSE,
      'triage_agent' => FALSE,
      'max_loops' => 3,
      'masquerade_roles' => [],
      'exclude_users_role' => FALSE,
    ])->save();

    $overrideStorage->create([
      'id' => 'list_override_enabled',
      'label' => 'Enabled Override',
      'status' => TRUE,
      'weight' => 0,
      'parent_agent' => 'list_agent',
      'prompt_mod_strategy' => AiAgentOverride::PROMPT_MOD_SUFFIX,
    ])->save();

    $overrideStorage->create([
      'id' => 'list_override_disabled',
      'label' => 'Disabled Override',
      'status' => FALSE,
      'weight' => 5,
      'parent_agent' => 'list_agent',
      'prompt_mod_strategy' => AiAgentOverride::PROMPT_MOD_SUFFIX,
    ])->save();

    $listBuilder = $this->container->get('entity_type.manager')->getListBuilder('ai_agent');
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($listBuilder, $form_state);

    if (!isset($form['entities']['#header']['overrides'])) {
      $alter = $this->container->get('class_resolver')->getInstanceFromDefinition(AiAgentCollectionFormAlter::class);
      $alter->formAiAgentCollectionAlter($form, $form_state);
    }

    $this->assertArrayHasKey('overrides', $form['entities']['#header']);
    $this->assertSame('Overrides', (string) $form['entities']['#header']['overrides']);

    $this->assertSame('Enabled: Enabled Override; Disabled: Disabled Override', $form['entities']['list_agent']['overrides']['#plain_text']);
    $this->assertSame('None', $form['entities']['plain_agent']['overrides']['#plain_text']);
  }

  /**
   * Ensures override enable status can be toggled from the agent form.
   */
  public function testAgentFormAllowsTogglingOverrides(): void {
    $agentStorage = $this->container->get('entity_type.manager')->getStorage('ai_agent');
    $overrideStorage = $this->container->get('entity_type.manager')->getStorage('ai_agent_override');

    $agentStorage->create([
      'id' => 'toggle_agent',
      'label' => 'Toggle Agent',
      'description' => 'Toggle agent.',
      'system_prompt' => 'Toggle prompt.',
      'tools' => [],
      'tool_settings' => [],
      'tool_usage_limits' => [],
      'orchestration_agent' => FALSE,
      'triage_agent' => FALSE,
      'max_loops' => 3,
      'masquerade_roles' => [],
      'exclude_users_role' => FALSE,
    ])->save();

    $overrideStorage->create([
      'id' => 'toggle_enabled',
      'label' => 'Enabled override',
      'status' => TRUE,
      'weight' => 0,
      'parent_agent' => 'toggle_agent',
      'prompt_mod_strategy' => AiAgentOverride::PROMPT_MOD_SUFFIX,
    ])->save();

    $overrideStorage->create([
      'id' => 'toggle_disabled',
      'label' => 'Disabled override',
      'status' => FALSE,
      'weight' => 1,
      'parent_agent' => 'toggle_agent',
      'prompt_mod_strategy' => AiAgentOverride::PROMPT_MOD_SUFFIX,
    ])->save();

    $agent = $agentStorage->load('toggle_agent');

    $formRender = $this->container->get('entity.form_builder')->getForm($agent, 'edit');
    $this->assertSame(['toggle_enabled'], $formRender['prompt_detail']['overrides']['override_status']['#default_value']);
    $this->assertArrayHasKey('toggle_disabled', $formRender['prompt_detail']['overrides']['override_status']['#options']);

    $formObject = $this->container->get('entity_type.manager')->getFormObject('ai_agent', 'edit');
    $formObject->setEntity($agent);

    $input = [
      'label' => $agent->label(),
      'description' => $agent->get('description'),
      'orchestration_agent' => $agent->get('orchestration_agent'),
      'triage_agent' => $agent->get('triage_agent'),
      'max_loops' => $agent->get('max_loops'),
      'user_roles' => [],
      'exclude_users_role' => $agent->get('exclude_users_role'),
      'default_information_tools' => $agent->get('default_information_tools') ?? '',
      'structured_output_enabled' => $agent->get('structured_output_enabled') ?? FALSE,
      'structured_output_schema' => $agent->get('structured_output_schema') ?? '',
      'system_prompt' => $agent->get('system_prompt'),
      'tools' => [],
      'tool_usage' => [],
      'override_status' => ['toggle_disabled'],
    ];

    $form_state = (new FormState())
      ->setFormObject($formObject)
      ->setBuildInfo(['args' => []])
      ->setValues($input)
      ->setUserInput($input);
    $form_state->setTriggeringElement($formRender['actions']['submit']);

    $this->container->get('form_builder')->submitForm($formObject, $form_state);

    $this->assertFalse($overrideStorage->load('toggle_enabled')->status());
    $this->assertTrue($overrideStorage->load('toggle_disabled')->status());
  }

  /**
   * Ensures tool library selection and override enforcement summary render.
   */
  public function testToolLibraryDisplaysSelectedToolsAndOverrideEnforcement(): void {
    $agentStorage = $this->container->get('entity_type.manager')->getStorage('ai_agent');
    $overrideStorage = $this->container->get('entity_type.manager')->getStorage('ai_agent_override');

    $agentStorage->create(
      [
        'id' => 'enforced_agent',
        'label' => 'Enforced Agent',
        'description' => 'Agent with enforced tools.',
        'system_prompt' => 'Prompt.',
        'tools' => [
          'ai_agent:list_taxonomy_term' => TRUE,
          'ai_agent:modify_taxonomy_term' => TRUE,
        ],
        'tool_settings' => [],
        'tool_usage_limits' => [],
        'orchestration_agent' => FALSE,
        'triage_agent' => FALSE,
        'max_loops' => 3,
        'masquerade_roles' => [],
        'exclude_users_role' => FALSE,
      ]
    )->save();

    $overrideStorage->create(
      [
        'id' => 'enforced_override',
        'label' => 'Enforced tools override',
        'status' => TRUE,
        'weight' => 0,
        'parent_agent' => 'enforced_agent',
        'tools_add' => ['ai_agent:list_taxonomy_term'],
        'tools_remove' => ['ai_agent:modify_taxonomy_term'],
        'prompt_mod_strategy' => AiAgentOverride::PROMPT_MOD_SUFFIX,
      ]
    )->save();

    $agent = $agentStorage->load('enforced_agent');
    $form = $this->container->get('entity.form_builder')->getForm($agent, 'edit');

    $toolsLibrary = $form['prompt_detail']['tools_box']['tools']['tools_library']['selected_tools']['#content'] ?? [];
    $this->assertIsArray($toolsLibrary);
    $this->assertArrayHasKey('ai_agent:list_taxonomy_term', $toolsLibrary);
    $this->assertArrayHasKey('ai_agent:modify_taxonomy_term', $toolsLibrary);

    $overrideEnforcement = $form['prompt_detail']['tools_box']['override_enforcement'] ?? NULL;
    $this->assertNotNull($overrideEnforcement);
    $this->assertSame('Override Enforcement', (string) $overrideEnforcement['#title']);

    $functionCallManager = $this->container->get('plugin.manager.ai.function_calls');
    $definitions = $functionCallManager->getDefinitions();
    $enabledToolName = $definitions['ai_agent:list_taxonomy_term']['name'] ?? 'ai_agent:list_taxonomy_term';
    $disabledToolName = $definitions['ai_agent:modify_taxonomy_term']['name'] ?? 'ai_agent:modify_taxonomy_term';
    $overrideLabel = 'Enforced tools override';

    $forcedEnabledMarkup = (string) ($overrideEnforcement['forced_enabled']['#markup'] ?? '');
    $this->assertStringContainsString($enabledToolName, $forcedEnabledMarkup);
    $this->assertStringContainsString('Enabled by: ' . $overrideLabel, $forcedEnabledMarkup);

    $forcedDisabledMarkup = (string) ($overrideEnforcement['forced_disabled']['#markup'] ?? '');
    $this->assertStringContainsString($disabledToolName, $forcedDisabledMarkup);
    $this->assertStringContainsString('Disabled by: ' . $overrideLabel, $forcedDisabledMarkup);
  }

}
