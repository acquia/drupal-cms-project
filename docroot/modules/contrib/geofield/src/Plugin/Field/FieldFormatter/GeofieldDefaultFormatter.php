<?php

namespace Drupal\geofield\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'geofield_default' formatter.
 */
#[FieldFormatter(
  id: 'geofield_default',
  label: new TranslatableMarkup('Raw Output'),
  field_types: [
    'geofield',
  ],
)]
class GeofieldDefaultFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * The Adapter Map Options.
   *
   * @var array
   */
  protected $options;

  /**
   * GeofieldDefaultFormatter constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The geoPhpWrapper.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    GeoPHPInterface $geophp_wrapper,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->geoPhpWrapper = $geophp_wrapper;
    $this->options = $this->geoPhpWrapper->getAdapterMap();
  }

  /**
   * {@inheritdoc}
   *
   * @return static
   *   A new instance of the plugin.
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
      $container->get('geofield.geophp')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The default settings, keyed by setting name.
   */
  public static function defaultSettings() {
    return [
      'output_format' => 'wkt',
      'output_escape' => TRUE,
    ];
  }

  /**
   * Returns the output format, set or default one.
   *
   * @return string
   *   The output format string.
   */
  protected function getOutputFormat() {
    return in_array($this->getSetting('output_format'), array_keys($this->options)) ? $this->getSetting('output_format') : self::defaultSettings()['output_format'];
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The form elements for the settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    unset($this->options['google_geocode']);

    $elements['output_format'] = [
      '#title' => $this->t('Output Format'),
      '#type' => 'select',
      '#default_value' => $this->getOutputFormat(),
      '#options' => $this->options,
      '#required' => TRUE,
    ];

    $elements['output_escape'] = [
      '#title' => $this->t('Escape output (recommended)'),
      '#description' => $this->t('The text is escaped by converting special characters to HTML entities.<br>In some circumstances (i.e. part of Json output) this might not be the wanted/preferred behavior.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('output_escape'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The lines of the settings summary.
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Geospatial output format: @format', ['@format' => $this->getOutputFormat()]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   A render array for each field item, keyed by delta.
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $geom = $this->geoPhpWrapper->load($item->get('value')->getValue());
      $output = $geom ? $geom->out($this->getOutputFormat()) : '';
      if ($this->getSetting('output_escape')) {
        $output = Html::escape($output);
      }
      $elements[$delta] = ['#markup' => $output];
    }
    return $elements;
  }

}
