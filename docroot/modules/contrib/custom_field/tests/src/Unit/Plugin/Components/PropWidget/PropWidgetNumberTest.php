<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetNumber;
use Drupal\custom_field\Plugin\PropWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'number' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetNumber
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetNumber::class)]
class PropWidgetNumberTest extends PropWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(PropWidgetNumber::class, 'number');
  }

  /**
   * Tests that defaultSettings() returns the expected keys and values.
   */
  public function testDefaultSettings(): void {
    $defaults = PropWidgetNumber::defaultSettings();
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
   * Tests that getPropValue() returns a float for valid numeric input.
   *
   * @param mixed $input
   *   The input value to test.
   * @param float $expected
   *   The expected float return value.
   *
   * @dataProvider validNumberProvider
   */
  #[DataProvider('validNumberProvider')]
  public function testGetPropValueReturnsFloat(mixed $input, float $expected): void {
    $this->assertSame($expected, $this->plugin->getPropValue($input));
  }

  /**
   * Tests that getPropValue() returns NULL for invalid input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidNumberProvider
   */
  #[DataProvider('invalidNumberProvider')]
  public function testGetPropValueReturnsNullForInvalidInput(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that massageValue() casts valid numeric values to floats.
   *
   * @param mixed $input
   *   The input value to test.
   * @param float $expected
   *   The expected float return value.
   *
   * @dataProvider validNumberProvider
   */
  #[DataProvider('validNumberProvider')]
  public function testMassageValueCastsToFloat(mixed $input, float $expected): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertSame($expected, $result['value']);
  }

  /**
   * Tests that massageValue() returns NULL for invalid input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidNumberProvider
   */
  #[DataProvider('invalidNumberProvider')]
  public function testMassageValueReturnsNullForInvalidInput(mixed $input): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertNull($result['value']);
  }

  /**
   * Provides valid numeric input cases and their expected float values.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function validNumberProvider(): array {
    return [
      'float' => [3.14, 3.14],
      'integer cast to float' => [42, 42.0],
      'negative float' => [-1.5, -1.5],
      'negative integer cast to float' => [-10, -10.0],
      'numeric string float' => ['3.14', 3.14],
      'numeric string integer' => ['42', 42.0],
      'zero' => [0, 0.0],
      'zero float' => [0.0, 0.0],
    ];
  }

  /**
   * Provides invalid input cases that should return NULL.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidNumberProvider(): array {
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
