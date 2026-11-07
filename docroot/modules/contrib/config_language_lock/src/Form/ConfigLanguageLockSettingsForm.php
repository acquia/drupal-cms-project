<?php

namespace Drupal\config_language_lock\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\config_language_lock\CanvasIntegrationChecker;
use Drupal\config_language_lock\ConfigLanguageLockBatch;
use Drupal\config_language_lock\ConfigLanguageLockConfigManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for config language lock.
 */
class ConfigLanguageLockSettingsForm extends ConfigFormBase {

  /**
   * Constructs a settings form.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected LanguageManagerInterface $languageManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigLanguageLockBatch $configLanguageLockBatch,
    protected ConfigLanguageLockConfigManager $configManager,
    protected CanvasIntegrationChecker $canvasChecker,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get(ConfigLanguageLockBatch::class),
      $container->get(ConfigLanguageLockConfigManager::class),
      $container->get(CanvasIntegrationChecker::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'config_language_lock_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['config_language_lock.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $languages = $this->languageManager->getLanguages();
    $counts = $this->configManager->countConfigItemsByLangcode();

    $row_labels = [];
    foreach ($counts as $langcode => $count) {
      $label = isset($languages[$langcode]) ? $languages[$langcode]->getName() : $this->t('Unknown language (@langcode)', ['@langcode' => $langcode]);
      $row_labels[$langcode] = (string) $label;
    }
    natcasesort($row_labels);

    $form['config_language_counts'] = [
      '#type' => 'details',
      '#title' => $this->t('Current configuration language distribution'),
      '#open' => TRUE,
    ];
    $form['config_language_counts']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Language'),
        $this->t('Configuration items'),
      ],
    ];
    foreach (array_keys($row_labels) as $langcode) {
      $form['config_language_counts']['table'][$langcode]['language'] = [
        '#markup' => $row_labels[$langcode],
      ];
      $form['config_language_counts']['table'][$langcode]['count'] = [
        '#markup' => (string) ($counts[$langcode] ?? 0),
      ];
    }

    $form['config_language_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Configure and reset configuration language'),
      '#open' => TRUE,
    ];

    $default_language = $this->languageManager->getDefaultLanguage();
    $form['config_language_settings']['follow_site_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t(
        'Set to site default language (@name) now, and automatically update when the site default changes in the future',
        ['@name' => $default_language->getName()],
      ),
      '#config_target' => 'config_language_lock.settings:follow_site_default',
    ];

    // A plain select is used rather than the 'language_select' element because
    // that element only becomes a real selector when the language module is
    // installed. Without it, core's LanguageSelect silently returns the
    // "not specified" langcode instead of rendering a widget.
    $language_options = [];
    foreach ($languages as $langcode => $language) {
      $language_options[$langcode] = $language->getName();
    }

    $form['config_language_settings']['locked_langcode'] = [
      '#type' => 'select',
      '#options' => $language_options,
      '#title' => $this->t('Configuration language'),
      '#description' => $this->t('Unless no locking is configured, all existing site configuration with language attached will be updated to the selected language. Future configuration will also be created in this language.'),
      '#required' => FALSE,
      '#empty_option' => $this->t('- Do not lock configuration language -'),
      '#config_target' => new ConfigTarget(
        'config_language_lock.settings',
        'locked_langcode',
        fromConfig: [self::class, 'lockedLangcodeFromConfig'],
        toConfig: [self::class, 'lockedLangcodeToConfig'],
      ),
      '#states' => [
        'disabled' => [
          ':input[name="follow_site_default"]' => ['checked' => TRUE],
        ],
      ],
    ];

    if ($this->canvasChecker->isCanvasInstalled()) {
      $settings = $this->config('config_language_lock.settings');
      $is_already_locked = $settings->get('follow_site_default') && $settings->get('locked_langcode') === $default_language->getId();
      if ($is_already_locked) {
        $canvas_message = $this->t('Configuration language is locked to the site default language because Drupal Canvas is installed. Drupal Canvas requires all configuration and Canvas pages to be in the site default language.');
      }
      else {
        $canvas_message = $this->t('Configuration language should be locked to the site default language because Drupal Canvas is installed. Drupal Canvas requires all configuration and Canvas pages to be in the site default language. Save this form to apply the correct setting.');
      }
      $form['config_language_settings']['canvas_notice'] = [
        '#type' => 'markup',
        '#markup' => '<p><strong>' . $canvas_message . '</strong></p>',
        '#weight' => -10,
      ];
      $form['config_language_settings']['follow_site_default']['#default_value'] = TRUE;
      $form['config_language_settings']['follow_site_default']['#required'] = TRUE;
      $form['config_language_settings']['locked_langcode']['#access'] = FALSE;
    }

    $affected_count = $this->configManager->countAffectedConfigItems();
    $acknowledgement = $this->t('I understand that submitting this form may update up to @count configuration items.', ['@count' => $affected_count]);
    if ($this->moduleHandler->moduleExists('locale')) {
      $acknowledgement .= ' ' . $this->t('Configuration translations will also be adapted as needed.');
    }
    $form['config_language_settings']['confirm_lock_change'] = [
      '#type' => 'checkbox',
      '#title' => $acknowledgement,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($this->canvasChecker->isCanvasInstalled()) {
      $form_state->setValue('follow_site_default', TRUE);
    }
    if ($form_state->getValue('follow_site_default')) {
      // The disabled selector submits no value. Set locked_langcode to the
      // current site default before parent validation runs so ConfigFormBase
      // writes a valid langcode rather than NULL or empty string.
      $default = $this->languageManager->getDefaultLanguage()->getId();
      $form_state->setValue('locked_langcode', $default);
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    // Batch completion provides the user-facing confirmation message.
    $this->messenger()->deleteByType(MessengerInterface::TYPE_STATUS);

    // Ensure subsequent reads in this request see the updated setting.
    $this->configFactory->reset('config_language_lock.settings');

    // Do not queue configuration rewrite work when locking is disabled.
    if ($this->configManager->getLockedLangcode() === NULL) {
      return;
    }

    if ($form_state->getValue('follow_site_default')) {
      $this->messenger()->addStatus($this->t(
        'Configuration language will now automatically follow the site default language.',
      ));
    }

    if ($batch = $this->configLanguageLockBatch->buildBatch()) {
      batch_set($batch);
    }
  }

  /**
   * Converts stored config value to form widget value.
   */
  public static function lockedLangcodeFromConfig(mixed $value): string {
    return is_string($value) ? $value : '';
  }

  /**
   * Converts form widget value to storable config value.
   */
  public static function lockedLangcodeToConfig(mixed $value): ?string {
    return $value === '' ? NULL : (string) $value;
  }

}
