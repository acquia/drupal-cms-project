<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_field\Unit\Plugin\Components\PropWidget;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\custom_field\Plugin\PropWidgetBase;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Base class for PropWidget plugin unit tests.
 */
abstract class PropWidgetTestBase extends UnitTestCase {

  /**
   * The plugin under test.
   *
   * @var \Drupal\custom_field\Plugin\PropWidgetBase
   */
  protected PropWidgetBase $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->plugin = $this->createPlugin();
    $this->plugin->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Creates the plugin instance under test.
   *
   * @return \Drupal\custom_field\Plugin\PropWidgetBase
   *   The plugin instance to test.
   */
  abstract protected function createPlugin(): PropWidgetBase;

  /**
   * Instantiates a PropWidget plugin with mocked dependencies for testing.
   *
   * @param class-string<\Drupal\custom_field\Plugin\PropWidgetBase> $class
   *   The fully-qualified class name of the PropWidget plugin to instantiate.
   * @param string $plugin_id
   *   The plugin ID to pass to the plugin constructor.
   * @param array<string, mixed> $settings
   *   (optional) Plugin settings to pass to the constructor. Defaults to the
   *   plugin's default settings when not provided.
   * @param \Drupal\custom_field\PluginManager\PropWidgetManagerInterface|null $prop_widget_manager
   *   (optional) A prop widget manager to use. Defaults to a basic mock when
   *   not provided.
   *
   * @return \Drupal\custom_field\Plugin\PropWidgetBase
   *   The instantiated plugin with mocked module handler and prop widget
   *   manager.
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   *   Thrown if the mock objects cannot be created.
   */
  protected function instantiatePlugin(string $class, string $plugin_id, array $settings = [], ?PropWidgetManagerInterface $prop_widget_manager = NULL): PropWidgetBase {
    return new $class(
      [],
      $plugin_id,
      [
        'id' => $plugin_id,
        'label' => $plugin_id,
      ],
      $settings ?: PropWidgetBase::defaultSettings(),
      $this->createMock(ModuleHandlerInterface::class),
      $prop_widget_manager ?? $this->createMock(PropWidgetManagerInterface::class),
    );
  }

  /**
   * Asserts that getPropValue() returns NULL for the given input.
   *
   * Use this to verify that a plugin correctly rejects invalid or unsupported
   * input types rather than returning a value.
   *
   * @param mixed $input
   *   The input value expected to produce a NULL result.
   */
  protected function assertPropValueIsNull(mixed $input): void {
    $this->assertNull(
      $this->plugin->getPropValue($input),
      sprintf(
        'Expected getPropValue() to return NULL for input of type %s.',
        get_debug_type($input),
      ),
    );
  }

}
