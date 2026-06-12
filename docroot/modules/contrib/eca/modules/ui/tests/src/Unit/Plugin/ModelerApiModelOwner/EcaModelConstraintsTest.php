<?php

namespace Drupal\Tests\eca_ui\Unit\Plugin\ModelerApiModelOwner;

use Drupal\eca_ui\Plugin\ModelerApiModelOwner\Eca;
use Drupal\modeler_api\Api;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ECA model owner's modelConstraints() method.
 *
 * @see \Drupal\eca_ui\Plugin\ModelerApiModelOwner\Eca::modelConstraints()
 */
#[Group('eca')]
#[Group('eca_ui')]
class EcaModelConstraintsTest extends TestCase {

  /**
   * The ECA model owner plugin under test.
   *
   * @var \Drupal\eca_ui\Plugin\ModelerApiModelOwner\Eca
   */
  protected Eca $ecaModelOwner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create the plugin instance bypassing the constructor via reflection,
    // since the base class requires container dependencies that are not
    // needed for testing modelConstraints().
    $ref = new \ReflectionClass(Eca::class);
    $this->ecaModelOwner = $ref->newInstanceWithoutConstructor();
  }

  /**
   * Tests that modelConstraints() returns the expected structure.
   */
  public function testModelConstraintsStructure(): void {
    $constraints = $this->ecaModelOwner->modelConstraints();

    self::assertNotEmpty($constraints);
    self::assertArrayHasKey(Api::COMPONENT_TYPE_START, $constraints);
    self::assertArrayHasKey(Api::COMPONENT_TYPE_ELEMENT, $constraints);
    self::assertArrayHasKey(Api::COMPONENT_TYPE_GATEWAY, $constraints);
  }

  /**
   * Tests that requireConditionWhenParallel is TRUE for all constrained types.
   */
  public function testRequireConditionWhenParallelIsEnabled(): void {
    $constraints = $this->ecaModelOwner->modelConstraints();
    $expectedTypes = [
      Api::COMPONENT_TYPE_START,
      Api::COMPONENT_TYPE_ELEMENT,
      Api::COMPONENT_TYPE_GATEWAY,
    ];

    foreach ($expectedTypes as $type) {
      self::assertArrayHasKey('successors', $constraints[$type], sprintf(
        'Component type %d should have a "successors" constraint.',
        $type,
      ));
      self::assertTrue(
        $constraints[$type]['successors']['requireConditionWhenParallel'],
        sprintf(
          'Component type %d should have requireConditionWhenParallel set to TRUE.',
          $type,
        ),
      );
    }
  }

  /**
   * Tests that LINK type is not constrained (conditions themselves).
   */
  public function testLinkTypeNotConstrained(): void {
    $constraints = $this->ecaModelOwner->modelConstraints();

    self::assertArrayNotHasKey(Api::COMPONENT_TYPE_LINK, $constraints);
  }

}
