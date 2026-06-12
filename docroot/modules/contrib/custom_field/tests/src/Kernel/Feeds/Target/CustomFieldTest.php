<?php

namespace Drupal\Tests\custom_field\Kernel\Feeds\Target;

use Drupal\Tests\feeds\Kernel\FeedsKernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Tests for mapping to custom_field fields.
 *
 * @group custom_field
 */
class CustomFieldTest extends FeedsKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var String[]
   */
  protected static $modules = [
    'field',
    'file',
    'node',
    'custom_field',
    'custom_field_viewfield',
    'custom_field_test',
    'feeds',
    'system',
    'user',
    'image',
    'views',
  ];

  /**
   * The feed type to test with.
   *
   * @var \Drupal\feeds\FeedTypeInterface
   */
  protected $feedType;

  /**
   * The CustomFieldTypeManager service.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   */
  protected $customFieldTypeManager;

  /**
   * The custom field feeds manager service.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldFeedsManagerInterface
   */
  protected $feedsManager;

  /**
   * The entity type for testing.
   *
   * @var string
   */
  protected string $entityTypeId;

  /**
   * The field name for testing.
   *
   * @var string
   */
  protected string $fieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Define the entity type and field names from the provided configuration.
    $this->entityTypeId = 'node';
    $bundle = 'custom_field_entity_test';
    $this->fieldName = 'field_test';

    $this->installConfig([
      'custom_field_test',
      'file',
    ]);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['custom_field', 'custom_field_test']);

    // Get the services required for testing.
    $this->customFieldTypeManager = $this->container->get('plugin.manager.custom_field_type');
    $this->feedsManager = $this->container->get('plugin.manager.custom_field_feeds');
    $fieldStorageConfig = FieldStorageConfig::loadByName($this->entityTypeId, $this->fieldName);
    $settings = $fieldStorageConfig->getSettings();
    $custom_items = $this->customFieldTypeManager->getCustomFieldItems($settings);

    // Create and configure feed type.
    $sources = [
      'title' => 'title',
    ];

    $mappings = [
      [
        'target' => 'title',
        'map' => ['value' => 'title'],
        'settings' => [
          'language' => NULL,
        ],
      ],
      [
        'target' => 'feeds_item',
        'map' => [
          'url' => '',
          'guid' => 'guid',
        ],
      ],
    ];

    $custom_field_map = [
      'target' => $this->fieldName,
      'map' => [],
    ];
    foreach ($custom_items as $name => $custom_item) {
      $sources[$name] = $name;
      $custom_field_map['map'][$name] = $name;
      if (in_array($custom_item->getDataType(), ['daterange', 'time_range'])) {
        $end = $name . '__end';
        $sources[$end] = $end;
        $custom_field_map['map'][$end] = $end;
      }
    }

    $mappings[] = $custom_field_map;

    $this->feedType = $this->createFeedTypeForCsv(
      $sources,
      [
        'mappings' => $mappings,
        'processor_configuration' => [
          'authorize' => FALSE,
          'values' => [
            'type' => $bundle,
          ],
        ],
      ],
    );
  }

  /**
   * Basic test loading a CSV file.
   *
   * @throws \Exception
   */
  public function test(): void {
    // Import CSV file.
    $feed = $this->createFeed($this->feedType->id(), [
      'source' => $this->resourcesPath() . '/csv/content.csv',
    ]);
    $feed->import();
    $this->assertNodeCount(3);
    $expected_values = [
      1 => [
        'string' => 'String 1',
        'string_long' => 'Long string 1',
        'integer' => '42',
        'decimal' => '3.14',
        'float' => '2.718',
        'email' => 'test@example.com',
        'telephone' => '+1234567890',
        'uri' => 'http://www.example.com',
        'link' => 'http://www.example.com',
        'boolean' => '1',
        'color' => '#FFA500',
        'map' => [
          [
            'key' => 'key1',
            'value' => 'value1',
          ],
          [
            'key' => 'key2',
            'value' => 'value2',
          ],
        ],
        'map_string' => [
          'value1',
          'value2',
          'value3',
        ],
        'datetime' => '2023-01-01T00:00:00',
        'daterange' => '2023-01-01T00:00:00',
        'daterange__end' => '2024-01-01T00:00:00',
        'time' => '24205',
        'time_range' => '24205',
        'time_range__end' => '27805',
      ],
      2 => [
        'string' => 'String 2',
        'string_long' => 'Long string 2',
        'integer' => NULL,
        'decimal' => '-1.62',
        'float' => '0.5778',
        'email' => NULL,
        'telephone' => '-9876543210',
        'uri' => 'internal:/',
        'link' => 'internal:/',
        'boolean' => '1',
        'color' => NULL,
        'map' => NULL,
        'map_string' => NULL,
        'datetime' => '2009-09-03T00:12:00',
        'daterange' => '2009-09-03T00:12:00',
        'daterange__end' => '2009-12-27T17:58:40',
        'time' => '45000',
        'time_range' => '45000',
        'time_range__end' => '48600',
      ],
      3 => [
        'string' => 'String 3',
        'string_long' => NULL,
        'integer' => '1234',
        'decimal' => '1.62',
        'float' => '0.577',
        'email' => NULL,
        'telephone' => NULL,
        'uri' => 'route:<nolink>',
        'link' => 'route:<nolink>',
        'boolean' => '1',
        'color' => '#FFFFFF',
        'map' => NULL,
        'map_string' => NULL,
        'datetime' => '2018-02-09T00:00:00',
        'daterange' => '2018-02-09T00:00:00',
        'daterange__end' => '2019-02-09T00:00:00',
        'time' => '34220',
        'time_range' => '34220',
        'time_range__end' => '37820',
      ],
    ];
    foreach ($expected_values as $nid => $data) {
      $node = Node::load($nid);
      $field_values = $node->get($this->fieldName)->first()->getValue();
      $this->assertNotEmpty($field_values, 'The field value is not empty');
      foreach ($data as $name => $data_value) {
        $this->assertSame($data_value, $field_values[$name], 'The expected value is the same as the saved value.');
      }
    }
    // Check if mappings can be unique.
    $unique_types = [
      'string',
      'string_long',
      'integer',
      'decimal',
      'email',
      'uri',
      'link',
      'telephone',
    ];
    $unique_count = count($unique_types);
    $mappings = $this->feedType->getMappings();
    $mappings[1]['unique'] = $unique_types;
    $this->feedType->setMappings($mappings);
    $this->feedType->save();
    $updated_mappings = $this->feedType->getMappings();
    $this->assertCount($unique_count, $updated_mappings[1]['unique'], 'The count of expected unique types is accurate.');
  }

  /**
   * Overrides the absolute directory path of the Feeds module.
   *
   * @return string
   *   The absolute path to the custom_field module.
   */
  protected function absolutePath(): string {
    return $this->absolute() . '/' . $this->getModulePath('custom_field');
  }

}
