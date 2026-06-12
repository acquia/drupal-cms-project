<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetObject;
use Drupal\custom_field\Plugin\PropWidgetBase;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'object' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetObject
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetObject::class)]
class PropWidgetObjectTest extends PropWidgetTestBase {

  /**
   * The prop widget manager mock.
   *
   * @var \Drupal\custom_field\PluginManager\PropWidgetManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected PropWidgetManagerInterface $propWidgetManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->propWidgetManager = $this->createMock(PropWidgetManagerInterface::class);
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(
      PropWidgetObject::class,
      'object',
      [
        'properties' => [
          'heading' => ['type' => 'string'],
          'content' => ['type' => 'string'],
        ],
        'required' => ['heading'],
      ],
      $this->propWidgetManager,
    );
  }

  /**
   * Tests that defaultSettings() returns the expected keys and values.
   */
  public function testDefaultSettings(): void {
    $defaults = PropWidgetObject::defaultSettings();
    $this->assertArrayHasKey('properties', $defaults);
    $this->assertArrayHasKey('required', $defaults);
    $this->assertSame([], $defaults['properties']);
    $this->assertSame([], $defaults['required']);
    // Verify parent defaults are merged in.
    $this->assertArrayHasKey('title', $defaults);
    $this->assertArrayHasKey('description', $defaults);
    $this->assertArrayHasKey('default', $defaults);
    $this->assertArrayHasKey('format', $defaults);
  }

  /**
   * Tests that getPropValue() returns NULL for non-array input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidPropValueProvider
   */
  #[DataProvider('invalidPropValueProvider')]
  public function testGetPropValueReturnsNullForNonArray(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that getPropValue() delegates to property widgets to resolve values.
   */
  public function testGetPropValueDelegatesToPropertyWidgets(): void {
    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($this->createStringPassthroughWidget());

    $input = [
      'heading' => ['value' => 'Hello'],
      'content' => ['value' => 'World'],
    ];
    $result = $this->plugin->getPropValue($input);
    $this->assertIsArray($result);
    $this->assertSame('Hello', $result['heading']);
    $this->assertSame('World', $result['content']);
  }

  /**
   * Tests that getPropValue() returns NULL when required properties are empty.
   */
  public function testGetPropValueReturnsNullWhenRequiredPropertyIsEmpty(): void {
    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($this->createStringPassthroughWidget());

    $input = [
      'heading' => ['value' => ''],
      'content' => ['value' => 'World'],
    ];
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that getPropValue() removes optional properties with empty values.
   */
  public function testGetPropValueRemovesEmptyOptionalProperties(): void {
    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($this->createStringPassthroughWidget());

    $input = [
      'heading' => ['value' => 'Hello'],
      'content' => ['value' => ''],
    ];
    $result = $this->plugin->getPropValue($input);
    $this->assertIsArray($result);
    $this->assertArrayHasKey('heading', $result);
    $this->assertArrayNotHasKey('content', $result);
  }

  /**
   * Tests that massageValue() delegates to property widgets.
   */
  public function testMassageValueDelegatesToPropertyWidgets(): void {
    $mock_widget = $this->createMock(PropWidgetBase::class);
    $mock_widget->method('massageValue')
      ->willReturnCallback(fn($value) => $value);

    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($mock_widget);

    $value = [
      'value' => [
        'heading' => ['widget' => 'string', 'value' => 'Hello'],
        'content' => ['widget' => 'string', 'value' => 'World'],
      ],
    ];
    $result = $this->plugin->massageValue($value);
    $this->assertIsArray($result['value']);
    $this->assertArrayHasKey('heading', $result['value']);
    $this->assertArrayHasKey('content', $result['value']);
  }

  /**
   * Provides invalid input cases that should return NULL from getPropValue().
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidPropValueProvider(): array {
    return [
      'null' => [NULL],
      'string' => ['foo'],
      'integer' => [42],
      'boolean' => [TRUE],
    ];
  }

  /**
   * Creates a mock PropWidget that returns non-empty string values.
   *
   * @return \Drupal\custom_field\Plugin\PropWidgetBase&\PHPUnit\Framework\MockObject\MockObject
   *   The mock widget.
   */
  protected function createStringPassthroughWidget(): PropWidgetBase {
    $mock_widget = $this->createMock(PropWidgetBase::class);
    $mock_widget->method('getPropValue')
      ->willReturnCallback(function ($value) {
        return is_string($value) && trim($value) !== '' ? $value : NULL;
      });
    return $mock_widget;
  }

}
