<?php

namespace Drupal\eureka\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ThemeSettingsProvider;

/**
 * Hook implementations for form alters.
 */
class FormAlterHooks {
  use StringTranslationTrait;

  /**
   * The theme settings provider.
   *
   * @var \Drupal\Core\Extension\ThemeSettingsProvider
   */
  protected ThemeSettingsProvider $themeSettingsProvider;

  /**
   * Constructs a new FormAlterHooks object.
   *
   * @param \Drupal\Core\Extension\ThemeSettingsProvider $themeSettingsProvider
   *   The theme settings provider.
   */
  public function __construct(
    ThemeSettingsProvider $themeSettingsProvider
  ) {
    $this->themeSettingsProvider = $themeSettingsProvider;
  }

  /**
   * Implements hook_form_system_theme_settings_alter().
   */
  #[Hook('form_system_theme_settings_alter')]
  public function formSystemThemeSettingsAlter(array &$form): void {
    $form['scheme_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Colour scheme'),
      '#open' => TRUE,
    ];

    $form['scheme_wrapper']['scheme'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the colour scheme'),
      '#description' => $this->t('This defines the main accent colour for the website.'),
      '#description_display' => 'before',
      '#default_value' => $this->themeSettingsProvider->getSetting('scheme'),
      '#options' => [
        'blue' => $this->t('Blue'),
        'red' => $this->t('Red'),
        'purple' => $this->t('Purple'),
        'green' => $this->t('Green'),
      ],
    ];

    $form['global_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Global settings'),
      '#open' => TRUE,
    ];

    $form['global_wrapper']['telephone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Contact telephone'),
      '#description' => $this->t('This defines the contact telephone for multiple parts of the website (like the header/footer).'),
      '#default_value' => $this->themeSettingsProvider->getSetting('telephone'),
    ];

    $form['global_wrapper']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#description' => $this->t('This defines the email address to display for multiple parts of the website (like the header/footer).'),
      '#default_value' => $this->themeSettingsProvider->getSetting('email'),
    ];

    $form['global_wrapper']['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#description' => $this->t('This defines the location to display for multiple parts of the website (like the header/footer).'),
      '#default_value' => $this->themeSettingsProvider->getSetting('location'),
    ];

    $form['header_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Header settings'),
      '#open' => TRUE,
    ];

    $form['header_wrapper']['header_info'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header top info'),
      '#description' => $this->t('This defines the short informational text to display in the header top bar.'),
      '#default_value' => $this->themeSettingsProvider->getSetting('header_info'),
    ];

    $form['footer_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Footer settings'),
      '#open' => TRUE,
    ];

    $form['footer_wrapper']['footer_contact_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact us title'),
      '#description' => $this->t('If no value is provided, it defaults to `Contact Us`'),
      '#default_value' => $this->themeSettingsProvider->getSetting('footer_contact_title'),
    ];

    $form['footer_wrapper']['footer_times_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opening times title'),
      '#description' => $this->t('If no value is provided, it defaults to `Opening Times`'),
      '#default_value' => $this->themeSettingsProvider->getSetting('footer_times_title'),
    ];

    $form['footer_wrapper']['footer_quicklinks_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Quick links title'),
      '#description' => $this->t('If no value is provided, it defaults to `Quick Links`'),
      '#default_value' => $this->themeSettingsProvider->getSetting('footer_quicklinks_title'),
    ];

    $footer_times = $this->themeSettingsProvider->getSetting('footer_times') ?? [];
    $form['footer_wrapper']['footer_times'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Opening times'),
      '#default_value' => $footer_times['value'] ?? '',
    ];
  }

}
