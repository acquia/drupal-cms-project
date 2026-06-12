<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager;
use Drupal\ai_agents\AiAgentOverrideInterface;
use Drupal\ai_agents\Entity\AiAgent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * AI Agent form.
 */
final class AiAgentForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\ai_agents\Entity\AiAgent
   */
  protected $entity;

  /**
   * Constructs a new AiAgentForm object.
   *
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The function call plugin manager.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager $functionGroupPluginManager
   *   The function group plugin manager.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\ai\Guardrail\AiGuardrailHelper $aiGuardrailHelper
   *   The AI guardrail helper.
   */
  public function __construct(
    protected FunctionCallPluginManager $functionCallPluginManager,
    protected FunctionGroupPluginManager $functionGroupPluginManager,
    protected Token $token,
    protected AiGuardrailHelper $aiGuardrailHelper,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai.function_calls'),
      $container->get('plugin.manager.ai.function_groups'),
      $container->get('token'),
      $container->get('ai.guardrail_helper'),
    );
  }

  /**
   * Provides the default config for the metadata form.
   *
   * @param string $idKey
   *   The key for the ID.
   *
   * @return array
   *   The default config for the metadata form.
   */
  public static function defaultConfigMetadata(string $idKey): array {
    return [
      'label' => '',
      $idKey => '',
      'description' => '',
      'orchestration_agent' => FALSE,
      'triage_agent' => FALSE,
      'max_loops' => 10,
      'max_loops_message' => '',
      'system_prompt' => '',
      'secured_system_prompt' => '[ai_agent:agent_instructions]',
      'default_information_tools' => '',
      'structured_output_enabled' => FALSE,
      'structured_output_schema' => '',
      'hostname_filter_disabled' => FALSE,
      'guardrail_set' => '',
    ];
  }

  /**
   * Builds the metadata form part of the AI Agent form.
   *
   * @param array $form
   *   The form array.
   * @param array $config
   *   The configuration values.
   * @param string $idKey
   *   The key of the ID field.
   * @param bool $isNew
   *   TRUE, if the form gets built for a new agents, FALSE otherwise.
   * @param bool $tokenBrowser
   *   TRUE, if the token browser should be displayed.
   *
   * @return array
   *   The form including the metadata form part.
   */
  public function buildFormMetadata(array $form, array $config, string $idKey, bool $isNew, bool $tokenBrowser): array {
    $tools = [];
    // Load the tools for the default tool library.
    foreach ($this->functionCallPluginManager->getDefinitions() as $def) {
      $parameters = [];
      foreach ($def['context_definitions'] ?? [] as $name => $cd) {
        $parameters[] = [
          'name' => $name,
          'description' => (string) $cd->getDescription(),
        ];
      }
      $tools[] = ['id' => $def['id'], 'parameters' => $parameters];
    }
    $form['#attached']['drupalSettings']['aiTools'] = $tools;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $config['label'],
      '#required' => TRUE,
    ];

    $form[$idKey] = [
      '#type' => 'machine_name',
      '#default_value' => $config[$idKey],
      '#machine_name' => [
        'exists' => [AiAgent::class, 'load'],
      ],
      '#disabled' => !$isNew,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('A description of the AI agent. This is really important, because when using agents as tools this will base their decisions to pick the right agent on this.'),
      '#required' => TRUE,
      '#default_value' => $config['description'],
      '#attributes' => [
        'rows' => 2,
      ],
    ];

    $form['prompt_detail'] = [
      '#type' => 'details',
      '#title' => $this->t('Usage details'),
      '#open' => TRUE,
      '#id' => 'ai-agents-prompt-detail',
    ];

    // Show the token browser if the module is enabled.
    if ($tokenBrowser) {
      $form['prompt_detail']['#description'] = $this->t('The prompt detail is the prompt that the AI agent will use to start the conversation. Please be descriptive and clear in how the agent should behave. You can use tokens in the system prompt and default information tools. The token browser will help you to find the right tokens to use. They can be used in the System Prompt, Default Information Tools and tool usage.');

      $form['prompt_detail']['token_help'] = [
        '#theme' => 'token_tree_link',
        // Other modules may provide token types.
        '#token_types' => [
          'ai_agent',
        ],
      ];
    }
    else {
      $form['prompt_detail']['#description'] = $this->t('The prompt detail is the prompt that the AI agent will use to start the conversation. Please be descriptive and clear in how the agent should behave. You can use tokens in the system prompt and default information tools. If you want to be able to use the token browser, please enable the token module to use this feature. Tokens will still work if you manually add them. You can use tokens in the system prompt, default information tools and detail tool usage.');
    }

    // Build typeahead values from all available tokens. Wrapped in a
    // try/catch because some token providers (e.g. system date tokens) may
    // fail in minimal environments where config entities are not installed.
    $typeahead_values = [];
    try {
      $token_info = $this->token->getInfo();
      foreach ($token_info['tokens'] as $type => $tokens) {
        $type_name = isset($token_info['types'][$type]['name']) ? (string) $token_info['types'][$type]['name'] : $type;
        foreach ($tokens as $token_key => $token_data) {
          $typeahead_values[] = [
            'value' => '[' . $type . ':' . $token_key . ']',
            'displayValue' => $type_name . ': ' . (string) ($token_data['name'] ?? $token_key),
            'description' => (string) ($token_data['description'] ?? ''),
          ];
        }
      }
    }
    catch (\Throwable) {
      // Fallback: provide at least the ai_agent token.
      $typeahead_values[] = [
        'value' => '[ai_agent:agent_instructions]',
        'displayValue' => $this->t('AI Agent: Agent instructions'),
        'description' => $this->t('The specific instructions that define how the AI agent should behave and respond to tasks for a particular interaction.'),
      ];
    }

    $typeahead_config = [
      'types' => [
        [
          'name' => 'tokens',
          'trigger' => '[',
          'values' => $typeahead_values,
        ],
      ],
    ];

    $editor_id = 'ai_agents_system_prompt_editor';

    $form['prompt_detail']['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Agent Instructions'),
      '#description' => $this->t('Specific instructions that define how the AI agent should behave and respond to tasks for a particular interaction.'),
      '#required' => TRUE,
      '#default_value' => $config['system_prompt'],
      '#attributes' => [
        'rows' => 10,
        'data-mdxeditor' => $editor_id,
      ],
      '#attached' => [
        'drupalSettings' => [
          'mdxeditor' => [
            $editor_id => [
              'plugins' => [
                'typeaheadPlugin' => $typeahead_config,
              ],
            ],
          ],
        ],
      ],
    ];

    // Show the secured system prompt only if configured in settings.php.
    if (Settings::get('show_secured_ai_agent_system_prompt', FALSE)) {
      $form['prompt_detail']['secured_system_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('System Prompt'),
        '#description' => $this->t('Expert configuration: This field contains the full system prompt sent to the AI, including any fixed behaviors not editable by regular users. You can use [ai_agent:agent_instructions] token to include the Agent Instructions field above. If left empty, only Agent Instructions will be used.'),
        // Set the full agent instructions as default value.
        '#default_value' => $config['secured_system_prompt'],
        '#attributes' => [
          'rows' => 10,
        ],
      ];
    }

    $form['prompt_detail']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
      '#weight' => 10,
    ];

    $form['prompt_detail']['advanced']['max_loops'] = [
      '#type' => 'number',
      '#title' => $this->t('Max loops'),
      '#description' => $this->t('The maximum amount of loops that the AI agent can run to feed itself with new context before giving up. This is a security feature to prevent infinite loops.'),
      '#default_value' => $config['max_loops'],
      '#required' => TRUE,
    ];

    $form['prompt_detail']['advanced']['max_loops_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Max loops message'),
      '#description' => $this->t('The message displayed to the user when the agent reaches its maximum number of loops. Leave empty for the default message.'),
      '#default_value' => $config['max_loops_message'],
      '#rows' => 3,
    ];

    $form['prompt_detail']['advanced']['default_information_tools'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default information tools'),
      '#description' => $this->t('A list of default information tools that can be used by the AI agent. You can either give an empty value, hardcoded value or dynamic value to parameters. If a dynamic value is set, an LLM will try to figure out how to fill in the value.'),
      '#default_value' => $config['default_information_tools'],
      '#attributes' => [
        'data-default-tools-editor' => TRUE,
      ],
    ];

    // Classification.
    $form['prompt_detail']['advanced']['classification'] = [
      '#type' => 'details',
      '#title' => $this->t('Classification settings'),
      '#description' => $this->t('These settings will help the AI agent to classify the tasks and decide which tools to use.'),
      '#open' => FALSE,
    ];

    $form['prompt_detail']['advanced']['classification']['orchestration_agent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Orchestration agent'),
      '#description' => $this->t('Check this box if you want to use this agent as an orchestration agent. An orchestration agent is an agent that is an orchestrator of other agents mainly.'),
      '#default_value' => $config['orchestration_agent'] ?? FALSE,
    ];

    $form['prompt_detail']['advanced']['classification']['triage_agent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Triage agent'),
      '#description' => $this->t('Check this box if you want to use this agent as a triage agent. A triage agent is an agent that is solving one specific type of task or problem, but still utilizing other agents to do so'),
      '#default_value' => $config['triage_agent'] ?? FALSE,
    ];

    // Add structured output if wanted in settings.
    $form['prompt_detail']['advanced']['structured_output_detail'] = [
      '#type' => 'details',
      '#title' => $this->t('Structured output'),
      '#description' => $this->t('Settings for providing structured (JSON) output from the AI agent.'),
      '#open' => $config['structured_output_enabled'],
    ];

    $form['prompt_detail']['advanced']['structured_output_detail']['structured_output_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Structured Output'),
      '#description' => $this->t('Check this box if you want the AI agent to provide structured (JSON) output. This is useful if you want to use the output in a structured way, like in a workflow or to parse it easily. You will have to provider a JSON schema of the output wanted.'),
      '#default_value' => $config['structured_output_enabled'],
    ];

    $form['prompt_detail']['advanced']['structured_output_detail']['structured_output_schema'] = [
      '#type' => 'ai_json_schema',
      '#title' => $this->t('JSON Schema'),
      '#description' => $this->t('The JSON schema that defines the structured output. Please provide a valid JSON schema according to OpenAI documentation: %link', [
        '%link' => Link::fromTextAndUrl($this->t('JSON Schema'), Url::fromUri('https://platform.openai.com/docs/guides/structured-outputs#examples', [
          'attributes' => [
            'target' => '_blank',
          ],
        ]))->toString(),
      ]),
      '#default_value' => $config['structured_output_schema'],
      '#states' => [
        'visible' => [
          ':input[name="structured_output_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prompt_detail']['advanced']['security'] = [
      '#type' => 'details',
      '#title' => $this->t('Security settings'),
      '#description' => $this->t('These settings will help the AI agent to handle security-related tasks.'),
      '#open' => FALSE,
    ];

    $guardrail_set_options = array_map(function (AiGuardrailSetInterface $guardrail_set) {
      return $guardrail_set->label();
    }, $this->aiGuardrailHelper->getRepository()->getAllGuardrailSets());
    $form['prompt_detail']['advanced']['security']['guardrail_set'] = [
      '#type' => 'select',
      '#title' => $this->t('Guardrail Set'),
      '#description' => $this->t('The guardrails set to apply to the agents calls.'),
      '#default_value' => $this->entity->get('guardrail_set') ?? '',
      '#required' => FALSE,
      '#empty_option' => $this->t('- No guardrail set -'),
      '#options' => $guardrail_set_options,
    ];

    $form['prompt_detail']['advanced']['security']['hostname_filter_disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable hostname filtering'),
      '#description' => $this->t('Check this box if you want to disable hostname filtering for this agent. Hostname filtering is a security feature that restricts the agent from making requests to certain hostnames. Disabling it can be useful for agents that need to access a wide range of hostnames, but it can also pose a security risk if the agent is not properly configured. Only disable this if you know what you are doing and have other security measures in place.'),
      '#default_value' => $config['hostname_filter_disabled'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form['#attached']['library'][] = 'ai_agents/agents_form';
    $form['#attached']['library'][] = 'ai/ai_global';
    $form['#attached']['library'][] = 'ai/default_tools_editor';

    $form['#title'] = $this->t('AI agent: %label', [
      '%label' => $this->entity->label() ?? $this->t('Create new AI agent'),
    ]);

    $form = $this->buildFormMetadata(
      $form,
      [
        'label' => $this->entity->label(),
        'id' => $this->entity->id(),
        'description' => $this->entity->get('description'),
        'max_loops' => $this->entity->get('max_loops') ?? 10,
        'max_loops_message' => $this->entity->get('max_loops_message') ?? '',
        'system_prompt' => $this->entity->get('system_prompt'),
        'orchestration_agent' => $this->entity->get('orchestration_agent') ?? FALSE,
        'triage_agent' => $this->entity->get('triage_agent') ?? FALSE,
        'secured_system_prompt' => $this->entity->get('secured_system_prompt') ?? '[ai_agent:agent_instructions]',
        'default_information_tools' => $this->entity->get('default_information_tools') ? Yaml::dump(Yaml::parse($this->entity->get('default_information_tools') ?? ''), 10, 2) : NULL,
        'structured_output_enabled' => $this->entity->get('structured_output_enabled') ?? FALSE,
        'structured_output_schema' => $this->entity->get('structured_output_schema') ?? '',
        'hostname_filter_disabled' => $this->entity->get('hostname_filter_disabled') ?? FALSE,
        'guardrail_set' => $this->entity->get('guardrail_set') ?? '',
      ] + self::defaultConfigMetadata('id'),
      'id',
      $this->entity->isNew(),
      $this->moduleHandler->moduleExists('token'),
    );

    $form['prompt_detail']['tools_box'] = [
      '#type' => 'details',
      '#title' => $this->t('Tools'),
      '#description' => $this->t('These are the tools that the Agent can use to get information, modify content/configs, call other agents, etc.'),
      '#open' => TRUE,
    ];

    $function_call_plugin_manager = $this->functionCallPluginManager;

    $form['prompt_detail']['tools_box']['tools'] = [
      '#type' => 'ai_tools_library',
      '#title' => $this->t('Tools for this agent'),
      '#default_value' => $this->entity->get('tools') ?? [],
      '#after_build' => [[self::class, 'afterBuildToolsLibrary']],
    ];

    // Add override enforcement summary section if there are active overrides.
    if (!$this->entity->isNew()) {
      $override_enforcements = $this->getOverrideToolEnforcements();

      if ($override_enforcements !== []) {
        $form['prompt_detail']['tools_box']['override_enforcement'] = [
          '#type' => 'details',
          '#title' => $this->t('Override Enforcement'),
          '#open' => TRUE,
          '#weight' => 100,
          '#description' => $this->t('The following tools are controlled by active overrides. Your selections above will be adjusted automatically when you save.'),
        ];

        $forced_enabled = [];
        $forced_disabled = [];

        foreach ($override_enforcements as $toolId => $enforcement) {
          $definition = $function_call_plugin_manager->getDefinition($toolId);
          $tool_name = $definition['name'] ?? $toolId;
          $override_labels = implode(', ', $enforcement['labels']);

          if ($enforcement['state']) {
            $forced_enabled[] = $this->t('<strong>@tool</strong><br><em>Enabled by: @overrides</em>', [
              '@tool' => $tool_name,
              '@overrides' => $override_labels,
            ]);
          }
          else {
            $forced_disabled[] = $this->t('<strong>@tool</strong><br><em>Disabled by: @overrides</em>', [
              '@tool' => $tool_name,
              '@overrides' => $override_labels,
            ]);
          }
        }

        if ($forced_enabled !== []) {
          $form['prompt_detail']['tools_box']['override_enforcement']['forced_enabled'] = [
            '#type' => 'item',
            '#title' => $this->t('Force Enabled Tools'),
            '#markup' => '<div class="override-forced-enabled">' . implode('<hr>', $forced_enabled) . '</div>',
          ];
        }

        if ($forced_disabled !== []) {
          $form['prompt_detail']['tools_box']['override_enforcement']['forced_disabled'] = [
            '#type' => 'item',
            '#title' => $this->t('Force Disabled Tools'),
            '#markup' => '<div class="override-forced-disabled">' . implode('<hr>', $forced_disabled) . '</div>',
          ];
        }

        $form['prompt_detail']['tools_box']['override_enforcement']['#attached']['library'][] = 'ai_agents/agents_form';
      }
    }

    // Selected tools.
    $selected_tools = [];
    if ($form_state->isRebuilding()) {
      $user_input = $form_state->getUserInput();
      $form_tools = $user_input['tools'] ?? '';
      // Handle both flat (string) and nested (array with 'tools' key) formats.
      if (is_array($form_tools)) {
        $form_tools = $form_tools['tools'] ?? '';
      }
      if (is_string($form_tools) && $form_tools !== '') {
        $form_tools = array_filter(explode(',', $form_tools));
      }
      else {
        $form_tools = [];
      }
      foreach ($form_tools as $value) {
        $selected_tools[$value] = TRUE;
      }
    }
    else {
      $selected_tools = $this->entity->get('tools') ?? [];
    }

    // Show the selected tools, if they are selected.
    if (count($selected_tools)) {
      $form['prompt_detail']['tool_usage'] = [
        '#type' => 'container',
        '#prefix' => '<div id="tool-usage">',
        '#suffix' => '</div>',
        '#tree' => TRUE,
      ];

      foreach (array_keys($selected_tools) as $tool_id) {
        try {
          /** @var \Drupal\ai\Service\FunctionCalling\FunctionCallInterface $tool */
          $tool = $function_call_plugin_manager->createInstance($tool_id);
          $definition = $function_call_plugin_manager->getDefinition($tool_id);
          $this->createToolUsageForm($tool, $definition, $form, $form_state);
        }
        catch (\Exception) {
          // Do nothing.
        }
      }

    }
    else {
      // The tool-usage element needs to exist or the AJAX will have nothing
      // to replace.
      $form['prompt_detail']['tool_usage'] = [
        '#markup' => '<div id="tool-usage"></div>',
      ];
    }

    if (!$this->entity->isNew()) {
      $form['prompt_detail']['overrides'] = [
        '#type' => 'details',
        '#title' => $this->t('Overrides'),
        '#open' => FALSE,
        '#weight' => 90,
        '#description' => $this->t("Overrides are site-level adjustments shipped by recipes or modules. When enabled, they can add or remove tools and adjust this agent's instructions."),
      ];

      $override_storage = $this->entityTypeManager->getStorage('ai_agent_override');
      /** @var \Drupal\ai_agents\AiAgentOverrideInterface[] $overrides */
      $overrides = $override_storage->loadByProperties(['parent_agent' => $this->entity->id()]);

      if ($overrides === []) {
        $form['prompt_detail']['overrides']['empty'] = [
          '#markup' => '<p>' . $this->t('This agent has no overrides.') . '</p>',
        ];
      }
      else {
        usort($overrides, static function (AiAgentOverrideInterface $a, AiAgentOverrideInterface $b): int {
          return [$a->getWeight(), $a->label()] <=> [$b->getWeight(), $b->label()];
        });

        $options = [];
        $default_value = [];
        foreach ($overrides as $override) {
          $options[$override->id()] = $override->label();
          if ($override->status()) {
            $default_value[] = $override->id();
          }
        }

        $form['prompt_detail']['overrides']['override_status'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Available overrides'),
          '#options' => $options,
          '#default_value' => $default_value,
          '#description' => $this->t('Check the overrides you want enabled for this agent.'),
          '#parents' => ['override_status'],
          '#attributes' => ['class' => ['ai-agent-overrides-list']],
        ];
      }
    }
    return $form;
  }

  /**
   * Helper method to create the tool usage form.
   *
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallInterface $tool_instance
   *   The tool instance.
   * @param array $tool_definition
   *   The definition.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function createToolUsageForm(FunctionCallInterface $tool_instance, array $tool_definition, array &$form, FormStateInterface $form_state) {
    // Container.
    $id = 'tool-' . str_replace(':', '_', $tool_definition['id']);
    $tool_name = $tool_definition['name'] ?? $tool_definition['id'];
    $form['prompt_detail']['tool_usage'][$tool_definition['id']] = [
      '#type' => 'container',
      '#prefix' => '
      <div class="modal micromodal-slide" aria-hidden="true" id="' . $id . '">
        <div class="ai-modal__overlay" tabindex="-1" data-micromodal-close>
          <div class="ai-modal__container" role="dialog" aria-modal="true" aria-labelledby="' . $id . '-title" >
            <div class="ai-modal__header">
              <h2 class="ai-modal__title" id="' . $id . '-title">' . Html::escape((string) $tool_name) . '</h2>
              <a class="ai-tools-library-item__remove" aria-label="Close modal" data-micromodal-close></a>
            </div>

          <div class="ai-modal__content" id="' . $id . '-content">
      ',
      '#suffix' => '
            </div>
          </div>
        </div>
      </div>
      ',
    ];

    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['tool_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('General Tool Settings'),
      '#description' => $this->t('These are settings for how the tool should be used by the AI agent.'),
      '#open' => TRUE,
    ];

    // Allow to return directly.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['tool_settings']['return_directly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Return directly'),
      '#description' => $this->t('Check this box if you want to return the result directly, without the LLM trying to rewrite them or use another tool. This is usually used for tools that are not used in a conversation or when its being used in an API where the tools is the structured result.'),
      '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['return_directly'] ?? FALSE,
    ];

    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['tool_settings']['require_usage'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require Usage'),
      '#description' => $this->t('Check this box if there should be a reminder to the agent anytime it tries to output text, but this tool has not been used.'),
      '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['require_usage'] ?? FALSE,
    ];

    // Allow to override description.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['tool_settings']['description_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override tool description'),
      '#description' => $this->t('Check this box if you want to override the description of the tool that is sent to the LLM.'),
      '#default_value' => !empty($this->entity->get('tool_settings')[$tool_definition['id']]['description_override']) ? TRUE : FALSE,
    ];

    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['tool_settings']['description_override'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description override'),
      '#attributes' => [
        'rows' => 2,
      ],
      '#description' => $this->t('This will override the description of the tool that is sent to the LLM. Use this if you want to give more specific instructions on how to use the tool. Keep it empty if you want to use the default description. The current description is: %description', [
        '%description' => $tool_definition['description'] ?? '',
      ]),
      '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['description_override'] ?? "",
      '#states' => [
        'visible' => [
          ':input[name="tool_usage[' . $tool_definition['id'] . '][tool_settings][description_enabled]"]' => [
            ['checked' => TRUE],
          ],
        ],
      ],
    ];

    // Artifact storage of tool response.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['tool_settings']['use_artifacts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Artifact storage'),
      '#description' => $this->t('Store tool response in an artifact, using a placeholder instead of sending responses to the AI provider. This is useful for tools that return large amounts of data and will be referenced by other tools but not needed for AI. The artifact will be stored and can be referenced by the placeholder "{{artifact:&lt;function_name&gt;:&lt;index&gt;}}". i.e. {{artifact:%tool_id:1}}. <strong>You will need to adjust your prompt to accommodate this.</strong>', [
        '%tool_id' => $tool_definition['id'],
      ]),
      '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['use_artifacts'] ?? FALSE,
    ];

    // Allow for a progress message.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['tool_settings']['progress_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Progress Message'),
      '#attributes' => [
        'rows' => 2,
      ],
      '#description' => $this->t('If there is a polling service being used to show progress, this will be the message being used to show progress in any chatbot or other user interface.', [
        '%description' => $tool_definition['description'] ?? '',
      ]),
      '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['progress_message'] ?? "",
    ];

    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'] = [
      '#type' => 'details',
      '#title' => $this->t('Property setup'),
      '#open' => TRUE,
    ];

    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Property'),
        $this->t('Description override'),
        $this->t('Restrictions'),
      ],
      '#empty' => $this->t('No properties available.'),
      '#attributes' => ['class' => ['ai-agent-property-restrictions-table']],
    ];

    // Get all the contexts.
    $properties = $tool_instance->normalize()->getProperties();
    foreach ($properties as $property) {
      $property_name = $property->getName();

      // Get the default values.
      $default_action = '';
      $default_values = '';
      $is_hidden = FALSE;
      $not_break = FALSE;
      if ($form_state->isRebuilding()) {
        $default_action = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          'property_restrictions',
          'table',
          $property_name,
          'restrictions',
          'action',
        ]);
        $default_values = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          'property_restrictions',
          'table',
          $property_name,
          'restrictions',
          'values',
        ]);
        $is_hidden = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          'property_restrictions',
          'table',
          $property_name,
          'restrictions',
          'hide_property',
        ]);
        $not_break = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          $property_name,
          'not_break',
        ]);
      }
      elseif ($tool_usage_limits = $this->entity->get('tool_usage_limits')) {
        if (isset($tool_usage_limits[$tool_definition['id']][$property_name])) {
          $default_action = $tool_usage_limits[$tool_definition['id']][$property_name]['action'] ?? "";
          $values = is_array($tool_usage_limits[$tool_definition['id']][$property_name]['values']) ? $tool_usage_limits[$tool_definition['id']][$property_name]['values'] : [];
          $default_values = implode("\n", $values);
          $is_hidden = $tool_usage_limits[$tool_definition['id']][$property_name]['hide_property'] ?? FALSE;
          $not_break = $tool_usage_limits[$tool_definition['id']][$property_name]['not_break'] ?? FALSE;
        }
      }

      $row = [];

      // Column 1: Property name.
      $row['property_label'] = [
        '#markup' => $property_name,
      ];

      // Column 2: Description override.
      $row['description'] = [
        'property_description_enabled' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Override description'),
          '#default_value' => !empty($this->entity->get('tool_settings')[$tool_definition['id']]['property_description_override'][$property_name]) ? TRUE : FALSE,
        ],
        'property_description_override' => [
          '#type' => 'textarea',
          '#title' => $this->t('Overridden description'),
          '#title_display' => 'invisible',
          '#attributes' => [
            'rows' => 2,
          ],
          '#description' => $this->t('Current: %description', [
            '%description' => $property->getDescription() ?? '',
          ]),
          '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['property_description_override'][$property_name] ?? "",
          '#states' => [
            'visible' => [
              ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][table][' . $property_name . '][description][property_description_enabled]"]' => [
                ['checked' => TRUE],
              ],
            ],
          ],
        ],
      ];

      // Column 3: Restrictions.
      $row['restrictions'] = [
        'action' => [
          '#type' => 'select',
          '#title' => $this->t('Restrictions'),
          '#title_display' => 'invisible',
          '#description' => $this->t('Select the type of restriction you want to apply to this property.'),
          '#options' => [
            '' => $this->t('Allow all'),
            'only_allow' => $this->t('Only allow certain values'),
            'force_value' => $this->t('Force value'),
          ],
          '#default_value' => $default_action,
        ],
        'hide_property' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Hide property'),
          '#description' => $this->t('Hide from LLM and logs.'),
          '#default_value' => $is_hidden,
          '#states' => [
            'visible' => [
              ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][table][' . $property_name . '][restrictions][action]"]' => [
                ['value' => 'force_value'],
              ],
            ],
          ],
        ],
        'values' => [
          '#type' => 'textarea',
          '#title' => $this->t('Values'),
          '#title_display' => 'invisible',
          '#description' => $this->t('Allowed values (newline separated) or forced value.'),
          '#default_value' => $default_values,
          '#rows' => 2,
          '#states' => [
            'visible' => [
              ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][table][' . $property_name . '][restrictions][action]"]' => [
                ['value' => 'only_allow'],
                'or',
                ['value' => 'force_value'],
              ],
            ],
          ],
        ],
        'not_break' => [
          '#type' => 'checkbox',
          '#title' => $this->t('One value, no line breaks'),
          '#description' => $this->t('Check this box if you do not want multiple values to be set on new line. This is useful for forced values of hardcoded prompts for instance.'),
          '#default_value' => $not_break,
          '#states' => [
            'visible' => [
              ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][table][' . $property_name . '][restrictions][action]"]' => [
                ['value' => 'only_allow'],
                'or',
                ['value' => 'force_value'],
              ],
            ],
          ],
        ],
      ];

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions']['table'][$property_name] = $row;
    }
  }

  /**
   * Ajax callback to add more information about the tool.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function modifyToolDescription(&$form, FormStateInterface $form_state) {
    return $form['prompt_detail']['tool_usage'];
  }

  /**
   * Builds enforced tool states keyed by plugin ID.
   *
   * @param array|null $statusOverrides
   *   Optional array of override statuses from form submission.
   *   Keyed by override ID, value is boolean (enabled/disabled).
   *
   * @return array
   *   A map of tool IDs to enforced state definitions.
   */
  private function getOverrideToolEnforcements(?array $statusOverrides = NULL): array {
    $storage = $this->entityTypeManager->getStorage('ai_agent_override');
    /** @var \Drupal\ai_agents\AiAgentOverrideInterface[] $overrides */
    $overrides = $storage->loadByProperties(['parent_agent' => $this->entity->id()]);

    if ($overrides === []) {
      return [];
    }

    usort($overrides, static function (AiAgentOverrideInterface $a, AiAgentOverrideInterface $b): int {
      return [$a->getWeight(), $a->label()] <=> [$b->getWeight(), $b->label()];
    });

    $enforcements = [];

    foreach ($overrides as $override) {
      // Determine if override is active: use form submission if available,
      // otherwise use persisted status.
      $isActive = $statusOverrides !== NULL
        ? !empty($statusOverrides[$override->id()])
        : $override->status();

      if (!$isActive) {
        continue;
      }

      foreach ($override->getToolsToAdd() as $toolId) {
        if ($toolId === '') {
          continue;
        }
        $label = (string) $override->label();
        if (!isset($enforcements[$toolId]) || $enforcements[$toolId]['state'] !== TRUE) {
          $enforcements[$toolId] = [
            'state' => TRUE,
            'labels' => [$label],
          ];
        }
        else {
          $enforcements[$toolId]['labels'][] = $label;
        }
      }

      foreach ($override->getToolsToRemove() as $toolId) {
        if ($toolId === '') {
          continue;
        }
        $label = (string) $override->label();
        $enforcements[$toolId] = [
          'state' => FALSE,
          'labels' => [$label],
        ];
      }
    }

    // Normalize label lists.
    foreach ($enforcements as &$definition) {
      $definition['labels'] = array_values(array_unique($definition['labels'] ?? []));
      if ($definition['labels'] === []) {
        $definition['labels'][] = (string) $this->t('Override');
      }
    }

    return $enforcements;
  }

  /**
   * Persists the override enable/disable selections.
   */
  private function persistOverrideStatuses(?array $submittedStatuses): void {
    if ($submittedStatuses === NULL || $this->entity->isNew()) {
      return;
    }

    $override_storage = $this->entityTypeManager->getStorage('ai_agent_override');
    /** @var \Drupal\ai_agents\AiAgentOverrideInterface[] $overrides */
    $overrides = $override_storage->loadByProperties(['parent_agent' => $this->entity->id()]);

    foreach ($overrides as $override) {
      if (!$override instanceof AiAgentOverrideInterface) {
        continue;
      }
      $shouldBeEnabled = !empty($submittedStatuses[$override->id()]);
      if ($override->status() !== $shouldBeEnabled) {
        $override->setStatus($shouldBeEnabled);
        $override->save();
      }
    }
  }

  /**
   * After build callback for the tools library element.
   *
   * Adds #limit_validation_errors to the update_widget button so the form
   * properly rebuilds after tool selection without requiring valid form data.
   */
  public static function afterBuildToolsLibrary(array $element, FormStateInterface $form_state): array {
    if (isset($element['tools_library']['update_widget'])) {
      $element['tools_library']['update_widget']['#limit_validation_errors'] = [];

      // The triggering element is stored as a copy during doBuildForm before
      // #after_build runs. Update it so validation respects our change.
      $triggering = $form_state->getTriggeringElement();
      if ($triggering && ($triggering['#name'] ?? NULL) === ($element['tools_library']['update_widget']['#name'] ?? NULL)) {
        $triggering['#limit_validation_errors'] = [];
        $form_state->setTriggeringElement($triggering);
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(&$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // If its a new entity, we do this check.
    if ($this->entity->isNew()) {
      // Check so the function name does not exist.
      if ($this->functionCallPluginManager->functionExists($this->entity->id())) {
        $form_state->setErrorByName('id', $this->t('The function name already exists.'));
      }
    }
    // If structured output is enabled, check if the schema is valid JSON.
    if ($form_state->getValue('structured_output_enabled')) {
      $schema = $form_state->getValue('structured_output_schema');
      if (!empty($schema)) {
        json_decode($schema);
        if (json_last_error() !== JSON_ERROR_NONE) {
          $form_state->setErrorByName('structured_output_schema', $this->t('The JSON schema is not valid JSON: %error', ['%error' => json_last_error_msg()]));
        }
      }
      else {
        $form_state->setErrorByName('structured_output_schema', $this->t('The JSON schema is required if structured output is enabled.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $tools = [];
    foreach ($form_state->getValue('tools') as $value) {
      $tools[$value] = TRUE;
    }
    // Apply override enforcements after user selection.
    // Pass the new override selections so we use the form-submitted status
    // instead of the persisted status (which hasn't been saved yet).
    if (!$this->entity->isNew()) {
      $overrideSelections = $form_state->getValue('override_status');
      foreach ($this->getOverrideToolEnforcements($overrideSelections) as $toolId => $enforcement) {
        if ($enforcement['state']) {
          $tools[$toolId] = TRUE;
        }
        else {
          unset($tools[$toolId]);
        }
      }
    }
    // Tool usage limits.
    $tool_usage_limits = [];

    // Save tools settings.
    $tool_settings = [];
    if (!empty($form_state->getValue('tool_usage'))) {
      foreach ($form_state->getValue('tool_usage') as $tool_id => $tool_usage) {
        // Check if it should return directly.
        $tool_settings[$tool_id]['return_directly'] = $tool_usage['tool_settings']['return_directly'] ?? FALSE;
        $tool_settings[$tool_id]['require_usage'] = $tool_usage['tool_settings']['require_usage'] ?? FALSE;
        // Check if description override is enabled.
        if (!empty($tool_usage['tool_settings']['description_enabled'])) {
          $tool_settings[$tool_id]['description_override'] = $tool_usage['tool_settings']['description_override'] ?? '';
        }
        else {
          $tool_settings[$tool_id]['description_override'] = '';
        }
        $tool_settings[$tool_id]['progress_message'] = $tool_usage['tool_settings']['progress_message'] ?? '';
        $tool_settings[$tool_id]['use_artifacts'] = $tool_usage['tool_settings']['use_artifacts'] ?? FALSE;
        $processed_restrictions = [];
        if (isset($tool_usage['property_restrictions']['table'])) {
          foreach ($tool_usage['property_restrictions']['table'] as $property_name => $values) {
            $restrictions = $values['restrictions'] ?? [];
            $description = $values['description'] ?? [];
            // Only set if an action is set.
            if (!empty($restrictions['action'])) {
              $cleaned_values = str_replace("\r\n", "\n", $restrictions['values'] ?? '');
              // Trim and remove all empty values.
              $all_values = $restrictions['not_break'] ? [trim($cleaned_values)] : array_filter(array_map('trim', explode("\n", $cleaned_values)));
              $processed_restrictions[$property_name] = [
                'action' => $restrictions['action'],
                'values' => $all_values,
                'hide_property' => $restrictions['hide_property'] ?? FALSE,
              ];
            }
            // Save the property description override as well.
            if (!empty($description['property_description_enabled'])) {
              $tool_settings[$tool_id]['property_description_override'][$property_name] = $description['property_description_override'] ?? '';
            }
            else {
              // Make sure to remove it if its not enabled.
              if (isset($tool_settings[$tool_id]['property_description_override'][$property_name])) {
                unset($tool_settings[$tool_id]['property_description_override'][$property_name]);
              }
            }
          }
        }
        if (count($tool_usage)) {
          $tool_usage_limits[$tool_id] = $processed_restrictions;
        }
      }
    }

    // Handle the secured system prompt.
    if (Settings::get('show_secured_ai_agent_system_prompt', FALSE)) {
      $secured_system_prompt = $form_state->getValue('secured_system_prompt');
      $this->entity->set('secured_system_prompt', $secured_system_prompt);
    }
    else {
      $secured_system_prompt = $this->entity->get('secured_system_prompt');
      if (empty($secured_system_prompt)) {
        // Set default value to [ai_agent:agent_instructions] if empty.
        $this->entity->set('secured_system_prompt', '[ai_agent:agent_instructions]');
      }
    }

    $this->entity->set('tool_usage_limits', $tool_usage_limits);
    // Store the json schema.
    $this->entity->set('structured_output_schema', $form_state->getValue('structured_output_schema'));
    $this->entity->set('structured_output_enabled', $form_state->getValue('structured_output_enabled'));
    $this->entity->set('tool_settings', $tool_settings);
    $this->entity->set('tools', $tools);

    // Make sure to remove \r characters from the yaml fields for nice YAML.
    // See: https://www.drupal.org/project/drupal/issues/3202796.
    $system_prompt = str_replace("\r\n", "\n", $form_state->getValue('system_prompt') ?? '');
    $this->entity->set('system_prompt', $system_prompt);
    $default_information_tools = str_replace("\r\n", "\n", $form_state->getValue('default_information_tools') ?? '');
    $this->entity->set('default_information_tools', $default_information_tools);

    $overrideSelections = $this->entity->isNew() ? NULL : $form_state->getValue('override_status');

    $result = parent::save($form, $form_state);

    if ($overrideSelections !== NULL) {
      $this->persistOverrideStatuses($overrideSelections);
    }
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl(Url::fromRoute('entity.ai_agent.collection'));
    return $result;
  }

}
