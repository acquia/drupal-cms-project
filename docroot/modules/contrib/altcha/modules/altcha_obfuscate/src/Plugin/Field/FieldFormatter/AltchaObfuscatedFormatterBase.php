<?php

namespace Drupal\altcha_obfuscate\Plugin\Field\FieldFormatter;

use Drupal\altcha\Form\AltchaSettingsForm;
use Drupal\altcha\Utility\AltchaI18nUtility;
use Drupal\altcha_obfuscate\Utility\ObfuscationUtility;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for ALTCHA obfuscation formatters.
 */
abstract class AltchaObfuscatedFormatterBase extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'reveal_text_override' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $elements['reveal_text_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override reveal text'),
      '#description' => $this->t('Optionally override the global reveal text.'),
      '#default_value' => $this->getSetting('reveal_text_override'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    if ($this->getSetting('reveal_text_override')) {
      $summary[] = $this->t('Reveal text is overridden: @text', [
        '@text' => $this->getSetting('reveal_text_override'),
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $config = $this->configFactory->get('altcha.settings');

    $labels = array_filter(array_map(function ($altcha_info) use ($config) {
      return $config->get($altcha_info['altcha_key']) ?? NULL;
    }, AltchaSettingsForm::getLabelMap()));

    [$language, $i18n_library] = AltchaI18nUtility::getI18nAttachment($labels);

    $attributes = [
      'hidelogo' => $config->get('hide_logo') ?? NULL,
      'hidefooter' => $config->get('hide_footer') ?? NULL,
      'language' => $language,
    ];

    $elements = [];
    foreach ($items as $delta => $item) {
      $attributes = array_merge($attributes, [
        'obfuscated' => ObfuscationUtility::encrypt(
          $this->transformValue($item->getValue()['value'] ?? NULL),
          '',
          $config->get('obfuscate_max_number') ?? NULL,
        ),
        // Floating is hardcoded enabled, since the non-floating UI is visually
        // not appealing and less user-friendly. The official ALTCHA docs also
        // recommend this, specifically when using the obfuscation plugin.
        'floating' => TRUE,
        'plugins' => 'obfuscation',
      ]);

      // Global reveal options.
      $reveal_element = [
        '#theme' => 'altcha_obfuscate_reveal_button',
        '#attributes' => [
          'data-clarify-button' => '',
        ],
        '#text' => $config->get('obfuscate_reveal_text') ?: $this->t('Click to reveal'),
        '#view_mode' => $this->viewMode,
        '#entity_type' => $items->getEntity()->getEntityTypeId(),
        '#plugin_id' => $this->getPluginId(),
      ];

      // Text override.
      if (!empty($this->getSetting('reveal_text_override'))) {
        $reveal_element['#text'] = $this->getSetting('reveal_text_override');
      }

      $elements[$delta] = [
        '#theme' => 'altcha_widget',
        '#attributes' => array_filter($attributes),
        '#content' => $reveal_element,
        '#attached' => array_merge_recursive(
          altcha_get_attached_library('altcha_obfuscate/altcha_obfuscate', 'obfuscate_library_override'),
          altcha_get_attached_library('altcha/altcha', 'library_override'),
          $i18n_library,
        ),
        '#cache' => [
          'tags' => [
            'config:altcha.settings',
          ],
        ],
      ];
    }

    return $elements;
  }

  /**
   * Transform value pre-obfuscation.
   *
   * @param string $value
   *   The string value before encryption.
   *
   * @return string
   *   Transformed string value.
   */
  abstract protected function transformValue(string $value): string;

}
