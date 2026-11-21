<?php

declare(strict_types=1);

namespace Drupal\custom_field_test\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Random;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for field type test plugins.
 */
abstract class FieldTypeTestBase extends PluginBase implements FieldTypeTestInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The content translation manager, if available.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface|null
   */
  protected ?ContentTranslationManagerInterface $contentTranslationManager;


  /**
   * The random utility class.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected Random $random;

  /**
   * Construct a CustomFieldType plugin instance.
   *
   * @param string $plugin_id
   *   The plugin ID for the field type.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array<string, mixed> $settings
   *   The field settings.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface|null $content_translation_manager
   *   The content translation manager.
   */
  public function __construct(string $plugin_id, mixed $plugin_definition, array $settings, ModuleHandlerInterface $module_handler, ?ContentTranslationManagerInterface $content_translation_manager = NULL) {
    parent::__construct([], $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
    $this->contentTranslationManager = $content_translation_manager;
    $this->random = new Random();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $module_handler = $container->get('module_handler');
    $content_translation_manager = $module_handler->moduleExists('content_translation')
      ? $container->get('content_translation.manager')
      : NULL;
    return new static(
      $plugin_id,
      $plugin_definition,
        $configuration['settings'] ?? [],
      $module_handler,
      $content_translation_manager,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function testCases(string $name, array $settings): array {
    return [];
  }

  /**
   * Helper function to build a test case.
   *
   * @param string|string[] $property
   *   The property name(s) for the test case.
   * @param mixed $value
   *   The expected value for the test case.
   * @param bool $violation
   *   A boolean to determine if test case should trigger a violation.
   * @param string|null $message
   *   The expected violation message to test for.
   * @param array $new_settings
   *   Optional altered field definition settings needed for test case.
   *
   * @return array
   *   An array of options to test.
   */
  protected function buildTestCase(mixed $property, mixed $value, bool $violation = FALSE, ?string $message = NULL, array $new_settings = []): array {
    return [
      $property,
      $value,
      $violation,
      $message,
      $new_settings,
    ];
  }

}
