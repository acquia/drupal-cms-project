<?php

namespace Drupal\custom_field_ai\Plugin\FieldWidgetAction;

use Drupal\ai_automators\Plugin\FieldWidgetAction\AutomatorBaseAction;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeTypeInterface;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The field widget action for "Custom Field".
 */
#[FieldWidgetAction(
  id: 'automator_custom_field',
  label: new TranslatableMarkup('Automator Custom Field'),
  widget_types: [
    'custom_stacked',
    'custom_flex',
  ],
  field_types: ['custom'],
  multiple: FALSE,
)]
class CustomField extends AutomatorBaseAction {

  /**
   * The custom field type manager.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   */
  protected CustomFieldTypeManagerInterface $customFieldTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->customFieldTypeManager = $container->get('plugin.manager.custom_field_type');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []): void {
    parent::actionButton($form, $form_state, $context);
    $field_name = $context['items']->getFieldDefinition()->getName();
    if (!empty($context['action_id'])) {
      $widget_id = $context['action_id'];
    }
    else {
      $widget_id = $field_name . '_field_widget_action_' . $this->getPluginId();
    }
    if (!empty($context['delta'])) {
      $widget_id .= '_' . $context['delta'];
    }
    // Add submit handler to run the automator and update widget state before
    // the AJAX callback. This ensures the form is rebuilt with enough delta
    // slots for all automator results.
    $form[$widget_id]['#submit'] = [[$this, 'runAutomatorSubmit']];
    $form[$widget_id]['#executes_submit_callback'] = TRUE;
    $form[$widget_id]['#limit_validation_errors'] = [
      [$field_name],
    ];
    // Store configuration for the submit handler since it's static.
    $form[$widget_id]['#automator_config'] = $this->getConfiguration();
  }

  /**
   * Submit handler to run the automator and update widget state.
   */
  public function runAutomatorSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $field_name = $triggering_element['#field_widget_action_field_name'] ?? NULL;
    $config = $triggering_element['#automator_config'] ?? [];

    if (!$field_name) {
      return;
    }

    $field_element = $form[$field_name]['widget'] ?? NULL;
    if (!$field_element) {
      return;
    }

    $parents = $field_element['#field_parents'] ?? [];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = static::buildEntity($form, $form_state);

    // Check if automator exists.
    $automator_id = $config['settings']['automator_id'] ?? NULL;
    $base_field = NULL;
    if ($automator_id) {
      /** @var \Drupal\ai_automators\Entity\AiAutomator $automator */
      $automator = $this->entityTypeManager->getStorage('ai_automator')->load($automator_id);
      if (!$automator) {
        $this->loggerFactory->get('ai_automators')->warning('Automator @automator_id not found.', [
          '@automator_id' => $automator_id,
        ]);
        return;
      }
      $base_field = $automator->get('base_field') ?? NULL;
    }

    // The automator must have a base field.
    if (!$base_field) {
      return;
    }
    // Clear existing field values before running the automator.
    $entity->set($field_name, NULL);

    // Set the base field value from input if it's not already set.
    $input = $form_state->getUserInput();
    if ($entity->get($base_field)->isEmpty() && !empty($input[$base_field])) {
      $entity->set($base_field, $input[$base_field]);
    }

    // Run the automator.
    $entity = $this->entityModifier->saveEntity($entity, FALSE, $field_name, FALSE);
    $items = $entity->get($field_name);

    $items_count = count($items);
    if ($items_count === 0) {
      return;
    }

    // Set the user input so the form rebuild creates proper custom_field
    // sub-fields with the automator values. This ensures the cached form has
    // the correct structure, so Save works correctly.
    $input = $form_state->getUserInput();
    $input[$field_name] = [];

    foreach ($items as $index => $item) {
      $input[$field_name][$index] = \array_merge(
        $input[$field_name][$index] ?? [],
        $item->toArray()
      );
    }

    $form_state->setUserInput($input);
    // Update widget state so the form rebuilds with enough deltas.
    $field_state = WidgetBase::getWidgetState($parents, $field_name, $form_state);
    $required_count = $items_count - 1;
    if ($required_count > ($field_state['items_count'] ?? 0)) {
      $field_state['items_count'] = $required_count;
      WidgetBase::setWidgetState($parents, $field_name, $form_state, $field_state);
    }

    $form_state->setRebuild();
  }

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;
    $key = $triggering_element['#field_widget_action_field_delta'] ?? NULL;
    if (!$form_key || !isset($form[$form_key])) {
      return [];
    }

    // Clear the rebuild flag set by the runAutomatorSubmit handler so that
    // subsequent form submissions (e.g. Save) process normally.
    $form_state->setRebuild(FALSE);

    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    $items = $entity->get($form_key);
    $settings = $items->getFieldDefinition()->getSettings();
    $display = $this->entityDisplayRepository->getFormDisplay($entity->getEntityTypeId(), $entity->bundle(), 'default');
    $component = $display->getComponent($form_key);
    $fields = $component['settings']['fields'] ?? [];

    if ($items->isEmpty() || empty($fields)) {
      return $form[$form_key];
    }

    $custom_field_items = $this->customFieldTypeManager->getCustomFieldItems($settings);

    // Process either a single item or all items based on $key.
    $deltas = $key !== NULL ? [$key] : array_keys(iterator_to_array($items));

    if (empty($deltas)) {
      return $form[$form_key];
    }

    $widget = &$form[$form_key]['widget'];

    // Filter items when no specific $key is present.
    if (!$key) {
      $kept_items = [];
      foreach ($deltas as $delta) {
        if (isset($widget[$delta])) {
          $kept_items[$delta] = $widget[$delta];
        }
      }
      // Remove ALL numeric keys that are NOT in $deltas.
      $numeric_keys = array_filter(array_keys($widget), 'is_numeric');
      $keys_to_remove = array_diff($numeric_keys, array_keys($kept_items));

      $wrapper_properties = array_diff_key($widget, array_flip($keys_to_remove));

      // Rebuild the widget array.
      $widget = $kept_items + $wrapper_properties;
    }

    foreach ($deltas as $delta) {
      if (!isset($items[$delta]) || !isset($widget[$delta])) {
        continue;
      }

      $item = $items[$delta];
      $widget_item = &$widget[$delta];

      foreach ($custom_field_items as $name => $custom_field_item) {
        $widget_type = $fields[$name]['type'] ?? $custom_field_item->getDefaultWidget();
        if (!$item->get($name) || $widget_type === 'hidden') {
          continue;
        }

        $value = $item->get($name)->getValue();
        $data_type = $custom_field_item->getDataType();

        if (in_array($data_type, ['uri', 'link'])) {
          $widget_item[$name]['uri']['#value'] = $value;
          if (isset($widget_item[$name]['title'])) {
            $title_value = $item->get($name . '__title')->getValue();
            $widget_item[$name]['title']['#value'] = $title_value;
          }
        }
        elseif ($data_type === 'boolean') {
          // For boolean fields, set both value and checked state.
          $widget_item[$name]['#value'] = $value ? 1 : 0;
          $widget_item[$name]['#checked'] = (bool) $value;
          $widget_item[$name]['#default_value'] = $value ? 1 : 0;
        }
        elseif ($widget_type === 'radios') {
          $widget_item[$name]['#default_value'] = $value;
        }
        elseif ($data_type === 'datetime') {
          $date_time_type = $custom_field_item->getDateTimeType();
          $date_format = DateTimeTypeInterface::DATE_STORAGE_FORMAT;
          $time_format = 'H:i:s';
          $timezone = date_default_timezone_get();
          $show_seconds = !empty($custom_field_item->getFieldSetting('seconds_enabled'));
          if ($date_time_type === DateTimeType::DATETIME_TYPE_DATE) {
            $timezone = DateTimeTypeInterface::STORAGE_TIMEZONE;
          }
          if ($widget_type === 'datetime_local') {
            $date_format = DateTimeTypeInterface::DATETIME_STORAGE_FORMAT;
          }
          try {
            $date = DrupalDateTime::createFromFormat($date_format, $value);
            if ($date && !$date->hasErrors()) {
              $date_object = $this->createDefaultDateValue($date, $timezone, $date_time_type, $show_seconds);
              $widget_item[$name]['value']['date']['#value'] = $date_object->format($date_format);
              if (isset($widget_item[$name]['value']['time'])) {
                $widget_item[$name]['value']['time']['#value'] = $date_object->format($time_format);
              }
            }
          }
          catch (\Exception $e) {
            // Invalid date format.
          }
        }
        else {
          if ($widget_type === 'select') {
            $widget_item[$name]['#default_value'] = $value;
          }
          $widget_item[$name]['#value'] = $value;
        }
      }
    }

    return $form[$form_key];
  }

  /**
   * Creates a date object for use as a default value.
   *
   * This will take a default value, apply the proper timezone for display in
   * a widget, and set the default time for date-only fields.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The UTC default date.
   * @param string $timezone
   *   The timezone to apply.
   * @param string $datetime_type
   *   The type of date.
   * @param bool $show_seconds
   *   Whether to omit the seconds from the default value.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   A date object for use as a default value in a field widget.
   *
   * @throws \DateInvalidTimeZoneException
   */
  protected function createDefaultDateValue(DrupalDateTime $date, string $timezone, string $datetime_type, bool $show_seconds = FALSE): DrupalDateTime {
    // The date was created and verified during field_load(), so it is safe to
    // use without further inspection.
    $year = (int) $date->format('Y');
    $month = (int) $date->format('m');
    $day = (int) $date->format('d');
    $date->setTimezone(new \DateTimeZone($timezone));
    if ($datetime_type === DateTimeType::DATETIME_TYPE_DATE) {
      $date->setDefaultDateTime();
      // Reset the date to handle cases where the UTC offset is greater than
      // 12 hours.
      $date->setDate($year, $month, $day);
    }
    if ($datetime_type === DateTimeType::DATETIME_TYPE_DATETIME && !$show_seconds) {
      $date->setTime((int) $date->format('H'), (int) $date->format('i'));
    }
    return $date;
  }

}
