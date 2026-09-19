<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Unit;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_icons_field\Plugin\Field\FieldType\IconType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the IconType class.
 *
 * @internal
 */
#[CoversClass(IconType::class)]
#[Group('ui_icons')]
class IconTypeTest extends UnitTestCase {

  /**
   * The field type.
   *
   * @var \Drupal\ui_icons_field\Plugin\Field\FieldType\IconType
   */
  private IconType $iconType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $definition = $this->createMock(ComplexDataDefinitionInterface::class);
    $definition->method('getPropertyDefinitions')->willReturn([]);

    $this->iconType = new IconType(
      $definition,
      'test',
    );
  }

  /**
   * Test the schema method.
   */
  public function testSchema(): void {
    $schema = $this->iconType::schema($this->createMock(FieldStorageDefinitionInterface::class));

    $this->assertCount(1, $schema['columns']);
    $this->assertArrayHasKey('target_id', $schema['columns']);
  }

  /**
   * Test the propertyDefinitions method.
   */
  public function testPropertyDefinitions(): void {
    $properties = $this->iconType::propertyDefinitions($this->createMock(FieldStorageDefinitionInterface::class));

    $this->assertSame('string', $properties['target_id']->getDataType());
  }

}
