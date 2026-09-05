<?php

namespace Drupal\custom_field_entity_browser\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Provides hooks related to config schemas.
 */
class ConfigSchemaHooks {

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    $definitions['custom_field.field.*']['mapping'] += [
      'entity_browser' => [
        'type'  => 'string',
        'label' => t('Entity browser'),
      ],
      'field_widget_display' => [
        'type'  => 'string',
        'label' => t('Field widget display'),
      ],
      'field_widget_edit' => [
        'type'  => 'boolean',
        'label' => t('Field widget edit'),
      ],
      'field_widget_remove' => [
        'type'  => 'boolean',
        'label' => t('Field widget remove'),
      ],
      'field_widget_replace' => [
        'type'  => 'boolean',
        'label' => t('Field widget replace'),
      ],
      'open' => [
        'type'  => 'boolean',
        'label' => t('Open'),
      ],
      'field_widget_display_settings' => [
        'type' => 'entity_browser.field_widget_display.[%parent.field_widget_display]',
      ],
    ];
  }

}
