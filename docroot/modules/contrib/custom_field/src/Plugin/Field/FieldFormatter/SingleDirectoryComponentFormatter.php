<?php

declare(strict_types=1);

namespace Drupal\custom_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\custom_field\Event\PreFormatEvent;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;
use Drupal\custom_field\Trait\SdcTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'custom_field_sdc' formatter.
 */
#[FieldFormatter(
  id: 'custom_field_sdc',
  label: new TranslatableMarkup('SDC (Single directory component)'),
  description: new TranslatableMarkup('Renders the items in a single display component.'),
  field_types: [
    'custom',
  ],
  weight: 10,
)]
class SingleDirectoryComponentFormatter extends BaseFormatter {

  use SdcTrait;

  /**
   * The component plugin manager.
   *
   * @var \Drupal\Core\Theme\ComponentPluginManager
   */
  protected ComponentPluginManager $componentManager;

  /**
   * The component prop widget manager.
   *
   * @var \Drupal\custom_field\PluginManager\PropWidgetManagerInterface
   */
  protected PropWidgetManagerInterface $propWidgetManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->componentManager = $container->get('plugin.manager.sdc');
    $instance->propWidgetManager = $container->get('plugin.manager.custom_field_component_prop_widget');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'component' => '',
      'variant' => '',
      'slots' => [],
      'props' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    // Remove the fields section from the base settings form.
    unset($form['fields']);
    $form['#process'] = [
      [$this, 'processSdcSettingsForm'],
    ];

    return $form;
  }

  /**
   * Processes the settings form for the SDC field formatter.
   *
   * @param array<string, mixed> $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $form
   *   The form array.
   *
   * @return array<string, mixed>
   *   The processed form element.
   */
  public function processSdcSettingsForm(array &$element, FormStateInterface $form_state, array $form): array {
    $field_name = $this->fieldDefinition->getName();
    $field_display = [];
    $form_object = $form_state->getFormObject();
    if (method_exists($form_object, 'getEntity')) {
      /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
      $display = $form_object->getEntity();
      $field_display = $display->getComponent($field_name);
    }

    $values = $form_state->getValues();
    if ($form_state instanceof SubformStateInterface) {
      $values = $form_state->getCompleteFormState()->getValues();
    }

    $parents = $element['#parents'] ?? [];
    $custom_items = $this->getCustomFieldItems();
    $trigger = $form_state->getTriggeringElement();
    $trigger_parents = $trigger['#parents'] ?? [];

    $wrapper_id = implode('_', [...$parents, 'sdc_wrapper']);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';
    $ajax_base = [
      'callback' => [static::class, 'componentCallback'],
      'wrapper' => $wrapper_id,
      'method' => 'replace',
    ];
    $valid_components = [];
    $invalid_components = [];
    $component_options = [];
    $default_slots = $this->getSetting('slots');
    $default_props = $this->getSetting('props');

    // UI patterns.
    $is_ui_pattern = in_array('ui_patterns', $parents);
    if ($is_ui_pattern) {
      $index = array_search('sources', $parents, TRUE);
      $ui_pattern_formatter = NULL;
      if ($index !== FALSE) {
        $ui_slot = array_slice($parents, 0, $index);
        $slot = end($ui_slot);
        $ui_pattern_formatter = $field_display['settings']['ui_patterns']['slots'][$slot]['sources'][0]['source']['type'] ?? NULL;
        $slot_value = NestedArray::getValue($values, [...$ui_slot, 'sources', 0, 'source']) ?? [];
        if (!empty($slot_value['type'])) {
          $ui_pattern_formatter = $slot_value['type'];
        }
      }
      if (!empty($trigger_parents) && end($trigger_parents) === 'type') {
        $ui_pattern_formatter = $trigger['#value'];
      }
      $access = !empty($ui_pattern_formatter);
      $element['#access'] = $access;
      if (!$access) {
        return $element;
      }
    }

    // Build the component options list.
    foreach ($this->componentManager->getAllComponents() as $component) {
      // Skip noUi components. (property added in Drupal 11.3).
      if (property_exists($component->metadata, 'noUi') && $component->metadata->noUi === TRUE) {
        continue;
      }

      $plugin_id = $component->getPluginId();
      $valid = $this->validateComponent($component);
      if ($valid === TRUE) {
        $valid_components[$plugin_id] = $component;
      }
      else {
        $invalid_components[$plugin_id] = [
          'title' => $this->t('@name (@id)', [
            '@name' => $component->metadata->name,
            '@id' => $component->metadata->id,
          ]),
          'reasons' => $valid,
        ];
      }
    }
    ksort($valid_components);
    foreach ($valid_components as $plugin_id => $valid_component) {
      $category = $valid_component->metadata->group;
      $component_options[(string) $category][$plugin_id] = $valid_component->metadata->name . ' (' . $valid_component->metadata->id . ')';
    }

    $component_id = NULL;
    $component_path = [...$parents, 'component'];
    if ($trigger) {
      $user_input = $form_state->getUserInput();
      $component_id = NestedArray::getValue($user_input, $component_path);
    }
    if ($component_id === NULL) {
      $component_id = NestedArray::getValue($values, $component_path);
    }
    if ($component_id === NULL) {
      $component_id = $this->getSetting('component');
    }
    if (!empty($trigger_parents) && end($trigger_parents) === 'component') {
      $component_id = $trigger['#value'] ?? '';
    }

    $active_component = NULL;
    if ($component_id !== '' && $component_id !== NULL) {
      $active_component = $valid_components[$component_id] ?? NULL;
    }

    $element['component'] = [
      '#type' => 'select',
      '#title' => $this->t('Component'),
      '#options' => $component_options,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $active_component?->getPluginId(),
      '#ajax' => $ajax_base,
      '#executes_submit_callback' => FALSE,
    ];
    if (!empty($invalid_components)) {
      $element['invalid_components'] = [
        '#type' => 'details',
        '#title' => $this->t('Invalid components'),
        '#description' => $this->t('<p>The following components are not compatible with this display formatter:</p>'),
      ];
      foreach ($invalid_components as $id => $invalid_component) {
        $element['invalid_components'][$id] = [
          '#type' => 'fieldset',
          '#title' => $invalid_component['title'],
          'reasons' => [
            '#theme' => 'item_list',
            '#items' => $invalid_component['reasons'],
          ],
        ];
      }
    }

    // Return early if no component is selected.
    if (!$active_component) {
      return $element;
    }
    $slots = $active_component->metadata->slots ?? [];
    $props = $active_component->getPluginDefinition()['props'] ?? [];

    $slot_options = \array_map(function ($custom_item) {
      return $custom_item->getLabel();
    }, $custom_items);

    if (!empty($slots)) {
      $tag_options = $this->tagManager->getTagOptions();
      $element['slots'] = [
        '#type' => 'details',
        '#title' => $this->t('Slots'),
        '#open' => TRUE,
      ];
      foreach ($slots as $name => $slot) {
        $visibility_path = $this->customFieldFormatterManager->getInputPathStates($parents, $name, FALSE, 'slots');
        $element['#visibility_path'] = $visibility_path . '[formatter_settings]';
        $element['#field_parents'] = [...$parents, 'slots', $name, 'formatter_settings'];
        $slot_wrapper_id = implode('_', [...$parents, 'slots', $name, 'wrapper']);
        $slot_path = [...$parents, 'slots', $name];

        // Defaults from stored settings.
        $slot_source = $default_slots[$name]['source'] ?? 'field';
        $slot_field = $default_slots[$name]['field'] ?? '';
        $format_type = $default_slots[$name]['format_type'] ?? '';
        $formatter_settings = $default_slots[$name]['formatter_settings'] ?? [];
        $wrapper_settings = $default_slots[$name]['wrappers'] ?? static::defaultWrappers();

        // Override with submitted values.
        if ($trigger) {
          $user_input = $form_state->getUserInput();
          $submitted_slot = NestedArray::getValue($user_input, $slot_path) ?? [];
          if (isset($submitted_slot['field'])) {
            $slot_field = $submitted_slot['field'];
            $format_type = $submitted_slot['format_type'] ?? '';
            $formatter_settings = $submitted_slot['formatter_settings'] ?? [];
            $wrapper_settings = $submitted_slot['wrappers'] ?? static::defaultWrappers();
          }
        }

        // Fallback to processed values.
        if (empty($slot_field)) {
          $processed_slot = NestedArray::getValue($values, $slot_path) ?? [];
          if (!empty($processed_slot)) {
            $slot_field = $processed_slot['field'] ?? '';
            $format_type = $processed_slot['format_type'] ?? '';
            $formatter_settings = $processed_slot['formatter_settings'] ?? [];
          }
        }

        $field_changed = FALSE;
        // Special handling for the triggering element.
        if (!empty($trigger_parents) && \in_array('slots', $trigger_parents, TRUE)) {
          $triggered_slot_name = $trigger_parents[array_search('slots', $trigger_parents, TRUE) + 1] ?? '';
          if ($triggered_slot_name === $name) {
            if (end($trigger_parents) === 'field') {
              $slot_field = $trigger['#value'] ?? '';
              $format_type = '';
              $formatter_settings = [];
              $field_changed = TRUE;
            }
            elseif (end($trigger_parents) === 'format_type') {
              $format_type = $trigger['#value'] ?? '';
              $formatter_settings = [];
            }
          }
        }

        $custom_item = $custom_items[$slot_field] ?? NULL;
        $formatter_options = $custom_item ? $this->customFieldFormatterManager->getOptions($custom_item) : [];
        if ($custom_item && empty($format_type)) {
          $format_type = $custom_item->getDefaultFormatter();
          $formatter_settings = [];
        }

        // Unset the hidden formatter type.
        if (isset($formatter_options['hidden'])) {
          unset($formatter_options['hidden']);
        }
        if (!$custom_item) {
          $format_type = '';
        }

        $element['slots'][$name] = [
          '#type' => 'details',
          '#title' => $slot['title'] ?? $name,
          '#attributes' => [
            'name' => implode('_', [...$parents, 'slots']),
          ],
          '#required' => $slot['required'] ?? FALSE,
        ];
        $element['slots'][$name]['source'] = [
          '#type' => 'value',
          '#value' => $slot_source,
        ];
        $element['slots'][$name]['field'] = [
          '#type' => 'select',
          '#title' => $this->t('Field'),
          '#description' => !empty($slot['description']) ? $this->t('@description', ['@description' => $slot['description']]) : NULL,
          '#options' => $slot_options,
          '#empty_option' => $this->t('- Select source -'),
          '#required' => $slot['required'] ?? FALSE,
          '#default_value' => $slot_field,
          '#ajax' => [
            ...$ajax_base,
            'wrapper' => $slot_wrapper_id,
          ],
          '#executes_submit_callback' => FALSE,
          '#limit_validation_errors' => [[...$parents, 'slots', $name, 'field']],
        ];
        $element['slots'][$name]['content'] = [
          '#type' => 'container',
          '#prefix' => '<div id="' . $slot_wrapper_id . '">',
          '#suffix' => '</div>',
          '#parents' => [...$parents, 'slots', $name],
        ];

        // Add the formatter settings.
        if (!empty($formatter_options)) {
          $options = $this->customFieldFormatterManager->createOptionsForInstance($custom_item, $format_type, $formatter_settings, $this->viewMode);

          $format = $this->customFieldFormatterManager->getInstance($options);
          $formatter = !is_null($format)
            ? $format->settingsForm($element, $form_state)
            : [];

          $format_wrapper_id = $slot_wrapper_id . '_format_wrapper';
          $element['slots'][$name]['content']['format_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Format type'),
            '#options' => $formatter_options,
            '#default_value' => $format_type,
            '#ajax' => [
              ...$ajax_base,
              'wrapper' => $format_wrapper_id,
            ],
            '#executes_submit_callback' => FALSE,
            '#validated' => TRUE,
          ];
          if ($field_changed || !array_key_exists($format_type, $formatter_options)) {
            $element['slots'][$name]['content']['format_type']['#value'] = $format_type;
          }
          $element['slots'][$name]['content']['formatter_settings'] = [
            '#type' => 'container',
            '#prefix' => '<div id="' . $format_wrapper_id . '">',
            '#suffix' => '</div>',
          ] + $formatter;
          $element['slots'][$name]['content']['formatter_settings']['label_display'] = [
            '#type' => 'select',
            '#title' => $this->t('Label display'),
            '#options' => $this->fieldLabelOptions(),
            '#default_value' => $formatter_settings['label_display'] ?? 'hidden',
            '#weight' => 10,
            '#access' => !($custom_item?->getPluginId() === 'boolean'),
          ];
          $element['slots'][$name]['content']['formatter_settings']['field_label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Field label'),
            '#description' => $this->t('The label for viewing this field. Leave blank to use the default field label.'),
            '#default_value' => $formatter_settings['field_label'] ?? '',
            '#weight' => 11,
            '#maxlength' => 255,
            '#access' => $format_type !== 'hidden',
            '#states' => [
              'visible' => [
                ':input[name="' . $visibility_path . '[formatter_settings][label_display]"]' => ['!value' => 'hidden'],
              ],
            ],
          ];

          // HTML wrappers.
          $this->buildHtmlWrappers($element['slots'][$name]['content'], $visibility_path, $wrapper_settings, $tag_options, $custom_item->getPluginId());
        }
      }
    }
    if (!empty($props)) {
      $properties = $props['properties'] ?? [];
      if (empty($properties)) {
        return $element;
      }
      $required = $props['required'] ?? [];
      $element['props'] = [
        '#type' => 'details',
        '#title' => $this->t('Props'),
        '#open' => TRUE,
      ];
      foreach ($properties as $property => $property_info) {
        $title = $property_info['title'] ?? ucfirst($property);
        $property_info['title'] = $title;
        $plugin = $this->propWidgetManager->getPropWidget($property_info);
        if (!$plugin) {
          continue;
        }
        $plugin_id = $plugin->getPluginId();
        $default_value = $default_props[$property] ?? [];
        // If the widget is different from the current one, reset the value.
        if (isset($default_value['widget']) && $default_value['widget'] !== $plugin_id) {
          $default_value['widget'] = $plugin_id;
          $default_value['value'] = NULL;
        }
        $is_required = \in_array($property, $required);
        $element['props'][$property] = $plugin->widget($element, $form_state, $default_value, $is_required, [
          'entity_type' => $this->fieldDefinition->getTargetEntityTypeId(),
        ]);
      }
    }

    return $element;
  }

  /**
   * Ajax callback for changing the component type.
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
  public static function componentCallback(array $form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $wrapper_id = $trigger['#ajax']['wrapper'];
    $parents = $trigger['#array_parents'];
    $sliced_parents = \array_slice($parents, 0, -1);
    $end = end($parents);

    // Create an AjaxResponse.
    $response = new AjaxResponse();
    // Get the updated element from the form structure.
    $updated_element = NestedArray::getValue($form, $sliced_parents);

    if ($end === 'field' && isset($updated_element['content'])) {
      if (empty($trigger['#value'])) {
        // Remove the format type and formatter settings when field is empty.
        if (isset($updated_element['content']['format_type'])) {
          unset($updated_element['content']['format_type']);
        }
        if (isset($updated_element['content']['formatter_settings'])) {
          unset($updated_element['content']['formatter_settings']);
        }
        if (isset($updated_element['content']['wrappers'])) {
          unset($updated_element['content']['wrappers']);
        }
      }
      $updated_element = $updated_element['content'];
    }
    if ($end === 'format_type' && isset($updated_element['formatter_settings'])) {
      $updated_element = $updated_element['formatter_settings'];
    }
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $updated_element));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $slots = $this->getSetting('slots');
    $props = $this->getSetting('props');
    $component = $this->getSetting('component');
    $active_component = NULL;
    try {
      $active_component = $this->componentManager->find($component);
    }
    catch (ComponentNotFoundException $e) {
      // Do nothing.
    }
    if (empty($component)) {
      $summary[] = $this->t('No component selected.');
    }
    elseif (!$active_component) {
      $summary[] = $this->t('An invalid component was selected: @component', ['@component' => $component]);
    }
    else {
      $component_props = $active_component->getPluginDefinition()['props'] ?? [];
      $properties = $component_props['properties'] ?? [];
      $summary[] = $this->t('<strong>Component:</strong> @component', ['@component' => $this->getSetting('component')]);
      if (!empty($slots)) {
        $summary[] = $this->t('<strong>Slots:</strong>');
        foreach ($slots as $name => $slot) {
          $summary[] = $this->t('&nbsp;&nbsp;@name: @field', [
            '@name' => $name,
            '@field' => $slot['field'] ?: '<empty>',
          ]);
        }
      }
      if (!empty($properties)) {
        $summary[] = $this->t('<strong>Props:</strong>');
        foreach ($properties as $property => $property_info) {
          $prop_value = $props[$property] ?? NULL;
          if (!$prop_value) {
            continue;
          }
          $widget = $prop_value['widget'] ?? NULL;
          $value = $prop_value['value'] ?? '';
          if (!$widget) {
            continue;
          }
          try {
            /** @var \Drupal\custom_field\Plugin\PropWidgetInterface $plugin */
            $plugin = $this->propWidgetManager->getPropWidget($property_info);
            $prop_summary = $plugin->settingsSummary($property, $value, 2);
            foreach ($prop_summary as $summary_item) {
              $summary[] = $summary_item;
            }
          }
          catch (\InvalidArgumentException $e) {
            continue;
          }
        }
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The render array of the field and subfields.
   */
  public function viewValue(FieldItemInterface $item, string $langcode): array {
    $field_name = $this->fieldDefinition->getName();
    $component = $this->getSetting('component');
    $slots = $this->getSetting('slots');
    $props = $this->getSetting('props') ? \array_filter($this->getSetting('props')) : [];

    try {
      $active_component = $this->componentManager->find($component);
    }
    catch (ComponentNotFoundException $e) {
      return [];
    }

    $component_props = $active_component->getPluginDefinition()['props'] ?? [];
    $required = $component_props['required'] ?? [];
    $is_valid = TRUE;
    $bubbleable_metadata = new BubbleableMetadata();
    $context = [
      'entity_type' => $this->fieldDefinition->getTargetEntityTypeId(),
      'entity' => $item->getEntity(),
      'bubbleable_metadata' => $bubbleable_metadata,
    ];

    // Return empty if the component is invalid and could result in a thrown
    // exception. Likely a missing dependency or malformed component.
    if (self::validateComponent($active_component) !== TRUE) {
      return [];
    }

    if (isset($component_props['properties'])) {
      $properties = $component_props['properties'];
      foreach ($props as $prop_key => $prop_value) {
        $component_prop = $properties[$prop_key] ?? NULL;
        if (!$component_prop) {
          continue;
        }

        if (!\is_array($prop_value)) {
          continue;
        }

        $widget = $this->propWidgetManager->getPropWidget($component_prop);
        if (!$widget) {
          continue;
        }

        if (!\array_key_exists('value', $prop_value)) {
          if ($widget->getPluginId() === 'boolean') {
            $prop_value['value'] = FALSE;
          }
          else {
            continue;
          }
        }

        $value = $widget->getPropValue($prop_value['value'], $context);

        if (in_array($prop_key, $required) && ($value === NULL || $value === '')) {
          $is_valid = FALSE;
          break;
        }
        if ($value === NULL || $value === '') {
          unset($props[$prop_key]);
        }
        else {
          $props[$prop_key] = $value;
        }
      }
    }

    // If a prop is required and has no value, don't render the component.
    if (!$is_valid) {
      return [];
    }

    $output = [
      '#type' => 'component',
      '#component' => $component,
      '#slots' => [],
      '#props' => $props,
    ];

    $values = $this->getFormattedValues($item, $langcode);
    foreach ($slots as $name => $slot) {
      $value = $values[$name] ?? NULL;
      if ($value !== NULL && $value !== '') {
        $output['#slots'][$name] = [
          '#theme' => 'custom_field_item',
          '#field_name' => $field_name,
          '#name' => $value['name'],
          '#value' => $value['value'],
          '#label' => $value['label'],
          '#label_display' => $value['label_display'],
          '#type' => $value['type'],
          '#wrappers' => $value['wrappers'],
          '#entity_type' => $value['entity_type'],
          '#lang_code' => $langcode,
        ];
      }
    }

    // Apply token cache metadata to the output.
    $bubbleable_metadata->applyTo($output);

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormattedValues(FieldItemInterface $item, string $langcode): array {
    $slots = $this->getSetting('slots') ?? [];
    if (empty($slots)) {
      return [];
    }
    $custom_items = $this->getCustomFieldItems();
    $subfields = [];
    foreach ($slots as $slot) {
      $field = $slot['field'] ?? NULL;
      if ($field && $custom_item = $custom_items[$field] ?? NULL) {
        $subfields[$field] = $custom_item;
      }
    }
    $event = new PreFormatEvent($subfields, $item, $langcode);
    $this->eventDispatcher->dispatch($event);
    $custom_items = $event->getCustomItems();

    $values = [];
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    foreach ($slots as $name => $slot) {
      $field = $slot['field'] ?? NULL;
      $custom_item = $custom_items[$field] ?? NULL;
      if (!$custom_item) {
        continue;
      }
      $value = static::prepareFormattedSubfieldValue($item, $custom_item, $field, $langcode);
      if ($value === '' || $value === NULL) {
        continue;
      }

      $default_wrappers = static::defaultWrappers();
      $wrappers = $slot['wrappers'] ?? $default_wrappers;
      $formatter_settings = [
        'format_type' => $slot['format_type'] ?? NULL,
        'formatter_settings' => $slot['formatter_settings'] ?? [],
        'wrappers' => \array_merge($default_wrappers, $wrappers),
      ];

      $format_type = $custom_item->getDefaultFormatter();
      // Get the available formatter options for this field type.
      $formatter_options = $this->customFieldFormatterManager->getOptions($custom_item);
      if (!empty($formatter_settings['format_type']) && isset($formatter_options[$formatter_settings['format_type']])) {
        $format_type = $formatter_settings['format_type'];
      }

      $options = $this->customFieldFormatterManager->createOptionsForInstance($custom_item, $format_type, $formatter_settings['formatter_settings'], $this->viewMode);
      /** @var \Drupal\custom_field\Plugin\CustomFieldFormatterInterface $plugin */
      $plugin = $this->customFieldFormatterManager->getInstance($options);
      $value = $plugin->formatValue($item, $value);
      if ($value === '' || $value === NULL) {
        continue;
      }

      $formatter_settings['formatter_settings'] += $plugin::defaultSettings();
      $field_label = $formatter_settings['formatter_settings']['field_label'] ?? NULL;

      // If formatValue() returned a render array, use it directly.
      // Otherwise, wrap scalar values in #markup for proper rendering.
      $render_value = is_array($value) ? $value : ['#markup' => $value];

      $markup = [
        'name' => $field,
        'value' => $render_value,
        'label' => $field_label ?: $custom_item->getLabel(),
        'label_display' => $formatter_settings['formatter_settings']['label_display'] ?? 'above',
        'type' => $custom_item->getPluginId(),
        'wrappers' => $formatter_settings['wrappers'],
        'entity_type' => $entity_type,
      ];

      $values[$name] = $markup;
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected static function defaultWrappers(): array {
    // Override the default tags to none.
    return [
      'field_wrapper_tag' => 'none',
      'field_wrapper_classes' => '',
      'field_tag' => 'none',
      'field_classes' => '',
      'label_tag' => 'none',
      'label_classes' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $component_id = $this->getSetting('component');
    $slots = $this->getSetting('slots') ?? [];
    $component = NULL;
    if (!empty($component_id)) {
      try {
        $component = $this->componentManager->find($component_id);
      }
      catch (ComponentNotFoundException $e) {
        // Component not found, do nothing.
      }
    }
    $component_props = [];
    if ($component) {
      // Add the component provider (module or theme) as a dependency.
      $provider = $component->getPluginDefinition()['provider'];
      /** @var \Drupal\Core\Theme\ExtensionType $extension */
      $extension = $component->getPluginDefinition()['extension_type'];
      $dependencies[$extension->value][] = $provider;
      $component_props = $component->getPluginDefinition()['props'] ?? [];
    }
    if (!empty($component_props['properties'])) {
      foreach ($component_props['properties'] as $property_info) {
        if ($property_info['type'] === 'array') {
          $id = $property_info['items']['id'] ?? '';
        }
        else {
          $id = $property_info['id'] ?? '';
        }
        // Add the canvas module as a dependency if the id matches the pattern.
        if ($id !== '' && str_contains($id, 'canvas.module')) {
          $dependencies['module'][] = 'canvas';
          break;
        }
      }
    }
    foreach ($slots as $slot) {
      $format_type = $slot['format_type'] ?? NULL;
      $formatter_settings = $slot['formatter_settings'] ?? [];
      if (empty($format_type) || empty($formatter_settings)) {
        continue;
      }
      try {
        /** @var \Drupal\custom_field\Plugin\CustomFieldFormatterInterface $plugin */
        $plugin = $this->customFieldFormatterManager->createInstance((string) $format_type);
        if ($plugin) {
          $plugin_dependencies = $plugin->calculateFormatterDependencies($formatter_settings);
          $dependencies = \array_merge_recursive($dependencies, $plugin_dependencies);
        }
      }
      catch (PluginException $e) {
        // Formatter not found, do nothing.
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);
    $settings_changed = FALSE;
    $slots = $this->getSetting('slots') ?? [];
    foreach ($slots as $name => $slot) {
      $format_type = $slot['format_type'] ?? NULL;
      $formatter_settings = $slot['formatter_settings'] ?? [];
      if (empty($format_type) || empty($formatter_settings)) {
        continue;
      }
      try {
        /** @var \Drupal\custom_field\Plugin\CustomFieldFormatterInterface $plugin */
        $plugin = $this->customFieldFormatterManager->createInstance((string) $format_type);
        if ($plugin) {
          $changed_settings = $plugin->onFormatterDependencyRemoval($dependencies, $formatter_settings);
          if (!empty($changed_settings)) {
            $slots[$name]['formatter_settings'] = $changed_settings;
            $settings_changed = TRUE;
          }
        }
      }
      catch (PluginException $e) {
        // Formatter not found, do nothing.
      }
    }
    if ($settings_changed) {
      $this->setSetting('slots', $slots);
    }
    $changed |= $settings_changed;

    return (bool) $changed;
  }

}
