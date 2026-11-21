<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayInteger;
use Drupal\custom_field\Plugin\PropWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'array_integer' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayInteger
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetArrayInteger::class)]
class PropWidgetArrayIntegerTest extends PropWidgetArrayTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(PropWidgetArrayInteger::class, 'array_integer');
  }

  /**
   * Tests that getPropValue() returns a filtered array of valid integers.
   *
   * @param mixed $input
   *   The input value to test.
   * @param array<int> $expected
   *   The expected array return value.
   *
   * @dataProvider validIntegerArrayProvider
   */
  #[DataProvider('validIntegerArrayProvider')]
  public function testGetPropValueReturnsFilteredIntegerArray(mixed $input, array $expected): void {
    $this->assertSame($expected, $this->plugin->getPropValue($input));
  }

  /**
   * Tests that getPropValue() returns NULL for invalid input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidIntegerArrayProvider
   */
  #[DataProvider('invalidIntegerArrayProvider')]
  public function testGetPropValueReturnsNullForInvalidInput(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that massageValue() returns a filtered array of valid integers.
   *
   * @param mixed $input
   *   The input value to test.
   * @param array<int> $expected
   *   The expected array return value.
   *
   * @dataProvider validIntegerArrayProvider
   */
  #[DataProvider('validIntegerArrayProvider')]
  public function testMassageValueReturnsFilteredIntegerArray(mixed $input, array $expected): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertSame($expected, $result['value']);
  }

  /**
   * Provides valid numeric input cases and their expected integer array values.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function validIntegerArrayProvider(): array {
    return [
      'integers' => [
        [1, 2, 3],
        [1, 2, 3],
      ],
      'numeric strings cast to integers' => [
        ['1', '2', '3'],
        [1, 2, 3],
      ],
      'floats truncated to integers' => [
        [1.9, 2.5, 3.1],
        [1, 2, 3],
      ],
      'negative integers' => [
        [-1, -2, -3],
        [-1, -2, -3],
      ],
      'mixed numeric types' => [
        [1, '2', 3.9],
        [1, 2, 3],
      ],
      'filters empty strings' => [
        [1, '', 3],
        [1, 3],
      ],
      'single item' => [
        [42],
        [42],
      ],
    ];
  }

  /**
   * Provides integer-specific invalid input cases that should return NULL.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidIntegerArrayProvider(): array {
    return [
      'all empty strings' => [['', '', '']],
      'non-numeric strings' => [['foo', 'bar']],
      'boolean values' => [[TRUE, FALSE]],
    ];
  }

}
