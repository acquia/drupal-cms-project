<?php

namespace Drupal\smart_date\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\FieldViewsDataProvider;

/**
 * Hook implementations for smart_date.
 */
class SmartDateViewsHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?FieldViewsDataProvider $fieldViewsDataProvider = NULL,
  ) {}

  /**
   * Implements hook_field_views_data().
   */
  #[Hook('field_views_data')]
  public function fieldViewsData(FieldStorageConfigInterface $field) {
    if ($this->fieldViewsDataProvider !== NULL) {
      $data = $this->fieldViewsDataProvider->defaultFieldImplementation($field);
    }
    else {
      /** @phpstan-ignore function.deprecated */
      $data = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => $this->fieldViewsDataProvider->defaultFieldImplementation($field), fn() => views_field_default_views_data($field));
    }
    $field_name = $field->getName();
    $entity_type_id = $field->getTargetEntityTypeId();
    $entity_manager = $this->entityTypeManager;
    $entity_type = $entity_manager->getDefinition($entity_type_id);
    // Override the default handlers.
    $columns = [
      'value' => 'date',
      'end_value' => 'date',
      'duration' => 'numeric',
      'timezone' => 'standard',
      'rrule' => 'standard',
    ];
    // Provide human-readable property names.
    $labels = [
      'value' => $this->t('Start'),
      'end_value' => $this->t('End'),
      'duration' => $this->t('Duration'),
      'timezone' => $this->t('Timezone'),
      'rrule' => $this->t('Recurring'),
    ];
    // Provide human-readable property help text.
    $desc = [
      'value' => $this->t('The start of the specified date/time range.'),
      'end_value' => $this->t('The end of the specified date/time range.'),
      'duration' => $this->t('The duration of the specified date/time range.'),
      'timezone' => $this->t('The timezone of the specified date/time range.'),
      'rrule' => $this->t('The recurrence rule for the specified date/time range.'),
    ];
    // The set of views handlers we want to manipulate.
    $types = [
      'field',
      'filter',
      'sort',
      'argument',
    ];
    foreach ($data as $table_name => $table_data) {
      if (!isset($table_data[$field_name])) {
        continue;
      }
      $base = $table_data[$field_name];
      foreach ($columns as $column => $plugin_id) {
        foreach ($types as $type) {
          if (isset($data[$table_name][$field_name . '_' . $column][$type]) || $type == 'field') {
            $plugin_id_adjusted = $plugin_id;
            // For certain types, the plugin id needs to change.
            if ($plugin_id == 'standard' && in_array($type, [
              'filter',
              'argument',
            ])) {
              $plugin_id_adjusted = 'string';
            }
            // Override the default data with our custom values.
            $data[$table_name][$field_name . '_' . $column][$type]['title'] = $base['title'] . ' - ' . $labels[$column];
            $data[$table_name][$field_name . '_' . $column][$type]['id'] = $plugin_id_adjusted;
            $data[$table_name][$field_name . '_' . $column][$type]['help'] = $desc[$column];
            $data[$table_name][$field_name . '_' . $column][$type]['field_name'] = $field_name;
            $data[$table_name][$field_name . '_' . $column][$type]['property'] = $column;
          }
        }
      }
      // Provide a relationship for the entity type with the entity reference
      // revisions field.
      $args = [
        '@label' => $this->t('Smart date recurring rule'),
        '@field_name' => $field_name,
      ];
      $data[$table_name][$field_name . '_rrule']['relationship'] = [
        'title' => $this->t('@label referenced from @field_name', $args),
        'label' => $this->t('@field_name: @label', $args),
        'group' => $entity_type->getLabel(),
        'help' => $this->t('Appears in: @bundles.', [
          '@bundles' => implode(', ', $field->getBundles()),
        ]),
        'id' => 'standard',
        'base' => 'smart_date_rule',
        'entity type' => 'smart_date_rule',
        'base field' => 'rid',
        'relationship field' => $field_name . '_rrule',
      ];
    }
    return $data;
  }

}
