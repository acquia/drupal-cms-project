<?php

namespace Drupal\custom_field_linkit\Hook;

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
    // Widget settings.
    $definitions['custom_field.field.*']['mapping'] += [
      'linkit_profile' => [
        'type'  => 'string',
        'label' => t('Linkit profile'),
      ],
      'linkit_auto_link_text' => [
        'type'  => 'boolean',
        'label' => t('Automatically populate link text from entity label'),
      ],
    ];
    // Formatter settings.
    $definitions['custom_field.formatter_settings']['mapping'] += [
      'linkit_profile' => [
        'type'  => 'string',
        'label' => t('Linkit profile'),
      ],
    ];
  }

}
