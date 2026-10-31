<?php

namespace Drupal\Tests\geofield\Unit\Plugin\jsonapi\FieldEnhancer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Shaper\Util\Context;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\geofield\GeoPHP\GeoPHPWrapper;
use Drupal\geofield\Plugin\jsonapi\FieldEnhancer\GeofieldGeoJsonEnhancer;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the GeoJSON field enhancer.
 *
 * Deprecations are ignored because, on Drupal 12, JSON:API Extras'
 * ResourceFieldEnhancerBase triggers DebugClassLoader notices for the Shaper
 * interfaces it implements. PHPUnit 11, which Drupal 11 runs, does not accept
 * a message pattern on this attribute.
 *
 * @coversDefaultClass \Drupal\geofield\Plugin\jsonapi\FieldEnhancer\GeofieldGeoJsonEnhancer
 * @group geofield
 */
#[Group('geofield')]
#[IgnoreDeprecations]
class GeofieldGeoJsonEnhancerTest extends UnitTestCase {

  /**
   * The Geofield JSON:API enhancer under test.
   *
   * @var \Drupal\geofield\Plugin\jsonapi\FieldEnhancer\GeofieldGeoJsonEnhancer
   */
  protected GeofieldGeoJsonEnhancer $geoFieldEnhancer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->geoFieldEnhancer = new GeofieldGeoJsonEnhancer([], 'geofield_geojson',
      [
        'id' => 'geofield_geojson',
        'label' => new TranslatableMarkup('GeoJSON (Geofield)'),
        'description' => new TranslatableMarkup('Converts Geofield data to GeoJSON format.'),
        'dependencies' => ['geofield'],
      ], new GeoPHPWrapper());
  }

  /**
   * Data provider for testUndoTransform.
   *
   * @return array<string, array{string, array<string, mixed>}>
   *   Named WKT inputs and their expected GeoJSON outputs.
   */
  public static function undoTransformProvider(): array {
    return [
      'point' => [
        'POINT (10 20)',
        ['type' => 'Point', 'coordinates' => [10, 20]],
      ],
      'linestring' => [
        'LINESTRING (0 0, 1 1, 2 2)',
        ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1], [2, 2]]],
      ],
      'polygon' => [
        'POLYGON ((0 0, 1 0, 1 1, 0 1, 0 0))',
        ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]],
      ],
    ];
  }

  /**
   * Data provider for testTransform.
   *
   * @return array<string, array{array<string, mixed>, string}>
   *   Named GeoJSON inputs and their expected WKT outputs.
   */
  public static function transformProvider(): array {
    return [
      'point' => [
        ['type' => 'Point', 'coordinates' => [10, 20]],
        'POINT (10 20)',
      ],
      'linestring' => [
        ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1], [2, 2]]],
        'LINESTRING (0 0, 1 1, 2 2)',
      ],
      'polygon' => [
        ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]],
        'POLYGON ((0 0, 1 0, 1 1, 0 1, 0 0))',
      ],
    ];
  }

  /**
   * Tests the JSON schema output structure.
   *
   * @covers ::getOutputJsonSchema
   */
  public function testGetOutputJsonSchema(): void {
    $schema = $this->geoFieldEnhancer->getOutputJsonSchema();

    $this->assertArrayHasKey('anyOf', $schema);
    $this->assertCount(3, $schema['anyOf']);

    // First branch: standard geometry types with coordinates.
    $geometry_branch = $schema['anyOf'][0];
    $this->assertEquals('object', $geometry_branch['type']);
    $this->assertArrayHasKey('type', $geometry_branch['properties']);
    $this->assertArrayHasKey('coordinates', $geometry_branch['properties']);
    $this->assertContains('required', array_keys($geometry_branch));
    $this->assertContains('type', $geometry_branch['required']);
    $this->assertContains('coordinates', $geometry_branch['required']);
    $this->assertContains('Point', $geometry_branch['properties']['type']['enum']);
    $this->assertNotContains('GeometryCollection', $geometry_branch['properties']['type']['enum']);

    // Second branch: GeometryCollection with geometries.
    $collection_branch = $schema['anyOf'][1];
    $this->assertEquals('object', $collection_branch['type']);
    $this->assertArrayHasKey('geometries', $collection_branch['properties']);
    $this->assertEquals(['GeometryCollection'], $collection_branch['properties']['type']['enum']);

    // Third branch: null for empty fields.
    $this->assertEquals('null', $schema['anyOf'][2]['type']);
  }

  /**
   * Tests WKT-to-GeoJSON conversion for the main geometry types.
   *
   * @covers ::doUndoTransform
   * @dataProvider undoTransformProvider
   */
  #[DataProvider('undoTransformProvider')]
  public function testUndoTransform(string $wkt, array $expected): void {
    $result = $this->geoFieldEnhancer->undoTransform(['value' => $wkt], new Context([]));
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests that a raw WKT string (not wrapped in an array) is accepted.
   *
   * @covers ::doUndoTransform
   */
  public function testUndoTransformAcceptsRawWktString(): void {
    $result = $this->geoFieldEnhancer->undoTransform('POINT (10 20)', new Context([]));
    $this->assertEquals(['type' => 'Point', 'coordinates' => [10, 20]], $result);
  }

  /**
   * Tests that an empty array returns NULL.
   *
   * @covers ::doUndoTransform
   */
  public function testUndoTransformWithEmptyDataReturnsNull(): void {
    $this->assertNull($this->geoFieldEnhancer->undoTransform([], new Context([])));
  }

  /**
   * Tests that a missing 'value' key in the data array returns NULL.
   *
   * @covers ::doUndoTransform
   */
  public function testUndoTransformWithMissingValueKeyReturnsNull(): void {
    $this->assertNull(
      $this->geoFieldEnhancer->undoTransform(['other_key' => 'POINT (0 0)'], new Context([]))
    );
  }

  /**
   * Tests that an empty string in the 'value' key returns NULL.
   *
   * @covers ::doUndoTransform
   */
  public function testUndoTransformWithEmptyStringValueReturnsNull(): void {
    $this->assertNull($this->geoFieldEnhancer->undoTransform(['value' => ''], new Context([])));
  }

  /**
   * Tests that a WKT string GeoPHP cannot parse returns NULL.
   *
   * @covers ::doUndoTransform
   */
  public function testUndoTransformWithUnparsableWktReturnsNull(): void {
    $this->assertNull(
      $this->geoFieldEnhancer->undoTransform(['value' => 'NOT_VALID_WKT'], new Context([]))
    );
  }

  /**
   * Tests GeoJSON-to-WKT conversion for the main geometry types.
   *
   * @covers ::doTransform
   * @dataProvider transformProvider
   */
  #[DataProvider('transformProvider')]
  public function testTransform(array $geojson, string $expectedWkt): void {
    $this->assertEquals($expectedWkt, $this->geoFieldEnhancer->transform($geojson));
  }

  /**
   * Tests that a raw GeoJSON string (not wrapped in an array) is accepted.
   *
   * @covers ::doTransform
   */
  public function testTransformAcceptsRawJsonString(): void {
    $this->assertEquals(
      'POINT (10 20)',
      $this->geoFieldEnhancer->transform('{"type":"Point","coordinates":[10,20]}')
    );
  }

  /**
   * Tests that an empty array returns NULL.
   *
   * @covers ::doTransform
   */
  public function testTransformWithEmptyDataReturnsNull(): void {
    $this->assertNull($this->geoFieldEnhancer->transform([]));
  }

  /**
   * Tests that GeoJSON whose 'type' property is not a string returns NULL.
   *
   * @covers ::doTransform
   */
  public function testTransformWithInvalidGeoJsonReturnsNull(): void {
    $this->assertNull($this->geoFieldEnhancer->transform(['type' => ['invalid']]));
  }

}
