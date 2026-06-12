<?php

namespace Drupal\google_translator\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the Google Translator widget settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The Cache Render.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheRender;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, CacheBackendInterface $cache_render) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->cacheRender = $cache_render;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('cache.render')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_translator_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('google_translator.settings');
    $modes = [
      'SIMPLE' => $this->t('Simple'),
      'HORIZONTAL' => $this->t('Horizontal'),
      'VERTICAL' => $this->t('Vertical'),
    ];

    $form['google_translator_active_languages_display_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display Mode'),
      '#description' => $this
        ->t('Simple is the most compact setting.<br>Horizontal will display "Powered by Google" text next to the language selector.<br>Vertical will display "Powered by Google" text beneath the language selector.'),
      '#options' => $modes,
      '#default_value' => $config->get('google_translator_active_languages_display_mode'),
    ];

    $form['gt_active_languages'] = [
      '#title' => $this->t('Languages configuration. Configure the languages available to your site.'),
      '#type' => 'details',
      '#collapsed' => TRUE,
    ];

    $form['gt_active_languages']['google_translator_active_languages'] = [
      '#type' => 'checkboxes',
      '#title' => $this
        ->t('Available Languages'),
      '#options' => $this
        ->getAvailableLanguages(),
      '#description' => $this
        ->t('Please select specific languages'),
      '#default_value' => $config
        ->get('google_translator_active_languages') ?: [],
    ];

    $form['google_translator_disclaimer_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service disclaimer title'),
      '#default_value' => $config->get('google_translator_disclaimer_title'),
      '#size' => 40,
      '#description' => $this->t('Title for the modal. Only shows up if you also set text.'),
    ];

    $form['google_translator_disclaimer'] = [
      '#title' => $this
        ->t('Service disclaimer text'),
      '#type' => 'textarea',
      '#default_value' => $config
        ->get('google_translator_disclaimer'),
      '#description' => $this
        ->t('Optionally require users to accept a disclaimer (in a popup modal on click) before allowing translation. Allowed tags: @tags', [
          '@tags' => implode(', ', Xss::getAdminTagList()),
        ]),
    ];

    // Goose the style of the form.
    $form['#attached']['library'][] = 'google_translator/settings_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('google_translator.settings');

    $config->set('google_translator_active_languages_display_mode', $form_state->getValue('google_translator_active_languages_display_mode'));
    $config->set('google_translator_active_languages', array_values(array_filter($form_state->getValue('google_translator_active_languages'))));
    $config->set('google_translator_disclaimer_title', $form_state->getValue('google_translator_disclaimer_title'));
    $config->set('google_translator_disclaimer', $form_state->getValue('google_translator_disclaimer'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'google_translator.settings',
    ];
  }

  /**
   * Returns a list of available languages.
   *
   * @return array
   *   The list of available languages, suitable for being used as options.
   */
  protected function getAvailableLanguages() {
    return [
      'af' => 'Afrikaans',
      'sq' => 'Albanian',
      'am' => 'Amharic',
      'ar' => 'Arabic',
      'hy' => 'Armenian',
      'az' => 'Azerbaijani',
      'eu' => 'Basque',
      'be' => 'Belarusian',
      'bn' => 'Bengali',
      'bs' => 'Bosnian',
      'bg' => 'Bulgarian',
      'ca' => 'Catalan',
      'ny' => 'Chichewa',
      'zh-CN' => 'Chinese (Simplified)',
      'zh-TW' => 'Chinese (Traditional)',
      'co' => 'Corsican',
      'hr' => 'Croatian',
      'cs' => 'Czech',
      'da' => 'Danish',
      'nl' => 'Dutch',
      'en' => 'English',
      'eo' => 'Esperanto',
      'et' => 'Estonian',
      'ee' => 'Ewe',
      'tl' => 'Filipino',
      'fi' => 'Finnish',
      'fr' => 'French',
      'fy' => 'Frisian',
      'gl' => 'Galician',
      'ka' => 'Georgian',
      'de' => 'German',
      'el' => 'Greek',
      'gn' => 'Guarani',
      'gu' => 'Gujarati',
      'ht' => 'Haitian Creole',
      'ha' => 'Hausa',
      'haw' => 'Hawaiian',
      'iw' => 'Hebrew',
      'hi' => 'Hindi',
      'hmn' => 'Hmong',
      'hu' => 'Hungarian',
      'is' => 'Icelandic',
      'ig' => 'Igbo',
      'id' => 'Indonesian',
      'ga' => 'Irish',
      'it' => 'Italian',
      'ja' => 'Japanese',
      'jw' => 'Javanese',
      'kn' => 'Kannada',
      'kk' => 'Kazakh',
      'km' => 'Khmer',
      'rw' => 'Kinyarwanda',
      'ko' => 'Korean',
      'kri' => 'Krio',
      'ku' => 'Kurdish (Kurmanji)',
      'ckb' => 'Kurdish (Soranî)',
      'ky' => 'Kyrgyz',
      'lo' => 'Lao',
      'lv' => 'Latvian',
      'ln' => 'Lingala',
      'lt' => 'Lithuanian',
      'lg' => 'Luganda',
      'mk' => 'Macedonian',
      'mg' => 'Malagasy',
      'ms' => 'Malay',
      'ml' => 'Malayalam',
      'mt' => 'Maltese',
      'mi' => 'Maori',
      'mr' => 'Marathi',
      'mn' => 'Mongolian',
      'ne' => 'Nepali',
      'no' => 'Norwegian',
      'or' => 'Odia (Oriya)',
      'om' => 'Oromo',
      'ps' => 'Pashto',
      'fa' => 'Persian',
      'pl' => 'Polish',
      'pt' => 'Portuguese',
      'pa' => 'Punjabi',
      'qu' => 'Quechua',
      'ro' => 'Romanian',
      'ru' => 'Russian',
      'gd' => 'Scots Gaelic',
      'sr' => 'Serbian',
      'st' => 'Sesotho',
      'sn' => 'Shona',
      'sd' => 'Sindhi',
      'si' => 'Sinhala',
      'sk' => 'Slovak',
      'sl' => 'Slovenian',
      'so' => 'Somali',
      'es' => 'Spanish',
      'su' => 'Sundanese',
      'sw' => 'Swahili',
      'sv' => 'Swedish',
      'tg' => 'Tajik',
      'ta' => 'Tamil',
      'tt' => 'Tatar',
      'te' => 'Telugu',
      'th' => 'Thai',
      'ti' => 'Tigrinya',
      'tr' => 'Turkish',
      'tk' => 'Turkmen',
      'ug' => 'Uyghur',
      'uk' => 'Ukrainian',
      'ur' => 'Urdu',
      'uz' => 'Uzbek',
      'vi' => 'Vietnamese',
      'cy' => 'Welsh',
      'xh' => 'Xhosa',
      'yi' => 'Yiddish',
      'yo' => 'Yoruba',
      'zu' => 'Zulu',
    ];
  }

}
