<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\CustomField\FieldType;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomFieldTypeBase;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity_reference' field type.
 */
#[CustomFieldType(
  id: 'entity_reference',
  label: new TranslatableMarkup('Entity reference'),
  description: new TranslatableMarkup('A field containing an entity reference.'),
  category: new TranslatableMarkup('Reference'),
  default_widget: 'entity_reference_autocomplete',
  default_formatter: 'entity_reference_label',
)]
class EntityReference extends CustomFieldTypeBase {

  /**
   * The entity reference selection plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected SelectionPluginManagerInterface $selectionPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->selectionPluginManager = $container->get('plugin.manager.entity_reference_selection');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    ['name' => $name, 'target_type' => $target_type] = $settings;

    try {
      $target_type_info = \Drupal::entityTypeManager()
        ->getDefinition($target_type);
    }
    catch (PluginNotFoundException $e) {
      throw new FieldException(sprintf("Field '%s' references a target entity type '%s' which does not exist.",
        $name,
        $target_type
      ));
    }
    /** @var array<\Drupal\Core\TypedData\DataDefinitionInterface> $properties */
    $properties = static::propertyDefinitions($settings);
    if ($target_type_info->entityClassImplements(FieldableEntityInterface::class) && $properties[(string) $name]->getSetting('data_type') === 'integer') {
      $columns[$name] = [
        'type' => 'int',
        'description' => 'The ID of the target entity.',
        'unsigned' => TRUE,
      ];
    }
    else {
      $columns[$name] = [
        'type' => 'varchar_ascii',
        'description' => 'The ID of the target entity.',
        // If the target entities act as bundles for another entity type,
        // their IDs should not exceed the maximum length for bundles.
        'length' => $target_type_info->getBundleOf() ? EntityTypeInterface::BUNDLE_MAX_LENGTH : 255,
      ];
    }

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): mixed {
    ['name' => $name, 'target_type' => $target_type] = $settings;
    $target_type_info = \Drupal::entityTypeManager()->getDefinition($target_type);

    // If the target entity type doesn't have an ID key, we cannot determine
    // the target_id data type.
    if (!$target_type_info->hasKey('id')) {
      throw new FieldException('Entity type "' . $target_type_info->id() . '" has no ID key and cannot be targeted by entity reference field "' . $name . '"');
    }

    $target_id_data_type = 'string';
    if ($target_type_info->entityClassImplements(FieldableEntityInterface::class)) {
      $id_definition = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($target_type)[$target_type_info->getKey('id')];
      if ($id_definition->getType() === 'integer') {
        $target_id_data_type = 'integer';
      }
    }

    $target_id_definition = CustomFieldDataDefinition::create('custom_field_entity_reference')
      ->setLabel(new TranslatableMarkup('@label ID', ['@label' => $name]))
      ->setSetting('data_type', $target_id_data_type)
      ->setSetting('target_type', $target_type)
      ->setRequired(FALSE);

    if ($target_id_data_type === 'integer') {
      $target_id_definition->setSetting('unsigned', TRUE);
    }

    $properties[$name] = $target_id_definition;
    $properties[$name . self::SEPARATOR . 'entity'] = DataReferenceDefinition::create('entity')
      ->setLabel($target_type_info->getLabel())
      ->setDescription(new TranslatableMarkup('The referenced entity'))
      ->setComputed(TRUE)
      ->setSettings(['target_id' => $name, 'target_type' => $target_type])
      ->setClass('\Drupal\custom_field\Plugin\CustomField\EntityReferenceComputed')
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create($target_type))
      ->addConstraint('EntityType', $target_type);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'handler_settings' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getFieldSettings();
    $target_type = $this->getTargetType();
    if (!isset($settings['handler'])) {
      $settings['handler'] = 'default:' . $target_type;
    }
    // Get all selection plugins for this entity type.
    $selection_plugins = $this->selectionPluginManager->getSelectionGroups($target_type);
    $handlers_options = [];
    foreach (array_keys($selection_plugins) as $selection_group_id) {
      // We only display base plugins (e.g. 'default', 'views', ...) and not
      // entity type specific plugins (e.g. 'default:node', 'default:user',
      // ...).
      if (array_key_exists($selection_group_id, $selection_plugins[$selection_group_id])) {
        $label = $selection_plugins[$selection_group_id][$selection_group_id]['label'];
        $handlers_options[$selection_group_id] = Html::escape((string) $label);
      }
      elseif (array_key_exists($selection_group_id . ':' . $target_type, $selection_plugins[$selection_group_id])) {
        $selection_group_plugin = $selection_group_id . ':' . $target_type;
        $label = $selection_plugins[$selection_group_id][$selection_group_plugin]['base_plugin_label'] ?? '';
        $handlers_options[$selection_group_plugin] = Html::escape((string) $label);
      }
    }
    $wrapper_id = 'reference-wrapper-' . $this->getName();
    $element['handler'] = [
      '#type' => 'details',
      '#title' => $this->t('Reference type'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[static::class, 'formProcessMergeParent']],
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    $element['handler']['handler'] = [
      '#type' => 'select',
      '#title' => $this->t('Reference method'),
      '#options' => $handlers_options,
      '#default_value' => $settings['handler'],
      '#required' => TRUE,
      '#ajax' => [
        'wrapper' => $wrapper_id,
        'callback' => [static::class, 'actionCallback'],
      ],
    ];

    $element['handler']['handler_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Change handler'),
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#submit' => [[static::class, 'settingsAjaxSubmit']],
    ];

    $element['handler']['handler_settings'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity_reference-settings']],
    ];

    $handler = $this->getSelectionHandler($settings, $target_type);
    $configuration_form = $handler ? $handler->buildConfigurationForm([], $form_state) : [];

    // Alter configuration to use our custom callback.
    foreach ($configuration_form as $key => $item) {
      if (isset($item['#limit_validation_errors'])) {
        unset($item['#limit_validation_errors']);
      }
      if (isset($item['#ajax'])) {
        $item['#ajax'] = [
          'wrapper' => $wrapper_id,
          'callback' => [static::class, 'actionCallback'],
        ];
      }
      if (is_array($item)) {
        foreach ($item as $prop_key => $prop) {
          if (!is_array($prop)) {
            continue;
          }
          if (isset($prop['#limit_validation_errors'])) {
            unset($prop['#limit_validation_errors']);
          }
          if (isset($prop['#ajax'])) {
            $prop['#ajax'] = [
              'wrapper' => $wrapper_id,
              'callback' => [static::class, 'actionCallback'],
            ];
          }
          $item[(string) $prop_key] = $prop;
        }
      }
      $configuration_form[(string) $key] = $item;
    }

    $element['handler']['handler_settings'] += $configuration_form;

    return $element;
  }

  /**
   * Render API callback that moves entity reference elements up a level.
   *
   * The elements (i.e. 'handler_settings') are moved for easier processing by
   * the validation and submission handlers.
   *
   * @param array<string, mixed> $element
   *   The form element.
   *
   * @return array<string, mixed>
   *   The modified form element.
   *
   * @see _entity_reference_field_settings_process()
   */
  public static function formProcessMergeParent(array $element): array {
    $parents = $element['#parents'];
    array_pop($parents);
    $element['#parents'] = $parents;
    return $element;
  }

  /**
   * Ajax callback for the handler settings form.
   *
   * @param array|array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The form element.
   */
  public static function actionCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];
    $parents = $triggering_element['#array_parents'];
    $sliced_parents = array_slice($parents, 0, 4, TRUE);
    $element = NestedArray::getValue($form, $sliced_parents);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $element));
    if (end($parents) === 'handler') {
      $focus_input = $element['handler']['#name'];
      $response->addCommand(new InvokeCommand(':input[name="' . $focus_input . '"]', 'focus'));
    }

    return $response;
  }

  /**
   * Submit handler for the non-JS case.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see static::fieldSettingsForm()
   */
  public static function settingsAjaxSubmit(array $form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * Gets the selection handler for a given entity_reference field.
   *
   * @param array<string, mixed> $settings
   *   An array of field settings.
   * @param string|null $target_type
   *   The target entity type.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity containing the reference field.
   *
   * @return mixed
   *   The selection handler.
   */
  public function getSelectionHandler(array $settings, ?string $target_type = NULL, ?EntityInterface $entity = NULL): mixed {
    if (!$target_type) {
      return NULL;
    }
    $options = $settings['handler_settings'] ?: [];
    $options += [
      'target_type' => $target_type,
      'handler' => $settings['handler'] ?? 'default:' . $target_type,
      'entity' => $entity,
    ];

    return $this->selectionPluginManager->getInstance($options);
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(CustomFieldTypeInterface $item, array $default_value): array {
    $entity_type_manager = \Drupal::entityTypeManager();
    $target_entity_type_id = $item->getTargetType();
    $target_entity_type = $entity_type_manager->getDefinition($target_entity_type_id);
    $field_settings = $item->getFieldSettings();
    $target_bundles = $field_settings['handler_settings']['target_bundles'] ?? [];
    $dependencies = [];
    $field_name = $item->getName();
    // Depend on default values entity types configurations.
    if (!empty($default_value)) {
      foreach ($default_value as $value) {
        if (isset($value[$field_name])) {
          $entity = $entity_type_manager->getStorage($target_entity_type_id)->load($value[$field_name]);
          if ($entity) {
            $dependencies[$entity->getConfigDependencyKey()][] = $entity->getConfigDependencyName();
          }
        }
      }
    }
    // Depend on target bundle configurations. Dependencies for 'target_bundles'
    // also covers the 'auto_create_bundle' setting, if any, because its value
    // is included in the 'target_bundles' list.
    if (!empty($target_bundles)) {
      if ($bundle_entity_type_id = $target_entity_type->getBundleEntityType()) {
        if ($storage = $entity_type_manager->getStorage($bundle_entity_type_id)) {
          foreach ($storage->loadMultiple($target_bundles) as $bundle) {
            $dependencies[$bundle->getConfigDependencyKey()][] = $bundle->getConfigDependencyName();
          }
        }
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function onDependencyRemoval(CustomFieldTypeInterface $item, array $dependencies): array {
    $entity_type_manager = \Drupal::entityTypeManager();
    $bundles_changed = FALSE;
    $target_entity_type_id = $item->getTargetType();
    $target_entity_type = $entity_type_manager->getDefinition($target_entity_type_id);
    $field_settings = $item->getFieldSettings();
    $handler_settings = $field_settings['handler_settings'] ?? [];
    $changed_settings = [];

    if (!empty($handler_settings['target_bundles'])) {
      if ($bundle_entity_type_id = $target_entity_type->getBundleEntityType()) {
        if ($storage = $entity_type_manager->getStorage($bundle_entity_type_id)) {
          foreach ($storage->loadMultiple($handler_settings['target_bundles']) as $bundle) {
            if (isset($dependencies[$bundle->getConfigDependencyKey()][$bundle->getConfigDependencyName()])) {
              unset($handler_settings['target_bundles'][$bundle->id()]);

              // If this bundle is also used in the 'auto_create_bundle'
              // setting, disable the auto-creation feature completely.
              $auto_create_bundle = !empty($handler_settings['auto_create_bundle']) ? $handler_settings['auto_create_bundle'] : FALSE;
              if ($auto_create_bundle && $auto_create_bundle == $bundle->id()) {
                $handler_settings['auto_create'] = FALSE;
                $handler_settings['auto_create_bundle'] = NULL;
              }

              $bundles_changed = TRUE;
            }
          }
        }
      }
    }
    if ($bundles_changed) {
      $field_settings['handler_settings'] = $handler_settings;
      $changed_settings = $field_settings;
    }

    return $changed_settings;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function generateSampleValue(CustomFieldTypeInterface $field, string $target_entity_type): mixed {
    $field_settings = $field->getFieldSettings();
    $handler_settings = $field_settings['handler_settings'] ?? [];

    // If the field hasn't been configured yet, return early.
    if (empty($handler_settings)) {
      return NULL;
    }

    $target_type = $field->getTargetType();
    // An associative array keyed by the reference type, target type, and
    // bundle.
    static $recursion_tracker = [];

    $manager = \Drupal::service('plugin.manager.entity_reference_selection');

    // Instead of calling $manager->getSelectionHandler($field_definition)
    // replicate the behavior to be able to override the sorting settings.
    $options = [
      'target_type' => $target_type,
      'handler' => $field_settings['handler'],
      'entity' => NULL,
    ] + $handler_settings;

    $entity_type = \Drupal::entityTypeManager()->getDefinition($options['target_type']);
    $options['sort'] = [
      'field' => $entity_type->getKey('id'),
      'direction' => 'DESC',
    ];

    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $selection_handler */
    $selection_handler = $manager->getInstance($options);

    // Select a random number of references between the last 50 referenceable
    // entities created.
    if ($referenceable = $selection_handler->getReferenceableEntities(NULL, 'CONTAINS', 50)) {
      $group = array_rand($referenceable);

      return array_rand($referenceable[$group]);
    }

    // Attempt to create a sample entity, avoiding recursion.
    $entity_storage = \Drupal::entityTypeManager()->getStorage($options['target_type']);
    if ($entity_storage instanceof ContentEntityStorageInterface) {
      $bundle = static::getRandomBundle($entity_type, $options);

      // Track the generated entity by reference type, target type, and bundle.
      $key = $target_entity_type . ':' . $options['target_type'] . ':' . $bundle;

      // If entity generation was attempted but did not finish, do not continue.
      if (isset($recursion_tracker[$key])) {
        return [];
      }

      // Mark this as an attempt at generation.
      $recursion_tracker[$key] = TRUE;

      // Mark the sample entity as being a preview.
      $entity = $entity_storage->createWithSampleValues($bundle, [
        'in_preview' => TRUE,
      ]);

      // Remove the indicator once the entity is successfully generated.
      unset($recursion_tracker[$key]);
      return ['entity' => $entity];
    }

    return NULL;
  }

  /**
   * Gets a bundle for a given entity type and selection options.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param array $selection_settings
   *   An array of selection settings.
   *
   * @return string|null
   *   Either the bundle string, or NULL if there is no bundle.
   */
  protected static function getRandomBundle(EntityTypeInterface $entity_type, array $selection_settings): ?string {
    if ($entity_type->getKey('bundle')) {
      if (!empty($selection_settings['target_bundles'])) {
        $bundle_ids = $selection_settings['target_bundles'];
      }
      else {
        $bundle_ids = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type->id());
      }
      return (string) array_rand($bundle_ids);
    }

    return NULL;
  }

}
