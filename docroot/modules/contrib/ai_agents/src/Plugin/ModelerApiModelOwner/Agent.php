<?php

namespace Drupal\ai_agents\Plugin\ModelerApiModelOwner;

use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_agents\Entity\AiAgent;
use Drupal\ai_agents\Form\AiAgentForm;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Attribute\ModelOwner;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Form\Settings as ModelerApiSettings;
use Drupal\modeler_api\Plugin\ComponentWrapperPlugin;
use Drupal\modeler_api\Plugin\ComponentWrapperPluginInterface;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Model owner plugin implementation for AI Agents.
 */
#[ModelOwner(
  id: "ai_agents_agent",
  label: new TranslatableMarkup("AI Agent"),
  description: new TranslatableMarkup("Configure AI Agents"),
  uiLabelNewModel: new TranslatableMarkup("New AI Agent"),
  uiLabelNewModelWithModeler: new TranslatableMarkup("New AI Agent with modeler"),
)]
class Agent extends ModelOwnerBase {

  public const SUPPORTED_COMPONENT_TYPES = [
    Api::COMPONENT_TYPE_START => 'agent',
    Api::COMPONENT_TYPE_SUBPROCESS => 'wrapper',
    Api::COMPONENT_TYPE_ELEMENT => 'tool',
    Api::COMPONENT_TYPE_LINK => 'link',
  ];

  /**
   * The list of sub models.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   */
  protected array $subModels = [];

  /**
   * Dependency Injection container.
   *
   * Used for getter injection.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|null
   */
  protected ?ContainerInterface $container;

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected FunctionCallPluginManager $functionCallPluginManager;

  /**
   * The random generator.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected Random $random;

  /**
   * Get Dependency Injection container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   Current Dependency Injection container.
   */
  protected function getContainer(): ContainerInterface {
    if (!isset($this->container)) {
      // @phpstan-ignore-next-line
      $this->container = \Drupal::getContainer();
    }
    return $this->container;
  }

  /**
   * Get the function call plugin manager.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   *   The function call plugin manager.
   */
  protected function functionCallPluginManager(): FunctionCallPluginManager {
    if (!isset($this->functionCallPluginManager)) {
      $this->functionCallPluginManager = $this->getContainer()->get('plugin.manager.ai.function_calls');
    }
    return $this->functionCallPluginManager;
  }

  /**
   * Get the random generator.
   *
   * @return \Drupal\Component\Utility\Random
   *   The random generator.
   */
  protected function random(): Random {
    if (!isset($this->random)) {
      $this->random = new Random();
    }
    return $this->random;
  }

  /**
   * {@inheritdoc}
   */
  public function modelIdExistsCallback(): array {
    return [AiAgent::class, 'load'];
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityProviderId(): string {
    return 'ai_agents';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityTypeId(): string {
    return 'ai_agent';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityBasePath(): ?string {
    return 'admin/config/ai/agents';
  }

  /**
   * {@inheritdoc}
   */
  public function modelConfigFormAlter(array &$form): void {
    $form['label']['#access'] = FALSE;
    $form['model_id']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponents(ConfigEntityInterface $model, ?string $parentId = NULL): array {
    assert($model instanceof AiAgent);
    $components = [];
    $config = [];
    foreach (AiAgentForm::defaultConfigMetadata('agent_id') as $key => $value) {
      $config[$key] = match ($key) {
        'agent_id' => $model->get('id'),
        'max_loops' => (string) $model->get($key),
        default => $model->get($key),
      };
    }
    $modelComponent = new Component(
      $this,
      $this->random()->name(20, TRUE),
      Api::COMPONENT_TYPE_START,
      $model->get('id'),
      $model->get('label') ?? '',
      $config,
      [],
      $parentId,
    );
    $components[] = $modelComponent;
    $successors = [];
    foreach ($model->get('tools') ?? [] as $id => $flag) {
      if (!$flag) {
        continue;
      }
      if (str_starts_with($id, 'ai_agents::ai_agent::')) {
        $id = substr($id, strlen('ai_agents::ai_agent::'));
        if ($subModel = AiAgent::load($id)) {
          $successors[] = new ComponentSuccessor($id, '');
          $components[] = new Component(
            $this,
            $id,
            Api::COMPONENT_TYPE_SUBPROCESS,
            $id,
            $subModel->get('label'),
            [],
            [],
            $parentId,
          );
          foreach ($this->usedComponents($subModel, $id) as $component) {
            $components[] = $component;
          }
        }
      }
      else {
        try {
          $plugin = $this->functionCallPluginManager()->createInstance($id);
        }
        catch (PluginException) {
          continue;
        }
        $componentId = $this->random()->name(20, TRUE);
        $successors[] = new ComponentSuccessor($componentId, '');
        $label = $plugin->pluginDefinition['label'] ?? $plugin->pluginDefinition['name'] ?? $id;
        $config = [];
        foreach ($model->get('tool_usage_limits')[$id] ?? [] as $key => $values) {
          $key = str_replace(':', '__colon__', $key);
          foreach ($values as $valueKey => $value) {
            if (is_numeric($value)) {
              $value = (bool) $value;
            }
            $config[$key . '___' . $valueKey] = is_array($value) ? implode("\n", $value) : $value;
          }
        }
        $config['return_directly'] = (bool) ($model->get('tool_settings')[$id]['return_directly'] ?? 0);
        $config['require_usage'] = (bool) ($model->get('tool_settings')[$id]['require_usage'] ?? 0);
        $config['use_artifacts'] = (bool) ($model->get('tool_settings')[$id]['use_artifacts'] ?? 0);
        $config['progress_message'] = (string) ($model->get('tool_settings')[$id]['progress_message'] ?? '');

        $components[] = new Component(
          $this,
          $componentId,
          Api::COMPONENT_TYPE_ELEMENT,
          $id,
          $label,
          $config,
          [],
          $parentId,
        );
      }
    }
    $modelComponent->setSuccessors($successors);
    return $components;
  }

  /**
   * {@inheritdoc}
   */
  public function supportedOwnerComponentTypes(): array {
    return self::SUPPORTED_COMPONENT_TYPES;
  }

  /**
   * {@inheritdoc}
   */
  public function availableOwnerComponents(int $type): array {
    return match($type) {
      Api::COMPONENT_TYPE_START => [new ComponentWrapperPlugin(Api::COMPONENT_TYPE_START, 'New_Subagent')],
      Api::COMPONENT_TYPE_ELEMENT => $this->createAllInstances(),
      Api::COMPONENT_TYPE_SUBPROCESS => $this->getAllAgents(),
      default => [],
    };
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentId(int $type): string {
    return self::SUPPORTED_COMPONENT_TYPES[$type] ?? 'unsupported';
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentDefaultConfig(int $type, string $id): array {
    $config = [];
    $plugin = $this->ownerComponent($type, $id);
    if ($plugin instanceof FunctionCallInterface) {
      $config['return_directly'] = FALSE;
      $config['require_usage'] = FALSE;
      $config['use_artifacts'] = FALSE;
      $config['progress_message'] = '';
      $properties = $plugin->normalize()->getProperties();
      foreach ($properties as $property) {
        $property_name = $property->getName();
        $config[$property_name . '___action'] = '';
        $config[$property_name . '___hide_property'] = FALSE;
        $config[$property_name . '___values'] = '';
      }
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentEditable(PluginInspectionInterface $plugin): bool {
    if ($plugin instanceof ComponentWrapperPluginInterface && $plugin->getType() === Api::COMPONENT_TYPE_SUBPROCESS) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentPluginChangeable(PluginInspectionInterface $plugin): bool {
    if ($plugin instanceof ComponentWrapperPluginInterface) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponent(int $type, string $id, array $config = []): ?PluginInspectionInterface {
    return match($type) {
      Api::COMPONENT_TYPE_START => new ComponentWrapperPlugin(Api::COMPONENT_TYPE_START, $id, $config),
      Api::COMPONENT_TYPE_ELEMENT => $this->functionCallPluginManager()->createInstance($id, $config),
      Api::COMPONENT_TYPE_SUBPROCESS => new ComponentWrapperPlugin(Api::COMPONENT_TYPE_SUBPROCESS, $id, $config),
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(PluginInspectionInterface $plugin, ?string $modelId = NULL, bool $modelIsNew = TRUE): array {
    $form = [];
    if ($plugin instanceof ComponentWrapperPluginInterface && $plugin->getType() === Api::COMPONENT_TYPE_START) {
      $agentForm = AiAgentForm::create($this->getContainer());
      $config = $plugin->getConfiguration() + AiAgentForm::defaultConfigMetadata('agent_id');
      $configuredModelId = $config['agent_id'];
      $config['agent_id'] = $modelIsNew ? '' : $config['agent_id'];
      $form = $agentForm->buildFormMetadata([], $config, 'agent_id', !(!$modelIsNew && $config['agent_id'] !== ''), FALSE);
      if ($modelId === $configuredModelId) {
        $form['agent_id']['#attributes'] = [
          'data-modeler-api-model-id' => '1',
        ];
      }
    }
    elseif ($plugin instanceof FunctionCallInterface) {
      $form['return_directly'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Return directly'),
        '#description' => $this->t('Check this box if you want to return the result directly, without the LLM trying to rewrite them or use another tool. This is usually used for tools that are not used in a conversation or when its being used in an API where the tools is the structured result.'),
        '#default_value' => FALSE,
      ];

      $form['require_usage'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Require usage'),
        '#description' => $this->t('Check this box if you want to require that this tool is used at least once in the agent execution. If the LLM does not use this tool, an error will be thrown. This is useful for tools that must be used, e.g. for compliance reasons.'),
        '#default_value' => FALSE,
      ];

      $form['use_artifacts'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use artifacts'),
        '#description' => $this->t('Store tool response in an artifact, using a placeholder instead of sending responses to the AI provider. This is useful for tools that return large amounts of data and will be referenced by other tools but not needed for AI.'),
        '#default_value' => FALSE,
      ];

      $form['progress_message'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Progress message'),
        '#description' => $this->t('An optional message that will be shown to the user while this tool is being executed. This can be used to inform the user about the current progress of the agent.'),
        '#default_value' => '',
      ];
      $properties = $plugin->normalize()->getProperties();
      foreach ($properties as $property) {
        $property_name = $property->getName();

        $form[$property_name . '___action'] = [
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
          '#default_value' => '',
        ];

        $form[$property_name . '___hide_property'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Hide property'),
          '#description' => $this->t('Check this box if you want to hide this property from being sent to the LLM or from being logged. For instance for API keys.'),
          '#default_value' => FALSE,
        ];

        $form[$property_name . '___values'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Values'),
          '#description' => $this->t('The values that are allowed or the value that should be set. If you pick to only allow certain values, you can set the allowed values new line separated if there are more then one. If you pick to force a value, you can set the value that should be set.'),
          '#default_value' => '',
          '#rows' => 2,
          '#states' => [
            'visible' => [
              ':input[name="' . $property_name . '___action"]' => [
                ['value' => 'only_allow'],
                ['value' => 'force_value'],
              ],
            ],
          ],
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function resetComponents(ConfigEntityInterface $model): ModelOwnerInterface {
    assert($model instanceof AiAgent);
    $this->subModels = [];
    $model->set('tools', []);
    $model->set('tool_usage_limits', []);
    $model->set('tool_settings', []);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addComponent(ConfigEntityInterface $model, Component $component): bool {
    if ($component->getType() === Api::COMPONENT_TYPE_LINK) {
      return TRUE;
    }
    $parentId = $component->getParentId();
    if ($parentId !== NULL && $parentId !== $model->id()) {
      if (!isset($this->subModels[$parentId])) {
        $model = AiAgent::load($parentId);
        if ($model === NULL) {
          $config = AiAgentForm::defaultConfigMetadata('id');
          $config['id'] = $parentId;
          $config['tools'] = [];
          $model = AiAgent::create($config);
        }
        $this->subModels[$component->getParentId()] = $model;
      }
      $model = $this->subModels[$component->getParentId()];
    }

    switch ($component->getType()) {
      case Api::COMPONENT_TYPE_SUBPROCESS:
        // The sub-agents get added in ::finalizeAddingComponents.
        $id = $component->getPluginId();
        if ($id !== '') {
          $this->subModels[$id] = AiAgent::load($id);
        }
        break;

      case Api::COMPONENT_TYPE_START:
        $config = $component->getConfiguration();
        // Handle the secured system prompt.
        if (!Settings::get('show_secured_ai_agent_system_prompt', FALSE)) {
          $config['secured_system_prompt'] = '[ai_agent:agent_instructions]';
        }
        foreach (AiAgentForm::defaultConfigMetadata('agent_id') as $key => $value) {
          if ($key === 'agent_id') {
            $model->set('id', $config[$key]);
          }
          elseif (is_int($value)) {
            $model->set($key, (int) ($config[$key] ?? $value));
          }
          else {
            $model->set($key, $config[$key] ?? $value);
          }
        }
        break;

      case API::COMPONENT_TYPE_ELEMENT:
        $id = $component->getPluginId();
        $config = $component->getConfiguration();
        $elements = $model->get('tools');
        $elementUsageLimits = $model->get('tool_usage_limits');
        $elementSettings = $model->get('tool_settings');

        $elements[$id] = TRUE;
        $elementSettings[$id] = [
          'progress_message' => $config['progress_message'] ?? "",
          'use_artifacts' => $config['use_artifacts'] ?? FALSE,
          'require_usage' => $config['require_usage'] ?? FALSE,
          'return_directly' => $config['return_directly'] ?? FALSE,
          'description_override' => $config['description_override'] ?? '',
        ];
        $config += $this->ownerComponentDefaultConfig(Api::COMPONENT_TYPE_ELEMENT, $id);
        unset($config['use_artifacts']);
        unset($config['return_directly']);
        unset($config['progress_message']);
        unset($config['require_usage']);
        unset($config['description_override']);
        foreach ($config as $key => $value) {
          // Only process keys in property restriction format.
          if (!str_contains($key, '___')) {
            continue;
          }
          [$plugin, $field] = explode('___', $key);
          $plugin = str_replace('__colon__', ':', $plugin);
          $value = match ($field) {
            'values' => empty($value) ? '' : explode("\n", $value),
            default => $value,
          };
          $elementUsageLimits[$id][$plugin][$field] = $value;
        }

        $model->set('tools', $elements);
        $model->set('tool_usage_limits', $elementUsageLimits);
        $model->set('tool_settings', $elementSettings);
        break;

    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeAddingComponents(ConfigEntityInterface $model): void {
    $elements = $model->get('tools') ?? [];
    foreach ($this->subModels as $subModel) {
      $subModel->save();
      $elements['ai_agents::ai_agent::' . $subModel->id()] = TRUE;
    }
    $model->set('tools', $elements);
  }

  /**
   * {@inheritdoc}
   */
  public function updateComponent(ConfigEntityInterface $model, Component $component): bool {
    return $this->addComponent($model, $component);
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponentsInfo(ConfigEntityInterface $model): array {
    assert($model instanceof AiAgent);
    return [];
  }

  /**
   * Provides a list of all available function call plugins.
   *
   * @return array
   *   The list of all available function call plugins.
   */
  public function createAllInstances(): array {
    // @todo This should go into the FunctionCallPluginManager.
    static $instances;
    if (!isset($instances)) {
      $instances = [];
      foreach ($this->functionCallPluginManager()->getDefinitions() as $definition) {
        try {
          $instances[$definition['id']] = $this->functionCallPluginManager()
            ->createInstance($definition['id']);
        }
        catch (PluginException) {
          // Deliberately ignored.
        }
      }
    }
    return $instances;
  }

  /**
   * Provides a list of all available agents.
   *
   * @return array
   *   The list of all available agents.
   */
  public function getAllAgents(): array {
    static $agents;
    if (!isset($agents)) {
      $agents = [];
      foreach (AiAgent::loadMultiple() as $agent) {
        $agents[] = new ComponentWrapperPlugin(Api::COMPONENT_TYPE_SUBPROCESS, $agent->id(), [], $agent->label());
      }
    }
    return $agents;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultStorageMethod(): string {
    return ModelerApiSettings::STORAGE_OPTION_NONE;
  }

  /**
   * {@inheritdoc}
   */
  public function enforceDefaultStorageMethod(): bool {
    return TRUE;
  }

}
