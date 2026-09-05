<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetBoolean;
use Drupal\custom_field\Plugin\PropWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'boolean' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetBoolean
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetBoolean::class)]
class PropWidgetBooleanTest extends PropWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(PropWidgetBoolean::class, 'boolean');
  }

  /**
   * Tests that getPropValue() returns the correct boolean for valid input.
   *
   * @param mixed $input
   *   The input value to test.
   * @param bool $expected
   *   The expected boolean return value.
   *
   * @dataProvider validBooleanProvider
   */
  #[DataProvider('validBooleanProvider')]
  public function testGetPropValueReturnsBool(mixed $input, bool $expected): void {
    $this->assertSame($expected, $this->plugin->getPropValue($input));
  }

  /**
   * Tests that getPropValue() returns NULL for invalid input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidBooleanProvider
   */
  #[DataProvider('invalidBooleanProvider')]
  public function testGetPropValueReturnsNullForInvalidInput(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that massageValue() casts values to boolean correctly.
   *
   * @param mixed $input
   *   The input value to test.
   * @param bool $expected
   *   The expected boolean return value.
   *
   * @dataProvider massageBooleanProvider
   */
  #[DataProvider('massageBooleanProvider')]
  public function testMassageValueCastsToBool(mixed $input, bool $expected): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertSame($expected, $result['value']);
  }

  /**
   * Provides valid input cases and their expected boolean values.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function validBooleanProvider(): array {
    return [
      'true' => [TRUE, TRUE],
      'false' => [FALSE, FALSE],
      'integer one' => [1, TRUE],
      'integer zero' => [0, FALSE],
      'null' => [NULL, FALSE],
    ];
  }

  /**
   * Provides invalid input cases that should return NULL.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidBooleanProvider(): array {
    return [
      'string' => ['foo'],
      'empty string' => [''],
      'array' => [[]],
    ];
  }

  /**
   * Provides input cases for massageValue() and their expected boolean values.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function massageBooleanProvider(): array {
    return [
      'true' => [TRUE, TRUE],
      'false' => [FALSE, FALSE],
      'integer one' => [1, TRUE],
      'integer zero' => [0, FALSE],
      'empty string' => ['', FALSE],
      'non-empty string' => ['foo', TRUE],
      'null' => [NULL, FALSE],
      'empty array' => [[], FALSE],
    ];
  }

}
