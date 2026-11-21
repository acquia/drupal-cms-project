<?php

namespace Drupal\Tests\custom_field\Kernel;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeTypeInterface;
use Drupal\file\Entity\File;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Tests the custom field type.
 *
 * @group custom_field
 */
class CustomFieldItemTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'custom_field_viewfield',
    'custom_field_test',
    'field',
    'node',
    'path',
    'path_alias',
    'system',
    'user',
    'file',
    'image',
    'views',
  ];

  /**
   * A field storage to use in this test class.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  protected $fieldStorage;

  /**
   * The field used in this test class.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $field;

  /**
   * The custom fields on the test entity bundle.
   *
   * @var array|\Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected array $fields = [];

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The CustomFieldTypeManager service.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   */
  protected $customFieldTypeManager;

  /**
   * The FieldTypeTestManager service.
   *
   * @var \Drupal\custom_field_test\PluginManager\FieldTypeTestManagerInterface
   */
  protected $fieldTypeTestManager;

  /**
   * The CustomFieldWidgetManager service.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldWidgetManagerInterface
   */
  protected $customFieldWidgetManager;

  /**
   * The CustomFieldFormatterManager service.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldFormatterManagerInterface
   */
  protected $customFieldFormatterManager;

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The entity type id.
   *
   * @var string
   */
  protected string $entityType;

  /**
   * The bundle type.
   *
   * @var string
   */
  protected string $bundle;

  /**
   * The field name.
   *
   * @var string
   */
  protected string $fieldName;

  /**
   * Created file entities.
   *
   * @var \Drupal\file\Entity\File[]
   */
  protected $files;

  /**
   * Created node entities.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installConfig([
      'system',
      'custom_field_test',
      'node',
      'field',
      'user',
      'file',
      'image',
      'views',
    ]);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);

    // Get the services required for testing.
    $this->customFieldTypeManager = $this->container->get('plugin.manager.custom_field_type');
    $this->customFieldWidgetManager = $this->container->get('plugin.manager.custom_field_widget');
    $this->customFieldFormatterManager = $this->container->get('plugin.manager.custom_field_formatter');
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->imageFactory = $this->container->get('image.factory');

    // Get the testing services.
    $this->fieldTypeTestManager = $this->container->get('plugin.manager.custom_field_type_test');

    $this->entityType = 'node';
    $this->bundle = 'custom_field_entity_test';
    $this->fieldName = 'field_test';
    $this->fields = $this->entityFieldManager->getFieldDefinitions('node', 'custom_field_entity_test');
    $this->field = $this->fields[$this->fieldName];
    $this->fieldStorage = FieldStorageConfig::loadByName($this->entityType, $this->fieldName);
    $file_urls = [
      'public://example.txt',
      'public://example2.txt',
    ];

    foreach ($file_urls as $file_url) {
      file_put_contents($file_url, $this->randomMachineName());
      $file = File::create([
        'uri' => $file_url,
      ]);
      $file->save();
      $this->files[$file->id()] = $file;
    }

    $image_urls = [
      'public://example.jpg',
      'public://example-2.jpg',
    ];
    foreach ($image_urls as $image_url) {
      \Drupal::service('file_system')->copy($this->root . '/core/misc/druplicon.png', $image_url);
      $image = File::create([
        'uri' => $image_url,
      ]);
      $image->save();
      $this->files[$image->id()] = $image;
    }

    // Set up article nodes for reference.
    foreach (['Article 1', 'Article 2', 'Article 3'] as $title) {
      $node = Node::create([
        'type' => 'article',
        'title' => $title,
      ]);
      $node->save();
      $this->nodes[$node->id()] = $node;
    }
  }

  /**
   * Test that all field types have a corresponding test plugin.
   */
  public function testFieldTypePlugins(): void {
    $field_types = $this->customFieldTypeManager->getDefinitions();

    // Assert there is a test plugin for every field type.
    foreach ($field_types as $id => $field_type) {
      $field_plugin = NULL;
      $test_plugin = NULL;
      try {
        /** @var \Drupal\custom_field\Plugin\CustomFieldTypeInterface $field_plugin */
        $field_plugin = $this->customFieldTypeManager->createInstance($id);
        /** @var \Drupal\custom_field_test\Plugin\FieldTypeTestInterface $test_plugin */
        $test_plugin = $this->fieldTypeTestManager->createInstance($id);
      }
      catch (\Exception $e) {
        // No test plugin exists.
      }
      // Assert the field plugin instance gets created.
      $this->assertNotNull($field_plugin, sprintf('The field plugin for "%s" exists', $id));
      // Assert there is a test plugin for each field type.
      $this->assertNotNull($test_plugin, sprintf('The test plugin for "%s" exists', $id));

      $default_widget = $field_plugin->getDefaultWidget();
      $default_formatter = $field_plugin->getDefaultFormatter();
      $test_default_widget = $test_plugin->getDefaultWidget();
      $test_default_formatter = $test_plugin->getDefaultFormatter();
      $widget_plugin = NULL;
      $formatter_plugin = NULL;

      // Assert the expected default widget id for the field type plugin.
      $this->assertEquals($default_widget, $test_default_widget['id'], 'The default widget id is equal to the expected widget id.');

      // Assert the expected default formatter id for the field type plugin.
      $this->assertEquals($default_formatter, $test_default_formatter['id'], 'The default formatter is equal to the expected formatter.');

      try {
        /** @var \Drupal\custom_field\Plugin\CustomFieldWidgetManager $widget_plugin */
        $widget_plugin = $this->customFieldWidgetManager->createInstance($default_widget);
        /** @var \Drupal\custom_field\Plugin\CustomFieldFormatterManager $formatter_plugin */
        $formatter_plugin = $this->customFieldFormatterManager->createInstance($default_formatter);
      }
      catch (\Exception $e) {
        // Plugin exception.
      }
      // Assert the expected default widget class for the field type plugin.
      $this->assertTrue($widget_plugin instanceof $test_default_widget['class'], sprintf('The default widget class "%s" is equal to the expected widget class "%s".', $default_widget, $test_default_widget['class']));

      // Assert the expected default formatter class for the field type plugin.
      $this->assertTrue($formatter_plugin instanceof $test_default_formatter['class'], sprintf('The default formatter class "%s" is equal to the expected formatter class "%s".', $default_formatter, $test_default_formatter['class']));
    }
  }

  /**
   * Tests using entity fields of the custom field type.
   */
  public function testCustomFieldItem(): void {
    // Perform assertions to verify that the storage was added successfully.
    $this->assertNotNull($this->fieldStorage, 'The field storage configuration exists.');
    $settings = $this->field->getSettings();
    $field_settings = $settings['field_settings'];
    $custom_items = $this->customFieldTypeManager->getCustomFieldItems($settings);

    // Create an entity.
    $entity = Node::create([
      'title' => 'Test node title',
      'type' => $this->bundle,
    ]);
    $test_cases = [];
    foreach ($custom_items as $name => $custom_item) {
      /** @var \Drupal\custom_field_test\Plugin\FieldTypeTestInterface $test_plugin */
      $test_plugin = $this->fieldTypeTestManager->createInstance($name);
      $plugin_test_cases = $test_plugin->testCases($name, $field_settings[$name] ?? []);
      if (empty($plugin_test_cases)) {
        trigger_error(sprintf('The custom field type "%s" is missing test cases.', $name), E_USER_WARNING);
      }
      $delta = 0;
      foreach ($plugin_test_cases as $plugin_test_case) {
        $test_cases[$delta][$name] = $plugin_test_case;
        $delta++;
      }
    }
    foreach ($test_cases as $test_fields) {
      $values = [];
      $extra_property_fields = [
        'datetime',
        'daterange',
        'entity_reference',
        'image',
        'link',
        'time_range',
        'viewfield',
      ];
      foreach ($test_fields as $field_name => $test_field) {
        [$property, $value, $is_violation, $expected_message, $new_settings] = $test_field;
        if (!empty($new_settings)) {
          $field_settings[$field_name] = $new_settings;
          $this->field->setSetting('field_settings', $field_settings);
        }
        if ($is_violation) {
          if (in_array($field_name, $extra_property_fields) && is_array($property)) {
            foreach ($value as $property_name => $property_value) {
              $entity->{$this->fieldName}->{$property_name} = $property_value;
            }
            $violations = $entity->validate();
            $this->assertCount(1, $violations);
            $violation = $violations[0];
            $path = explode('.', $violation->getPropertyPath());
            $end_path = end($path);
            $message = strip_tags((string) $violation->getMessage());
            // Assert the expected violation matches the property name.
            $this->assertArrayHasKey($end_path, $value);
            // Assert the expected violation message matches the actual message.
            $this->assertEquals($expected_message, $message, 'The violation message matches the expected message.');
            $entity->{$this->fieldName}->{$end_path} = NULL;
          }
          else {
            $entity->{$this->fieldName}->{$property} = $value;
            $violations = $entity->validate();
            // Why does decimal '20-40' test trigger 2 violations?
            $expected_count = $field_name === 'decimal' ? 2 : 1;
            $this->assertCount($expected_count, $violations);
            $violation = $violations[0];
            $path = explode('.', $violation->getPropertyPath());
            $message = strip_tags((string) $violation->getMessage());
            // Assert the expected violation matches the property name.
            $this->assertEquals(end($path), $property);
            // Assert the expected violation message matches the actual message.
            $this->assertEquals($expected_message, $message, 'The violation message matches the expected message.');
            $entity->{$this->fieldName}->{$property} = NULL;
          }
        }
        else {
          $violations = $entity->validate();
          $this->assertCount(0, $violations, 'No violations are expected.');
          // Account for fields with extra properties.
          if (in_array($field_name, $extra_property_fields) && is_array($property)) {
            foreach ($property as $property_name) {
              if (is_array($value) && isset($value[$property_name])) {
                $values[$property_name] = $value[$property_name];
              }
            }
          }
          else {
            $values[$property] = $value;
          }
        }
      }
      if (!empty($values)) {
        $entity->set($this->fieldName, [$values]);
        $entity->save();
        $id = $entity->id();
        $entity = Node::load($id);
        // Verify entity has been created properly.
        $this->assertInstanceOf(FieldItemListInterface::class, $entity->{$this->fieldName});
        $this->assertInstanceOf(FieldItemInterface::class, $entity->{$this->fieldName}[0]);
        $image_values = [];
        $link_values = [];
        $viewfield_values = [];
        $datetime_values = [];
        $daterange_values = [];
        $time_range_values = [];
        foreach ($values as $property_name => $value) {
          // Link fields are special case.
          if (str_starts_with($property_name, 'link')) {
            $link_values[$property_name] = $value;
            continue;
          }
          // Viewfield fields are special case.
          if (str_starts_with($property_name, 'viewfield')) {
            $viewfield_values[$property_name] = $value;
            continue;
          }
          // Datetime fields are special case.
          if (str_starts_with($property_name, 'datetime')) {
            $datetime_values[$property_name] = $value;
            continue;
          }
          // Datetime fields are special case.
          if (str_starts_with($property_name, 'daterange')) {
            $daterange_values[$property_name] = $value;
            continue;
          }
          // Image fields are special case.
          if (str_starts_with($property_name, 'image')) {
            $image_values[$property_name] = $value;
            continue;
          }
          if (str_starts_with($property_name, 'time_range')) {
            $time_range_values[$property_name] = $value;
            continue;
          }
          // Color fields get saved in uppercase with the # prefix.
          if ($property_name === 'color' && !is_null($value)) {
            $value = strtoupper($value);
            if (!str_starts_with($value, '#')) {
              $value = '#' . $value;
            }
          }
          // Cast decimal values to float to ensure accurate comparison.
          if ($property_name === 'decimal' && is_string($value)) {
            $value = (float) $value;
          }
          $this->assertEquals($value, $entity->{$this->fieldName}->{$property_name});
          $this->assertEquals($value, $entity->{$this->fieldName}[0]->{$property_name});
          if ($property_name === 'file') {
            $this->assertArrayHasKey($value, $this->files, sprintf('The file ID %d is in the files array.', $value));
            $this->assertEquals($entity->{$this->fieldName}->{$property_name . '__entity'}->id(), $this->files[$value]->id());
            $this->assertEquals($entity->{$this->fieldName}->{$property_name . '__entity'}->getFileUri(), $this->files[$value]->getFileUri());
          }
          if ($property_name === 'entity_reference') {
            $this->assertArrayHasKey($value, $this->nodes, sprintf('The node ID %d is in the nodes array.', $value));
            $this->assertEquals($entity->{$this->fieldName}->{$property_name . '__entity'}->id(), $this->nodes[$value]->id());
            $this->assertEquals($entity->{$this->fieldName}->{$property_name . '__entity'}->getTitle(), $this->nodes[$value]->getTitle());
          }
        }

        // Assertions for 'image' field type.
        if (!empty($image_values)) {
          if (!array_key_exists('image', $image_values)) {
            // Assert that extra properties data are empty.
            foreach ($image_values as $property_name => $value) {
              if (in_array($property_name, ['image__alt', 'image__title', 'image__width', 'image__height'])) {
                $this->assertNull($entity->{$this->fieldName}->{$property_name});
              }
            }
          }
          else {
            foreach ($image_values as $property_name => $value) {
              if ($property_name === 'image') {
                $image_url = $this->files[$value]?->getFileUri();
                $image = $this->imageFactory->get($image_url);
                $this->assertArrayHasKey($value, $this->files, sprintf('The file ID %d is in the images array.', $value));
                $this->assertEquals($value, $entity->{$this->fieldName}->{$property_name});
                $this->assertEquals($value, $entity->{$this->fieldName}[0]->{$property_name});
                $this->assertEquals($entity->{$this->fieldName}->{$property_name . '__entity'}?->id(), $this->files[$value]?->id(), 'The image entity ID matches the expected image entity ID.');
                $this->assertEquals($entity->{$this->fieldName}->{$property_name . '__entity'}?->uuid(), $this->files[$value]?->uuid());
                $this->assertEquals($entity->{$this->fieldName}->{$property_name . '__entity'}?->getFileUri(), $this->files[$value]?->getFileUri());
                $this->assertEquals($image->getWidth(), $entity->{$this->fieldName}->{$property_name . '__width'});
                $this->assertEquals($image->getHeight(), $entity->{$this->fieldName}->{$property_name . '__height'});
              }
              elseif (in_array($property_name, ['image__alt', 'image__title'])) {
                $this->assertEquals($value, $entity->{$this->fieldName}->{$property_name});
              }
            }
          }
        }
        // Assertions for 'link' field types.
        if (!empty($link_values)) {
          if (!array_key_exists('link', $link_values)) {
            // Assert that extra properties data are empty.
            foreach ($link_values as $property_name => $value) {
              if ($property_name === 'link__options') {
                $this->assertSame([], $entity->{$this->fieldName}->{$property_name});
              }
              if ($property_name === 'link__title') {
                $this->assertNull($entity->{$this->fieldName}->{$property_name});
              }
            }
          }
          else {
            foreach ($link_values as $property_name => $value) {
              if ($property_name === 'link' || $property_name === 'link__title') {
                $this->assertEquals($value, $entity->{$this->fieldName}->{$property_name});
                $this->assertEquals($value, $entity->{$this->fieldName}[0]->{$property_name});
              }
            }
            // Assert the link title is NULL.
            if (!isset($link_values['link__title'])) {
              $this->assertNull($entity->{$this->fieldName}->link__title);
            }
            // Assert the link options are empty.
            elseif (!isset($link_values['link__options'])) {
              $this->assertSame([], $entity->{$this->fieldName}->link__options);
            }
          }
        }
        // Assertions for 'viewfield' type.
        if (!empty($viewfield_values)) {
          if (!array_key_exists('viewfield', $viewfield_values)) {
            foreach ($viewfield_values as $property_name => $value) {
              $this->assertNull($entity->{$this->fieldName}->{$property_name}, "The 'viewfield' extra properties are NULL when main property is NULL.");
            }
          }
          else {
            foreach ($viewfield_values as $property_name => $value) {
              $this->assertEquals($value, $entity->{$this->fieldName}->{$property_name});
              $this->assertEquals($value, $entity->{$this->fieldName}[0]->{$property_name});
            }
          }
        }
        // Assertions for 'datetime' type.
        if (!empty($datetime_values)) {
          if (!array_key_exists('datetime', $datetime_values)) {
            foreach ($datetime_values as $property_name => $value) {
              $this->assertNull($entity->{$this->fieldName}->{$property_name}, 'Extra properties are NULL when main property is NULL.');
            }
          }
          else {
            foreach ($datetime_values as $property_name => $value) {
              $this->assertEquals($value, $entity->{$this->fieldName}->{$property_name});
              $this->assertEquals($value, $entity->{$this->fieldName}[0]->{$property_name});
              $this->assertEquals(DateTimeTypeInterface::STORAGE_TIMEZONE, $entity->{$this->fieldName}[0]->getProperties()['datetime']->getDateTime()->getTimeZone()->getName());
              $this->assertEquals(DateTimeTypeInterface::STORAGE_TIMEZONE, $entity->{$this->fieldName}->{'datetime__date'}->getTimeZone()->getName());
            }
          }
        }
        // Assertions for 'daterange' type.
        if (!empty($daterange_values)) {
          if (!array_key_exists('daterange', $daterange_values)) {
            foreach ($daterange_values as $property_name => $value) {
              $this->assertNull($entity->{$this->fieldName}->{$property_name}, 'Extra properties are NULL when main property is NULL.');
            }
          }
          else {
            $start_date = $entity->{$this->fieldName}->{'daterange__start_date'};
            $this->assertEquals(DateTimeTypeInterface::STORAGE_TIMEZONE, $start_date->getTimeZone()->getName());
            foreach ($daterange_values as $property_name => $value) {
              $this->assertEquals($value, $entity->{$this->fieldName}->{$property_name});
              $this->assertEquals($value, $entity->{$this->fieldName}[0]->{$property_name});
              if ($property_name === 'daterange__end') {
                $end_date = $entity->{$this->fieldName}->{'daterange__end_date'};
                $this->assertEquals(DateTimeTypeInterface::STORAGE_TIMEZONE, $end_date->getTimeZone()->getName());
              }
            }
          }
        }
        // Assertions for 'time_range' type.
        if (!empty($time_range_values)) {
          if (!array_key_exists('time_range', $time_range_values)) {
            foreach ($time_range_values as $property_name => $value) {
              $this->assertNull($entity->{$this->fieldName}->{$property_name}, 'Extra properties are NULL when main property is NULL.');
            }
          }
          else {
            foreach ($time_range_values as $property_name => $value) {
              $this->assertEquals($value, $entity->{$this->fieldName}->{$property_name});
              $this->assertEquals($value, $entity->{$this->fieldName}[0]->{$property_name});
            }
          }
        }
      }
    }

    // Test sample item generation.
    $entity = Node::create([
      'title' => 'Test node title',
      'type' => $this->bundle,
    ]);
    $entity->{$this->fieldName}->generateSampleItems();
    $this->entityValidateAndSave($entity);
  }

  /**
   * Tests using the datetime_type of 'date'.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDateOnly(): void {
    $columns = $this->fieldStorage->getSetting('columns');
    $columns['datetime']['datetime_type'] = 'date';
    $columns['daterange']['datetime_type'] = 'date';
    $this->fieldStorage->setSetting('columns', $columns);
    $this->fieldStorage->save();

    // Create an entity.
    $entity = Node::create([
      'title' => 'Test node title',
      'type' => $this->bundle,
    ]);
    $date = '2014-01-01';
    $date_range = [
      'start' => $date,
      'end' => '2015-02-02',
    ];
    $entity->{$this->fieldName}->datetime = $date;
    $entity->{$this->fieldName}->daterange = $date_range['start'];
    $entity->{$this->fieldName}->{'daterange__end'} = $date_range['end'];
    $this->entityValidateAndSave($entity);

    // Verify entity has been created properly.
    $id = $entity->id();
    $entity = Node::load($id);
    $this->assertInstanceOf(FieldItemListInterface::class, $entity->{$this->fieldName});
    $this->assertInstanceOf(FieldItemInterface::class, $entity->{$this->fieldName}[0]);
    $this->assertEquals($date, $entity->{$this->fieldName}->datetime);
    $this->assertEquals($date, $entity->{$this->fieldName}[0]->datetime);
    $this->assertEquals(DateTimeTypeInterface::STORAGE_TIMEZONE, $entity->{$this->fieldName}[0]->getProperties()['datetime']->getDateTime()->getTimeZone()->getName());
    $this->assertEquals($date_range['start'], $entity->{$this->fieldName}->daterange);
    $this->assertEquals($date_range['start'], $entity->{$this->fieldName}[0]->daterange);
    $this->assertEquals($date_range['end'], $entity->{$this->fieldName}->{'daterange__end'});
    $this->assertEquals($date_range['end'], $entity->{$this->fieldName}[0]->{'daterange__end'});
    /** @var \Drupal\Core\Datetime\DrupalDateTime $date_object */
    $date_object = $entity->{$this->fieldName}[0]->getProperties()['datetime']->getDateTime();
    $this->assertEquals('00:00:00', $date_object->format('H:i:s'));
    $date_object->setDefaultDateTime();
    $this->assertEquals('12:00:00', $date_object->format('H:i:s'));
  }

}
