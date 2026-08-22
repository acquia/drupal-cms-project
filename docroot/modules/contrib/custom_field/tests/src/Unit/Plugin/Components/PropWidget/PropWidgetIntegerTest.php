<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetInteger;
use Drupal\custom_field\Plugin\PropWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'integer' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetInteger
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetInteger::class)]
class PropWidgetIntegerTest extends PropWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(PropWidgetInteger::class, 'integer');
  }

  /**
   * Tests that defaultSettings() returns the expected keys and values.
   */
  public function testDefaultSettings(): void {
    $defaults = PropWidgetInteger::defaultSettings();
    $this->assertArrayHasKey('minimum', $defaults);
    $this->assertArrayHasKey('maximum', $defaults);
    $this->assertArrayHasKey('enum', $defaults);
    $this->assertArrayHasKey('meta:enum', $defaults);
    // Verify parent defaults are merged in.
    $this->assertArrayHasKey('title', $defaults);
    $this->assertArrayHasKey('description', $defaults);
    $this->assertArrayHasKey('default', $defaults);
    $this->assertArrayHasKey('format', $defaults);
    // Verify default values.
    $this->assertSame('', $defaults['minimum']);
    $this->assertSame('', $defaults['maximum']);
    $this->assertSame([], $defaults['enum']);
    $this->assertSame([], $defaults['meta:enum']);
  }

  /**
   * Tests that getPropValue() returns an integer for valid numeric input.
   *
   * @param mixed $input
   *   The input value to test.
   * @param int $expected
   *   The expected integer return value.
   *
   * @dataProvider validIntegerProvider
   */
  #[DataProvider('validIntegerProvider')]
  public function testGetPropValueReturnsInteger(mixed $input, int $expected): void {
    $this->assertSame($expected, $this->plugin->getPropValue($input));
  }

  /**
   * Tests that getPropValue() returns NULL for invalid input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidIntegerProvider
   */
  #[DataProvider('invalidIntegerProvider')]
  public function testGetPropValueReturnsNullForInvalidInput(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that massageValue() casts valid numeric values to integers.
   *
   * @param mixed $input
   *   The input value to test.
   * @param int $expected
   *   The expected integer return value.
   *
   * @dataProvider validIntegerProvider
   */
  #[DataProvider('validIntegerProvider')]
  public function testMassageValueCastsToInteger(mixed $input, int $expected): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertSame($expected, $result['value']);
  }

  /**
   * Tests that massageValue() returns NULL for invalid input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidIntegerProvider
   */
  #[DataProvider('invalidIntegerProvider')]
  public function testMassageValueReturnsNullForInvalidInput(mixed $input): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertNull($result['value']);
  }

  /**
   * Provides valid numeric input cases and their expected integer values.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function validIntegerProvider(): array {
    return [
      'integer' => [42, 42],
      'integer zero' => [0, 0],
      'negative integer' => [-10, -10],
      'numeric string' => ['42', 42],
      'negative numeric string' => ['-10', -10],
      'float truncates to integer' => [3.9, 3],
      'numeric string with float' => ['3.9', 3],
    ];
  }

  /**
   * Provides invalid input cases that should return NULL.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidIntegerProvider(): array {
    return [
      'null' => [NULL],
      'empty string' => [''],
      'whitespace only' => ['   '],
      'non-numeric string' => ['foo'],
      'boolean true' => [TRUE],
      'boolean false' => [FALSE],
      'array' => [[]],
    ];
  }

}
