<?php

declare(strict_types=1);

namespace Drupal\Tests\geofield\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the config schema of views that use the proximity handlers.
 *
 * The proximity field, filter and sort store the same units, source and
 * source configuration options, so a view using all three must validate.
 *
 * @group geofield
 */
#[Group('geofield')]
class ProximityViewsSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'geofield',
    'system',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');

    FieldStorageConfig::create([
      'field_name' => 'field_geo',
      'entity_type' => 'entity_test',
      'type' => 'geofield',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_geo',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests that the proximity handlers' options all have schema.
   *
   * Saving the view is itself the first assertion: kernel tests check config
   * schema strictly on every save.
   */
  public function testProximityHandlerSchema(): void {
    $handler = [
      'id' => 'field_geo_proximity',
      'table' => 'entity_test__field_geo',
      'field' => 'field_geo_proximity',
      'units' => 'GEOFIELD_KILOMETERS',
      'source' => 'geofield_manual_origin',
      'source_configuration' => [
        'origin' => [
          'lat' => '38.25',
          'lon' => '-122.5',
        ],
        'origin_hidden_flag' => FALSE,
        'origin_summary_flag' => TRUE,
      ],
    ];

    $filter = $handler;
    $filter['plugin_id'] = 'geofield_proximity_filter';
    $filter['operator'] = '<=';
    $filter['value'] = [
      'value' => 100,
      'min' => 0,
      'max' => 0,
    ];
    $filter['source_configuration']['exposed_summary'] = FALSE;

    View::create([
      'id' => 'test_geofield_proximity',
      'label' => 'Geofield proximity',
      'base_table' => 'entity_test',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'fields' => [
              'field_geo_proximity' => ['plugin_id' => 'geofield_proximity_field'] + $handler,
            ],
            'sorts' => [
              'field_geo_proximity' => ['plugin_id' => 'geofield_proximity_sort', 'order' => 'ASC'] + $handler,
            ],
            'filters' => [
              'field_geo_proximity' => $filter,
            ],
          ],
        ],
      ],
    ])->save();

    $this->assertConfigSchemaByName('views.view.test_geofield_proximity');
  }

}
