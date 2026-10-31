<?php

namespace Drupal\Tests\geofield\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\geofield\Plugin\Validation\Constraint\GeoConstraint;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests geofield constraints.
 *
 * @group geofield
 */
#[Group('geofield')]
#[RunTestsInSeparateProcesses]
class ConstraintsTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['geofield'];

  /**
   * Tests GeoType constraint.
   *
   * @covers \Drupal\geofield\Plugin\Validation\Constraint\GeoConstraintValidator
   * @covers \Drupal\geofield\Plugin\Validation\Constraint\GeoConstraint
   *
   * @dataProvider geoProvider
   */
  #[DataProvider('geoProvider')]
  public function testGeoConstraint($coordinates, $expected_violation_count) {
    // Check message in constraint.
    $constraint = new GeoConstraint();
    $this->assertEquals('"@value" is not a valid geospatial content.', $constraint->message, 'Correct constraint message found.');

    // Validate through the typed data validator, which runs
    // GeoConstraintValidator the way the installed Symfony version expects.
    $definition = DataDefinition::create('string')->addConstraint('GeoType');
    $violations = $this->container->get('typed_data_manager')->create($definition, $coordinates)->validate();
    $this->assertCount($expected_violation_count, $violations);
    foreach ($violations as $violation) {
      $this->assertSame('"' . $coordinates . '" is not a valid geospatial content.', (string) $violation->getMessage());
    }
  }

  /**
   * Provides test data for testGeoConstraint().
   */
  public static function geoProvider(): array {
    return [
      'valid POINT' => ['POINT (40 -3)', 0],
      'invalid POAINT' => ['POAINT (40 -3)', 1],
      'invalid POINT' => ['POINT (40 -A)', 1],
    ];
  }

}
