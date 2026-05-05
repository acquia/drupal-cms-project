<?php

namespace Drupal\Tests\custom_field\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Test cases related to the 'check empty' feature.
 *
 * @group custom_field
 */
class CheckEmptyTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'user',
    'system',
    'field',
    'text',
    'entity_test',
    'field_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    // Create a generic custom field for validation.
    FieldStorageConfig::create(
      [
        'field_name' => 'field_test',
        'entity_type' => 'entity_test',
        'type' => 'custom',
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
        'settings' => [
          'columns' => [
            'string_1' => [
              'name' => 'string_1',
              'type' => 'string',
              'length' => 255,
            ],
            'string_2' => [
              'name' => 'string_2',
              'type' => 'string',
              'length' => 255,
            ],
          ],
        ],
      ]
    )->save();

    FieldConfig::create(
      [
        'entity_type' => 'entity_test',
        'field_name' => 'field_test',
        'bundle' => 'entity_test',
        'settings' => [
          'field_settings' => [
            'string_1' => [
              'check_empty' => FALSE,
              'description' => '',
              'description_display' => 'after',
            ],
            'string_2' => [
              'check_empty' => TRUE,
              'description' => '',
              'description_display' => 'after',
            ],
          ],
        ],
      ]
    )->save();
  }

  /**
   * Test case for the 'check empty' feature.
   */
  public function testCheckEmpty(): void {
    $entity = EntityTest::create([
      'type' => 'entity_test',
      'field_test' => [
        ['string_1' => 'Test 1', 'string_2' => 'Test 2'],
        ['string_1' => 'Test 3', 'string_2' => 'Test 4'],
      ],
    ]);
    $entity->save();

    $expected = [
      0 => ['string_1' => 'Test 1', 'string_2' => 'Test 2'],
      1 => ['string_1' => 'Test 3', 'string_2' => 'Test 4'],
    ];
    $this->assertSame($expected, $entity->field_test->getValue());

    $entity->field_test->setValue([
      0 => ['string_1' => 'Test 1', 'string_2' => 'Test 2'],
      1 => ['string_1' => 'Test 3', 'string_2' => ''],
    ]);

    $entity->save();
    \Drupal::entityTypeManager()->getStorage('entity_test')->resetCache();
    $entity = EntityTest::load($entity->id());

    // Because 'string_2' was empty, delta 1 should have been removed.
    $expected = [
      0 => ['string_1' => 'Test 1', 'string_2' => 'Test 2'],
    ];
    $this->assertSame($expected, $entity->field_test->getValue());
  }

}
