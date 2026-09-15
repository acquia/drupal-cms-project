<?php

namespace Drupal\google_translator\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Google Translate Language Selector' block.
 *
 * @Block(
 *   id = "google_translator",
 *   admin_label = @Translation("Google Translator"),
 *   category = @Translation("Google Translator")
 * )
 */
class GoogleTranslator extends BlockBase implements ContainerFactoryPluginInterface {

  const DISCLAIMER_CLASS = 'google-translator-switch';
  const ELEMENT_ID = 'google_translator_element';

  /**
   * This module's settings, pulled from configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $moduleConfig;

  /**
   * A logger channel for this module.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $log;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Class constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ImmutableConfig $module_config
   *   This module's settings, pulled from configuration.
   * @param \Psr\Log\LoggerInterface $log
   *   A logger channel for this module.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    ImmutableConfig $module_config,
    LoggerInterface $log,
    RendererInterface $renderer,
    LanguageManagerInterface $languageManager) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleConfig = $module_config;
    $this->log = $log;
    $this->renderer = $renderer;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container
        ->get('config.factory')
        ->get('google_translator.settings'),
      $container
        ->get('logger.factory')
        ->get('google_translator'),
      $container
        ->get('renderer'),
      $container
        ->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label' => $this->t('Translate this page'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    // Override the block label display checkbox, since title is required.
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['label_display'] = [
      '#type' => 'value',
      '#value' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Build a link for our custom disclaimer javascript to target. This render
    // element can't live at the top level, because its '#attributes' key will
    // get gobbled up by the block attributes processing.
    $build = [
      'link' => [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => $this
          ->label(),
      ],
      '#cache' => [
        'tags' => $this->moduleConfig->getCacheTags(),
      ],
    ];
    $this->addLinkAttributes($build['link']['#attributes']);
    $this->attachDisclaimer($build);
    return $build;
  }

  /**
   * Returns a safe version of the disclaimer string.
   *
   * @return string
   *   The sanitized disclaimer string.
   */
  protected function getDisclaimer() {
    return Xss::filterAdmin($this->moduleConfig
      ->get('google_translator_disclaimer'));
  }

  /**
   * Adds link attributes to support the disclaimer popup.
   */
  protected function addLinkAttributes(&$attributes) {
    $attributes['href'] = '#';
    $attributes['class'][] = 'notranslate';
    $attributes['class'][] = static::DISCLAIMER_CLASS;
  }

  /**
   * Attaches the javascript libraries and settings to the given element.
   */
  public function attachDisclaimer(&$build) {
    $element = $this->getElement();
    $build['#attached']['library'][] = 'google_translator/disclaimer';
    $build['#attached']['drupalSettings']['googleTranslatorDisclaimer'] = [
      'selector' => '.' . static::DISCLAIMER_CLASS,
      'displayMode' => Html::escape($this->moduleConfig
        ->get('google_translator_active_languages_display_mode')),
      'disclaimerTitle' => Xss::filterAdmin($this->moduleConfig
        ->get('google_translator_disclaimer_title')),
      'disclaimer' => $this
        ->getDisclaimer(),
      'acceptText' => $this
        ->t('Accept'),
      'dontAcceptText' => $this
        ->t('Do Not Accept'),
      'element' => $this->renderer
        ->render($element),
    ];
  }

  /**
   * Builds the script to be injected on the page.
   *
   * @return array
   *   A render array with markup for targeting by the Google Translate script.
   */
  protected function getElement() {
    // Get current language code.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Make sure languages are enabled.
    $active_languages = array_filter($this->moduleConfig
      ->get('google_translator_active_languages'));
    if (empty($active_languages)) {
      $this->log
        ->warning('Specify some languages in the <a href=":url">Google Translator settings</a> to enable translation', [
          ':url' => '/admin/config/regional/google-translator',
        ]);
      return [
        '#markup' => $this
          ->t('No languages available for translation'),
      ];
    }

    // Sanitize inputs.
    array_walk($active_languages, 'Drupal\Component\Utility\Html::escape');
    $display_mode = Html::escape($this->moduleConfig
      ->get('google_translator_active_languages_display_mode'));

    // Build the render array that will print the Google Translate widget.
    $callback = 'Drupal.behaviors.googleTranslatorElement.init';
    return [
      'placeholder' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['id' => static::ELEMENT_ID],
        '#attached' => [
          'library' => [
            'google_translator/element',
          ],
          'drupalSettings' => [
            'googleTranslatorElement' => [
              'id' => static::ELEMENT_ID,
              'langcode' => $langcode,
              'languages' => implode(',', $active_languages),
              'displayMode' => $display_mode ?: 'SIMPLE',
            ],
          ],
        ],
      ],
      'script' => [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#attributes' => [
          'src' => "//translate.google.com/translate_a/element.js?cb=$callback",
        ],
      ],
    ];
  }

}
