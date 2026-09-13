<?php

namespace Drupal\ai_seo\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * AI SEO configuration form.
 */
class ConfigurationForm extends ConfigFormBase {

  /**
   * AI analyzer.
   *
   * @var \Drupal\ai_seo\AiSeoAnalyzer
   */
  protected $analyzer;

  /**
   * The provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->analyzer = $container->get('ai_seo.service');
    $instance->providerManager = $container->get('ai.provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ai_seo.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_seo_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $preferences_token = NULL) {
    // Create the form.
    $config = $this->config('ai_seo.settings');
    // Get models from AI provider.
    $chat_models = $this->providerManager->getSimpleProviderModelOptions('chat');
    $default_chat_model = $this->providerManager->getSimpleDefaultProviderOptions('chat');
    array_shift($chat_models);
    array_splice($chat_models, 0, 1);
    $form['provider_and_model'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
      '#default_value' => $config->get('provider_and_model') ?? $default_chat_model,
      '#title' => $this->t('Choose Provider and Model used for SEO/GEO analyzing.'),
    ];

    $form['enable_field_buttons'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable inline field analysis buttons'),
      '#description' => $this->t('Shows a small "SEO/GEO ✦" button next to text field labels on node edit forms, allowing editors to run a focused AI analysis on an individual field without leaving the page.'),
      '#default_value' => (bool) ($config->get('enable_field_buttons') ?? FALSE),
    ];

    $form['prompt'] = [
      '#type' => 'details',
      '#title' => $this->t('Prompts'),
      '#open' => TRUE,
    ];

    $form['prompt']['system'] = [
      '#type' => 'details',
      '#title' => $this->t('System prompt'),
      '#open' => FALSE,
    ];

    $form['prompt']['system']['default_system_prompt'] = [
      '#type' => 'textarea',
      '#readonly' => TRUE,
      '#disabled' => TRUE,
      '#title' => $this->t('Default prompt'),
      '#description' => $this->t('The default system prompt comes with the module and it is the one that is used unless a custom prompt is provided below.'),
      '#value' => $this->analyzer->getDefaultSystemPrompt(),
    ];

    $form['prompt']['system']['custom_system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System prompt'),
      '#default_value' => $config->get('custom_system_prompt') ?? '',
    ];

    $form['prompt']['default_prompt'] = [
      '#type' => 'textarea',
      '#readonly' => TRUE,
      '#disabled' => TRUE,
      '#title' => $this->t('Default prompt'),
      '#description' => $this->t('The default prompt comes with the module and it is the one that is used unless a custom prompt is provided below.'),
      '#value' => $this->analyzer->getDefaultPrompt(),
    ];

    $form['prompt']['custom_prompt'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>There are configurable report types available when analyzing content. Each report type has its own prompt that can be modified by editing the report type entities. The default report type is "Full".</p><p>If you want to customize the prompt for a specific report type, please go to the <a href=":link">Report Types</a> administration page and edit the desired report type.</p>', [
        ':link' => Url::fromRoute('entity.ai_seo_report_type.collection')->toString(),
      ]),
    ];

    // Commented out since there are configurable report types now.
    // $form['prompt']['custom_prompt'] = [
    //   '#type' => 'textarea',
    //   '#title' => $this->t('Custom prompt'),
    //   '#default_value' => $config->get('custom_prompt') ?? '',
    // ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, ?string $preferences_token = NULL) {
    $config = $this->config('ai_seo.settings');
    $custom_system_prompt = $form_state->getValue('custom_system_prompt') ?? '';
    $custom_prompt = $form_state->getValue('custom_prompt') ?? '';
    $config
      ->set('custom_system_prompt', trim($custom_system_prompt))
      ->set('custom_prompt', trim($custom_prompt))
      ->set('provider_and_model', $form_state->getValue('provider_and_model'))
      ->set('enable_field_buttons', (bool) $form_state->getValue('enable_field_buttons'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Page title.
   */
  public function getTitle() {
    return $this->t('Administer AI SEO/GEO analyzer settings');
  }

}
