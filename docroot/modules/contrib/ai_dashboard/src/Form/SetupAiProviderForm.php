<?php

namespace Drupal\ai_dashboard\Form;

use Drupal\ai_dashboard\AiSetupStatusInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains form to set up AI Provider.
 */
class SetupAiProviderForm extends FormBase {

  /**
   * The AI providers manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected PluginManagerInterface $aiProviderManager;

  /**
   * The config action manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected PluginManagerInterface $configActionManager;

  /**
   * The AI Setup Status service.
   *
   * @var \Drupal\ai_dashboard\AiSetupStatusInterface
   */
  protected AiSetupStatusInterface $aiSetupStatus;

  /**
   * Constructs form instance.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $ai_provider_manager
   *   The AI provider manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $config_action_manager
   *   The config action manager.
   * @param \Drupal\ai_dashboard\AiSetupStatusInterface $ai_setup_status
   *   The AI setup status.
   */
  public function __construct(PluginManagerInterface $ai_provider_manager, PluginManagerInterface $config_action_manager, AiSetupStatusInterface $ai_setup_status) {
    $this->aiProviderManager = $ai_provider_manager;
    $this->configActionManager = $config_action_manager;
    $this->aiSetupStatus = $ai_setup_status;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('plugin.manager.config_action'),
      $container->get('ai_dashboard.setup_status')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_setup_ai_provider';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $providers = $this->aiProviderManager->getDefinitions();
    $options = [];
    $already_setup_providers = [];
    foreach ($providers as $provider) {
      $options[$provider['id']] = $provider['label'];
      $key_is_set = $this->aiSetupStatus->getProviderSetupStatus($provider['id']);
      if ($key_is_set == 'yes') {
        $already_setup_providers[] = $provider['id'];
      }
    }
    $form['add_provider'] = [
      '#type' => 'details',
      '#title' => $this->t('Add Provider'),
      '#open' => TRUE,
      '#description' => $this->t('Select a provider and enter its API key to add it to your site. To learn more check the <a href="@url" target="_blank">documentation</a>.', ['@url' => 'https://project.pages.drupalcode.org/ai/1.2.x/']),
    ];
    $form['add_provider']['provider_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-item--provider'],
      ],
    ];
    $form['add_provider']['provider_wrapper']['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Provider'),
      '#title_display' => 'invisible',
      '#description' => $this->t('Select a provider. If you do not see the desired provider, check "Extensions" section of this page. In "AI Providers" block you may find more providers.'),
      '#empty_option' => $this->t('Provider'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['add_provider']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#title_display' => 'invisible',
      '#description' => $this->t('The API Key you need to create on AI Provider side.'),
      '#placeholder' => $this->t('API Key'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['add_provider']['actions'] = [
      '#type' => 'actions',
    ];
    $form['add_provider']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
    ];
    if (!empty($already_setup_providers)) {
      $states_condition = [];
      foreach ($already_setup_providers as $provider) {
        $states_condition[] = [
          'select[name="provider"]' => ['value' => $provider],
        ];
      }
      $form['add_provider']['api_key']['#states'] = [
        'disabled' => $states_condition,
      ];
      $form['add_provider']['provider_wrapper']['warning'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['ai-provider-setup-warning'],
        ],
        '#states' => [
          'visible' => $states_condition,
        ],
      ];
      $form['add_provider']['provider_wrapper']['warning']['text'] = [
        '#markup' => $this->t('Selected provider is already configured. Change the configuration <a target="_blank" href="@url">here</a>.', ['@url' => Url::fromRoute('ai.admin_providers')->toString()]),
      ];
    }
    $form['#theme'] = 'system_config_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $provider = $form_state->getValue('provider');
    /** @var \Drupal\ai\AiProviderInterface $provider_definition */
    $provider_definition = $this->aiProviderManager->getDefinition($provider);
    $api_key = $form_state->getValue('api_key');
    $config_action = $this->configActionManager->createInstance('setupAiProvider');
    $config_name = $provider_definition['provider'] . '.settings';
    $config_action->apply($config_name, [
      'provider' => $provider,
      'key_name' => $provider,
      'key_label' => (string) $provider_definition['label'],
      'key_value' => $api_key,
    ]);
    if ($provider == 'openai') {
      $config_action = $this->configActionManager->createInstance('simpleConfigUpdate');
      $config_action->apply($config_name, [
        'openai_moderation' => TRUE,
      ]);
    }
    $this->messenger()->addMessage($this->t('Provider "%provider" was set up successfully.', ['%provider' => $provider_definition['label']]));
  }

}
