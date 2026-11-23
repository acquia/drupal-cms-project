<?php

namespace Drupal\geofield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'geofield_latlon' widget.
 */
#[FieldWidget(
  id: 'geofield_latlon',
  label: new TranslatableMarkup('Latitude/Longitude'),
  field_types: [
    'geofield',
  ],
)]
class GeofieldLatLonWidget extends GeofieldBaseWidget {

  /**
   * Lat Lon widget components.
   *
   * @var array
   */
  public $components = ['lon', 'lat'];

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The default settings, keyed by setting name.
   */
  public static function defaultSettings() {
    return [
      'html5_geolocation' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The form elements for the settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['html5_geolocation'] = [
      '#type' => 'checkbox',
      '#title' => 'Use HTML5 Geolocation to set default values',
      '#default_value' => $this->getSetting('html5_geolocation'),
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
    return [
      $this->t('Use HTML5 Geolocation of user: @state', ['@state' => $this->getSetting('html5_geolocation') ? $this->t('enabled') : $this->t('disabled')]),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The form element for the field item.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $geofield_item = $items->getValue()[$delta];
    if (!is_array($geofield_item) || empty($geofield_item['value'])) {
      $geofield_item = [];
    }

    if (empty($geofield_item) || ($geofield_item['geo_type'] ?? NULL) === 'Point') {
      $latlon_value = [];

      foreach ($this->components as $component) {
        $latlon_value[$component] = isset($items[$delta]->{$component}) ? floatval($items[$delta]->{$component}) : '';
      }

      $element['value'] += [
        '#type' => 'geofield_latlon',
        '#default_value' => $latlon_value,
        '#geolocation' => $this->getSetting('html5_geolocation'),
        '#error_label' => !empty($element['#title']) ? $element['#title'] : $this->fieldDefinition->getLabel(),
      ];
    }
    else {
      $widget_label = $this->getPluginDefinition()['label']->render();
      $element['value'] += [
        '#prefix' => '<div class="geofield-warning">' . $this->t('The "@widget_label" widget cannot be applied because it doesn\'t support no "Point" type Geometries.', [
          '@widget_label' => $widget_label,
        ]) . '</div>',
        '#type' => 'textarea',
        '#default_value' => $geofield_item['value'],
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The field values, in the form the field type stores.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      foreach ($this->components as $component) {
        if (!isset($value['value'][$component]) || !is_numeric($value['value'][$component])) {
          $values[$delta]['value'] = '';
          continue 2;
        }

      }
      $components = $value['value'];
      $values[$delta]['value'] = $this->geofieldBackendValue($this->wktGenerator->wktBuildPoint([
        trim($components['lon']),
        trim($components['lat']),
      ]));
    }

    return $values;
  }

}
