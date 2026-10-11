<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\Core\Template\Attribute;
use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetAttributes;
use Drupal\custom_field\Plugin\PropWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'attributes' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetAttributes
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetAttributes::class)]
class PropWidgetAttributesTest extends PropWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(PropWidgetAttributes::class, 'attributes');
  }

  /**
   * Tests that getPropValue() returns NULL for non-array input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidInputProvider
   */
  #[DataProvider('invalidInputProvider')]
  public function testGetPropValueReturnsNullForNonArray(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Provides invalid input cases for getPropValue().
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidInputProvider(): array {
    return [
      'string' => ['not-an-array'],
      'integer' => [42],
      'null' => [NULL],
      'boolean' => [TRUE],
      'float' => [1.5],
    ];
  }

  /**
   * Tests that getPropValue() returns an Attribute instance for array input.
   */
  public function testGetPropValueReturnsAttributeInstance(): void {
    $result = $this->plugin->getPropValue(['class' => 'foo']);
    $this->assertInstanceOf(Attribute::class, $result);
  }

  /**
   * Tests that getPropValue() filters out empty values.
   */
  public function testGetPropValueFiltersEmptyValues(): void {
    $result = $this->plugin->getPropValue(['class' => 'foo', 'id' => '', 'title' => '']);
    $this->assertInstanceOf(Attribute::class, $result);
    $this->assertFalse(isset($result['id']));
    $this->assertFalse(isset($result['title']));
  }

  /**
   * Tests that getPropValue() returns an empty Attribute when values are empty.
   */
  public function testGetPropValueReturnsEmptyAttributeForAllEmptyValues(): void {
    $result = $this->plugin->getPropValue(['class' => '', 'id' => '']);
    $this->assertInstanceOf(Attribute::class, $result);
    $this->assertEmpty((string) $result);
  }

  /**
   * Tests that getPropValue() returns an empty Attribute for an empty array.
   */
  public function testGetPropValueReturnsEmptyAttributeForEmptyArray(): void {
    $result = $this->plugin->getPropValue([]);
    $this->assertInstanceOf(Attribute::class, $result);
    $this->assertEmpty((string) $result);
  }

  /**
   * Tests that getPropValue() preserves all valid attribute keys.
   */
  public function testGetPropValuePreservesValidAttributes(): void {
    $input = [
      'class' => 'foo',
      'id' => 'bar',
      'title' => 'baz',
      'aria-label' => 'qux',
    ];
    $result = $this->plugin->getPropValue($input);
    $this->assertInstanceOf(Attribute::class, $result);
    $this->assertTrue(isset($result['class']));
    $this->assertTrue(isset($result['id']));
    $this->assertTrue(isset($result['title']));
    $this->assertTrue(isset($result['aria-label']));
  }

  /**
   * Tests that massageValue() filters empty entries from the value array.
   */
  public function testMassageValueFiltersEmptyEntries(): void {
    $input = ['value' => ['class' => 'foo', 'id' => '', 'title' => '']];
    $result = $this->plugin->massageValue($input);
    $this->assertSame(['class' => 'foo'], $result['value']);
  }

  /**
   * Tests that massageValue() preserves all non-empty entries.
   */
  public function testMassageValuePreservesNonEmptyEntries(): void {
    $input = ['value' => ['class' => 'foo', 'id' => 'bar', 'title' => 'baz']];
    $result = $this->plugin->massageValue($input);
    $this->assertSame(['class' => 'foo', 'id' => 'bar', 'title' => 'baz'], $result['value']);
  }

  /**
   * Tests that massageValue() returns an empty array when all values are empty.
   */
  public function testMassageValueWithAllEmptyValues(): void {
    $input = ['value' => ['class' => '', 'id' => '', 'title' => '']];
    $result = $this->plugin->massageValue($input);
    $this->assertSame([], $result['value']);
  }

  /**
   * Tests that massageValue() returns an empty array for an empty value array.
   */
  public function testMassageValueWithEmptyArray(): void {
    $input = ['value' => []];
    $result = $this->plugin->massageValue($input);
    $this->assertSame([], $result['value']);
  }

}
