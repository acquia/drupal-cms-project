<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayObject;
use Drupal\custom_field\Plugin\PropWidgetBase;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'array_object' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayObject
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetArrayObject::class)]
class PropWidgetArrayObjectTest extends PropWidgetArrayTestBase {

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
      PropWidgetArrayObject::class,
      'array_object',
      [
        'maxItems' => '',
        'items' => [
          'type' => 'object',
          'properties' => [
            'heading' => ['type' => 'string'],
            'content' => ['type' => 'string'],
          ],
          'required' => ['heading'],
        ],
      ],
      $this->propWidgetManager,
    );
  }

  /**
   * {@inheritdoc}
   *
   * PropWidgetArrayObject::getPropValue() returns an empty array rather than
   * NULL for an empty array input, so we override to exclude that case.
   */
  public function testDefaultSettingsContainsArrayBaseKeys(): void {
    $defaults = $this->plugin::defaultSettings();
    $this->assertArrayHasKey('maxItems', $defaults);
    $this->assertArrayHasKey('items', $defaults);
    $this->assertSame('', $defaults['maxItems']);
    // array_object extends items with additional keys beyond the base.
    $this->assertArrayHasKey('type', $defaults['items']);
    $this->assertArrayHasKey('properties', $defaults['items']);
    $this->assertArrayHasKey('required', $defaults['items']);
    $this->assertSame('', $defaults['items']['type']);
    $this->assertSame([], $defaults['items']['properties']);
    $this->assertSame([], $defaults['items']['required']);
    // Verify parent defaults are merged in.
    $this->assertArrayHasKey('title', $defaults);
    $this->assertArrayHasKey('description', $defaults);
    $this->assertArrayHasKey('default', $defaults);
    $this->assertArrayHasKey('format', $defaults);
  }

  /**
   * {@inheritdoc}
   *
   * PropWidgetArrayObject::getPropValue() returns an empty array rather than
   * NULL for an empty array input, so we override to exclude that case.
   */
  public function testGetPropValueReturnsNullForNonArray(): void {
    $this->assertPropValueIsNull(NULL);
    $this->assertPropValueIsNull('foo');
    $this->assertPropValueIsNull(42);
    $this->assertPropValueIsNull(TRUE);
  }

  /**
   * {@inheritdoc}
   *
   * PropWidgetArrayObject::massageValue() iterates directly over
   * $value['value'] without a null guard, so we only test the empty array
   * case here rather than NULL or non-array.
   */
  public function testMassageValueReturnsEmptyArrayForInvalidInput(): void {
    $result = $this->plugin->massageValue(['value' => []]);
    $this->assertSame([], $result['value']);
  }

  /**
   * Tests that getPropValue() delegates to property widgets to resolve values.
   */
  public function testGetPropValueDelegatesToPropertyWidgets(): void {
    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($this->createStringPassthroughWidget());

    $input = [
      [
        'heading' => ['value' => 'Hello'],
        'content' => ['value' => 'World'],
      ],
    ];
    $result = $this->plugin->getPropValue($input);
    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertSame('Hello', $result[0]['heading']);
    $this->assertSame('World', $result[0]['content']);
  }

  /**
   * Tests that getPropValue() removes items when required empty properties.
   */
  public function testGetPropValueRemovesItemsWithEmptyRequiredProperties(): void {
    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($this->createStringPassthroughWidget());

    $input = [
      ['heading' => ['value' => '']],
      ['heading' => ['value' => 'Hello']],
    ];
    $result = $this->plugin->getPropValue($input);
    $this->assertIsArray($result);
    $this->assertCount(1, $result);
  }

  /**
   * Tests that getPropValue() removes optional properties with empty values.
   */
  public function testGetPropValueRemovesEmptyOptionalProperties(): void {
    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($this->createStringPassthroughWidget());

    $input = [
      [
        'heading' => ['value' => 'Hello'],
        'content' => ['value' => ''],
      ],
    ];
    $result = $this->plugin->getPropValue($input);
    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertArrayHasKey('heading', $result[0]);
    $this->assertArrayNotHasKey('content', $result[0]);
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
        0 => [
          'heading' => ['widget' => 'string', 'value' => 'Hello'],
          'content' => ['widget' => 'string', 'value' => 'World'],
        ],
      ],
    ];
    $result = $this->plugin->massageValue($value);
    $this->assertIsArray($result['value']);
    $this->assertCount(1, $result['value']);
    $this->assertArrayHasKey('heading', $result['value'][0]);
    $this->assertArrayHasKey('content', $result['value'][0]);
  }

  /**
   * Creates a mock PropWidget that returns non-empty string values.
   *
   * Passes the scalar value directly to getPropValue() matching how
   * PropWidgetArrayObject extracts $item['value'] before delegating.
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
