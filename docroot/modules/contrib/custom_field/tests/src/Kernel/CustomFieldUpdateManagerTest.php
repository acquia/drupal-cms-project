<?php

namespace Drupal\Tests\custom_field\Kernel;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Test the CustomFieldUpdateManager service.
 *
 * @group custom_field
 */
class CustomFieldUpdateManagerTest extends KernelTestBase {

  use UserCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'node',
    'views',
    'custom_field',
    'custom_field_viewfield',
    'custom_field_test',
    'user',
    'path',
    'file',
    'image',
  ];

  /**
   * The CustomFieldUpdateManager service.
   *
   * @var \Drupal\custom_field\CustomFieldUpdateManager
   */
  protected $customFieldUpdateManager;

  /**
   * The current user object.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $currentUser;

  /**
   * The custom field data generator.
   *
   * @var \Drupal\custom_field\CustomFieldGenerateDataInterface
   */
  protected $customFieldGenerator;

  /**
   * The entity type.
   *
   * @var string
   */
  protected string $entityTypeId = 'node';

  /**
   * The bundle type.
   *
   * @var string
   */
  protected string $bundle = 'custom_field_entity_test';

  /**
   * The field name.
   *
   * @var string
   */
  protected string $fieldName = 'field_test';

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['custom_field', 'custom_field_test']);

    // Create and log in a test user with necessary permissions.
    $this->createUser([
      'create ' . $this->bundle . ' content',
      'edit own ' . $this->bundle . ' content',
    ], 'test_user');

    // Get the services required for testing.
    $this->customFieldUpdateManager = $this->container->get('custom_field.update_manager');
    $this->customFieldGenerator = $this->container->get('custom_field.generate_data');
  }

  /**
   * Sets up field storage and creates a test node.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUpFieldAndNode(): NodeInterface {
    $fieldStorageConfig = FieldStorageConfig::loadByName($this->entityTypeId, $this->fieldName);
    $columns = $fieldStorageConfig->getSetting('columns');
    $fieldStorageConfig->setSetting('columns', $columns)->save();

    $fieldConfig = FieldConfig::loadByName($this->entityTypeId, $this->bundle, $this->fieldName);
    $settings = $fieldConfig->getSettings();
    $targetEntityType = $fieldConfig->getTargetEntityTypeId();

    $node = $this->createNode([
      'type' => $this->bundle,
      'title' => 'Test Node',
      $this->fieldName => $this->customFieldGenerator->generateFieldData($settings, $targetEntityType),
      'langcode' => 'en',
    ]);
    $node->save();

    return $node;
  }

  /**
   * Asserts that a node and its field are correctly set up.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to verify.
   */
  protected function assertNodeAndField(NodeInterface $node): void {
    $this->assertInstanceOf(FieldItemListInterface::class, $node->{$this->fieldName});
    $this->assertInstanceOf(FieldItemInterface::class, $node->{$this->fieldName}[0]);
  }

  /**
   * Test the addColumn method.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAddColumn(): void {
    $node = $this->setUpFieldAndNode();
    $this->assertNodeAndField($node);
    $id = $node->id();

    // Call the addColumn method.
    $this->customFieldUpdateManager->addColumn(
      $this->entityTypeId,
      $this->fieldName,
      'new_property',
      'string',
      ['length' => 255],
    );

    // Call the addColumn method with a map data type.
    $this->customFieldUpdateManager->addColumn(
      $this->entityTypeId,
      $this->fieldName,
      'new_map',
      'map',
    );

    // Call the addColumn method with a complex field.
    $this->customFieldUpdateManager->addColumn(
      $this->entityTypeId,
      $this->fieldName,
      'new_daterange',
      'daterange',
      ['datetime_type' => 'date'],
    );

    // Perform assertions to verify that the column was added successfully.
    $storage = FieldStorageConfig::loadByName($this->entityTypeId, $this->fieldName);
    $this->assertNotNull($storage, 'The field storage configuration exists.');
    $this->assertEquals('custom', $storage->getType(), 'The field storage type is "custom".');

    // Verify a new column has been added to the field storage configuration.
    $columns = $storage->getSetting('columns');
    $this->assertArrayHasKey('new_property', $columns, 'The new property is added to the columns settings.');
    $this->assertArrayHasKey('new_map', $columns, 'The new map property is added to the columns settings.');
    $this->assertArrayHasKey('new_daterange', $columns, 'The new property is added to the columns settings.');
    $this->assertEquals('string', $columns['new_property']['type'], 'The new property has the correct data type.');
    $this->assertEquals('map', $columns['new_map']['type'], 'The new map property has the correct data type.');
    $this->assertEquals('daterange', $columns['new_daterange']['type'], 'The new property has the correct data type.');

    // Verify no data loss resulted in adding the column.
    $node->save();
    $node = Node::load($id);
    $field_value = $node->get($this->fieldName)->getValue();
    $this->assertNotEmpty($field_value, 'The field value is not empty.');

    // Perform assertions to verify that the column was added successfully.
    $storage = FieldStorageConfig::loadByName($this->entityTypeId, $this->fieldName);
    $this->assertNotNull($storage, 'The field storage configuration exists.');
    $this->assertEquals('custom', $storage->getType(), 'The field storage type is "custom".');

    // Call the addColumn method with a non-existent field.
    $no_exist = 'non_existent_field';
    try {
      $this->customFieldUpdateManager->addColumn(
        $this->entityTypeId,
        $no_exist,
        'new_string',
        'string',
      );
      $this->fail('An exception should have been thrown for a non-existent field.');
    }
    catch (\Exception $e) {
      // Assert that the exception message contains the field name.
      $this->assertStringContainsString($no_exist, $e->getMessage(), 'Exception message contains the non-existent field name.');
    }
  }

  /**
   * Test the removeColumn method.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\Sql\SqlContentEntityStorageException
   */
  public function testRemoveColumn(): void {
    $node = $this->setUpFieldAndNode();
    $this->assertNodeAndField($node);
    $id = $node->id();

    $storage = FieldStorageConfig::loadByName($this->entityTypeId, $this->fieldName);

    // Iterate through each column in the field settings and remove it.
    $columns = $storage->getSetting('columns');
    // Remove last item from array to test not removing all columns.
    $last = [array_key_last($columns) => array_pop($columns)];
    $last_column = key($last);
    foreach ($columns as $column_name => $columnSettings) {
      // Call the removeColumn method.
      $this->customFieldUpdateManager->removeColumn($this->entityTypeId, $this->fieldName, $column_name);
      // Perform assertions to verify that the column was removed successfully.
      $storage = FieldStorageConfig::loadByName($this->entityTypeId, $this->fieldName);
      $columns = $storage->getSetting('columns');
      $this->assertArrayNotHasKey($column_name, $columns, 'The column "' . $column_name . '" is removed from the columns settings.');
    }

    // Verify no data loss resulted in removing the column.
    $node->save();
    $node = Node::load($id);
    $field_value = $node->get($this->fieldName)->getValue();
    $this->assertNotEmpty($field_value, 'The field value is not empty.');

    // Reload columns and verify the new count.
    $columns = $storage->getSetting('columns');
    $this->assertCount(1, $columns, 'The column count is 1.');

    // Try to remove the last column.
    try {
      $this->customFieldUpdateManager->removeColumn($this->entityTypeId, $this->fieldName, $last_column);
      $this->fail('An exception should have been thrown for removing the only field.');
    }
    catch (\Exception $e) {
      // Assert that the exception message contains the field name.
      $this->assertStringContainsString($last_column, $e->getMessage(), 'Exception message contains the last field name.');
    }

    // Verify the last remaining item still exists.
    $columns = $storage->getSetting('columns');
    $this->assertArrayHasKey($last_column, $columns, 'The column "' . $last_column . '" still exists in the columns settings.');
  }

}
