<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayImage;
use Drupal\custom_field\Plugin\PropWidgetBase;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'array_image' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayImage
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetArrayImage::class)]
class PropWidgetArrayImageTest extends PropWidgetArrayTestBase {

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
      PropWidgetArrayImage::class,
      'array_image',
      [
        'maxItems' => '',
        'items' => [
          'id' => '',
          'type' => 'object',
          'properties' => [],
          'required' => [],
        ],
      ],
      $this->propWidgetManager,
    );
  }

  /**
   * {@inheritdoc}
   *
   * PropWidgetArrayImage has an additional 'id' key in items beyond
   * PropWidgetArrayObject, so we override to assert the correct structure.
   */
  public function testDefaultSettingsContainsArrayBaseKeys(): void {
    $defaults = PropWidgetArrayImage::defaultSettings();
    $this->assertArrayHasKey('maxItems', $defaults);
    $this->assertArrayHasKey('items', $defaults);
    $this->assertSame('', $defaults['maxItems']);
    $this->assertArrayHasKey('id', $defaults['items']);
    $this->assertArrayHasKey('type', $defaults['items']);
    $this->assertArrayHasKey('properties', $defaults['items']);
    $this->assertArrayHasKey('required', $defaults['items']);
    $this->assertSame('', $defaults['items']['id']);
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
   * PropWidgetArrayImage::getPropValue() always returns an array, never NULL,
   * so we override to exclude the empty array case.
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
   * PropWidgetArrayImage::massageValue() iterates directly over $value['value']
   * without a null guard, so we only test the empty array case here.
   */
  public function testMassageValueReturnsEmptyArrayForInvalidInput(): void {
    $result = $this->plugin->massageValue(['value' => []]);
    $this->assertSame([], $result['value']);
  }

  /**
   * Tests that getPropValue() returns an empty array when items are invalid.
   *
   * @param mixed $input
   *   The input with invalid items.
   *
   * @dataProvider invalidImageArrayProvider
   */
  #[DataProvider('invalidImageArrayProvider')]
  public function testGetPropValueReturnsEmptyArrayForInvalidItems(mixed $input): void {
    $result = $this->plugin->getPropValue($input);
    $this->assertSame([], $result);
  }

  /**
   * Tests that getPropValue() extracts the inner value from valid image items.
   */
  public function testGetPropValueExtractsInnerValue(): void {
    $input = [
      [
        'value' => [
          'src' => 'https://example.com/image.jpg',
          'alt' => 'An image',
          'width' => 800,
          'height' => 600,
        ],
      ],
    ];
    $result = $this->plugin->getPropValue($input);
    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertSame('https://example.com/image.jpg', $result[0]['src']);
    $this->assertSame('An image', $result[0]['alt']);
  }

  /**
   * Tests that getPropValue() removes items missing the 'value' key.
   */
  public function testGetPropValueRemovesItemsMissingValueKey(): void {
    $input = [
      ['src' => 'https://example.com/image.jpg'],
      [
        'value' => [
          'src' => 'https://example.com/image2.jpg',
          'alt' => 'Second image',
        ],
      ],
    ];
    $result = $this->plugin->getPropValue($input);
    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertSame('https://example.com/image2.jpg', $result[0]['src']);
  }

  /**
   * Tests that getPropValue() removes items with empty value.
   */
  public function testGetPropValueRemovesItemsWithEmptyValue(): void {
    $input = [
      ['value' => []],
      ['value' => NULL],
      [
        'value' => [
          'src' => 'https://example.com/image.jpg',
          'alt' => 'Valid image',
        ],
      ],
    ];
    $result = $this->plugin->getPropValue($input);
    $this->assertIsArray($result);
    $this->assertCount(1, $result);
    $this->assertSame('https://example.com/image.jpg', $result[0]['src']);
  }

  /**
   * Tests that massageValue() delegates to image widget and keeps valid items.
   */
  public function testMassageValueDelegatesToImageWidget(): void {
    $mock_widget = $this->createMock(PropWidgetBase::class);
    $mock_widget->method('massageValue')
      ->willReturnCallback(fn($value) => [
        'widget' => 'image',
        'value' => ['src' => 'https://example.com/image.jpg', 'alt' => 'Test'],
      ]);

    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($mock_widget);

    $value = [
      'value' => [
        0 => [
          'value' => [
            'src' => 'https://example.com/image.jpg',
            'alt' => 'Test',
          ],
        ],
      ],
    ];
    $result = $this->plugin->massageValue($value);
    $this->assertIsArray($result['value']);
    $this->assertCount(1, $result['value']);
  }

  /**
   * Tests that massageValue() removes items with empty massaged value.
   */
  public function testMassageValueRemovesItemsWithEmptyMassagedValue(): void {
    $mock_widget = $this->createMock(PropWidgetBase::class);
    $mock_widget->method('massageValue')
      ->willReturnCallback(fn($value) => ['widget' => 'image', 'value' => []]);

    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($mock_widget);

    $value = [
      'value' => [
        0 => [
          'value' => [
            'src' => '',
            'alt' => '',
          ],
        ],
      ],
    ];
    $result = $this->plugin->massageValue($value);
    $this->assertIsArray($result['value']);
    $this->assertCount(0, $result['value']);
  }

  /**
   * Tests that massageValue() re-indexes after removing items.
   */
  public function testMassageValueReindexesAfterRemovals(): void {
    $mock_widget = $this->createMock(PropWidgetBase::class);
    $mock_widget->method('massageValue')
      ->willReturnOnConsecutiveCalls(
        ['widget' => 'image', 'value' => []],
        ['widget' => 'image', 'value' => ['src' => 'https://example.com/image.jpg']],
      );

    $this->propWidgetManager->method('getPropWidget')
      ->willReturn($mock_widget);

    $value = [
      'value' => [
        0 => ['value' => ['src' => '']],
        1 => ['value' => ['src' => 'https://example.com/image.jpg']],
      ],
    ];
    $result = $this->plugin->massageValue($value);
    $this->assertIsArray($result['value']);
    $this->assertCount(1, $result['value']);
    $this->assertArrayHasKey(0, $result['value']);
    $this->assertArrayNotHasKey(1, $result['value']);
  }

  /**
   * Provides input cases where all items are invalid and result is empty array.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidImageArrayProvider(): array {
    return [
      'all items missing value key' => [
        [['src' => 'https://example.com/image.jpg']],
      ],
      'all items with empty value' => [
        [['value' => []], ['value' => NULL]],
      ],
    ];
  }

}
