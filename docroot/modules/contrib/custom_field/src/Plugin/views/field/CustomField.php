<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Entity\Sql\TableMappingInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\custom_field\Entity\Render\EntityFieldRenderer;
use Drupal\custom_field\Plugin\CustomFieldFormatterManagerInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\field\MultiItemsFieldHandlerInterface;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to properly render custom fields with formatters.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField('custom_field')]
final class CustomField extends FieldPluginBase implements MultiItemsFieldHandlerInterface {

  use FieldAPIHandlerTrait;

  /**
   * Does the rendered fields get limited.
   *
   * @var bool
   */
  private bool $limitValues;

  /**
   * Does the field supports multiple field values.
   *
   * @var bool
   */
  private bool $multiple;

  /**
   * Static cache for ::getEntityFieldRenderer().
   *
   * @var \Drupal\custom_field\Entity\Render\EntityFieldRenderer|null
   */
  protected ?EntityFieldRenderer $entityFieldRenderer = NULL;

  /**
   * Constructs a CustomField object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface $customFieldTypeManager
   *   The custom field type manager.
   * @param \Drupal\custom_field\Plugin\CustomFieldFormatterManagerInterface $customFieldFormatterManager
   *   The custom field formatter manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    RendererInterface $renderer,
    EntityFieldManagerInterface $entity_field_manager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly CustomFieldTypeManagerInterface $customFieldTypeManager,
    private readonly CustomFieldFormatterManagerInterface $customFieldFormatterManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityFieldManager = $entity_field_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new CustomField(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('plugin.manager.custom_field_type'),
      $container->get('plugin.manager.custom_field_formatter'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);

    $this->limitValues = FALSE;
    $this->multiple = FALSE;

    $field_definition = $this->getFieldDefinition();
    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
    if ($field_definition->getFieldStorageDefinition()->isMultiple()) {
      $this->multiple = TRUE;
    }

    // If "Display all values in the same row" is FALSE, then we always limit
    // in order to show a single unique value per row.
    if (!$this->options['group_rows']) {
      $this->limitValues = TRUE;
    }

    // If "First and last only" is chosen, limit the values.
    if (!empty($this->options['delta_first_last'])) {
      $this->limitValues = TRUE;
    }

    // Otherwise, we only limit values if the user hasn't selected "all", 0, or
    // the value matching field cardinality.
    if ((($this->options['delta_limit'] > 0) && ($this->options['delta_limit'] != $cardinality)) || intval($this->options['delta_offset'])) {
      $this->limitValues = TRUE;
    }
  }

  /**
   * Called to add the field to a query.
   *
   * By default, all needed data is taken from entities loaded by the query
   * plugin. Columns are added only if they are used in groupings.
   *
   * @param bool $use_groupby
   *   The columns are grouped.
   */
  public function query(bool $use_groupby = FALSE): void {
    $fields = $this->additional_fields;
    // No need to add the entity type.
    $entity_type_key = array_search('entity_type', $fields);
    if ($entity_type_key !== FALSE) {
      unset($fields[$entity_type_key]);
    }
    if ($use_groupby) {
      // Add the fields that we're actually grouping on.
      $options = [];
      if ($this->options['group_column'] != 'entity_id') {
        $options = [$this->options['group_column'] => $this->options['group_column']];
      }
      $options += is_array($this->options['group_columns']) ? $this->options['group_columns'] : [];

      // Go through the list and determine the actual column name from field
      // api.
      $fields = [];
      $table_mapping = $this->getTableMapping();
      $field_definition = $this->getFieldStorageDefinition();

      foreach ($options as $column) {
        $fields[$column] = $table_mapping->getFieldColumnName($field_definition, $column);
      }
    }

    // Add additional fields (and the table join itself) if needed.
    if ($this->addFieldTable($use_groupby)) {
      $this->ensureMyTable();
      $this->addAdditionalFields($fields);
    }

    // Let the entity field renderer alter the query if needed.
    $this->getEntityFieldRenderer()->query($this->query, $this->relationship);
  }

  /**
   * Determine if the field table should be added to the query.
   *
   * @param bool $use_groupby
   *   If grouping is enabled on the field.
   *
   * @return bool
   *   Whether to add join for field table.
   */
  public function addFieldTable(bool $use_groupby = FALSE): bool {
    // Grouping is enabled.
    if ($use_groupby) {
      return TRUE;
    }
    // This a multiple value field, but "group multiple values" is not checked.
    if ($this->multiple && !$this->options['group_rows']) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable(): bool {
    // A field is not click sortable if it's a multiple field with
    // "group multiple values" checked, since a click sort in that case would
    // add a join to the field table, which would produce unwanted duplicates.
    if ($this->multiple && $this->options['group_rows']) {
      return FALSE;
    }

    // If the field definition is set, use that.
    if (isset($this->definition['click sortable'])) {
      return (bool) $this->definition['click sortable'];
    }

    // Default to true.
    return TRUE;
  }

  /**
   * Called to determine what to tell the click sorter.
   */
  public function clickSort($order): void {
    // No column selected, can't continue.
    if (empty($this->options['click_sort_column'])) {
      return;
    }

    // Currently, only the Sql plugin has the functions used below.
    if (!$this->query instanceof Sql) {
      return;
    }

    $this->ensureMyTable();
    $field_storage_definition = $this->getFieldStorageDefinition();
    $column = $this->getTableMapping()->getFieldColumnName($field_storage_definition, $this->options['click_sort_column']);
    if (!isset($this->aliases[$column])) {
      // Column is not in query; add a sort on it.
      $this->aliases[$column] = $this->tableAlias . '.' . $column;
      // If the query uses DISTINCT we need to add the column too.
      if (!empty($this->view->getQuery()->options['distinct'])) {
        $this->query->addField($this->tableAlias, $column);
      }
    }
    $this->query->addOrderBy('', NULL, $order, $this->aliases[$column]);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $field_storage_definition = $this->getFieldStorageDefinition();

    // Add formatter options.
    $options['click_sort_column'] = [
      'default' => $this->definition['property'],
    ];
    $options['type'] = ['default' => $this->definition['default_formatter']];
    $options['settings'] = ['default' => $this->definition['default_formatter_settings']];
    $options['group_column'] = [
      'default' => $this->definition['property'],
    ];
    $options['group_columns'] = [
      'default' => [],
    ];
    // Options used for multiple value fields.
    $options['group_rows'] = [
      'default' => TRUE,
    ];
    // If we know the exact number of allowed values, then that can be the
    // default. Otherwise, default to 'all'.
    $options['delta_limit'] = [
      'default' => ($field_storage_definition->getCardinality() > 1) ? $field_storage_definition->getCardinality() : 0,
    ];
    $options['delta_offset'] = ['default' => 0];
    $options['delta_reversed'] = ['default' => FALSE];
    $options['delta_first_last'] = [
      'default' => FALSE,
    ];
    $options['multi_type'] = [
      'default' => 'separator',
    ];
    $options['separator'] = [
      'default' => ', ',
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    // Extract field name and subfield from the field handler definition.
    [$field_name, $subfield] = $this->extractFieldInfo();
    $visibility_path = $this->customFieldFormatterManager->getInputPathForStatesApi($form_state, $field_name, $subfield, TRUE, 'fields');
    $form['#field_parents'] = ['options', 'settings'];
    $form['#visibility_path'] = $visibility_path;

    // Get field storage configuration.
    $field_storage = $this->getFieldStorageDefinition();

    // Get custom field items for this field.
    $custom_fields = $this->customFieldTypeManager->getCustomFieldItems($field_storage->getSettings());
    $custom_field = $custom_fields[$subfield];

    $formatter_options = $this->customFieldFormatterManager->getOptions($custom_field);

    // If this is a multiple value field, add its options.
    if ($this->multiple) {
      $this->multipleOptionsForm($form, $form_state);
    }

    $extra_columns = $this->definition['extra columns'];
    // No need to ask the user anything if the field has only one column.
    if (empty($extra_columns)) {
      $form['click_sort_column'] = [
        '#type' => 'value',
        '#value' => $subfield,
      ];
    }
    else {
      // Add the main property.
      $sort_columns[$subfield] = $subfield;
      // Add the extra properties keyed by the table column name pattern.
      foreach ($extra_columns as $key => $column) {
        $sort_columns[$subfield . '__' . $key] = $column;
      }
      $form['click_sort_column'] = [
        '#type' => 'select',
        '#title' => $this->t('Column used for click sorting'),
        '#options' => array_combine(array_keys($sort_columns), $sort_columns),
        '#default_value' => $this->options['click_sort_column'],
        '#description' => $this->t('Used by Style: Table to determine the actual column to click sort the field on. The default is usually fine.'),
      ];
    }

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Formatter'),
      '#options' => $formatter_options,
      '#default_value' => $this->options['type'],
      '#ajax' => [
        // @todo How to make PHPStan find this function?
        // @phpstan-ignore-next-line
        'url' => views_ui_build_form_url($form_state),
      ],
      '#submit' => [[$this, 'submitTemporaryForm']],
      '#executes_submit_callback' => TRUE,
    ];

    // Get the settings form.
    $settings_form = ['#value' => []];
    $format = $form_state->getUserInput()['options']['type'] ?? $this->options['type'];

    // Get current formatter.
    $settings = $this->options['settings'] + $this->customFieldFormatterManager->getDefaultSettings($format);
    $options = $this->customFieldFormatterManager->createOptionsForInstance($custom_field, $format, $settings, '_custom');
    if ($format = $this->customFieldFormatterManager->getInstance($options)) {
      $settings_form = $format->settingsForm($form, $form_state);
    }
    $form['settings'] = $settings_form;
  }

  /**
   * Provide options for multiple value fields.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function multipleOptionsForm(array &$form, FormStateInterface $form_state): void {

    $form['multiple_field_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Multiple field settings'),
      '#weight' => 5,
    ];

    $form['group_rows'] = [
      '#title' => $this->t('Display all values in the same row'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['group_rows'],
      '#description' => $this->t('If checked, multiple values for this field will be shown in the same row. If not checked, each value in this field will create a new row. If using group by, make sure to group by "Entity ID" for this setting to have any effect.'),
      '#fieldset' => 'multiple_field_settings',
    ];

    // Make the string translatable by keeping it as a whole rather than
    // translating prefix and suffix separately.
    [$prefix, $suffix] = explode('@count', $this->t('Display @count value(s)')->render());

    $form['multi_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display type'),
      '#options' => [
        'ul' => $this->t('Unordered list'),
        'ol' => $this->t('Ordered list'),
        'separator' => $this->t('Simple separator'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $this->options['multi_type'],
      '#fieldset' => 'multiple_field_settings',
    ];

    $form['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator'),
      '#default_value' => $this->options['separator'],
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
          ':input[name="options[multi_type]"]' => ['value' => 'separator'],
        ],
      ],
      '#fieldset' => 'multiple_field_settings',
    ];

    $field = $this->getFieldDefinition();
    // Not the best solution but safest as long as BaseFieldDefinition
    // does not has its own interface that extends both FieldDefinitionInterface
    // and FieldStorageDefinitionInterface.
    if ($field instanceof BaseFieldDefinition) {
      if ($field->getCardinality() === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
        $type = 'textfield';
        $options = NULL;
        $size = 5;
      }
      else {
        $type = 'select';
        $range = range(1, $field->getCardinality());
        $options = array_combine($range, $range);
        $size = 1;
      }
      $form['delta_limit'] = [
        '#type' => $type,
        '#size' => $size,
        '#field_prefix' => $prefix,
        '#field_suffix' => $suffix,
        '#options' => $options,
        '#default_value' => $this->options['delta_limit'],
        '#prefix' => '<div class="container-inline">',
        '#states' => [
          'visible' => [
            ':input[name="options[group_rows]"]' => ['checked' => TRUE],
          ],
        ],
        '#fieldset' => 'multiple_field_settings',
      ];
    }

    [$prefix, $suffix] = explode('@count', $this->t('starting from @count')->render());
    $form['delta_offset'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#field_prefix' => $prefix,
      '#field_suffix' => $suffix,
      '#default_value' => $this->options['delta_offset'],
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('(first item is 0)'),
      '#fieldset' => 'multiple_field_settings',
    ];
    $form['delta_reversed'] = [
      '#title' => $this->t('Reversed'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['delta_reversed'],
      '#suffix' => $suffix,
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('(start from last values)'),
      '#fieldset' => 'multiple_field_settings',
    ];
    $form['delta_first_last'] = [
      '#title' => $this->t('First and last only'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['delta_first_last'],
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
        ],
      ],
      '#fieldset' => 'multiple_field_settings',
    ];
  }

  /**
   * Extend the groupby form with group columns.
   */
  public function buildGroupByForm(&$form, FormStateInterface $form_state): void {
    parent::buildGroupByForm($form, $form_state);

    // Not the best solution but safest as long as BaseFieldDefinition
    // does not has its own interface that extends both FieldDefinitionInterface
    // and FieldStorageDefinitionInterface.
    $field = $this->getFieldDefinition();
    $columns = $field instanceof BaseFieldDefinition ? $field->getColumns() : [];

    // With "field API" fields, the column target of the grouping function
    // and any additional grouping columns must be specified.
    $field_columns = array_keys($columns);
    // @todo What is happening here? What is the point of the array_combine()
    // on the same array?
    $group_columns = [
      'entity_id' => $this->t('Entity ID'),
    ] + array_map('ucfirst', array_combine($field_columns, $field_columns));

    $form['group_column'] = [
      '#type' => 'select',
      '#title' => $this->t('Group column'),
      '#default_value' => $this->options['group_column'],
      '#description' => $this->t('Select the column of this field to apply the grouping function selected above.'),
      '#options' => $group_columns,
    ];

    $options = [
      'bundle' => 'Bundle',
      'language' => 'Language',
      'entity_type' => 'Entity_type',
    ];
    // Add on defined fields, noting that they're prefixed with the field name.
    $form['group_columns'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Group columns (additional)'),
      '#default_value' => $this->options['group_columns'],
      '#description' => $this->t('Select any additional columns of this field to include in the query and to group on.'),
      '#options' => $options + $group_columns,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitGroupByForm(&$form, FormStateInterface $form_state): void {
    parent::submitGroupByForm($form, $form_state);
    $item = &$form_state->get('handler')->options;

    // Add settings for "field API" fields.
    $item['group_column'] = $form_state->getValue(['options', 'group_column']);
    $item['group_columns'] = array_filter($form_state->getValue(['options', 'group_columns']));
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormCalculateOptions(array $options, array $form_state_options): array {
    // When we change the formatter type we don't want to keep any of the
    // previous configured formatter settings, as there might be schema
    // conflict.
    unset($options['settings']);
    $options = $form_state_options + $options;
    if (!isset($options['settings'])) {
      $options['settings'] = [];
    }
    return $options;
  }

  /**
   * Adapts the $items according to the delta configuration.
   *
   * This selects displayed deltas, reorders items, and takes offsets into
   * account.
   *
   * @param array<int, mixed> $all_values
   *   The items for individual rendering.
   *
   * @return array<int, mixed>
   *   The manipulated items.
   */
  private function prepareItemsByDelta(array $all_values): array {
    if ($this->options['delta_reversed']) {
      $all_values = array_reverse($all_values);
    }

    // We are supposed to show only certain deltas.
    if ($this->limitValues) {
      $row = $this->view->result[$this->view->row_index];

      // Offset is calculated differently when row grouping for a field is not
      // enabled. Since there are multiple rows, delta needs to be taken into
      // account, so that different values are shown per row.
      if (!$this->options['group_rows'] && isset($this->aliases['delta']) && isset($row->{$this->aliases['delta']})) {
        $delta_limit = 1;
        $offset = $row->{$this->aliases['delta']};
      }
      // Single fields don't have a delta available so choose 0.
      elseif (!$this->options['group_rows'] && !$this->multiple) {
        $delta_limit = 1;
        $offset = 0;
      }
      else {
        $delta_limit = (int) $this->options['delta_limit'];
        $offset = intval($this->options['delta_offset']);

        // We should only get here in this case if there is an offset, and in
        // that case we are limiting to all values after the offset.
        if ($delta_limit === 0) {
          $delta_limit = count($all_values) - $offset;
        }
      }

      // Determine if only the first and last values should be shown.
      $delta_first_last = $this->options['delta_first_last'];

      $new_values = [];
      for ($i = 0; $i < $delta_limit; $i++) {
        $new_delta = $offset + $i;

        if (isset($all_values[$new_delta])) {
          // If first-last option was selected, only use the first and last
          // values.
          if (!$delta_first_last
            // Use the first value.
            || $new_delta == $offset
            // Use the last value.
            || $new_delta == ($delta_limit + $offset - 1)) {
            $new_values[] = $all_values[$new_delta];
          }
        }
      }
      $all_values = $new_values;
    }

    return $all_values;
  }

  /**
   * Extracts field name and subfield from the field ID.
   *
   * @return array<int, mixed>
   *   An array with field name and subfield name.
   */
  private function extractFieldInfo(): array {
    return [
      $this->definition['field_name'],
      $this->definition['property'],
    ];
  }

  /**
   * Gets the table mapping for the entity type of the field.
   *
   * @return \Drupal\Core\Entity\Sql\TableMappingInterface|null
   *   The table mapping.
   */
  private function getTableMapping(): ?TableMappingInterface {

    try {
      $entity_type_storage = $this->entityTypeManager->getStorage($this->definition['entity_type']);
      assert($entity_type_storage instanceof SqlEntityStorageInterface);
      return $entity_type_storage->getTableMapping();
    }
    catch (\Exception $exception) {
      // Silent fail, for now.
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values): void {
    parent::preRender($values);
    $this->getEntityFieldRenderer()->preRender($values);
  }

  /**
   * Returns the entity field renderer.
   *
   * @return \Drupal\custom_field\Entity\Render\EntityFieldRenderer
   *   The entity field renderer.
   */
  private function getEntityFieldRenderer(): EntityFieldRenderer {
    if (is_null($this->entityFieldRenderer)) {
      // This can be invoked during field handler initialization in which case
      // view fields are not set yet.
      if (!empty($this->view->field)) {
        foreach ($this->view->field as $field) {
          // An entity field renderer can handle only a single relationship.
          if ($field->relationship == $this->relationship && isset($field->entityFieldRenderer)) {
            $this->entityFieldRenderer = $field->entityFieldRenderer;
            break;
          }
        }
      }
      if (is_null($this->entityFieldRenderer)) {
        try {
          $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
          $this->entityFieldRenderer = new EntityFieldRenderer($this->view, $this->relationship, $this->languageManager, $entity_type, $this->entityTypeManager, $this->entityRepository);
        }
        catch (\Exception $exception) {
          // Silent fail, for now.
        }
      }
    }
    return $this->entityFieldRenderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getItems(ResultRow $values): array {
    $entity = $this->getEntity($values);
    if (!$entity) {
      return [];
    }
    $relationship = $this->options['relationship'] ?? 'none';
    $entity = $this->entityFieldRenderer->getEntityTranslationByRelationship($entity, $values, $relationship);
    $langcode = $entity->language()->getId();
    /** @var string $field_name */
    /** @var string $subfield */
    [$field_name, $subfield] = $this->extractFieldInfo();
    /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomItemList $field_items */
    $field_items = $entity->{$field_name};

    // Return early if the field is empty.
    if (!$field_items || $field_items->isEmpty()) {
      return [];
    }

    $field_storage = $this->getFieldStorageDefinition();
    $field_config = FieldConfig::loadByName($entity->getEntityTypeId(), $entity->bundle(), $field_storage->getName());
    $settings = array_merge($field_storage->getSettings(), $field_config->getSettings());
    $custom_fields = $this->customFieldTypeManager->getCustomFieldItems($settings);
    $custom_field = $custom_fields[$subfield];
    $formatter_id = $this->options['type'];
    $formatter_settings = $this->options['settings'] ?? [];
    $instance_options = $this->customFieldFormatterManager->createOptionsForInstance($custom_field, $formatter_id, $formatter_settings, '_custom');
    $plugin = $this->customFieldFormatterManager->getInstance($instance_options);
    $extra_columns = $this->definition['extra columns'];

    // Prepare render array.
    $items = [];
    foreach ($field_items as $delta => $field_item) {
      $raw_value = $field_item->{$subfield};
      $raw_extra = [];
      if (!empty($extra_columns)) {
        foreach ($extra_columns as $key => $extra_column) {
          $raw_extra[$key] = $field_item->{$subfield . '__' . $key};
        }
      }
      $value = $raw_value;
      if ($value == '' || $value == NULL) {
        continue;
      }
      $data_type = $custom_field->getDataType();
      $reference_types = [
        'entity_reference',
        'image',
        'file',
      ];
      if (in_array($data_type, $reference_types)) {
        $value = $field_item->{$subfield . '__entity'};
        if ($value instanceof TranslatableInterface) {
          $value = $this->entityRepository->getTranslationFromContext($value, $langcode);
        }
      }
      elseif ($data_type === 'viewfield') {
        $value = [
          'target_id' => $value,
          'display_id' => $field_item->{$subfield . '__display'},
          'arguments' => $field_item->{$subfield . '__arguments'},
          'items_to_display' => $field_item->{$subfield . '__items'},
        ];
      }
      elseif ($data_type === 'uri') {
        $value = [
          'uri' => $value,
        ];
      }
      elseif ($data_type === 'link') {
        $value = [
          'uri' => $value,
          'title' => $field_item->{$subfield . '__title'},
          'options' => $field_item->{$subfield . '__options'},
        ];
      }
      elseif ($data_type === 'datetime') {
        $value = [
          'date' => $field_item->{$subfield . '__date'},
          'timezone' => $field_item->{$subfield . '__timezone'},
        ];
      }
      elseif ($data_type === 'daterange') {
        $value = [
          'start_date' => $field_item->{$subfield . '__start_date'},
          'end_date' => $field_item->{$subfield . '__end_date'},
          'timezone' => $field_item->{$subfield . '__timezone'},
          'duration' => $field_item->{$subfield . '__duration'},
        ];
      }
      elseif ($data_type === 'time_range') {
        $value = [
          'start' => $value,
          'end' => $field_item->{$subfield . '__end'},
          'duration' => $field_item->{$subfield . '__duration'},
        ];
      }
      if (in_array($data_type, ['map', 'map_string'])) {
        $raw_value = Json::encode($raw_value);
      }
      $formatted_value = $plugin->formatValue($field_item, $value);
      $items[(int) $delta] = [
        'raw' => $raw_value,
        'raw_extra' => $raw_extra,
        'rendered' => $formatted_value,
      ];
    }
    if (empty($items)) {
      return [];
    }

    return $this->prepareItemsByDelta($items);
  }

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps,Drupal.Commenting.FunctionComment.Missing
  public function render_item($count, $item): MarkupInterface|string {
    if (is_array($item['rendered'])) {
      return $this->renderer->render($item['rendered']);
    }
    return (string) $item['rendered'];
  }

  /**
   * {@inheritdoc}
   */
  public function renderItems($items): MarkupInterface|string {
    if ($items !== []) {
      if ($this->options['multi_type'] === 'separator' || !$this->options['group_rows']) {
        $separator = $this->options['multi_type'] === 'separator' ? Xss::filterAdmin($this->options['separator']) : '';
        $build = [
          '#type' => 'inline_template',
          '#template' => '{{ items | safe_join(separator) }}',
          '#context' => ['separator' => $separator, 'items' => $items],
        ];
      }
      else {
        $build = [
          '#theme' => 'item_list',
          '#items' => $items,
          '#title' => NULL,
          '#list_type' => $this->options['multi_type'],
        ];
      }
      return $this->renderer->render($build);
    }

    return '';
  }

  /**
   * {@inheritdoc}
   *
   * @param string[] $tokens
   *   The tokens array.
   */
  protected function documentSelfTokens(&$tokens): void {
    $property = $this->definition['property'];
    $extra_columns = $this->definition['extra columns'];
    $tokens['{{ ' . $this->options['id'] . '__raw }}'] = (string) $this->t('Raw @column', ['@column' => $property]);
    if (!empty($extra_columns)) {
      foreach ($extra_columns as $column) {
        $tokens['{{ ' . $this->options['id'] . '__' . $column . ' }}'] = (string) $this->t('Raw @column', ['@column' => $column]);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $tokens
   *   The tokens array.
   * @param array<string, mixed> $item
   *   The item.
   */
  protected function addSelfTokens(&$tokens, $item): void {
    $raw = $item['raw'] ?? '';
    $rendered = $item['rendered'] ?? '';
    $tokens['{{ ' . $this->options['id'] . ' }}'] = $rendered;
    $tokens['{{ ' . $this->options['id'] . '__raw }}'] = $raw;
    if (isset($item['raw_extra'])) {
      $extra_columns = $this->definition['extra columns'];
      $raw_extra = $item['raw_extra'];
      foreach ($extra_columns as $id => $extra_column) {
        if (isset($raw_extra[$id])) {
          $tokens['{{ ' . $this->options['id'] . '__' . $id . ' }}'] = (string) $raw_extra[$id];
        }
        else {
          // Make sure that empty values are replaced as well.
          $tokens['{{ ' . $this->options['id'] . '__' . $id . ' }}'] = '';
        }
      }
    }
  }

}
