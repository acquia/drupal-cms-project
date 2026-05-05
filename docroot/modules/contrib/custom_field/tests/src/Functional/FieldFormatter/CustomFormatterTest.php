<?php

namespace Drupal\Tests\custom_field\Functional\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\custom_field\Time;
use Drupal\file\Entity\File;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the default 'custom_formatter' formatter.
 *
 * @group custom_field
 */
final class CustomFormatterTest extends FormatterTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_test',
    'node',
    'field_ui',
  ];

  /**
   * The view display to use for testing.
   *
   * @var string
   */
  protected string $viewDisplay = 'node.custom_field_entity_test.default';

  /**
   * The display type to use for testing.
   *
   * @var string
   */
  protected string $displayType = 'custom_formatter';

  /**
   * The default wrappers.
   *
   * @var array
   */
  protected array $defaultWrappers = [
    'field_wrapper_tag' => '',
    'field_wrapper_classes' => '',
    'field_tag' => '',
    'field_classes' => '',
    'label_tag' => 'h3',
    'label_classes' => '',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $display = EntityViewDisplay::load($this->viewDisplay);
    $component = $display->getComponent($this->fieldName);
    $fields = $component['settings']['fields'];
    foreach ($fields as $field_name => $field_settings) {
      $component['settings']['fields'][$field_name]['wrappers'] = $this->defaultWrappers;
    }
    $display->setComponent($this->fieldName, $component)->save();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests components.
   */
  public function testFieldsRender(): void {
    $display = EntityViewDisplay::load($this->viewDisplay);
    $component = $display->getComponent($this->fieldName);
    $display->setComponent($this->fieldName, $component)->save();
    $session = $this->assertSession();
    $articleNode = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test Article',
    ]);

    // Create a test file.
    $test_files = $this->getTestFiles('text');
    $test_file_info = reset($test_files);
    $test_file = File::create([
      'uri' => $test_file_info->uri,
      'filename' => $test_file_info->filename,
      'name' => $test_file_info->name,
      'filesize' => filesize($test_file_info->uri),
    ]);
    $test_file->setPermanent();
    $test_file->save();

    // Create a test image.
    $test_images = $this->getTestFiles('image');
    $test_image_info = reset($test_images);
    $test_image = File::create([
      'uri' => $test_image_info->uri,
      'filename' => $test_image_info->filename,
      'name' => $test_image_info->name,
      'filesize' => filesize($test_image_info->uri),
    ]);
    $test_image->setPermanent();
    $test_image->save();

    $node1 = $this->drupalCreateNode([
      'title' => 'Test Node',
      'type' => 'custom_field_entity_test',
      $this->fieldName => [
        'boolean' => TRUE,
        'string' => 'Test string',
        'string_long' => 'Test string long',
        'integer' => 42,
        'float' => 3.14,
        'datetime' => '2020-01-01T01:30:00',
        'daterange' => '2020-01-01T01:45:00',
        'daterange__end' => '2021-01-01T02:40:00',
        'decimal' => 42.420,
        'email' => 'test@example.com',
        'telephone' => '+1234567890',
        'uri' => 'http://www.example.com',
        'color' => 'FF0000',
        'time' => Time::createFromHtml5Format('13:40:30')->getTimestamp(),
        'time_range' => Time::createFromHtml5Format('11:40:30')->getTimestamp(),
        'time_range__end' => Time::createFromHtml5Format('14:40:30')->getTimestamp(),
        'map_string' => [
          'Value 1',
          'Value 2',
          'Value 3',
        ],
        'map' => [
          [
            'key' => 'key1',
            'value' => 'Value 1',
          ],
          [
            'key' => 'key2',
            'value' => 'Value 2',
          ],
          [
            'key' => 'key3',
            'value' => 'Value 3',
          ],
        ],
        'image' => $test_image->id(),
        'file' => $test_file->id(),
        'link' => 'http://www.example.com',
        'link__title' => 'Example link',
        'link__options' => [
          'attributes' => [
            'rel' => 'nofollow',
            'target' => '_blank',
            'class' => ['link-test'],
          ],
        ],
        'entity_reference' => $articleNode->id(),
        'viewfield' => 'custom_field_test',
        'viewfield__display' => 'block_1',
        'duration' => 604800,
      ],
    ]);
    $values = $node1->get($this->fieldName)->getValue();
    $date = $node1->{$this->fieldName}->{'datetime__date'};
    assert($date instanceof DrupalDateTime);
    $date_range_start = $node1->{$this->fieldName}->{'daterange__start_date'};
    assert($date_range_start instanceof DrupalDateTime);
    $date_range_end = $node1->{$this->fieldName}->{'daterange__end_date'};
    assert($date_range_end instanceof DrupalDateTime);
    $this->drupalGet('/node/' . $node1->id());
    // Test boolean.
    $session->elementTextEquals('css', 'div.field--name-boolean .field__item', 'Yes');
    // Test string.
    $session->elementTextEquals('css', 'div.field--name-string .field__item', 'Test string');
    // Test string_long.
    $session->elementTextEquals('css', 'div.field--name-string-long .field__item', 'Test string long');
    // Test integer.
    $session->elementTextEquals('css', 'div.field--name-integer .field__item', '42');
    // Test float.
    $session->elementTextEquals('css', 'div.field--name-float .field__item', '3.14');
    // Test decimal.
    $session->elementTextEquals('css', 'div.field--name-decimal .field__item', '42.42');
    // Test email.
    $session->elementAttributeContains('css', 'div.field--name-email .field__item a', 'href', 'mailto:test@example.com');
    // Test telephone.
    $session->elementTextEquals('css', 'div.field--name-telephone .field__item a', '+1234567890');
    $session->elementAttributeContains('css', 'div.field--name-telephone .field__item a', 'href', 'tel:%2B1234567890');
    // Test uri.
    $session->elementTextEquals('css', 'div.field--name-uri .field__item a', 'http://www.example.com');
    $session->elementAttributeContains('css', 'div.field--name-uri .field__item a', 'href', 'http://www.example.com');
    // Test link.
    $session->elementTextEquals('css', 'div.field--name-link .field__item a', 'Example link');
    $session->elementAttributeContains('css', 'div.field--name-link .field__item a', 'href', 'http://www.example.com');
    $session->elementAttributeContains('css', 'div.field--name-link .field__item a', 'rel', 'nofollow');
    $session->elementAttributeContains('css', 'div.field--name-link .field__item a', 'target', '_blank');
    $session->elementAttributeContains('css', 'div.field--name-link .field__item a', 'class', 'link-test');
    // Test color.
    $session->elementTextEquals('css', 'div.field--name-color .field__item', '#FF0000');
    // Test datetime.
    $session->elementAttributeContains('css', 'div.field--name-datetime .field__item time', 'datetime', $values[0]['datetime']);
    $session->elementTextEquals('css', 'div.field--name-datetime .field__item time', $this->dateFormatter->format($date->getTimestamp()));
    // Test daterange.
    $session->elementAttributeContains('css', 'div.field--name-daterange .field__item time:first-child', 'datetime', $values[0]['daterange']);
    $session->elementTextEquals('css', 'div.field--name-daterange .field__item time:first-child', $this->dateFormatter->format($date_range_start->getTimestamp(), 'custom', 'F jS, Y g:ia'));
    $session->elementAttributeContains('css', 'div.field--name-daterange .field__item time:last-child', 'datetime', $values[0]['daterange__end']);
    $session->elementTextEquals('css', 'div.field--name-daterange .field__item time:last-child', $this->dateFormatter->format($date_range_end->getTimestamp(), 'custom', 'F jS, Y g:ia'));
    // Test time.
    $session->elementTextEquals('css', 'div.field--name-time .field__item', '01:40 pm');
    // Test time_range.
    $session->elementTextEquals('css', 'div.field--name-time-range .field__item time:first-child', '11:40am');
    $session->elementTextEquals('css', 'div.field--name-time-range .field__item time:last-child', '2:40pm');
    // Test map_string.
    $session->elementTextContains('css', 'div.field--name-map-string .field__item', 'Value 1');
    $session->elementTextContains('css', 'div.field--name-map-string .field__item', 'Value 2');
    $session->elementTextContains('css', 'div.field--name-map-string .field__item', 'Value 3');
    // Test map.
    $session->elementTextContains('css', 'div.field--name-map .field__item', 'key1');
    $session->elementTextContains('css', 'div.field--name-map .field__item', 'Value 1');
    $session->elementTextContains('css', 'div.field--name-map .field__item', 'key2');
    $session->elementTextContains('css', 'div.field--name-map .field__item', 'Value 2');
    $session->elementTextContains('css', 'div.field--name-map .field__item', 'key3');
    $session->elementTextContains('css', 'div.field--name-map .field__item', 'Value 3');
    // Test duration.
    $session->elementTextEquals('css', 'div.field--name-duration .field__item', '7 days');
    // Test entity_reference.
    $session->elementTextEquals('css', 'div.field--name-entity-reference .field__item a', 'Test Article');
    $session->elementAttributeContains('css', 'div.field--name-entity-reference .field__item a', 'href', '/node/' . $articleNode->id());
    // Test file.
    $session->elementAttributeContains('css', 'div.field--name-file .field__item a', 'href', $test_file->createFileUrl());
    $session->elementTextEquals('css', 'div.field--name-file .field__item a', $test_file->getFilename());
    // Test image.
    $session->elementAttributeContains('css', 'div.field--name-image .field__item img', 'src', $test_image->createFileUrl());
    // Test viewfield.
    $session->elementExists('css', 'div.field--name-viewfield .view-custom-field-test');
    $session->elementsCount('css', 'div.field--name-viewfield .view-custom-field-test .views-row', 1);
    $session->elementTextContains('css', 'div.field--name-viewfield .view-custom-field-test .views-row a', 'Test Article');
    // Create another article node.
    $this->drupalCreateNode([
      'title' => 'Test Article 2',
      'type' => 'article',
    ]);
    $this->drupalGet('/node/' . $node1->id());
    $session->elementsCount('css', 'div.field--name-viewfield .view-custom-field-test .views-row', 2);
  }

}
