<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayNumber;
use Drupal\custom_field\Plugin\PropWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'array_number' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayNumber
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetArrayNumber::class)]
class PropWidgetArrayNumberTest extends PropWidgetArrayTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(PropWidgetArrayNumber::class, 'array_number');
  }

  /**
   * Tests that getPropValue() returns a filtered array of valid floats.
   *
   * @param mixed $input
   *   The input value to test.
   * @param array<float> $expected
   *   The expected array return value.
   *
   * @dataProvider validNumberArrayProvider
   */
  #[DataProvider('validNumberArrayProvider')]
  public function testGetPropValueReturnsFilteredNumberArray(mixed $input, array $expected): void {
    $this->assertSame($expected, $this->plugin->getPropValue($input));
  }

  /**
   * Tests that getPropValue() returns NULL for invalid input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidNumberArrayProvider
   */
  #[DataProvider('invalidNumberArrayProvider')]
  public function testGetPropValueReturnsNullForInvalidInput(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that massageValue() returns a filtered array of valid floats.
   *
   * @param mixed $input
   *   The input value to test.
   * @param array<float> $expected
   *   The expected array return value.
   *
   * @dataProvider validNumberArrayProvider
   */
  #[DataProvider('validNumberArrayProvider')]
  public function testMassageValueReturnsFilteredNumberArray(mixed $input, array $expected): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertSame($expected, $result['value']);
  }

  /**
   * Provides valid numeric input cases and their expected float array values.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function validNumberArrayProvider(): array {
    return [
      'floats' => [
        [1.5, 2.5, 3.5],
        [1.5, 2.5, 3.5],
      ],
      'integers cast to floats' => [
        [1, 2, 3],
        [1.0, 2.0, 3.0],
      ],
      'numeric strings cast to floats' => [
        ['1.5', '2.5', '3.5'],
        [1.5, 2.5, 3.5],
      ],
      'negative floats' => [
        [-1.5, -2.5, -3.5],
        [-1.5, -2.5, -3.5],
      ],
      'mixed numeric types' => [
        [1, '2.5', 3.9],
        [1.0, 2.5, 3.9],
      ],
      'filters empty strings' => [
        [1.5, '', 3.5],
        [1.5, 3.5],
      ],
      'single item' => [
        [1.5],
        [1.5],
      ],
    ];
  }

  /**
   * Provides number-specific invalid input cases that should return NULL.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidNumberArrayProvider(): array {
    return [
      'all empty strings' => [['', '', '']],
      'non-numeric strings' => [['foo', 'bar']],
      'boolean values' => [[TRUE, FALSE]],
    ];
  }

}
