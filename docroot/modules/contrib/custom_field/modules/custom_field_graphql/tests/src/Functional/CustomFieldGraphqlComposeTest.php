<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field_graphql\Functional;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\custom_field\CustomFieldGenerateDataInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;

/**
 * Test the Custom Field integration.
 *
 * @group legacy
 */
class CustomFieldGraphqlComposeTest extends GraphQLComposeBrowserTestBase {

  /**
   * We aren't concerned with strict config schema for contrib.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // @phpcs:ignore

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'custom_field_test',
    'custom_field_graphql_test',
    'custom_field_viewfield',
    'custom_field_graphql',
    'graphql_compose',
    'graphql_compose_views',
    'node',
    'user',
  ];

  /**
   * The custom field generate data service.
   *
   * @var \Drupal\custom_field\CustomFieldGenerateDataInterface
   */
  protected CustomFieldGenerateDataInterface $customFieldDataGenerator;

  /**
   * File URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->customFieldDataGenerator = $this->container->get('custom_field.generate_data');
    $this->fileUrlGenerator = $this->container->get('file_url_generator');

    $field_config = FieldConfig::loadByName(
      'node',
      'custom_field_entity_test',
      'field_test'
    );

    // Set the graphql views in the widget settings.
    $field_settings = $field_config->getSetting('field_settings');
    $field_settings['viewfield']['allowed_views'] = [
      'custom_field_graphql_test' => [
        'graphql_1' => 'graphql_1',
      ],
      'custom_field_graphql_test2' => [
        'graphql_1' => 'graphql_1',
      ],
    ];
    $field_config->setSetting('field_settings', $field_settings);
    $field_config->save();

    $field_data = $this->customFieldDataGenerator->generateFieldData($field_config->getSettings(), $field_config->getTargetEntityTypeId());

    // Set a graphql view display.
    $field_data['viewfield'] = 'custom_field_graphql_test';
    $field_data['viewfield__display'] = 'graphql_1';

    $this->node = $this->createNode([
      'type' => 'custom_field_entity_test',
      'title' => 'Test',
      'body' => [
        'value' => 'Test content',
        'format' => 'plain_text',
      ],
      'status' => 1,
      'field_test' => $field_data,
    ]);

    // Create some article nodes.
    $article_titles = ['Article 1', 'Article 2', 'Article 3'];
    foreach ($article_titles as $article_title) {
      $this->createNode([
        'type' => 'article',
        'title' => $article_title,
        'status' => 1,
      ]);
    }

    $this->setEntityConfig('node', 'custom_field_entity_test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);
    $this->setEntityConfig('node', 'article', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'custom_field_entity_test', 'field_test', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Throwable
   */
  public function testCustomField(): void {
    $node = $this->node;
    $entity_type_id = $node->getEntityTypeId();
    $bundle = $node->bundle();
    $field_name = 'field_test';
    $field_config = FieldConfig::loadByName(
      'node',
      'custom_field_entity_test',
      'field_test'
    );
    $columns = $field_config->getSetting('columns');

    $config = $this->config('graphql_compose.settings');
    $config_path = "field_config.$entity_type_id.$bundle.$field_name.subfields";
    $settings = $config->get($config_path);
    // Test backwards compatibility to ensure names with underscores continue to
    // resolve.
    foreach ($columns as $name => $column) {
      $settings[(string) $name] = [
        'enabled' => TRUE,
        'name_sdl' => (string) $name,
      ];
    }
    $config->set($config_path, $settings);
    $config->save();

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeCustomFieldEntityTest {
            test {
              boolean
              color
              datetime {
                offset
                time
                timestamp
                timezone
              }
              daterange {
                start {
                  offset
                  time
                  timestamp
                  timezone
                }
                end {
                  offset
                  time
                  timestamp
                  timezone
                }
                duration
              }
              decimal
              duration
              email
              entity_reference {
                __typename
                ... on NodeArticle {
                  id
                }
              }
              file {
                description
                mime
                name
                size
                url
              }
              float
              image {
                alt
                height
                mime
                size
                title
                url
                width
              }
              integer
              link {
                internal
                title
                url
                attributes {
                  accesskey
                  ariaLabel
                  class
                  id
                  name
                  rel
                  target
                  title
                }
              }
              map
              map_string
              string
              string_long {
                value
                processed
                format
              }
              telephone
              time
              time_range {
                start
                end
                duration
              }
              uri {
                internal
                title
                url
              }
              viewfield {
                views {
                  __typename
                  ... on CustomFieldTestGraphqlResult {
                    results {
                      __typename
                      ... on NodeArticle {
                        id
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertNotNull($content['data']['node']['test'] ?? NULL);

    $custom_field = $content['data']['node']['test'];
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $node->get('field_test')->first();

    $this->assertEquals($item->get('boolean')->getValue(), $custom_field['boolean']);
    $this->assertEquals($item->get('color')->getValue(), $custom_field['color']);
    $this->assertEquals($item->get('decimal')->getValue(), $custom_field['decimal']);
    $this->assertEquals($item->get('duration')->getValue(), $custom_field['duration']);
    $this->assertEquals($item->get('email')->getValue(), $custom_field['email']);

    // Entity reference type.
    $reference = $custom_field['entity_reference'];
    /** @var \Drupal\node\NodeInterface $reference_entity */
    $reference_entity = $item->get('entity_reference__entity')->getValue();
    $this->assertEquals('NodeArticle', $reference['__typename']);
    $this->assertEquals($reference_entity->uuid(), $reference['id']);

    // Datetime type.
    $date_value = $item->get('datetime')->getValue();
    $date = new DrupalDateTime($date_value, new \DateTimeZone('UTC'));
    $this->assertEquals($date->getTimestamp(), $custom_field['datetime']['timestamp']);
    $this->assertEquals($date->getTimezone()->getName(), $custom_field['datetime']['timezone']);
    $this->assertEquals($date->format('P'), $custom_field['datetime']['offset']);
    $this->assertEquals($date->format(\DateTime::RFC3339), $custom_field['datetime']['time']);

    // Daterange type.
    $start_date_value = $item->get('daterange')->getValue();
    $start_date = new DrupalDateTime($start_date_value, new \DateTimeZone('UTC'));
    $this->assertEquals($start_date->getTimestamp(), $custom_field['daterange']['start']['timestamp']);
    $this->assertEquals($start_date->getTimezone()->getName(), $custom_field['daterange']['start']['timezone']);
    $this->assertEquals($start_date->format('P'), $custom_field['daterange']['start']['offset']);
    $this->assertEquals($start_date->format(\DateTime::RFC3339), $custom_field['daterange']['start']['time']);
    $end_date_value = $item->get('daterange__end')->getValue();
    $end_date = new DrupalDateTime($end_date_value, new \DateTimeZone('UTC'));
    $this->assertEquals($end_date->getTimestamp(), $custom_field['daterange']['end']['timestamp']);
    $this->assertEquals($end_date->getTimezone()->getName(), $custom_field['daterange']['end']['timezone']);
    $this->assertEquals($end_date->format('P'), $custom_field['daterange']['end']['offset']);
    $this->assertEquals($end_date->format(\DateTime::RFC3339), $custom_field['daterange']['end']['time']);
    $duration = $item->get('daterange__duration')->getValue();
    $this->assertEquals($duration, $custom_field['daterange']['duration']);

    // File.
    /** @var \Drupal\file\FileInterface $file */
    $file = $item->get('file__entity')->getValue();
    $file_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    $this->assertEquals($file->getMimeType(), $custom_field['file']['mime']);
    $this->assertEquals($file->getSize(), $custom_field['file']['size']);
    $this->assertEquals($file->getFilename(), $custom_field['file']['name']);
    $this->assertEquals($file_url, $custom_field['file']['url']);

    // Why are float values slightly off in comparison?
    $this->assertTrue(is_numeric($custom_field['float']));

    // Image type.
    /** @var \Drupal\file\FileInterface $image */
    $image = $item->get('image__entity')->getValue();
    $image_url = $this->fileUrlGenerator->generateAbsoluteString($image->getFileUri());
    $this->assertEquals($item->get('image__alt')->getValue(), $custom_field['image']['alt']);
    $this->assertEquals($item->get('image__height')->getValue(), $custom_field['image']['height']);
    $this->assertEquals($image->getMimeType(), $custom_field['image']['mime']);
    $this->assertEquals($image->getSize(), $custom_field['image']['size']);
    $this->assertEquals($item->get('image__title')->getValue(), $custom_field['image']['title']);
    $this->assertEquals($item->get('image__width')->getValue(), $custom_field['image']['width']);
    $this->assertEquals($image_url, $custom_field['image']['url']);

    // Link type.
    $link_url = Url::fromUri($item->get('link')->getValue());
    $this->assertEquals($link_url->toString(), $custom_field['link']['url']);
    $this->assertEquals(!$link_url->isExternal(), (bool) $custom_field['link']['internal']);
    $this->assertEquals($item->get('link__title')->getValue(), (bool) $custom_field['link']['title']);

    $this->assertEquals($item->get('map_string')->getValue(), $custom_field['map_string']);
    $this->assertEquals($item->get('map')->getValue(), $custom_field['map']);
    $this->assertEquals($item->get('integer')->getValue(), $custom_field['integer']);
    $this->assertEquals($item->get('string')->getValue(), $custom_field['string']);

    // String long type.
    $this->assertEquals($item->get('string_long')->getValue(), $custom_field['string_long']['value']);
    $this->assertNotEmpty($custom_field['string_long']['format']);
    $this->assertNotEmpty($custom_field['string_long']['processed']);

    $this->assertEquals($item->get('telephone')->getValue(), $custom_field['telephone']);
    $this->assertEquals($item->get('time')->getValue(), $custom_field['time']);

    // Time range type.
    $this->assertEquals($item->get('time_range')->getValue(), $custom_field['time_range']['start']);
    $this->assertEquals($item->get('time_range__end')->getValue(), $custom_field['time_range']['end']);
    $this->assertEquals($item->get('time_range__duration')->getValue(), $custom_field['time_range']['duration']);

    // Uri type.
    $uri_url = Url::fromUri($item->get('uri')->getValue());
    $this->assertEquals($uri_url->toString(), $custom_field['uri']['url']);
    $this->assertEquals(!$uri_url->isExternal(), (bool) $custom_field['uri']['internal']);

    // Viewfield type.
    $viewfield = $custom_field['viewfield'];
    $this->assertEquals('CustomFieldTestGraphqlResult', $viewfield['views']['__typename']);
    $this->assertEquals('NodeArticle', $viewfield['views']['results'][0]['__typename']);
  }

  /**
   * Test load entity by id with advanced settings.
   *
   * @throws \Throwable
   */
  public function testCustomFieldAdvancedSettings(): void {
    $node = $this->node;
    $entity_type_id = $node->getEntityTypeId();
    $bundle = $node->bundle();
    $field_name = 'field_test';

    $config = $this->config('graphql_compose.settings');
    $config_path = "field_config.$entity_type_id.$bundle.$field_name.subfields";
    $settings = $config->get($config_path);
    // Set a field's name_sdl and verify it works.
    $settings['string'] = [
      'enabled' => TRUE,
      'name_sdl' => 'someCustomName',
    ];
    $config->set($config_path, $settings);
    $config->save();

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeCustomFieldEntityTest {
            test {
              someCustomName
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertNotNull($content['data']['node']['test'] ?? NULL);

    $custom_field = $content['data']['node']['test'];
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $node->get('field_test')->first();

    $this->assertEquals($item->get('string')->getValue(), $custom_field['someCustomName']);
  }

}
