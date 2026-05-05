<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager;
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
   */
  public function __construct(
    protected FunctionCallPluginManager $functionCallPluginManager,
    protected FunctionGroupPluginManager $functionGroupPluginManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai.function_calls'),
      $container->get('plugin.manager.ai.function_groups'),
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
      'max_loops' => 3,
      'system_prompt' => '',
      'secured_system_prompt' => '[ai_agent:agent_instructions]',
      'default_information_tools' => '',
      'structured_output_enabled' => FALSE,
      'structured_output_schema' => '',
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
      '#description' => $this->t('A description of the AI agent. This is really important, because triage agents or orchestration tools will base their decisions to pick the right agent on this.'),
      '#required' => TRUE,
      '#default_value' => $config['description'],
      '#attributes' => [
        'rows' => 2,
      ],
    ];

    $form['orchestration_agent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Swarm orchestration agent'),
      '#description' => $this->t('Check this box if this AI agent is a swarm orchestration agent. Swarm orchestration agents are usually a direct agent a UI can talk to that collects information and sets up tasks for other agents. Note that orchestration agents usually only work with context and agent tools and should have a least one agent tool.'),
      '#default_value' => $config['orchestration_agent'],
    ];

    $form['triage_agent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Project manager agent'),
      '#description' => $this->t('Check this box if this AI agent is a project manager agent that usually runs first. Only an recommendation and will not be used by all swarm tools.'),
      '#default_value' => $config['triage_agent'],
    ];

    $form['max_loops'] = [
      '#type' => 'number',
      '#title' => $this->t('Max loops'),
      '#description' => $this->t('The maximum amount of loops that the AI agent can run to feed itself with new context before giving up. This is a security feature to prevent infinite loops.'),
      '#default_value' => $config['max_loops'],
      '#required' => TRUE,
    ];

    $form['prompt_detail'] = [
      '#type' => 'details',
      '#title' => $this->t('Usage details'),
      '#open' => TRUE,
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

    $form['prompt_detail']['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Agent Instructions'),
      '#description' => $this->t('Specific instructions that define how the AI agent should behave and respond to tasks for a particular interaction.'),
      '#required' => TRUE,
      '#default_value' => $config['system_prompt'],
      '#attributes' => [
        'rows' => 10,
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

    $form['prompt_detail']['default_information_tools'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default information tools'),
      '#description' => $this->t('A list of default information tools that can be used by the AI agent. You can either give an empty value, hardcoded value or dynamic value to parameters. If a dynamic value is set, an LLM will try to figure out how to fill in the value.'),
      '#default_value' => $config['default_information_tools'],
    ];

    // Add structured output if wanted in settings.
    $form['prompt_detail']['structured_output_detail'] = [
      '#type' => 'details',
      '#title' => $this->t('Structured output'),
      '#description' => $this->t('Settings for providing structured (JSON) output from the AI agent.'),
      '#open' => $config['structured_output_enabled'],
    ];

    $form['prompt_detail']['structured_output_detail']['structured_output_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Structured Output'),
      '#description' => $this->t('Check this box if you want the AI agent to provide structured (JSON) output. This is useful if you want to use the output in a structured way, like in a workflow or to parse it easily. You will have to provider a JSON schema of the output wanted.'),
      '#default_value' => $config['structured_output_enabled'],
    ];

    $form['prompt_detail']['structured_output_detail']['structured_output_schema'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JSON Schema'),
      '#description' => $this->t('The JSON schema that defines the structured output. Please provide a valid JSON schema according to OpenAI documentation: %link', [
        '%link' => Link::fromTextAndUrl($this->t('JSON Schema'), Url::fromUri('https://platform.openai.com/docs/guides/structured-outputs#examples', [
          'attributes' => [
            'target' => '_blank',
          ],
        ]))->toString(),
      ]),
      '#default_value' => $config['structured_output_schema'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form['#attached']['library'][] = 'ai_agents/agents_form';

    $form['#title'] = $this->t('AI agent: %label', [
      '%label' => $this->entity->label() ?? $this->t('Create new AI agent'),
    ]);

    $form = $this->buildFormMetadata(
      $form,
      [
        'label' => $this->entity->label(),
        'id' => $this->entity->id(),
        'description' => $this->entity->get('description'),
        'orchestration_agent' => $this->entity->get('orchestration_agent'),
        'triage_agent' => $this->entity->get('triage_agent'),
        'max_loops' => $this->entity->get('max_loops') ?? 3,
        'system_prompt' => $this->entity->get('system_prompt'),
        'secured_system_prompt' => $this->entity->get('secured_system_prompt') ?? '[ai_agent:agent_instructions]',
        'default_information_tools' => $this->entity->get('default_information_tools') ? Yaml::dump(Yaml::parse($this->entity->get('default_information_tools') ?? ''), 10, 2) : NULL,
        'structured_output_enabled' => $this->entity->get('structured_output_enabled') ?? FALSE,
        'structured_output_schema' => $this->entity->get('structured_output_schema') ?? '',
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
    ];

    // Selected tools.
    $selected_tools = [];
    if ($form_state->isRebuilding()) {
      foreach ($form_state->getValue('tools') as $value) {
        $selected_tools[$value] = TRUE;
      }
    }
    else {
      $selected_tools = $this->entity->get('tools') ?? [];
    }

    // Show the selected tools, if they are selected.
    if (count($selected_tools)) {
      $form['prompt_detail']['tool_usage'] = [
        '#type' => 'details',
        '#title' => $this->t('Detailed tool usage'),
        '#open' => TRUE,
        '#prefix' => '<div id="tool-usage">',
        '#suffix' => '</div>',
        '#tree' => TRUE,
      ];

      // Show the token browser if the module is enabled.
      if ($this->moduleHandler->moduleExists('token')) {
        $form['prompt_detail']['tool_usage']['#description'] = $this->t('The token browser can be used for the values you set in the Detail Tool Usage.');

        $form['prompt_detail']['tool_usage']['token_help'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => [],
        ];
      }

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
    // Details.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']] = [
      '#type' => 'details',
      '#title' => $tool_definition['name'],
      '#open' => FALSE,
    ];

    // Allow to return directly.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['return_directly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Return directly'),
      '#description' => $this->t('Check this box if you want to return the result directly, without the LLM trying to rewrite them or use another tool. This is usually used for tools that are not used in a conversation or when its being used in an API where the tools is the structured result.'),
      '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['return_directly'] ?? FALSE,
    ];

    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['require_usage'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require Usage'),
      '#description' => $this->t('Check this box if there should be a reminder to the agent anytime it tries to output text, but this tool has not been used.'),
      '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['require_usage'] ?? FALSE,
    ];

    // Allow to override description.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['description_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override tool description'),
      '#description' => $this->t('Check this box if you want to override the description of the tool that is sent to the LLM.'),
      '#default_value' => !empty($this->entity->get('tool_settings')[$tool_definition['id']]['description_override']) ? TRUE : FALSE,
    ];

    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['description_override'] = [
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
          ':input[name="tool_usage[' . $tool_definition['id'] . '][description_enabled]"]' => [
            ['checked' => TRUE],
          ],
        ],
      ],
    ];

    // Artifact storage of tool response.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['use_artifacts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Artifact storage'),
      '#description' => $this->t('Store tool response in an artifact, using a placeholder instead of sending responses to the AI provider. This is useful for tools that return large amounts of data and will be referenced by other tools but not needed for AI. The artifact will be stored and can be referenced by the placeholder "{{artifact:&lt;function_name&gt;:&lt;index&gt;}}". i.e. {{artifact:%tool_id:1}}. <strong>You will need to adjust your prompt to accommodate this.</strong>', [
        '%tool_id' => $tool_definition['id'],
      ]),
      '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['use_artifacts'] ?? FALSE,
    ];

    // Allow for a progress message.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['progress_message'] = [
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
    ];

    // Get all the contexts.
    $properties = $tool_instance->normalize()->getProperties();
    foreach ($properties as $property) {
      $property_name = $property->getName();
      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name] = [
        '#type' => 'fieldset',
        '#attributes' => [
          'class' => ['tool-usage-container'],
        ],
      ];

      // Get the default values.
      $default_action = '';
      $default_values = '';
      $is_hidden = FALSE;
      $not_break = FALSE;
      if ($form_state->isRebuilding()) {
        $default_action = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          $property_name,
          'action',
        ]);
        $default_values = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          $property_name,
          'values',
        ]);
        $is_hidden = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          $property_name,
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

      // Make sure to open if there is a value set.
      if ($default_action || $default_values) {
        $form['prompt_detail']['tool_usage'][$tool_definition['id']]['#open'] = TRUE;
      }

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name]['property_description_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Override property description'),
        '#description' => $this->t('Check this box if you want to override the description of the property that is sent to the LLM.'),
        '#default_value' => !empty($this->entity->get('tool_settings')[$tool_definition['id']]['property_description_override'][$property_name]) ? TRUE : FALSE,
      ];

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name]['property_description_override'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Override %name property description', [
          '%name' => $property_name,
        ]),
        '#attributes' => [
          'rows' => 2,
        ],
        '#description' => $this->t('This will override the description of the property that is sent to the LLM. Use this if you want to give more specific instructions on how to use the property. The current description is: %description', [
          '%description' => $property->getDescription() ?? '',
        ]),
        '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['property_description_override'][$property_name] ?? "",
        '#states' => [
          'visible' => [
            ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][' . $property_name . '][property_description_enabled]"]' => [
              ['checked' => TRUE],
            ],
          ],
        ],
      ];

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name]['action'] = [
        '#type' => 'select',
        '#title' => $this->t('Restrictions for property %name', [
          '%name' => $property_name,
        ]),
        '#options' => [
          '' => $this->t('Allow all'),
          'only_allow' => $this->t('Only allow certain values'),
          'force_value' => $this->t('Force value'),
        ],
        '#description' => $this->t('Restrict the allowed values or enforce a value.'),
        '#default_value' => $default_action,
      ];

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name]['hide_property'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Hide property'),
        '#description' => $this->t('Check this box if you want to hide this property from being sent to the LLM or from being logged. For instance for API keys.'),
        '#default_value' => $is_hidden,
        '#states' => [
          'visible' => [
            ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][' . $property_name . '][action]"]' => [
              ['value' => 'force_value'],
            ],
          ],
        ],
      ];

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name]['not_break'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('One value, no line breaks'),
        '#description' => $this->t('Check this box if you do not want multiple values to be set on new line. This is useful for forced values of hardcoded prompts for instance.'),
        '#default_value' => $not_break,
        '#states' => [
          'visible' => [
            ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][' . $property_name . '][action]"]' => [
              ['value' => 'only_allow'],
              'or',
              ['value' => 'force_value'],
            ],
          ],
        ],
      ];

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name]['values'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Values'),
        '#description' => $this->t('The values that are allowed or the value that should be set. Depending on the checkbox above named "One value, no line breaks" you can either provide one value or multiple values separated by new lines.'),
        '#default_value' => $default_values,
        '#rows' => 2,
        '#states' => [
          'visible' => [
            ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][' . $property_name . '][action]"]' => [
              ['value' => 'only_allow'],
              'or',
              ['value' => 'force_value'],
            ],
          ],
        ],
      ];
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
    $dependencies = [];
    // Remove unchecked values.
    foreach ($tools as $key => $value) {
      $tool = $this->functionCallPluginManager->getDefinition($key);
      $dependencies[] = $tool['provider'];
    }
    // Tool usage limits.
    $tool_usage_limits = [];

    // Save tools settings.
    $tool_settings = [];
    if (!empty($form_state->getValue('tool_usage'))) {
      foreach ($form_state->getValue('tool_usage') as $tool_id => $tool_usage) {
        // Check if it should return directly.
        $tool_settings[$tool_id]['return_directly'] = $tool_usage['return_directly'] ?? FALSE;
        $tool_settings[$tool_id]['require_usage'] = $tool_usage['require_usage'] ?? FALSE;
        // Check if description override is enabled.
        if (!empty($tool_usage['description_enabled'])) {
          $tool_settings[$tool_id]['description_override'] = $tool_usage['description_override'] ?? '';
        }
        else {
          $tool_settings[$tool_id]['description_override'] = '';
        }
        $tool_settings[$tool_id]['progress_message'] = $tool_usage['progress_message'] ?? '';
        $tool_settings[$tool_id]['use_artifacts'] = $tool_usage['use_artifacts'] ?? FALSE;
        if (isset($tool_usage['property_restrictions'])) {
          foreach ($tool_usage['property_restrictions'] as $property_name => $values) {
            // Only set if an action is set.
            if ($values['action']) {
              $cleaned_values = str_replace("\r\n", "\n", $values['values'] ?? '');
              // Trim and remove all empty values.
              $all_values = $values['not_break'] ? [trim($cleaned_values)] : array_filter(array_map('trim', explode("\n", $cleaned_values)));
              $tool_usage['property_restrictions'][$property_name]['values'] = $all_values;
            }
            else {
              unset($tool_usage[$property_name]);
            }
            // Save the property description override as well.
            if (!empty($tool_usage['property_restrictions'][$property_name]['property_description_enabled'])) {
              $tool_settings[$tool_id]['property_description_override'][$property_name] = $values['property_description_override'];
            }
            else {
              // Make sure to remove it if its not enabled.
              if (isset($tool_settings[$tool_id]['property_description_override'][$property_name])) {
                unset($tool_settings[$tool_id]['property_description_override'][$property_name]);
              }
            }
            // Remove it from the tool_usage.
            if (isset($tool_usage['property_restrictions'][$property_name]) && isset($tool_usage['property_restrictions'][$property_name]['property_description_override'])) {
              unset($tool_usage['property_restrictions'][$property_name]['property_description_override']);
              unset($tool_usage['property_restrictions'][$property_name]['property_description_enabled']);
            }
          }
        }
        if (count($tool_usage)) {
          $tool_usage_limits[$tool_id] = $tool_usage['property_restrictions'] ?? [];
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

    // Make sure to set dependencies on the tools.
    $this->entity->set('dependencies', array_unique($dependencies));
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

    $result = parent::save($form, $form_state);
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
