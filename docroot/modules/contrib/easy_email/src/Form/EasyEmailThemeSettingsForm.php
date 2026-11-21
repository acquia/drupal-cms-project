<?php

namespace Drupal\easy_email\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Easy Email theme settings.
 */
class EasyEmailThemeSettingsForm extends FormBase {

  protected ThemeExtensionList $themeExtensionList;

  protected ThemeInstallerInterface $themeInstaller;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The typed config manager.
   */
  protected TypedConfigManagerInterface $typedConfigManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->themeExtensionList = $container->get('extension.list.theme');
    $instance->themeInstaller = $container->get('theme_installer');
    $instance->fileSystem = $container->get('file_system');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->typedConfigManager = $container->get('config.typed');
    return $instance;
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'easy_email_theme_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $themes = $this->themeExtensionList->reset()->getList();
    $user_can_administer_themes = $this->currentUser()->hasPermission('administer themes');
    $theme_is_available = !empty($themes['easy_email_theme']);
    $theme_is_installed = $theme_is_available && !empty($themes['easy_email_theme']->status);

    $form['easy_email_theme'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Easy Email Theme'),
    ];

    if ($user_can_administer_themes) {
      $form['easy_email_theme']['available'] = [
        '#type' => 'value',
        '#value' => $theme_is_available,
      ];

      if ($theme_is_available) {
        $form['easy_email_theme']['enable'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable Easy Email Theme'),
          '#default_value' => $theme_is_installed,
        ];
        if ($theme_is_installed) {
          $form['easy_email_theme']['logo'] = $this->buildLogoSettings();
          if ($this->themeSupportsFooter()) {
            $form['easy_email_theme']['footer'] = $this->buildFooterSettings();
          }
        }
      }
      else {
        $form['easy_email_theme']['message'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => [
              'messages',
              'messages--warning',
            ],
          ],
          '#value' => $this->t('<a href="@link" target="_blank">Easy Email Theme</a> is not available', [
            '@link' => 'https://www.drupal.org/project/easy_email_theme',
          ]),
        ];
      }
    }
    else {
      $form['easy_email_theme']['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => [
            'messages',
            'messages--warning',
          ],
        ],
        '#value' => $this->t('You must have %permission permission to change theme settings.', [
          '%permission' => 'administer themes',
        ]),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save theme settings'),
      '#button_type' => 'primary',
      '#disabled' => !$theme_is_available || !$user_can_administer_themes,
    ];

    return $form;
  }

  /**
   * Builds the logo settings of Easy Email Theme.
   *
   * The theme is hidden, so its own settings page is not reachable.
   *
   * @return array
   *   The logo settings form elements.
   */
  protected function buildLogoSettings(): array {
    $config = $this->config('easy_email_theme.settings');
    $element = [
      '#type' => 'details',
      '#title' => $this->t('Logo'),
      '#open' => TRUE,
    ];
    $element['use_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use the logo of the default theme'),
      '#default_value' => $config->get('logo.use_default') ?? TRUE,
    ];
    $element['settings'] = [
      '#type' => 'container',
      '#states' => [
        'invisible' => [
          'input[name="easy_email_theme[logo][use_default]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['settings']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to custom logo'),
      '#description' => $this->t('A path relative to the Drupal root or to the public files directory, or a stream wrapper URI. Many email clients do not show SVG images, so use a PNG, JPEG or GIF.'),
      '#default_value' => $config->get('logo.path'),
    ];
    if ($this->moduleHandler->moduleExists('file')) {
      // File elements do not support nested names, so give it its own.
      $element['settings']['upload'] = [
        '#type' => 'file',
        '#parents' => ['logo_upload'],
        '#title' => $this->t('Upload logo image'),
        '#description' => $this->t("If you don't have direct file access to the server, use this field to upload your logo."),
        '#upload_validators' => [
          'FileExtension' => [
            'extensions' => 'png gif jpg jpeg',
          ],
        ],
      ];
    }
    return $element;
  }

  /**
   * Checks whether the installed Easy Email Theme supports a footer.
   *
   * The footer was added in a later release of Easy Email Theme, so older
   * versions do not define it in their config schema.
   *
   * @return bool
   *   TRUE if the theme's config schema defines the footer settings.
   */
  protected function themeSupportsFooter(): bool {
    if (!$this->typedConfigManager->hasConfigSchema('easy_email_theme.settings')) {
      return FALSE;
    }
    $definition = $this->typedConfigManager->getDefinition('easy_email_theme.settings');
    return isset($definition['mapping']['footer']);
  }

  /**
   * Builds the footer settings of Easy Email Theme.
   *
   * @return array
   *   The footer settings form elements.
   */
  protected function buildFooterSettings(): array {
    $config = $this->config('easy_email_theme.settings');
    $element = [
      '#type' => 'details',
      '#title' => $this->t('Footer'),
      '#open' => TRUE,
    ];
    $element['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a footer'),
      '#description' => $this->t('Ends HTML emails with the site name and slogan from the <a href=":url">Basic site settings</a>.', [
        ':url' => Url::fromRoute('system.site_information_settings')->toString(),
      ]),
      '#default_value' => $config->get('footer.enabled') ?? FALSE,
    ];
    $element['settings'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          'input[name="easy_email_theme[footer][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    // The theme shows both parts unless they are turned off.
    $element['settings']['site_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Site name'),
      '#default_value' => $config->get('footer.site_name') ?? TRUE,
    ];
    $element['settings']['slogan'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Slogan'),
      '#default_value' => $config->get('footer.slogan') ?? TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if (!isset($form['easy_email_theme']['logo'])) {
      return;
    }
    $logo = $form['easy_email_theme']['logo'];
    if (isset($logo['settings']['upload'])) {
      $file = _file_save_upload_from_form($logo['settings']['upload'], $form_state, 0);
      if ($file) {
        $form_state->setValue('logo_upload', $file);
      }
    }
    $path = $form_state->getValue(['easy_email_theme', 'logo', 'settings', 'path']);
    if (!$form_state->getValue(['easy_email_theme', 'logo', 'use_default']) && $path && !$this->validateLogoPath($path)) {
      $form_state->setError($logo['settings']['path'], $this->t('The custom logo path is invalid.'));
    }
  }

  /**
   * Saves the logo settings of Easy Email Theme.
   *
   * @param array $values
   *   The submitted logo values.
   * @param \Drupal\file\FileInterface|null $upload
   *   The uploaded logo, if any.
   */
  protected function submitLogoSettings(array $values, $upload = NULL): void {
    $use_default = !empty($values['use_default']);
    $path = $values['settings']['path'] ?? '';
    if ($upload) {
      try {
        $default_scheme = $this->config('system.file')->get('default_scheme');
        $path = $this->fileSystem->copy($upload->getFileUri(), $default_scheme . '://');
        $use_default = FALSE;
      }
      catch (FileException) {
        $this->messenger()->addError($this->t('The logo could not be saved.'));
      }
    }
    $this->configFactory()->getEditable('easy_email_theme.settings')
      ->set('logo.use_default', $use_default)
      ->set('logo.path', $use_default ? '' : (string) $this->validateLogoPath($path))
      ->save();
  }

  /**
   * Saves the footer settings of Easy Email Theme.
   *
   * @param array $values
   *   The submitted footer values.
   */
  protected function submitFooterSettings(array $values): void {
    $this->configFactory()->getEditable('easy_email_theme.settings')
      ->set('footer.enabled', !empty($values['enabled']))
      ->set('footer.site_name', !empty($values['settings']['site_name']))
      ->set('footer.slogan', !empty($values['settings']['slogan']))
      ->save();
  }

  /**
   * Validates a logo path, like the core theme settings form does.
   *
   * @param string $path
   *   A path relative to the Drupal root or to the public files directory, or
   *   a stream wrapper URI.
   *
   * @return string|false
   *   A path the theme can display, or FALSE when it is not valid.
   */
  protected function validateLogoPath(string $path): string|false {
    if ($path === '' || $this->fileSystem->realpath($path) == $path) {
      return FALSE;
    }
    if (is_file($path)) {
      return $path;
    }
    if (StreamWrapperManager::getScheme($path) === FALSE) {
      $path = 'public://' . $path;
    }
    return is_file($path) ? $path : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if ($this->currentUser()->hasPermission('administer themes') && !empty($values['easy_email_theme']['available'])) {
      $themes = $this->themeExtensionList->reset()->getList();
      $theme_is_installed = !empty($themes['easy_email_theme']) && !empty($themes['easy_email_theme']->status);
      if (!$theme_is_installed && !empty($values['easy_email_theme']['enable'])) {
        $this->themeInstaller->install(['easy_email_theme']);
        $this->messenger()->addStatus(t('Easy Email Theme has been installed.'));
      }
      elseif ($theme_is_installed && empty($values['easy_email_theme']['enable'])) {
        $this->themeInstaller->uninstall(['easy_email_theme']);
        $this->messenger()->addStatus(t('Easy Email Theme has been uninstalled.'));
      }
      elseif ($theme_is_installed && isset($values['easy_email_theme']['logo'])) {
        $this->submitLogoSettings($values['easy_email_theme']['logo'], $form_state->getValue('logo_upload'));
        if (isset($values['easy_email_theme']['footer'])) {
          $this->submitFooterSettings($values['easy_email_theme']['footer']);
        }
        $this->messenger()->addStatus($this->t('The theme settings have been saved.'));
      }
    }
  }

  public function access() : AccessResultInterface {
    $themes = $this->themeExtensionList->reset()->getList();
    $theme_is_available = !empty($themes['easy_email_theme']);
    return AccessResult::allowedIf($theme_is_available)
      ->andIf(AccessResult::allowedIfHasPermission($this->currentUser(), 'administer themes'));
  }

}
