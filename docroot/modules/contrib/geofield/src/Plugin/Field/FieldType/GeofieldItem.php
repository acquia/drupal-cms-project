<?php

namespace Drupal\geofield\Plugin\Field\FieldType;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'geofield' field type.
 *
 * @property string|null $value
 * @property string|null $geo_type
 * @property float|null $lat
 * @property float|null $lon
 * @property float|null $left
 * @property float|null $top
 * @property float|null $right
 * @property float|null $bottom
 * @property string|null $geohash
 * @property string|null $latlon
 */
#[FieldType(
  id: 'geofield',
  label: new TranslatableMarkup('Geofield'),
  description: new TranslatableMarkup('This field stores geospatial information.'),
  default_widget: 'geofield_latlon',
  default_formatter: 'geofield_default',
)]
class GeofieldItem extends FieldItemBase {

  /**
   * The Geofield Geometry.
   *
   * @var \Geometry|null
   */
  protected ?\Geometry $geometry;

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The default storage settings, keyed by setting name.
   */
  public static function defaultStorageSettings() {
    return [
      'backend' => 'geofield_backend_default',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The default field settings, keyed by setting name.
   */
  public static function defaultFieldSettings() {
    return [] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The schema of the field columns and indexes.
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    /** @var \Drupal\geofield\Plugin\GeofieldBackendManager $backend_manager */
    $backend_manager = \Drupal::service('plugin.manager.geofield_backend');
    try {
      /** @var \Drupal\geofield\Plugin\GeofieldBackendPluginInterface $backend_plugin */
      if (!empty($field_definition->getSetting('backend')) && $backend_manager->getDefinition($field_definition->getSetting('backend')) != NULL) {
        $backend_plugin = $backend_manager->createInstance($field_definition->getSetting('backend'));
      }
    }
    catch (PluginException $e) {
      \Drupal::service('logger.factory')->get('geofield')->error($e->getMessage());
    }
    return [
      'columns' => [
        'value' => isset($backend_plugin) ? $backend_plugin->schema() : [],
        'geo_type' => [
          'type' => 'varchar',
          'default' => '',
          'length' => 64,
        ],
        'lat' => [
          'type' => 'numeric',
          'precision' => 18,
          'scale' => 12,
          'not null' => FALSE,
        ],
        'lon' => [
          'type' => 'numeric',
          'precision' => 18,
          'scale' => 12,
          'not null' => FALSE,
        ],
        'left' => [
          'type' => 'numeric',
          'precision' => 18,
          'scale' => 12,
          'not null' => FALSE,
        ],
        'top' => [
          'type' => 'numeric',
          'precision' => 18,
          'scale' => 12,
          'not null' => FALSE,
        ],
        'right' => [
          'type' => 'numeric',
          'precision' => 18,
          'scale' => 12,
          'not null' => FALSE,
        ],
        'bottom' => [
          'type' => 'numeric',
          'precision' => 18,
          'scale' => 12,
          'not null' => FALSE,
        ],
        'geohash' => [
          'type' => 'varchar',
          'length' => GEOFIELD_GEOHASH_LENGTH,
          'not null' => FALSE,
        ],
      ],
      'indexes' => [
        'lat' => ['lat'],
        'lon' => ['lon'],
        'top' => ['top'],
        'bottom' => ['bottom'],
        'left' => ['left'],
        'right' => ['right'],
        'geohash' => ['geohash'],
        'centroid' => ['lat', 'lon'],
        'bbox' => ['top', 'bottom', 'left', 'right'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The property definitions, keyed by property name.
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Geometry'))
      ->addConstraint('GeoType', []);

    $properties['geo_type'] = DataDefinition::create('string')
      ->setLabel(t('Geometry Type'));

    $properties['lat'] = DataDefinition::create('float')
      ->setLabel(t('Centroid Latitude'));

    $properties['lon'] = DataDefinition::create('float')
      ->setLabel(t('Centroid Longitude'));

    $properties['left'] = DataDefinition::create('float')
      ->setLabel(t('Left Bounding'));

    $properties['top'] = DataDefinition::create('float')
      ->setLabel(t('Top Bounding'));

    $properties['right'] = DataDefinition::create('float')
      ->setLabel(t('Right Bounding'));

    $properties['bottom'] = DataDefinition::create('float')
      ->setLabel(t('Bottom Bounding'));

    $properties['geohash'] = DataDefinition::create('string')
      ->setLabel(t('Geohash'));

    $properties['latlon'] = DataDefinition::create('string')
      ->setLabel(t('LatLong Pair'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The form elements for the storage settings.
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $settings = $this->getSettings();

    // Provides a field for the geofield storage backend plugin.
    $backend_manager = \Drupal::service('plugin.manager.geofield_backend');
    $backends = $backend_manager->getDefinitions();
    $backend_options = [];
    $backend_descriptions_list = '<ul>';
    foreach ($backends as $id => $backend) {
      $backend_options[$id] = $backend['admin_label'];
      $backend_descriptions_list .= '<li>' . $backend['admin_label'] . ': ' . $backend['description'] . '</li>';
    }
    $element['backend'] = [
      '#type' => 'select',
      '#title' => $this->t('Storage backend'),
      '#default_value' => $settings['backend'],
      '#options' => $backend_options,
      '#description' => [
        '#markup' => $this->t('Select the Backend for storing Geofield data. The following are available: @backend_descriptions_list', [
          '@backend_descriptions_list' => new FormattableMarkup($backend_descriptions_list, []),
        ]),
      ],
      '#disabled' => $has_data,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @return bool
   *   TRUE if the field item holds no value, FALSE otherwise.
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    if (!empty($value)) {
      /** @var \Drupal\geofield\GeoPHP\GeoPHPInterface $geo_php_wrapper */
      // Note: Geofield FieldType doesn't support Dependency Injection yet
      // (https://www.drupal.org/node/2053415).
      $geo_php_wrapper = \Drupal::service('geofield.geophp');
      $this->geometry = $geo_php_wrapper->load($value);
      return $this->geometry instanceof \Geometry ? $this->geometry->isEmpty() : TRUE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    parent::setValue($values);
    $this->populateComputedValues();
  }

  /**
   * Populates computed variables.
   */
  protected function populateComputedValues() {
    // Populate values only if $this->>value is not NULL.
    // @see https://www.drupal.org/project/geofield/issues/3256644
    // As passing null to parameter #2 ($data) of type string is deprecated in
    // fwrite() of geoPHP::detectFormat()
    // @see https://php.watch/versions/8.1/internal-func-non-nullable-null-deprecation
    if (!$this->isEmpty()) {
      /** @var \Point $centroid */
      $centroid = $this->geometry->getCentroid();
      $bounding = $this->geometry->getBBox();

      $this->geo_type = $this->geometry->geometryType();
      $this->lon = $centroid->getX();
      $this->lat = $centroid->getY();
      $this->left = $bounding['minx'];
      $this->top = $bounding['maxy'];
      $this->right = $bounding['maxx'];
      $this->bottom = $bounding['miny'];
      $this->geohash = substr($this->geometry->out('geohash'), 0, GEOFIELD_GEOHASH_LENGTH);
      $this->latlon = $centroid->getY() . ',' . $centroid->getX();
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The sample value of the field item.
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    return [
      'value' => \Drupal::service('geofield.wkt_generator')->WktGenerateGeometry(),
    ];
  }

}
