<?php

declare(strict_types=1);

namespace Drupal\custom_field_viewfield\Plugin\CustomField\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Attribute\CustomFieldWidget;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldWidgetBase;
use Drupal\custom_field_viewfield\Plugin\CustomField\FieldType\ViewfieldType;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'viewfield_select' widget.
 */
#[CustomFieldWidget(
  id: 'viewfield_select',
  label: new TranslatableMarkup('Viewfield select'),
  category: new TranslatableMarkup('Viewfield'),
  field_types: [
    'viewfield',
  ],
)]
class ViewfieldSelectWidget extends CustomFieldWidgetBase {

  /**
   * The token entity mapper service.
   *
   * @var \Drupal\token\TokenEntityMapperInterface|null
   */
  protected mixed $tokenEntityMapper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenEntityMapper = $container->get(
      'token.entity_mapper',
      ContainerInterface::NULL_ON_INVALID_REFERENCE
    );

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'empty_option' => '- None -',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function widgetSettingsForm(FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widgetSettingsForm($form_state, $field);
    $settings = $this->getSettings() + static::defaultSettings();

    $element['empty_option'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty option'),
      '#description' => $this->t('Option to show when field is not required.'),
      '#default_value' => $settings['empty_option'],
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function widget(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state, CustomFieldTypeInterface $field): array {
    $element = parent::widget($items, $delta, $element, $form, $form_state, $field);
    assert($field instanceof ViewfieldType);
    $settings = $this->getSettings() + static::defaultSettings();
    $field_parents = $element['#field_parents'];
    $field_settings = $field->getFieldSettings();
    $allowed_views = $this->getAllowedViewsOptions($field_settings['allowed_views']);
    $token_module_installed = $this->moduleHandler->moduleExists('token');
    /** @var \Drupal\custom_field\Plugin\Field\FieldType\CustomItem $item */
    $item = $items[$delta];
    $entity_type_id = $item->getEntity()->getEntityTypeId();
    $is_required = $item->getFieldDefinition()->isRequired() && $field_settings['required'];
    $values = $form_state->getValues();
    if ($this->isDefaultValueWidget($form_state) && !$field_settings['force_default']) {
      $is_required = FALSE;
    }
    $field_name = $item->getFieldDefinition()->getName();
    $name = $field->getName();
    if (!$this->isDefaultValueWidget($form_state) && $field_settings['force_default']) {
      $element['#access'] = FALSE;
    }
    $wrapper = $this->getUniqueElementId($form, $field_name, $delta, $name);

    // Create a condition string for states api.
    $path_parts = [...$field_parents];
    $base = array_shift($path_parts);
    $visibility_path = $base . '[' . implode('][', $path_parts) . ']';

    $field_value = NestedArray::getValue($values, $field_parents);
    // If there are no processed values, use the input.
    if (empty($field_value)) {
      $user_input = $form_state->getUserInput();
      $field_value = NestedArray::getValue($user_input, $field_parents);
    }
    if (!empty($field_value) && isset($field_value['target_id'])) {
      $target_id = $field_value['target_id'];
      $default_display_id = $field_value['display_id'];
      $default_arguments = $field_value['view_options']['arguments'];
      $default_items_to_display = $field_value['view_options']['items_to_display'];
    }
    // Use the saved values.
    else {
      $target_id = $item->{$name};
      $default_display_id = $item->{$name . '__display'} ?? NULL;
      $default_arguments = $item->{$name . '__arguments'} ?? NULL;
      $default_items_to_display = $item->{$name . '__items'} ?? NULL;
    }

    // Use the allowed displays by the current view selected.
    $display_id_options = $target_id ? $allowed_views[$target_id]['displays'] ?? [] : [];

    // Add a container div for flex layout compatibility.
    $element['#theme_wrappers'] = ['container'];
    // Add our widget type and additional properties and return.
    $element['target_id'] = [
      '#title' => $this->t('View'),
      '#type' => 'select',
      '#options' => $field->getViewOptions(TRUE),
      '#empty_value' => '',
      '#default_value' => $target_id,
      '#description' => $this->t('View name.'),
      '#required' => $is_required,
      '#ajax' => [
        'callback' => [$this, 'ajaxGetDisplayOptions'],
        'wrapper' => $wrapper,
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Getting options...'),
        ],
      ],
    ];
    if (!$is_required) {
      $element['target_id']['#empty_option'] = $settings['empty_option'];
    }

    $element['display_id'] = [
      '#title' => $this->t('Display'),
      '#type' => 'select',
      '#options' => $display_id_options,
      '#empty_option' => $settings['empty_option'],
      '#default_value' => $default_display_id,
      '#description' => $this->t('View display to be used.'),
      '#prefix' => '<div id="' . $wrapper . '">',
      '#suffix' => '</div>',
    ];

    // Hide the display id field and set its value to NULL if no view selected.
    if (count($display_id_options) < 1) {
      $element['display_id']['#type'] = 'hidden';
      $element['display_id']['#value'] = NULL;
      unset($element['display_id']['#options']);
    }

    else {
      // Set value for display_id back to NULL when options change.
      if (!empty($default_display_id) && !array_key_exists($default_display_id, $display_id_options)) {
        $element['display_id']['#value'] = NULL;
      }
      $element['display_id']['#required'] = TRUE;
    }

    $element['view_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced options'),
      '#states' => [
        'visible' => [
          [':input[name="' . $visibility_path . '[target_id]"]' => ['filled' => TRUE]],
        ],
      ],
    ];
    $element['view_options']['arguments'] = [
      '#title' => $this->t('Arguments'),
      '#type' => 'textfield',
      '#default_value' => $default_arguments ?? NULL,
      '#description' => Markup::create($this->t('Separate contextual filters with a "/". Each filter may use "+" or "," for multi-value arguments.<br> @tokens', [
        '@tokens' => $token_module_installed ? $this->t('This field supports tokens.') : '',
      ])),
      '#maxlength' => 255,
    ];
    $element['view_options']['items_to_display'] = [
      '#title' => $this->t('Items to display'),
      '#type' => 'number',
      '#default_value' => $default_items_to_display,
      '#description' => $this->t('Override the number of items to display. This also disables the pager if one is configured. Leave empty for default limit.'),
      '#min' => 1,
      '#max' => 100,
    ];
    if ($token_module_installed) {
      $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity_type_id);
      $element['view_options']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [$token_type],
        '#recursion_limit' => $field_settings['token_browser']['recursion_limit'] ?? 3,
        '#recursion_limit_max' => 6,
        '#global_types' => $field_settings['token_browser']['global_types'] ?? FALSE,
      ];
    }

    return $element;
  }

  /**
   * Get allowed views for widget options.
   *
   * @param array<string, mixed> $allowed_views
   *   An array of views to filter by.
   *
   * @return array<string, mixed>
   *   A filtered array of views based on enabled displays.
   */
  public function getAllowedViewsOptions(array $allowed_views): array {
    $views = Views::getEnabledViews();
    $allowed_options = [];

    foreach ($allowed_views as $view_name => $displays) {
      // Check if the view and our filters exist in $views before adding.
      if (isset($views[$view_name])) {
        $filtered_displays = array_filter($displays);
        $display_options = [];
        foreach ($filtered_displays as $key => $display) {
          $views_display = $views[$view_name]->getDisplay($key);
          if (isset($views_display['display_options']['enabled']) && !$views_display['display_options']['enabled']) {
            continue;
          }
          $display_options[$key] = FieldFilteredMarkup::create($views_display['display_title']);
        }
        if (!empty($display_options)) {
          natcasesort($display_options);
          $allowed_options[$view_name] = [
            'label' => $views[$view_name]->label(),
            'displays' => $display_options,
          ];
        }
      }
    }

    return $allowed_options;
  }

  /**
   * Ajax callback to retrieve display IDs.
   *
   * @param array $form
   *   The form from which the display IDs are being requested.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function ajaxGetDisplayOptions(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $wrapper_id = $trigger['#ajax']['wrapper'];
    $form_state_keys = array_slice($trigger['#array_parents'], 0, -1);

    // Get the updated element from the form structure.
    $updated_element = NestedArray::getValue($form, $form_state_keys)['display_id'];
    $sliced_parents = array_slice($trigger['#parents'], 0, -1, TRUE);
    $user_input = $form_state->getUserInput();

    NestedArray::setValue($user_input, [...$sliced_parents, 'display_id'], NULL);
    $form_state->setValueForElement($updated_element, NULL);
    $form_state->setUserInput($user_input);

    $response = new AjaxResponse();
    // Add a ReplaceCommand to replace the content inside the widget's wrapper.
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $updated_element));
    $form_state->setRebuild();

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValue(mixed $value, array $column): mixed {
    if (isset($value['target_id']) && empty($value['target_id'])) {
      return NULL;
    }
    if (isset($value['view_options'])) {
      $value = array_merge($value, $value['view_options']);
      unset($value['view_options']);
    }
    return $value;
  }

}
