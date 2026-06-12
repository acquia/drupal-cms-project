<?php

declare(strict_types=1);

namespace Drupal\custom_field_viewfield\Plugin\CustomField\FieldType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\custom_field\Attribute\CustomFieldType;
use Drupal\custom_field\Plugin\CustomField\FieldType\EntityReference;
use Drupal\custom_field\Plugin\CustomFieldTypeInterface;
use Drupal\custom_field\TypedData\CustomFieldDataDefinition;
use Drupal\views\Views;

/**
 * Plugin implementation of the 'viewfield' field type.
 */
#[CustomFieldType(
  id: 'viewfield',
  label: new TranslatableMarkup('Viewfield'),
  description: new TranslatableMarkup('Defines a entity reference field type to display a view.'),
  category: new TranslatableMarkup('Reference'),
  default_widget: 'viewfield_select',
  default_formatter: 'viewfield_default',
)]
class ViewfieldType extends EntityReference {

  /**
   * {@inheritdoc}
   */
  public static function schema(array $settings): array {
    $columns = parent::schema($settings);
    ['name' => $name] = $settings;

    $display_id = $name . self::SEPARATOR . 'display';
    $arguments = $name . self::SEPARATOR . 'arguments';
    $items_to_display = $name . self::SEPARATOR . 'items';

    $columns[$name]['description'] = 'The ID of the view';
    $columns[$display_id] = [
      'description' => 'The ID of the view display.',
      'type' => 'varchar_ascii',
      'length' => 255,
    ];
    $columns[$arguments] = [
      'description' => 'Arguments to be passed to the display.',
      'type' => 'varchar',
      'length' => 255,
    ];
    $columns[$items_to_display] = [
      'description' => 'Items to display.',
      'type' => 'int',
      'size' => 'small',
      'unsigned' => TRUE,
    ];

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(array $settings): array {
    $properties = parent::propertyDefinitions($settings);
    ['name' => $name] = $settings;

    $display_id = $name . self::SEPARATOR . 'display';
    $arguments = $name . self::SEPARATOR . 'arguments';
    $items_to_display = $name . self::SEPARATOR . 'items';

    $properties[$name] = CustomFieldDataDefinition::create('custom_field_viewfield')
      ->setLabel(new TranslatableMarkup('@label ID', ['@label' => $name]))
      ->setSetting('target_type', 'view')
      ->setRequired(FALSE);

    $properties[$display_id] = DataDefinition::create('string')
      ->setLabel(t('Display ID'))
      ->setDescription(t('The view display ID'));

    $properties[$arguments] = DataDefinition::create('string')
      ->setLabel(t('Arguments'))
      ->setDescription(t('An optional comma-delimited list of arguments for the display'));

    $properties[$items_to_display] = DataDefinition::create('integer')
      ->setLabel(t('Items to display'))
      ->setDescription(t('Override the number of displayed items.'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'force_default' => 0,
      'allowed_views' => [],
      'items_to_display' => NULL,
      'token_browser' => [
        'recursion_limit' => 3,
        'global_types' => FALSE,
      ],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array &$form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    unset($element['handler']);
    $settings = $this->getFieldSettings();
    $range = range(1, 6);
    $views = [];
    $view_options = $this->getViewOptions(FALSE);
    foreach ($view_options as $id => $view) {
      $displays = $this->getDisplayOptions($id);
      if (!empty($displays)) {
        $views[$id] = [
          'label' => $view,
          'displays' => $displays,
        ];
      }
    }
    $element['allowed_views'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Allowed views'),
      '#description' => $this->t('Views displays available for content authors. Leave empty to allow all.'),
      '#description_display' => 'before',
      '#element_validate' => [[static::class, 'validateAllowedViews']],
    ];
    foreach ($views as $view_name => $view) {
      $element['allowed_views'][$view_name] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('@label', ['@label' => $view['label']]),
        '#options' => $view['displays'],
        '#default_value' => $settings['allowed_views'][$view_name] ?? [],
      ];
    }
    $element['force_default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always use default value'),
      '#description' => $this->t('The allowed views will not immediately be available in the default value form until they are saved into configuration. It is recommended to save the field settings prior to setting the default values for this particular setting.'),
      '#default_value' => $settings['force_default'],
    ];
    $element['token_browser'] = [
      '#type' => 'details',
      '#title' => $this->t('Token browser'),
      '#description' => $this->t('Settings to handle available tokens for the arguments field when token module is enabled.'),
      '#description_display' => 'before',
    ];
    $element['token_browser']['recursion_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Recursion limit'),
      '#description' => $this->t('The depth of the token browser tree.'),
      '#options' => array_combine($range, $range),
      '#default_value' => $settings['token_browser']['recursion_limit'] ?? 3,
    ];
    $element['token_browser']['global_types'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Global types'),
      '#description' => $this->t("Enable 'global' context tokens like [current-user:*] or [site:*]."),
      '#default_value' => $settings['token_browser']['global_types'] ?? FALSE,
    ];

    $element['#element_validate'][] = [static::class, 'fieldSettingsFormValidate'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(CustomFieldTypeInterface $item, array $default_value): array {
    $dependencies = [];
    $entity_type_manager = \Drupal::entityTypeManager();
    $allowed_views = $item->getFieldSetting('allowed_views') ?? [];
    foreach ($allowed_views as $view_name => $displays) {
      /** @var \Drupal\views\Entity\View $view */
      if ($view = $entity_type_manager->getStorage('view')->load($view_name)) {
        $filtered_displays = array_filter($displays);
        if (!empty($filtered_displays)) {
          $dependency_key = $view->getConfigDependencyKey();
          $dependencies[$dependency_key][] = $view->getConfigDependencyName();
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
    $views_changed = FALSE;
    $field_settings = $item->getFieldSettings();
    $allowed_views = $field_settings['allowed_views'] ?? [];
    $changed_settings = [];
    foreach ($allowed_views as $view_name => $displays) {
      /** @var \Drupal\views\Entity\View $view */
      if ($view = $entity_type_manager->getStorage('view')->load($view_name)) {
        $dependency_key = $view->getConfigDependencyKey();
        $dependency_name = $view->getConfigDependencyName();
        if (isset($dependencies[$dependency_key][$dependency_name])) {
          unset($allowed_views[$view_name]);
          $views_changed = TRUE;
        }
      }
    }
    if ($views_changed) {
      $field_settings['allowed_views'] = $allowed_views;
      $changed_settings = $field_settings;
    }

    return $changed_settings;
  }

  /**
   * Get an options array of views.
   *
   * @param bool $filter
   *   Flag to filter the output using the 'allowed_views' setting.
   *
   * @return array
   *   The array of options.
   */
  public function getViewOptions(bool $filter): array {
    $views_options = [];
    $allowed_views = [];
    if ($filter) {
      $allowed_views_setting = $this->getFieldSetting('allowed_views') ?? [];
      // Add only the views where displays are allowed.
      foreach ($allowed_views_setting as $id => $displays) {
        if (!empty(array_filter($displays))) {
          $allowed_views[$id] = $displays;
        }
      }
    }

    foreach (Views::getEnabledViews() as $key => $view) {
      if (empty($allowed_views) || isset($allowed_views[$key])) {
        $views_options[$key] = FieldFilteredMarkup::create($view->get('label'));
      }
    }
    natcasesort($views_options);

    return $views_options;
  }

  /**
   * Get display ID options for a view.
   *
   * @param string $entity_id
   *   The entity_id of the view.
   * @param bool $filter
   *   (optional) Flag to filter the output using the 'allowed_display_types'
   *   setting.
   *
   * @return array<\Drupal\Component\Render\MarkupInterface|string>
   *   The array of options.
   */
  public function getDisplayOptions(string $entity_id, bool $filter = TRUE): array {
    $display_options = [];
    $views = Views::getEnabledViews();
    if (isset($views[$entity_id])) {
      foreach ($views[$entity_id]->get('display') as $key => $display) {
        if (isset($display['display_options']['enabled']) && !$display['display_options']['enabled']) {
          continue;
        }
        $display_options[$key] = FieldFilteredMarkup::create($display['display_title']);
      }
      natcasesort($display_options);
    }

    return $display_options;
  }

  /**
   * Validates the allowed views fieldset to enforce at least one view enabled.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateAllowedViews(array &$element, FormStateInterface $form_state): void {
    $any_enabled = FALSE;
    $views = $form_state->getValue($element['#parents']);
    // Iterate for each view's displays to check for enabled.
    foreach ($views as $displays) {
      if (!empty(array_filter($displays))) {
        $any_enabled = TRUE;
        break;
      }
    }
    // No displays for any view are enabled, so set an error.
    if (!$any_enabled) {
      $form_state->setError($element, t('At least one view display must be enabled.'));
    }
  }

  /**
   * Form API callback.
   *
   * Requires that field defaults be supplied when the 'force_default' option
   * is checked.
   *
   * This function is assigned as an #element_validate callback in
   * fieldSettingsForm().
   */
  public static function fieldSettingsFormValidate(array &$element, FormStateInterface $form_state, array &$form): void {
    $parents = $element['#array_parents'];
    $settings = $form_state->getValue($parents);

    if ($settings['force_default']) {
      $default_value = $form_state->getValue('default_value_input');
      /** @var \Drupal\field_ui\Form\FieldConfigEditForm $form_object */
      $form_object = $form_state->getFormObject();
      /** @var \Drupal\Core\Field\FieldConfigInterface $field_definition */
      $field_definition = $form_object->getEntity();
      $field_name = $field_definition->getName();
      $subfield_name = (string) end($parents);
      if (empty($default_value[$field_name][0][$subfield_name]['display_id'])) {
        $form_element = NestedArray::getValue($form, $parents);
        // Set an error on the default value checkbox.
        $form_state->setErrorByName('set_default_value', t('%title requires a default value.', [
          '%title' => $form_element['force_default']['#title'],
        ]));
        // Set an error on the target id field.
        $target_form_keys = [
          'default_value',
          'widget',
          0,
          $subfield_name,
          'target_id',
        ];
        $complete_form = $form_state->getCompleteForm();
        $target_id_element = NestedArray::getValue($complete_form, $target_form_keys);
        if ($target_id_element) {
          $form_state->setError($target_id_element, t('The field %view requires a default view.', [
            '%view' => $target_id_element['#title'],
          ]));
        }
      }
    }
  }

}
