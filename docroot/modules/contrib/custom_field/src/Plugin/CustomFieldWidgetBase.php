<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin;

use Drupal\Component\Utility\Html;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\PluginSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Base class for CustomField widget plugins.
 */
abstract class CustomFieldWidgetBase extends PluginSettingsBase implements CustomFieldWidgetInterface, ContainerFactoryPluginInterface {

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
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The content translation manager, if available.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface|null
   */
  protected ?ContentTranslationManagerInterface $contentTranslationManager;

  /**
   * The custom field definition.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeInterface|null
   */
  protected ?CustomFieldTypeInterface $customFieldDefinition;

  /**
   * The field definition name.
   *
   * @var string|null
   */
  protected ?string $fieldName;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, array $settings, ModuleHandlerInterface $module_handler, LanguageManagerInterface $language_manager, ?ContentTranslationManagerInterface $content_translation_manager = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settings = $settings;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
    $this->contentTranslationManager = $content_translation_manager;
    $this->fieldName = $configuration['field_name'] ?? NULL;
    $this->customFieldDefinition = $configuration['custom_field_definition'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $module_handler = $container->get('module_handler');
    $language_manager = $container->get('language_manager');
    $content_translation_manager = $module_handler->moduleExists('content_translation')
      ? $container->get('content_translation.manager')
      : NULL;
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
        $configuration['settings'] ?? static::defaultSettings(),
      $module_handler,
      $language_manager,
      $content_translation_manager,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'label' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    // Prep the element base properties. Implementations of the plugin can
    // override as necessary or just set #type and be on their merry way.
    $field_definition = $items->getFieldDefinition();
    $field_name = $field_definition->getName();
    $field_settings = $field->getFieldSettings();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $items->getEntity();
    $settings = $this->getSettings() ?? static::defaultSettings();
    $is_required = $items->getFieldDefinition()->isRequired();
    $description = !empty($field_settings['description']) ? $this->t('@description', ['@description' => $field_settings['description']]) : NULL;
    /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomItem $item */
    $item = $items[$delta];
    $access = TRUE;
    $parents = $form['#parents'] ?? [];
    $field_parents = array_merge($parents, [$field_name, $delta, $field->getName()]);
    if (!$this->isDefaultValueWidget($form_state) && $entity->isTranslatable()) {
      $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
      $is_translatable = $field_definition->isTranslatable() && $field_settings['translatable'];
      if (!$entity->isNew() && $entity->hasTranslation($langcode)) {
        $entity = $entity->getTranslation($langcode);
      }
      $is_default_translation = $entity->isDefaultTranslation();
      $access = $is_default_translation || $is_translatable || $entity->isNew();
    }

    $label = $settings['label'] ? trim($settings['label']) : '';
    return [
      '#title' => $label ?: $field->getLabel(),
      '#description' => $description,
      '#description_display' => $field_settings['description_display'] ?: NULL,
      '#default_value' => $item->{$field->getName()} ?? NULL,
      '#required' => !(isset($form_state->getBuildInfo()['base_form_id']) && $form_state->getBuildInfo()['base_form_id'] == 'field_config_form') && $is_required && $field_settings['required'],
      '#access' => $access,
      '#field_parents' => $field_parents,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $settings = $this->getSettings() + static::defaultSettings();

    // Some table columns containing raw markup.
    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('The form element label. Leave blank to use the default field label.'),
      '#default_value' => $settings['label'] ?? '',
      '#maxlength' => 255,
      '#required' => FALSE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    if (is_string($value) && trim($value) === '') {
      return NULL;
    }

    return $value;
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
  public static function isApplicable(CustomFieldTypeInterface $custom_item): bool {
    // By default, widgets are available for all fields.
    return TRUE;
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
   * Returns whether the widget used for default value form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if a widget used to input default value, FALSE otherwise.
   */
  protected function isDefaultValueWidget(FormStateInterface $form_state): bool {
    return (bool) $form_state->get('default_value_widget');
  }

  /**
   * Reports field-level validation errors against actual form elements.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   A list of constraint violations to flag.
   * @param array $form
   *   The form structure where field elements are attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function flagErrors(FieldItemListInterface $items, ConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state): void {}

  /**
   * Helper function to create a unique identifier for the element.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param string $field_name
   *   The field name.
   * @param int $delta
   *   The item number.
   * @param string $custom_field_name
   *   The custom field name.
   * @param string $separator
   *   An optional separator to construct the id.
   *
   * @return string
   *   The unique id.
   */
  public function getUniqueElementId(array $form, string $field_name, int $delta, string $custom_field_name, string $separator = '-'): string {
    $parents = is_array($form['#parents']) ? $form['#parents'] : [];
    $id = implode($separator, [...$parents, $field_name, $delta, $custom_field_name]);

    return Html::cleanCssIdentifier($id);
  }

}
