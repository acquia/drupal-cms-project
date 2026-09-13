<?php

namespace Drupal\custom_field_viewfield\Hook;

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
    $definitions['custom_field.field_settings.*']['mapping'] += [
      'allowed_views' => [
        'type'  => 'sequence',
        'label' => t('Views'),
        'sequence' => [
          'type' => 'sequence',
          'label' => t('View'),
          'sequence' => [
            'type' => 'string',
            'label' => t('View display'),
          ],
        ],
      ],
      'force_default' => [
        'type' => 'boolean',
        'label' => t('Always use default value'),
      ],
      'token_browser' => [
        'type' => 'custom_field.token_browser',
      ],
    ];
    $definitions['custom_field.formatter_settings']['mapping']['always_build_output'] = [
      'type' => 'boolean',
      'label' => t('Always build output'),
    ];
  }

}
