<?php

namespace Drupal\Tests\address\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

// cspell:ignore Testgatan

/**
 * Tests validation of the address field.
 *
 * @group address
 */
class AddressItemValidationTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_address',
      'entity_type' => 'entity_test',
      'type' => 'address',
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_address',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => [
        'field_overrides' => [
          'administrativeArea' => ['override' => 'required'],
        ],
      ],
    ]);
    $field->save();
  }

  /**
   * Tests that "Required" overrides are ignored for unused fields.
   *
   * The administrative area is forced "required" above, but Sweden's
   * address format has no administrative area at all. Validation should not
   * demand a value that the address form never even allows entering.
   *
   * @see https://www.drupal.org/project/address/issues/3508727
   */
  public function testRequiredOverrideIgnoredForUnusedField() {
    $entity = EntityTest::create([
      'name' => 'Test',
      'field_address' => [
        'country_code' => 'SE',
        'given_name' => 'Jane',
        'family_name' => 'Doe',
        'address_line1' => 'Testgatan 1',
        'locality' => 'Stockholm',
        'postal_code' => '11122',
      ],
    ]);
    $violations = $entity->field_address->validate();
    $this->assertCount(0, $violations);
  }

  /**
   * Tests that the "Required" override still applies for a used field.
   */
  public function testRequiredOverrideAppliesForUsedField() {
    $entity = EntityTest::create([
      'name' => 'Test',
      'field_address' => [
        'country_code' => 'US',
        'given_name' => 'Jane',
        'family_name' => 'Doe',
        'address_line1' => '1098 Alta Ave',
        'locality' => 'Mountain View',
        'postal_code' => '94043',
      ],
    ]);
    $violations = $entity->field_address->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('0.administrative_area', $violations[0]->getPropertyPath());
  }

}
