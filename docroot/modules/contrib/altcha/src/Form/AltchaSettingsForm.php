<?php

namespace Drupal\altcha\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\altcha\SecretManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure ALTCHA settings for this site.
 */
class AltchaSettingsForm extends ConfigFormBase {

  /**
   * The ALTCHA SaaS API challenge path.
   */
  const SAAS_API_CHALLENGE_PATH = '/api/v1/challenge';

  /**
   * The ALTCHA sentinel API path.
   */
  const SENTINEL_API_CHALLENGE_PATH = '/v1/challenge';

  /**
   * Constructs an ALTCHA settings form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\altcha\SecretManager $secretManager
   *   The ALTCHA secret manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file url generator service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected SecretManager $secretManager,
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('altcha.secret_manager'),
      $container->get('stream_wrapper_manager'),
      $container->get('file_url_generator'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'altcha_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['altcha.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['integration_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Integration type'),
      '#options' => [
        'self_hosted' => $this->t('Self-hosted'),
        'sentinel_api' => $this->t('Sentinel (API key needed)'),
        'saas_api' => $this->t('SaaS (API key needed - deprecated)'),
      ],
      '#config_target' => 'altcha.settings:integration_type',
    ];

    $form['self_hosted'] = [
      '#type' => 'details',
      '#title' => $this->t('Self-hosted settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name=integration_type]' => ['value' => 'self_hosted'],
        ],
      ],
    ];

    $form['self_hosted']['secret_key_status'] = [
      '#type' => 'item',
      '#markup' => $this->getSecretKeyStatusMessage(),
      '#prefix' => '<div id="altcha-secret-key-status">',
      '#suffix' => '</div>',
    ];

    $form['self_hosted']['secret_key_regenerate'] = [
      '#type' => 'button',
      '#value' => $this->t('Regenerate secret key'),
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::regenerateSecretKey',
        'wrapper' => 'altcha-secret-key-status',
        'event' => 'click',
      ],
    ];

    $form['saas_api'] = [
      '#type' => 'details',
      '#title' => $this->t('SaaS API settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name=integration_type]' => ['value' => 'saas_api'],
        ],
      ],
    ];

    $form['saas_api']['saas_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('Enter an API key which has the "AntiSpam API" feature enabled in the ALTCHA UI. More info on API keys can be found <a href="https://altcha.org/docs/api/api_keys" target="_blank">here</a>.'),
      '#placeholder' => 'key_*********************',
      '#states' => [
        'required' => [
          ':input[name=integration_type]' => ['value' => 'saas_api'],
        ],
      ],
      '#config_target' => 'altcha.settings:saas_api_key',
    ];

    $form['saas_api']['saas_api_region'] = [
      '#type' => 'select',
      '#title' => $this->t('API Region'),
      '#description' => $this->t('Choose the ALTCHA API region. More info can be found <a href="https://altcha.org/docs/api/regions" target="_blank">here</a>.'),
      '#options' => static::getRegionUrlMap(),
      '#states' => [
        'required' => [
          ':input[name=integration_type]' => ['value' => 'saas_api'],
        ],
      ],
      '#config_target' => 'altcha.settings:saas_api_region',
    ];

    $form['sentinel'] = [
      '#type' => 'details',
      '#title' => $this->t('Sentinel settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name=integration_type]' => ['value' => 'sentinel_api'],
        ],
      ],
    ];

    $form['sentinel']['sentinel_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sentinel base URL'),
      '#description' => $this->t('Configure the Sentinel base url.'),
      '#placeholder' => 'https://sentinel.example.com',
      '#states' => [
        'required' => [
          ':input[name=integration_type]' => ['value' => 'sentinel_api'],
        ],
      ],
      '#config_target' => 'altcha.settings:sentinel_api_url',
    ];

    $form['sentinel']['sentinel_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('Configure your API key.'),
      '#placeholder' => 'key_...',
      '#states' => [
        'required' => [
          ':input[name=integration_type]' => ['value' => 'sentinel_api'],
        ],
      ],
      '#config_target' => 'altcha.settings:sentinel_api_key',
    ];

    $form['sentinel']['sentinel_api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API secret'),
      '#description' => $this->t('Configure your API secret.'),
      '#placeholder' => 'sec_...',
      '#states' => [
        'required' => [
          ':input[name=integration_type]' => ['value' => 'sentinel_api'],
        ],
      ],
      '#config_target' => 'altcha.settings:sentinel_api_secret',
    ];

    $form['sentinel']['sentinel_fallback_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable fallback to self-hosted'),
      '#description' => $this->t('Enables fallback to ALTCHA self-hosted in case ALTCHA Sentinel is unavailable.'),
      '#config_target' => 'altcha.settings:sentinel_fallback_enabled',
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => TRUE,
    ];

    $form['advanced']['auto_verification'] = [
      '#type' => 'select',
      '#title' => $this->t('Auto verification'),
      '#description' => $this->t('Automatically verify without user interaction.'),
      '#options' => [
        'off' => $this->t('Off'),
        'onload' => $this->t('On page load'),
        'onfocus' => $this->t('On form focus'),
        'onsubmit' => $this->t('On form submit'),
      ],
      '#config_target' => 'altcha.settings:auto_verification',
    ];

    $form['advanced']['max_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Complexity'),
      '#description' => $this->t('Tweak the complexity to make the challenge easier or harder to solve. View the <a href="https://altcha.org/docs/complexity" target="_blank">ALTCHA docs</a>  for more information.'),
      '#min' => 1000,
      '#max' => 1000000,
      '#placeholder' => 20000,
      '#config_target' => 'altcha.settings:max_number',
      '#states' => [
        'disabled' => [
          ':input[name=integration_type]' => ['value' => 'sentinel'],
        ],
      ],
    ];

    $form['advanced']['expire'] = [
      '#type' => 'number',
      '#title' => $this->t('Expire'),
      '#description' => $this->t('Challenge expiration duration in milliseconds.'),
      '#placeholder' => 0,
      '#config_target' => 'altcha.settings:expire',
      '#states' => [
        'disabled' => [
          ':input[name=integration_type]' => ['value' => 'sentinel'],
        ],
      ],
    ];

    $form['advanced']['delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Delay'),
      '#description' => $this->t('Artificial delay in milliseconds before verification.'),
      '#placeholder' => 0,
      '#config_target' => 'altcha.settings:delay',
    ];

    // phpcs:disable Drupal.Strings.UnnecessaryStringConcat.Found
    $form['advanced']['library_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override ALTCHA JS library'),
      '#description' => $this->t('Override the ALTCHA JavaScript library with a CDN link or custom JavaScript file.')
      . '<ul>'
      . '<li>' . $this->t('CDN example: https://cdn.jsdelivr.net/gh/altcha-org/altcha@2.1/dist/altcha.min.js') . '</li>'
      . '<li>' . $this->t('Path example: libraries/js/altcha.min.js')
      . '<br><small>' . $this->t('The path may be absolute (e.g., %abs), relative to the drupal web root (e.g., %rel), or defined using a stream wrapper (e.g., %str).', [
        '%abs' => '/var/www/html/web/libraries/js/altcha.min.js',
        '%rel' => 'libraries/js/altcha.min.js',
        '%str' => 'public://libraries/js/altcha.min.js',
      ]) . '</small></li>'
      . '</ul>'
      . $this->t('Leave empty to use the default library included with the module.'),
      '#element_validate' => ['::validateOverrideAltchaLibrary'],
      '#config_target' => 'altcha.settings:library_override',
    ];
    // phpcs:enable

    $form['widget'] = [
      '#type' => 'details',
      '#title' => $this->t('Widget settings'),
      '#description' => $this->moduleHandler->moduleExists('altcha_obfuscate') ? $this->t('These settings also apply to the ALTCHA obfuscate widget. Invisible captcha being the exception, this option is automatically enabled.') : NULL,
      '#open' => TRUE,
    ];

    $form['widget']['floating_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable invisible captcha'),
      '#description' => $this->t('Enables ALTCHA floating UI. This feature is currently not supported on AJAX forms.'),
      '#config_target' => 'altcha.settings:floating_enabled',
    ];

    $form['widget']['floating_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Floating mode'),
      '#options' => [
        'auto' => $this->t('Auto'),
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#states' => [
        'invisible' => [
          ':input[name=floating_enabled]' => ['checked' => FALSE],
        ],
      ],
      '#config_target' => 'altcha.settings:floating_mode',
    ];

    $form['widget']['floating_anchor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Floating anchor'),
      '#description' => $this->t('CSS selector of the “anchor” to which the floating UI will be attached.'),
      '#placeholder' => 'input[name="op"]',
      '#states' => [
        'invisible' => [
          ':input[name=floating_enabled]' => ['checked' => FALSE],
        ],
      ],
      '#config_target' => 'altcha.settings:floating_anchor',
    ];

    $form['widget']['floating_offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Floating offset'),
      '#description' => $this->t('Y offset from the anchor element for the floating UI in pixels.'),
      '#placeholder' => 12,
      '#states' => [
        'invisible' => [
          ':input[name=floating_enabled]' => ['checked' => FALSE],
        ],
      ],
      '#config_target' => 'altcha.settings:floating_offset',
    ];

    $form['widget']['hide_logo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide logo'),
      '#description' => $this->t('Hide the ALTCHA logo from display.'),
      '#config_target' => 'altcha.settings:hide_logo',
    ];

    $form['widget']['hide_footer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide footer'),
      '#description' => $this->t('Hide the ALTCHA footer from display.'),
      '#config_target' => 'altcha.settings:hide_footer',
    ];

    $form['i18n'] = [
      '#type' => 'details',
      '#title' => $this->t('Internationalization'),
      '#open' => TRUE,
    ];

    $form['i18n']['i18n_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Internationalization method'),
      '#description' => $this->t('Choose how ALTCHA widget labels are translated. The ALTCHA i18n library includes built-in translations for all supported languages. When using Drupal interface translations, the ALTCHA i18n library is not loaded, which reduces the JavaScript payload. Widget label overrides configured below are applied regardless of the selected method.'),
      '#options' => [
        'altcha' => $this->t('Use ALTCHA built-in translations (loads the i18n library)'),
        'drupal' => $this->t('Use Drupal interface translations only (does not load the i18n library)'),
      ],
      '#config_target' => 'altcha.settings:i18n_method',
    ];

    // phpcs:disable Drupal.Strings.UnnecessaryStringConcat.Found
    $form['i18n']['i18n_library_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override ALTCHA i18n JS library'),
      '#description' => $this->t('Override the ALTCHA i18n JavaScript library with a CDN link or custom JavaScript file.')
      . '<ul>'
      . '<li>' . $this->t('CDN example: https://cdn.jsdelivr.net/gh/altcha-org/altcha@2.1/dist_i18n/all.min.js') . '</li>'
      . '<li>' . $this->t('Path example: libraries/js/altcha-i18n/all.min.js')
      . '<br><small>' . $this->t('The path may be absolute (e.g., %abs), relative to the drupal web root (e.g., %rel), or defined using a stream wrapper (e.g., %str).', [
        '%abs' => '/var/www/html/web/libraries/js/altcha-i18n/all.min.js',
        '%rel' => 'libraries/js/altcha-i18n/all.min.js',
        '%str' => 'public://libraries/js/altcha-i18n/all.min.js',
      ]) . '</small></li>'
      . '</ul>'
      . $this->t('Leave empty to use the default library included with the module.'),
      '#element_validate' => ['::validateOverrideI18nLibrary'],
      '#config_target' => 'altcha.settings:i18n_library_override',
      '#states' => [
        'visible' => [
          ':input[name=i18n_method]' => ['value' => 'altcha'],
        ],
      ],
    ];
    // phpcs:enable

    $form['i18n']['labels'] = [
      '#type' => 'details',
      '#title' => $this->t('Override widget labels'),
      '#description' => $this->t('Override the text displayed in the ALTCHA widget. Leave empty to use the default text. These overrides can be translated using the config_translation module and the Translate ALTCHA tab.'),
      '#open' => TRUE,
    ];

    foreach (static::getLabelMap() as $field_name => $altcha_info) {
      $form['i18n']['labels'][$field_name] = [
        '#type' => 'textfield',
        '#title' => $this->t('Override: @title', ['@title' => $altcha_info['altcha_title']]),
        '#description' => $altcha_info['altcha_description'] ?? '',
        '#placeholder' => $altcha_info['altcha_example'],
        '#config_target' => "altcha.settings:$field_name",
      ];
    }

    if ($this->moduleHandler->moduleExists('altcha_obfuscate')) {
      $form['obfuscate'] = [
        '#type' => 'details',
        '#title' => $this->t('Obfuscate plugin'),
        '#description' => $this->t('These settings apply specifically to the ALTCHA obfuscate widget.'),
        '#open' => TRUE,
      ];

      $form['obfuscate']['obfuscate_reveal_text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reveal text'),
        '#placeholder' => $this->t('Click to reveal'),
        '#config_target' => 'altcha.settings:obfuscate_reveal_text',
      ];

      $form['obfuscate']['obfuscate_max_number'] = [
        '#type' => 'number',
        '#title' => $this->t('Complexity'),
        '#description' => $this->t('Tweak the complexity to make the challenge easier or harder to solve. View the <a href="https://altcha.org/docs/obfuscation/#complexity" target="_blank">ALTCHA docs</a> for more information.'),
        '#min' => 1,
        '#max' => 10000,
        '#placeholder' => 10000,
        '#config_target' => 'altcha.settings:obfuscate_max_number',
      ];

      // phpcs:disable Drupal.Strings.UnnecessaryStringConcat.Found
      $form['obfuscate']['obfuscate_library_override'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Override ALTCHA obfuscate JS library'),
        '#description' => $this->t('Override the ALTCHA obfuscate JavaScript library with a CDN link or custom JavaScript file.')
        . '<ul>'
        . '<li>' . $this->t('CDN example: https://cdn.jsdelivr.net/gh/altcha-org/altcha@1.x/dist_plugins/obfuscation.min.js') . '</li>'
        . '<li>' . $this->t('Path example: libraries/js/obfuscation.min.js')
        . '<br><small>' . $this->t('The path may be absolute (e.g., %abs), relative to the drupal web root (e.g., %rel), or defined using a stream wrapper (e.g., %str).', [
          '%abs' => '/var/www/html/web/libraries/js/obfuscation.min.js',
          '%rel' => 'libraries/js/obfuscation.min.js',
          '%str' => 'public://libraries/js/obfuscation.min.js',
        ]) . '</small></li>'
        . '</ul>'
        . $this->t('Leave empty to use the default library included with the module.'),
        '#element_validate' => ['::validateOverrideObfuscateLibrary'],
        '#config_target' => 'altcha.settings:obfuscate_library_override',
      ];
      // phpcs:enable
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Massage empty integer values to NULL. This is a known bug in drupal core.
   *
   * @link https://www.drupal.org/project/drupal/issues/2925445
   * @link https://www.drupal.org/project/drupal/issues/2220381
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $integer_fields = ['max_number', 'expire', 'delay', 'floating_offset', 'obfuscate_max_number'];
    foreach ($integer_fields as $integer_field) {
      if (empty($form_state->getValue($integer_field))) {
        $form_state->setValue($integer_field, NULL);
      }
    }

    if ($form_state->getValue('integration_type') === 'saas_api') {
      if (empty($form_state->getValue('saas_api_key'))) {
        $form_state->setErrorByName('saas_api_key', 'API Key is required when using ALTCHA SaaS API.');
      }

      if (empty($form_state->getValue('saas_api_region'))) {
        $form_state->setErrorByName('saas_api_region', 'API Region is required when using ALTCHA SaaS API.');
      }
    }

    if ($form_state->getValue('integration_type') === 'sentinel') {
      if (empty($form_state->getValue('sentinel_api_url'))) {
        $form_state->setErrorByName('sentinel_api_url', 'Challenge URL is required when using ALTCHA Sentinel.');
      }
    }

    // Obfuscate specific validation.
    if ($this->moduleHandler->moduleExists('altcha_obfuscate')) {
      if (!empty($form_state->getValue('library_override')) && empty($form_state->getValue('obfuscate_library_override'))) {
        $form_state->setErrorByName('obfuscate_library_override', 'When the main ALTCHA library is overridden, the obfuscate library should be overridden too.');
      }

      if (!empty($form_state->getValue('obfuscate_library_override')) && empty($form_state->getValue('library_override'))) {
        $form_state->setErrorByName('library_override', 'When the obfuscate library is overridden, the main ALTCHA library should be overridden too.');
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * Callback to validate the overridden ALTCHA JS library.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateOverrideAltchaLibrary(array &$form, FormStateInterface $form_state): void {
    $this->validateOverrideLibrary($form, $form_state, 'library_override');
  }

  /**
   * Callback to validate the overridden ALTCHA i18n library.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateOverrideI18nLibrary(array &$form, FormStateInterface $form_state): void {
    $this->validateOverrideLibrary($form, $form_state, 'i18n_library_override');
  }

  /**
   * Callback to validate the overridden ALTCHA obfuscate JS library.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateOverrideObfuscateLibrary(array &$form, FormStateInterface $form_state): void {
    $this->validateOverrideLibrary($form, $form_state, 'obfuscate_library_override');
  }

  /**
   * Validate an overridden ALTCHA JS library.
   *
   * Either a CDN link or a path to a local JS file can be provided. Therefore,
   * check for either a URL or an existing file.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $field_name
   *   The name of the field to validate.
   */
  public function validateOverrideLibrary(array &$form, FormStateInterface $form_state, $field_name): void {
    $value = $form_state->getValue($field_name);
    if (empty($value)) {
      return;
    }

    // File uri: transform to relative file path before validation.
    if ($this->streamWrapperManager->isValidUri($value)) {
      $value = trim($this->fileUrlGenerator->generateString($value), '/');
    }

    // Absolute URL: validate the external url.
    if (filter_var($value, FILTER_VALIDATE_URL)) {
      // Ensure it points to a JavaScript file.
      if (!preg_match('/\.js$/', $value)) {
        $form_state->setErrorByName($field_name, $this->t('The provided URL must point to a JavaScript file.'));
      }
      return;
    }

    // File path: does the file actually exist on the path?
    if (!is_file($value)) {
      $form_state->setErrorByName($field_name, $this->t('There is no file at the specified location.'));
    }

    // File path: is the file readable?
    if ((!is_readable($value))) {
      $form_state->setErrorByName($field_name, $this->t('The file at the specified location is not readable.'));
    }
  }

  /**
   * Callback to regenerate a secret key.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The updated secret key status form element.
   */
  public function regenerateSecretKey(array &$form, FormStateInterface $form_state): array {
    $this->secretManager->generateSecretKey();
    $form['self_hosted']['secret_key_status']['#markup'] = $this->getSecretKeyStatusMessage(TRUE);
    return $form['self_hosted']['secret_key_status'];
  }

  /**
   * Helper to get the secret key status message.
   *
   * @param bool $update
   *   Whether to fetch the 'update' status message.
   */
  protected function getSecretKeyStatusMessage(bool $update = FALSE): string {
    if ($update) {
      return $this->secretManager->getSecretKey() ? $this->t('✔ Secret key was successfully updated!') : $this->t('✗ Secret key could not be updated!');
    }

    return $this->secretManager->getSecretKey() ? $this->t('✔ Secret key is configured') : $this->t('✗ Secret key is not configured');
  }

  /**
   * Field map to create label form fields dynamically.
   *
   * @see altcha_captcha()
   */
  public static function getLabelMap(): array {
    return [
      'aria' => [
        'altcha_title' => t('Aria link label text'),
        'altcha_key' => 'ariaLinkLabel',
        'altcha_example' => t('Visit altcha.org', [], ['context' => 'altcha_placeholder']),
      ],
      'enter_code' => [
        'altcha_title' => t('Enter code text'),
        'altcha_key' => 'enterCode',
        'altcha_example' => t('Enter code', [], ['context' => 'altcha_placeholder']),
      ],
      'enter_code_aria' => [
        'altcha_title' => t('Aria enter code label text'),
        'altcha_key' => 'enterCodeAria',
        'altcha_example' => t('Enter code you hear. Press Space to play audio.', [], ['context' => 'altcha_placeholder']),
      ],
      'error' => [
        'altcha_title' => t('Error text'),
        'altcha_key' => 'error',
        'altcha_example' => t('Verification failed. Try again later.', [], ['context' => 'altcha_placeholder']),
      ],
      'expired' => [
        'altcha_title' => t('Expired text'),
        'altcha_key' => 'expired',
        'altcha_example' => t('Verification expired. Try again.', [], ['context' => 'altcha_placeholder']),
      ],
      'footer' => [
        'altcha_title' => t('Footer text'),
        'altcha_key' => 'footer',
        'altcha_example' => t('Protected by <a href="https://altcha.org" target="_blank" aria-label="Visit altcha.org">ALTCHA</a>', [], ['context' => 'altcha_placeholder']),
      ],
      'get_audio' => [
        'altcha_title' => t('Get audio challenge text'),
        'altcha_key' => 'getAudioChallenge',
        'altcha_example' => t('Get an audio challenge', [], ['context' => 'altcha_placeholder']),
      ],
      'label' => [
        'altcha_title' => t('Label text'),
        'altcha_key' => 'label',
        'altcha_example' => t("I'm not a robot", [], ['context' => 'altcha_placeholder']),
      ],
      'verify' => [
        'altcha_title' => t('Verify button text'),
        'altcha_key' => 'verify',
        'altcha_example' => t('Verify', [], ['context' => 'altcha_placeholder']),
      ],
      'verification_required' => [
        'altcha_title' => t('Verification required text'),
        'altcha_key' => 'verificationRequired',
        'altcha_example' => t('Verification required!', [], ['context' => 'altcha_placeholder']),
      ],
      'verified' => [
        'altcha_title' => t('Verified text'),
        'altcha_key' => 'verified',
        'altcha_example' => t('Verified', [], ['context' => 'altcha_placeholder']),
      ],
      'verifying' => [
        'altcha_title' => t('Verifying text'),
        'altcha_key' => 'verifying',
        'altcha_example' => t('Verifying...', [], ['context' => 'altcha_placeholder']),
      ],
      'wait' => [
        'altcha_title' => t('Wait alert text'),
        'altcha_description' => t('This only applies when the widget has auto-verification active and set to "form submit". The alert appears when a user re-submits the form when ALTCHA is still calculating the solution for the initial submit.'),
        'altcha_key' => 'waitAlert',
        'altcha_example' => t('Verifying... please wait.', [], ['context' => 'altcha_placeholder']),
      ],
    ];
  }

  /**
   * SaaS API Region map.
   *
   * @see altcha_captcha()
   */
  public static function getRegionUrlMap(): array {
    return [
      'eu' => 'https://eu.altcha.org',
      'us' => 'https://us.altcha.org',
    ];
  }

}
