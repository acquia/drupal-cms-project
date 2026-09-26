<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetString;
use Drupal\custom_field\Plugin\PropWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'string' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetString
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetString::class)]
class PropWidgetStringTest extends PropWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(PropWidgetString::class, 'string');
  }

  /**
   * Tests that defaultSettings() returns the expected keys and values.
   */
  public function testDefaultSettings(): void {
    $defaults = PropWidgetString::defaultSettings();
    $this->assertArrayHasKey('maxlength', $defaults);
    $this->assertArrayHasKey('pattern', $defaults);
    $this->assertArrayHasKey('enum', $defaults);
    $this->assertArrayHasKey('meta:enum', $defaults);
    // Verify parent defaults are merged in.
    $this->assertArrayHasKey('title', $defaults);
    $this->assertArrayHasKey('description', $defaults);
    $this->assertArrayHasKey('default', $defaults);
    $this->assertArrayHasKey('format', $defaults);
    // Verify default values.
    $this->assertSame('', $defaults['maxlength']);
    $this->assertSame('', $defaults['pattern']);
    $this->assertSame([], $defaults['enum']);
    $this->assertSame([], $defaults['meta:enum']);
  }

  /**
   * Tests that getPropValue() returns scalar string values as-is.
   *
   * @param mixed $input
   *   The input value to test.
   * @param mixed $expected
   *   The expected return value.
   *
   * @dataProvider scalarValueProvider
   */
  #[DataProvider('scalarValueProvider')]
  public function testGetPropValueReturnsScalarValues(mixed $input, mixed $expected): void {
    $this->assertSame($expected, $this->plugin->getPropValue($input));
  }

  /**
   * Tests that getPropValue() returns NULL for empty strings.
   *
   * @param string $input
   *   The empty or whitespace-only string to test.
   *
   * @dataProvider emptyStringProvider
   */
  #[DataProvider('emptyStringProvider')]
  public function testGetPropValueReturnsNullForEmptyStrings(string $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that getPropValue() returns NULL for non-string values.
   *
   * @param mixed $input
   *   The non-string input to test.
   *
   * @dataProvider nonStringValueProvider
   */
  #[DataProvider('nonStringValueProvider')]
  public function testGetPropValueReturnsNullForNonStringValues(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that massageValue() returns NULL for empty strings.
   *
   * @param string $input
   *   The empty or whitespace-only string to test.
   *
   * @dataProvider emptyStringProvider
   */
  #[DataProvider('emptyStringProvider')]
  public function testMassageValueReturnsNullForEmptyStrings(string $input): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertNull($result['value']);
  }

  /**
   * Tests that massageValue() preserves non-empty string values.
   *
   * @param mixed $input
   *   The input value to test.
   * @param mixed $expected
   *   The expected return value.
   *
   * @dataProvider scalarValueProvider
   */
  #[DataProvider('scalarValueProvider')]
  public function testMassageValuePreservesNonEmptyValues(mixed $input, mixed $expected): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertSame($expected, $result['value']);
  }

  /**
   * Provides scalar value cases for getPropValue() and massageValue().
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function scalarValueProvider(): array {
    return [
      'simple string' => ['hello', 'hello'],
      'string with spaces' => ['hello world', 'hello world'],
      'string with html characters' => ['<b>bold</b>', '<b>bold</b>'],
      'numeric string' => ['42', '42'],
    ];
  }

  /**
   * Provides empty and whitespace-only string cases.
   *
   * @return array<string, array<string>>
   *   An array of test cases.
   */
  public static function emptyStringProvider(): array {
    return [
      'empty string' => [''],
      'whitespace only' => ['   '],
      'tab character' => ["\t"],
      'newline character' => ["\n"],
    ];
  }

  /**
   * Provides non-string value cases for getPropValue().
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function nonStringValueProvider(): array {
    return [
      'null' => [NULL],
      'integer' => [42],
      'array' => [[]],
      'boolean true' => [TRUE],
      'boolean false' => [FALSE],
    ];
  }

}
