<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the custom field formatter plugin manager.
 */
class CustomFieldFormatterManager extends DefaultPluginManager implements CustomFieldFormatterManagerInterface {

  /**
   * Constructs a new CustomFieldFormatterManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface $customFieldTypeManager
   *   The custom field type manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, protected CustomFieldTypeManagerInterface $customFieldTypeManager) {
    parent::__construct(
      'Plugin/CustomField/FieldFormatter',
      $namespaces,
      $module_handler,
      'Drupal\custom_field\Plugin\CustomFieldFormatterInterface',
      FieldFormatter::class,
      'Drupal\Core\Field\Annotation\FieldFormatter'
    );

    $this->setCacheBackend($cache_backend, 'custom_field_formatter_plugins');
    $this->alterInfo('custom_field_formatter_info');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);

    // @todo This is copied from \Drupal\Core\Plugin\Factory\ContainerFactory.
    //   Find a way to restore sanity to
    //   \Drupal\Core\Field\FormatterBase::__construct().
    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      // @todo Find a better way to solve this, if possible at all.
      // @phpstan-ignore-next-line
      return $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition);
    }

    return new $plugin_class($plugin_id, $plugin_definition, $configuration['custom_field_definition'], $configuration['settings'], $configuration['view_mode'], $configuration['third_party_settings']);
  }

  /**
   * {@inheritdoc}
   */
  public function createOptionsForInstance($custom_item, string $format_type, array $formatter_settings, string $view_mode): array {
    return [
      'custom_field_definition' => $custom_item,
      'configuration' => [
        'type' => $format_type,
        'settings' => $formatter_settings,
      ],
      'view_mode' => $view_mode,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options): ?CustomFieldFormatterInterface {
    try {
      $configuration = $options['configuration'];
      $custom_field_definition = $options['custom_field_definition'];

      assert($custom_field_definition instanceof CustomFieldTypeInterface);
      $field_type = $custom_field_definition->getDataType();

      // @todo Which subfield type uses this? Can this be dropped?
      // Fill in default configuration if needed.
      if (!isset($options['prepare']) || $options['prepare'] == TRUE) {
        $configuration = $this->prepareConfiguration($field_type, $configuration);
      }

      $plugin_id = $configuration['type'];

      // Switch back to default formatter if either:
      // - the configuration does not specify a formatter class
      // - the field type is not allowed for the formatter
      // - the formatter is not applicable to the field definition.
      $definition = $this->getDefinition($configuration['type'], FALSE);
      if (!isset($definition['class']) || !in_array($field_type, $definition['field_types']) || !$definition['class']::isApplicable($custom_field_definition)) {

        // Grab the default formatter for the field type.
        $field_type_definition = $this->customFieldTypeManager->getDefinition($field_type);
        if (empty($field_type_definition['default_formatter'])) {
          return NULL;
        }
        $plugin_id = $field_type_definition['default_formatter'];
      }

      $configuration += [
        'custom_field_definition' => $custom_field_definition,
        'view_mode' => $options['view_mode'] ?? 'default',
      ];
      /** @var \Drupal\custom_field\Plugin\CustomFieldFormatterInterface $instance */
      $instance = $this->createInstance($plugin_id, $configuration);
      return $instance;
    }
    catch (\Exception $exception) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings(string $type): array {
    try {
      $plugin_definition = $this->getDefinition($type, FALSE);
      if (!empty($plugin_definition['class'])) {
        /** @var \Drupal\custom_field\Plugin\CustomFieldFormatterInterface $plugin_class */
        $plugin_class = DefaultFactory::getPluginClass($type, $plugin_definition);
        return $plugin_class::defaultSettings();
      }
    }
    catch (\Exception $exception) {
      // Silent fail, for now.
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatterValueKeys(FormStateInterface $form_state, string $field_name): array {
    $form_id = $form_state->getFormObject()->getFormId();
    $value_keys = [
      'fields',
      $field_name,
      'settings_edit_form',
      'settings',
    ];

    switch ($form_id) {
      case 'views_ui_config_item_form':
        $value_keys = [
          'options',
          'settings',
        ];
        break;

      case 'block_form':
        $value_keys = [
          'settings',
          'formatter_settings',
        ];
        break;

      case 'layout_builder_add_block':
      case 'layout_builder_update_block':
        $value_keys = [
          'settings',
          'formatter',
          'settings',
        ];
        break;

      default:
        break;
    }

    return $value_keys;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputPathForStatesApi(FormStateInterface $form_state, string $field_name, string $property, bool $is_views_subfield = FALSE, string $wrapper = 'fields'): string {
    $form_id = $form_state->getFormObject()->getFormId();
    $segments = match ($form_id) {
      'views_ui_config_item_form' => $is_views_subfield
        ? ['options', 'settings']
        : ['options', 'settings', $wrapper, $property, 'formatter_settings'],
      'block_form' => ['settings', 'formatter_settings', $wrapper, $property, 'formatter_settings'],
      'layout_builder_add_block',
      'layout_builder_update_block' => ['settings', 'formatter', 'settings', $wrapper, $property, 'formatter_settings'],
      default => ['fields', $field_name, 'settings_edit_form', 'settings', $wrapper, $property, 'formatter_settings'],
    };

    $path = array_shift($segments);
    foreach ($segments as $segment) {
      $path .= '[' . $segment . ']';
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getOptions(CustomFieldTypeInterface $custom_field): array {
    $field_type = $custom_field->getPluginId();
    $candidates = [];
    foreach ($this->getDefinitions() as $id => $definition) {
      /** @var \Drupal\custom_field\Plugin\CustomFieldFormatterInterface $plugin_class */
      $plugin_class = DefaultFactory::getPluginClass($id, $definition);
      $is_applicable = $plugin_class::isApplicable($custom_field);
      if (!\in_array($field_type, $definition['field_types']) || !$is_applicable) {
        continue;
      }
      $candidates[$id] = $definition + ['weight' => 0];
    }
    // Sort by weight, then label.
    \uasort($candidates, function (array $a, array $b): int {
      if ($a['weight'] !== $b['weight']) {
        return $a['weight'] <=> $b['weight'];
      }
      return \strcmp((string) $a['label'], (string) $b['label']);
    });

    return \array_map(function ($definition) {
      return $definition['label'];
    }, $candidates);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function prepareConfiguration(string $field_type, array $configuration): array {
    // Fill in defaults for missing properties.
    $configuration += [
      'settings' => [],
      'third_party_settings' => [],
    ];

    // If no formatter is specified, use the default formatter.
    if (!isset($configuration['type'])) {
      $field_type = $this->customFieldTypeManager->getDefinition($field_type);
      $configuration['type'] = $field_type['default_formatter'];
    }
    // Filter out unknown settings, and fill in defaults for missing settings.
    $default_settings = $this->getDefaultSettings($configuration['type']);
    $configuration['settings'] = \array_intersect_key($configuration['settings'], $default_settings) + $default_settings;

    return $configuration;
  }

}
