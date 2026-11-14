<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Kernel\Plugin\Components\PropWidget;

use Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetUri;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the 'uri' PropWidget plugin.
 *
 * @group custom_field
 * @covers \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetUri
 * @runTestsInSeparateProcesses
 */
#[CoversClass(PropWidgetUri::class)]
#[Group('custom_field')]
#[RunTestsInSeparateProcesses]
class PropWidgetUriTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_field',
    'system',
  ];

  /**
   * The plugin under test.
   *
   * @var \Drupal\custom_field\Plugin\Components\PropWidget\PropWidgetUri
   */
  protected PropWidgetUri $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->plugin = $this->container
      ->get('plugin.manager.custom_field_component_prop_widget')
      ->createInstance('uri');
  }

  /**
   * Tests that getPropValue() returns NULL for empty input.
   */
  public function testGetPropValueReturnsNullForEmptyInput(): void {
    $this->assertNull($this->plugin->getPropValue(NULL));
    $this->assertNull($this->plugin->getPropValue(''));
    $this->assertNull($this->plugin->getPropValue([]));
    $this->assertNull($this->plugin->getPropValue(FALSE));
  }

  /**
   * Tests that getPropValue() returns a string for a valid external URL.
   */
  public function testGetPropValueReturnsStringForExternalUrl(): void {
    $result = $this->plugin->getPropValue('https://example.com');
    $this->assertIsString($result);
    $this->assertStringContainsString('example.com', $result);
  }

  /**
   * Tests that getPropValue() returns a string for a valid external HTTP URL.
   */
  public function testGetPropValueReturnsStringForHttpUrl(): void {
    $result = $this->plugin->getPropValue('http://example.com/path');
    $this->assertIsString($result);
    $this->assertStringContainsString('example.com/path', $result);
  }

  /**
   * Tests that getPropValue() handles invalid URIs gracefully.
   */
  public function testGetPropValueHandlesInvalidUriGracefully(): void {
    // Invalid URIs should fall back to Url::fromRoute('<none>') which
    // returns an empty string rather than throwing an exception.
    $result = $this->plugin->getPropValue('not-a-valid-uri');
    $this->assertIsString($result);
  }

}
