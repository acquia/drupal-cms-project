<?php

namespace Drupal\smart_date\Hook;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for smart_date.
 */
class SmartDateHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.smart_date':
        if ($this->moduleHandler->moduleExists('field_ui')) {
          $field_ui_link = Url::fromRoute('help.page', [
            'name' => 'field_ui',
          ])->toString();
        }
        else {
          $field_ui_link = '#';
        }
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Smart Date module provides a Date field that stores start dates and times, as well as end dates and times. See the <a href=":field">Field module help</a> and the <a href=":field_ui">Field UI module help</a> pages for general information on fields and how to create and manage them. For more information, see the <a href=":datetime_do">online documentation for the Smart Date module</a>.', [
          ':field' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
          ':field_ui' => $field_ui_link,
          ':datetime_do' => 'https://www.drupal.org/docs/contributed-modules/smart-date',
        ]) . '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Managing and displaying date fields') . '</dt>';
        $output .= '<dd>' . $this->t('The <em>settings</em> and the <em>display</em> of the Smart Date field can be configured separately. See the <a href=":field_ui">Field UI help</a> for more information on how to manage fields and their display.', [
          ':field_ui' => $field_ui_link,
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Displaying dates') . '</dt>';
        $output .= '<dd>' . $this->t('Smart Dates can be displayed using the <em>Plain</em> or the <em>Default</em> formatter. The <em>Plain</em> formatter displays the date in the <a href="http://en.wikipedia.org/wiki/ISO_8601">ISO 8601</a> format. If you choose the <em>Default</em> formatter, you can configure the format at a granular level by altering the display options to meet your needs.') . '</dd>';
        $output .= '</dl>';
        return $output;
    }
  }

  /**
   * Implements hook_entity_access().
   *
   * Prevent deletion of the default configuration.
   */
  #[Hook('entity_access')]
  public static function entityAccess($entity, $operation, $account) {
    $info = $entity->getEntityType();
    if ($info->id() == 'smart_date_format') {
      if ($entity->id() == 'default' && $operation == 'delete') {
        return AccessResult::forbidden();
      }
      else {
        return AccessResult::neutral();
      }
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   *
   * Prepopulate the smart date field with the date passed from query parameter.
   */
  #[Hook('form_node_form_alter')]
  public static function formNodeFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    // Event start date from query parameter.
    $start = \Drupal::request()->query->get('start');
    // Field name of the start date from query parameter.
    $start_field = \Drupal::request()->query->get('start_field');
    if (empty($start) || empty($start_field)) {
      // Incomplete data, nothing to do.
      return;
    }
    if (!isset($form[$start_field]['#attributes']['class']) || !is_array($form[$start_field]['#attributes']['class']) || !in_array('field--type-smartdate', $form[$start_field]['#attributes']['class'])) {
      // Not a Smart Date field.
      return;
    }
    $node = $form_state->getFormObject()->getEntity();
    if (!$node->isNew()) {
      // Only populate new nodes.
      return;
    }
    // This is a new node, so properly process Smart Date default times.
    $field_definition = $node->get($start_field)->first()->getFieldDefinition();
    $defaults = $field_definition->getDefaultValueLiteral();
    if (is_array($defaults)) {
      $defaults = array_pop($defaults);
    }
    if (strpos($start, ' ')) {
      // The datetime string shouldn't contain a space, so if there assume it
      // should be a +.
      $start = str_replace(' ', '+', $start);
    }
    $start_time = new DrupalDateTime($start);
    $end_time = NULL;
    $start_str_len = strlen($start);
    if ($start_str_len == 10) {
      // If only date supplied, populate time based on default setting.
      switch ($defaults['default_date_type']) {
        // Set start time based on what would be used as default.
        case 'next_hour':
          $current_time = new DrupalDateTime();
          $next_hour = $current_time->format('H');
          if ($current_time->format('i') != 0) {
            $next_hour++;
          }
          $start_time->setTime($next_hour, 0);
          break;

        case 'now':
          $current_time = new DrupalDateTime();
          $start_time->setTime($current_time->format('H'), $current_time->format('i'));
          break;

        case 'relative':
          $relative_time = DrupalDateTime::createFromTimestamp(strtotime($defaults['default_date']));
          $start_time->setTime($relative_time->format('H'), $relative_time->format('i'));
          break;

        default:
          // No default set, so assume it should be all day.
          $end_time = clone $start_time;
          $end_time->setTime(23, 59);
      }
    }
    if (!$end_time) {
      // Offset the end time based on default duration.
      $end_time = clone $start_time;
      $end_time->modify('+' . $defaults['default_duration'] . ' minutes');
    }
    if (isset($form[$start_field]['widget'][0]['time_wrapper'])) {
      $form[$start_field]['widget'][0]['time_wrapper']['value']['#default_value'] = $start_time;
      $form[$start_field]['widget'][0]['time_wrapper']['end_value']['#default_value'] = $end_time;
    }
    else {
      $form[$start_field]['widget'][0]['value']['#default_value'] = $start_time;
      $form[$start_field]['widget'][0]['end_value']['#default_value'] = $end_time;
    }
  }

  /**
   * Implements hook_views_plugins_filter_alter().
   *
   * Replace the core date views filter with a version supporting granularity.
   */
  #[Hook('views_plugins_filter_alter')]
  public static function viewsPluginsFilterAlter(array &$plugins) {
    if (isset($plugins['date'])) {
      $plugins['date']['class'] = 'Drupal\smart_date\Plugin\views\filter\Date';
      $plugins['date']['provider'] = 'smart_date';
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public static function configSchemaInfoAlter(&$definitions) {
    if (isset($definitions['views.filter_value.date'])) {
      $definitions['views.filter_value.date']['mapping']['granularity'] = [
        'type' => 'string',
        'label' => 'Granularity',
      ];
    }
  }

  /**
   * Implements hook_views_plugins_argument_alter().
   *
   * Replace the core date views argument with a version with more options.
   */
  #[Hook('views_plugins_argument_alter')]
  public static function viewsPluginsArgumentAlter(array &$plugins) {
    $plugins['date']['class'] = 'Drupal\smart_date\Plugin\views\argument\Date';
  }

}
