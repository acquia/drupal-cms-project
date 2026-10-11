<?php

namespace Drupal\smart_date_recur\Hook;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\smart_date_recur\Entity\SmartDateRule;
use Drupal\smart_date_recur\SmartDateRecurManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for smart_date_recur.
 */
class SmartDateRecurHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly SmartDateRecurManager $smartDateRecurManager,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.smart_date_recur':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Smart Date Recur module adds recurring date functionality to Smart Date fields. For more information, see the <a href=":datetime_do">online documentation for the Smart Date module</a>.', [
          ':datetime_do' => 'https://www.drupal.org/docs/contributed-modules/smart-date',
        ]) . '</p>';
        return $output;
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme($existing, $type, $theme, $path) {
    return [
      'smart_date_recurring_formatter' => [
        'variables' => [
          'rule_text' => NULL,
          'past_display' => NULL,
          'next_display' => NULL,
          'upcoming_display' => NULL,
        ],
      ],
      'smart_date_recurring_text_rule' => [
        'variables' => [
          'rule' => NULL,
          'repeat' => NULL,
          'repeat_separator' => NULL,
          'day' => NULL,
          'day_separator' => NULL,
          'days_array' => [],
          'month' => NULL,
          'month_separator' => NULL,
          'time' => NULL,
          'time_separator' => NULL,
          'limit' => NULL,
          'limit_separator' => NULL,
        ],
      ],
      'smart_date_recurring_comma_list' => [
        'variables' => [
          'values' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public static function entityDelete($entity) {
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    // Check for rules that apply to this entity type and bundle.
    $query = \Drupal::entityTypeManager()->getStorage('smart_date_rule');
    $query_result = $query->getQuery()->condition('entity_type', $entity_type)->condition('bundle', $bundle)->accessCheck(TRUE)->execute();
    // If none exist, nothing to do.
    if (!$query_result) {
      return;
    }
    $field_names = [];
    // Get all the relevant fields for this bundle.
    foreach ($query_result as $rule) {
      $rrule = SmartDateRule::load($rule);
      if ($rrule) {
        $field_name = $rrule->field_name->value;
        if (!in_array($field_name, $field_names)) {
          $field_names[] = $field_name;
        }
      }
    }
    // If no relevant fields for this bundle, nothing to do.
    if (!$field_names) {
      return;
    }
    // Check for relevant field values on the deleted entity.
    foreach ($field_names as $field_name) {
      if (!$values = $entity->{$field_name}) {
        continue;
      }
      $rules = [];
      // Collect all distinct rules defined for the deleted entity.
      foreach ($values as $value) {
        if ($value->rrule && !in_array($value->rrule, $rules)) {
          $rules[] = $value->rrule;
        }
      }
      // If no rules defined for this field, nothing to do.
      if (!$rules) {
        continue;
      }
      // Delete any rules found.
      foreach ($rules as $rule) {
        if ($rrule = SmartDateRule::load($rule)) {
          $rrule->delete();
        }
      }
    }
  }

  /**
   * Implements hook_cron().
   *
   * Queues rules without defined limits to have more instances generated.
   */
  #[Hook('cron')]
  public static function cron() {
    // Check a time variable to control how often this runs.
    $time_check = \Drupal::state()->get('smart_date_recur_check');
    if ($time_check && $time_check > time()) {
      // Wait until a week has lapsed since the last check.
      return;
    }
    /** @var QueueFactory $queue_factory */
    $queue_factory = \Drupal::service('queue');
    /** @var QueueInterface $queue */
    $queue = $queue_factory->get('smart_date_recur_rules');
    $queue->createQueue();
    $to_process = [];
    // Get the data we need, and group it by impacted entity.
    $ids = \Drupal::entityTypeManager()->getStorage('smart_date_rule')->getRuleIdsToCheck();
    foreach (SmartDateRule::loadMultiple($ids) as $rule) {
      $entity_type = $rule?->entity_type?->getString();
      if (!$entity_type) {
        // Invalid rule, might be an orphaned rule.
        // @todo Delete invalid rule?
        // @todo Log a warning.
        continue;
      }
      // Validate rule before calling getParentEntity
      // Any orphaned rules (invalid entity type, bundle or field name) will
      // prevent new instances from being generated.
      $entity_id = $rule->validateRule() ? $rule->getParentEntity(TRUE) : NULL;
      if (!$entity_id) {
        // Invalid entity, might be an orphaned rule.
        // @todo Log a warning.
        continue;
      }
      $field_name = $rule->field_name->getString();
      $rrid = $rule->id();
      // Group the collected data by impacted entity.
      $to_process[$entity_type][$entity_id][$field_name][$rrid] = $rrid;
    }
    foreach ($to_process as $entity_type => $type_items) {
      foreach ($type_items as $entity_id => $data) {
        // Create new queue item.
        $item = new \stdClass();
        $item->entity_type = $entity_type;
        $item->entity_id = $entity_id;
        $item->data = $data;
        // Add our item to the queue.
        $queue->createItem($item);
      }
    }
    // Set the time to next extend instances.
    $wait = "+7 days";
    \Drupal::state()->set('smart_date_recur_check', strtotime($wait));
  }

  /**
   * Implements hook_field_widget_third_party_settings_form().
   *
   * Adds extra configuration fields to Smart Date fields configured to recur.
   */
  #[Hook('field_widget_third_party_settings_form')]
  public function fieldWidgetThirdPartySettingsForm($plugin, $field_definition, $form_mode, $form, $form_state) {
    if ($field_definition->getType() === 'smartdate') {
      if ($field_definition instanceof BaseFieldDefinition) {
        $allow_recurring = $field_definition->getSetting('allow_recurring');
      }
      else {
        $allow_recurring = $field_definition->getThirdPartySetting('smart_date_recur', 'allow_recurring');
      }
      if ($allow_recurring) {
        $recur_manager = $this->smartDateRecurManager;
        $element['modal'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Use modal for managing instances'),
          '#default_value' => $recur_manager->getThirdPartyFallback($plugin, 'modal', 1),
        ];
        $element['show_single'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Show a value of 1 in the increment field'),
          '#default_value' => $recur_manager->getThirdPartyFallback($plugin, 'show_single', 0),
        ];
        $element['allowed_recur_freq_values'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Allowed frequency values for recurring events'),
          '#default_value' => $recur_manager->getThirdPartyFallback($plugin, 'allowed_recur_freq_values', _smart_date_recur_get_freq_defaults()),
          '#options' => [
            'MINUTELY' => $this->t('by Minutes'),
            'HOURLY' => $this->t('Hourly'),
            'DAILY' => $this->t('Daily'),
            'WEEKLY' => $this->t('Weekly'),
            'MONTHLY' => $this->t('Monthly'),
            'YEARLY' => $this->t('Annually'),
          ],
        ];
        return $element;
      }
    }
  }

  /**
   * Implements hook_field_widget_settings_summary_alter().
   */
  #[Hook('field_widget_settings_summary_alter')]
  public function fieldWidgetSettingsSummaryAlter(&$summary, $context) {
    $recur_manager = $this->smartDateRecurManager;
    // Append messages to the summary.
    if ($context['field_definition']->getType() == 'smartdate' && $recur_manager->getThirdPartyFallback($context['field_definition'], 'allow_recurring')) {
      if ($recur_manager->getThirdPartyFallback($context['widget'], 'modal', 1)) {
        $summary[] = $this->t('Use modal for managing instances.');
      }
      if ($recur_manager->getThirdPartyFallback($context['widget'], 'show_single', 1)) {
        $summary[] = $this->t('Show a value of 1 in the increment field.');
      }
      if ($freq_values = $recur_manager->getThirdPartyFallback($context['widget'], 'allowed_recur_freq_values', _smart_date_recur_get_freq_defaults())) {
        $labels = $this->labelFreqDefaults($freq_values);
        $summary[] = $this->t('Dates can recur:') . ' ' . implode(', ', $labels);
      }
    }
  }

  /**
   * Helper function to centralize default frequency values.
   */
  protected function labelFreqDefaults($freq_values) {
    $labels = [];
    $freq_labels = $this->smartDateRecurManager->getFrequencyLabels();
    foreach ($freq_values as $key => $value) {
      if ($value && isset($freq_labels[$value])) {
        $labels[$key] = $freq_labels[$value];
      }
    }
    return $labels;
  }

}
