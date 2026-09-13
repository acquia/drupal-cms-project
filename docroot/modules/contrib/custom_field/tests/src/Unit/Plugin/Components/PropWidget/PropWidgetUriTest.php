<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetUri;
use Drupal\custom_field\Plugin\PropWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'uri' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetUri
 */
#[Group('custom_field')]
#[CoversClass(PropWidgetUri::class)]
class PropWidgetUriTest extends PropWidgetTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Url::fromUri() and Url::fromRoute() require a container with a router.
    $container = new ContainerBuilder();

    $unrouted_url_assembler = $this->createMock('\Drupal\Core\Utility\UnroutedUrlAssemblerInterface');
    $unrouted_url_assembler->method('assemble')
      ->willReturnCallback(function (string $uri): string {
        return $uri;
      });

    $url_generator = $this->createMock('\Drupal\Core\Routing\UrlGeneratorInterface');
    $url_generator->method('generateFromRoute')
      ->willReturnCallback(function (string $route) {
        return match ($route) {
          '<none>' => '',
          default => '/' . $route,
        };
      });
    $container->set('url_generator', $url_generator);
    $container->set('unrouted_url_assembler', $unrouted_url_assembler);
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function createPlugin(): PropWidgetBase {
    return $this->instantiatePlugin(PropWidgetUri::class, 'uri');
  }

  /**
   * Tests that defaultSettings() returns the expected keys and values.
   */
  public function testDefaultSettings(): void {
    $defaults = PropWidgetUri::defaultSettings();
    $this->assertArrayHasKey('format', $defaults);
    // Verify parent defaults are merged in.
    $this->assertArrayHasKey('title', $defaults);
    $this->assertArrayHasKey('description', $defaults);
    $this->assertArrayHasKey('default', $defaults);
    // Verify default values.
    $this->assertSame('uri', $defaults['format']);
  }

  /**
   * Tests that getPropValue() returns a string for valid URIs.
   *
   * @param mixed $input
   *   The input value to test.
   * @param string $expected
   *   The expected string return value.
   *
   * @dataProvider validUriProvider
   */
  #[DataProvider('validUriProvider')]
  public function testGetPropValueReturnsStringForValidUri(mixed $input, string $expected): void {
    $this->assertSame($expected, $this->plugin->getPropValue($input));
  }

  /**
   * Tests that getPropValue() returns NULL for empty or invalid input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidUriProvider
   */
  #[DataProvider('invalidUriProvider')]
  public function testGetPropValueReturnsNullForInvalidInput(mixed $input): void {
    $this->assertPropValueIsNull($input);
  }

  /**
   * Tests that massageValue() preserves valid URI values.
   *
   * @param string $input
   *   The input URI string.
   *
   * @dataProvider validMassageUriProvider
   */
  #[DataProvider('validMassageUriProvider')]
  public function testMassageValuePreservesValidUri(string $input): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertSame($input, $result['value']);
  }

  /**
   * Tests that massageValue() returns NULL for empty or invalid input.
   *
   * @param mixed $input
   *   The invalid input to test.
   *
   * @dataProvider invalidMassageUriProvider
   */
  #[DataProvider('invalidMassageUriProvider')]
  public function testMassageValueReturnsNullForInvalidInput(mixed $input): void {
    $result = $this->plugin->massageValue(['value' => $input]);
    $this->assertNull($result['value']);
  }

  /**
   * Provides valid URI input cases and their expected string values.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function validUriProvider(): array {
    return [
      'external http url' => ['http://example.com', 'http://example.com'],
      'external https url' => ['https://example.com', 'https://example.com'],
      'external url with path' => ['https://example.com/path', 'https://example.com/path'],
    ];
  }

  /**
   * Provides invalid URI input cases that should return NULL.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidUriProvider(): array {
    return [
      'null' => [NULL],
      'empty string' => [''],
      'empty array' => [[]],
      'boolean false' => [FALSE],
      'integer zero' => [0],
    ];
  }

  /**
   * Provides valid URI string cases for massageValue().
   *
   * @return array<string, array<string>>
   *   An array of test cases.
   */
  public static function validMassageUriProvider(): array {
    return [
      'external http url' => ['http://example.com'],
      'external https url' => ['https://example.com'],
      'internal path' => ['/node/1'],
    ];
  }

  /**
   * Provides invalid input cases for massageValue() that should return NULL.
   *
   * @return array<string, array<mixed>>
   *   An array of test cases.
   */
  public static function invalidMassageUriProvider(): array {
    return [
      'null' => [NULL],
      'empty string' => [''],
      'whitespace only' => ['   '],
    ];
  }

}
