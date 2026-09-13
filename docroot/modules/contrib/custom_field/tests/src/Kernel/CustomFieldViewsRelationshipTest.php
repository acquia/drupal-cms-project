<?php

namespace Drupal\Tests\custom_field\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests custom field subfields accessed through Views relationships.
 *
 * @group custom_field
 */
class CustomFieldViewsRelationshipTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'field',
    'node',
    'user',
    'system',
    'file',
    'image',
    'link',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);

    // Create "school" node type with a custom field.
    NodeType::create([
      'type' => 'school',
      'name' => 'School',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_room',
      'entity_type' => 'node',
      'type' => 'custom',
      'settings' => [
        'columns' => [
          'a' => [
            'name' => 'a',
            'type' => 'string',
            'length' => 255,
          ],
          'b' => [
            'name' => 'b',
            'type' => 'string',
            'length' => 255,
          ],
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_room',
      'entity_type' => 'node',
      'bundle' => 'school',
      'label' => 'Room',
    ])->save();

    // Create "student" node type with entity_reference to "school".
    NodeType::create([
      'type' => 'student',
      'name' => 'Student',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_school_ref',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'node',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_school_ref',
      'entity_type' => 'node',
      'bundle' => 'student',
      'label' => 'School Reference',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['school' => 'school'],
        ],
      ],
    ])->save();

    // Clear views data cache so newly created fields are discovered.
    $this->container->get('views.views_data')->clear();
  }

  /**
   * Tests that custom field subfields work through a Views relationship.
   */
  public function testSubfieldsViaRelationship(): void {
    // Create a school with custom field values.
    $school = Node::create([
      'type' => 'school',
      'title' => 'Test School',
      'field_room' => [
        'a' => 'Room Alpha',
        'b' => 'Room Beta',
      ],
    ]);
    $school->save();

    // Create a student referencing the school.
    $student = Node::create([
      'type' => 'student',
      'title' => 'Test Student',
      'field_school_ref' => ['target_id' => $school->id()],
    ]);
    $student->save();

    // Create the test view programmatically.
    $this->createRelationshipTestView();
    $view = Views::getView('custom_field_relationship_test');
    $this->assertNotNull($view, 'View was created.');

    $view->execute();

    // The view should return one row (the student).
    $this->assertCount(1, $view->result, 'View returns one result row.');

    $row = $view->result[0];

    // The custom field subfields should be accessible through the
    // relationship and not be blank.
    $field_a = $view->field['field_room__a'];
    $items_a = $field_a->getItems($row);
    $this->assertNotEmpty($items_a, 'Subfield "a" should not be empty when accessed via relationship.');
    $this->assertEquals('Room Alpha', $items_a[0]['raw']);

    $field_b = $view->field['field_room__b'];
    $items_b = $field_b->getItems($row);
    $this->assertNotEmpty($items_b, 'Subfield "b" should not be empty when accessed via relationship.');
    $this->assertEquals('Room Beta', $items_b[0]['raw']);
  }

  /**
   * Creates the test view with a relationship.
   */
  private function createRelationshipTestView(): void {
    // First get the views data to find the correct table/field names.
    $views_data = $this->container->get('views.views_data');
    $ref_data = $views_data->get('node__field_school_ref');

    // Find the relationship field key.
    $relationship_field = NULL;
    foreach ($ref_data as $key => $info) {
      if (isset($info['relationship'])) {
        $relationship_field = $key;
        break;
      }
    }
    $this->assertNotNull($relationship_field, 'Entity reference relationship found in views data.');

    $view_config = [
      'id' => 'custom_field_relationship_test',
      'label' => 'Custom Field Relationship Test',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [
            'relationships' => [
              'field_school_ref' => [
                'id' => 'field_school_ref',
                'table' => 'node__field_school_ref',
                'field' => $relationship_field,
                'relationship' => 'none',
                'required' => TRUE,
                'plugin_id' => 'standard',
              ],
            ],
            'fields' => [
              'title' => [
                'id' => 'title',
                'table' => 'node_field_data',
                'field' => 'title',
                'relationship' => 'none',
                'plugin_id' => 'field',
              ],
              'field_room__a' => [
                'id' => 'field_room__a',
                'table' => 'node__field_room',
                'field' => 'field_room_a',
                'relationship' => 'field_school_ref',
                'plugin_id' => 'custom_field',
              ],
              'field_room__b' => [
                'id' => 'field_room__b',
                'table' => 'node__field_room',
                'field' => 'field_room_b',
                'relationship' => 'field_school_ref',
                'plugin_id' => 'custom_field',
              ],
            ],
            'filters' => [
              'type' => [
                'id' => 'type',
                'table' => 'node_field_data',
                'field' => 'type',
                'value' => ['student' => 'student'],
                'plugin_id' => 'bundle',
                'entity_type' => 'node',
              ],
            ],
          ],
        ],
      ],
    ];

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('view');
    $view = $storage->create($view_config);
    $view->save();
  }

}
