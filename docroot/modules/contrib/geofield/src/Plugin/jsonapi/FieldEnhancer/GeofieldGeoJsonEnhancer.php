<?php

namespace Drupal\geofield\Plugin\jsonapi\FieldEnhancer;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\jsonapi_extras\Attribute\ResourceFieldEnhancer;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enhances geofield data to output GeoJSON format.
 */
#[ResourceFieldEnhancer(
  id: 'geofield_geojson',
  label: new TranslatableMarkup('GeoJSON (Geofield)'),
  description: new TranslatableMarkup('Converts Geofield data to GeoJSON format.'),
  dependencies: ['geofield'],
)]
class GeofieldGeoJsonEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * The GeoPHP wrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  private readonly GeoPHPInterface $geoPHP;

  /**
   * Constructs a new GeofieldGeoJsonEnhancer.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geo_php
   *   The GeoPHP wrapper service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, GeoPHPInterface $geo_php) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->geoPHP = $geo_php;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('geofield.geophp'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Convert GeoJSON back to WKT for storage.
   */
  protected function doTransform($data, Context $context) {
    if (empty($data)) {
      return NULL;
    }

    $geojson = is_array($data) ? Json::encode($data) : $data;
    $geometry = $this->geoPHP->load($geojson, 'json');

    if (!$geometry instanceof \Geometry) {
      return NULL;
    }

    return $geometry->out('wkt');
  }

  /**
   * {@inheritdoc}
   *
   *  Converts a Geofield WKT value to a decoded GeoJSON array.
   */
  protected function doUndoTransform($data, Context $context) {
    if (empty($data)) {
      return NULL;
    }

    // The data comes as the raw field value. Extract the WKT string.
    $wkt = is_array($data) ? ($data['value'] ?? NULL) : $data;

    if (empty($wkt)) {
      return NULL;
    }

    $geometry = $this->geoPHP->load($wkt);

    if (!$geometry instanceof \Geometry) {
      return NULL;
    }

    return Json::decode($geometry->out('json'));
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema(): array {
    $geometry_types = [
      'Point',
      'MultiPoint',
      'LineString',
      'MultiLineString',
      'Polygon',
      'MultiPolygon',
    ];
    return [
      'anyOf' => [
        [
          'type' => 'object',
          'properties' => [
            'type' => ['type' => 'string', 'enum' => $geometry_types],
            'coordinates' => ['type' => 'array'],
          ],
          'required' => ['type', 'coordinates'],
        ],
        [
          'type' => 'object',
          'properties' => [
            'type' => ['type' => 'string', 'enum' => ['GeometryCollection']],
            'geometries' => ['type' => 'array'],
          ],
          'required' => ['type', 'geometries'],
        ],
        ['type' => 'null'],
      ],
    ];
  }

}
