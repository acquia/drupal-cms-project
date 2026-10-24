<?php

namespace Drupal\Tests\custom_field\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that HTML in allowed_values labels renders correctly.
 *
 * @group custom_field
 */
class HtmlLabelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'field',
    'node',
    'system',
    'user',
    'text',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'system', 'filter']);

    // Create a content type.
    NodeType::create([
      'type' => 'test_bundle',
      'name' => 'Test Bundle',
    ])->save();

    // Create a custom field with a string sub-field that has HTML labels.
    FieldStorageConfig::create([
      'field_name' => 'field_html_label_test',
      'entity_type' => 'node',
      'type' => 'custom',
      'settings' => [
        'columns' => [
          'status' => [
            'name' => 'status',
            'type' => 'string',
            'length' => 255,
          ],
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_html_label_test',
      'entity_type' => 'node',
      'bundle' => 'test_bundle',
      'label' => 'HTML Label Test',
      'settings' => [
        'field_settings' => [
          'status' => [
            'check_empty' => FALSE,
            'required' => FALSE,
            'translatable' => FALSE,
            'description' => '',
            'description_display' => 'after',
            'prefix' => '',
            'suffix' => '',
            'allowed_values' => [
              [
                'key' => 'active',
                'label' => '<em>Active</em> Status',
              ],
              [
                'key' => 'inactive',
                'label' => '<strong>Inactive</strong> Status',
              ],
            ],
          ],
        ],
      ],
    ])->save();

    // Create a view display with the custom_formatter.
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'test_bundle',
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'field_html_label_test' => [
          'type' => 'custom_formatter',
          'weight' => 0,
          'region' => 'content',
          'settings' => [
            'fields' => [
              'status' => [
                'weight' => 0,
                'format_type' => 'string',
                'formatter_settings' => [
                  'key_label' => 'label',
                  'label_display' => 'hidden',
                  'field_label' => 'status',
                  'prefix_suffix' => FALSE,
                ],
                'wrappers' => [
                  'field_wrapper_tag' => 'div',
                  'field_wrapper_classes' => '',
                  'field_tag' => 'div',
                  'field_classes' => '',
                  'label_tag' => 'h3',
                  'label_classes' => '',
                ],
              ],
            ],
          ],
          'label' => 'above',
          'third_party_settings' => [],
        ],
      ],
    ])->save();
  }

  /**
   * Tests that HTML in allowed_values labels is preserved in formatter output.
   */
  public function testHtmlInLabelsRendering(): void {
    // Create a node with the 'active' key.
    $node = Node::create([
      'type' => 'test_bundle',
      'title' => 'Test Node',
      'field_html_label_test' => [
        'status' => 'active',
      ],
    ]);
    $node->save();
    $node = Node::load($node->id());

    // Verify the value was saved correctly.
    $this->assertEquals('active', $node->field_html_label_test->status);

    // Render the node.
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $build = $view_builder->view($node, 'default');
    $output = (string) \Drupal::service('renderer')->renderRoot($build);

    // The HTML in the label should be preserved, not escaped.
    // If the bug exists, we'll see "&lt;em&gt;Active&lt;/em&gt;" instead.
    $this->assertStringContainsString('<em>Active</em> Status', $output, 'HTML in label should be rendered, not escaped.');
    $this->assertStringNotContainsString('&lt;em&gt;', $output, 'HTML should not be double-escaped.');
  }

  /**
   * Tests with strong tag in labels.
   */
  public function testStrongTagInLabels(): void {
    $node = Node::create([
      'type' => 'test_bundle',
      'title' => 'Test Node 2',
      'field_html_label_test' => [
        'status' => 'inactive',
      ],
    ]);
    $node->save();
    $node = Node::load($node->id());

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $build = $view_builder->view($node, 'default');
    $output = (string) \Drupal::service('renderer')->renderRoot($build);

    $this->assertStringContainsString('<strong>Inactive</strong> Status', $output, 'HTML strong tag in label should be rendered.');
    $this->assertStringNotContainsString('&lt;strong&gt;', $output, 'Strong tag should not be escaped.');
  }

}
