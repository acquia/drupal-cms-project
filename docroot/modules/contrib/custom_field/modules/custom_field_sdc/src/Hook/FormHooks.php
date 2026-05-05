<?php

declare(strict_types=1);

namespace Drupal\custom_field_sdc\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\custom_field\PluginManager\PropWidgetManagerInterface;
use Drupal\custom_field\Trait\SdcTrait;
use Drupal\layout_builder\LayoutBuilderEnabledInterface;

/**
 * Provides hooks related to forms.
 */
class FormHooks {

  use StringTranslationTrait;
  use SdcTrait;

  public function __construct(
    protected ComponentPluginManager $componentManager,
    protected PropWidgetManagerInterface $propWidgetManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_entity_view_display_edit_form_alter')]
  public function formEntityViewDisplayEditFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
    $display = $form_object->getEntity();

    // Return early if the display is layout builder enabled.
    if ($display instanceof LayoutBuilderEnabledInterface && $display->isLayoutBuilderEnabled()) {
      return;
    }

    $settings = $display->getThirdPartySetting('custom_field_sdc', 'settings', []);
    $enabled = !empty($settings['enabled']);
    $component_id = $settings['component'] ?? '';
    $default_props = $settings['props'] ?? [];
    $default_slots = $settings['slots'] ?? [];
    $valid_components = [];
    $invalid_components = [];
    $component_options = [];
    $wrapper_id = 'custom-field-sdc-display-wrapper';
    $user_input = $form_state->getUserInput();
    $ajax_base = [
      'callback' => [static::class, 'sdcSettingsCallback'],
      'wrapper' => $wrapper_id,
      'method' => 'replace',
    ];

    $form['custom_field_sdc'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Custom Field - Single directory component options'),
      '#description' => $this->t('Configure the display of this view mode using Single Directory Components.'),
      '#tree' => TRUE,
    ];

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
    ksort($invalid_components);
    foreach ($valid_components as $plugin_id => $component) {
      $category = $component->metadata->group;
      $component_options[$category][$plugin_id] = $component->metadata->name . ' (' . $component->metadata->id . ')';
    }

    if (!empty($user_input)) {
      $settings = NestedArray::getValue($user_input, ['custom_field_sdc', 'settings']);
      $enabled = !empty($settings['enabled']);
      $component_id = $settings['component'] ?? '';
    }
    $active_component = $valid_components[$component_id] ?? NULL;

    // Show a warning message if sdc_display module is enabled.
    if ($this->moduleHandler->moduleExists('sdc_display')) {
      $form['custom_field_sdc']['sdc_display_enabled_warning'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', 'messages--warning'],
        ],
        'text' => [
          '#markup' => $this->t('The <em>sdc_display</em> module is controlling this display.'),
        ],
        '#states' => [
          'visible' => [
            ':input[name="sdc_display[enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    $form['custom_field_sdc']['settings'] = [
      '#type' => 'container',
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    // Add visibility states if sdc_display module is enabled.
    if ($this->moduleHandler->moduleExists('sdc_display')) {
      $form['custom_field_sdc']['settings']['#states'] = [
        'visible' => [
          ':input[name="sdc_display[enabled]"]' => ['checked' => FALSE],
        ],
      ];
    }
    $form['custom_field_sdc']['settings']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render using a component'),
      '#description' => $this->t('Check this box to render this view mode using a component.'),
      '#default_value' => !empty($enabled),
      '#ajax' => $ajax_base,
    ];
    if ($enabled) {
      $form['custom_field_sdc']['settings']['component'] = [
        '#type' => 'select',
        '#title' => $this->t('Component'),
        '#required' => FALSE,
        '#options' => $component_options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $active_component?->getPluginId() ?? '',
        '#ajax' => $ajax_base,
        '#limit_validation_errors' => [['custom_field_sdc', 'settings', 'component']],
      ];
      if (!empty($invalid_components)) {
        $form['custom_field_sdc']['settings']['invalid_components'] = [
          '#type' => 'details',
          '#title' => $this->t('Invalid components'),
          '#description' => $this->t('<p>The following components are not compatible with this display formatter:</p>'),
        ];
        foreach ($invalid_components as $component_id => $component) {
          $form['custom_field_sdc']['settings']['invalid_components'][$component_id] = [
            '#type' => 'fieldset',
            '#title' => $component['title'],
            'reasons' => [
              '#theme' => 'item_list',
              '#items' => $component['reasons'],
            ],
          ];
        }
      }
      if ($active_component) {
        $slots = $active_component->metadata->slots ?? [];
        $props = $active_component->getPluginDefinition()['props'] ?? [];
        $fields = array_keys($display->getComponents());
        if (!empty($slots)) {
          $form['custom_field_sdc']['settings']['slots'] = [
            '#type' => 'details',
            '#title' => $this->t('Slots'),
            '#open' => TRUE,
          ];
          foreach ($slots as $name => $slot) {
            $slot_source = $default_slots[$name]['source'] ?? 'field';
            $slot_field = $default_slots[$name]['field'] ?? '';
            $form['custom_field_sdc']['settings']['slots'][$name] = [
              '#type' => 'details',
              '#title' => $slot['title'] ?? $name,
              '#attributes' => [
                'name' => 'custom_field_sdc_slots',
              ],
              '#required' => !empty($slot['required']),
            ];
            $form['custom_field_sdc']['settings']['slots'][$name]['source'] = [
              '#type' => 'value',
              '#value' => $slot_source,
            ];
            $form['custom_field_sdc']['settings']['slots'][$name]['field'] = [
              '#type' => 'select',
              '#title' => $this->t('Field'),
              '#options' => array_combine($fields, $fields),
              '#empty_option' => $this->t('- Select source -'),
              '#default_value' => $slot_field,
              '#required' => !empty($slot['required']),
              '#limit_validation_errors' => [['custom_field_sdc', 'settings', 'slots', $name, 'field']],
            ];
          }
        }
        if (!empty($props)) {
          $properties = $props['properties'] ?? [];
          $required = $props['required'] ?? [];
          $form['custom_field_sdc']['settings']['props'] = [
            '#type' => 'details',
            '#title' => $this->t('Props'),
            '#open' => TRUE,
            '#access' => !empty($properties),
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
            $form['custom_field_sdc']['settings']['props'][$property] = $plugin->widget($form, $form_state, $default_value, $is_required);
          }
        }
      }
    }
    $form['#entity_builders'][] = [static::class, 'entityViewDisplayBuilder'];
  }

  /**
   * Custom #entity_builders callback to save third-party settings.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The entity view display being edited.
   * @param array<string, mixed> $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function entityViewDisplayBuilder(string $entity_type_id, EntityViewDisplayInterface $display, array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Theme\ComponentPluginManager $componentManager */
    $componentManager = \Drupal::service('plugin.manager.sdc');
    /** @var \Drupal\custom_field\PluginManager\PropWidgetManagerInterface $propWidgetManager */
    $propWidgetManager = \Drupal::service('plugin.manager.custom_field_component_prop_widget');
    $values = $form_state->getValue(['custom_field_sdc']);
    $settings = $values['settings'] ?? [];
    $component_id = $settings['component'] ?? '';

    if (!empty($settings['enabled']) && !empty($component_id)) {
      try {
        $sdc_component = $componentManager->find($component_id);
      }
      catch (ComponentNotFoundException) {
        return;
      }
      $component_props = $sdc_component->metadata->schema['properties'] ?? [];
      $props = $settings['props'] ?? [];
      foreach ($props as $prop_key => $prop_value) {
        $component_prop = $component_props[$prop_key] ?? NULL;
        if (!$component_prop) {
          unset($settings['props'][$prop_key]);
          continue;
        }
        $widget = $propWidgetManager->getPropWidget($component_prop);
        if (!$widget) {
          unset($settings['props'][$prop_key]);
          continue;
        }

        $massaged_value = $widget->massageValue($prop_value);
        $settings['props'][$prop_key] = $massaged_value;
      }
      $display->setThirdPartySetting('custom_field_sdc', 'settings', $settings);
    }
    else {
      $display->unsetThirdPartySetting('custom_field_sdc', 'settings');
    }
  }

  /**
   * Ajax callback for changing the sdc settings.
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
  public static function sdcSettingsCallback(array $form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'];
    $sliced_parents = \array_slice($parents, 0, -1);
    $wrapper_id = $trigger['#ajax']['wrapper'];
    $response = new AjaxResponse();
    $updated_element = NestedArray::getValue($form, $sliced_parents);
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $updated_element));
    return $response;
  }

}
