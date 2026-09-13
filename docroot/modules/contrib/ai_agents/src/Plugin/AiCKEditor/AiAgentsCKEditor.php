<?php

namespace Drupal\ai_agents\Plugin\AICKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin to do AI completion.
 */
#[AiCKEditor(
  id: 'ai_agents_ckeditor',
  label: new TranslatableMarkup('AI Agents CKEditor'),
  description: new TranslatableMarkup('Let AI Agents output the content for you.'),
  module_dependencies: [],
)]
final class AiAgentsCKEditor extends AiCKEditorPluginBase {

  /**
   * The agent plugin manager.
   *
   * @var \Drupal\ai_agents\PluginManager\AiAgentManager
   */
  protected AiAgentManager $agentManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AiProviderPluginManager $ai_provider_manager,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $account,
    RequestStack $requestStack,
    LoggerChannelFactoryInterface $logger_factory,
    AiAgentManager $agent_manager,
    ConfigFactoryInterface $config_factory,
    EntityFieldManagerInterface $field_manager,
    FileUrlGeneratorInterface $file_url_generator,
    EntityFormBuilderInterface $entity_form_builder,
    LanguageManagerInterface $language_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $ai_provider_manager, $entity_type_manager, $account, $requestStack, $logger_factory, $language_manager);
    $this->agentManager = $agent_manager;
    $this->configFactory = $config_factory;
    $this->fieldManager = $field_manager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->entityFormBuilder = $entity_form_builder;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('plugin.manager.ai_agents'),
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('file_url_generator'),
      $container->get('entity.form_builder'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'agents' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Only get agent ckeditor output.
    foreach ($this->agentManager->getAgentsByTool('ai_agent:agent_ckeditor_output') as $agent_id => $definition) {
      $form[$agent_id . '_advanced'] = [
        '#type' => 'details',
        '#title' => $this->t('%label Settings', [
          '%label' => $definition['label'],
        ]),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="' . $agent_id . '"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form[$agent_id . '_advanced'][$agent_id] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable %workflow', [
          '%workflow' => $definition['label'],
        ]),
        '#default_value' => $this->configuration['agents'][$agent_id]['enabled'] ?? FALSE,
      ];

      $form[$agent_id . '_advanced']['prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Prompt'),
        '#description' => $this->t('If you use selection this prompt will be used before the selection. Can be left empty if no selection is required.'),
        '#default_value' => $this->configuration['agents'][$agent_id]['prompt'] ?? [],
      ];

      $form[$agent_id . '_advanced']['require_selection'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Require Selection'),
        '#description' => $this->t('If the user has to select text in the parent editor to use this workflow.'),
        '#default_value' => $this->configuration['agents'][$agent_id]['require_selection'] ?? FALSE,
      ];

      $form[$agent_id . '_advanced']['prompt_description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Prompt Description'),
        '#description' => $this->t('This description will be used to explain the prompt to the user.'),
        '#default_value' => $this->configuration['agents'][$agent_id]['prompt_description'] ?? '',
        '#rows' => 3,
      ];

      $form[$agent_id . '_advanced']['write_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Write Mode'),
        '#description' => $this->t('Select the write mode for this workflow.'),
        '#options' => [
          'append' => $this->t('Append'),
          'prepend' => $this->t('Prepend'),
          'replace' => $this->t('Replace'),
        ],
        '#default_value' => $this->configuration['agents'][$agent_id]['write_mode'] ?? 'replace',
      ];

      $form[$agent_id . '_advanced']['submit_text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Submit Text'),
        '#description' => $this->t('The text that will be used to submit the workflow.'),
        '#default_value' => $this->configuration['agents'][$agent_id]['submit_text'] ?? $this->t('Run Agent'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->agentManager->getAgentsByTool('ai_agent:agent_ckeditor_output') as $agent_id => $definition) {
      $this->configuration['agents'][$agent_id]['enabled'] = $form_state->getValue($agent_id . '_advanced')[$agent_id];
      $this->configuration['agents'][$agent_id]['prompt'] = $form_state->getValue($agent_id . '_advanced')['prompt'];
      $this->configuration['agents'][$agent_id]['require_selection'] = $form_state->getValue($agent_id . '_advanced')['require_selection'];
      $this->configuration['agents'][$agent_id]['write_mode'] = $form_state->getValue($agent_id . '_advanced')['write_mode'];
      $this->configuration['agents'][$agent_id]['submit_text'] = $form_state->getValue($agent_id . '_advanced')['submit_text'];
      $this->configuration['agents'][$agent_id]['prompt_description'] = $form_state->getValue($agent_id . '_advanced')['prompt_description'] ?? '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    $storage = $form_state->getStorage();
    $form = parent::buildCkEditorModalForm($form, $form_state);

    // Something is wrong with the settings if we don't get the ids.
    if (!isset($settings['config_id']) || !isset($settings['editor_id']) || !isset($settings['plugin_id'])) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    // Check that the settings exists.
    $editor_config = $this->configFactory->get('editor.editor.' . $settings['editor_id']);
    if (empty($editor_config->get('settings'))) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    // Since this is custom form outside the CKEditor5 context, we have to
    // check that the user has permissions to this specific text format.
    if (!$this->account->hasPermission('use text format ' . $editor_config->get('format'))) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    $instance_config = $editor_config->get('settings')['plugins']['ai_ckeditor_ai'] ?? [];

    // Get the configuration.
    $plugin_config = $instance_config['plugins'][$settings['plugin_id']]['agents'][$settings['config_id']];

    // If selection is required, make sure that there is a selection.
    if ($plugin_config['require_selection'] && empty($storage['selected_text'])) {
      return [
        '#markup' => '<p>' . $this->t('Please select text in the editor before using this assistant.') . '</p>',
      ];
    }

    // If the pre-prompt is empty and the require selection is not set.
    if (empty($plugin_config['prompt']) && !$plugin_config['require_selection']) {
      // We have a prompt field.
      $form['prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Prompt'),
        '#description' => $plugin_config['prompt_description'] ?? $this->t('This prompt will be used before the selected text.'),
        '#default_value' => $plugin_config['prompt'] ?? '',
      ];
    }

    $form['agent_id'] = [
      '#type' => 'value',
      '#value' => $settings['config_id'],
    ];
    $form['config_id'] = [
      '#type' => 'hidden',
      '#value' => $settings['config_id'],
    ];
    $form['selected_text'] = [
      '#type' => 'hidden',
      '#value' => $storage['selected_text'],
    ];
    $form['agent_write_mode'] = [
      '#type' => 'value',
      '#value' => $plugin_config['write_mode'],
    ];
    $form['pre_prompt'] = [
      '#type' => 'value',
      '#default_value' => $plugin_config['prompt'] ?? '',
    ];

    $form['#attached']['library'][] = 'ai_agents/agents_ckeditor';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxGenerate(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $configuration = $form_state->getValue('plugin_config');
    // Check if an prompt was provided.
    $prompt = $configuration['prompt'] ?? '';
    if (empty($prompt)) {
      // Then we combine the selected text with the pre-prompt.
      $prompt = $configuration['pre_prompt'] . "\n" . $configuration['selected_text'];
    }

    // Load the agent.
    $agent_id = $configuration['agent_id'] ?? '';
    $agent = $this->agentManager->createInstance($agent_id);
    if (!$agent) {
      throw new \InvalidArgumentException('The agent with ID ' . $agent_id . ' does not exist.');
    }

    $agent->setChatInput(new ChatInput([
      new ChatMessage('user', $prompt),
    ]));

    $agent->determineSolvability();

    $response->addCommand(new InvokeCommand(
      '#ai-ckeditor-response',
      'agentUpdateCkEditor',
      [$agent->solve()],
    ));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function availableEditors() {
    $editors = [];
    foreach ($this->configuration['agents'] as $agent_id => $data) {
      if (!empty($data['enabled'])) {
        // Load the agent.
        $agent = $this->entityTypeManager->getStorage('ai_agent')->load($agent_id);
        if (!$agent) {
          continue;
        }
        $id = $this->getPluginId() . '__' . $agent_id;
        $editors[$id] = $agent->label();
      }
    }
    return $editors;
  }

  /**
   * {@inheritdoc}
   */
  protected function needsSelectedText() {
    return FALSE;
  }

}
