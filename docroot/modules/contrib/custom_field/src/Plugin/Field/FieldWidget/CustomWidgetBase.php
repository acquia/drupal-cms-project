<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Field\FieldWidget;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Base widget definition for custom field type.
 */
abstract class CustomWidgetBase extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'wrapper' => 'details',
      'label_value' => '',
      'label_limit' => 60,
      'label_prefix' => 'Item',
      'auto_collapse' => FALSE,
      'open' => TRUE,
      'fields' => [],
    ] + parent::defaultSettings();
  }

  /**
   * Constructs a custom field widget.
   *
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface $customFieldTypeManager
   *   The custom field type manager.
   * @param \Drupal\custom_field\Plugin\CustomFieldWidgetManagerInterface $customFieldWidgetManager
   *   The custom field widget manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, protected CustomFieldTypeManagerInterface $customFieldTypeManager, protected CustomFieldWidgetManagerInterface $customFieldWidgetManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.custom_field_type'),
      $container->get('plugin.manager.custom_field_widget')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $definition = $this->fieldDefinition;
    $is_multiple = $definition->getFieldStorageDefinition()->isMultiple();
    $field_name = $definition->getName();
    $settings = $this->getSettings() + static::defaultSettings();
    $field_settings = $this->getSetting('fields') ?? [];
    $custom_items = $this->getCustomFieldItems($form_state);
    $values = $form_state->getValues();

    $elements = parent::settingsForm($form, $form_state);
    $elements['#tree'] = TRUE;
    $elements['#attached']['library'][] = 'custom_field/custom-field-admin';

    $elements['wrapper'] = [
      '#type' => 'select',
      '#title' => $this->t('Wrapper'),
      '#default_value' => $settings['wrapper'],
      '#options' => [
        'div' => $this->t('Default (div)'),
        'fieldset' => $this->t('Fieldset'),
        'details' => $this->t('Details'),
      ],
    ];
    $textual_custom_items = array_filter($custom_items, static function (CustomFieldTypeInterface $custom_item) {
      return in_array($custom_item->getDataType(), ['string', 'email', 'telephone']);
    });
    $elements['label_value'] = [
      '#type' => 'select',
      '#title' => $this->t('Label value'),
      '#description' => $this->t('Select a textual property that will be used as the label for the details summary element. If not provided or if property contains no data, the label will use the default numeric label.'),
      '#required' => FALSE,
      '#default_value' => $settings['label_value'],
      '#options' => array_map(static function (CustomFieldTypeInterface $custom_item) {
        return $custom_item->getLabel();
      }, $textual_custom_items),
      '#empty_option' => $this->t('- None -'),
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][wrapper]"]' => ['value' => 'details'],
        ],
      ],
    ];
    $elements['label_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Label limit'),
      '#description' => $this->t('The maximum number of characters to display in the label.'),
      '#default_value' => $settings['label_limit'],
      '#required' => TRUE,
      '#max' => 255,
      '#min' => 10,
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][wrapper]"]' => ['value' => 'details'],
          0 => 'AND',
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][label_value]"]' => ['!value' => ''],
        ],
      ],
    ];
    $elements['label_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label prefix'),
      '#description' => $this->t('The label prefix for the details summary element. E.g. <em>Item (1)</em>, <em>Item (2)</em>, <em>Item (3)</em>. Leave empty for default functionality.'),
      '#default_value' => $settings['label_prefix'],
      '#maxlength' => 30,
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][wrapper]"]' => ['value' => 'details'],
          0 => 'AND',
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][label_value]"]' => ['value' => ''],
        ],
      ],
      '#access' => $is_multiple,
    ];
    $elements['open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show open by default?'),
      '#default_value' => $settings['open'],
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][wrapper]"]' => ['value' => 'details'],
        ],
      ],
    ];
    $elements['auto_collapse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-collapse'),
      '#description' => $this->t('When one item is opened, close all others.'),
      '#default_value' => $settings['auto_collapse'],
      '#states' => [
        'visible' => [
          'select[name="fields[' . $field_name . '][settings_edit_form][settings][wrapper]"]' => ['value' => 'details'],
          0 => 'AND',
          'input[name="fields[' . $field_name . '][settings_edit_form][settings][open]"]' => ['checked' => FALSE],
        ],
      ],
      '#access' => $is_multiple,
    ];
    $elements['fields'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Field settings'),
        $this->t('Weight'),
      ],
      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'field-settings-order-weight',
        ],
      ],
      '#responsive' => FALSE,
      '#sticky' => FALSE,
      '#weight' => 10,
      '#attributes' => [
        'class' => ['form-fields-settings-table'],
      ],
    ];

    foreach ($custom_items as $name => $custom_item) {
      $plugin_id = $custom_item->getPluginId();
      $value_keys = [
        'fields',
        $field_name,
        'settings_edit_form',
        'settings',
        'fields',
        $name,
      ];
      $wrapper_id = 'field-' . $field_name . '-' . $name;

      // UUid fields have no configuration.
      if ($plugin_id === 'uuid') {
        continue;
      }
      $settings = $field_settings[$name] ?? [];
      $weight = $settings['weight'] ?? 0;
      $options = self::getCustomFieldWidgetOptions($custom_item);
      $options_count = count($options);
      $widget_type = $settings['type'] ?? NULL;

      if (!empty($widget_type) && in_array($widget_type, $this->customFieldWidgetManager->getWidgetsForField($plugin_id))) {
        $type = $widget_type;
      }
      else {
        $type = $custom_item->getDefaultWidget();
      }
      if (!empty($values)) {
        $type = NestedArray::getValue($values, [...$value_keys, 'type']) ?? $type;
      }
      $open = FALSE;
      // Keep details open when type changes.
      if ($form_state->isRebuilding()) {
        $trigger = $form_state->getTriggeringElement();
        if (in_array($name, $trigger['#parents']) && end($trigger['#parents']) === 'type') {
          $open = TRUE;
        }
      }

      $elements['fields'][$name] = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
        '#weight' => $weight,
      ];
      $elements['fields'][$name]['settings'] = [
        '#type' => 'details',
        '#title' => $this->t('@label', ['@label' => $custom_item->getLabel()]),
        '#parents' => $value_keys,
        '#open' => $open,
        '#prefix' => '<div id="' . $wrapper_id . '">',
        '#suffix' => '</div>',
      ];
      $elements['fields'][$name]['settings']['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Widget'),
        '#options' => $options,
        '#default_value' => $type,
        '#value' => $type,
        '#ajax' => [
          'callback' => [$this, 'widgetTypeCallback'],
          'wrapper' => $wrapper_id,
        ],
        '#attributes' => [
          'disabled' => $options_count <= 1,
        ],
      ];

      $plugin_options = $this->customFieldWidgetManager->createOptionsForInstance(
        $field_name,
        $custom_item,
        $type,
        $settings,
        'default'
      );
      /** @var \Drupal\custom_field\Plugin\CustomFieldWidgetInterface $widget */
      $widget = $this->customFieldWidgetManager->getInstance($plugin_options);
      $elements['fields'][$name]['settings'] += $widget->widgetSettingsForm($form_state, $custom_item);
      $elements['fields'][$name]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @label', ['@label' => $custom_item->getLabel()]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['field-settings-order-weight']],
      ];
    }

    return $elements;
  }

  /**
   * Ajax callback for changing widget type.
   *
   * Selects and returns the fieldset with the names in it.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The updated form element.
   */
  public function widgetTypeCallback(array $form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $wrapper_id = $trigger['#ajax']['wrapper'];

    // Get the current parent array for this widget.
    $parents = $trigger['#array_parents'];
    $sliced_parents = array_slice($parents, 0, -1, TRUE);

    // Get the updated element from the form structure.
    $updated_element = NestedArray::getValue($form, $sliced_parents);

    // Create an AjaxResponse.
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $updated_element));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings() + static::defaultSettings();
    $is_multiple = $this->getFieldStorageDefinition()->isMultiple();
    $summary = [];
    $summary[] = $this->t('Wrapper: @wrapper', ['@wrapper' => $settings['wrapper']]);
    if ($settings['wrapper'] === 'details') {
      if ($is_multiple) {
        $label_value = $settings['label_value'];
        $label_limit = $settings['label_limit'];
        if (!empty($label_value)) {
          $summary[] = $this->t('Label value: @label_value', ['@label_value' => $label_value]);
          $summary[] = $this->t('Label limit: @label_limit', ['@label_limit' => $label_limit]);
        }
        else {
          $label_prefix = $settings['label_prefix'] ?? '';
          $summary[] = $this->t('Label prefix: @label', ['@label' => empty($label_prefix) ? 'Default' : $label_prefix]);
        }
      }
      $summary[] = $this->t('Open: @open', ['@open' => $settings['open'] ? 'Yes' : 'No']);
      if ($is_multiple && !$settings['open']) {
        $summary[] = $this->t('Auto-collapse: @auto_collapse', ['@auto_collapse' => $settings['auto_collapse'] ? 'Yes' : 'No']);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['#attached']['library'][] = 'custom_field/custom-field-widget';
    $element['#attributes']['class'][] = 'custom-field-widget-wrapper';
    $element['#type'] = 'container';
    $field_name = $items->getName();
    $parents = $element['#field_parents'] ?? [];
    $is_multiple = $this->getFieldStorageDefinition()->isMultiple();
    $settings = $this->getSettings() + static::defaultSettings();
    $wrapper = $settings['wrapper'];

    if ($wrapper === 'fieldset') {
      $element['#type'] = 'fieldset';
    }
    elseif ($wrapper === 'details') {
      $field_state = static::getWidgetState($parents, $field_name, $form_state);
      $open = $settings['open'] ?? FALSE;
      $label_value = $settings['label_value'];
      $label_limit = $settings['label_limit'];
      $label_prefix = $settings['label_prefix'];

      if (!$open && $trigger = $form_state->getTriggeringElement()) {
        $trigger_parents = $trigger['#parents'] ?? [];
        // Set a new item to open when triggered by the 'add more' button.
        if (in_array($field_name, $trigger_parents, TRUE) && end($trigger_parents) === 'add_more') {
          if ($field_state['items_count'] === $delta) {
            $open = TRUE;
          }
        }
      }

      $element['#type'] = 'details';
      $element['#open'] = $open;
      if ($is_multiple) {
        $element['#attributes']['class'][] = 'custom-field-collapsible';

        // Set the default details label value.
        $raw_label = $items->get($delta)->getValue()[$label_value] ?? '';
        if (!empty($label_value) && !empty($raw_label)) {
          $plain = Html::escape(Html::decodeEntities(strip_tags($raw_label)));
          $truncated = Unicode::truncate($plain, (int) $label_limit, TRUE, TRUE);
          $element['#title'] = PlainTextOutput::renderFromHtml($truncated);
        }
        elseif (!empty($label_prefix)) {
          $element['#title'] = $this->t('@prefix (@delta)', [
            '@prefix' => $label_prefix,
            '@delta' => $delta + 1,
          ]);
        }
      }
    }

    return $element;
  }

  /**
   * Get the field storage definition.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface
   *   The field storage definition.
   */
  public function getFieldStorageDefinition(): FieldStorageDefinitionInterface {
    return $this->fieldDefinition->getFieldStorageDefinition();
  }

  /**
   * Get the custom field items for this field.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\custom_field\Plugin\CustomFieldTypeInterface[]
   *   An array of custom field items.
   */
  public function getCustomFieldItems(FormStateInterface $form_state): array {
    $settings = $this->fieldDefinition->getSettings();
    $fields = $this->getSetting('fields') ?? [];

    // Account for unsaved fields in field config default values form.
    if (!empty($form_state->get('current_settings'))) {
      $settings = $form_state->get('current_settings');
    }
    $custom_items = $this->customFieldTypeManager->getCustomFieldItems($settings);

    // Sort items by weight.
    uasort($custom_items, function (CustomFieldTypeInterface $a, CustomFieldTypeInterface $b) use ($fields) {
      $weight_a = $fields[$a->getName()]['weight'] ?? 0;
      $weight_b = $fields[$b->getName()]['weight'] ?? 0;
      return $weight_a <=> $weight_b;
    });

    return $custom_items;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $columns = $this->getFieldSetting('columns');
    $plugins = $this->getWidgetPlugins();
    foreach ($values as &$item) {
      foreach ($item as $field_name => &$field_value) {
        $plugin = $plugins[$field_name] ?? NULL;

        if ($plugin && method_exists($plugin, 'massageFormValue')) {
          $field_value = $plugin->massageFormValue($field_value, $columns[$field_name] ?? '');
        }
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state): array {
    $parents = $form['#parents'];
    $field_name = $this->fieldDefinition->getName();
    $storage_definition = $this->getFieldStorageDefinition();
    $cardinality = $storage_definition->getCardinality();
    $is_multiple = $storage_definition->isMultiple();
    $processed_flag = "custom_field_{$field_name}_processed";
    $settings = $this->getSettings() + static::defaultSettings();
    if (!empty($parents)) {
      $id_suffix = implode('_', $parents);
      $processed_flag .= "_{$id_suffix}";
    }

    // If we're using unlimited cardinality we don't display one empty item.
    // Form validation will kick in if left empty which essentially means
    // people won't be able to submit without filling required fields for
    // another value.
    if ($cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && count($items) > 0 && !$form_state->get($processed_flag)) {
      $field_state = static::getWidgetState($parents, $field_name, $form_state);
      if (empty($field_state['array_parents'])) {
        --$field_state['items_count'];
        static::setWidgetState($parents, $field_name, $form_state, $field_state);

        // Set a flag on the form denoting that we've already removed the empty
        // item that is usually appended to the end on fresh form loads.
        $form_state->set($processed_flag, TRUE);
      }
    }

    $elements = parent::formMultipleElements($items, $form, $form_state);
    if ($is_multiple && $settings['wrapper'] === 'details') {
      $open = $settings['open'];
      $label_value = $settings['label_value'];
      $label_limit = $settings['label_limit'];
      $auto_collapse = !$open && $settings['auto_collapse'];
      $table_class = implode('-', [...$parents, $field_name]);
      $elements['#custom_field_header'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('@label', ['@label' => $open ? 'Collapse all' : 'Edit all']),
        '#table_class' => $table_class,
        '#attributes' => [
          'type' => 'button',
          'class' => [
            'button',
            'button--extrasmall',
            'expand-all-details',
          ],
        ],
      ];
      $elements['#attached']['drupalSettings']['custom_field']['auto_collapse'][$table_class] = $auto_collapse;
      $elements['#attached']['library'][] = 'custom_field/custom-field-widget';
      $elements['#attached']['library'][] = 'custom_field/custom-field-table-header';
      $elements['#attached']['library'][] = 'custom_field/custom-field-widget-details-label';
      if (!empty($label_value)) {
        $elements['#attached']['drupalSettings']['custom_field']['label_limit'] = $label_limit;
        foreach (Element::children($elements) as $key) {
          if (!is_int($key)) {
            continue;
          }
          $elements[$key]['#attributes']['data-details-label-target'] = TRUE;
          if (isset($elements[$key][$label_value])) {
            $elements[$key][$label_value]['#attributes']['data-details-label-provider'] = TRUE;
          }
        }
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    $path = explode('.', $error->getPropertyPath());
    $field_name = (string) end($path);
    $plugins = $this->getWidgetPlugins();
    if (!empty($element[$field_name]) && isset($plugins[$field_name])) {
      $plugin = $plugins[$field_name];
      if (method_exists($plugin, 'errorElement')) {
        return $plugin->errorElement($element, $error, $form, $form_state);
      }
    }
    return isset($error->arrayPropertyPath[0]) ? $element[$error->arrayPropertyPath[0]] : $element;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $plugins = $this->getWidgetPlugins();
    foreach ($plugins as $plugin) {
      $plugin_dependencies = $plugin->calculateWidgetDependencies();
      $dependencies = array_merge_recursive($dependencies, $plugin_dependencies);
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);
    $plugins = $this->getWidgetPlugins();
    $fields = $this->getSetting('fields');
    foreach ($plugins as $name => $plugin) {
      $changed_settings = $plugin->onWidgetDependencyRemoval($dependencies);
      if (!empty($changed_settings) && isset($fields[$name])) {
        $fields[$name] = $changed_settings;
        $changed = TRUE;
      }
    }
    if ($changed) {
      $this->setSetting('fields', $fields);
    }

    return $changed;
  }

  /**
   * Reports field-level validation errors against actual form elements.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\custom_field\Plugin\Field\FieldType\CustomItem> $items
   *   The field values.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   A list of constraint violations to flag.
   * @param array<string, mixed> $form
   *   The form structure where field elements are attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function flagErrors(FieldItemListInterface $items, ConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state): void {
    $plugins = $this->getWidgetPlugins();
    foreach ($plugins as $plugin) {
      if (method_exists($plugin, 'flagErrors')) {
        $plugin->flagErrors($items, $violations, $form, $form_state);
      }
    }

    parent::flagErrors($items, $violations, $form, $form_state);
  }

  /**
   * Return the available widget plugins as an array keyed by plugin_id.
   *
   * @param \Drupal\custom_field\Plugin\CustomFieldTypeInterface $custom_item
   *   The Custom field type interface.
   *
   * @return array<string, mixed>
   *   The array of widget options.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private static function getCustomFieldWidgetOptions(CustomFieldTypeInterface $custom_item): array {
    $options = [];
    /** @var \Drupal\custom_field\Plugin\CustomFieldWidgetManager $plugin_service */
    $plugin_service = \Drupal::service('plugin.manager.custom_field_widget');
    $definitions = $plugin_service->getDefinitions();
    $type = $custom_item->getPluginId();
    // Remove undefined widgets for data_type.
    foreach ($definitions as $key => $definition) {
      /** @var \Drupal\custom_field\Plugin\CustomFieldWidgetInterface $instance */
      $instance = $plugin_service->createInstance($definition['id']);
      if (!$instance::isApplicable($custom_item)) {
        unset($definitions[$key]);
      }
      if (!in_array($type, $definition['field_types'])) {
        unset($definitions[$key]);
      }
    }
    // Sort the widgets by category and then by name.
    uasort($definitions, function ($a, $b) {
      if ($a['category'] != $b['category']) {
        return strnatcasecmp((string) $a['category'], (string) $b['category']);
      }
      return strnatcasecmp((string) $a['label'], (string) $b['label']);
    });
    foreach ($definitions as $id => $definition) {
      $category = $definition['category'];
      // Add category grouping for multiple options.
      $options[(string) $category][$id] = $definition['label'];
    }
    if (count($options) <= 1) {
      $options = array_values($options)[0];
    }

    return $options;
  }

  /**
   * Helper function to fetch field widget plugins.
   *
   * @return array<string, \Drupal\custom_field\Plugin\CustomFieldWidgetInterface>
   *   An array of widget plugins.
   */
  protected function getWidgetPlugins(): array {
    $plugins = [];
    $fields = $this->getSetting('fields');
    $custom_items = $this->customFieldTypeManager->getCustomFieldItems($this->fieldDefinition->getSettings());
    foreach ($custom_items as $name => $custom_item) {
      if ($custom_item->getDataType() === 'uuid') {
        continue;
      }
      $widget = $fields[$name]['type'] ?? $custom_item->getDefaultWidget();
      $options = $this->customFieldWidgetManager->createOptionsForInstance($this->fieldDefinition->getName(), $custom_item, $widget, $fields[$name] ?? [], 'default');
      try {
        /** @var \Drupal\custom_field\Plugin\CustomFieldWidgetInterface $plugin */
        $plugin = $this->customFieldWidgetManager->getInstance($options);
        $plugins[(string) $name] = $plugin;
      }
      catch (PluginException $e) {
        // No errors applicable if we somehow have an invalid plugin.
      }
    }

    return $plugins;
  }

}
