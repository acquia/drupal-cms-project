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
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }
    $field_name = $this->fieldDefinition->getName();
    $value_keys = $this->customFieldFormatterManager->getFormatterValueKeys($form_state, $field_name);
    $user_input = $form_state->getUserInput();
    $trigger = $form_state->getTriggeringElement();
    $wrapper_id = $field_name . 'sdc-wrapper';
    $valid_components = [];
    $invalid_components = [];
    $component_options = [];

    // Build the component options list.
    foreach ($this->componentManager->getAllComponents() as $component) {
      // Skip noUi components (property added in Drupal 11.3).
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
    foreach ($valid_components as $plugin_id => $component) {
      $category = $component->metadata->group;
      $component_options[(string) $category][$plugin_id] = $component->metadata->name . ' (' . $component->metadata->id . ')';
    }
    $component_id = $this->getSetting('component');
    $default_slots = $this->getSetting('slots');
    $default_props = $this->getSetting('props');
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';
    $ajax_base = [
      'callback' => [static::class, 'componentCallback'],
      'wrapper' => $wrapper_id,
      'method' => 'replace',
    ];

    if (!empty($user_input)) {
      $component_value = NestedArray::getValue($user_input, [...$value_keys, 'component']);
      if ($component_value !== NULL) {
        $component_id = $component_value;
      }
    }
    $active_component = $valid_components[$component_id] ?? NULL;

    $form['component'] = [
      '#type' => 'select',
      '#title' => $this->t('Component'),
      '#options' => $component_options,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $active_component?->getPluginId() ?? '',
      '#ajax' => $ajax_base,
      '#executes_submit_callback' => FALSE,
    ];
    if (!empty($invalid_components)) {
      $form['invalid_components'] = [
        '#type' => 'details',
        '#title' => $this->t('Invalid components'),
        '#description' => $this->t('<p>The following components are not compatible with this display formatter:</p>'),
      ];
      foreach ($invalid_components as $component_id => $component) {
        $form['invalid_components'][$component_id] = [
          '#type' => 'fieldset',
          '#title' => $component['title'],
          'reasons' => [
            '#theme' => 'item_list',
            '#items' => $component['reasons'],
          ],
        ];
      }
    }

    // Return early if no component is selected.
    if (!$active_component) {
      return $form;
    }

    $custom_items = $this->getCustomFieldItems();
    $slot_options = \array_map(function ($custom_item) {
      return $custom_item->getLabel();
    }, $custom_items);

    $slots = $active_component->metadata->slots ?? [];
    $props = $active_component->getPluginDefinition()['props'] ?? [];

    if (!empty($slots)) {
      $form['slots'] = [
        '#type' => 'details',
        '#title' => $this->t('Slots'),
        '#open' => TRUE,
      ];
      foreach ($slots as $name => $slot) {
        $visibility_path = $this->customFieldFormatterManager->getInputPathForStatesApi($form_state, $field_name, $name, FALSE, 'slots');
        $root_visibility_path = $visibility_path;
        // Strip the last [formatter_settings] to get root path.
        if (str_ends_with($visibility_path, '[formatter_settings]')) {
          $root_visibility_path = substr($visibility_path, 0, -strlen('[formatter_settings]'));
        }

        $form['#visibility_path'] = $visibility_path;
        $slot_wrapper = $field_name . '-slot-' . $name;
        $parents = [...$value_keys, 'slots', $name];
        $form['#field_parents'] = [...$parents, 'formatter_settings'];
        $slot_source = $default_slots[$name]['source'] ?? 'field';
        $slot_field = $default_slots[$name]['field'] ?? '';
        $formatter_settings = $default_slots[$name]['formatter_settings'] ?? [];
        $wrapper_settings = $default_slots[$name]['wrappers'] ?? static::defaultWrappers();
        $custom_item = $custom_items[$slot_field] ?? NULL;
        $default_format_type = $default_slots[$name]['format_type'] ?? '';
        $format_type = $default_format_type ?: $custom_item?->getDefaultFormatter() ?? '';
        $formatter_options = $custom_item ? $this->customFieldFormatterManager->getOptions($custom_item) : [];
        $formatter = [];

        if (!empty($user_input)) {
          $slot_value = NestedArray::getValue($user_input, $parents);
          if ($slot_value !== NULL) {
            $slot_field = $slot_value['field'] ?? '';
            $custom_item = $custom_items[$slot_field] ?? NULL;
            $formatter_options = $custom_item ? $this->customFieldFormatterManager->getOptions($custom_item) : [];
            if ($trigger && isset($trigger['#parents'])) {
              $end = end($trigger['#parents']);
              if ($end === 'field') {
                $format_type = $custom_item?->getDefaultFormatter() ?? '';
                $formatter_settings = [];
              }
              elseif ($end === 'format_type') {
                $format_type = $slot_value['format_type'] ?? '';
                $formatter_settings = [];
              }
            }
          }
        }

        if (isset($formatter_options['hidden'])) {
          unset($formatter_options['hidden']);
        }

        $ajax_slot = $ajax_base;
        $ajax_slot['wrapper'] = $slot_wrapper;
        $form['slots'][$name] = [
          '#type' => 'details',
          '#title' => $slot['title'] ?? $name,
          '#attributes' => [
            'name' => $field_name,
          ],
          '#required' => $slot['required'] ?? FALSE,
        ];
        $form['slots'][$name]['source'] = [
          '#type' => 'value',
          '#value' => $slot_source,
        ];
        $form['slots'][$name]['field'] = [
          '#type' => 'select',
          '#title' => $this->t('Field'),
          '#description' => !empty($slot['description']) ? $this->t('@description', ['@description' => $slot['description']]) : NULL,
          '#options' => $slot_options,
          '#empty_option' => t('- Select source -'),
          '#required' => $slot['required'] ?? FALSE,
          '#default_value' => $slot_field,
          '#ajax' => $ajax_slot,
          '#executes_submit_callback' => FALSE,
          '#limit_validation_errors' => [[...$parents, 'field']],
        ];
        $form['slots'][$name]['content'] = [
          '#type' => 'container',
          '#prefix' => '<div id="' . $slot_wrapper . '">',
          '#suffix' => '</div>',
          '#parents' => $parents,
        ];

        if (!empty($formatter_options)) {
          $options = $this->customFieldFormatterManager->createOptionsForInstance($custom_item, $format_type, $formatter_settings, $this->viewMode);
          // Get the formatter settings form.
          $format = $this->customFieldFormatterManager->getInstance($options);
          if (!is_null($format)) {
            $formatter = $format->settingsForm($form, $form_state);
          }
          $ajax_format = $ajax_base;
          $format_wrapper = $slot_wrapper . '-format';
          $ajax_format['wrapper'] = $format_wrapper;
          $form['slots'][$name]['content']['format_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Format type'),
            '#options' => $formatter_options,
            '#default_value' => $format_type,
            '#ajax' => $ajax_format,
            '#executes_submit_callback' => FALSE,
            '#limit_validation_errors' => [[...$parents, 'format_type']],
          ];
          if (!array_key_exists($format_type, $formatter_options)) {
            $form['slots'][$name]['content']['format_type']['#value'] = $format_type;
          }
          $form['slots'][$name]['content']['formatter_settings'] = [
            '#type' => 'container',
            '#prefix' => '<div id="' . $format_wrapper . '">',
            '#suffix' => '</div>',
          ] + $formatter;

          $form['slots'][$name]['content']['formatter_settings']['label_display'] = [
            '#type' => 'select',
            '#title' => $this->t('Label display'),
            '#options' => $this->fieldLabelOptions(),
            '#default_value' => $formatter_settings['label_display'] ?? 'hidden',
            '#weight' => 10,
            '#access' => !($custom_item?->getPluginId() === 'boolean'),
          ];
          $form['slots'][$name]['content']['formatter_settings']['field_label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Field label'),
            '#description' => $this->t('The label for viewing this field. Leave blank to use the default field label.'),
            '#default_value' => $formatter_settings['field_label'] ?? '',
            '#weight' => 11,
            '#maxlength' => 255,
            '#access' => $format_type !== 'hidden',
            '#states' => [
              'visible' => [
                ':input[name="' . $visibility_path . '[label_display]"]' => ['!value' => 'hidden'],
              ],
            ],
          ];
          // HTML wrapper settings.
          $tag_options = $this->tagManager->getTagOptions();
          $form['slots'][$name]['content']['wrappers'] = [
            '#type' => 'details',
            '#title' => $this->t('Style settings'),
            '#states' => [
              'visible' => [
                ':input[name="' . $root_visibility_path . '[format_type]"]' => ['!value' => 'hidden'],
              ],
            ],
          ];
          $form['slots'][$name]['content']['wrappers']['field_wrapper_tag'] = [
            '#type' => 'select',
            '#title' => $this->t('Field wrapper tag'),
            '#description' => $this->t('Choose the HTML element to wrap around this field and label.'),
            '#options' => $tag_options,
            '#empty_option' => $this->t('- Use default -'),
            '#default_value' => $wrapper_settings['field_wrapper_tag'],
          ];
          $form['slots'][$name]['content']['wrappers']['field_wrapper_classes'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Field wrapper classes'),
            '#description' => $this->t('Enter additional classes, separated by space.'),
            '#default_value' => $wrapper_settings['field_wrapper_classes'],
            '#states' => [
              'invisible' => [
                ':input[name="' . $root_visibility_path . '[wrappers][field_wrapper_tag]"]' => ['value' => 'none'],
              ],
            ],
          ];
          $form['slots'][$name]['content']['wrappers']['field_tag'] = [
            '#type' => 'select',
            '#title' => $this->t('Field tag'),
            '#description' => $this->t('Choose the HTML element to wrap around this field.'),
            '#options' => $tag_options,
            '#empty_option' => $this->t('- Use default -'),
            '#default_value' => $wrapper_settings['field_tag'],
          ];
          $form['slots'][$name]['content']['wrappers']['field_classes'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Field classes'),
            '#description' => $this->t('Enter additional classes, separated by space.'),
            '#default_value' => $wrapper_settings['field_classes'],
            '#states' => [
              'invisible' => [
                ':input[name="' . $root_visibility_path . '[wrappers][field_tag]"]' => ['value' => 'none'],
              ],
            ],
          ];
          $form['slots'][$name]['content']['wrappers']['label_tag'] = [
            '#type' => 'select',
            '#title' => $this->t('Label tag'),
            '#description' => $this->t('Choose the HTML element to wrap around this label.'),
            '#options' => $tag_options,
            '#empty_option' => $this->t('- Use default -'),
            '#default_value' => $wrapper_settings['label_tag'],
            '#access' => !($custom_item?->getPluginId() === 'boolean'),
            '#states' => [
              'visible' => [
                ':input[name="' . $visibility_path . '[label_display]"]' => ['!value' => 'hidden'],
              ],
            ],
          ];
          $form['slots'][$name]['content']['wrappers']['label_classes'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Label classes'),
            '#description' => $this->t('Enter additional classes, separated by space.'),
            '#default_value' => $wrapper_settings['label_classes'],
            '#access' => !($custom_item?->getPluginId() === 'boolean'),
            '#states' => [
              'visible' => [
                ':input[name="' . $visibility_path . '[label_display]"]' => ['!value' => 'hidden'],
                ':input[name="' . $root_visibility_path . '[wrappers][label_tag]"]' => ['!value' => 'none'],
              ],
            ],
          ];
        }
      }
    }

    if (!empty($props)) {
      $properties = $props['properties'] ?? [];
      if (empty($properties)) {
        return $form;
      }
      $required = $props['required'] ?? [];
      $form['props'] = [
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
        $form['props'][$property] = $plugin->widget($form, $form_state, $default_value, $is_required);
      }
    }

    return $form;
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
            $prop_summary = $plugin->settingsSummary($property, $value);
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
        $value = static::formatPropValue($component_prop, $prop_value);
        if ($value !== NULL) {
          $props[$prop_key] = $value;
        }
        else {
          unset($props[$prop_key]);
        }
      }
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
