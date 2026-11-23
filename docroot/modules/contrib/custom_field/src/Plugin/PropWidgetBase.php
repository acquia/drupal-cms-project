<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\PluginSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for CustomField widget plugins.
 */
abstract class PropWidgetBase extends PluginSettingsBase implements PropWidgetInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The widget settings.
   *
   * @var array<string, mixed>
   */
  protected $settings;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The component prop widget manager.
   *
   * @var \Drupal\custom_field\PluginManager\PropWidgetManagerInterface
   */
  protected PropWidgetManagerInterface $propWidgetManager;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, array $settings, ModuleHandlerInterface $module_handler, PropWidgetManagerInterface $prop_widget_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settings = $settings;
    $this->moduleHandler = $module_handler;
    $this->propWidgetManager = $prop_widget_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $module_handler = $container->get('module_handler');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
        $configuration['settings'] ?? static::defaultSettings(),
      $module_handler,
      $container->get('plugin.manager.custom_field_component_prop_widget'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'title' => '',
      'description' => '',
      'default' => NULL,
      'format' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(string $property, mixed $value, int $indent): array {
    return [
      $this->t('@space@property: @value', [
        '@space' => $this->space($indent),
        '@property' => $property,
        '@value' => !is_scalar($value) || $value === '' ? self::EMPTY_VALUE : $value,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function space(int $indent = 2): string {
    return Html::decodeEntities(str_repeat(self::SPACE_CHARACTER, $indent));
  }

  /**
   * {@inheritdoc}
   */
  public function widget(array &$form, FormStateInterface $form_state, $value, $required, array $context = []): array {
    $settings = $this->getSettings() ?? static::defaultSettings();
    $description = !empty($settings['description']) ? $this->t('@description', ['@description' => $settings['description']]) : NULL;
    return [
      '#type' => 'container',
      'widget' => [
        '#type' => 'hidden',
        '#value' => $this->getPluginId(),
      ],
      'value' => [
        '#title' => $settings['title'],
        '#description' => $description,
        '#default_value' => $value['value'] ?? $settings['default'],
        '#required' => $required,
      ],
    ];
  }

  /**
   * Helper function to return widget settings label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->settings['label'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function calculateWidgetDependencies(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onWidgetDependencyRemoval(array $dependencies): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function massageValue(array $value): array {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(mixed $value, array $context = []): mixed {
    return $value;
  }

  /**
   * Recursively strips ignored keys from a nested array.
   *
   * @param array<string, mixed> $data
   *   The array to clean.
   *
   * @return array<string, mixed>
   *   The cleaned array.
   */
  protected static function stripIgnoredKeys(array $data): array {
    $ignored_keys = ['add_more', '_weight', '_actions'];
    foreach ($ignored_keys as $key) {
      unset($data[$key]);
    }
    foreach ($data as &$value) {
      if (\is_array($value)) {
        $value = self::stripIgnoredKeys($value);
      }
    }
    unset($value);
    return $data;
  }

}
