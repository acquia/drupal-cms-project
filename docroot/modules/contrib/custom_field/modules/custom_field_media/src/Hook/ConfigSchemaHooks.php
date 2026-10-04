<?php

namespace Drupal\custom_field_media\Hook;

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
    $definitions['custom_field.field.*']['mapping']['media_types'] = [
      'type'  => 'sequence',
      'label' => t('Allowed media types, in display order'),
      'sequence' => [
        'type' => 'string',
        'label' => t('Media type ID'),
      ],
    ];
  }

}
