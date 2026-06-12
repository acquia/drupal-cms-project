<?php

namespace Drupal\custom_field\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\EntityReference;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters a view by entity reference subfields.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("custom_field_entity_reference")]
class CustomFieldEntityReference extends EntityReference {

  /**
   * Constructs a CustomFieldEntityReference object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected SelectionPluginManagerInterface $selectionPluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
    protected EntityRepositoryInterface $entityRepository,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $selectionPluginManager, $entityTypeManager, $messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): CustomFieldEntityReference {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('entity.repository'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getReferencedEntityType(): EntityTypeInterface {
    return $this->entityTypeManager->getDefinition($this->definition['target_type']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultSelectedEntities(): array {
    $referenced_type_id = $this->getReferencedEntityType()->id();
    $entity_storage = $this->entityTypeManager->getStorage($referenced_type_id);
    if (!empty($this->value) && !isset($this->value[static::ALL_VALUE])) {
      return $entity_storage->loadMultiple($this->value);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getValueOptionsCallback(SelectionInterface $selection_handler): array {
    $options = [];
    if ($this->options['widget'] === static::WIDGET_SELECT) {
      $target_type = $this->definition['target_type'];
      $storage = $this->entityTypeManager->getStorage($target_type);
      $entity_type = $this->entityTypeManager->getDefinition($target_type);
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->range(0, static::WIDGET_SELECT_LIMIT)
        ->sort($this->entityTypeManager->getDefinition($target_type)->getKey('label'));

      // Filter entities by their presence in the column of the field table.
      $field_name = $this->definition['field_name'];
      $subfield = $this->definition['subfield'];
      $subfield_name = $field_name . '_' . $subfield;

      // Use a subquery to get field_custom_reference values.
      $subquery = $this->database->select($this->table, 'f')
        ->fields('f', [$subfield_name])
        ->condition('f.' . $subfield_name, NULL, 'IS NOT NULL')
        ->distinct();

      $query->condition($entity_type->getKey('id'), $subquery, 'IN');

      // Limit by bundles if configured.
      if (!empty($this->options['sub_handler_settings']['target_bundles'])) {
        $target_bundles = array_filter($this->options['sub_handler_settings']['target_bundles']);
        if ($target_bundles) {
          $bundle_key = $this->entityTypeManager->getDefinition($target_type)->getKey('bundle');
          if ($bundle_key) {
            $query->condition($bundle_key, $target_bundles, 'IN');
          }
        }
      }

      $entities = $storage->loadMultiple($query->execute());

      foreach ($entities as $entity) {
        $options[$entity->id()] = $this->entityRepository->getTranslationFromContext($entity)->label();
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function alternateWidgetsDefaultNormalize(array &$form, FormStateInterface $form_state): void {
    $field_id = '_' . $this->definition['field_name'] . '-' . $this->definition['subfield'] . '-widget';
    $form[$field_id] = [
      '#type' => 'hidden',
      '#value' => $this->options['widget'],
    ];

    $previous_widget = $form_state->getUserInput()[$field_id] ?? NULL;
    if ($previous_widget && $previous_widget !== $this->options['widget']) {
      $form['value']['#value_callback'] = function ($element) {
        return $element['#default_value'] ?? '';
      };
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function valueValidate($form, FormStateInterface $form_state): void {
    if ($this->options['widget'] !== static::WIDGET_AUTOCOMPLETE) {
      return;
    }
    $ids = [];
    if ($values = $form_state->getValue(['options', 'value'])) {
      foreach ($values as $value) {
        $ids[] = $value['target_id'];
      }
    }

    $form_state->setValue(['options', 'value'], $ids);
  }

}
