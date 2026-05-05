<?php

namespace Drupal\custom_field\Plugin;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\PluginSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for CustomField formatter plugins.
 */
abstract class CustomFieldFormatterBase extends PluginSettingsBase implements CustomFieldFormatterInterface, ContainerFactoryPluginInterface {

  /**
   * The custom field definition.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeInterface|null
   */
  protected ?CustomFieldTypeInterface $customFieldDefinition;

  /**
   * The view mode.
   *
   * @var string
   */
  protected string $viewMode;

  /**
   * Constructs a CustomFieldFormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface|null $custom_field_definition
   *   The custom field definition.
   * @param array $settings
   *   The formatter settings.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   */
  final public function __construct($plugin_id, $plugin_definition, ?CustomFieldTypeInterface $custom_field_definition, array $settings, $view_mode, array $third_party_settings) {
    parent::__construct([], $plugin_id, $plugin_definition);
    $this->customFieldDefinition = $custom_field_definition;
    $this->settings = $settings;
    $this->viewMode = $view_mode;
    $this->thirdPartySettings = $third_party_settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($plugin_id, $plugin_definition, $configuration['custom_field_definition'] ?? NULL, $configuration['settings'] ?? [], $configuration['view_mode'] ?? '', $configuration['third_party_settings'] ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function formatValue(FieldItemInterface $item, mixed $value): mixed {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateFormatterDependencies(array $settings): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onFormatterDependencyRemoval(array $dependencies, array $settings): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(CustomFieldTypeInterface $custom_item): bool {
    // By default, formatters are available for all fields.
    return TRUE;
  }

}
