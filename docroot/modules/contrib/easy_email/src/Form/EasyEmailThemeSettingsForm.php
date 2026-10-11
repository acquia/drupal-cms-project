<?php

namespace Drupal\easy_email\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Easy Email theme settings.
 */
class EasyEmailThemeSettingsForm extends FormBase {

  protected ThemeExtensionList $themeExtensionList;

  protected ThemeInstallerInterface $themeInstaller;


  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->themeExtensionList = $container->get('extension.list.theme');
    $instance->themeInstaller = $container->get('theme_installer');
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
    }
  }

  public function access() : AccessResultInterface {
    $themes = $this->themeExtensionList->reset()->getList();
    $theme_is_available = !empty($themes['easy_email_theme']);
    return AccessResult::allowedIf($theme_is_available)
      ->andIf(AccessResult::allowedIfHasPermission($this->currentUser(), 'administer themes'));
  }

}
