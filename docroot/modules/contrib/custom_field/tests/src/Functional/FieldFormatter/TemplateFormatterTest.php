<?php

namespace Drupal\Tests\custom_field\Functional\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Render\Markup;
use Drupal\custom_field\Time;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the default 'custom_formatter' formatter.
 *
 * @group custom_field
 */
final class TemplateFormatterTest extends FormatterTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field_test',
    'node',
    'field_ui',
    'token',
    'image',
    'taxonomy',
  ];

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

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
  protected string $displayType = 'custom_template';

  /**
   * The article node to use for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $articleNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();
    // Add custom field to the vocabulary.
    FieldStorageConfig::create([
      'field_name' => 'field_custom',
      'entity_type' => 'taxonomy_term',
      'type' => 'custom',
      'cardinality' => 1,
      'settings' => [
        'columns' => [
          'title' => [
            'name' => 'title',
            'type' => 'string',
            'length' => 255,
          ],
        ],
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_custom',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'tags',
      'label' => 'Custom',
      'settings' => [
        'add_more_label' => '',
        'field_settings' => [
          'title' => [
            'label' => 'Title',
            'check_empty' => FALSE,
            'required' => FALSE,
            'translatable' => FALSE,
            'description' => '',
            'description_display' => 'after',
            'prefix' => '',
            'suffix' => '',
            'allowed_values' => [],
          ],
        ],
      ],
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
      'cardinality' => -1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Tags',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'tags' => 'tags',
          ],
        ],
      ],
    ])->save();
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Test term',
      'field_custom' => [
        'title' => 'Test custom title on Term',
      ],
    ]);
    $term->save();
    $this->articleNode = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test Article',
      'field_tags' => [
        ['target_id' => $term->id()],
      ],
    ]);
    $this->imageFactory = $this->container->get('image.factory');
    $display = EntityViewDisplay::load($this->viewDisplay);
    $component = $display->getComponent($this->fieldName);
    $component['type'] = $this->displayType;
    $component['settings'] = [
      'template' => '',
      'tokens' => 'basic',
      'advanced_tokens' => [
        'recursion_limit' => 3,
        'global_types' => FALSE,
      ],
    ];
    $display->setComponent($this->fieldName, $component)->save();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests advanced tokens.
   */
  public function testAdvancedTokens(): void {
    $display = EntityViewDisplay::load($this->viewDisplay);
    $component = $display->getComponent($this->fieldName);
    $component['settings']['tokens'] = 'advanced';
    $component['settings']['template'] = '
    <div class="boolean">[node:' . $this->fieldName . ':boolean:value]</div>
    <div class="string">[node:' . $this->fieldName . ':string:value]</div>
    <div class="string-field-label">[node:' . $this->fieldName . ':string:field_label]</div>
    <div class="string-label">[node:' . $this->fieldName . ':string:label]</div>
    <div class="string_long">[node:' . $this->fieldName . ':string_long:value]</div>
    <div class="integer">[node:' . $this->fieldName . ':integer:value]</div>
    <div class="float">[node:' . $this->fieldName . ':float:value]</div>
    <div class="decimal">[node:' . $this->fieldName . ':decimal:value]</div>
    <div class="email">[node:' . $this->fieldName . ':email:value]</div>
    <div class="telephone">[node:' . $this->fieldName . ':telephone:value]</div>
    <div class="color">[node:' . $this->fieldName . ':color:value]</div>
    <div class="time">[node:' . $this->fieldName . ':time:value]</div>
    <div class="time_range">[node:' . $this->fieldName . ':time_range:value]</div>
    <div class="time_range-end">[node:' . $this->fieldName . ':time_range:end_value]</div>
    <div class="time_range-duration">[node:' . $this->fieldName . ':time_range:duration]</div>
    <div class="datetime">[node:' . $this->fieldName . ':datetime]</div>
    <div class="datetime-formatted-html_date">[node:' . $this->fieldName . ':datetime:formatted:html_date]</div>
    <div class="datetime-formatted-custom-Y">[node:' . $this->fieldName . ':datetime:formatted:custom:Y]</div>
    <div class="daterange">[node:' . $this->fieldName . ':daterange:value]</div>
    <div class="daterange-end">[node:' . $this->fieldName . ':daterange:end_value]</div>
    <div class="daterange-duration">[node:' . $this->fieldName . ':daterange:duration]</div>
    <div class="daterange-startdate-html_date">[node:' . $this->fieldName . ':daterange:start_date:html_date]</div>
    <div class="daterange-startdate-custom-Y">[node:' . $this->fieldName . ':daterange:start_date:custom:Y]</div>
    <div class="daterange-enddate-html_date">[node:' . $this->fieldName . ':daterange:end_date:html_date]</div>
    <div class="daterange-enddate-custom-Y">[node:' . $this->fieldName . ':daterange:end_date:custom:Y]</div>
    <div class="uri-url">[node:' . $this->fieldName . ':uri:url]</div>
    <div class="uri-url-brief">[node:' . $this->fieldName . ':uri:url:brief]</div>
    <div class="link-url">[node:' . $this->fieldName . ':link:url]</div>
    <div class="link-url-args-last">[node:' . $this->fieldName . ':link:url:args:last]</div>
    <div class="link-title">[node:' . $this->fieldName . ':link:title]</div>
    <div class="map">[node:' . $this->fieldName . ':map:value]</div>
    <div class="map_string">[node:' . $this->fieldName . ':map_string:value]</div>
    <div class="image-alt">[node:' . $this->fieldName . ':image:alt]</div>
    <div class="image-title">[node:' . $this->fieldName . ':image:title]</div>
    <div class="image-height">[node:' . $this->fieldName . ':image:height]</div>
    <div class="image-width">[node:' . $this->fieldName . ':image:width]</div>
    <div class="image-entity">[node:' . $this->fieldName . ':image:entity]</div>
    <div class="image-entity-url">[node:' . $this->fieldName . ':image:entity:url]</div>
    <div class="image-entity-name">[node:' . $this->fieldName . ':image:entity:name]</div>
    <div class="image-large-width">[node:' . $this->fieldName . ':image:large:width]</div>
    <div class="image-large-uri">[node:' . $this->fieldName . ':image:large:uri]</div>
    <div class="image-large-url">[node:' . $this->fieldName . ':image:large:url]</div>
    <div class="image-large-mimetype">[node:' . $this->fieldName . ':image:large:mimetype]</div>
    <div class="image-large-filesize">[node:' . $this->fieldName . ':image:large:filesize]</div>
    <div class="file">[node:' . $this->fieldName . ':file]</div>
    <div class="file-entity-extension">[node:' . $this->fieldName . ':file:entity:extension]</div>
    <div class="file-entity-url">[node:' . $this->fieldName . ':file:entity:url]</div>
    <div class="duration">[node:' . $this->fieldName . ':duration:value]</div>
    <div class="entity_reference">[node:' . $this->fieldName . ':entity_reference]</div>
    <div class="entity_reference-entity">[node:' . $this->fieldName . ':entity_reference:entity]</div>
    <div class="entity_reference-entity-title">[node:' . $this->fieldName . ':entity_reference:entity:title]</div>
    <div class="entity_reference-entity-url-args-last">[node:' . $this->fieldName . ':entity_reference:entity:url:args:last]</div>
    <div class="entity_reference-entity-field-custom-title">[node:' . $this->fieldName . ':entity_reference:entity:field_tags:0:entity:field_custom:title]</div>
    ';

    $display->setComponent($this->fieldName, $component)->save();
    $session = $this->assertSession();

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

    $map = [
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
    ];
    $map_string = [
      'Value 1',
      'Value 2',
      'Value 3',
    ];
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
        'map_string' => $map_string,
        'map' => $map,
        'image' => $test_image->id(),
        'image__alt' => 'Test image alt',
        'image__title' => 'Test image title',
        'file' => $test_file->id(),
        'link' => 'entity:node/' . $this->articleNode->id(),
        'link__title' => $this->articleNode->label(),
        'link__options' => [
          'attributes' => [
            'rel' => 'nofollow',
            'target' => '_blank',
            'class' => ['link-test'],
          ],
        ],
        'entity_reference' => $this->articleNode->id(),
        'viewfield' => 'custom_field_test',
        'viewfield__display' => 'block_1',
        'duration' => 604800,
      ],
    ]);

    $values = $node1->get($this->fieldName)->getValue();
    $this->drupalGet('/node/' . $node1->id());
    // Test boolean.
    $session->elementTextEquals('css', '.boolean', '1');
    // Test string.
    $session->elementTextEquals('css', '.string', 'Test string');
    // Test that the options label is just value since no allowed_values set.
    $session->elementTextEquals('css', '.string-label', 'Test string');
    // Test string_long.
    $session->elementTextEquals('css', '.string_long', 'Test string long');
    // Test integer.
    $session->elementTextEquals('css', '.integer', '42');
    // Test float.
    $session->elementTextEquals('css', '.float', '3.14');
    // Test decimal.
    $session->elementTextEquals('css', '.decimal', '42.42');
    // Test email.
    $session->elementTextEquals('css', '.email', 'test@example.com');
    // Test telephone.
    $session->elementTextEquals('css', '.telephone', '+1234567890');
    // Test color.
    $session->elementTextEquals('css', '.color', '#FF0000');
    // Test map.
    $session->elementTextEquals('css', '.map', Json::encode($map));
    // Test map_string.
    $session->elementTextEquals('css', '.map_string', Json::encode($map_string));
    // Test link.
    $session->elementTextEquals('css', '.link-url', $this->articleNode->toUrl()->toString());
    $session->elementTextEquals('css', '.link-url-args-last', $this->articleNode->id());
    $session->elementTextEquals('css', '.link-title', $this->articleNode->label());
    // Test uri.
    $session->elementTextEquals('css', '.uri-url', 'http://www.example.com');
    $session->elementTextEquals('css', '.uri-url-brief', 'www.example.com');
    // Test image.
    $image_width = $values[0]['image__width'];
    $image_height = $values[0]['image__height'];
    $image_uri = $test_image->getFileUri();
    $image_style_storage = $this->entityTypeManager->getStorage('image_style');
    $large_image_style = $image_style_storage->load('large');
    $dimensions = [
      'width' => $image_width,
      'height' => $image_height,
    ];
    $large_image_style->transformDimensions($dimensions, $image_uri);
    $session->elementTextEquals('css', '.image-alt', 'Test image alt');
    $session->elementTextEquals('css', '.image-title', 'Test image title');
    $session->elementTextEquals('css', '.image-height', $image_height);
    $session->elementTextEquals('css', '.image-width', $image_width);
    $session->elementTextEquals('css', '.image-entity', $test_image->label());
    $session->elementTextEquals('css', '.image-entity-name', $test_image->label());
    $session->elementTextEquals('css', '.image-entity-url', $test_image->createFileUrl(FALSE));
    // Test image style tokens.
    $session->elementTextEquals('css', '.image-large-width', $dimensions['width']);
    $session->elementTextEquals('css', '.image-large-uri', $large_image_style->buildUri($image_uri));
    // Generate the image derivative.
    $derivative_image_uri = $large_image_style->buildUri($image_uri);
    $large_image_style->createDerivative($image_uri, $derivative_image_uri);
    $large_image = $this->imageFactory->get($derivative_image_uri);
    $session->elementTextEquals('css', '.image-large-url', Markup::create($large_image_style->buildUrl($image_uri)));
    $session->elementTextEquals('css', '.image-large-mimetype', $large_image->getMimeType());
    $session->elementTextEquals('css', '.image-large-filesize', $large_image->getFileSize());

    // Test file.
    $session->elementTextEquals('css', '.file', $test_file->label());
    $session->elementTextEquals('css', '.file-entity-extension', 'txt');
    $session->elementTextEquals('css', '.file-entity-url', $test_file->createFileUrl(FALSE));

    // Test duration.
    $session->elementTextEquals('css', '.duration', $values[0]['duration']);

    // Test time.
    $session->elementTextEquals('css', '.time', $values[0]['time']);

    // Test time_range.
    $session->elementTextEquals('css', '.time_range', $values[0]['time_range']);
    $session->elementTextEquals('css', '.time_range-end', $values[0]['time_range__end']);
    $session->elementTextEquals('css', '.time_range-duration', $values[0]['time_range__duration']);

    // Test datetime.
    $session->elementTextEquals('css', '.field--type-custom .datetime', $values[0]['datetime']);
    $datetime_object = $node1->get($this->fieldName)->first()->{'datetime__date'};
    $timestamp = $datetime_object->getTimestamp();
    $session->elementTextEquals('css', '.datetime-formatted-html_date', $this->dateFormatter->format($timestamp, 'html_date'));
    $session->elementTextEquals('css', '.datetime-formatted-custom-Y', $this->dateFormatter->format($timestamp, 'custom', 'Y'));

    // Test daterange.
    $start_date = $node1->get($this->fieldName)->first()->{'daterange__start_date'};
    $end_date = $node1->get($this->fieldName)->first()->{'daterange__end_date'};
    $session->elementTextEquals('css', '.daterange', $values[0]['daterange']);
    $session->elementTextEquals('css', '.daterange-end', $values[0]['daterange__end']);
    $session->elementTextEquals('css', '.daterange-duration', $values[0]['daterange__duration']);
    $session->elementTextEquals('css', '.daterange-startdate-html_date', $this->dateFormatter->format($start_date->getTimestamp(), 'html_date'));
    $session->elementTextEquals('css', '.daterange-startdate-custom-Y', $this->dateFormatter->format($start_date->getTimestamp(), 'custom', 'Y'));
    $session->elementTextEquals('css', '.daterange-enddate-html_date', $this->dateFormatter->format($end_date->getTimestamp(), 'html_date'));
    $session->elementTextEquals('css', '.daterange-enddate-custom-Y', $this->dateFormatter->format($end_date->getTimestamp(), 'custom', 'Y'));

    // Test entity_reference.
    $session->elementTextEquals('css', '.entity_reference', $this->articleNode->label());
    $session->elementTextEquals('css', '.entity_reference-entity-title', $this->articleNode->label());
    $session->elementTextEquals('css', '.entity_reference-entity-url-args-last', $this->articleNode->id());
    // Test deep nested entity_reference.
    $term = $this->articleNode->get('field_tags')->referencedEntities()[0];
    $session->elementTextEquals('css', '.entity_reference-entity-field-custom-title', $term->get('field_custom')->first()->get('title')->getValue());

    // Test string options label.
    $settings = $this->field->getSettings();
    $settings['field_settings']['string']['allowed_values'] = [
      ['key' => 'option1', 'label' => 'Option 1'],
      ['key' => 'option2', 'label' => 'Option 2'],
    ];
    $this->field->setSettings($settings);
    $this->field->save();
    $node1->get($this->fieldName)->first()->set('string', 'option1');
    $node1->save();
    $this->drupalGet('/node/' . $node1->id());
    $session->elementTextEquals('css', '.string', 'option1');
    $session->elementTextEquals('css', '.string-label', 'Option 1');

    // Test a field label.
    $expected_label = $settings['field_settings']['string']['label'];
    $session->elementTextEquals('css', '.string-field-label', $expected_label);
  }

}
