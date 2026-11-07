<?php

namespace Drupal\Tests\address\Kernel\Plugin\diff\Field;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the address field diff builder plugin.
 *
 * @requires module diff
 * @group address
 */
class AddressFieldBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'entity_test',
    'user',
    'address',
    'diff',
  ];

  /**
   * The field name used for the test address field.
   *
   * @var string
   */
  protected $fieldName = 'field_address';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installConfig(['field', 'address']);

    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'type' => 'address',
    ])->save();

    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests that the plugin builds a diff-comparable render array.
   *
   * This exercises the fix for the AddressFieldBuilder::build() method
   * signature, which must remain compatible with the diff module's
   * CoreFieldBuilder::build(): array.
   */
  public function testBuild(): void {
    $entity = EntityTest::create([
      'name' => $this->randomString(),
      $this->fieldName => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
        'locality' => 'Mountain View',
        'postal_code' => '94043',
        'address_line1' => '1600 Amphitheatre Parkway',
      ],
    ]);
    $entity->save();

    /** @var \Drupal\diff\DiffBuilderManager $diff_builder_manager */
    $diff_builder_manager = \Drupal::service('plugin.manager.diff.builder');
    $plugin = $diff_builder_manager->createInstance('address_field_diff_builder');

    $result = $plugin->build($entity->get($this->fieldName));

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);

    $rendered = (string) $result[0][0];
    $this->assertStringContainsString('1600 Amphitheatre Parkway', $rendered);
    $this->assertStringContainsString('94043', $rendered);
  }

}
