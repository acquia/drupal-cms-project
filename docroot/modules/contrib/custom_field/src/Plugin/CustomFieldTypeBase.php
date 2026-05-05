<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for CustomField Type plugins.
 */
abstract class CustomFieldTypeBase extends PluginBase implements CustomFieldTypeInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The custom field separator for extended properties.
   *
   * @var string
   */
  const SEPARATOR = '__';

  /**
   * The default max length for string fields.
   *
   * @var int
   */
  const MAX_LENGTH = 255;

  /**
   * The field settings.
   *
   * @var array<string, mixed>
   */
  protected array $settings;

  /**
   * The name of the custom field item.
   *
   * @var string
   */
  protected string $name = 'value';

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
    // Initialize properties based on configuration.
    $this->settings = $settings;
    $this->name = $settings['name'] ?? 'value';
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
  public static function defaultFieldSettings(): array {
    return [
      'label' => '',
      'required' => FALSE,
      'translatable' => FALSE,
      'description' => '',
      'description_display' => 'after',
      'check_empty' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\field_ui\Form\FieldConfigEditForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Core\Field\FieldConfigInterface $field_definition */
    $field_definition = $form_object->getEntity();
    $bundle_is_translatable = FALSE;
    if ($this->contentTranslationManager) {
      $bundle_is_translatable = $this->contentTranslationManager->isEnabled($field_definition->getTargetEntityTypeId(), $field_definition->getTargetBundle());
    }
    $settings = $this->getFieldSettings();

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->getLabel(),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $element['required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Required'),
      '#description' => $this->t('This setting is only applicable when the field itself is required.'),
      '#default_value' => $settings['required'],
      '#states' => [
        'visible' => [
          ':input[name="required"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['check_empty_warning'] = [
      '#type' => 'container',
      'text' => [
        '#markup' => $this->t('<strong>Warning:</strong> Both new and existing %field values with empty %subfield will be discarded when the @entity entity is saved.', [
          '%field' => $field_definition->getName(),
          '%subfield' => $this->getName(),
          '@entity' => $field_definition->getTargetEntityTypeId(),
        ]),
      ],
      '#attributes' => [
        'class' => ['messages', 'messages--warning'],
      ],
      '#states' => [
        'visible' => [
          ':input[name="settings[field_settings][' . $this->getName() . '][check_empty]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['check_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Discard other values if this field is empty'),
      '#description' => $this->t('If this sub-field is left blank, clear all other sub-field values in the same row.'),
      '#default_value' => $settings['check_empty'] ?? FALSE,
    ];
    $element['translatable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Users may translate this field'),
      '#default_value' => $settings['translatable'],
      '#states' => [
        'visible' => [
          ':input[name="translatable"]' => ['checked' => TRUE],
        ],
      ],
      '#access' => $this->moduleHandler->moduleExists('content_translation') && $bundle_is_translatable,
    ];
    $element['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Help text'),
      '#description' => $this->t('Instructions to present to the user for this field on the editing form.'),
      '#rows' => 2,
      '#default_value' => $settings['description'],
    ];
    $element['description_display'] = [
      '#type' => 'radios',
      '#title' => $this->t('Help text position'),
      '#options' => [
        'before' => $this->t('Before input'),
        'after' => $this->t('After input'),
      ],
      '#default_value' => $settings['description_display'],
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function value(FieldItemInterface $item): mixed {
    return $item->{$this->name};
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormatter(): string {
    /** @var array<string, mixed> $definition */
    $definition = $this->getPluginDefinition();
    return $definition['default_formatter'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultWidget(): string {
    /** @var array<string, mixed> $definition */
    $definition = $this->getPluginDefinition();
    return $definition['default_widget'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->getFieldSetting('label') ?: ucfirst(str_replace(['-', '_'], ' ', $this->settings['name']));
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->settings['name'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxlength(): int {
    $length = !empty($this->settings['length']) ? (int) $this->settings['length'] : NULL;
    return $length ?? self::MAX_LENGTH;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataType(): string {
    return $this->settings['type'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function isUnsigned(): bool {
    if (!isset($this->settings['unsigned'])) {
      return FALSE;
    }
    return (bool) $this->settings['unsigned'];
  }

  /**
   * {@inheritdoc}
   */
  public function getScale(): int {
    if (!isset($this->settings['scale']) || !is_numeric($this->settings['scale'])) {
      return 2;
    }
    return (int) $this->settings['scale'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPrecision(): int {
    if (!isset($this->settings['precision']) || !is_numeric($this->settings['precision'])) {
      return 10;
    }
    return (int) $this->settings['precision'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDatetimeType(): string {
    return $this->settings['datetime_type'] ?? DateTimeType::DATETIME_TYPE_DATETIME;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetType(): ?string {
    return $this->settings['target_type'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting(string $setting): mixed {
    return $this->settings[$setting] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldSettings(): array {
    return $this->getSetting('field_settings') + static::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldSetting(string $setting): mixed {
    $field_settings = $this->getFieldSettings();
    return $field_settings[$setting] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function checkEmpty(): bool {
    return !empty($this->getFieldSetting('check_empty'));
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    return [
      'type' => 'varchar',
      'length' => $settings['length'] ?? 255,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): mixed {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): mixed {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(CustomFieldTypeInterface $item, array $default_value): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function onDependencyRemoval(CustomFieldTypeInterface $item, array $dependencies): array {
    return [];
  }

  /**
   * Helper method to flatten an array of allowed values and randomize.
   *
   * @param array<array{key: int|string, value: string}> $allowed_values
   *   An array of allowed values.
   *
   * @return int|string
   *   A random key from allowed values array.
   */
  protected static function getRandomOptions(array $allowed_values): int|string {
    $randoms = [];
    foreach ($allowed_values as $value) {
      if (isset($value['key'], $value['label'])) {
        $randoms[$value['key']] = $value['label'];
      }
    }

    return array_rand($randoms, 1);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange(string $property_name, bool $notify, FieldItemInterface $item): void {}

}
