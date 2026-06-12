<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayString;
use Drupal\custom_field\Plugin\PropWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'array_string' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetArrayString
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetArrayString::class)]
class PropWidgetArrayStringTest extends PropWidgetArrayTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(PropWidgetArrayString::class, 'array_string');
  }

  /**
   * Tests that getPropValue() returns a filtered array for valid string input.
   *
   * @param mixed $input
   *   The input value to test.
   * @param array<string> $expected
   *   The expected array return value.
   *
   * @dataProvider validArrayProvider
   */
  #[DataProvider('validArrayProvider')]
  public function testGetPropValueReturnsFilteredArray(mixed $input, array $expected): void {
    $this->assertSame($expected, $this->plugin->getPropValue($input));
  }

  /**
   * Tests that getPropValue() returns NULL when all items are invalid strings.
   *
   * @param mixed $input
   *   The input value to test.
   *
   * @dataProvider invalidStringArrayProvider
   */
  #[DataProvider('invalidStringArrayProvider')]
  public function testGetPropValueReturnsNullForInvalidStringItems(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that massageValue() returns a filtered array for valid string input.
   *
   * @param mixed $input
   *   The input value to test.
   * @param array<string> $expected
   *   The expected array return value.
   *
   * @dataProvider validArrayProvider
   */
  #[DataProvider('validArrayProvider')]
  public function testMassageValueReturnsFilteredArray(mixed $input, array $expected): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertSame($expected, $result['value']);
  }

  /**
   * Tests that massageValue() returns an empty array when items are invalid.
   *
   * @param mixed $input
   *   The input value to test.
   *
   * @dataProvider invalidStringArrayProvider
   */
  #[DataProvider('invalidStringArrayProvider')]
  public function testMassageValueReturnsEmptyArrayForInvalidStringItems(mixed $input): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertSame([], $result['value']);
  }

  /**
   * Provides valid string array input cases and their expected filtered values.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function validArrayProvider(): array {
    return [
      'simple strings' => [
        ['tag1', 'tag2', 'tag3'],
        ['tag1', 'tag2', 'tag3'],
      ],
      'filters empty strings' => [
        ['tag1', '', 'tag3'],
        ['tag1', 'tag3'],
      ],
      'filters whitespace only strings' => [
        ['tag1', '   ', 'tag3'],
        ['tag1', 'tag3'],
      ],
      'single item' => [
        ['tag1'],
        ['tag1'],
      ],
    ];
  }

  /**
   * Provides string-specific invalid input cases that should return NULL.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidStringArrayProvider(): array {
    return [
      'all empty strings' => [['', '   ', '']],
      'all whitespace' => [['   ', "\t", "\n"]],
    ];
  }

}
