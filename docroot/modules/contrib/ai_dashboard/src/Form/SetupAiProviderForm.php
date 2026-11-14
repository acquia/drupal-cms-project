<?php

namespace Drupal\ai_dashboard\Form;

use Drupal\ai_dashboard\AiSetupStatusInterface;
use Drupal\Component\Plugin\Exception\PluginException;
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
   * Static cache of whether providers require an API key, keyed by provider.
   *
   * @var bool[]
   */
  protected array $requiresApiKey = [];

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
    $key_required_providers = [];
    foreach ($providers as $provider) {
      $options[$provider['id']] = $provider['label'];
      $key_is_set = $this->aiSetupStatus->getProviderSetupStatus($provider['id']);
      if ($key_is_set == 'yes') {
        $already_setup_providers[] = $provider['id'];
      }
      if ($this->providerRequiresApiKey($provider['id'])) {
        $key_required_providers[] = $provider['id'];
      }
    }
    $form['add_provider'] = [
      '#type' => 'details',
      '#title' => $this->t('Add Provider'),
      '#open' => TRUE,
      '#description' => $this->t('Select a provider and, if required, enter its API key to add it to your site. Some providers, such as local providers, do not require an API key. To learn more check the <a href="@url" target="_blank">documentation</a>.', ['@url' => 'https://project.pages.drupalcode.org/ai/1.2.x/']),
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
      '#description' => $this->t('The API Key you need to create on AI Provider side. Not required for providers that do not need one, such as local providers.'),
      '#placeholder' => $this->t('API Key'),
      '#maxlength' => 255,
    ];
    if (!empty($key_required_providers)) {
      $key_required_states = [];
      foreach ($key_required_providers as $provider) {
        $key_required_states[] = [
          'select[name="provider"]' => ['value' => $provider],
        ];
      }
      // Only require the API key on the client side for providers that
      // actually need one, so local providers (e.g. Ollama) can be added
      // without it.
      $form['add_provider']['api_key']['#states']['required'] = $key_required_states;
    }
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
      $form['add_provider']['api_key']['#states']['disabled'] = $states_condition;
      $form['add_provider']['provider_wrapper']['warning'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['ai-provider-setup-warning', 'ai-font-size-xs', 'ai-icon--warning'],
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
   * Checks whether a provider requires an API key to be set up.
   *
   * A provider is considered to need a key when its setup data declares a
   * 'key_config_name'. Providers without it (for example local providers such
   * as Ollama) can be set up without a key. If the provider can't be loaded a
   * key is assumed to be required, so key creation is never silently skipped.
   *
   * @param string $provider_name
   *   The AI provider plugin ID.
   *
   * @return bool
   *   TRUE if the provider needs an API key, FALSE otherwise.
   */
  public function providerRequiresApiKey(string $provider_name): bool {
    if (!isset($this->requiresApiKey[$provider_name])) {
      try {
        $setup_data = $this->aiProviderManager->createInstance($provider_name)->getSetupData();
        $this->requiresApiKey[$provider_name] = !empty($setup_data['key_config_name']);
      }
      catch (PluginException) {
        return TRUE;
      }
    }
    return $this->requiresApiKey[$provider_name];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $provider = $form_state->getValue('provider');
    if (!$provider) {
      return;
    }
    // The API key field is only marked required client-side (via #states)
    // for providers that need one, so it has to be validated here too.
    if ($this->providerRequiresApiKey($provider) && trim((string) $form_state->getValue('api_key')) === '') {
      $form_state->setErrorByName('api_key', $this->t('The API Key field is required for the selected provider.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $provider = $form_state->getValue('provider');
    /** @var \Drupal\ai\AiProviderInterface $provider_definition */
    $provider_definition = $this->aiProviderManager->getDefinition($provider);
    $requires_api_key = $this->providerRequiresApiKey($provider);
    if (!$requires_api_key && trim((string) $form_state->getValue('api_key')) !== '') {
      $this->messenger()->addWarning($this->t('The provider "%provider" does not use an API key, so the key you entered was not saved.', ['%provider' => $provider_definition['label']]));
    }
    $config_action = $this->configActionManager->createInstance('setupAiProvider');
    $config_name = $provider_definition['provider'] . '.settings';
    $config_action->apply($config_name, [
      'provider' => $provider,
      'key_name' => $provider,
      'key_label' => (string) $provider_definition['label'],
      'key_value' => $requires_api_key ? $form_state->getValue('api_key') : '',
      'no_key_needed' => !$requires_api_key,
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
