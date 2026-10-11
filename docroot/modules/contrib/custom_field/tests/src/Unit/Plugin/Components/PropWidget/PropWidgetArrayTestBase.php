<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

/**
 * Base class for array PropWidget plugin unit tests.
 */
abstract class PropWidgetArrayTestBase extends PropWidgetTestBase {

  /**
   * Tests that defaultSettings() contains the expected array base keys.
   */
  public function testDefaultSettingsContainsArrayBaseKeys(): void {
    $defaults = $this->plugin->defaultSettings();
    $this->assertArrayHasKey('maxItems', $defaults);
    $this->assertArrayHasKey('items', $defaults);
    $this->assertSame('', $defaults['maxItems']);
    $this->assertSame(['type' => ''], $defaults['items']);
    // Verify parent defaults are merged in.
    $this->assertArrayHasKey('title', $defaults);
    $this->assertArrayHasKey('description', $defaults);
    $this->assertArrayHasKey('default', $defaults);
    $this->assertArrayHasKey('format', $defaults);
  }

  /**
   * Tests that getPropValue() returns NULL for structurally invalid input.
   */
  public function testGetPropValueReturnsNullForNonArray(): void {
    $this->assertPropValueIsNull(NULL);
    $this->assertPropValueIsNull([]);
    $this->assertPropValueIsNull('foo');
    $this->assertPropValueIsNull(42);
  }

  /**
   * Tests that massageValue() returns an empty array for invalid input.
   */
  public function testMassageValueReturnsEmptyArrayForInvalidInput(): void {
    $this->assertSame([], $this->plugin->massageValue(['value' => NULL])['value']);
    $this->assertSame([], $this->plugin->massageValue(['value' => []])['value']);
    $this->assertSame([], $this->plugin->massageValue(['value' => 'foo'])['value']);
  }

}
