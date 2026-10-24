<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Field\FieldType;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateRangeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\custom_field\Time;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Plugin implementation of the 'custom' field type.
 */
#[FieldType(
  id: 'custom',
  label: new TranslatableMarkup('Custom field'),
  description: new TranslatableMarkup('A field of fields stored in a single table.'),
  default_widget: 'custom_stacked',
  default_formatter: 'custom_formatter',
  list_class: '\Drupal\custom_field\Plugin\Field\FieldType\CustomItemList',
)]
class CustomItem extends FieldItemBase {

  use StringTranslationTrait;

  /**
   * The custom field separator for extended properties.
   *
   * @var string
   */
  public const SEPARATOR = '__';

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   A list of default settings, keyed by the setting name.
   */
  public static function defaultStorageSettings(): array {
    // Need to have at least one item by default because the table is created
    // before the user gets a chance to customize and will throw an Exception
    // if there isn't at least one column defined.
    return [
      'columns' => [
        'value' => [
          'name' => 'value',
          'type' => 'string',
        ],
      ],
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    /** @var \Drupal\custom_field\Plugin\CustomFieldTypeManager $plugin_service */
    $plugin_service = \Drupal::service('plugin.manager.custom_field_type');
    $columns = [];
    foreach ($field_definition->getSetting('columns') as $item) {
      $plugin = $plugin_service->createInstance($item['type']);
      if (method_exists($plugin, 'schema')) {
        $field_schema = $plugin->schema($item);
        $columns += $field_schema;
      }
    }

    $schema['columns'] = $columns;

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    /** @var \Drupal\custom_field\Plugin\CustomFieldTypeManager $plugin_service */
    $plugin_service = \Drupal::service('plugin.manager.custom_field_type');
    $properties = [];

    foreach ($field_definition->getSetting('columns') as $item) {
      try {
        $plugin = $plugin_service->createInstance($item['type']);
        if (method_exists($plugin, 'propertyDefinitions')) {
          $definitions = $plugin->propertyDefinitions($item);
          if (is_array($definitions)) {
            $properties += $definitions;
          }
        }
      }
      catch (PluginException $e) {
        continue;
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   An associative array of values.
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    $field_manager = \Drupal::service('plugin.manager.custom_field_type');
    /** @var \Drupal\custom_field\Plugin\CustomFieldTypeInterface[] $custom_items */
    $custom_items = $field_manager->getCustomFieldItems($field_definition->getSettings());
    $target_entity_type = $field_definition->getTargetEntityTypeId();
    $values = [];
    foreach ($custom_items as $name => $custom_item) {
      $values[(string) $name] = $custom_item->generateSampleValue($custom_item, $target_entity_type);
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraints = parent::getConstraints();
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $settings = $this->getSettings();
    /** @var \Drupal\custom_field\Plugin\CustomFieldTypeManager $plugin_service */
    $plugin_service = \Drupal::service('plugin.manager.custom_field_type');
    $field_constraints = [];
    $custom_items = $plugin_service->getCustomFieldItems($settings);
    foreach ($custom_items as $name => $custom_item) {
      $item_constraints = $custom_item->getConstraints();
      if (!empty($item_constraints)) {
        $field_constraints[$name] = $custom_item->getConstraints();
      }
    }

    if (!empty($field_constraints)) {
      $constraints[] = $constraint_manager->create('ComplexData', $field_constraints);
    }

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(): void {
    parent::preSave();
    $field_definition = $this->getFieldDefinition();
    $field_name = $field_definition->getName();
    $custom_items = $this->getCustomFieldManager()->getCustomFieldItems($field_definition->getSettings());
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $is_default_translation = FALSE;
    $has_translations = FALSE;
    $original_entity = $entity;

    if (!$entity->isNew() && $entity->isTranslatable() && $field_definition->isTranslatable()) {
      $is_default_translation = $entity->isDefaultTranslation();
      $has_translations = count($entity->getTranslationLanguages()) > 1;
      $original_entity = $has_translations ? $entity->getUntranslated() : $entity;
    }

    // Get the fields from the original or current entity based on whether it
    // has translations.
    /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomFieldItemListInterface<\Drupal\custom_field\Plugin\Field\FieldType\CustomItem>[] $original_fields */
    $original_fields = $original_entity->get($field_name);
    foreach ($original_fields as $delta => $original_field) {

      $current_field = $entity->get($field_name)->get($delta);
      if (!$current_field instanceof CustomItem) {
        continue;
      }

      foreach ($custom_items as $name => $custom_item) {
        $field_type = $custom_item->getDataType();
        $is_subfield_translatable = $custom_item->getFieldSetting('translatable') ?? FALSE;

        // The synchronization logic only applies if the entity supports
        // translations, and we're not in the default language.
        if ($has_translations && !$is_default_translation && !$is_subfield_translatable) {
          // Fetch the value from the default language for this delta.
          $default_value = $original_field->{$name};
          if (!empty($default_value)) {
            $current_field->{$name} = $default_value;
            // Set extra default language properties for image.
            if ($field_type === 'image') {
              $alt = $original_field->{$name . self::SEPARATOR . 'alt'};
              $title = $original_field->{$name . self::SEPARATOR . 'title'};
              $width = $original_field->{$name . self::SEPARATOR . 'width'};
              $height = $original_field->{$name . self::SEPARATOR . 'height'};
              $current_field->{$name . self::SEPARATOR . 'alt'} = $alt;
              $current_field->{$name . self::SEPARATOR . 'title'} = $title;
              $current_field->{$name . self::SEPARATOR . 'width'} = $width;
              $current_field->{$name . self::SEPARATOR . 'height'} = $height;
            }
            if ($field_type === 'link') {
              $title = $original_field->{$name . self::SEPARATOR . 'title'};
              $options = $original_field->{$name . self::SEPARATOR . 'options'};
              $current_field->{$name . self::SEPARATOR . 'title'} = $title;
              $current_field->{$name . self::SEPARATOR . 'options'} = $options;
            }
            if (in_array($field_type, ['datetime', 'daterange'])) {
              $timezone = $original_field->{$name . self::SEPARATOR . 'timezone'};
              $current_field->{$name . self::SEPARATOR . 'timezone'} = $timezone;
            }
            if (in_array($field_type, ['daterange', 'time_range'])) {
              $end = $original_field->{$name . self::SEPARATOR . 'end'};
              $duration = $original_field->{$name . self::SEPARATOR . 'duration'};
              $current_field->{$name . self::SEPARATOR . 'end'} = $end;
              $current_field->{$name . self::SEPARATOR . 'duration'} = $duration;
            }
          }
        }

        // Set subfield value to current applicable translation value.
        $subfield_value = $current_field->{$name};

        // Existing field type handling logic, which should work for all cases:
        switch ($field_type) {
          case 'color':
            $color = is_string($subfield_value) ? trim($subfield_value) : '';
            if (str_starts_with($color, '#')) {
              $color = substr($color, 1);
            }

            // Make sure we have a valid hexadecimal color.
            $current_field->{$name} = strlen($color) === 6 ? '#' . strtoupper($color) : NULL;
            break;

          case 'map':
          case 'map_string':
            if (!is_array($subfield_value) || empty($subfield_value)) {
              $current_field->{$name} = NULL;
            }
            else {
              $current_field->{$name} = array_values($subfield_value);
            }
            break;

          case 'decimal':
            if (is_numeric($subfield_value)) {
              $scale = $custom_item->getScale();
              if (is_string($subfield_value)) {
                $subfield_value = (float) $subfield_value;
              }
              $current_field->{$name} = round($subfield_value, $scale);
            }
            break;

          case 'time':
            if ($subfield_value == '86401') {
              $current_field->{$name} = NULL;
            }
            break;

          case 'image':
            if (empty($subfield_value)) {
              foreach (['alt', 'title', 'width', 'height'] as $extra_property) {
                $current_field->{$name . self::SEPARATOR . $extra_property} = NULL;
              }
            }
            else {
              if (is_array($subfield_value) && isset($subfield_value['target_id'])) {
                $subfield_value = $subfield_value['target_id'];
              }
              if (!empty($subfield_value)) {
                $width = $current_field->get($name . self::SEPARATOR . 'width')
                  ->getValue();
                $height = $current_field->get($name . self::SEPARATOR . 'height')
                  ->getValue();
                if (empty($width) || empty($height)) {
                  /** @var \Drupal\file\FileInterface $file */
                  $file = \Drupal::entityTypeManager()
                    ->getStorage('file')
                    ->load($subfield_value);
                  if ($file) {
                    $image = \Drupal::service('image.factory')
                      ->get($file->getFileUri());
                    if ($image->isValid()) {
                      $current_field->{$name . self::SEPARATOR . 'width'} = $image->getWidth();
                      $current_field->{$name . self::SEPARATOR . 'height'} = $image->getHeight();
                    }
                  }
                }
              }
            }
            break;

          case 'link':
            if (empty($subfield_value)) {
              // Set extra properties to empty state.
              $current_field->{$name . self::SEPARATOR . 'title'} = NULL;
              $current_field->{$name . self::SEPARATOR . 'options'} = [];
            }
            break;

          case 'datetime':
            if (empty($subfield_value)) {
              // Set timezone to NULL when there is no date value.
              $current_field->{$name . self::SEPARATOR . 'timezone'} = NULL;
            }
            break;

          case 'daterange':
            if (empty($subfield_value)) {
              foreach (['end', 'timezone', 'duration'] as $extra_property) {
                $current_field->{$name . self::SEPARATOR . $extra_property} = NULL;
              }
            }
            else {
              $start_date = $current_field->get($name . self::SEPARATOR . 'start_date')
                ->getValue();
              $end_date = $current_field->get($name . self::SEPARATOR . 'end_date')
                ->getValue();
              $duration = NULL;
              if ($start_date instanceof DrupalDateTime) {
                $duration = 0;
                if ($end_date instanceof DrupalDateTime) {
                  $difference = $end_date->getTimestamp() - $start_date->getTimestamp();
                  if ($difference > 0) {
                    $duration = $difference;
                  }
                }
                $current_field->{$name . self::SEPARATOR . 'duration'} = $duration;
              }
            }
            break;

          case 'time_range':
            $start_value = $current_field->get($name)->getValue();
            $end_value = $current_field->get($name . self::SEPARATOR . 'end')->getValue();
            $duration = NULL;
            try {
              $start_time = Time::createFromTimestamp($start_value);
              $end_time = Time::createFromTimestamp($end_value);
            }
            catch (\Exception $e) {
              $start_time = NULL;
              $end_time = NULL;
            }
            if (!$start_time) {
              foreach (['end', 'duration'] as $extra_property) {
                $current_field->{$name . self::SEPARATOR . $extra_property} = NULL;
              }
            }
            else {
              if ($end_time) {
                $start_timestamp = $start_time->getTimestamp();
                $end_timestamp = $end_time->getTimestamp();
                if ($start_timestamp > $end_timestamp) {
                  $current_field->{$name . self::SEPARATOR . 'end'} = NULL;
                }
                else {
                  $difference = $end_timestamp - $start_timestamp;
                  if ($difference > 0) {
                    $duration = $difference;
                  }
                }
              }
              $current_field->{$name . self::SEPARATOR . 'duration'} = $duration;
            }
            break;

          case 'viewfield':
            if (empty($subfield_value)) {
              // Set extra properties to NULL.
              foreach (['display', 'arguments', 'items'] as $extra_property) {
                $current_field->{$name . self::SEPARATOR . $extra_property} = NULL;
              }
            }
            break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   * @param bool $has_data
   *   TRUE if the field already has data, FALSE if not.
   *
   * @return array<string, mixed>
   *   The form definition for the field settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data): array {
    assert($form_state instanceof SubformStateInterface);
    $form_state = $form_state->getCompleteFormState();
    $parents = ['field_storage', 'subform', 'settings'];
    $storage = $form_state->getStorage();
    $settings = $this->getSettings();
    $current_settings = $form_state->get('current_settings');
    $field_name = $this->getFieldDefinition()->getName();
    // Calculate a safe max column length to coincide with SQL column limit.
    $max_name_length = 64 - strlen($field_name) - 12;
    $is_cloning = FALSE;
    if (empty($current_settings)) {
      $form_state->set('current_settings', $this->getSettings());
    }

    if ($form_state->isRebuilding()) {
      $settings['items'] = $form_state->getValue([...$parents, 'items']) ?? $current_settings['columns'];
      $is_cloning = !empty($form_state->getValue([...$parents, 'clone']));
      $field_settings = $form_state->getValue(['settings', 'field_settings']) ?? $current_settings['field_settings'];
      $current_columns = $current_settings['columns'];
      $columns = $settings['items'];
      $user_input = $form_state->getUserInput();
      $input = NestedArray::getValue($user_input, [...$parents, 'items']);
      $widget_input = NestedArray::getValue($user_input, ['settings', 'field_settings']);
      $default_value_input = NestedArray::getValue($user_input, ['default_value_input', $field_name]);
      $reset_input = FALSE;
      foreach ($settings['items'] as $name => $item) {
        unset($item['remove']);
        if ($name != $item['name']) {
          $settings['items'][$item['name']] = $item;
          $columns[$item['name']] = $item;
          unset($settings['items'][$name]);
          unset($columns[$name]);
          if (isset($field_settings[$name])) {
            unset($field_settings[$name]);
          }
          // Clear out existing input.
          if (isset($input[$name])) {
            unset($input[$name]);
          }
          // Clear out existing widget input.
          if (isset($widget_input[$name])) {
            unset($widget_input[$name]);
          }
          $reset_input = TRUE;
        }
        elseif (isset($current_columns[$name])) {
          $diff_item = array_filter($item, 'is_scalar');
          $diff_column = array_filter($current_columns[$name], 'is_scalar');
          $diffs = array_diff($diff_item, $diff_column);
          if (!empty($diffs)) {
            $columns[$name] = $item;
            if (isset($field_settings[$name])) {
              unset($field_settings[$name]);
            }
          }
          if ($item['type'] !== $current_columns[$name]['type']) {
            // Clear out existing widget.
            if (isset($field_settings[$name])) {
              unset($field_settings[$name]);
            }
            // Clear out existing widget input.
            if (isset($widget_input[$name])) {
              unset($widget_input[$name]);
              $reset_input = TRUE;
            }
            // Clear out default value input.
            foreach ($default_value_input as $delta => $default_value) {
              if (isset($default_value[$name])) {
                unset($default_value_input[$delta][$name]);
                $reset_input = TRUE;
              }
            }

            if (isset($item['length'])) {
              // Unset length.
              if (!in_array($item['type'], ['string', 'telephone'])) {
                unset($item['length']);
                if (isset($input[$name]['length'])) {
                  unset($input[$name]['length']);
                }
              }
              // Reset length.
              else {
                $item['length'] = NULL;
                if (isset($input[$name]['length'])) {
                  $input[$name]['length'] = NULL;
                }
              }
              $columns[$name] = $item;
              $settings['items'][$name] = $item;
              $reset_input = TRUE;
            }

            if ($item['type'] === 'entity_reference') {
              $settings['items'][$name]['target_type'] = NULL;
              // Force the selection of target type.
              unset($columns[$name]);
            }
            elseif (in_array($item['type'], ['file', 'image'])) {
              $settings['items'][$name]['target_type'] = 'file';
            }
            elseif ($item['type'] === 'viewfield') {
              $settings['items'][$name]['target_type'] = 'view';
            }
          }
        }
      }
      $form_state->set('current_settings', [
        'columns' => $columns,
        'field_settings' => $field_settings,
      ]);
      if ($reset_input) {
        $user_input = $form_state->getUserInput();
        NestedArray::setValue($user_input, [...$parents, 'items'], $input);
        NestedArray::setValue($user_input, ['settings', 'field_settings'], $widget_input);
        NestedArray::setValue($user_input, ['default_value_input', $field_name], $default_value_input);
        $form_state->setUserInput($user_input);
      }
    }
    else {
      $settings['items'] = $settings['columns'];
    }

    $element = [
      '#tree' => TRUE,
      'columns' => [
        '#type' => 'value',
        '#value' => $settings['columns'],
        '#parents' => [...$parents, 'columns'],
      ],
      'items' => [
        '#type' => 'container',
        '#parents' => [...$parents, 'items'],
        '#title' => $this->t('Custom field items'),
        '#attributes' => [
          'style' => 'display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem;',
        ],
      ],
      'actions' => [
        '#type' => 'actions',
        '#weight' => -9,
      ],
    ];

    $items_count = count($settings['items']);

    // Support copying settings from another custom field.
    if (!$has_data) {
      $sources = $this->getExistingCustomFieldStorageOptions($storage['entity_type_id']);
      if (!empty($sources)) {
        $element['clone'] = [
          '#type' => 'select',
          '#title' => $this->t('Clone settings from:'),
          '#description' => $this->t('Copy configuration from an existing field.'),
          '#options' => [
            '' => $this->t("- Don't clone settings -"),
          ] + $sources,
          '#attributes' => [
            'data-id' => 'custom-field-storage-clone',
          ],
          '#weight' => -10,
        ];
        $element['clone_message'] = [
          '#type' => 'container',
          '#states' => [
            'invisible' => [
              'select[data-id="custom-field-storage-clone"]' => ['value' => ''],
            ],
          ],
          // Initialize the display, so we don't see it flash on init page load.
          '#attributes' => [
            'style' => 'display: none;',
          ],
        ];
        $element['clone_message']['message'] = [
          '#markup' => 'The selected custom field field settings will be cloned. Any existing settings for this field will be overwritten. Field widget and formatter settings will not be cloned.',
          '#prefix' => '<div class="messages messages--warning" role="alert" style="display: block;">',
          '#suffix' => '</div>',
        ];
        // Add states to items.
        $element['items']['#states'] = [
          'visible' => [
            'select[data-id="custom-field-storage-clone"]' => ['value' => ''],
          ],
        ];
      }
    }

    foreach ($settings['items'] as $i => $item) {
      $type = $item['type'] ?? '';
      $element['items'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('@label', ['@label' => $item['name']]),
        '#attributes' => [
          'style' => 'margin-top: 0; margin-bottom: 0;',
        ],
        '#open' => !$has_data,
      ];
      $element['items'][$i]['name'] = [
        '#type' => 'machine_name',
        '#description' => $this->t('A unique machine-readable name containing only letters, numbers, or single underscores.'),
        '#default_value' => $item['name'],
        '#disabled' => $has_data,
        '#machine_name' => [
          'source' => ['items', $i, 'name'],
          'exists' => [$this, 'machineNameExists'],
          'label' => $this->t('Machine-readable name'),
          'standalone' => FALSE,
          'error' => $this->t('The machine-readable name must contain only letters, numbers, or single underscores'),
        ],
        '#maxlength' => $max_name_length,
        '#size' => 20,
      ];
      if (!$has_data) {
        $element['items'][$i]['name']['#machine_name']['replace_pattern'] = '[^a-zA-Z0-9_]+|__';
      }
      $element['items'][$i]['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#options' => $this->getCustomFieldManager()->fieldTypeOptions(),
        '#default_value' => $type,
        '#delta' => $i,
        '#required' => TRUE,
        '#empty_option' => $this->t('- Select -'),
        '#disabled' => $has_data,
      ];
      // Length field for supported types.
      if (in_array($type, ['string', 'telephone'])) {
        $default_max = $type === 'telephone' ? 256 : 255;
        $element['items'][$i]['length'] = [
          '#type' => 'number',
          '#title' => $this->t('Length'),
          '#default_value' => !empty($item['length']) ? $item['length'] : $default_max,
          '#required' => TRUE,
          '#description' => $this->t('The maximum length of the field in characters.'),
          '#min' => 1,
          '#max' => $default_max,
          '#disabled' => $has_data,
        ];
      }
      // Size field for supported types.
      if (in_array($type, ['integer', 'float'])) {
        $element['items'][$i]['size'] = [
          '#type' => 'select',
          '#title' => $this->t('Size'),
          '#default_value' => $item['size'] ?? 'normal',
          '#disabled' => $has_data,
          '#options' => [
            'tiny' => $this->t('Tiny'),
            'small' => $this->t('Small'),
            'medium' => $this->t('Medium'),
            'big' => $this->t('Big'),
            'normal' => $this->t('Normal'),
          ],
        ];
      }
      // Unsigned field for supported types.
      if (in_array($type, ['integer', 'float', 'decimal'])) {
        $element['items'][$i]['unsigned'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Unsigned'),
          '#default_value' => $item['unsigned'] ?? FALSE,
          '#disabled' => $has_data,
        ];
      }
      // Decimal field extra settings.
      if ($type === 'decimal') {
        $element['items'][$i]['precision'] = [
          '#type' => 'number',
          '#title' => $this->t('Precision'),
          '#min' => 10,
          '#max' => 32,
          '#default_value' => $item['precision'] ?? 10,
          '#description' => $this->t('The total number of digits to store in the database, including those to the right of the decimal.'),
          '#disabled' => $has_data,
          '#required' => TRUE,
        ];
        $element['items'][$i]['scale'] = [
          '#type' => 'number',
          '#title' => $this->t('Scale'),
          '#description' => $this->t('The number of digits to the right of the decimal.'),
          '#default_value' => $item['scale'] ?? 2,
          '#disabled' => $has_data,
          '#min' => 0,
          '#max' => 10,
          '#required' => TRUE,
        ];
      }
      // Datetime field extra settings.
      if ($type === 'datetime' || $type === 'daterange') {
        $datetime_type_options = [
          DateTimeType::DATETIME_TYPE_DATETIME => $this->t('Date and time'),
          DateTimeType::DATETIME_TYPE_DATE => $this->t('Date only'),
        ];
        if ($type === 'daterange') {
          $datetime_type_options[DateRangeType::DATETIME_TYPE_ALLDAY] = $this->t('All day');
        }
        $element['items'][$i]['datetime_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Date type'),
          '#description' => $this->t('Choose the type of date to create.'),
          '#default_value' => $item['datetime_type'] ?? DateTimeType::DATETIME_TYPE_DATETIME,
          '#disabled' => $has_data,
          '#options' => $datetime_type_options,
          '#required' => TRUE,
        ];
      }
      // Entity reference field extra settings.
      if ($type === 'entity_reference') {
        // Only allow the field to target entity types that have an ID key. This
        // is enforced in ::propertyDefinitions().
        $entity_type_manager = \Drupal::entityTypeManager();
        $filter = function (string $entity_type_id) use ($entity_type_manager): bool {
          return $entity_type_manager->getDefinition($entity_type_id)
            ->hasKey('id');
        };
        $options = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);

        $element['items'][$i]['target_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Type of item to reference'),
          '#default_value' => $item['target_type'] ?? NULL,
          '#required' => TRUE,
          '#disabled' => $has_data,
          '#size' => 1,
        ];
        foreach ($options as $group_name => $group) {
          $element['items'][$i]['target_type']['#options'][$group_name] = array_filter($group, $filter, ARRAY_FILTER_USE_KEY);
        }
      }
      // File & Image field extra settings.
      if ($type === 'file' || $type === 'image') {
        $element['#attached']['library'][] = 'file/drupal.file';
        $scheme_options = \Drupal::service('stream_wrapper_manager')->getNames(StreamWrapperInterface::WRITE_VISIBLE);
        $element['items'][$i]['uri_scheme'] = [
          '#type' => 'radios',
          '#title' => $this->t('Upload destination'),
          '#options' => $scheme_options,
          '#default_value' => $item['uri_scheme'] ?? \Drupal::config('system.file')->get('default_scheme'),
          '#description' => $this->t('Select where the final files should be stored. Private file storage has significantly more overhead than public files, but allows restricted access to files within this field.'),
          '#disabled' => $has_data,
        ];
        $element['items'][$i]['target_type'] = [
          '#type' => 'value',
          '#value' => 'file',
        ];
      }
      // Viewfield extra settings.
      elseif ($type === 'viewfield') {
        $element['items'][$i]['target_type'] = [
          '#type' => 'value',
          '#value' => 'view',
        ];
      }
      $element['items'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#submit' => [get_class($this) . '::removeSubmit'],
        '#name' => 'remove_' . $i,
        '#delta' => $i,
        '#access' => !($has_data || $items_count === 1),
        '#ajax' => [
          'callback' => [$this, 'actionCallback'],
          'wrapper' => 'field-combined',
        ],
      ];
    }

    if (!$has_data && !$is_cloning) {
      $element['actions']['add'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add sub-field'),
        '#submit' => [get_class($this) . '::addSubmit'],
        '#name' => 'custom_field_add_item',
        '#ajax' => [
          'callback' => [$this, 'actionCallback'],
          'wrapper' => 'field-combined',
        ],
        '#attributes' => [
          'class' => [
            'button--primary',
          ],
        ],
      ];
    }

    $form_state->setCached(FALSE);

    return $element;
  }

  /**
   * Submit handler for the StorageConfigEditForm.
   *
   * This handler is added in custom_field.module since it has to be placed
   * directly on the submit button (which we don't have access to in our
   * ::storageSettingsForm() method above).
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function submitStorageConfigEditForm(array &$form, FormStateInterface $form_state): void {
    // Rekey our column settings and overwrite the values in form_state so that
    // we have clean settings saved to the db.
    $columns = [];
    $parents = ['field_storage', 'subform', 'settings'];
    $item_parents = ['field_storage', 'subform', 'settings', 'items'];
    /** @var \Drupal\Core\Field\FieldConfigInterface $field_config */
    $field_config = $form_state->get('field_config');

    if ($field_name = $form_state->getValue([...$parents, 'clone'])) {
      [$entity_type, $bundle_name, $field_name] = explode('.', $field_name);
      $source_storage = FieldStorageConfig::loadByName($entity_type, $field_name);
      $source_field_config = FieldConfig::loadByName($entity_type, $bundle_name, $field_name);
      // Grab the columns from the field storage config.
      $columns = $source_storage->getSetting('columns');
      $field_settings = $source_field_config->getSettings();
      $field_config->setSettings($field_settings);
      $form_state->setValue(['settings'], $field_settings);
    }
    else {
      $items = $form_state->getValue($item_parents) ?? [];
      foreach ($items as $item) {
        $column_name = (string) $item['name'];
        $columns[$column_name] = $item;
        unset($columns[$column_name]['remove']);
      }
    }

    $form_state->setValue([...$parents, 'columns'], $columns);
    $form_state->setValue([...$parents, 'items'], NULL);

    // Reset the field storage config property - it will be recalculated when
    // accessed via the property definitions getter.
    // @see Drupal\field\Entity\FieldStorageConfig::getPropertyDefinitions()
    // If we don't do this, an exception is thrown during the table update that
    // is very difficult to recover from since the original field tables have
    // already been removed at that point.
    $field_storage_config = $form_state->getBuildInfo()['callback_object']->getEntity();
    $field_storage_config->set('propertyDefinitions', NULL);
  }

  /**
   * Checks if machine name already exists for field.
   *
   * @param string $value
   *   The value to compare.
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   Returns TRUE if field exists, otherwise FALSE.
   */
  public function machineNameExists(string $value, array $form, FormStateInterface $form_state): bool {
    $count = 0;
    $parents = ['field_storage', 'subform', 'settings', 'items'];
    $settings = $form_state->getValue($parents) ?? [];
    foreach ($settings as $item) {
      if ($item['name'] == $value) {
        $count++;
      }
    }

    return $count > 1;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $settings = $this->getSettings();
    $custom_items = $this->getCustomFieldManager()->getCustomFieldItems($settings);
    $emptyCounter = 0;
    $field_count = count($custom_items);
    foreach ($custom_items as $name => $custom_item) {
      $definition = $custom_item->getPluginDefinition();
      $check = $custom_item->checkEmpty();
      $no_check = is_array($definition) && array_key_exists('never_check_empty', $definition) && $definition['never_check_empty'];
      $item_value = $this->get($name)->getValue();
      if ($item_value === '' || ($item_value === NULL && !$no_check)) {
        $emptyCounter++;
        // If any of the empty check fields are filled or all fields are empty.
        if ($check || $emptyCounter === $field_count) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE): void {
    $settings = $this->getSettings();
    $custom_items = $this->getCustomFieldManager()->getCustomFieldItems($settings);
    foreach ($custom_items as $name => $custom_item) {
      if ($property_name === $name) {
        $custom_item->onChange($property_name, $notify, $this);
      }
    }
    parent::onChange($property_name, $notify);
  }

  /**
   * Callback for both ajax-enabled buttons in storage form.
   *
   * Selects and returns the fieldset with the names in it.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function actionCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#delta'])) {
      $field = $trigger['#delta'];
      $field_settings = $form_state->getValue(['settings', 'field_settings']);
      if (!empty($field_settings[$field])) {
        unset($field_settings[$field]);
        $form_state->setValue(['settings', 'field_settings'], $field_settings);
      }
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#field-combined', $form));
    if ($trigger['#name'] === 'custom_field_add_item') {
      $sliced_parents = array_slice($trigger['#parents'], 0, -2);
      $items = Element::children(NestedArray::getValue($form, $sliced_parents)['items']);
      $last_item = end($items);
      $input = 'field_storage[subform][settings][items][' . $last_item . '][name]';
      // Set the focus to the name field for the last item added.
      $response->addCommand(new InvokeCommand('input[name="' . $input . '"]', 'focus'));
    }

    return $response;
  }

  /**
   * Submit handler for the "Add another" button.
   *
   * Triggers form state notice to add item and causes a form rebuild.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function addSubmit(array &$form, FormStateInterface $form_state): void {
    $storage = $form_state->getValue('field_storage');
    $default_name = uniqid('value_');
    $default = static::defaultStorageSettings()['columns']['value'];
    unset($default['name']);
    $storage['subform']['settings']['items'][$default_name] = [
      'name' => $default_name,
    ] + $default;
    $form_state->setValue('field_storage', $storage);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "Remove" button.
   *
   * Triggers form state notice to remove item and causes a form rebuild.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function removeSubmit(array &$form, FormStateInterface $form_state): void {
    $remove = $form_state->getTriggeringElement()['#delta'];
    $settings = $form_state->getValue('settings');
    $storage = $form_state->getValue('field_storage');
    unset($storage['subform']['settings']['items'][$remove]);
    // Remove the field setting if it exists.
    if (isset($settings['field_settings'][$remove])) {
      unset($settings['field_settings'][$remove]);
      $form_state->setValue('settings', $settings);
    }
    $form_state->setValue('field_storage', $storage);
    $form_state->setRebuild();
  }

  /**
   * Get the existing custom field storage config options.
   *
   * @param string $entity_type_id
   *   The entity type to match for exclusion.
   *
   * @return array<string, mixed>
   *   An array of existing field configurations.
   */
  protected function getExistingCustomFieldStorageOptions(string $entity_type_id): array {
    $sources = [];
    $existingCustomFields = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('custom');
    $existing_field_name = $this->getFieldDefinition()->getName();
    if (!empty($existingCustomFields)) {
      foreach ($existingCustomFields as $entity_type => $fields) {
        $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
        foreach ($fields as $field_name => $info) {
          if ($entity_type === $entity_type_id && $existing_field_name == $field_name) {
            continue;
          }
          foreach ($info['bundles'] as $bundle) {
            $group = $bundleInfo[$bundle]['label'] . ' (' . $entity_type . ')';
            if ($info = FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
              $sources[$group][$entity_type . '.' . $bundle . '.' . $info->getName()] = $info->getLabel();
            }
          }
        }
      }
    }
    return $sources;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The default field settings.
   */
  public static function defaultFieldSettings(): array {
    return [
      'field_settings' => [],
      'add_more_label' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The field settings form.
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $wrapper_id = 'custom-field-settings-wrapper';
    $is_cloning = FALSE;

    if ($form_state->isRebuilding()) {
      $is_cloning = !empty($form_state->getValue(['field_storage', 'subform', 'settings', 'clone']));
    }

    $element['add_more_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Add another button label'),
      '#description' => $this->t('The add button label for multiple items. Leave empty for default button text.'),
      '#weight' => -100,
      '#default_value' => $this->getSetting('add_more_label'),
      '#attributes' => [
        'disabled' => !$this->getFieldDefinition()->getFieldStorageDefinition()->isMultiple(),
      ],
    ];

    $element['field_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field settings'),
      '#weight' => -99,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#access' => !$is_cloning,
    ];

    $current_settings = $form_state->get('current_settings') ?? $this->getSettings();
    $custom_items = $this->getCustomFieldManager()->getCustomFieldItems($current_settings);

    // Build the table rows and columns.
    foreach ($custom_items as $name => $custom_item) {
      $plugin_id = $custom_item->getPluginId();

      // UUid fields have no configuration.
      if ($plugin_id === 'uuid') {
        continue;
      }

      $element['field_settings'][$name] = [
        '#type' => 'details',
        '#title' => $this->t('@name', ['@name' => $name]),
      ];

      // Add our plugin field settings form.
      $element['field_settings'][$name] += $custom_item->fieldSettingsForm($form, $form_state);

      if ($custom_item->getSetting('never_check_empty')) {
        // Hide the check empty element for irrelevant types.
        $element['field_settings'][$name]['check_empty']['#type'] = 'value';
        $element['field_settings'][$name]['check_empty']['#value'] = FALSE;
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(FieldDefinitionInterface $field_definition): array {
    $dependencies = parent::calculateDependencies($field_definition);
    /** @var \Drupal\custom_field\Plugin\CustomFieldTypeManager $plugin_service */
    $plugin_service = \Drupal::service('plugin.manager.custom_field_type');
    $custom_items = $plugin_service->getCustomFieldItems($field_definition->getSettings());
    $default_value = $field_definition->getDefaultValueLiteral();

    foreach ($custom_items as $custom_item) {
      $plugin_dependencies = $custom_item->calculateDependencies($custom_item, $default_value);
      $dependencies = array_merge_recursive($dependencies, $plugin_dependencies);
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateStorageDependencies(FieldStorageDefinitionInterface $field_definition): array {
    $dependencies = parent::calculateStorageDependencies($field_definition);
    $entity_type_manager = \Drupal::entityTypeManager();
    /** @var \Drupal\custom_field\Plugin\CustomFieldTypeManager $plugin_service */
    $plugin_service = \Drupal::service('plugin.manager.custom_field_type');
    $custom_items = $plugin_service->getCustomFieldItems($field_definition->getSettings());
    foreach ($custom_items as $custom_item) {
      if ($custom_item->getTargetType() !== NULL) {
        $target_entity_type_id = $custom_item->getTargetType();
        try {
          $target_entity_type = $entity_type_manager->getDefinition($target_entity_type_id);
          $plugin_dependencies['module'][] = $target_entity_type->getProvider();
          $dependencies = array_merge_recursive($dependencies, $plugin_dependencies);
        }
        catch (PluginNotFoundException $e) {
          // Plugin not found.
        }
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function onDependencyRemoval(FieldDefinitionInterface $field_definition, array $dependencies): bool {
    $changed = parent::onDependencyRemoval($field_definition, $dependencies);
    $settings = $field_definition->getSettings();
    $columns = $settings['columns'];
    $field_settings = $settings['field_settings'];
    /** @var \Drupal\custom_field\Plugin\CustomFieldTypeManager $plugin_service */
    $plugin_service = \Drupal::service('plugin.manager.custom_field_type');
    /** @var \Drupal\custom_field\Plugin\CustomFieldTypeInterface[] $custom_items */
    $custom_items = $plugin_service->getCustomFieldItems($settings);
    $settings_changed = FALSE;

    // Try to update the default value config dependency, if possible.
    if ($default_value = $field_definition->getDefaultValueLiteral()) {
      $entity_type_manager = \Drupal::entityTypeManager();
      foreach ($default_value as $key => $value) {
        foreach ($value as $column_key => $column_value) {
          if (isset($columns[$column_key])) {
            $column = $columns[(string) $column_key];
            if (isset($column['target_type']) && !empty($column_value)) {
              $entity = $entity_type_manager->getStorage($column['target_type'])
                ->load($column_value);
              if ($entity && isset($dependencies[$entity->getConfigDependencyKey()][$entity->getConfigDependencyName()])) {
                $default_value[$key][$column_key] = NULL;
                $changed = TRUE;
              }
            }
          }
        }
      }
    }
    if ($changed && $field_definition instanceof FieldConfig) {
      $field_definition->setDefaultValue($default_value);
    }

    foreach ($custom_items as $name => $custom_item) {
      $widget_settings = $custom_item->onDependencyRemoval($custom_item, $dependencies);
      if (!empty($widget_settings)) {
        $field_settings[$name]['widget_settings']['settings'] = $widget_settings;
        $settings_changed = TRUE;
      }
    }

    if ($settings_changed && $field_definition instanceof FieldConfig) {
      $field_definition->setSetting('field_settings', $field_settings);
    }

    $changed |= $settings_changed;

    return (bool) $changed;
  }

  /**
   * Get the custom field_type manager plugin.
   *
   * @return \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   *   Returns the 'custom' field type plugin manager.
   */
  public function getCustomFieldManager(): CustomFieldTypeManagerInterface {
    return \Drupal::service('plugin.manager.custom_field_type');
  }

}
